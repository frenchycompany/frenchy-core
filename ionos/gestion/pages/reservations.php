<?php
// pages/reservations.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../config.php';
include '../pages/menu.php';

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit;
}

// Filtres de période
$dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-01');
$dateFin   = isset($_GET['date_fin'])   ? $_GET['date_fin']   : date('Y-m-t');
$logementFilter = isset($_GET['logement_id']) ? (int)$_GET['logement_id'] : 0;

// Connexion REMOTE (Raspberry Pi) pour les réservations
$remoteHost = '109.219.194.30';
$remotePort = 3306;
$remoteDb   = 'sms_db';
$remoteUser = 'remote';
$remotePass = 'remoteionos25';
$remoteOk   = false;

try {
    $pdoRemote = new PDO(
        "mysql:host={$remoteHost};port={$remotePort};dbname={$remoteDb};charset=utf8mb4",
        $remoteUser,
        $remotePass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $remoteOk = true;
} catch (Throwable $e) {
    $remoteError = $e->getMessage();
}

// Récupérer la liste des logements (locale)
$logements = $conn->query("SELECT id, nom_du_logement FROM liste_logements ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);

$stats = [];
$statsGlobales = [];
$reservationsListe = [];
$statsPlateforme = [];
$statsParMois = [];
$prochainesArrivees = [];
$prochainsDepartures = [];

if ($remoteOk) {
    try {
        // Clause filtre logement
        $logWhere = $logementFilter > 0 ? " AND r.logement_id = :logement_id " : "";
        $logParams = $logementFilter > 0 ? [':logement_id' => $logementFilter] : [];

        // =============================================================
        // 1) STATISTIQUES GLOBALES sur la période
        // =============================================================
        $sqlGlobal = "
            SELECT
                COUNT(*) AS total_reservations,
                SUM(CASE WHEN r.statut = 'confirmée' THEN 1 ELSE 0 END) AS confirmees,
                SUM(CASE WHEN r.statut = 'annulée' THEN 1 ELSE 0 END) AS annulees,
                SUM(GREATEST(DATEDIFF(r.date_depart, r.date_arrivee), 0)) AS total_nuits,
                ROUND(AVG(GREATEST(DATEDIFF(r.date_depart, r.date_arrivee), 0)), 1) AS duree_moyenne,
                SUM(COALESCE(r.nb_adultes,0) + COALESCE(r.nb_enfants,0) + COALESCE(r.nb_bebes,0)) AS total_voyageurs,
                ROUND(AVG(COALESCE(r.nb_adultes,0) + COALESCE(r.nb_enfants,0) + COALESCE(r.nb_bebes,0)), 1) AS moy_voyageurs
            FROM reservation r
            WHERE r.date_arrivee <= :date_fin
              AND r.date_depart >= :date_debut
              {$logWhere}
        ";
        $stGlobal = $pdoRemote->prepare($sqlGlobal);
        $stGlobal->execute(array_merge([':date_debut' => $dateDebut, ':date_fin' => $dateFin], $logParams));
        $statsGlobales = $stGlobal->fetch(PDO::FETCH_ASSOC);

        // =============================================================
        // 2) STATS PAR LOGEMENT
        // =============================================================
        $sqlParLogement = "
            SELECT
                r.logement_id,
                l.nom_du_logement AS logement,
                COUNT(*) AS nb_reservations,
                SUM(CASE WHEN r.statut = 'confirmée' THEN 1 ELSE 0 END) AS confirmees,
                SUM(CASE WHEN r.statut = 'annulée' THEN 1 ELSE 0 END) AS annulees,
                SUM(GREATEST(DATEDIFF(r.date_depart, r.date_arrivee), 0)) AS total_nuits,
                ROUND(AVG(GREATEST(DATEDIFF(r.date_depart, r.date_arrivee), 0)), 1) AS duree_moyenne,
                SUM(COALESCE(r.nb_adultes,0) + COALESCE(r.nb_enfants,0) + COALESCE(r.nb_bebes,0)) AS total_voyageurs,
                ROUND(AVG(COALESCE(r.nb_adultes,0) + COALESCE(r.nb_enfants,0) + COALESCE(r.nb_bebes,0)), 1) AS moy_voyageurs
            FROM reservation r
            LEFT JOIN liste_logements l ON r.logement_id = l.id
            WHERE r.date_arrivee <= :date_fin
              AND r.date_depart >= :date_debut
              AND r.statut = 'confirmée'
              {$logWhere}
            GROUP BY r.logement_id, l.nom_du_logement
            ORDER BY nb_reservations DESC
        ";
        $stParLogement = $pdoRemote->prepare($sqlParLogement);
        $stParLogement->execute(array_merge([':date_debut' => $dateDebut, ':date_fin' => $dateFin], $logParams));
        $stats = $stParLogement->fetchAll(PDO::FETCH_ASSOC);

        // =============================================================
        // 3) STATS PAR PLATEFORME
        // =============================================================
        $sqlPlateforme = "
            SELECT
                COALESCE(NULLIF(TRIM(r.plateforme), ''), 'Non renseigné') AS plateforme,
                COUNT(*) AS nb_reservations,
                SUM(GREATEST(DATEDIFF(r.date_depart, r.date_arrivee), 0)) AS total_nuits
            FROM reservation r
            WHERE r.date_arrivee <= :date_fin
              AND r.date_depart >= :date_debut
              AND r.statut = 'confirmée'
              {$logWhere}
            GROUP BY plateforme
            ORDER BY nb_reservations DESC
        ";
        $stPlateforme = $pdoRemote->prepare($sqlPlateforme);
        $stPlateforme->execute(array_merge([':date_debut' => $dateDebut, ':date_fin' => $dateFin], $logParams));
        $statsPlateforme = $stPlateforme->fetchAll(PDO::FETCH_ASSOC);

        // =============================================================
        // 4) EVOLUTION MENSUELLE (12 derniers mois)
        // =============================================================
        $sqlMois = "
            SELECT
                DATE_FORMAT(r.date_arrivee, '%Y-%m') AS mois,
                COUNT(*) AS nb_reservations,
                SUM(GREATEST(DATEDIFF(r.date_depart, r.date_arrivee), 0)) AS total_nuits
            FROM reservation r
            WHERE r.date_arrivee >= DATE_SUB(:date_fin, INTERVAL 12 MONTH)
              AND r.date_arrivee <= :date_fin2
              AND r.statut = 'confirmée'
              {$logWhere}
            GROUP BY DATE_FORMAT(r.date_arrivee, '%Y-%m')
            ORDER BY mois ASC
        ";
        $stMois = $pdoRemote->prepare($sqlMois);
        $stMois->execute(array_merge([':date_fin' => $dateFin, ':date_fin2' => $dateFin], $logParams));
        $statsParMois = $stMois->fetchAll(PDO::FETCH_ASSOC);

        // =============================================================
        // 5) PROCHAINES ARRIVEES (7 jours)
        // =============================================================
        $today = date('Y-m-d');
        $dans7j = date('Y-m-d', strtotime('+7 days'));
        $sqlArrivees = "
            SELECT
                r.id,
                r.prenom,
                r.nom,
                r.telephone,
                r.plateforme,
                l.nom_du_logement AS logement,
                r.date_arrivee,
                r.heure_arrivee,
                r.date_depart,
                (COALESCE(r.nb_adultes,0) + COALESCE(r.nb_enfants,0) + COALESCE(r.nb_bebes,0)) AS nb_voyageurs,
                GREATEST(DATEDIFF(r.date_depart, r.date_arrivee), 0) AS nb_nuits
            FROM reservation r
            LEFT JOIN liste_logements l ON r.logement_id = l.id
            WHERE r.date_arrivee BETWEEN :today AND :dans7j
              AND r.statut = 'confirmée'
            ORDER BY r.date_arrivee ASC
            LIMIT 20
        ";
        $stArr = $pdoRemote->prepare($sqlArrivees);
        $stArr->execute([':today' => $today, ':dans7j' => $dans7j]);
        $prochainesArrivees = $stArr->fetchAll(PDO::FETCH_ASSOC);

        // =============================================================
        // 6) PROCHAINS DEPARTS (7 jours)
        // =============================================================
        $sqlDeparts = "
            SELECT
                r.id,
                r.prenom,
                r.nom,
                r.telephone,
                l.nom_du_logement AS logement,
                r.date_depart,
                r.heure_depart
            FROM reservation r
            LEFT JOIN liste_logements l ON r.logement_id = l.id
            WHERE r.date_depart BETWEEN :today AND :dans7j
              AND r.statut = 'confirmée'
            ORDER BY r.date_depart ASC
            LIMIT 20
        ";
        $stDep = $pdoRemote->prepare($sqlDeparts);
        $stDep->execute([':today' => $today, ':dans7j' => $dans7j]);
        $prochainsDepartures = $stDep->fetchAll(PDO::FETCH_ASSOC);

        // =============================================================
        // 7) LISTE DES RESERVATIONS (paginée)
        // =============================================================
        $limit = 20;
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $offset = ($page - 1) * $limit;

        $sqlCount = "
            SELECT COUNT(*)
            FROM reservation r
            WHERE r.date_arrivee <= :date_fin
              AND r.date_depart >= :date_debut
              {$logWhere}
        ";
        $stCount = $pdoRemote->prepare($sqlCount);
        $stCount->execute(array_merge([':date_debut' => $dateDebut, ':date_fin' => $dateFin], $logParams));
        $totalReservations = $stCount->fetchColumn();
        $totalPages = ceil($totalReservations / $limit);

        $sqlListe = "
            SELECT
                r.id,
                r.reference,
                r.prenom,
                r.nom,
                r.telephone,
                r.email,
                r.plateforme,
                l.nom_du_logement AS logement,
                r.date_arrivee,
                r.heure_arrivee,
                r.date_depart,
                r.heure_depart,
                r.statut,
                r.nb_adultes,
                r.nb_enfants,
                r.nb_bebes,
                GREATEST(DATEDIFF(r.date_depart, r.date_arrivee), 0) AS nb_nuits,
                r.created_at
            FROM reservation r
            LEFT JOIN liste_logements l ON r.logement_id = l.id
            WHERE r.date_arrivee <= :date_fin
              AND r.date_depart >= :date_debut
              {$logWhere}
            ORDER BY r.date_arrivee DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stListe = $pdoRemote->prepare($sqlListe);
        $stListe->execute(array_merge([':date_debut' => $dateDebut, ':date_fin' => $dateFin], $logParams));
        $reservationsListe = $stListe->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $remoteOk = false;
        $remoteError = $e->getMessage();
    }
}

// Calcul du taux d'occupation par logement (période sélectionnée)
$totalJoursPeriode = max(1, (new DateTime($dateDebut))->diff(new DateTime($dateFin))->days + 1);

// Noms des mois en français
$moisFr = [
    '01' => 'Jan', '02' => 'Fév', '03' => 'Mar', '04' => 'Avr',
    '05' => 'Mai', '06' => 'Juin', '07' => 'Juil', '08' => 'Août',
    '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Déc'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques Réservations</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .stat-card {
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            color: #fff;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card .stat-value { font-size: 2.2rem; font-weight: 700; }
        .stat-card .stat-label { font-size: 0.85rem; opacity: 0.9; margin-top: 4px; }
        .bg-gradient-primary { background: linear-gradient(135deg, #667eea, #764ba2); }
        .bg-gradient-success { background: linear-gradient(135deg, #11998e, #38ef7d); }
        .bg-gradient-info { background: linear-gradient(135deg, #2193b0, #6dd5ed); }
        .bg-gradient-warning { background: linear-gradient(135deg, #f7971e, #ffd200); }
        .bg-gradient-danger { background: linear-gradient(135deg, #eb3349, #f45c43); }
        .bg-gradient-dark { background: linear-gradient(135deg, #434343, #000000); }
        .chart-container { position: relative; height: 300px; }
        .table-reservations th { white-space: nowrap; }
        .badge-plateforme { font-size: 0.75rem; }
    </style>
</head>
<body>

<div class="container mt-4">
    <h2>Statistiques des Réservations</h2>

    <?php if (!$remoteOk): ?>
        <div class="alert alert-danger">
            Connexion à la base de données distante impossible. Les statistiques de réservations ne sont pas disponibles.
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <br><small>Erreur : <?= htmlspecialchars($remoteError ?? 'Inconnue') ?></small>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Filtres -->
    <form method="GET" class="mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="date_debut" class="form-label">Date de début :</label>
                <input type="date" id="date_debut" name="date_debut" class="form-control" value="<?= htmlspecialchars($dateDebut) ?>" required>
            </div>
            <div class="col-md-3">
                <label for="date_fin" class="form-label">Date de fin :</label>
                <input type="date" id="date_fin" name="date_fin" class="form-control" value="<?= htmlspecialchars($dateFin) ?>" required>
            </div>
            <div class="col-md-3">
                <label for="logement_id" class="form-label">Logement :</label>
                <select id="logement_id" name="logement_id" class="form-control">
                    <option value="0">Tous les logements</option>
                    <?php foreach ($logements as $log): ?>
                        <option value="<?= $log['id'] ?>" <?= $logementFilter == $log['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($log['nom_du_logement']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
            </div>
        </div>
    </form>

    <?php if ($remoteOk): ?>

    <!-- Cartes résumé -->
    <div class="row g-3 mb-4">
        <div class="col-md-2 col-6">
            <div class="stat-card bg-gradient-primary">
                <div class="stat-value"><?= (int)($statsGlobales['total_reservations'] ?? 0) ?></div>
                <div class="stat-label">Réservations</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card bg-gradient-success">
                <div class="stat-value"><?= (int)($statsGlobales['confirmees'] ?? 0) ?></div>
                <div class="stat-label">Confirmées</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card bg-gradient-danger">
                <div class="stat-value"><?= (int)($statsGlobales['annulees'] ?? 0) ?></div>
                <div class="stat-label">Annulées</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card bg-gradient-info">
                <div class="stat-value"><?= (int)($statsGlobales['total_nuits'] ?? 0) ?></div>
                <div class="stat-label">Nuitées totales</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card bg-gradient-warning">
                <div class="stat-value"><?= ($statsGlobales['duree_moyenne'] ?? 0) ?></div>
                <div class="stat-label">Durée moy. (nuits)</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card bg-gradient-dark">
                <div class="stat-value"><?= (int)($statsGlobales['total_voyageurs'] ?? 0) ?></div>
                <div class="stat-label">Voyageurs</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Graphique évolution mensuelle -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><strong>Evolution mensuelle des réservations</strong></div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="chartMensuel"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphique répartition par plateforme -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><strong>Répartition par plateforme</strong></div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="chartPlateforme"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau stats par logement -->
    <div class="card mb-4">
        <div class="card-header"><strong>Statistiques par logement</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Logement</th>
                            <th class="text-center">Réservations</th>
                            <th class="text-center">Confirmées</th>
                            <th class="text-center">Annulées</th>
                            <th class="text-center">Nuitées</th>
                            <th class="text-center">Durée moy.</th>
                            <th class="text-center">Voyageurs</th>
                            <th class="text-center">Moy. voyageurs</th>
                            <th class="text-center">Taux occupation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stats)): ?>
                            <tr><td colspan="9" class="text-center text-muted">Aucune donnée pour cette période.</td></tr>
                        <?php else: ?>
                            <?php foreach ($stats as $s): ?>
                                <?php $tauxOccupation = $totalJoursPeriode > 0 ? round(($s['total_nuits'] / $totalJoursPeriode) * 100, 1) : 0; ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($s['logement'] ?? 'Logement #'.$s['logement_id']) ?></strong></td>
                                    <td class="text-center"><?= (int)$s['nb_reservations'] ?></td>
                                    <td class="text-center"><span class="badge bg-success"><?= (int)$s['confirmees'] ?></span></td>
                                    <td class="text-center"><span class="badge bg-danger"><?= (int)$s['annulees'] ?></span></td>
                                    <td class="text-center"><?= (int)$s['total_nuits'] ?></td>
                                    <td class="text-center"><?= $s['duree_moyenne'] ?> j</td>
                                    <td class="text-center"><?= (int)$s['total_voyageurs'] ?></td>
                                    <td class="text-center"><?= $s['moy_voyageurs'] ?></td>
                                    <td class="text-center">
                                        <div class="progress" style="height: 20px; min-width: 80px;">
                                            <div class="progress-bar <?= $tauxOccupation >= 70 ? 'bg-success' : ($tauxOccupation >= 40 ? 'bg-warning' : 'bg-danger') ?>"
                                                 role="progressbar"
                                                 style="width: <?= min(100, $tauxOccupation) ?>%"
                                                 title="<?= $tauxOccupation ?>%">
                                                <?= $tauxOccupation ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tableau par plateforme -->
    <div class="card mb-4">
        <div class="card-header"><strong>Statistiques par plateforme</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Plateforme</th>
                            <th class="text-center">Réservations</th>
                            <th class="text-center">Nuitées</th>
                            <th class="text-center">Part (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totalResaPlateforme = array_sum(array_column($statsPlateforme, 'nb_reservations'));
                        foreach ($statsPlateforme as $p):
                            $pct = $totalResaPlateforme > 0 ? round(($p['nb_reservations'] / $totalResaPlateforme) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['plateforme']) ?></strong></td>
                                <td class="text-center"><?= (int)$p['nb_reservations'] ?></td>
                                <td class="text-center"><?= (int)$p['total_nuits'] ?></td>
                                <td class="text-center"><?= $pct ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($statsPlateforme)): ?>
                            <tr><td colspan="4" class="text-center text-muted">Aucune donnée.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Prochaines arrivées et départs -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white"><strong>Prochaines arrivées (7 jours)</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Voyageur</th>
                                    <th>Logement</th>
                                    <th>Nuits</th>
                                    <th>Pers.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($prochainesArrivees)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">Aucune arrivée prévue.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($prochainesArrivees as $a): ?>
                                        <tr>
                                            <td><?= date('d/m', strtotime($a['date_arrivee'])) ?><?= $a['heure_arrivee'] ? ' '.$a['heure_arrivee'] : '' ?></td>
                                            <td><?= htmlspecialchars($a['prenom'].' '.$a['nom']) ?></td>
                                            <td><?= htmlspecialchars($a['logement'] ?? '-') ?></td>
                                            <td><?= (int)$a['nb_nuits'] ?></td>
                                            <td><?= (int)$a['nb_voyageurs'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-danger text-white"><strong>Prochains départs (7 jours)</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Voyageur</th>
                                    <th>Logement</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($prochainsDepartures)): ?>
                                    <tr><td colspan="3" class="text-center text-muted">Aucun départ prévu.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($prochainsDepartures as $d): ?>
                                        <tr>
                                            <td><?= date('d/m', strtotime($d['date_depart'])) ?><?= $d['heure_depart'] ? ' '.$d['heure_depart'] : '' ?></td>
                                            <td><?= htmlspecialchars($d['prenom'].' '.$d['nom']) ?></td>
                                            <td><?= htmlspecialchars($d['logement'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des réservations -->
    <div class="card mb-4">
        <div class="card-header"><strong>Liste des réservations (<?= (int)$totalReservations ?> au total)</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-reservations mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Réf.</th>
                            <th>Voyageur</th>
                            <th>Tél.</th>
                            <th>Plateforme</th>
                            <th>Logement</th>
                            <th>Arrivée</th>
                            <th>Départ</th>
                            <th>Nuits</th>
                            <th>Adultes</th>
                            <th>Enfants</th>
                            <th>Bébés</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reservationsListe)): ?>
                            <tr><td colspan="13" class="text-center text-muted">Aucune réservation pour cette période.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reservationsListe as $r): ?>
                                <tr>
                                    <td><?= (int)$r['id'] ?></td>
                                    <td><?= htmlspecialchars($r['reference'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($r['prenom'].' '.$r['nom']) ?></td>
                                    <td><?= htmlspecialchars($r['telephone'] ?? '-') ?></td>
                                    <td>
                                        <?php
                                        $pf = trim($r['plateforme'] ?? '');
                                        $pfClass = 'bg-secondary';
                                        if (stripos($pf, 'airbnb') !== false) $pfClass = 'bg-danger';
                                        elseif (stripos($pf, 'booking') !== false) $pfClass = 'bg-primary';
                                        elseif (stripos($pf, 'direct') !== false) $pfClass = 'bg-success';
                                        ?>
                                        <span class="badge <?= $pfClass ?> badge-plateforme"><?= htmlspecialchars($pf ?: '-') ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($r['logement'] ?? '-') ?></td>
                                    <td><?= date('d/m/Y', strtotime($r['date_arrivee'])) ?><?= $r['heure_arrivee'] ? '<br><small>'.$r['heure_arrivee'].'</small>' : '' ?></td>
                                    <td><?= date('d/m/Y', strtotime($r['date_depart'])) ?><?= $r['heure_depart'] ? '<br><small>'.$r['heure_depart'].'</small>' : '' ?></td>
                                    <td class="text-center"><?= (int)$r['nb_nuits'] ?></td>
                                    <td class="text-center"><?= (int)$r['nb_adultes'] ?></td>
                                    <td class="text-center"><?= (int)$r['nb_enfants'] ?></td>
                                    <td class="text-center"><?= (int)$r['nb_bebes'] ?></td>
                                    <td>
                                        <span class="badge <?= $r['statut'] === 'confirmée' ? 'bg-success' : 'bg-danger' ?>">
                                            <?= htmlspecialchars($r['statut']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if (($totalPages ?? 0) > 1): ?>
    <nav>
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                    <a class="page-link" href="?date_debut=<?= urlencode($dateDebut) ?>&date_fin=<?= urlencode($dateFin) ?>&logement_id=<?= $logementFilter ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <?php endif; /* remoteOk */ ?>
</div>

<!-- Charts JS -->
<?php if ($remoteOk): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Graphique mensuel
    <?php
    $labelsM = [];
    $dataResaM = [];
    $dataNuitsM = [];
    foreach ($statsParMois as $m) {
        $parts = explode('-', $m['mois']);
        $labelsM[] = ($moisFr[$parts[1]] ?? $parts[1]) . ' ' . $parts[0];
        $dataResaM[] = (int)$m['nb_reservations'];
        $dataNuitsM[] = (int)$m['total_nuits'];
    }
    ?>
    const ctxMensuel = document.getElementById('chartMensuel').getContext('2d');
    new Chart(ctxMensuel, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labelsM) ?>,
            datasets: [
                {
                    label: 'Réservations',
                    data: <?= json_encode($dataResaM) ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.7)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Nuitées',
                    data: <?= json_encode($dataNuitsM) ?>,
                    type: 'line',
                    borderColor: 'rgba(17, 153, 142, 1)',
                    backgroundColor: 'rgba(17, 153, 142, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left',
                    title: { display: true, text: 'Réservations' }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    title: { display: true, text: 'Nuitées' },
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });

    // Graphique plateforme (doughnut)
    <?php
    $labelsPf = array_column($statsPlateforme, 'plateforme');
    $dataPf = array_map('intval', array_column($statsPlateforme, 'nb_reservations'));
    $couleursPf = ['#eb3349','#2193b0','#11998e','#f7971e','#764ba2','#434343','#38ef7d','#ffd200'];
    ?>
    const ctxPf = document.getElementById('chartPlateforme').getContext('2d');
    new Chart(ctxPf, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($labelsPf) ?>,
            datasets: [{
                data: <?= json_encode($dataPf) ?>,
                backgroundColor: <?= json_encode(array_slice($couleursPf, 0, count($dataPf))) ?>,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 15, usePointStyle: true }
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
