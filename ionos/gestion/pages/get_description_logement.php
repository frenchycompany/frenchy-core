<?php
include '../config.php'; // Connexion à la base de données

$logement_id = filter_input(INPUT_GET, 'logement_id', FILTER_VALIDATE_INT);

if (!$logement_id) {
    echo json_encode(['error' => 'ID du logement invalide']);
    exit;
}

try {
    // Vérifier si une fiche descriptive existe
    $stmt = $conn->prepare("SELECT * FROM description_logements WHERE logement_id = ?");
    $stmt->execute([$logement_id]);
    $logement_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si aucune fiche n'existe, créer une entrée par défaut
    if (!$logement_data) {
        $stmt = $conn->prepare("
            INSERT INTO description_logements (logement_id) 
            VALUES (?)
        ");
        $stmt->execute([$logement_id]);

        // Recharger les données après création
        $stmt = $conn->prepare("SELECT * FROM description_logements WHERE logement_id = ?");
        $stmt->execute([$logement_id]);
        $logement_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    echo json_encode($logement_data);
} catch (PDOException $e) {
    error_log('get_description_logement.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Une erreur interne est survenue.']);
}
