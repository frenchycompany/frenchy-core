<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1); // Remove in production

include '../includes/db.php'; // Adjust path if needed

$response = ['error' => null, 'messages' => []];
$sender_param = $_GET['sender'] ?? null; // The 'sender' from conversation view is the 'receiver' in outbox

if (empty($sender_param)) {
    $response['error'] = 'Numéro de destinataire manquant.';
    echo json_encode($response);
    exit;
}

// Clean the number just in case
$receiver_clean = preg_replace('/[^0-9+]/', '', $sender_param);

if (empty($receiver_clean)) {
     $response['error'] = 'Numéro de destinataire invalide.';
     echo json_encode($response);
     exit;
}


try {
    // Fetch messages from sms_outbox for this receiver
    // Limit results for performance if needed, e.g., last 50 messages
    $stmt = $conn->prepare("
        SELECT 
            id, 
            message, 
            status, 
            sent_at, 
            modem 
        FROM sms_outbox 
        WHERE receiver = ? 
        ORDER BY COALESCE(sent_at, '1970-01-01') DESC, id DESC 
        LIMIT 50 
    "); // Order by sent time (if available), then ID
    $stmt->execute([$receiver_clean]);
    $response['messages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Reverse the array so the oldest fetched appears first when prepending
    $response['messages'] = array_reverse($response['messages']); 

} catch (PDOException $e) {
    // error_log("Error fetching sent messages for $receiver_clean: " . $e->getMessage());
    $response['error'] = 'Erreur lors de la récupération des messages envoyés.';
    // $response['error'] .= ' Details: ' . $e->getMessage(); // Debug only
}

echo json_encode($response);
?>