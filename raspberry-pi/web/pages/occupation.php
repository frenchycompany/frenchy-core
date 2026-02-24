<?php
/**
 * Page de visualisation des taux d'occupation
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header_minimal.php';

if (!($pdo instanceof PDO)) {
    die('Erreur: PDO non disponible.');
}

// Periode d'analyse (par defaut 14 jours, configurable)
$periodes = [
    7 => '7 jours',
    14 => '14 jours',
    30 => '30 jours',
    60 => '60 jours',
    90 => '90 jours'
];
$jours = isset($_GET['jours']) ? intval($_GET['jours']) : 14;
if (!isset($periodes[$jours])) $jours = 14;

// Filtre actifs seulement
$actifsOnly = isset($_GET['actifs']) && $_GET['actifs'] == '1';

// Calculer le taux d'occupation pour chaque logement
function getOccupationData($pdo, $jours, $actifsOnly = false) {
    $data = [];

    // Recuperer les logements (filtre optionnel sur superhote_config.is_active)
    if ($actifsOnly) {
        $logements = $pdo->query("
            SELECT l.id, l.nom_du_logement, l.adresse
            FROM liste_logements l
            INNER JOIN superhote_config sc ON l.id = sc.logement_id AND sc.is_active = 1
            ORDER BY l.nom_du_logement
        ")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $logements = $pdo->query("
            SELECT l.id, l.nom_du_logement, l.adresse
            FROM liste_logements l
            ORDER BY l.nom_du_logement
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    foreach ($logements as $logement) {
        // Compter les jours occupes
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT d.date) as jours_occupes
            FROM (
                SELECT DATE_ADD(CURDATE(), INTERVAL n DAY) as date
                FROM (
                    SELECT a.N + b.N * 10 as n
                    FROM (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                          UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
                         (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3
                          UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
                ) numbers
                WHERE n < ?
            ) d
            INNER JOIN reservation r ON d.date >= r.date_arrivee AND d.date < r.date_depart
            WHERE r.logement_id = ? AND r.statut != 'annulee'
        ");
        $stmt->execute([$jours, $logement['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $joursOccupes = intval($result['jours_occupes'] ?? 0);
        $tauxOccupation = round(($joursOccupes / $jours) * 100, 1);

        // Recuperer les reservations a venir
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as nb_reservations
            FROM reservation
            WHERE logement_id = ?
              AND date_depart > CURDATE()
              AND date_arrivee < DATE_ADD(CURDATE(), INTERVAL ? DAY)
              AND statut != 'annulee'
        ");
        $stmt->execute([$logement['id'], $jours]);
        $nbReservations = $stmt->fetchColumn();

        $data[] = [
            'id' => $logement['id'],
            'nom' => $logement['nom_du_logement'],
            'adresse' => $logement['adresse'],
            'jours_occupes' => $joursOccupes,
            'jours_libres' => $jours - $joursOccupes,
            'taux' => $tauxOccupation,
            'nb_reservations' => $nbReservations
        ];
    }

    return $data;
}

// Recuperer les donnees
$occupationData = getOccupationData($pdo, $jours, $actifsOnly);

// Calculer les stats globales
$totalLogements = count($occupationData);
$tauxMoyen = $totalLogements > 0 ? round(array_sum(array_column($occupationData, 'taux')) / $totalLogements, 1) : 0;
$logementsVides = count(array_filter($occupationData, fn($l) => $l['taux'] == 0));
$logementsComplets = count(array_filter($occupationData, fn($l) => $l['taux'] >= 90));

// Trier par taux d'occupation (du plus faible au plus eleve par defaut)
$tri = $_GET['tri'] ?? 'taux_asc';
usort($occupationData, function($a, $b) use ($tri) {
    switch ($tri) {
        case 'taux_desc': return $b['taux'] <=> $a['taux'];
        case 'taux_asc': return $a['taux'] <=> $b['taux'];
        case 'nom': return strcmp($a['nom'], $b['nom']);
        default: return $a['taux'] <=> $b['taux'];
    }
});
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1><i class="fas fa-chart-pie text-primary"></i> Taux d'occupation</h1>
            <p class="text-muted">Analyse de l'occupation sur les <?= $jours ?> prochains jours</p>
        </div>
    </div>

    <!-- Filtres -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body py-2">
                    <form method="GET" class="form-inline">
                        <label class="mr-2">Periode:</label>
                        <select name="jours" class="form-control form-control-sm mr-3" onchange="this.form.submit()">
                            <?php foreach ($periodes as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $jours == $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label class="mr-2 ml-3">Trier par:</label>
                        <select name="tri" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="taux_asc" <?= $tri == 'taux_asc' ? 'selected' : '' ?>>Occupation (croissant)</option>
                            <option value="taux_desc" <?= $tri == 'taux_desc' ? 'selected' : '' ?>>Occupation (decroissant)</option>
                            <option value="nom" <?= $tri == 'nom' ? 'selected' : '' ?>>Nom</option>
                        </select>

                        <div class="custom-control custom-checkbox ml-4">
                            <input type="checkbox" class="custom-control-input" name="actifs" value="1"
                                   id="actifsOnly" <?= $actifsOnly ? 'checked' : '' ?> onchange="this.form.submit()">
                            <label class="custom-control-label" for="actifsOnly">Actifs uniquement</label>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats globales -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= $tauxMoyen ?>%</h2>
                    <small>Taux moyen</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= $totalLogements ?></h2>
                    <small>Logements</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= $logementsVides ?></h2>
                    <small>Logements vides</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= $logementsComplets ?></h2>
                    <small>Quasi-complets (>90%)</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau des logements -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-home"></i> Detail par logement</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>Logement</th>
                            <th>Adresse</th>
                            <th class="text-center">Reservations</th>
                            <th class="text-center">Jours occupes</th>
                            <th class="text-center">Jours libres</th>
                            <th style="width: 30%">Taux d'occupation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($occupationData as $logement): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($logement['nom']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($logement['adresse'] ?? '-') ?></td>
                            <td class="text-center">
                                <span class="badge badge-info"><?= $logement['nb_reservations'] ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-success"><?= $logement['jours_occupes'] ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-<?= $logement['jours_libres'] > $jours/2 ? 'danger' : 'warning' ?>">
                                    <?= $logement['jours_libres'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="progress flex-grow-1" style="height: 25px;">
                                        <?php
                                        $color = 'danger';
                                        if ($logement['taux'] >= 70) $color = 'success';
                                        elseif ($logement['taux'] >= 50) $color = 'info';
                                        elseif ($logement['taux'] >= 30) $color = 'warning';
                                        ?>
                                        <div class="progress-bar bg-<?= $color ?>"
                                             style="width: <?= $logement['taux'] ?>%;">
                                            <?= $logement['taux'] ?>%
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (empty($occupationData)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                <em>Aucun logement trouve</em>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Legende -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h6><i class="fas fa-info-circle"></i> Legende des couleurs</h6>
                    <div class="d-flex flex-wrap">
                        <span class="badge badge-danger mr-3 mb-2 p-2">0-29% : Critique</span>
                        <span class="badge badge-warning mr-3 mb-2 p-2">30-49% : Faible</span>
                        <span class="badge badge-info mr-3 mb-2 p-2">50-69% : Moyen</span>
                        <span class="badge badge-success mr-3 mb-2 p-2">70%+ : Bon</span>
                    </div>
                    <p class="text-muted mt-2 mb-0">
                        <small>
                            <i class="fas fa-lightbulb"></i>
                            Les logements avec un faible taux d'occupation beneficient automatiquement d'une reduction
                            de prix si l'option "Regles d'occupation" est activee dans le Yield Management.
                        </small>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
