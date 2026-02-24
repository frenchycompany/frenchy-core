<?php
include '../config.php'; // Inclut la configuration de la base de données
include '../pages/menu.php'; // Inclut le menu de navigation
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Modèles de Contrat</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-4">
        <?php
        try {
            // Récupérer tous les modèles de contrat
            $stmt = $conn->query("SELECT id, title, updated_at FROM contract_templates");
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo '<h2>Liste des Modèles de Contrat</h2>';
            echo '<a href="create_template.php" class="btn btn-success mb-3">Créer un Nouveau Modèle</a>';
            echo '<table class="table table-striped">';
            echo '<thead><tr><th>#</th><th>Titre</th><th>Dernière Mise à Jour</th><th>Actions</th></tr></thead>';
            echo '<tbody>';

            foreach ($templates as $template) {
                echo '<tr>';
                echo "<td>{$template['id']}</td>";
                echo "<td>{$template['title']}</td>";
                echo "<td>{$template['updated_at']}</td>";
                echo '<td>';
                echo "<a href='edit_template.php?id={$template['id']}' class='btn btn-primary btn-sm'>Modifier</a> ";
                echo "<a href='delete_template.php?id={$template['id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Êtes-vous sûr de vouloir supprimer ce modèle ?\");'>Supprimer</a> ";
                echo "<a href='duplicate_template.php?id={$template['id']}' class='btn btn-secondary btn-sm'>Dupliquer</a>";
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
        }
        ?>
    </div>
</body>
</html>
