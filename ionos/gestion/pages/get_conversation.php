<?php
/**
 * API AJAX — Charge les messages d'une conversation (reçus + envoyés)
 * Adapté depuis raspberry-pi/web/pages/get_conversation.php
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/error_handler.php';
require_once __DIR__ . '/../includes/rpi_db.php';

try {
    $pdo = getRpiPdo();

    $sender_raw = $_GET['sender'] ?? '';
    if ($sender_raw === '') {
        echo json_encode(['error' => 'Numéro de téléphone manquant']);
        exit;
    }

    $sender_e164 = preg_replace('/[^0-9+]/', '', $sender_raw);
    $sender_0    = preg_replace('/^\+33/', '0', $sender_e164);

    // Messages reçus
    $st_in = $pdo->prepare("
        SELECT id, sender AS phone, message, received_at AS date,
               'in' AS direction, 'delivered' AS status
        FROM sms_in
        WHERE sender = :s1 OR sender = :s2
        ORDER BY received_at ASC
    ");
    $st_in->execute([':s1' => $sender_e164, ':s2' => $sender_0]);
    $messages_in = $st_in->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Messages envoyés
    $messages_out = [];
    $st_out = $pdo->prepare("
        SELECT id, receiver AS phone, message, COALESCE(sent_at, created_at) AS date,
               'out' AS direction, status
        FROM sms_outbox
        WHERE receiver = :s1 OR receiver = :s2
        ORDER BY COALESCE(sent_at, created_at) ASC
    ");
    $st_out->execute([':s1' => $sender_e164, ':s2' => $sender_0]);
    $messages_out = $st_out->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Normaliser
    foreach ($messages_in as &$r) {
        $r['phone'] = (string)($r['phone'] ?? '');
        $r['message'] = (string)($r['message'] ?? '');
        $r['date'] = (string)($r['date'] ?? '');
    }
    unset($r);
    foreach ($messages_out as &$r) {
        $r['phone'] = (string)($r['phone'] ?? '');
        $r['message'] = (string)($r['message'] ?? '');
        $r['date'] = (string)($r['date'] ?? '');
        $r['status'] = (string)($r['status'] ?? 'pending');
    }
    unset($r);

    // Fusion + tri chronologique
    $all = array_merge($messages_in, $messages_out);
    usort($all, function ($a, $b) {
        return strcmp($a['date'], $b['date']);
    });

    echo json_encode($all, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    error_log('get_conversation.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Une erreur interne est survenue.'], JSON_UNESCAPED_UNICODE);
}
