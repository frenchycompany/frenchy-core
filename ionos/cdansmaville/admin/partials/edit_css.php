<?php
session_start();
require_once 'config.php';
require_once '../db/connection.php';

// Vérifier si un client est sélectionné
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

// Vérifier si le client existe
$client = null;
if ($client_id) {
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Si le client n'existe pas, afficher une erreur
if (!$client) {
    die("Client introuvable.");
}

// Sauvegarde du CSS personnalisé
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $css_personnalise = $_POST['css_personnalise'];

    $stmt = $conn->prepare("UPDATE clients SET css_personnalise = ? WHERE id = ?");
    $stmt->execute([$css_personnalise, $client_id]);

    header("Location: edit_css.php?client_id=" . $client_id);
    exit();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Édition du CSS</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <h1>Personnalisation du CSS</h1>
        <a href="index.php">Retour au Tableau de Bord</a>
        <a href="manage_content.php?client_id=<?php echo $client_id; ?>">Gérer le Contenu</a>
    </header>
    <main>
        <h2>Édition du CSS pour <?php echo htmlspecialchars($client['nom']); ?></h2>
        <form action="edit_css.php?client_id=<?php echo $client_id; ?>" method="post">
            <label for="css_personnalise">CSS Personnalisé :</label>
            <textarea name="css_personnalise" rows="10" style="width:100%;"><?php echo htmlspecialchars($client['css_personnalise'] ?? ''); ?></textarea>
            <button type="submit">Enregistrer</button>
        </form>
    </main>
</body>
</html>
