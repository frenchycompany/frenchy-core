<?php
// Listing complet et détaillé des réservations
require_once __DIR__ . '/../includes/error_handler.php';
// DB loaded via config.php
require_once __DIR__ . '/../includes/rpi_db.php';
$pdo = getRpiPdo();
// header loaded via menu.php

if (!($pdo instanceof PDO)) {
    die('Erreur: PDO non disponible. Vérifiez la connexion à la base de données.');
}

// Paramètres de filtrage
$filtre_logement = isset($_GET['logement']) && $_GET['logement'] !== '' ? (int)$_GET['logement'] : null;
$filtre_statut = isset($_GET['statut']) && $_GET['statut'] !== '' ? $_GET['statut'] : null;
$filtre_date_debut = isset($_GET['date_debut']) && $_GET['date_debut'] !== '' ? $_GET['date_debut'] : null;
$filtre_date_fin = isset($_GET['date_fin']) && $_GET['date_fin'] !== '' ? $_GET['date_fin'] : null;
$filtre_plateforme = isset($_GET['plateforme']) && $_GET['plateforme'] !== '' ? $_GET['plateforme'] : null;
$tri = isset($_GET['tri']) ? $_GET['tri'] : 'date_desc';
$vue = isset($_GET['vue']) ? $_GET['vue'] : 'logement'; // 'logement', 'liste', 'calendrier'

// Récupérer tous les logements depuis la table liste_logements
$logements = [];
try {
    $stmt = $pdo->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement");
    $logements = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignorer
}

// Récupérer les plateformes disponibles
$plateformes = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT plateforme FROM reservation WHERE plateforme IS NOT NULL AND plateforme != '' ORDER BY plateforme");
    $plateformes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Ignorer
}

// Construire la requête SQL avec filtres et LEFT JOIN
$sql = "
    SELECT
        r.*,
        l.nom_du_logement,
        DATEDIFF(r.date_depart, r.date_arrivee) as duree_sejour,
        DATEDIFF(r.date_arrivee, CURDATE()) as jours_avant_arrivee
    FROM reservation r
    LEFT JOIN liste_logements l ON r.logement_id = l.id
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

// Appliquer le tri
switch ($tri) {
    case 'date_asc':
        $sql .= " ORDER BY r.date_arrivee ASC, r.date_depart ASC";
        break;
    case 'date_desc':
        $sql .= " ORDER BY r.date_arrivee DESC, r.date_depart DESC";
        break;
    case 'logement':
        $sql .= " ORDER BY l.nom_du_logement ASC, r.date_arrivee DESC";
        break;
    case 'client':
        $sql .= " ORDER BY r.prenom ASC, r.nom ASC";
        break;
    default:
        $sql .= " ORDER BY r.date_arrivee DESC";
}

// Exécuter la requête
$reservations = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Grouper par logement si vue = logement
$reservations_par_logement = [];
if ($vue === 'logement') {
    foreach ($reservations as $res) {
        $logement_id = $res['logement_id'] ?? 0;
        $logement_nom = $res['nom_du_logement'] ?? 'Non assigné';

        if (!isset($reservations_par_logement[$logement_id])) {
            $reservations_par_logement[$logement_id] = [
                'nom' => $logement_nom,
                'reservations' => [],
                'stats' => [
                    'total' => 0,
                    'confirmees' => 0,
                    'annulees' => 0,
                    'nuits_total' => 0
                ]
            ];
        }

        $reservations_par_logement[$logement_id]['reservations'][] = $res;
        $reservations_par_logement[$logement_id]['stats']['total']++;

        if (strtolower($res['statut'] ?? '') === 'confirmée' || strtolower($res['statut'] ?? '') === 'confirmed') {
            $reservations_par_logement[$logement_id]['stats']['confirmees']++;
        }
        if (strtolower($res['statut'] ?? '') === 'annulée' || strtolower($res['statut'] ?? '') === 'cancelled') {
            $reservations_par_logement[$logement_id]['stats']['annulees']++;
        }

        $reservations_par_logement[$logement_id]['stats']['nuits_total'] += $res['duree_sejour'] ?? 0;
    }
}

