<?php
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=UTF-8');

require 'db/connection.php'; // Connexion à la base de données

try {
    // Vérification de l'ID reçu
    if (!isset($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID de l\'événement manquant.']);
        exit;
    }

    $id = intval($_GET['id']);

    // Récupérer les données de l'événement depuis la base
    $stmt = $conn->prepare("SELECT * FROM structured_events WHERE id = ?");
    $stmt->execute([$id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    // Vérifier si l'événement existe
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Événement non trouvé.']);
        exit;
    }

    // Retourner les données de l'événement en JSON
    echo json_encode(['success' => true, 'event' => $event], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>
