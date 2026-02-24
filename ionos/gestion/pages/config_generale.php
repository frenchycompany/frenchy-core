<?php
// config_general.php
include '../config.php'; // Connexion à la base de données
include '../pages/menu.php'; // Menu de navigation

// Vérifie si l'utilisateur est admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../error.php?message=Accès non autorisé.");
    exit;
}

// Gestion des paramètres
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_site = filter_input(INPUT_POST, 'nom_site', FILTER_SANITIZE_STRING);
    $email_contact = filter_input(INPUT_POST, 'email_contact', FILTER_SANITIZE_EMAIL);
    $mode_maintenance = isset($_POST['mode_maintenance']) ? 1 : 0;
    $footer_text = filter_input(INPUT_POST, 'footer_text', FILTER_SANITIZE_STRING);

    try {
        $stmt = $conn->prepare("
            UPDATE configuration 
            SET nom_site = ?, email_contact = ?, mode_maintenance = ?, footer_text = ?
            WHERE id = 1
        ");
        $stmt->execute([$nom_site, $email_contact, $mode_maintenance, $footer_text]);
        $message = "Configuration mise à jour avec succès.";
    } catch (PDOException $e) {
        $message = "Erreur lors de la mise à jour : " . $e->getMessage();
    }
}

// Récupération des paramètres actuels
$config_query = $conn->query("SELECT * FROM configuration LIMIT 1");
$config = $config_query->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
           <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h2 class="text-center">Configuration Générale</h2>

    <?php if (isset($message)): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="nom_site">Nom du site :</label>
            <input type="text" name="nom_site" id="nom_site" class="form-control" value="<?= htmlspecialchars($config['nom_site']) ?>" required>
        </div>
        <div class="form-group">
            <label for="email_contact">Email de contact :</label>
            <input type="email" name="email_contact" id="email_contact" class="form-control" value="<?= htmlspecialchars($config['email_contact']) ?>" required>
        </div>
        <div class="form-group">
            <label for="footer_text">Texte du pied de page :</label>
            <textarea name="footer_text" id="footer_text" class="form-control" rows="3"><?= htmlspecialchars($config['footer_text']) ?></textarea>
        </div>
        <div class="form-group form-check">
            <input type="checkbox" name="mode_maintenance" id="mode_maintenance" class="form-check-input" <?= $config['mode_maintenance'] ? 'checked' : '' ?>>
            <label for="mode_maintenance" class="form-check-label">Activer le mode maintenance</label>
        </div>
        <button type="submit" class="btn btn-primary">Mettre à jour</button>
    </form>
</div>

<!-- Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
