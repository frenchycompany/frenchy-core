<?php
session_start();
require_once __DIR__ . '/../db/connection.php';

// 🔹 Vérifier que la requête est bien en POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../modules/cta.php");
    exit();
}

// 🔹 Vérifier les champs obligatoires
if (empty($_POST['email']) || empty($_POST['user_name']) || empty($_POST['user_email']) || empty($_POST['message'])) {
    echo "<script>alert('❌ Tous les champs sont obligatoires.'); window.history.back();</script>";
    exit();
}

// 🔹 Sécuriser les entrées utilisateur
$to = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
$from = filter_var($_POST['user_email'], FILTER_SANITIZE_EMAIL);
$name = htmlspecialchars($_POST['user_name']);
$message = htmlspecialchars($_POST['message']);

if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
    echo "<script>alert('❌ Adresse e-mail invalide.'); window.history.back();</script>";
    exit();
}

// 🔹 Construire l'e-mail
$subject = "📩 Nouveau message de $name";
$headers = "From: $from\r\nReply-To: $from\r\nContent-Type: text/plain; charset=UTF-8";
$body = "Nom: $name\nEmail: $from\n\nMessage:\n$message";

// 🔹 Vérifier si la fonction mail() est activée
if (!function_exists('mail')) {
    echo "<script>alert('❌ L'envoi d'e-mail n'est pas activé sur le serveur.'); window.history.back();</script>";
    exit();
}

// ✅ Envoyer l'e-mail
if (mail($to, $subject, $body, $headers)) {
    echo "<script>alert('✅ Votre message a bien été envoyé.'); window.location.href='../modules/cta.php';</script>";
} else {
    echo "<script>alert('❌ Erreur lors de l\'envoi du message.'); window.history.back();</script>";
}
?>
