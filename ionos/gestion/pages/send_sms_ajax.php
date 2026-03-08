<?php
/**
 * API AJAX — Envoi de SMS (insertion dans sms_outbox du RPi)
 * Adapté depuis raspberry-pi/web/pages/send_sms_ajax.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/error_handler.php';
require_once __DIR__ . '/../includes/rpi_db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$receiver = trim($_POST['receiver'] ?? '');
$message = trim($_POST['message'] ?? '');
$modem = trim($_POST['modem'] ?? 'modem1');

if (empty($receiver)) {
    echo json_encode(['success' => false, 'message' => 'Le numéro du destinataire est obligatoire']);
    exit;
}
if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Le message est obligatoire']);
    exit;
}

$cleanReceiver = preg_replace('/\s/', '', $receiver);

try {
    $pdo = getRpiPdo();
    $stmt = $pdo->prepare("
        INSERT INTO sms_outbox (receiver, message, modem, status, created_at)
        VALUES (:receiver, :message, :modem, 'pending', NOW())
    ");
    $stmt->execute([
        ':receiver' => $cleanReceiver,
        ':message' => $message,
        ':modem' => $modem
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'SMS mis en file d\'attente',
        'sms_id' => $pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    error_log("Erreur send_sms_ajax.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Une erreur interne est survenue.']);
}
