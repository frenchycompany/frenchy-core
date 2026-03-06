<?php
include '../config.php'; // Connexion à la base de données

// Valider l'ID du logement passé en paramètre
$logement_id = filter_input(INPUT_GET, 'logement_id', FILTER_VALIDATE_INT);

if (!$logement_id) {
    echo json_encode(['error' => 'ID du logement invalide']);
    exit;
}

try {
    // Récupérer les données du logement avec les infos propriétaire si disponibles
    $stmt = $conn->prepare("
        SELECT l.*,
               p.nom AS proprietaire_nom,
               p.prenom AS proprietaire_prenom,
               p.email AS proprietaire_email,
               p.telephone AS proprietaire_telephone,
               p.adresse AS proprietaire_adresse
        FROM liste_logements l
        LEFT JOIN FC_proprietaires p ON l.proprietaire_id = p.id
        WHERE l.id = ?
    ");
    $stmt->execute([$logement_id]);
    $logement_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Vérifier si des données ont été récupérées
    if (!$logement_data) {
        echo json_encode(['error' => 'Aucun logement trouvé avec cet ID.']);
        exit;
    }

    // Retourner les données sous forme JSON
    echo json_encode($logement_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur lors de la récupération des données : ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur inattendue : ' . $e->getMessage()]);
    exit;
}
