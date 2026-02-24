<?php
require_once '../db/connection.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    $name = htmlspecialchars($_POST['user_name']);
    $email = filter_var($_POST['user_email'], FILTER_SANITIZE_EMAIL);
    $file_url = filter_var($_POST['file_url'], FILTER_SANITIZE_URL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($name)) {
        echo json_encode(["success" => false, "message" => "❌ Veuillez entrer un nom et une adresse email valide."]);
        exit();
    }

    try {
        // 🔥 Enregistrement en base de données
        $stmt = $conn->prepare("INSERT INTO downloads (client_id, name, email, file_url, download_date) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$client_id, $name, $email, $file_url]);

        // 🔥 Envoi d'email au client
        $to = $email;
        $subject = "📥 Téléchargement du fichier";
        $headers = "From: contact@votre-site.com\r\nReply-To: contact@votre-site.com\r\nContent-Type: text/plain; charset=UTF-8";
        $body = "Bonjour $name,\n\nMerci pour votre téléchargement.\nVoici votre fichier :\n$file_url\n\nCordialement,\nL'équipe de Votre Site.";

        mail($to, $subject, $body, $headers);

        // 🔥 Réponse JSON
        echo json_encode(["success" => true, "message" => "✅ Téléchargement en cours...", "file_url" => $file_url]);
    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => "❌ Erreur serveur : " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "❌ Requête invalide."]);
}
?>
