<?php
include '../config.php'; // Connexion à la base de données

$logement_id = filter_input(INPUT_GET, 'logement_id', FILTER_VALIDATE_INT);

if (!$logement_id) {
    echo json_encode(['error' => 'ID du logement invalide']);
    exit;
}

// Récupérer la fiche descriptive
$stmt = $conn->prepare("SELECT * FROM description_logements WHERE logement_id = ?");
$stmt->execute([$logement_id]);
$logement_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Si aucune fiche descriptive n'existe, retourner un tableau vide avec les colonnes par défaut
if (!$logement_data) {
    $stmt = $conn->query("SHOW COLUMNS FROM description_logements");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $logement_data = array_fill_keys($columns, '');
    $logement_data['logement_id'] = $logement_id; // Associer le logement sélectionné
}

echo json_encode($logement_data);
