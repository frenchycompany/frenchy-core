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

// Lecture des inputs
$ids = $_POST['intervention_ids'] ?? [];
$newStatus = $_POST['new_status'] ?? '';

if (!is_array($ids) || empty($ids)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Aucune intervention sélectionnée.']);
    exit;
}

// Whitelist des statuts autorisés
$allowedStatuses = ['À Faire', 'À Vérifier', 'Fait', 'Vérifier'];
if (!in_array($newStatus, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Statut invalide.']);
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
