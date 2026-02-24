<?php
session_start();
require_once '../db/connection.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Récupérer le client à partir du paramètre GET
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
if (!$client_id) {
    echo "<p>Aucun client sélectionné.</p>";
    return;
}

// Récupérer les informations du client
$stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) {
    echo "<p>Client introuvable.</p>";
    return;
}

// Récupérer la bannière du client (si elle existe)
$stmt = $conn->prepare("SELECT * FROM client_banners WHERE client_id = ?");
$stmt->execute([$client_id]);
$banner = $stmt->fetch(PDO::FETCH_ASSOC);

// Gérer la soumission du formulaire pour enregistrer la bannière
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_banner'])) {
    $button_text = $_POST['button_text'] ?? '';
    $button_link = $_POST['button_link'] ?? '';

    // Gestion du téléchargement de l'image
    $image_path = $banner['image_path'] ?? '';
    if (!empty($_FILES['banner_image']['name'])) {
        $image_name = "banner_" . $client_id . "_" . time() . "." . pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION);
        $image_path = "../uploads/" . $image_name;
        move_uploaded_file($_FILES['banner_image']['tmp_name'], $image_path);
    }

    if ($banner) {
        // Mise à jour de la bannière existante
        $stmt = $conn->prepare("UPDATE client_banners SET image_path=?, button_text=?, button_link=? WHERE client_id=?");
        $stmt->execute([$image_path, $button_text, $button_link, $client_id]);
    } else {
        // Insertion d'une nouvelle bannière
        $stmt = $conn->prepare("INSERT INTO client_banners (client_id, image_path, button_text, button_link) VALUES (?, ?, ?, ?)");
        $stmt->execute([$client_id, $image_path, $button_text, $button_link]);
    }

    header("Location: ?section=banner&client_id=" . $client_id);
    exit();
}

// Gérer la suppression de la bannière
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_banner'])) {
    if (!empty($banner['image_path']) && file_exists($banner['image_path'])) {
        unlink($banner['image_path']);
    }
    $stmt = $conn->prepare("DELETE FROM client_banners WHERE client_id = ?");
    $stmt->execute([$client_id]);

    header("Location: ?section=banner&client_id=" . $client_id);
    exit();
}

// Génération du code d'intégration (pour la landing page)
$landing_url = "https://frenchyconciergerie.fr/cdansmaville/admin/landing.php?client_id=" . $client_id;
$iframe_code = "<iframe src=\"" . htmlspecialchars($landing_url, ENT_QUOTES, 'UTF-8') . "\" width=\"100%\" height=\"800\" frameborder=\"0\"></iframe>";
?>

<div class="form-container">
    <h2>Gérer la Bannière pour <?php echo htmlspecialchars($client['nom'], ENT_QUOTES, 'UTF-8'); ?></h2>

    <form method="post" enctype="multipart/form-data">
        <label>Image de la bannière :</label>
        <input type="file" name="banner_image" accept="image/*">
        <?php if (!empty($banner['image_path'])): ?>
            <div class="banner-preview">
                <img src="<?php echo htmlspecialchars($banner['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Bannière">
            </div>
        <?php endif; ?>

        <label>Texte du bouton :</label>
        <input type="text" name="button_text" value="<?php echo htmlspecialchars($banner['button_text'] ?? 'Télécharger', ENT_QUOTES, 'UTF-8'); ?>">

        <label>Lien du bouton :</label>
        <input type="text" name="button_link" value="<?php echo htmlspecialchars($banner['button_link'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>">

        <button type="submit" name="save_banner">Enregistrer</button>
    </form>

    <?php if ($banner): ?>
        <form method="post" style="margin-top: 10px;">
            <button type="submit" name="delete_banner" style="background: #dc3545;">Supprimer la bannière</button>
        </form>
    <?php endif; ?>

    <h2>Code d'Intégration</h2>
    <textarea style="width:100%; height:80px;" readonly><?php echo htmlspecialchars($iframe_code, ENT_QUOTES, 'UTF-8'); ?></textarea>
</div>
