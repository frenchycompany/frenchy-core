<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'errors.log');
header('Content-Type: application/json');

require 'db/connection.php';

try {
    // Récupérer les données POST
    $input = json_decode(file_get_contents('php://input'), true);
    $imageid = $input['imageid'] ?? null;
    $newContent = $input['new_content'] ?? null;

    if (!$imageid || !$newContent) {
        throw new Exception('ID de l\'image ou nouveau contenu manquant.');
    }

    // Mettre à jour la base de données
    $stmt = $conn->prepare("UPDATE image_process SET image_content = ?, process_chatgpt = 'ok' WHERE imageid = ?");
    $stmt->execute([$newContent, $imageid]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
