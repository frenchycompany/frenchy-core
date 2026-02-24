<?php
include '../config.php'; // Connexion à la base de données

$logement_id = filter_input(INPUT_POST, 'logement_id', FILTER_VALIDATE_INT);

if (!$logement_id) {
    echo "Erreur : ID du logement invalide.";
    exit;
}

// Récupérer toutes les données postées
$data = $_POST;
unset($data['logement_id']); // Supprimer l'ID car il est utilisé dans la clause WHERE

// Construire la requête d'insertion ou de mise à jour
$fields = implode(", ", array_map(fn($key) => "$key = :$key", array_keys($data)));

$stmt = $conn->prepare("
    INSERT INTO description_logements (logement_id, " . implode(", ", array_keys($data)) . ")
    VALUES (:logement_id, " . implode(", ", array_map(fn($key) => ":$key", array_keys($data))) . ")
    ON DUPLICATE KEY UPDATE $fields
");

$params = array_merge(['logement_id' => $logement_id], $data);
if ($stmt->execute($params)) {
    echo "Données sauvegardées avec succès.";
} else {
    echo "Erreur lors de la sauvegarde des données.";
}
