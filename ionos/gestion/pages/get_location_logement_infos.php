<?php
/**
 * AJAX endpoint - Donnees logement + details personnalises pour contrats de location
 */
include '../config.php';
header('Content-Type: application/json; charset=utf-8');

$logement_id = filter_input(INPUT_GET, 'logement_id', FILTER_VALIDATE_INT);

if (!$logement_id) {
    echo json_encode(['error' => 'ID du logement invalide']);
    exit;
}

try {
    // Donnees du logement
    $stmt = $conn->prepare("SELECT * FROM liste_logements WHERE id = ?");
    $stmt->execute([$logement_id]);
    $logement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$logement) {
        echo json_encode(['error' => 'Logement introuvable']);
        exit;
    }

    // Details personnalises pour contrats de location
    $details = null;
    try {
        $stmt = $conn->prepare("SELECT * FROM location_contract_logement_details WHERE logement_id = ?");
        $stmt->execute([$logement_id]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table n'existe pas encore
    }

    // Fusionner les donnees
    $result = $logement;

    // Ajouter les details personnalises avec prefixe detail_
    if ($details) {
        $result['detail_description_logement'] = $details['description_logement'] ?? '';
        $result['detail_equipements'] = $details['equipements'] ?? '';
        $result['detail_regles_maison'] = $details['regles_maison'] ?? '';
        $result['detail_heure_arrivee'] = $details['heure_arrivee'] ?? '16:00';
        $result['detail_heure_depart'] = $details['heure_depart'] ?? '10:00';
        $result['detail_depot_garantie'] = $details['depot_garantie'] ?? '';
        $result['detail_taxe_sejour_par_nuit'] = $details['taxe_sejour_par_nuit'] ?? '';
        $result['detail_conditions_annulation'] = $details['conditions_annulation'] ?? '';
        $result['detail_informations_supplementaires'] = $details['informations_supplementaires'] ?? '';
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur SQL : ' . $e->getMessage()]);
}
