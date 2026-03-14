<?php
header('Content-Type: application/json');
ini_set('display_errors', 0); // Don't display errors in JSON response

include '../config.php';
require_once __DIR__ . '/../includes/rpi_db.php';
$pdo = getRpiPdo();

// Lire le JSON du POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'JSON invalide']);
    exit;
}

$receiver = $data['receiver'] ?? '';
$message = $data['message'] ?? '';
$reservation_id = $data['reservation_id'] ?? null;

// Validation
if (empty($receiver)) {
    echo json_encode(['success' => false, 'message' => 'Numéro de téléphone requis']);
    exit;
}

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message vide']);
    exit;
}

if (strlen($message) > 1600) {
    echo json_encode(['success' => false, 'message' => 'Message trop long (max 1600 caractères)']);
    exit;
}

try {
    // Normaliser le numéro de téléphone
    $receiver_clean = preg_replace('/[^0-9+]/', '', $receiver);

    // S'assurer que le numéro est en format international (+33...)
    if (!str_starts_with($receiver_clean, '+')) {
        if (str_starts_with($receiver_clean, '0')) {
            $receiver_clean = '+33' . substr($receiver_clean, 1);
        } elseif (!str_starts_with($receiver_clean, '33')) {
            $receiver_clean = '+33' . $receiver_clean;
        } else {
            $receiver_clean = '+' . $receiver_clean;
        }
    }

    // --- 1. Insérer dans la table Gammu 'outbox' pour envoi réel ---
    $stmt_gammu = $pdo->prepare("
        INSERT INTO outbox (
            DestinationNumber,
            TextDecoded,
            CreatorID,
            Coding,
            Class,
            InsertIntoDB,
            SendingTimeOut,
            DeliveryReport
        ) VALUES (
            :receiver,
            :message,
            'WebApp',
            'Default_No_Compression',
            -1,
            NOW(),
            NOW(),
            'default'
        )
    ");

    $stmt_gammu->execute([
        ':receiver' => $receiver_clean,
        ':message' => $message
    ]);

    $gammu_id = $pdo->lastInsertId();

    // --- 2. Insérer dans notre table 'sms_outbox' pour historique ---
    $stmt_history = $pdo->prepare("
        INSERT INTO sms_outbox (
            receiver,
            message,
            status,
            sent_at,
            reservation_id,
            gammu_outbox_id
        ) VALUES (
            :receiver,
            :message,
            'pending',
            NOW(),
            :reservation_id,
            :gammu_id
        )
    ");

    $stmt_history->execute([
        ':receiver' => $receiver_clean,
        ':message' => $message,
        ':reservation_id' => $reservation_id,
        ':gammu_id' => $gammu_id
    ]);

    // Succès
    echo json_encode([
        'success' => true,
        'message' => 'SMS ajouté à la file d\'envoi',
        'outbox_id' => $gammu_id,
        'receiver' => $receiver_clean
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur interne est survenue.'
    ]);
    error_log('send_sms.php: ' . $e->getMessage());
    exit;
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur interne est survenue.'
    ]);
    error_log('send_sms.php: ' . $e->getMessage());
    exit;
}
?>