<?php
/**
 * Listing des réservations — Page unifiée
 * Intègre le module réservations du Raspberry Pi dans l'interface gestion
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_bridge.php';
require_once __DIR__ . '/../includes/rpi_db.php';

// Paramètres de filtrage
$filtre_logement = isset($_GET['logement']) && $_GET['logement'] !== '' ? (int)$_GET['logement'] : null;
$filtre_statut = isset($_GET['statut']) && $_GET['statut'] !== '' ? $_GET['statut'] : null;
$filtre_date_debut = isset($_GET['date_debut']) && $_GET['date_debut'] !== '' ? $_GET['date_debut'] : null;
$filtre_date_fin = isset($_GET['date_fin']) && $_GET['date_fin'] !== '' ? $_GET['date_fin'] : null;
$filtre_plateforme = isset($_GET['plateforme']) && $_GET['plateforme'] !== '' ? $_GET['plateforme'] : null;
$tri = $_GET['tri'] ?? 'date_desc';

// Récupérer les logements (VPS)
$logements = [];
$logementNames = [];
try {
    $logements = $pdo->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll();
    foreach ($logements as $l) { $logementNames[$l['id']] = $l['nom_du_logement']; }
} catch (PDOException $e) { /* ignore */ }

// Récupérer les plateformes (RPi)
$plateformes = [];
try {
    $pdoRpi = getRpiPdo();
    $plateformes = $pdoRpi->query("SELECT DISTINCT plateforme FROM reservation WHERE plateforme IS NOT NULL AND plateforme != '' ORDER BY plateforme")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { /* ignore */ }

// Construire la requête (RPi)
$sql = "
    SELECT r.*,
           DATEDIFF(r.date_depart, r.date_arrivee) as duree_sejour,
           DATEDIFF(r.date_arrivee, CURDATE()) as jours_avant_arrivee
    FROM reservation r
    WHERE 1=1
";
$params = [];

if ($filtre_logement !== null) {
    $sql .= " AND r.logement_id = :logement";
    $params[':logement'] = $filtre_logement;
}
if ($filtre_statut !== null) {
    $sql .= " AND r.statut = :statut";
    $params[':statut'] = $filtre_statut;
}
if ($filtre_date_debut !== null) {
    $sql .= " AND r.date_arrivee >= :date_debut";
    $params[':date_debut'] = $filtre_date_debut;
}
if ($filtre_date_fin !== null) {
    $sql .= " AND r.date_depart <= :date_fin";
    $params[':date_fin'] = $filtre_date_fin;
}
if ($filtre_plateforme !== null) {
    $sql .= " AND r.plateforme = :plateforme";
    $params[':plateforme'] = $filtre_plateforme;
}

switch ($tri) {
    case 'date_asc': $sql .= " ORDER BY r.date_arrivee ASC"; break;
    case 'logement': $sql .= " ORDER BY r.logement_id ASC, r.date_arrivee DESC"; break;
    default: $sql .= " ORDER BY r.date_arrivee DESC"; break;
}

$reservations = [];
$stats = ['total' => 0, 'en_cours' => 0, 'a_venir' => 0, 'passees' => 0];
try {
    $pdoRpi = getRpiPdo();
    $stmt = $pdoRpi->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll();
    // Enrichir avec noms de logements (VPS)
    foreach ($reservations as &$r) {
        $r['nom_du_logement'] = $logementNames[$r['logement_id'] ?? 0] ?? '';
    }
    unset($r);
    $stats['total'] = count($reservations);
    foreach ($reservations as $r) {
        if ($r['jours_avant_arrivee'] > 0) $stats['a_venir']++;
        elseif ($r['jours_avant_arrivee'] <= 0 && ($r['duree_sejour'] + $r['jours_avant_arrivee']) > 0) $stats['en_cours']++;
        else $stats['passees']++;
    }
} catch (PDOException $e) { /* ignore */ }

// ── Statistiques étendues (calculées depuis les résultats filtrés) ──
$statsExt = [
    'confirmees' => 0, 'annulees' => 0,
    'total_nuits' => 0, 'duree_moyenne' => 0,
    'total_adultes' => 0, 'total_enfants' => 0, 'total_bebes' => 0,
    'total_voyageurs' => 0, 'moy_voyageurs' => 0,
    'sms_start' => 0, 'sms_j1' => 0, 'sms_dep' => 0, 'sms_complet' => 0,
    'par_plateforme' => [], 'par_logement' => [], 'par_mois' => [], 'villes' => [],
];

foreach ($reservations as $r) {
    if (($r['statut'] ?? '') === 'confirmée') $statsExt['confirmees']++;
    elseif (($r['statut'] ?? '') === 'annulée') $statsExt['annulees']++;

    $duree = max(0, (int)$r['duree_sejour']);
    $statsExt['total_nuits'] += $duree;

    $adultes = (int)($r['nb_adultes'] ?? 0);
    $enfants = (int)($r['nb_enfants'] ?? 0);
    $bebes   = (int)($r['nb_bebes'] ?? 0);
    $statsExt['total_adultes'] += $adultes;
    $statsExt['total_enfants'] += $enfants;
    $statsExt['total_bebes']   += $bebes;

    if (!empty($r['start_sent'])) $statsExt['sms_start']++;
    if (!empty($r['j1_sent']))    $statsExt['sms_j1']++;
    if (!empty($r['dep_sent']))   $statsExt['sms_dep']++;
    if (!empty($r['start_sent']) && !empty($r['j1_sent']) && !empty($r['dep_sent'])) $statsExt['sms_complet']++;

    $pf = !empty($r['plateforme']) ? $r['plateforme'] : 'Inconnue';
    if (!isset($statsExt['par_plateforme'][$pf])) $statsExt['par_plateforme'][$pf] = ['count' => 0, 'nuits' => 0];
    $statsExt['par_plateforme'][$pf]['count']++;
    $statsExt['par_plateforme'][$pf]['nuits'] += $duree;

    $logNom = $r['nom_du_logement'] ?: 'N/A';
    if (!isset($statsExt['par_logement'][$logNom])) $statsExt['par_logement'][$logNom] = ['count' => 0, 'nuits' => 0, 'voyageurs' => 0];
    $statsExt['par_logement'][$logNom]['count']++;
    $statsExt['par_logement'][$logNom]['nuits'] += $duree;
    $statsExt['par_logement'][$logNom]['voyageurs'] += $adultes + $enfants + $bebes;

    $mois = date('Y-m', strtotime($r['date_arrivee']));
    if (!isset($statsExt['par_mois'][$mois])) $statsExt['par_mois'][$mois] = ['count' => 0, 'nuits' => 0];
    $statsExt['par_mois'][$mois]['count']++;
    $statsExt['par_mois'][$mois]['nuits'] += $duree;

    $ville = trim($r['ville'] ?? '');
    if (!empty($ville)) {
        if (!isset($statsExt['villes'][$ville])) $statsExt['villes'][$ville] = 0;
        $statsExt['villes'][$ville]++;
    }
}

$statsExt['total_voyageurs'] = $statsExt['total_adultes'] + $statsExt['total_enfants'] + $statsExt['total_bebes'];
$statsExt['duree_moyenne']   = $stats['total'] > 0 ? round($statsExt['total_nuits'] / $stats['total'], 1) : 0;
$statsExt['moy_voyageurs']   = $stats['total'] > 0 ? round($statsExt['total_voyageurs'] / $stats['total'], 1) : 0;
$statsExt['taux_sms']        = $stats['total'] > 0 ? round(($statsExt['sms_complet'] / $stats['total']) * 100) : 0;

arsort($statsExt['villes']);
$statsExt['villes'] = array_slice($statsExt['villes'], 0, 10, true);
ksort($statsExt['par_mois']);
uasort($statsExt['par_plateforme'], function($a, $b) { return $b['count'] - $a['count']; });
uasort($statsExt['par_logement'], function($a, $b) { return $b['count'] - $a['count']; });

$moisLabels = ['01'=>'Janvier','02'=>'Février','03'=>'Mars','04'=>'Avril','05'=>'Mai','06'=>'Juin',
               '07'=>'Juillet','08'=>'Août','09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Décembre'];

// ── Revenus depuis FC_revenus (VPS) ──
$revenus = ['revenu_brut' => 0, 'revenu_net' => 0, 'commission' => 0, 'nb_nuits_payees' => 0, 'taux_occupation_moy' => 0];
$hasRevenus = false;
try {
    $sqlRev = "SELECT SUM(revenu_brut) as total_brut, SUM(revenu_net) as total_net, SUM(commission) as total_commission, SUM(nb_nuits) as total_nuits, AVG(taux_occupation) as moy_occupation FROM FC_revenus";
    $paramsRev = [];
    $conditionsRev = [];
    if ($filtre_logement !== null) { $conditionsRev[] = "logement_id = :logement"; $paramsRev[':logement'] = $filtre_logement; }
    if ($filtre_date_debut !== null) { $conditionsRev[] = "mois >= :date_debut"; $paramsRev[':date_debut'] = $filtre_date_debut; }
    if ($filtre_date_fin !== null) { $conditionsRev[] = "mois <= :date_fin"; $paramsRev[':date_fin'] = $filtre_date_fin; }
    if (!empty($conditionsRev)) $sqlRev .= " WHERE " . implode(' AND ', $conditionsRev);
    $stmtRev = $pdo->prepare($sqlRev);
    $stmtRev->execute($paramsRev);
    $revRow = $stmtRev->fetch(PDO::FETCH_ASSOC);
    if ($revRow && $revRow['total_brut'] !== null) {
        $revenus['revenu_brut']        = (float)$revRow['total_brut'];
        $revenus['revenu_net']         = (float)$revRow['total_net'];
        $revenus['commission']         = (float)$revRow['total_commission'];
        $revenus['nb_nuits_payees']    = (int)$revRow['total_nuits'];
        $revenus['taux_occupation_moy'] = round((float)$revRow['moy_occupation'], 1);
        $hasRevenus = true;
    }
} catch (PDOException $e) { /* table may not exist yet */ }

// Revenus par logement
$revParLogement = [];
if ($hasRevenus) {
    try {
        $sqlRevLog = "SELECT r.logement_id, SUM(r.revenu_brut) as brut, SUM(r.revenu_net) as net, SUM(r.commission) as comm, SUM(r.nb_nuits) as nuits, AVG(r.taux_occupation) as occ FROM FC_revenus r";
        $paramsRevLog = [];
        $condRevLog = [];
        if ($filtre_logement !== null) { $condRevLog[] = "r.logement_id = :logement"; $paramsRevLog[':logement'] = $filtre_logement; }
        if ($filtre_date_debut !== null) { $condRevLog[] = "r.mois >= :date_debut"; $paramsRevLog[':date_debut'] = $filtre_date_debut; }
        if ($filtre_date_fin !== null) { $condRevLog[] = "r.mois <= :date_fin"; $paramsRevLog[':date_fin'] = $filtre_date_fin; }
        if (!empty($condRevLog)) $sqlRevLog .= " WHERE " . implode(' AND ', $condRevLog);
        $sqlRevLog .= " GROUP BY r.logement_id ORDER BY net DESC";
        $stmtRevLog = $pdo->prepare($sqlRevLog);
        $stmtRevLog->execute($paramsRevLog);
        $revParLogement = $stmtRevLog->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { /* ignore */ }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservations — FrenchyConciergerie</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stat-card { border-left: 4px solid; transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 16px rgba(0,0,0,0.1); }
        .stat-value { font-size: 2rem; font-weight: 700; }
        .stat-label { font-size: 0.85rem; }
        .stats-table th { font-size: 0.8rem; text-transform: uppercase; color: #6c757d; }
        .stats-table td { vertical-align: middle; }
        .progress-thin { height: 6px; }
        .tab-content .table { margin-bottom: 0; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <h2><i class="fas fa-calendar-check text-primary"></i> Réservations</h2>

    <!-- ══════ KPI Cards ══════ -->
    <div class="row mb-4 g-3">
        <div class="col-md-3 col-6">
            <div class="card stat-card h-100" style="border-left-color:#667eea;">
                <div class="card-body text-center py-3">
                    <i class="fas fa-calendar-alt fa-2x text-primary mb-2"></i>
                    <div class="stat-value text-primary"><?= $stats['total'] ?></div>
                    <div class="stat-label text-muted">Réservations</div>
                    <small class="text-muted">
                        <span class="text-success"><?= $stats['en_cours'] ?> en cours</span> &middot;
                        <span class="text-info"><?= $stats['a_venir'] ?> à venir</span> &middot;
                        <span class="text-secondary"><?= $stats['passees'] ?> passées</span>
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card h-100" style="border-left-color:#28a745;">
                <div class="card-body text-center py-3">
                    <i class="fas fa-moon fa-2x text-success mb-2"></i>
                    <div class="stat-value text-success"><?= number_format($statsExt['total_nuits']) ?></div>
                    <div class="stat-label text-muted">Nuitées totales</div>
                    <small class="text-muted">Moy. <?= $statsExt['duree_moyenne'] ?> nuits/séjour</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card h-100" style="border-left-color:#f39c12;">
                <div class="card-body text-center py-3">
                    <i class="fas fa-users fa-2x text-warning mb-2"></i>
                    <div class="stat-value text-warning"><?= number_format($statsExt['total_voyageurs']) ?></div>
                    <div class="stat-label text-muted">Voyageurs accueillis</div>
                    <small class="text-muted"><?= $statsExt['total_adultes'] ?> ad. &middot; <?= $statsExt['total_enfants'] ?> enf. &middot; <?= $statsExt['total_bebes'] ?> béb.</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card h-100" style="border-left-color:#17a2b8;">
                <div class="card-body text-center py-3">
                    <i class="fas fa-sms fa-2x text-info mb-2"></i>
                    <div class="stat-value text-info"><?= $statsExt['taux_sms'] ?>%</div>
                    <div class="stat-label text-muted">SMS complets envoyés</div>
                    <small class="text-muted"><?= $statsExt['sms_complet'] ?>/<?= $stats['total'] ?> avec les 3 SMS</small>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════ Tableau de statistiques détaillé (collapsible) ══════ -->
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#statsDetail" aria-expanded="false">
            <h6 class="mb-0"><i class="fas fa-chart-bar text-primary"></i> Statistiques détaillées</h6>
            <i class="fas fa-chevron-down text-muted"></i>
        </div>
        <div class="collapse" id="statsDetail">
            <div class="card-body p-0">
                <!-- Nav tabs -->
                <ul class="nav nav-tabs px-3 pt-3" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabPlateforme">Par plateforme</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabLogement">Par logement</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabMois">Par mois</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabSMS">SMS</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabVilles">Origines</a></li>
                    <?php if ($hasRevenus): ?>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabRevenus"><i class="fas fa-euro-sign"></i> Revenus</a></li>
                    <?php endif; ?>
                </ul>

                <div class="tab-content p-3">
                    <!-- Tab: Par plateforme -->
                    <div class="tab-pane fade show active" id="tabPlateforme">
                        <div class="table-responsive">
                            <table class="table table-sm stats-table">
                                <thead><tr><th>Plateforme</th><th class="text-end">Réservations</th><th class="text-end">%</th><th class="text-end">Nuitées</th><th class="text-end">Moy. durée</th><th style="min-width:120px">Répartition</th></tr></thead>
                                <tbody>
                                <?php foreach ($statsExt['par_plateforme'] as $pf => $d): ?>
                                    <?php $pct = $stats['total'] > 0 ? round(($d['count'] / $stats['total']) * 100, 1) : 0; ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($pf) ?></strong></td>
                                        <td class="text-end"><?= $d['count'] ?></td>
                                        <td class="text-end"><?= $pct ?>%</td>
                                        <td class="text-end"><?= $d['nuits'] ?></td>
                                        <td class="text-end"><?= $d['count'] > 0 ? round($d['nuits'] / $d['count'], 1) : 0 ?>n</td>
                                        <td>
                                            <div class="progress progress-thin">
                                                <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($statsExt['par_plateforme'])): ?>
                                    <tr><td colspan="6" class="text-center text-muted">Aucune donnée</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab: Par logement -->
                    <div class="tab-pane fade" id="tabLogement">
                        <div class="table-responsive">
                            <table class="table table-sm stats-table">
                                <thead><tr><th>Logement</th><th class="text-end">Réservations</th><th class="text-end">%</th><th class="text-end">Nuitées</th><th class="text-end">Moy. durée</th><th class="text-end">Voyageurs</th><th style="min-width:120px">Répartition</th></tr></thead>
                                <tbody>
                                <?php foreach ($statsExt['par_logement'] as $nom => $d): ?>
                                    <?php $pct = $stats['total'] > 0 ? round(($d['count'] / $stats['total']) * 100, 1) : 0; ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($nom) ?></strong></td>
                                        <td class="text-end"><?= $d['count'] ?></td>
                                        <td class="text-end"><?= $pct ?>%</td>
                                        <td class="text-end"><?= $d['nuits'] ?></td>
                                        <td class="text-end"><?= $d['count'] > 0 ? round($d['nuits'] / $d['count'], 1) : 0 ?>n</td>
                                        <td class="text-end"><?= $d['voyageurs'] ?></td>
                                        <td>
                                            <div class="progress progress-thin">
                                                <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($statsExt['par_logement'])): ?>
                                    <tr><td colspan="7" class="text-center text-muted">Aucune donnée</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab: Par mois -->
                    <div class="tab-pane fade" id="tabMois">
                        <div class="table-responsive">
                            <table class="table table-sm stats-table">
                                <thead><tr><th>Mois</th><th class="text-end">Réservations</th><th class="text-end">Nuitées</th><th class="text-end">Moy. durée</th><th style="min-width:150px">Volume</th></tr></thead>
                                <tbody>
                                <?php
                                $maxMoisCount = 1;
                                foreach ($statsExt['par_mois'] as $d) { if ($d['count'] > $maxMoisCount) $maxMoisCount = $d['count']; }
                                ?>
                                <?php foreach ($statsExt['par_mois'] as $moisKey => $d): ?>
                                    <?php
                                    $parts = explode('-', $moisKey);
                                    $moisNom = ($moisLabels[$parts[1]] ?? $parts[1]) . ' ' . $parts[0];
                                    $barPct = round(($d['count'] / $maxMoisCount) * 100);
                                    ?>
                                    <tr>
                                        <td><strong><?= $moisNom ?></strong></td>
                                        <td class="text-end"><?= $d['count'] ?></td>
                                        <td class="text-end"><?= $d['nuits'] ?></td>
                                        <td class="text-end"><?= $d['count'] > 0 ? round($d['nuits'] / $d['count'], 1) : 0 ?>n</td>
                                        <td>
                                            <div class="progress progress-thin">
                                                <div class="progress-bar bg-info" style="width:<?= $barPct ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($statsExt['par_mois'])): ?>
                                    <tr><td colspan="5" class="text-center text-muted">Aucune donnée</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab: SMS -->
                    <div class="tab-pane fade" id="tabSMS">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm stats-table">
                                    <thead><tr><th>Type de SMS</th><th class="text-end">Envoyés</th><th class="text-end">Taux</th><th style="min-width:120px">Couverture</th></tr></thead>
                                    <tbody>
                                    <?php
                                    $smsTypes = [
                                        ['Préparation (start)', $statsExt['sms_start'], 'bg-warning'],
                                        ['Check-in (J1)', $statsExt['sms_j1'], 'bg-success'],
                                        ['Check-out (départ)', $statsExt['sms_dep'], 'bg-info'],
                                        ['Cycle complet (3/3)', $statsExt['sms_complet'], 'bg-primary'],
                                    ];
                                    foreach ($smsTypes as $st):
                                        $taux = $stats['total'] > 0 ? round(($st[1] / $stats['total']) * 100) : 0;
                                    ?>
                                        <tr>
                                            <td><strong><?= $st[0] ?></strong></td>
                                            <td class="text-end"><?= $st[1] ?>/<?= $stats['total'] ?></td>
                                            <td class="text-end"><?= $taux ?>%</td>
                                            <td>
                                                <div class="progress progress-thin">
                                                    <div class="progress-bar <?= $st[2] ?>" style="width:<?= $taux ?>%"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5>Statut des réservations</h5>
                                        <table class="table table-sm mb-0">
                                            <tr><td>Confirmées</td><td class="text-end"><span class="badge bg-success"><?= $statsExt['confirmees'] ?></span></td></tr>
                                            <tr><td>Annulées</td><td class="text-end"><span class="badge bg-danger"><?= $statsExt['annulees'] ?></span></td></tr>
                                            <tr><td>Taux d'annulation</td><td class="text-end"><strong><?= $stats['total'] > 0 ? round(($statsExt['annulees'] / $stats['total']) * 100, 1) : 0 ?>%</strong></td></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Origines (villes) -->
                    <div class="tab-pane fade" id="tabVilles">
                        <div class="row">
                            <div class="col-md-8">
                                <table class="table table-sm stats-table">
                                    <thead><tr><th>Ville d'origine</th><th class="text-end">Réservations</th><th class="text-end">%</th><th style="min-width:120px">Répartition</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($statsExt['villes'] as $ville => $cnt): ?>
                                        <?php $pct = $stats['total'] > 0 ? round(($cnt / $stats['total']) * 100, 1) : 0; ?>
                                        <tr>
                                            <td><i class="fas fa-map-marker-alt text-muted me-1"></i> <strong><?= htmlspecialchars($ville) ?></strong></td>
                                            <td class="text-end"><?= $cnt ?></td>
                                            <td class="text-end"><?= $pct ?>%</td>
                                            <td>
                                                <div class="progress progress-thin">
                                                    <div class="progress-bar bg-warning" style="width:<?= $pct ?>%"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($statsExt['villes'])): ?>
                                        <tr><td colspan="4" class="text-center text-muted">Aucune donnée de ville</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6><i class="fas fa-info-circle text-info"></i> Résumé voyageurs</h6>
                                        <ul class="list-unstyled mb-0 small">
                                            <li>Moy. <?= $statsExt['moy_voyageurs'] ?> voyageurs/réservation</li>
                                            <li><?= $statsExt['total_adultes'] ?> adultes au total</li>
                                            <li><?= $statsExt['total_enfants'] ?> enfants au total</li>
                                            <li><?= $statsExt['total_bebes'] ?> bébés au total</li>
                                            <li><?= count($statsExt['villes']) ?> villes différentes (top 10)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($hasRevenus): ?>
                    <!-- Tab: Revenus -->
                    <div class="tab-pane fade" id="tabRevenus">
                        <div class="row mb-3 g-3">
                            <div class="col-md-3">
                                <div class="card border-success">
                                    <div class="card-body text-center py-2">
                                        <div class="stat-label text-muted">Revenu brut</div>
                                        <div class="fs-4 fw-bold text-success"><?= number_format($revenus['revenu_brut'], 2, ',', ' ') ?> &euro;</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-danger">
                                    <div class="card-body text-center py-2">
                                        <div class="stat-label text-muted">Commission</div>
                                        <div class="fs-4 fw-bold text-danger"><?= number_format($revenus['commission'], 2, ',', ' ') ?> &euro;</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-primary">
                                    <div class="card-body text-center py-2">
                                        <div class="stat-label text-muted">Revenu net</div>
                                        <div class="fs-4 fw-bold text-primary"><?= number_format($revenus['revenu_net'], 2, ',', ' ') ?> &euro;</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-info">
                                    <div class="card-body text-center py-2">
                                        <div class="stat-label text-muted">Taux occupation moy.</div>
                                        <div class="fs-4 fw-bold text-info"><?= $revenus['taux_occupation_moy'] ?>%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($revParLogement)): ?>
                        <table class="table table-sm stats-table">
                            <thead><tr><th>Logement</th><th class="text-end">Nuitées</th><th class="text-end">Revenu brut</th><th class="text-end">Commission</th><th class="text-end">Revenu net</th><th class="text-end">Occ. moy.</th></tr></thead>
                            <tbody>
                            <?php foreach ($revParLogement as $rl): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($logementNames[$rl['logement_id']] ?? 'ID '.$rl['logement_id']) ?></strong></td>
                                    <td class="text-end"><?= (int)$rl['nuits'] ?></td>
                                    <td class="text-end"><?= number_format((float)$rl['brut'], 2, ',', ' ') ?> &euro;</td>
                                    <td class="text-end text-danger"><?= number_format((float)$rl['comm'], 2, ',', ' ') ?> &euro;</td>
                                    <td class="text-end fw-bold"><?= number_format((float)$rl['net'], 2, ',', ' ') ?> &euro;</td>
                                    <td class="text-end"><?= round((float)$rl['occ'], 1) ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Logement</label>
                    <select name="logement" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        <?php foreach ($logements as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= $filtre_logement == $l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['nom_du_logement']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Plateforme</label>
                    <select name="plateforme" class="form-select form-select-sm">
                        <option value="">Toutes</option>
                        <?php foreach ($plateformes as $p): ?>
                            <option value="<?= htmlspecialchars($p) ?>" <?= $filtre_plateforme === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Du</label>
                    <input type="date" name="date_debut" class="form-control form-control-sm" value="<?= htmlspecialchars($filtre_date_debut ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Au</label>
                    <input type="date" name="date_fin" class="form-control form-control-sm" value="<?= htmlspecialchars($filtre_date_fin ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tri</label>
                    <select name="tri" class="form-select form-select-sm">
                        <option value="date_desc" <?= $tri === 'date_desc' ? 'selected' : '' ?>>Date (récent)</option>
                        <option value="date_asc" <?= $tri === 'date_asc' ? 'selected' : '' ?>>Date (ancien)</option>
                        <option value="logement" <?= $tri === 'logement' ? 'selected' : '' ?>>Par logement</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm me-2"><i class="fas fa-filter"></i> Filtrer</button>
                    <a href="reservations.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tableau -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Logement</th>
                            <th>Client</th>
                            <th>Téléphone</th>
                            <th>Arrivée</th>
                            <th>Départ</th>
                            <th>Durée</th>
                            <th>Plateforme</th>
                            <th>Statut</th>
                            <th>SMS</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($reservations as $r): ?>
                        <?php
                        $today = date('Y-m-d');
                        $isEnCours = ($r['date_arrivee'] <= $today && $r['date_depart'] >= $today);
                        $isAVenir = $r['date_arrivee'] > $today;
                        $rowClass = $isEnCours ? 'table-success' : ($isAVenir ? '' : 'text-muted');
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td>#<?= $r['id'] ?></td>
                            <td><strong><?= htmlspecialchars($r['nom_du_logement'] ?? 'N/A') ?></strong></td>
                            <td><?= htmlspecialchars(($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? '')) ?></td>
                            <td><small><?= htmlspecialchars($r['telephone'] ?? '') ?></small></td>
                            <td><?= date('d/m/Y', strtotime($r['date_arrivee'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($r['date_depart'])) ?></td>
                            <td><span class="badge bg-secondary"><?= $r['duree_sejour'] ?>n</span></td>
                            <td><small><?= htmlspecialchars($r['plateforme'] ?? '') ?></small></td>
                            <td>
                                <?php if (($r['statut'] ?? '') === 'confirmée'): ?>
                                    <span class="badge bg-success">Confirmée</span>
                                <?php elseif (($r['statut'] ?? '') === 'annulée'): ?>
                                    <span class="badge bg-danger">Annulée</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($r['statut'] ?? 'N/A') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= !empty($r['start_sent']) ? '<i class="fas fa-check text-success" title="Préparation envoyé"></i>' : '<i class="fas fa-minus text-muted"></i>' ?>
                                <?= !empty($r['j1_sent']) ? '<i class="fas fa-check text-success" title="Check-in envoyé"></i>' : '<i class="fas fa-minus text-muted"></i>' ?>
                                <?= !empty($r['dep_sent']) ? '<i class="fas fa-check text-success" title="Check-out envoyé"></i>' : '<i class="fas fa-minus text-muted"></i>' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($reservations)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">Aucune réservation trouvée.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
