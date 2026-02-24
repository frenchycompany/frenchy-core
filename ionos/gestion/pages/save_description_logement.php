<?php
include '../config.php'; // Connexion à la base de données

$logement_id = filter_input(INPUT_POST, 'logement_id', FILTER_VALIDATE_INT);
if (!$logement_id) {
    echo "Erreur : ID du logement invalide.";
    exit;
}

// Récupérer toutes les données postées
$data = $_POST;

// Vérifier si la fiche existe déjà
try {
    $stmt = $conn->prepare("SELECT id FROM description_logements WHERE logement_id = ?");
    $stmt->execute([$logement_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erreur lors de la vérification : " . $e->getMessage();
    exit;
}

// Retirer les champs non valides (comme description_id)
unset($data['description_id']); // Supprime la clé qui ne correspond à aucune colonne
unset($data['logement_id']); // Supprime `logement_id` pour ne pas l'inclure dans les champs à mettre à jour

// Préparer les colonnes pour l'insertion ou la mise à jour
$fields = implode(", ", array_map(fn($key) => "$key = :$key", array_keys($data)));
$params = array_merge(['logement_id' => $logement_id], $data);

try {
    if ($existing) {
        // Mise à jour
        $stmt = $conn->prepare("
            UPDATE description_logements SET $fields WHERE logement_id = :logement_id
        ");
        $stmt->execute($params);
        echo "Données mises à jour avec succès.";
    } else {
        // Insertion
        $columns = implode(", ", array_keys($params));
        $placeholders = implode(", ", array_map(fn($key) => ":$key", array_keys($params)));
        $stmt = $conn->prepare("
            INSERT INTO description_logements ($columns) VALUES ($placeholders)
        ");
        $stmt->execute($params);
        echo "Données insérées avec succès.";
    }
} catch (PDOException $e) {
    echo "Erreur lors de la sauvegarde : " . $e->getMessage();
}
