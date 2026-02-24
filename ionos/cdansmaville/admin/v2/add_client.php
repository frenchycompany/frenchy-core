<?php
session_start();
require_once 'config.php';
require_once '../db/connection.php';

// Récupérer les types de commerce depuis la base de données
$types_commerce = $conn->query("SELECT nom FROM types_commerce")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom = htmlspecialchars($_POST['nom']);
    $type_commerce = htmlspecialchars($_POST['type_commerce']);
    
    $stmt = $conn->prepare("INSERT INTO clients (nom, type_commerce) VALUES (?, ?)");
    $stmt->execute([$nom, $type_commerce]);
    
    header("Location: clients.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Client</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <h1>Ajouter un Client</h1>
        <a href="clients.php">Retour à la Gestion des Clients</a>
        <a href="logout.php">Déconnexion</a>
    </header>
    <main>
        <form action="add_client.php" method="post">
            <label for="nom">Nom du Client :</label>
            <input type="text" name="nom" required>
            <label for="type_commerce">Type de Commerce :</label>
            <select name="type_commerce" required>
                <?php foreach ($types_commerce as $type): ?>
                    <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Ajouter</button>
        </form>
    </main>
</body>
</html>
