<?php
include '../config.php'; // Connexion à la base de données
include '../pages/menu.php'; // Menu de navigation

// Initialiser les filtres
$dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-01'); // Premier jour du mois
$dateFin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-t'); // Dernier jour du mois
$nombreInterventions = 0;

// Récupération du nombre d'interventions pour la période sélectionnée
try {
    $stmtInterventions = $conn->prepare("
        SELECT COUNT(*) AS total_interventions 
        FROM planning 
        WHERE date BETWEEN :date_debut AND :date_fin
    ");
    $stmtInterventions->execute([
        ':date_debut' => $dateDebut,
        ':date_fin' => $dateFin
    ]);
    $nombreInterventions = $stmtInterventions->fetchColumn();
} catch (PDOException $e) {
    die("Erreur lors de la récupération des interventions : " . $e->getMessage());
}

// Gestion des soumissions de formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $periodeDebut = $_POST['periode_debut'];
    $periodeFin = $_POST['periode_fin'];
    $nombreDeLocations = (int)$_POST['nombre_de_locations'];
    $nombreDeMachines = (int)$_POST['nombre_de_machines'];

    try {
        $stmtInsert = $conn->prepare("
            INSERT INTO gestion_machines (periode_debut, periode_fin, nombre_de_locations, nombre_de_machines) 
            VALUES (:periode_debut, :periode_fin, :nombre_de_locations, :nombre_de_machines)
        ");
        $stmtInsert->execute([
            ':periode_debut' => $periodeDebut,
            ':periode_fin' => $periodeFin,
            ':nombre_de_locations' => $nombreDeLocations,
            ':nombre_de_machines' => $nombreDeMachines
        ]);
    } catch (PDOException $e) {
        die("Erreur lors de l'enregistrement des données : " . $e->getMessage());
    }
}

// Récupération des données pour affichage
try {
    $stmtMachines = $conn->query("SELECT * FROM gestion_machines ORDER BY periode_debut DESC");
    $dataMachines = $stmtMachines->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des données : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Machines</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h2>Gestion des Machines</h2>

    <!-- Filtres pour la période -->
    <form method="GET" class="mb-4">
        <div class="row">
            <div class="col-md-4">
                <label for="date_debut">Date de début :</label>
                <input type="date" id="date_debut" name="date_debut" class="form-control" value="<?= htmlspecialchars($dateDebut) ?>" required>
            </div>
            <div class="col-md-4">
                <label for="date_fin">Date de fin :</label>
                <input type="date" id="date_fin" name="date_fin" class="form-control" value="<?= htmlspecialchars($dateFin) ?>" required>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
            </div>
        </div>
    </form>

    <!-- Résumé des interventions -->
    <h3>Nombre d'interventions pour la période</h3>
    <div class="alert alert-info">
        Nombre total d'interventions : <strong><?= $nombreInterventions ?></strong>
    </div>

    <!-- Formulaire pour ajouter des données -->
    <h3>Ajouter des données</h3>
    <form method="POST" class="mb-4">
        <div class="row">
            <div class="col-md-3">
                <label for="periode_debut">Période début :</label>
                <input type="date" id="periode_debut" name="periode_debut" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label for="periode_fin">Période fin :</label>
                <input type="date" id="periode_fin" name="periode_fin" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label for="nombre_de_locations">Nombre de locations :</label>
                <input type="number" id="nombre_de_locations" name="nombre_de_locations" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label for="nombre_de_machines">Nombre de machines :</label>
                <input type="number" id="nombre_de_machines" name="nombre_de_machines" class="form-control" required>
            </div>
        </div>
        <button type="submit" class="btn btn-success mt-3">Enregistrer</button>
    </form>

    <!-- Tableau des données enregistrées -->
    <h3>Historique des données</h3>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Période Début</th>
                <th>Période Fin</th>
                <th>Nombre de Locations</th>
                <th>Nombre de Machines</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dataMachines as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['periode_debut']) ?></td>
                    <td><?= htmlspecialchars($row['periode_fin']) ?></td>
                    <td><?= htmlspecialchars($row['nombre_de_locations']) ?></td>
                    <td><?= htmlspecialchars($row['nombre_de_machines']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
