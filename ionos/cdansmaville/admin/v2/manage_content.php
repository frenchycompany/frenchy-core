<?php
session_start();
require_once 'config.php';
require_once '../db/connection.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Récupérer la liste des clients
$clients = $conn->query("SELECT * FROM clients")->fetchAll(PDO::FETCH_ASSOC);

// Vérifier si un client a été sélectionné
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

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

// 📌 Gestion de l'upload des images (logo, header et promo)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['upload_images'])) {
    if ($client_id) {
        // ✅ Logo
        if (!empty($_FILES['logo']['name'])) {
            $logoName = "logo_" . $client_id . "_" . time() . "." . pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $logoPath = $uploadDir . $logoName;

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $logoPath)) {
                $stmt = $conn->prepare("UPDATE clients SET logo = ? WHERE id = ?");
                $stmt->execute([$logoPath, $client_id]);
            }
        }

        // ✅ Image d'en-tête
        if (!empty($_FILES['header_image']['name'])) {
            $headerName = "header_" . $client_id . "_" . time() . "." . pathinfo($_FILES['header_image']['name'], PATHINFO_EXTENSION);
            $headerPath = $uploadDir . $headerName;

            if (move_uploaded_file($_FILES['header_image']['tmp_name'], $headerPath)) {
                $stmt = $conn->prepare("UPDATE clients SET header_image = ? WHERE id = ?");
                $stmt->execute([$headerPath, $client_id]);
            }
        }

        // ✅ Image promotionnelle
        if (!empty($_FILES['promo_image']['name'])) {
            $promoName = "promo_" . $client_id . "_" . time() . "." . pathinfo($_FILES['promo_image']['name'], PATHINFO_EXTENSION);
            $promoPath = $uploadDir . $promoName;

            if (move_uploaded_file($_FILES['promo_image']['tmp_name'], $promoPath)) {
                $stmt = $conn->prepare("UPDATE clients SET promo_image = ? WHERE id = ?");
                $stmt->execute([$promoPath, $client_id]);
            }
        }

        header("Location: manage_content.php?client_id=" . $client_id);
        exit();
    }
}

// 📌 Suppression d'une image (logo, header ou promo)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_image'])) {
    if ($client_id) {
        $image_type = $_POST['delete_image'];
        $column = ($image_type === "logo") ? "logo" : ($image_type === "header" ? "header_image" : "promo_image");

        // Vérifier si l'image existe et la supprimer
        if (!empty($client[$column]) && file_exists($client[$column])) {
            unlink($client[$column]);
        }

        // Mettre à jour la base de données
        $stmt = $conn->prepare("UPDATE clients SET $column = NULL WHERE id = ?");
        $stmt->execute([$client_id]);

        header("Location: manage_content.php?client_id=" . $client_id);
        exit();
    }
}

// Génération du code d'intégration
$landing_url = "https://frenchyconciergerie.fr/cdansmaville/admin/landing.php?client_id=" . $client_id;
$iframe_code = "<iframe src=\"$landing_url\" width=\"100%\" height=\"800\" frameborder=\"0\"></iframe>";
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
                <label for="logo">Logo :</label>
                <input type="file" name="logo" accept="image/*">
                <?php if (!empty($client['logo'])): ?>
                    <img src="<?php echo $client['logo']; ?>" alt="Logo" width="150">
                    <button type="submit" name="delete_image" value="logo">Supprimer</button>
                <?php endif; ?>

                <label for="header_image">Image d'en-tête :</label>
                <input type="file" name="header_image" accept="image/*">
                <?php if (!empty($client['header_image'])): ?>
                    <img src="<?php echo $client['header_image']; ?>" alt="Image d'en-tête" width="300">
                    <button type="submit" name="delete_image" value="header">Supprimer</button>
                <?php endif; ?>

                <label for="promo_image">Image Promotionnelle :</label>
                <input type="file" name="promo_image" accept="image/*">
                <?php if (!empty($client['promo_image'])): ?>
                    <img src="<?php echo $client['promo_image']; ?>" alt="Promo Image" width="300">
                    <button type="submit" name="delete_image" value="promo">Supprimer</button>
                <?php endif; ?>

                <button type="submit" name="upload_images">Enregistrer</button>
            </form>

            <h2>Code d'Intégration</h2>
            <textarea style="width:100%; height:80px;" readonly><?php echo htmlspecialchars($iframe_code); ?></textarea>
        <?php endif; ?>
    </main>
</body>
</html>
