<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'errors.log');
header('Content-Type: application/json');

require 'db/connection.php'; // Connexion à la base de données

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'];
$text = $data['text'];

if ($id && $text) {
    $stmt = $conn->prepare("UPDATE image_process SET image_content = ? WHERE imageid = ?");
    $stmt->execute([$text, $id]);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants.']);
}
