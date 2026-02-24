<?php
require_once '../db/connection.php';

$file_url = "https://frenchyconciergerie.fr/bienvenue/guide_bienvenue.pdf"; // Lien direct vers le PDF
$error = "";
$success = "";

// Vérifier si le formulaire a été soumis
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Récupération et nettoyage des données du formulaire
    $name  = isset($_POST['name']) ? trim($_POST['name']) : "";
    $email = isset($_POST['email']) ? trim($_POST['email']) : "";

    // Sécuriser le nom et filtrer l'email
    $name  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);

    if (!empty($name) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            // Enregistrer les informations dans la base
            $stmt = $conn->prepare("INSERT INTO downloads (name, email, download_date) VALUES (?, ?, NOW())");
            $stmt->execute([$name, $email]);

            $success = "Merci ! Votre téléchargement va commencer dans quelques instants.";

            // Ouvrir automatiquement le PDF dans un nouvel onglet
            echo "<script>window.open('$file_url', '_blank');</script>";
        } catch (Exception $e) {
            $error = "❌ Erreur lors de l'enregistrement. Veuillez réessayer.";
        }
    } else {
        $error = "❌ Veuillez entrer un nom et un email valide.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Obtenez votre Guide</title>
    <style>
        .container {
            max-width: 400px;
            margin: 40px auto;
            padding: 20px;
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            font-family: Arial, sans-serif;
        }
        h2 { text-align: center; }
        label { font-weight: bold; display: block; margin-top: 10px; }
        input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            width: 100%;
            background: #007bff;
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            margin-top: 15px;
        }
        .message {
            text-align: center;
            padding: 10px;
            margin-top: 10px;
            font-weight: bold;
        }
        .error { background: #f8d7da; color: #721c24; }
        .success { background: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Obtenez votre Guide</h2>

        <?php if (!empty($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="message success"><?php echo $success; ?></div>
            <div class="message">
                <a href="<?php echo $file_url; ?>" target="_blank">Cliquez ici pour télécharger le guide</a>
            </div>
        <?php else: ?>
            <form method="post">
                <label>Votre Nom :</label>
                <input type="text" name="name" required>

                <label>Votre Email :</label>
                <input type="email" name="email" required>

                <button type="submit">📥 Télécharger</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
