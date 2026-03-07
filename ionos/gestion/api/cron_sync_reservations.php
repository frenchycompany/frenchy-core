<?php
/**
 * API endpoint pour la synchronisation des réservations ICS (appelé par cron RPi via curl)
 *
 * Synchronise les réservations depuis les flux iCalendar (Airbnb, Booking)
 * vers la base de données VPS.
 *
 * Authentification par token CRON_SECRET dans le header Authorization.
 *
 * Usage depuis le RPi :
 *   curl -s -H "Authorization: Bearer <CRON_SECRET>" \
 *        "https://gestion.frenchyconciergerie.fr/api/cron_sync_reservations.php"
 */

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Paris');

// Charger l'environnement et la BDD
require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../db/connection.php';

// Autoload Sabre\VObject
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Composer autoload manquant. Exécuter: cd ionos/gestion && composer install']);
    exit;
}
require_once $autoloadPath;

use Sabre\VObject\Reader;

// --- Auth par token ---
$cronSecret = env('CRON_SECRET', '');
if (empty($cronSecret)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'CRON_SECRET non configuré sur le serveur']);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$token = '';
if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
    $token = $m[1];
}

if (!hash_equals($cronSecret, $token)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token invalide']);
    exit;
}

// --- Log ---
$logs = [];
function logMsg($message, $level = 'INFO') {
    global $logs;
    $logs[] = ['time' => date('H:i:s'), 'level' => $level, 'message' => $message];
}

// --- Synchronisation ---
logMsg("=== Début de la synchronisation des réservations ===");

try {
    $stmtLog = $pdo->query("
        SELECT id, nom_du_logement, ics_url
        FROM liste_logements
        WHERE ics_url IS NOT NULL AND ics_url <> ''
    ");
    $logements = $stmtLog->fetchAll();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur SQL logements: ' . $e->getMessage()]);
    exit;
}

if (count($logements) === 0) {
    logMsg("Aucun logement avec URL ICS configurée", 'WARNING');
    echo json_encode(['status' => 'success', 'message' => 'Aucun logement ICS', 'logs' => $logs]);
    exit;
}

logMsg("Logements trouvés : " . count($logements));

$totalNew = 0;
$totalUpd = 0;
$totalErrors = 0;

// Préparer les statements
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
        reference, logement_id, date_reservation, date_arrivee, date_depart,
        prenom, nom, plateforme, telephone, ville, statut
    ) VALUES (
        :reference, :logement_id, NOW(), :date_in, :date_out,
        :prenom, '', :plateforme, :telephone, :ville, 'confirmée'
    )
");

foreach ($logements as $lg) {
    $lid = (int)$lg['id'];
    logMsg("→ Logement #{$lid} «{$lg['nom_du_logement']}»");

    // Charger le flux ICS
    $ctx = stream_context_create(['http' => ['timeout' => 30]]);
    $content = @file_get_contents($lg['ics_url'], false, $ctx);
    if ($content === false) {
        logMsg("  URL ICS inaccessible : {$lg['ics_url']}", 'WARNING');
        $totalErrors++;
        continue;
    }

    // Parser le flux
    try {
        $vcal = Reader::read($content);
    } catch (\Throwable $e) {
        logMsg("  ICS invalide : " . $e->getMessage(), 'WARNING');
        $totalErrors++;
        continue;
    }

    $events = $vcal->select('VEVENT');
    logMsg("  Événements trouvés : " . count($events));

    foreach ($events as $evt) {
        $summary = trim((string)$evt->SUMMARY);
        $description = (string)$evt->DESCRIPTION;

        // Ignorer les "Blocked dates"
        if (stripos($summary, 'Blocked dates') === 0) {
            continue;
        }

        // Extraire prénom, plateforme et référence
        if (!preg_match(
            '/^(?<prenom>.+?)\s+-\s+(?<platform>.+?)\s+-\s+(?<ref>\d+)$/iu',
            $summary,
            $m
        )) {
            logMsg("    Ignoré (format inattendu) : {$summary}");
            continue;
        }

        $prenom = trim($m['prenom']);
        $plateforme = trim($m['platform']);
        $reference = trim($m['ref']);

        // Dates
        try {
            $dateIn = $evt->DTSTART->getDateTime()->format('Y-m-d');
            $dateOut = $evt->DTEND->getDateTime()->format('Y-m-d');
        } catch (\Throwable $e) {
            logMsg("    Ignoré (dates invalides) : {$summary}");
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

        // Upsert
        $stmtCheck->execute([':ref' => $reference]);
        $rid = $stmtCheck->fetchColumn();

        if ($rid) {
            $stmtUpd->execute([
                ':logement_id' => $lid,
                ':date_in' => $dateIn,
                ':date_out' => $dateOut,
                ':prenom' => $prenom,
                ':plateforme' => $plateforme,
                ':telephone' => $telephone,
                ':ville' => $ville,
                ':id' => (int)$rid,
            ]);
            $totalUpd++;
        } else {
            $stmtIns->execute([
                ':reference' => $reference,
                ':logement_id' => $lid,
                ':date_in' => $dateIn,
                ':date_out' => $dateOut,
                ':prenom' => $prenom,
                ':plateforme' => $plateforme,
                ':telephone' => $telephone,
                ':ville' => $ville,
            ]);
            $totalNew++;
        }
    }
}

logMsg("=== Résumé ===");
logMsg("Nouvelles insertions : $totalNew");
logMsg("Mises à jour : $totalUpd");
if ($totalErrors > 0) {
    logMsg("Erreurs : $totalErrors", 'WARNING');
}
logMsg("=== Synchronisation terminée ===");

echo json_encode([
    'status' => 'success',
    'new' => $totalNew,
    'updated' => $totalUpd,
    'errors' => $totalErrors,
    'logs' => $logs
]);
