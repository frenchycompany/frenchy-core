<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    $recipient_email = $_POST['email'] ?? '';
    $name = $_POST['name'] ?? '';
    $user_email = $_POST['user_email'] ?? '';
    $message = $_POST['message'] ?? '';

    // 📌 Vérifier que tous les champs sont remplis
    if (empty($recipient_email) || empty($name) || empty($user_email) || empty($message)) {
        header("Location: ../preview_landing.php?client_id=$client_id&status=error");
        exit();
    }

    // 📧 Préparer l'email
    $subject = "Nouveau message de $name";
    $headers = "From: $user_email\r\n";
    $headers .= "Reply-To: $user_email\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    // 📌 Envoi de l'email
    $mailSent = mail($recipient_email, $subject, $message, $headers);

    if ($mailSent) {
        header("Location: ../preview_landing.php?client_id=$client_id&status=success");
    } else {
        header("Location: ../preview_landing.php?client_id=$client_id&status=error");
    }
    exit();
}
