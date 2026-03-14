<?php
// pages/bulk_delete.php
declare(strict_types=1);

ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

require_once '../config.php';

// Vérif session / rôle
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Accès refusé.']);
    exit;
}

// Lecture des inputs depuis JSON ou POST
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $jsonData = json_decode(file_get_contents('php://input'), true);
    $ids = $jsonData['intervention_ids'] ?? [];
} else {
    $ids = $_POST['intervention_ids'] ?? [];
}

if (!is_array($ids) || empty($ids)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Aucune intervention sélectionnée.']);
    exit;
}

// Filtre & sécurise les IDs (entiers positifs)
$ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Liste d\'IDs invalide.']);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // Nettoyer la compta associée (best effort)
    try {
        $delCompta = $conn->prepare("DELETE FROM comptabilite WHERE source_typeIndex = 'intervention' AND source_idIndex IN ($placeholders)");
        $delCompta->execute($ids);
    } catch (Throwable $e) { error_log('bulk_delete.php: ' . $e->getMessage()); }

    // Nettoyer les tokens associés (best effort)
    try {
        $delTokens = $conn->prepare("DELETE FROM intervention_tokens WHERE intervention_id IN ($placeholders)");
        $delTokens->execute($ids);
    } catch (Throwable $e) { error_log('bulk_delete.php: ' . $e->getMessage()); }

    // Supprimer les interventions
    $stmt = $conn->prepare("DELETE FROM planning WHERE id IN ($placeholders)");
    $stmt->execute($ids);

    echo json_encode([
        'status' => 'success',
        'deleted_count' => $stmt->rowCount(),
        'deleted_ids' => $ids
    ]);
} catch (Throwable $e) {
    error_log("bulk_delete error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Une erreur interne est survenue.']);
}
