<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

// Logger ce qui est reçu
$log = [
    'received_ical_url' => $input['ical_url'] ?? 'NOT FOUND',
    'all_input' => $input
];

// Faire l'update comme dans travel_accounts_api.php
$connection_id = $input['connection_id'] ?? null;
$ical_url = $input['ical_url'] ?? '';

if ($connection_id) {
    $stmt = $pdo->prepare("
        UPDATE travel_account_connections
        SET ical_url = ?
        WHERE id = ?
    ");

    $stmt->execute([$ical_url, $connection_id]);

    // Vérifier ce qui est dans la base
    $stmt = $pdo->prepare("SELECT ical_url FROM travel_account_connections WHERE id = ?");
    $stmt->execute([$connection_id]);
    $saved = $stmt->fetchColumn();

    $log['ical_url_sent_to_db'] = $ical_url;
    $log['ical_url_in_db'] = $saved;
    $log['success'] = ($saved === $ical_url);
}

echo json_encode($log, JSON_PRETTY_PRINT);
?>