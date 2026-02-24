<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'errors.log');
header('Content-Type: application/json');

require 'db/connection.php'; // Connexion à la base de données

// Vérifier si l'ID est fourni via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imageId = $_POST['imageid'] ?? null;

    if (!$imageId) {
        echo json_encode(['success' => false, 'message' => 'ID de l\'image non fourni.']);
        exit;
    }

    try {
        // Vérifier si l'image existe
        $checkStmt = $conn->prepare("SELECT * FROM image_process WHERE imageid = ?");
        $checkStmt->execute([$imageId]);
        $image = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) {
            echo json_encode(['success' => false, 'message' => 'Aucune image trouvée avec cet ID.']);
            exit;
        }

        // Supprimer l'image de la base de données
        $deleteStmt = $conn->prepare("DELETE FROM image_process WHERE imageid = ?");
        $deleteStmt->execute([$imageId]);

        echo json_encode(['success' => true, 'message' => 'Image supprimée avec succès.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression.', 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Requête invalide.']);
}
?>
