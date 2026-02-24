<?php
require_once __DIR__ . '/../db/connection.php';

// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$email = '';

// Vérifier si mail() est activé
if (!function_exists('mail')) {
    die("❌ La fonction mail() n'est pas activée sur votre serveur.");
}

// Récupération de l'email du client
if ($client_id) {
    $stmt = $conn->prepare("SELECT contact_email FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $email = $stmt->fetchColumn();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $from = filter_var($_POST['user_email'], FILTER_SANITIZE_EMAIL);
    $name = trim(htmlspecialchars($_POST['user_name']));
    $message = trim(htmlspecialchars($_POST['message']));

    if (!filter_var($from, FILTER_VALIDATE_EMAIL) || empty($name) || empty($message)) {
        $error = "❌ Veuillez entrer une adresse email valide, un nom et un message.";
    } else {
        $to = $email;
        $subject = "Nouveau message de $name";

        // En-têtes du mail
        $headers = "From: contact@frenchycompany.fr\r\n"; // Adresse du domaine pour éviter le blocage
        $headers .= "Reply-To: $from\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        // Corps du message formaté
        $body = wordwrap("Nom: $name\nEmail: $from\n\nMessage:\n$message", 70);

        // Envoi du mail
        if (mail($to, $subject, $body, $headers)) {
            $success = "✅ Votre message a bien été envoyé.";
        } else {
            $error = "❌ Erreur lors de l'envoi du message. Vérifiez la configuration du serveur.";
        }
    }
}
?>

<style>
.cta-container {
    max-width: 400px;
    margin: auto;
    background: #fff;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    font-family: Arial, sans-serif;
}
.cta-container h2 { text-align: center; color: #333; }
.cta-container label { font-weight: bold; }
.cta-container input, .cta-container textarea {
    width: 100%;
    padding: 10px;
    margin-bottom: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
}
.cta-container button {
    width: 100%;
    background: #007bff;
    color: white;
    border: none;
    padding: 10px;
    cursor: pointer;
}
.message {
    text-align: center;
    padding: 10px;
    margin-bottom: 10px;
    font-weight: bold;
}
.success { background: #d4edda; color: #155724; }
.error { background: #f8d7da; color: #721c24; }
</style>

<section class="cta-container">
    <h2>Contactez-nous</h2>

    <?php if (!empty($success)): ?>
        <div class="message success"><?php echo $success; ?></div>
    <?php elseif (!empty($error)): ?>
        <div class="message error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="post">
        <label for="user_name">Votre Nom :</label>
        <input type="text" name="user_name" required>

        <label for="user_email">Votre Email :</label>
        <input type="email" name="user_email" required>

        <label for="message">Votre Message :</label>
        <textarea name="message" required></textarea>

        <button type="submit">Envoyer</button>
    </form>
</section>
