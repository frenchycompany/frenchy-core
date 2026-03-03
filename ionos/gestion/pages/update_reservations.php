<?php
// web/pages/update_reservations.php (version PDO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../includes/db.php'; // Utilise $pdo (PDO)

use Sabre\VObject\Reader;

if (!($pdo instanceof PDO)) {
    die('Erreur: PDO non disponible. Vérifiez la connexion à la base de données.');
}

// Par défaut, tout en associatif
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$messages = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Récupérer tous les logements ayant un ICS configuré (PDO)
    try {
        $stmtLog = $pdo->query("
            SELECT id, nom_du_logement, ics_url
              FROM liste_logements
             WHERE ics_url IS NOT NULL AND ics_url <> ''
        ");
        $logements = $stmtLog->fetchAll();
    } catch (PDOException $e) {
        die("Erreur SQL logements : " . $e->getMessage());
    }

    $totalNew = 0;
    $totalUpd = 0;

    foreach ($logements as $lg) {
        $lid = (int)$lg['id'];
        $messages .= "→ Logement #{$lid} «{$lg['nom_du_logement']}»\n";

        // 2) Charger le flux ICS
        $content = @file_get_contents($lg['ics_url']);
        if ($content === false) {
            $messages .= "  ⚠️ URL ICS inaccessible\n\n";
            continue;
        }

        // 3) Parser et lister les VEVENT
        try {
            $vcal   = Reader::read($content);
        } catch (\Throwable $e) {
            $messages .= "  ⚠️ ICS invalide: " . $e->getMessage() . "\n\n";
            continue;
        }

        $events = $vcal->select('VEVENT');
        $messages .= "  Événements trouvés : " . count($events) . "\n";

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

            // Ignorer les “Blocked dates”
            if (stripos($summary, 'Blocked dates') === 0) {
                continue;
            }

            // Extraire prénom, plateforme et référence
            if (!preg_match(
                '/^(?<prenom>[^-]+)\s*-\s*(?<platform>[^-]+?)\s*-\s*(?<ref>\d+)$/iu',
                $summary,
                $m
            )) {
                $messages .= "    Ignoré (format inattendu) : {$summary}\n";
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
                $messages .= "    Ignoré (dates invalides) : {$summary}\n";
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

            // 4) Vérifier si la réservation existe déjà
            $stmtCheck->execute([':ref' => $reference]);
            $rid = $stmtCheck->fetchColumn(); // false si inexistant

            if ($rid) {
                // → UPDATE
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
                $messages .= "    ↺ Mise à jour ref#{$reference} (ID={$rid}) plateforme={$plateforme} tel={$telephone} ville={$ville}\n";
            } else {
                // → INSERT
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
                $messages .= "    ✓ Inséré ref#{$reference} (ID={$newId}) plateforme={$plateforme} tel={$telephone} ville={$ville}\n";
            }
        }

        $messages .= "\n";
    }

    // Résumé global
    $messages .= "=== Résumé ===\n";
    $messages .= "Nouvelles insertions : {$totalNew}\n";
    $messages .= "Mises à jour : {$totalUpd}\n";
}


?>

<div class="container mt-4">
  <!-- En-tête de la page -->
  <div class="text-center mb-5">
    <h1 class="display-4 text-gradient-primary">
      <i class="fas fa-sync-alt"></i> Mise à jour des réservations
    </h1>
    <p class="lead text-muted">Synchroniser les réservations depuis les flux iCalendar</p>
  </div>

  <!-- Bouton de lancement -->
  <div class="card shadow-custom mb-4">
    <div class="card-body text-center p-4">
      <form method="post">
        <button type="submit" class="btn btn-primary btn-lg px-5">
          <i class="fas fa-sync-alt"></i> Lancer la synchronisation
        </button>
      </form>
      <p class="text-muted mt-3 mb-0">
        <i class="fas fa-info-circle"></i> Cette action va télécharger les flux iCalendar et mettre à jour les réservations
      </p>
    </div>
  </div>

  <!-- Résultats de la synchronisation -->
  <?php if ($messages !== ''): ?>
    <div class="card shadow-custom">
      <div class="card-header bg-success text-white">
        <i class="fas fa-check-circle"></i> Résultats de la synchronisation
      </div>
      <div class="card-body">
        <pre class="bg-light p-3 border rounded mb-0" style="max-height: 600px; overflow-y: auto;"><?= htmlspecialchars($messages) ?></pre>
      </div>
    </div>
  <?php endif; ?>
</div>


