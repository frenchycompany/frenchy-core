<?php
session_start();
require_once 'config.php';
require_once '../db/connection.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 📌 Récupérer la liste des clients
$clients = $conn->query("SELECT * FROM clients")->fetchAll(PDO::FETCH_ASSOC);

// 📌 Vérifier si un client a été sélectionné
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

// 📌 Récupérer les informations du client
$client = null;
if ($client_id) {
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 📌 Vérifier si le client a déjà une bannière
$banner = null;
if ($client_id) {
    $stmt = $conn->prepare("SELECT * FROM client_banners WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 📌 Gérer la soumission du formulaire
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_banner'])) {
    $button_text = $_POST['button_text'] ?? '';
    $button_link = $_POST['button_link'] ?? '';

    // 📌 Gestion du téléchargement de l'image
    $image_path = $banner['image_path'] ?? '';
    if (!empty($_FILES['banner_image']['name'])) {
        $image_name = "banner_" . $client_id . "_" . time() . "." . pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION);
        $image_path = "../uploads/" . $image_name;
        move_uploaded_file($_FILES['banner_image']['tmp_name'], $image_path);
    }

    if ($banner) {
        // 🔄 Mise à jour de la bannière
        $stmt = $conn->prepare("UPDATE client_banners SET image_path=?, button_text=?, button_link=? WHERE client_id=?");
        $stmt->execute([$image_path, $button_text, $button_link, $client_id]);
    } else {
        // ➕ Ajout d'une nouvelle bannière
        $stmt = $conn->prepare("INSERT INTO client_banners (client_id, image_path, button_text, button_link) VALUES (?, ?, ?, ?)");
        $stmt->execute([$client_id, $image_path, $button_text, $button_link]);
    }

    header("Location: manage_banner.php?client_id=" . $client_id);
    exit();
}

// 📌 Suppression de la bannière
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_banner'])) {
    if (!empty($banner['image_path']) && file_exists($banner['image_path'])) {
        unlink($banner['image_path']);
    }

    $stmt = $conn->prepare("DELETE FROM client_banners WHERE client_id = ?");
    $stmt->execute([$client_id]);

    header("Location: manage_banner.php?client_id=" . $client_id);
    exit();
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer la Bannière</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .form-container { max-width: 600px; margin: auto; padding: 20px; background: #fff; border-radius: 5px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); }
        .form-container label { font-weight: bold; display: block; margin-top: 10px; }
        .form-container input, .form-container select { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 5px; }
        .form-container button { width: 100%; background: #007bff; color: white; border: none; padding: 10px; cursor: pointer; margin-top: 10px; }
        .banner-preview img { max-width: 100%; display: block; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Gérer la Bannière</h2>

        <!-- Sélection du client -->
        <form method="get" action="manage_banner.php">
            <label for="client_id">Sélectionner un client :</label>
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
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">

                <label>Image de la bannière :</label>
                <input type="file" name="banner_image">
                <?php if (!empty($banner['image_path'])): ?>
                    <div class="banner-preview">
                        <img src="<?php echo htmlspecialchars($banner['image_path']); ?>" alt="Bannière">
                    </div>
                <?php endif; ?>

                <label>Texte du bouton :</label>
                <input type="text" name="button_text" value="<?php echo htmlspecialchars($banner['button_text'] ?? 'Télécharger'); ?>">

                <label>Lien du bouton :</label>
                <input type="text" name="button_link" value="<?php echo htmlspecialchars($banner['button_link'] ?? '#'); ?>">

                <button type="submit" name="save_banner">Enregistrer</button>
            </form>

            <!-- Suppression de la bannière -->
            <?php if ($banner): ?>
                <form method="post" style="margin-top: 10px;">
                    <button type="submit" name="delete_banner" style="background: #dc3545;">Supprimer la bannière</button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <p>🔍 Sélectionnez un client pour gérer sa bannière.</p>
        <?php endif; ?>
    </div>
</body>
</html>
