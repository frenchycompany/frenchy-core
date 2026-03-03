#!/usr/bin/env php
<?php
/**
 * Script de synchronisation des réservations depuis les flux iCalendar
 * Peut être exécuté en ligne de commande ou via cron
 *
 * Usage: php sync_reservations.php
 */

// Charger l'autoloader de composer
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../web/includes/env_loader.php';

use Sabre\VObject\Reader;

// Configuration de la base de données
try {
    $pdo = new PDO(
        "mysql:host=" . env('DB_HOST') . ";dbname=" . env('DB_NAME') . ";charset=utf8mb4",
        env('DB_USER'),
        env('DB_PASSWORD'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    echo "❌ Erreur de connexion à la base de données : " . $e->getMessage() . "\n";
    exit(1);
}

// Chemin du fichier de log
$logFile = __DIR__ . '/../logs/sync_reservations.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

/**
 * Écrire dans le log et afficher à l'écran
 */
function logMessage($message, $toFile = true) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$message}\n";

    echo $line;

    if ($toFile) {
        file_put_contents($logFile, $line, FILE_APPEND);
    }
}

logMessage("=== Début de la synchronisation des réservations ===");

try {
    // Récupérer tous les logements ayant un ICS configuré
    $stmtLog = $pdo->query("
        SELECT id, nom_du_logement, ics_url
        FROM liste_logements
        WHERE ics_url IS NOT NULL AND ics_url <> ''
    ");
    $logements = $stmtLog->fetchAll();

    if (count($logements) === 0) {
        logMessage("⚠️  Aucun logement avec URL ICS configurée");
        exit(0);
    }

    logMessage("Logements trouvés : " . count($logements));

} catch (PDOException $e) {
    logMessage("❌ Erreur SQL logements : " . $e->getMessage());
    exit(1);
}

$totalNew = 0;
$totalUpd = 0;
$totalErrors = 0;

foreach ($logements as $lg) {
    $lid = (int)$lg['id'];
    logMessage("→ Logement #{$lid} «{$lg['nom_du_logement']}»");

    // Charger le flux ICS
    $content = @file_get_contents($lg['ics_url']);
    if ($content === false) {
        logMessage("  ⚠️  URL ICS inaccessible : {$lg['ics_url']}");
        $totalErrors++;
        continue;
    }

    // Parser et lister les VEVENT
    try {
        $vcal = Reader::read($content);
    } catch (\Throwable $e) {
        logMessage("  ⚠️  ICS invalide : " . $e->getMessage());
        $totalErrors++;
        continue;
    }

    $events = $vcal->select('VEVENT');
    logMessage("  Événements trouvés : " . count($events));

    // Préparer les statements réutilisables (perf)
    $stmtCheck = $pdo->prepare("SELECT id FROM reservation WHERE reference = :ref");
    $stmtUpd = $pdo->prepare("
        UPDATE reservation SET
            logement_id  = :logement_id,
            date_arrivee = :date_in,
            date_depart  = :date_out,
            prenom       = :prenom,
            nom          = '',
            plateforme   = :plateforme,
            telephone    = :telephone,
            ville        = :ville
        WHERE id = :id
    ");
    $stmtIns = $pdo->prepare("
        INSERT INTO reservation (
            reference,
            logement_id,
            date_reservation,
            date_arrivee,
            date_depart,
            prenom,
            nom,
            plateforme,
            telephone,
            ville,
            statut
        ) VALUES (
            :reference,
            :logement_id,
            NOW(),
            :date_in,
            :date_out,
            :prenom,
            '',
            :plateforme,
            :telephone,
            :ville,
            'confirmée'
        )
    ");

    foreach ($events as $evt) {
        $summary     = trim((string)$evt->SUMMARY);
        $description = (string)$evt->DESCRIPTION;

        // Ignorer les "Blocked dates"
        if (stripos($summary, 'Blocked dates') === 0) {
            continue;
        }

        // Extraire prénom, plateforme et référence
        // Format attendu: "Prénom - Plateforme - Référence"
        // Note: \s+-\s+ exige des espaces autour du séparateur "-"
        // pour ne pas confondre avec les prénoms composés (Lou-Ann, Jean-Pierre…)
        if (!preg_match(
            '/^(?<prenom>.+?)\s+-\s+(?<platform>.+?)\s+-\s+(?<ref>\d+)$/iu',
            $summary,
            $m
        )) {
            logMessage("    Ignoré (format inattendu) : {$summary}");
            continue;
        }

        $prenom     = trim($m['prenom']);
        $plateforme = trim($m['platform']);
        $reference  = trim($m['ref']);

        // Dates
        try {
            $dateIn  = $evt->DTSTART->getDateTime()->format('Y-m-d');
            $dateOut = $evt->DTEND->getDateTime()->format('Y-m-d');
        } catch (\Throwable $e) {
            logMessage("    Ignoré (dates invalides) : {$summary}");
            continue;
        }

        // Téléphone depuis DESCRIPTION
        $telephone = '';
        if (preg_match('/(\+?\d[\d\-\s\(\)]{7,}\d)/', $description, $pm)) {
            $telephone = preg_replace('/[^\d+]/', '', $pm[1]);
        }

        // Ville depuis DESCRIPTION
        $ville = '';
        if (preg_match('/\bCity\s*[:\-]\s*(?<city>[^\r\n]+)/i', $description, $cm)) {
            $ville = trim($cm['city']);
        }

        // Vérifier si la réservation existe déjà
        $stmtCheck->execute([':ref' => $reference]);
        $rid = $stmtCheck->fetchColumn(); // false si inexistant

        if ($rid) {
            // UPDATE
            $stmtUpd->execute([
                ':logement_id' => $lid,
                ':date_in'     => $dateIn,
                ':date_out'    => $dateOut,
                ':prenom'      => $prenom,
                ':plateforme'  => $plateforme,
                ':telephone'   => $telephone,
                ':ville'       => $ville,
                ':id'          => (int)$rid,
            ]);
            $totalUpd++;
            logMessage("    ↺ Mise à jour ref#{$reference} (ID={$rid})");
        } else {
            // INSERT
            $stmtIns->execute([
                ':reference'   => $reference,
                ':logement_id' => $lid,
                ':date_in'     => $dateIn,
                ':date_out'    => $dateOut,
                ':prenom'      => $prenom,
                ':plateforme'  => $plateforme,
                ':telephone'   => $telephone,
                ':ville'       => $ville,
            ]);
            $newId = (int)$pdo->lastInsertId();
            $totalNew++;
            logMessage("    ✓ Inséré ref#{$reference} (ID={$newId})");
        }
    }
}

// Résumé global
logMessage("=== Résumé ===");
logMessage("✓ Nouvelles insertions : {$totalNew}");
logMessage("↺ Mises à jour : {$totalUpd}");
if ($totalErrors > 0) {
    logMessage("⚠️  Erreurs : {$totalErrors}");
}
logMessage("=== Synchronisation terminée ===");

exit(0);
