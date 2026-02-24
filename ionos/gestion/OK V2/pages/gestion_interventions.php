<?php
include '../config.php';
include '../pages/menu.php';

// Récupération des interventions pour affichage
$interventions = $conn->query("
    SELECT p.*, l.nom_du_logement 
    FROM planning p 
    JOIN liste_logements l ON p.logement_id = l.id
    ORDER BY p.date ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Interventions</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h2>Gestion des Interventions</h2>
    <table class="table table-striped">
        <thead>
        <tr>
            <th>Logement</th>
            <th>Date</th>
            <th>Statut</th>
            <th>Chiffre d'Affaires (€)</th>
            <th>Charges (€)</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($interventions as $intervention): ?>
            <tr>
                <td><?= htmlspecialchars($intervention['nom_du_logement']) ?></td>
                <td><?= htmlspecialchars($intervention['date']) ?></td>
                <td><?= htmlspecialchars($intervention['statut']) ?></td>
                <td><?= htmlspecialchars($intervention['chiffre_affaires'] ?? 0) ?></td>
                <td><?= htmlspecialchars($intervention['charges'] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
