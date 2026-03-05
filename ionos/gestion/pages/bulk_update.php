<?php
// pages/bulk_update.php
declare(strict_types=1);

// IMPORTANT : ne pas afficher les notices/warings pour ne pas casser le JSON retourné
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once '../config.php';
session_start();

// Vérif session / rôle
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Accès refusé.']);
    exit;
}

// Lecture des inputs depuis JSON ou POST
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    // Lecture depuis JSON
    $jsonData = json_decode(file_get_contents('php://input'), true);
    $ids = $jsonData['intervention_ids'] ?? [];
    $newStatus = $jsonData['new_status'] ?? '';
} else {
    // Lecture depuis POST classique
    $ids = $_POST['intervention_ids'] ?? [];
    $newStatus = $_POST['new_status'] ?? '';
}

// DEBUG - Enregistrer ce qui est reçu
error_log("DEBUG bulk_update - Content-Type: " . $contentType);
error_log("DEBUG bulk_update - IDs reçus: " . json_encode($ids));
error_log("DEBUG bulk_update - Type IDs: " . gettype($ids));
error_log("DEBUG bulk_update - Statut reçu: " . json_encode($newStatus));

if (!is_array($ids) || empty($ids)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Aucune intervention sélectionnée.', 'debug_ids' => $ids, 'debug_type' => gettype($ids)]);
    exit;
}

// Whitelist des statuts autorisés
$allowedStatuses = ['À Faire', 'À Vérifier', 'Fait', 'Vérifier', 'Annulé'];
if (!in_array($newStatus, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Statut invalide.',
        'debug_status_received' => $newStatus,
        'debug_allowed' => $allowedStatuses
    ]);
    exit;
}

// Filtre & sécurise les IDs (entiers positifs)
$ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Liste d’IDs invalide.']);
    exit;
}

try {
    // Construction dynamique des placeholders
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sql = "UPDATE planning SET statut = ? WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);

    // Bind : 1er param = statut, puis tous les IDs
    $params = array_merge([$newStatus], $ids);
    $stmt->execute($params);

    echo json_encode([
        'status' => 'success',
        'updated_count' => $stmt->rowCount(),
        'updated_ids' => $ids,
        'new_status' => $newStatus
    ]);
} catch (Throwable $e) {
    // Log serveur si besoin : error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur lors de la mise à jour.']);
}
