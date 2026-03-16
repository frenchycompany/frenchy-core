<?php
/**
 * API de recherche globale (AJAX) — FrenchyConciergerie
 * Retourne les résultats en JSON pour la barre de recherche topbar
 */
header('Content-Type: application/json; charset=utf-8');

// Auth : session requise
session_start();
if (!isset($_SESSION['id_intervenant']) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../db/connection.php';

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$searchTerm = '%' . $q . '%';
$results = [];
$limit = 5; // Max par catégorie pour le dropdown

// --- Logements ---
try {
    $stmt = $conn->prepare("
        SELECT id, nom_du_logement, adresse
        FROM liste_logements
        WHERE nom_du_logement LIKE :q1 OR adresse LIKE :q2
        ORDER BY nom_du_logement LIMIT :lim
    ");
    $stmt->bindValue(':q1', $searchTerm);
    $stmt->bindValue(':q2', $searchTerm);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'type' => 'logement',
            'type_label' => 'Logement',
            'title' => $row['nom_du_logement'],
            'subtitle' => $row['adresse'] ?? '',
            'url' => 'pages/logements.php#logement-' . $row['id']
        ];
    }
} catch (PDOException $e) { error_log('search_api logements: ' . $e->getMessage()); }

// --- Réservations ---
try {
    $stmt = $conn->prepare("
        SELECT r.id, r.reference, r.prenom, r.nom, r.date_arrivee, l.nom_du_logement
        FROM reservation r
        LEFT JOIN liste_logements l ON r.logement_id = l.id
        WHERE r.reference LIKE :q1 OR r.prenom LIKE :q2 OR r.nom LIKE :q3
           OR r.email LIKE :q4 OR r.telephone LIKE :q5
           OR CONCAT(r.prenom, ' ', r.nom) LIKE :q6
        ORDER BY r.date_arrivee DESC LIMIT :lim
    ");
    $stmt->bindValue(':q1', $searchTerm);
    $stmt->bindValue(':q2', $searchTerm);
    $stmt->bindValue(':q3', $searchTerm);
    $stmt->bindValue(':q4', $searchTerm);
    $stmt->bindValue(':q5', $searchTerm);
    $stmt->bindValue(':q6', $searchTerm);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'type' => 'reservation',
            'type_label' => 'Réservation',
            'title' => trim(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? '')) . ' — ' . ($row['reference'] ?? '#' . $row['id']),
            'subtitle' => ($row['nom_du_logement'] ?? '') . ' · ' . date('d/m/Y', strtotime($row['date_arrivee'])),
            'url' => 'pages/reservation_details.php?id=' . $row['id']
        ];
    }
} catch (PDOException $e) { error_log('search_api reservations: ' . $e->getMessage()); }

// --- Propriétaires ---
try {
    $stmt = $conn->prepare("
        SELECT id, nom, prenom, email, telephone, societe
        FROM FC_proprietaires
        WHERE nom LIKE :q1 OR prenom LIKE :q2 OR email LIKE :q3
           OR telephone LIKE :q4 OR COALESCE(societe,'') LIKE :q5
           OR CONCAT(prenom, ' ', nom) LIKE :q6
        ORDER BY nom LIMIT :lim
    ");
    $stmt->bindValue(':q1', $searchTerm);
    $stmt->bindValue(':q2', $searchTerm);
    $stmt->bindValue(':q3', $searchTerm);
    $stmt->bindValue(':q4', $searchTerm);
    $stmt->bindValue(':q5', $searchTerm);
    $stmt->bindValue(':q6', $searchTerm);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'type' => 'proprietaire',
            'type_label' => 'Propriétaire',
            'title' => trim(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? '')),
            'subtitle' => $row['societe'] ?? $row['email'] ?? '',
            'url' => 'pages/proprietaires.php?id=' . $row['id']
        ];
    }
} catch (PDOException $e) { error_log('search_api proprietaires: ' . $e->getMessage()); }

// --- Intervenants ---
try {
    $stmt = $conn->prepare("
        SELECT id, nom, role, telephone, email
        FROM intervenant
        WHERE nom LIKE :q1 OR COALESCE(telephone,'') LIKE :q2 OR COALESCE(email,'') LIKE :q3
        ORDER BY nom LIMIT :lim
    ");
    $stmt->bindValue(':q1', $searchTerm);
    $stmt->bindValue(':q2', $searchTerm);
    $stmt->bindValue(':q3', $searchTerm);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'type' => 'intervenant',
            'type_label' => 'Intervenant',
            'title' => $row['nom'],
            'subtitle' => $row['role'] ?? '',
            'url' => 'pages/intervenants.php'
        ];
    }
} catch (PDOException $e) { error_log('search_api intervenants: ' . $e->getMessage()); }

// --- Leads ---
try {
    $stmt = $conn->prepare("
        SELECT id, nom, email, telephone, statut
        FROM prospection_leads
        WHERE nom LIKE :q1 OR COALESCE(email,'') LIKE :q2
           OR COALESCE(telephone,'') LIKE :q3 OR COALESCE(adresse,'') LIKE :q4
        ORDER BY created_at DESC LIMIT :lim
    ");
    $stmt->bindValue(':q1', $searchTerm);
    $stmt->bindValue(':q2', $searchTerm);
    $stmt->bindValue(':q3', $searchTerm);
    $stmt->bindValue(':q4', $searchTerm);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'type' => 'lead',
            'type_label' => 'Lead',
            'title' => $row['nom'],
            'subtitle' => ($row['statut'] ?? '') . ' · ' . ($row['email'] ?? ''),
            'url' => 'pages/prospection_proprietaires.php?id=' . $row['id']
        ];
    }
} catch (PDOException $e) { error_log('search_api leads: ' . $e->getMessage()); }

// --- Clients (depuis reservations) ---
try {
    $stmt = $conn->prepare("
        SELECT
            MAX(r.id) as id,
            GROUP_CONCAT(DISTINCT CONCAT(TRIM(COALESCE(r.prenom,'')), ' ', TRIM(COALESCE(r.nom,''))) SEPARATOR ', ') as names,
            MAX(r.email) as email,
            MAX(r.telephone) as telephone,
            COUNT(*) as nb_resa
        FROM reservation r
        WHERE r.telephone LIKE :q1 OR r.prenom LIKE :q2 OR r.nom LIKE :q3
           OR r.email LIKE :q4 OR CONCAT(r.prenom, ' ', r.nom) LIKE :q5
        GROUP BY REPLACE(REPLACE(REPLACE(r.telephone,' ',''),'.',''),'-','')
        HAVING nb_resa > 0
        ORDER BY nb_resa DESC LIMIT :lim
    ");
    $stmt->bindValue(':q1', $searchTerm);
    $stmt->bindValue(':q2', $searchTerm);
    $stmt->bindValue(':q3', $searchTerm);
    $stmt->bindValue(':q4', $searchTerm);
    $stmt->bindValue(':q5', $searchTerm);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'type' => 'client',
            'type_label' => 'Client',
            'title' => $row['names'],
            'subtitle' => $row['nb_resa'] . ' séjour(s) · ' . ($row['email'] ?? ''),
            'url' => 'pages/clients.php?phone=' . urlencode($row['telephone'] ?? '')
        ];
    }
} catch (PDOException $e) { error_log('search_api clients: ' . $e->getMessage()); }

echo json_encode(['results' => $results, 'query' => $q]);
