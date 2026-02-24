<?php
/**
 * Contact form handler — Envoie un email au propriétaire.
 * Appelé en AJAX depuis main.js ou en POST classique.
 */
require_once __DIR__ . '/db/connection.php';
require_once __DIR__ . '/db/helpers.php';

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

// Rate limiting par IP (max 5 messages / 15 min)
session_start();
$now = time();
$_SESSION['vf_contact_times'] = array_filter(
    $_SESSION['vf_contact_times'] ?? [],
    function ($t) use ($now) { return $t > $now - 900; }
);
if (count($_SESSION['vf_contact_times'] ?? []) >= 5) {
    $error = 'Trop de messages envoyés. Veuillez patienter 15 minutes.';
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }
    header('Location: /?contact=error#contact');
    exit;
}

// Honeypot check
if (!empty($_POST['website'])) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    header('Location: /?contact=ok#contact');
    exit;
}

// Validate inputs
$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!$name || !$email || !$message) {
    $error = 'Veuillez remplir tous les champs obligatoires.';
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }
    header('Location: /?contact=error#contact');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Adresse email invalide.';
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }
    header('Location: /?contact=error#contact');
    exit;
}

// Sanitize
$name    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

// Load site config for recipient email
$settings = vf_load_settings($conn);
$site     = vf_build_site_config($settings);
$to       = $site['email'];

if (!$to) {
    error_log('[VF] Contact form: no recipient email configured');
    $error = 'Erreur de configuration. Veuillez nous contacter par téléphone.';
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }
    header('Location: /?contact=error#contact');
    exit;
}

// Build email
$mail_subject = '[' . $site['name'] . '] ' . ($subject ?: 'Nouveau message de ' . $name);
$mail_body = "Nouveau message depuis le site web\n";
$mail_body .= "═══════════════════════════════\n\n";
$mail_body .= "Nom : {$name}\n";
$mail_body .= "Email : {$email}\n";
if ($subject) {
    $mail_body .= "Sujet : {$subject}\n";
}
$mail_body .= "\nMessage :\n{$message}\n";
$mail_body .= "\n═══════════════════════════════\n";
$mail_body .= "Envoyé depuis " . $site['name'] . " le " . date('d/m/Y à H:i');

$headers  = "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Mailer: VF-Contact/1.0\r\n";

$sent = @mail($to, $mail_subject, $mail_body, $headers);

if ($sent) {
    $_SESSION['vf_contact_times'][] = $now;
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    header('Location: /?contact=ok#contact');
    exit;
} else {
    error_log('[VF] Contact form: mail() failed to ' . $to);
    $error = 'Erreur lors de l\'envoi. Veuillez réessayer ou nous contacter par téléphone.';
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }
    header('Location: /?contact=error#contact');
    exit;
}
