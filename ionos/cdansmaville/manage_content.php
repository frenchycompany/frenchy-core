<?php
session_start();
require_once 'config.php';
require_once '../db/connection.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Vérifier la sélection d'un client
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

// Récupérer la liste des clients
$clients = $conn->query("SELECT * FROM clients")->fetchAll(PDO::FETCH_ASSOC");

// Récupérer les informations du client sélectionné
$client = null;
if ($client_id) {
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Vérifier et créer le dossier uploads
$uploadDir = '../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Upload du logo et de l’image d’en-tête
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['upload_images'])) {
    // Vérifier et enregistrer le logo
    if (!empty($_FILES['logo']['name'])) {
        $logoName = "logo_" . $client_id . "_" . time() . "." . pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $logoPath = $uploadDir . $logoName;

        if (move_uploaded_file($_FILES['logo']['tmp_name'], $logoPath)) {
            $stmt = $conn->prepare("UPDATE clients SET logo = ? WHERE id = ?");
            $stmt->execute([$logoPath, $client_id]);
        }
    }

    // Vérifier et enregistrer l’image d’en-tête
    if (!empty($_FILES['header_image']['name'])) {
        $headerName = "header_" . $client_id . "_" . time() . "." . pathinfo($_FILES['header_image']['name'], PATHINFO_EXTENSION);
        $headerPath = $uploadDir . $headerName;

        if (move_uploaded_file($_FILES['header_image']['tmp_name'], $headerPath)) {
            $stmt = $conn->prepare("UPDATE clients SET header_image = ? WHERE id = ?");
            $stmt->execute([$headerPath, $client_id]);
        }
    }

    header("Location: manage_content.php?client_id=" . $client_id);
    exit();
}

// Suppression d'une image
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_image'])) {
    $image_type = $_POST['image_type'];
    $column = $image_type === "logo" ? "logo" : "header_image";

    // Supprimer le fichier du serveur
    if (!empty($client[$column]) && file_exists($client[$column])) {
        unlink($client[$column]);
    }

    // Mettre à jour la base de données
    $stmt = $conn->prepare("UPDATE clients SET $column = NULL WHERE id = ?");
    $stmt->execute([$client_id]);

    header("Location: manage_content.php?client_id=" . $client_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer le Contenu des Clients</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <h1>Gérer le Contenu des Clients</h1>
        <a href="index.php">Retour au Tableau de Bord</a>
        <a href="logout.php">Déconnexion</a>
    </header>

    <main>
        <h2>Sélectionner un Client</h2>
        <form action="manage_content.php" method="get">
            <label for="client_id">Client :</label>
            <select name="client_id" onchange="this.form.submit()">
                <option value="">-- Sélectionner --</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($client_id == $c['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['nom']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($client_id): ?>
            <h2>Personnalisation Visuelle</h2>
            <form action="manage_content.php?client_id=<?php echo $client_id; ?>" method="post" enctype="multipart/form-data">
                <div class="image-upload">
                    <label for="logo">Logo :</label>
                    <input type="file" name="logo" accept="image/*">
                    <?php if (!empty($client['logo'])): ?>
                        <div class="image-preview">
                            <img src="<?php echo $client['logo']; ?>" alt="Logo" width="150">
                            <form action="manage_content.php?client_id=<?php echo $client_id; ?>" method="post">
                                <input type="hidden" name="image_type" value="logo">
                                <button type="submit" name="delete_image">Supprimer</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="image-upload">
                    <label for="header_image">Image d'en-tête :</label>
                    <input type="file" name="header_image" accept="image/*">
                    <?php if (!empty($client['header_image'])): ?>
                        <div class="image-preview">
                            <img src="<?php echo $client['header_image']; ?>" alt="Image d'en-tête" width="300">
                            <form action="manage_content.php?client_id=<?php echo $client_id; ?>" method="post">
                                <input type="hidden" name="image_type" value="header_image">
                                <button type="submit" name="delete_image">Supprimer</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <button type="submit" name="upload_images">Enregistrer</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
