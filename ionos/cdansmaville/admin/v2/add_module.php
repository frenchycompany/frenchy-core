
<?php
session_start();
require_once 'config.php';
require_once '../db/connection.php';

// Ajouter un module
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_module'])) {
    $nom = htmlspecialchars($_POST['nom']);
    $description = htmlspecialchars($_POST['description']);
    
    $stmt = $conn->prepare("INSERT INTO modules (nom, description) VALUES (?, ?)");
    $stmt->execute([$nom, $description]);
    
    header("Location: modules.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Module</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <h1>Ajouter un Module</h1>
        <a href="modules.php">Retour à la Gestion des Modules</a>
        <a href="logout.php">Déconnexion</a>
    </header>
    <main>
        <form action="add_module.php" method="post">
            <label for="nom">Nom du Module :</label>
            <input type="text" name="nom" required>
            <label for="description">Description :</label>
            <textarea name="description" required></textarea>
            <button type="submit" name="add_module">Ajouter</button>
        </form>
    </main>
</body>
</html>
