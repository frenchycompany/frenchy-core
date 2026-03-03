<?php
/**
 * Synchronisation iCalendar — Page unifiée
 * Déclenche la synchronisation des réservations depuis Airbnb/Booking via iCal
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_bridge.php';

$messages = '';
$logements_sync = [];

// Handler du bouton "Synchroniser maintenant"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_all'])) {
    validateCsrfToken();
    $script = __DIR__ . '/../../../raspberry-pi/scripts/sync_reservations.php';
    $output = shell_exec('php ' . escapeshellarg($script) . ' 2>&1');
    $messages = $output ?: 'Synchronisation terminée (aucune sortie)';
}

// Récupérer les logements avec URL iCal configurée
try {
    $logements_sync = $pdo->query("
        SELECT id, nom_du_logement, ics_url, ics_url_2, actif
        FROM liste_logements
        WHERE ics_url IS NOT NULL AND ics_url != ''
        ORDER BY nom_du_logement
    ")->fetchAll();
} catch (PDOException $e) {
    $messages = "Erreur : " . $e->getMessage();
}

// Dernière synchronisation
$last_sync = null;
try {
    $stmt = $pdo->query("SELECT MAX(imported_at) as last_sync FROM ical_reservations");
    $result = $stmt->fetch();
    $last_sync = $result['last_sync'] ?? null;
} catch (PDOException $e) { /* table peut ne pas exister */ }

// Statistiques par logement
$stats_par_logement = [];
try {
    $stats_par_logement = $pdo->query("
        SELECT l.id, l.nom_du_logement,
               COUNT(r.id) as nb_reservations,
               SUM(CASE WHEN r.date_arrivee >= CURDATE() THEN 1 ELSE 0 END) as nb_futures
        FROM liste_logements l
        LEFT JOIN reservation r ON l.id = r.logement_id
        WHERE l.ics_url IS NOT NULL AND l.ics_url != ''
        GROUP BY l.id
    ")->fetchAll(PDO::FETCH_UNIQUE);
} catch (PDOException $e) { /* ignore */ }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sync iCal — FrenchyConciergerie</title>
</head>
<body>
<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-sync-alt text-primary"></i> Synchronisation iCalendar</h2>
            <p class="text-muted">
                <?= count($logements_sync) ?> logement(s) configuré(s) pour la sync iCal
                <?php if ($last_sync): ?>
                    — Dernière sync : <?= date('d/m/Y H:i', strtotime($last_sync)) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="col-auto">
            <form method="POST" action="" id="syncForm">
                <?php echoCsrfField(); ?>
                <button type="submit" name="sync_all" class="btn btn-primary" id="syncBtn">
                    <i class="fas fa-sync-alt"></i> Synchroniser maintenant
                </button>
            </form>
        </div>
    </div>

    <?php if (!empty($messages)): ?>
        <div class="alert alert-info"><pre class="mb-0"><?= htmlspecialchars($messages) ?></pre></div>
    <?php endif; ?>

    <!-- Tableau des logements configurés -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-home"></i> Logements avec sync iCal</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Logement</th>
                            <th>URL iCal principale</th>
                            <th>URL iCal secondaire</th>
                            <th>Réservations</th>
                            <th>Futures</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logements_sync as $l): ?>
                        <?php $s = $stats_par_logement[$l['id']] ?? ['nb_reservations' => 0, 'nb_futures' => 0]; ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($l['nom_du_logement']) ?></strong>
                                <?php if (empty($l['actif'])): ?>
                                    <span class="badge bg-secondary">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-truncate d-block" style="max-width: 300px;" title="<?= htmlspecialchars($l['ics_url']) ?>">
                                    <?= htmlspecialchars(substr($l['ics_url'], 0, 60)) ?>...
                                </small>
                            </td>
                            <td>
                                <?php if (!empty($l['ics_url_2'])): ?>
                                    <span class="badge bg-info">Configuré</span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-primary"><?= $s['nb_reservations'] ?? 0 ?></span></td>
                            <td><span class="badge bg-success"><?= $s['nb_futures'] ?? 0 ?></span></td>
                            <td><span class="badge bg-success"><i class="fas fa-check"></i> Configuré</span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logements_sync)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">
                            Aucun logement configuré pour la sync iCal.
                            <a href="logements.php">Configurer les URLs iCal</a>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Info -->
    <div class="card mt-4">
        <div class="card-body">
            <h6><i class="fas fa-info-circle text-info"></i> Comment ça marche</h6>
            <ul class="mb-0">
                <li>Les URLs iCal se configurent dans la <a href="logements.php">gestion des logements</a></li>
                <li>La synchronisation automatique tourne toutes les heures via cron</li>
                <li>Les réservations sont importées depuis Airbnb, Booking.com et autres plateformes</li>
                <li>Le planning des ménages est automatiquement mis à jour après chaque sync</li>
            </ul>
        </div>
    </div>
</div>

</body>
</html>
