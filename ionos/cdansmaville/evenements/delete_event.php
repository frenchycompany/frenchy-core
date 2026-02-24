<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'errors.log');
header('Content-Type: application/json');

require 'db/connection.php'; // Connexion à la base de données

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eventId = $_POST['eventid'] ?? null; // L'ID de l'événement envoyé via POST

    if (!$eventId) {
        echo json_encode(['success' => false, 'message' => 'ID de l\'événement non fourni.']);
        exit;
    }

    try {
        // Vérifier si l'événement existe dans la base
        $stmt = $conn->prepare("SELECT id FROM structured_events WHERE id = ?");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            echo json_encode(['success' => false, 'message' => 'Aucun événement trouvé avec cet ID.']);
            exit;
        }

        // Supprimer l'événement
        $deleteStmt = $conn->prepare("DELETE FROM structured_events WHERE id = ?");
        $deleteStmt->execute([$eventId]);

        if ($deleteStmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Événement supprimé avec succès.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Impossible de supprimer l\'événement.']);
        }
    } catch (PDOException $e) {
        // Gestion des erreurs SQL ou de connexion
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression.', 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Requête invalide.']);
}