// Statistiques globales
$stats_globales = [
    'total' => count($reservations),
    'confirmees' => 0,
    'annulees' => 0,
    'en_attente' => 0,
    'nuits_total' => 0,
    'chiffre_affaire_estimatif' => 0
];

foreach ($reservations as $res) {
    $statut = strtolower($res['statut'] ?? '');
    if ($statut === 'confirmée' || $statut === 'confirmed') {
        $stats_globales['confirmees']++;
    } elseif ($statut === 'annulée' || $statut === 'cancelled') {
        $stats_globales['annulees']++;
    } else {
        $stats_globales['en_attente']++;
    }
    $stats_globales['nuits_total'] += $res['duree_sejour'] ?? 0;
}
?>

<div class="container-fluid mt-4">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="text-gradient-primary">
                <i class="fas fa-calendar-alt"></i> Listing des réservations
            </h1>
            <p class="text-muted">Vue complète et détaillée de toutes vos réservations</p>
        </div>
        <div class="col-md-4 text-right">
            <a href="reservation_list.php" class="btn btn-secondary mr-2">
                <i class="fas fa-list"></i> Vue SMS
            </a>
            <button class="btn btn-success" onclick="exportToCSV()">
                <i class="fas fa-file-excel"></i> Export CSV
            </button>
        </div>
    </div>

    <!-- Statistiques globales -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-custom border-primary">
                <div class="card-body text-center">
                    <i class="fas fa-calendar-check fa-2x text-primary mb-2"></i>
                    <h3 class="mb-0"><?= $stats_globales['total'] ?></h3>
                    <p class="text-muted mb-0">Réservations totales</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-custom border-success">
                <div class="card-body text-center">
                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                    <h3 class="mb-0"><?= $stats_globales['confirmees'] ?></h3>
                    <p class="text-muted mb-0">Confirmées</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-custom border-danger">
                <div class="card-body text-center">
                    <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                    <h3 class="mb-0"><?= $stats_globales['annulees'] ?></h3>
                    <p class="text-muted mb-0">Annulées</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-custom border-info">
                <div class="card-body text-center">
                    <i class="fas fa-moon fa-2x text-info mb-2"></i>
                    <h3 class="mb-0"><?= $stats_globales['nuits_total'] ?></h3>
                    <p class="text-muted mb-0">Nuits totales</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card shadow-custom mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fas fa-filter"></i> Filtres et options
                <button class="btn btn-sm btn-light float-right" type="button" data-bs-toggle="collapse" data-bs-target="#filtresCollapse">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </h5>
        </div>
        <div class="collapse show" id="filtresCollapse">
            <div class="card-body">
                <form method="GET" id="filtreForm">
                    <div class="row">
                        <!-- Vue -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><i class="fas fa-eye"></i> Mode d'affichage</label>
                                <select class="form-control" name="vue" onchange="this.form.submit()">
                                    <option value="logement" <?= $vue === 'logement' ? 'selected' : '' ?>>Par logement</option>
                                    <option value="liste" <?= $vue === 'liste' ? 'selected' : '' ?>>Liste complète</option>
                                </select>
                            </div>
                        </div>

                        <!-- Logement -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><i class="fas fa-home"></i> Logement</label>
                                <select class="form-control" name="logement">
                                    <option value="">Tous les logements</option>
                                    <?php foreach ($logements as $logement): ?>
                                        <option value="<?= $logement['id'] ?>" <?= $filtre_logement == $logement['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($logement['nom_du_logement']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Statut -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><i class="fas fa-info-circle"></i> Statut</label>
                                <select class="form-control" name="statut">
                                    <option value="">Tous les statuts</option>
                                    <option value="confirmée" <?= $filtre_statut === 'confirmée' ? 'selected' : '' ?>>Confirmée</option>
                                    <option value="annulée" <?= $filtre_statut === 'annulée' ? 'selected' : '' ?>>Annulée</option>
                                    <option value="en attente" <?= $filtre_statut === 'en attente' ? 'selected' : '' ?>>En attente</option>
                                </select>
                            </div>
                        </div>

                        <!-- Plateforme -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><i class="fas fa-globe"></i> Plateforme</label>
                                <select class="form-control" name="plateforme">
                                    <option value="">Toutes les plateformes</option>
                                    <?php foreach ($plateformes as $plateforme): ?>
                                        <option value="<?= htmlspecialchars($plateforme) ?>" <?= $filtre_plateforme === $plateforme ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($plateforme) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Date début -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><i class="fas fa-calendar-alt"></i> Arrivée du</label>
                                <input type="date" class="form-control" name="date_debut" value="<?= htmlspecialchars($filtre_date_debut ?? '') ?>">
                            </div>
                        </div>

                        <!-- Date fin -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><i class="fas fa-calendar-alt"></i> Départ jusqu'au</label>
                                <input type="date" class="form-control" name="date_fin" value="<?= htmlspecialchars($filtre_date_fin ?? '') ?>">
                            </div>
                        </div>

                        <!-- Tri -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><i class="fas fa-sort"></i> Trier par</label>
                                <select class="form-control" name="tri">
                                    <option value="date_desc" <?= $tri === 'date_desc' ? 'selected' : '' ?>>Date (plus récente)</option>
                                    <option value="date_asc" <?= $tri === 'date_asc' ? 'selected' : '' ?>>Date (plus ancienne)</option>
                                    <option value="logement" <?= $tri === 'logement' ? 'selected' : '' ?>>Logement</option>
                                    <option value="client" <?= $tri === 'client' ? 'selected' : '' ?>>Client (A-Z)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Boutons -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filtrer
                                    </button>
                                    <a href="?" class="btn btn-secondary">
                                        <i class="fas fa-redo"></i> Réinitialiser
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Affichage selon la vue -->
    <?php if ($vue === 'logement'): ?>
        <!-- Vue par logement (accordéons) -->
        <div class="accordion" id="accordionLogements">
            <?php foreach ($reservations_par_logement as $logement_id => $data): ?>
                <div class="card shadow-custom mb-3">
                    <div class="card-header bg-light" id="heading<?= $logement_id ?>">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-0">
                                    <button class="btn btn-link text-left" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#collapse<?= $logement_id ?>">
                                        <i class="fas fa-home text-primary"></i>
                                        <strong><?= htmlspecialchars($data['nom']) ?></strong>
                                    </button>
                                </h5>
                            </div>
                            <div class="col-md-6 text-right">
                                <span class="badge text-bg-primary mr-2">
                                    <?= $data['stats']['total'] ?> réservation(s)
                                </span>
                                <span class="badge text-bg-success mr-2">
                                    <?= $data['stats']['confirmees'] ?> confirmée(s)
                                </span>
                                <span class="badge text-bg-info">
                                    <?= $data['stats']['nuits_total'] ?> nuit(s)
                                </span>
                            </div>
                        </div>
                    </div>

                    <div id="collapse<?= $logement_id ?>" class="collapse" data-parent="#accordionLogements">
                        <div class="card-body">
                            <?php include 'reservations_table_partial.php';
                                  $reservations_to_display = $data['reservations']; ?>
                            <?php include __DIR__ . '/reservations_table_partial.php'; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- Vue liste complète -->
        <div class="card shadow-custom">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> Liste complète (<?= count($reservations) ?> réservation(s))
                </h5>
            </div>
            <div class="card-body">
                <?php $reservations_to_display = $reservations; ?>
                <?php include __DIR__ . '/reservations_table_partial.php'; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Fonction pour exporter en CSV avec les mêmes filtres
function exportToCSV() {
    // Récupérer les paramètres actuels de l'URL
    const urlParams = new URLSearchParams(window.location.search);

    // Construire l'URL d'export avec les mêmes paramètres
    let exportUrl = 'export_reservations.php?';

    const params = ['logement', 'statut', 'date_debut', 'date_fin', 'plateforme'];
    params.forEach(param => {
        const value = urlParams.get(param);
        if (value) {
            exportUrl += param + '=' + encodeURIComponent(value) + '&';
        }
    });

    // Ouvrir l'URL d'export dans une nouvelle fenêtre pour télécharger le fichier
    window.location.href = exportUrl;
}
</script>


