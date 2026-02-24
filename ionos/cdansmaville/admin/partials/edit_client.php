
<?php
session_start();
require_once 'config.php';
require_once '../db/connection.php';

// Vérifier si l'ID du client est passé en paramètre
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: clients.php");
    exit();
}

$id = intval($_GET['id']);

// Récupérer les informations du client
$stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    header("Location: clients.php");
    exit();
}

// Récupérer les types de commerce
$types_commerce = $conn->query("SELECT nom FROM types_commerce")->fetchAll(PDO::FETCH_COLUMN);

// Mettre à jour les informations du client
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom = htmlspecialchars($_POST['nom']);
    $type_commerce = htmlspecialchars($_POST['type_commerce']);
    
    $stmt = $conn->prepare("UPDATE clients SET nom = ?, type_commerce = ? WHERE id = ?");
    $stmt->execute([$nom, $type_commerce, $id]);

    $stmt = $conn->prepare("UPDATE clients SET visite_virtuelle_code = ? WHERE id = ?");
$stmt->execute([$_POST['visite_virtuelle_code'], $client_id]);

    
    header("Location: clients.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un Client</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <h1>Modifier un Client</h1>
        <a href="clients.php">Retour à la Gestion des Clients</a>
        <a href="logout.php">Déconnexion</a>
    </header>
    <main>
        <form action="edit_client.php?id=<?php echo $id; ?>" method="post">
            <label for="nom">Nom du Client :</label>
            <input type="text" name="nom" value="<?php echo htmlspecialchars($client['nom']); ?>" required>
            <label for="type_commerce">Type de Commerce :</label>
            <label for="visite_virtuelle_code">Code Visite Virtuelle Matterport :</label>
<input type="text" name="visite_virtuelle_code" value="<?php echo htmlspecialchars($client['visite_virtuelle_code'] ?? ''); ?>">

            <select name="type_commerce" required>
                <?php foreach ($types_commerce as $type): ?>
                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($client['type_commerce'] == $type) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($type); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Mettre à jour</button>
        </form>
    </main>
</body>
</html>
