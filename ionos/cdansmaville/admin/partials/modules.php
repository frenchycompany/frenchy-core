<?php
session_start();
require_once 'config.php';
require_once '../db/connection.php';

// Ajouter un module
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_module'])) {
    $nom = htmlspecialchars($_POST['nom']);
    $description = htmlspecialchars($_POST['description']);
    $fichier_php = htmlspecialchars($_POST['fichier_php']);

    $stmt = $conn->prepare("INSERT INTO modules (nom, description, fichier_php) VALUES (?, ?, ?)");
    $stmt->execute([$nom, $description, $fichier_php]);

    header("Location: modules.php");
    exit();
}

// Supprimer un module
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_module'])) {
    $id = intval($_POST['id']);
    $stmt = $conn->prepare("DELETE FROM modules WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: modules.php");
    exit();
}

// Récupérer tous les modules
$modules = $conn->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Modules</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <h1>Gestion des Modules</h1>
        <a href="index.php">Retour au Tableau de Bord</a>
        <a href="logout.php">Déconnexion</a>
    </header>
    <main>
        <h2>Liste des Modules</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Description</th>
                    <th>Fichier PHP</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modules as $module): ?>
                    <tr>
                        <td><?php echo $module['id']; ?></td>
                        <td><?php echo htmlspecialchars($module['nom']); ?></td>
                        <td><?php echo htmlspecialchars($module['description']); ?></td>
                        <td><?php echo htmlspecialchars($module['fichier_php']); ?></td>
                        <td>
                            <!-- Supprimer -->
                            <form action="modules.php" method="post" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $module['id']; ?>">
                                <button type="submit" name="delete_module" class="btn btn-danger" onclick="return confirm('Voulez-vous vraiment supprimer ce module ?');">Supprimer</button>
                            </form>

                            <!-- Activer -->
                            <a href="?section=modules&run=<?php echo pathinfo($module['fichier_php'], PATHINFO_FILENAME); ?>" class="btn btn-primary" style="margin-left: 5px;">Activer</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Ajouter un Nouveau Module</h2>
        <form action="modules.php" method="post">
            <label for="nom">Nom du Module :</label>
            <input type="text" name="nom" required>

            <label for="description">Description :</label>
            <textarea name="description" required></textarea>

            <label for="fichier_php">Nom du fichier PHP :</label>
            <input type="text" name="fichier_php" placeholder="ex: airbnb_embed.php" required>

            <button type="submit" name="add_module">Ajouter</button>
        </form>
    </main>
</body>
</html>
