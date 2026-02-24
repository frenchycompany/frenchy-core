<?php
/**
 * Taux d'occupation — Page unifiée
 * Vue calendaire de l'occupation des logements
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_bridge.php';

$mois = isset($_GET['mois']) ? (int)$_GET['mois'] : (int)date('m');
$annee = isset($_GET['annee']) ? (int)$_GET['annee'] : (int)date('Y');

$debut_mois = "$annee-" . str_pad($mois, 2, '0', STR_PAD_LEFT) . "-01";
$fin_mois = date('Y-m-t', strtotime($debut_mois));
$nb_jours = (int)date('t', strtotime($debut_mois));

// Logements actifs
$logements = [];
try {
    $logements = $pdo->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Réservations du mois
$reservations = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.logement_id, r.date_arrivee, r.date_depart, r.prenom, r.plateforme
        FROM reservation r
        WHERE r.date_arrivee <= ? AND r.date_depart >= ?
          AND r.statut != 'annulée'
        ORDER BY r.date_arrivee
    ");
    $stmt->execute([$fin_mois, $debut_mois]);
    foreach ($stmt->fetchAll() as $r) {
        $reservations[$r['logement_id']][] = $r;
    }
} catch (PDOException $e) { /* ignore */ }

// Calcul du taux d'occupation
$taux_global = 0;
$jours_occupes_total = 0;
$jours_total = count($logements) * $nb_jours;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Occupation — FrenchyConciergerie</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .cal-cell { width: 28px; height: 28px; text-align: center; font-size: 0.7rem; border: 1px solid #e5e7eb; }
        .cal-occupied { background: #10b981; color: white; }
        .cal-today { border: 2px solid #ef4444; }
        .cal-header { font-size: 0.7rem; font-weight: 600; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-chart-pie text-primary"></i> Taux d'occupation</h2>
            <p class="text-muted">
                <?php
                $mois_noms = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
                echo $mois_noms[$mois] . ' ' . $annee;
                ?> — <?= count($logements) ?> logement(s) actif(s)
            </p>
        </div>
        <div class="col-auto">
            <?php
            $mois_prec = $mois == 1 ? 12 : $mois - 1;
            $annee_prec = $mois == 1 ? $annee - 1 : $annee;
            $mois_suiv = $mois == 12 ? 1 : $mois + 1;
            $annee_suiv = $mois == 12 ? $annee + 1 : $annee;
            ?>
            <a href="?mois=<?= $mois_prec ?>&annee=<?= $annee_prec ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-chevron-left"></i></a>
            <a href="?mois=<?= (int)date('m') ?>&annee=<?= (int)date('Y') ?>" class="btn btn-outline-primary btn-sm">Aujourd'hui</a>
            <a href="?mois=<?= $mois_suiv ?>&annee=<?= $annee_suiv ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-chevron-right"></i></a>
        </div>
    </div>

    <!-- Calendrier d'occupation -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th style="min-width: 150px;">Logement</th>
                            <?php for ($j = 1; $j <= $nb_jours; $j++): ?>
                                <th class="cal-header text-center p-0" style="width: 28px;">
                                    <?= $j ?>
                                </th>
                            <?php endfor; ?>
                            <th class="text-center">Taux</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logements as $l):
                        $jours_occ = 0;
                    ?>
                        <tr>
                            <td><small><strong><?= htmlspecialchars($l['nom_du_logement']) ?></strong></small></td>
                            <?php for ($j = 1; $j <= $nb_jours; $j++):
                                $date_j = "$annee-" . str_pad($mois, 2, '0', STR_PAD_LEFT) . "-" . str_pad($j, 2, '0', STR_PAD_LEFT);
                                $is_today = ($date_j === date('Y-m-d'));
                                $occupied = false;
                                $guest = '';
                                foreach (($reservations[$l['id']] ?? []) as $r) {
                                    if ($date_j >= $r['date_arrivee'] && $date_j < $r['date_depart']) {
                                        $occupied = true;
                                        $guest = $r['prenom'] ?? '';
                                        break;
                                    }
                                }
                                if ($occupied) $jours_occ++;
                                $jours_occupes_total += $occupied ? 1 : 0;
                            ?>
                                <td class="cal-cell <?= $occupied ? 'cal-occupied' : '' ?> <?= $is_today ? 'cal-today' : '' ?>"
                                    title="<?= $date_j ?><?= $guest ? ' — ' . htmlspecialchars($guest) : '' ?>">
                                    <?= $occupied ? '<i class="fas fa-circle" style="font-size:6px;"></i>' : '' ?>
                                </td>
                            <?php endfor; ?>
                            <td class="text-center">
                                <?php $taux = $nb_jours > 0 ? round(($jours_occ / $nb_jours) * 100) : 0; ?>
                                <span class="badge bg-<?= $taux >= 70 ? 'success' : ($taux >= 40 ? 'warning' : 'danger') ?>">
                                    <?= $taux ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <strong>Taux global :</strong>
            <?php $taux_global = $jours_total > 0 ? round(($jours_occupes_total / $jours_total) * 100) : 0; ?>
            <span class="badge bg-<?= $taux_global >= 70 ? 'success' : ($taux_global >= 40 ? 'warning' : 'danger') ?> fs-6">
                <?= $taux_global ?>%
            </span>
            (<?= $jours_occupes_total ?> nuitées / <?= $jours_total ?> possibles)
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
