<?php
ob_start(); // Démarrer le buffer pour éviter les erreurs d'en-têtes

include '../config.php'; // Connexion à la base de données
include '../pages/menu.php'; // Inclusion du menu

// Activer les erreurs PHP pour débogage (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurer la locale pour afficher les mois en français
setlocale(LC_TIME, 'fr_FR.UTF-8');

// Récupération des mois et années disponibles dans la table comptabilite
try {
    $datesDisponibles = $conn->query("
        SELECT DISTINCT 
            MONTH(date_comptabilisation) AS mois, 
            YEAR(date_comptabilisation) AS annee 
        FROM comptabilite
        ORDER BY annee DESC, mois DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des dates : " . $e->getMessage());
}

// Récupération des intervenants disponibles
try {
    $intervenants = $conn->query("
        SELECT DISTINCT i.id, i.nom 
        FROM intervenant i
        JOIN comptabilite c ON i.id = c.intervenant_id
        ORDER BY i.nom ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des intervenants : " . $e->getMessage());
}

// Récupération des filtres via GET
$mois = filter_input(INPUT_GET, 'mois', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
$annee = filter_input(INPUT_GET, 'annee', FILTER_VALIDATE_INT);
$intervenant = filter_input(INPUT_GET, 'intervenant', FILTER_VALIDATE_INT);

// Construction de la clause WHERE pour appliquer les filtres
$conditions = [];
$params = [];

if ($mois) {
    $conditions[] = "MONTH(c.date_comptabilisation) = :mois";
    $params[':mois'] = $mois;
}
if ($annee) {
    $conditions[] = "YEAR(c.date_comptabilisation) = :annee";
    $params[':annee'] = $annee;
}
if ($intervenant) {
    $conditions[] = "c.intervenant_id = :intervenant";
    $params[':intervenant'] = $intervenant;
}
$whereClause = count($conditions) > 0 ? "WHERE " . implode(" AND ", $conditions) : "";

// --- Export CSV des interventions (si demandé) ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    try {
        $exportQuery = "
            SELECT c.date_comptabilisation, c.type, c.montant, c.description, 
                   i.nom AS intervenant_nom,
                   ll.nom_du_logement AS nom_du_logement
            FROM comptabilite c
            LEFT JOIN intervenant i ON c.intervenant_id = i.id
            LEFT JOIN planning p ON c.source_id = p.id
            LEFT JOIN liste_logements ll ON p.logement_id = ll.id
            " . $whereClause . "
            ORDER BY c.date_comptabilisation ASC
        ";
        $stmtExport = $conn->prepare($exportQuery);
        $stmtExport->execute($params);
        $exportData = $stmtExport->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Erreur lors de l'exportation des interventions : " . $e->getMessage());
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=interventions_export.csv');
    $output = fopen('php://output', 'w');
    // En-tête du CSV
    fputcsv($output, ['Date', 'Type', 'Montant', 'Description', 'Intervenant', 'Logement']);
    foreach ($exportData as $row) {
        fputcsv($output, [
            $row['date_comptabilisation'] ?? '',
            $row['type'] ?? '',
            $row['montant'] ?? '',
            $row['description'] ?? '',
            $row['intervenant_nom'] ?? '',
            $row['nom_du_logement'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}

// Calcul des totaux et du coût moyen par intervention
try {
    // Total du Chiffre d'Affaires (CA)
    $totalCAQuery = "
        SELECT SUM(montant) 
        FROM comptabilite c 
        WHERE c.type = 'CA' " . ($whereClause ? " AND " . substr($whereClause, 6) : "");
    $stmtCA = $conn->prepare($totalCAQuery);
    $stmtCA->execute($params);
    $caTotal = $stmtCA->fetchColumn() ?: 0;

    // Total des Charges
    $totalChargesQuery = "
        SELECT SUM(montant) 
        FROM comptabilite c 
        WHERE c.type = 'Charge' " . ($whereClause ? " AND " . substr($whereClause, 6) : "");
    $stmtCharges = $conn->prepare($totalChargesQuery);
    $stmtCharges->execute($params);
    $chargesTotal = $stmtCharges->fetchColumn() ?: 0;

    // Nombre total d'interventions
    $totalInterventionsQuery = "
        SELECT COUNT(*) 
        FROM comptabilite c 
        " . ($whereClause ? $whereClause : "");
    $stmtInterventions = $conn->prepare($totalInterventionsQuery);
    $stmtInterventions->execute($params);
    $interventionsCount = $stmtInterventions->fetchColumn() ?: 0;

    // Calcul du coût moyen par intervention
    $coutMoyenIntervention = $interventionsCount > 0 ? $chargesTotal / $interventionsCount : 0;
} catch (PDOException $e) {
    die("Erreur lors du calcul des totaux : " . $e->getMessage());
}

// Requête pour récupérer les interventions détaillées (pour affichage)
try {
    $detailQuery = "
        SELECT c.date_comptabilisation, c.type, c.montant, c.description, 
               i.nom AS intervenant_nom,
               ll.nom_du_logement AS nom_du_logement
        FROM comptabilite c
        LEFT JOIN intervenant i ON c.intervenant_id = i.id
        LEFT JOIN planning p ON c.source_id = p.id
        LEFT JOIN liste_logements ll ON p.logement_id = ll.id
        " . $whereClause . "
        ORDER BY c.date_comptabilisation ASC
    ";
    $stmtDetail = $conn->prepare($detailQuery);
    $stmtDetail->execute($params);
    $interventionsDetails = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des interventions : " . $e->getMessage());
}

// Préparation de la chaîne de requête pour conserver les filtres lors de l'export CSV
$queryString = http_build_query([
    'mois' => $mois,
    'annee' => $annee,
    'intervenant' => $intervenant
]);

// Regroupement des interventions par jour (format Y-m-d)
$grouped = [];
foreach ($interventionsDetails as $row) {
    $dateKey = date('Y-m-d', strtotime($row['date_comptabilisation']));
    $grouped[$dateKey][] = $row;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de la Comptabilité</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h2>Gestion de la Comptabilité</h2>

    <!-- Résumé des Totaux -->
    <div class="row">
        <div class="col-md-4">
            <div class="card text-white bg-success mb-3">
                <div class="card-header">Chiffre d'Affaires Total</div>
                <div class="card-body">
                    <h5 class="card-title"><?= number_format($caTotal, 2, ',', ' ') ?> €</h5>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-danger mb-3">
                <div class="card-header">Charges Totales</div>
                <div class="card-body">
                    <h5 class="card-title"><?= number_format($chargesTotal, 2, ',', ' ') ?> €</h5>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-info mb-3">
                <div class="card-header">Coût Moyen par Intervention</div>
                <div class="card-body">
                    <h5 class="card-title"><?= number_format($coutMoyenIntervention, 2, ',', ' ') ?> €</h5>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulaire de Filtres -->
    <form method="GET" action="comptabilite.php" class="mb-4">
        <div class="row">
            <div class="col-md-4">
                <label for="mois">Mois :</label>
                <select name="mois" id="mois" class="form-control">
                    <option value="">Tous</option>
                    <?php 
                    $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'Europe/Paris', IntlDateFormatter::GREGORIAN, 'LLLL');
                    foreach ($datesDisponibles as $date): ?>
                        <option value="<?= $date['mois'] ?>" <?= $mois == $date['mois'] ? 'selected' : '' ?>>
                            <?= ucfirst($formatter->format(DateTime::createFromFormat('!m', $date['mois']))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="annee">Année :</label>
                <select name="annee" id="annee" class="form-control">
                    <option value="">Toutes</option>
                    <?php foreach (array_unique(array_column($datesDisponibles, 'annee')) as $anneeDisponible): ?>
                        <option value="<?= $anneeDisponible ?>" <?= $annee == $anneeDisponible ? 'selected' : '' ?>>
                            <?= $anneeDisponible ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="intervenant">Intervenant :</label>
                <select name="intervenant" id="intervenant" class="form-control">
                    <option value="">Tous</option>
                    <?php foreach ($intervenants as $interv): ?>
                        <option value="<?= $interv['id'] ?>" <?= $intervenant == $interv['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($interv['nom'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-12 text-right">
                <button type="submit" class="btn btn-primary">Filtrer</button>
            </div>
        </div>
    </form>

    <!-- Liens Module Facturation & Export CSV -->
    <div class="row mb-4">
        <div class="col-md-6 text-left">
            <a href="facturation.php" class="btn btn-primary">Accéder au Module Facturation</a>
        </div>
        <div class="col-md-6 text-right">
            <a href="export_interventions.php?<?= $queryString ?>" class="btn btn-secondary">Exporter les Interventions (CSV)</a>
        </div>
    </div>

    <!-- Bouton de Mise à Jour de la Comptabilité -->
    <form method="POST" action="update_comptabilite.php">
        <button type="submit" class="btn btn-warning mb-4">Mettre à jour la Comptabilité</button>
    </form>

    <!-- Affichage des Interventions Groupées par Jour -->
    <h3>Détail des Interventions par Jour</h3>
    <?php if(!empty($grouped)): ?>
        <?php foreach($grouped as $date => $interventions): ?>
            <h4><?= htmlspecialchars($date) ?></h4>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Montant</th>
                        <th>Description</th>
                        <th>Intervenant</th>
                        <th>Logement</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($interventions as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['type'] ?? '') ?></td>
                            <td><?= number_format($row['montant'] ?? 0, 2, ',', ' ') ?> €</td>
                            <td><?= htmlspecialchars($row['description'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['intervenant_nom'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['nom_du_logement'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <!-- Calcul du résumé par intervenant pour la journée -->
            <?php 
            $sums = [];
            foreach($interventions as $row){
                $nom = $row['intervenant_nom'] ?? 'Non défini';
                if(!isset($sums[$nom])){
                    $sums[$nom] = 0;
                }
                $sums[$nom] += $row['montant'] ?? 0;
            }
            ?>
            <h5>Résumé par Intervenant pour le <?= htmlspecialchars($date) ?> :</h5>
            <ul>
                <?php foreach($sums as $nom => $total): ?>
                    <li><?= htmlspecialchars($nom) ?> : <?= number_format($total, 2, ',', ' ') ?> €</li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-warning">Aucune intervention trouvée.</div>
    <?php endif; ?>
</div>
</body>
</html>
<?php
ob_end_flush();
?>
