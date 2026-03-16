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

// Normalisation téléphone : retirer espaces, points, tirets pour comparaison
$phoneDigits = preg_replace('/[^0-9+]/', '', $q);
$phoneVariants = [];
if (strlen($phoneDigits) >= 4) {
    $phoneVariants[] = '%' . $phoneDigits . '%';
    // 0612... → aussi chercher +33612...
    if (strlen($phoneDigits) === 10 && $phoneDigits[0] === '0') {
        $phoneVariants[] = '%+33' . substr($phoneDigits, 1) . '%';
        $phoneVariants[] = '%0033' . substr($phoneDigits, 1) . '%';
    }
    // +33612... → aussi chercher 0612...
    if (substr($phoneDigits, 0, 3) === '+33') {
        $phoneVariants[] = '%0' . substr($phoneDigits, 3) . '%';
    }
    if (substr($phoneDigits, 0, 4) === '0033') {
        $phoneVariants[] = '%0' . substr($phoneDigits, 4) . '%';
    }
}
$isPhoneSearch = !empty($phoneVariants);

// Construire clause SQL pour recherche téléphone (compare sans espaces/points/tirets)
function phoneWhereClause($column, $paramPrefix, &$bindings, $phoneVariants) {
    if (empty($phoneVariants)) return '';
    $clauses = [];
    $stripped = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($column,' ',''),'.',''),'-',''),'(',''),')','')";
    foreach ($phoneVariants as $i => $variant) {
        $p = $paramPrefix . $i;
        $clauses[] = "$stripped LIKE :$p";
        $bindings[":$p"] = $variant;
    }
    return '(' . implode(' OR ', $clauses) . ')';
}

// --- Pages / Outils du menu ---
require_once __DIR__ . '/../pages/menu_categories.php';

// Mots-clés associés aux pages pour élargir la recherche
$page_keywords = [
    'proprietaires.php' => 'propriétaire propriétaires owner bailleur bailleurs',
    'logements.php' => 'logement logements appartement maison bien biens',
    'logement_equipements.php' => 'équipement équipements wifi digicode clé clés',
    'planning.php' => 'planning ménage intervention interventions',
    'editer_planning.php' => 'planning éditer modifier intervention ménage',
    'intervenants.php' => 'intervenant intervenants femme ménage agent agents équipe',
    'reservations.php' => 'réservation réservations booking résa',
    'calendrier.php' => 'calendrier planning disponibilité disponibilités',
    'comptabilite.php' => 'comptabilité comptes paiement paiements argent finance',
    'facturation.php' => 'facture factures facturation propriétaire',
    'create_contract.php' => 'contrat conciergerie créer nouveau propriétaire',
    'contrats_generes.php' => 'contrat contrats générés conciergerie propriétaire',
    'create_location_contract.php' => 'contrat location bail créer locataire',
    'location_contrats_generes.php' => 'contrat contrats location bail générés',
    'prospection_proprietaires.php' => 'lead leads prospection propriétaire CRM commercial',
    'simulations.php' => 'simulation simuler rentabilité revenu propriétaire',
    'clients.php' => 'client clients voyageur voyageurs carnet',
    'sms_recus.php' => 'sms message messages reçus',
    'sms_envoyer.php' => 'sms envoyer message',
    'sms_templates.php' => 'sms template modèle message',
    'sms_automations.php' => 'sms automatisation auto robot',
    'checkup_logement.php' => 'checkup vérification état logement',
    'inventaire.php' => 'inventaire objet objets stock',
    'superhote.php' => 'tarif tarifs prix superhôte',
    'statistiques.php' => 'statistique statistiques stats chiffres',
    'coffre_fort.php' => 'coffre-fort coffre document documents sécurisé',
    'todo.php' => 'todo tâche tâches à faire',
    'rdv_agenda.php' => 'rendez-vous rdv agenda',
    'sync_ical.php' => 'sync ical calendrier synchronisation',
    'occupation.php' => 'occupation taux remplissage',
    'admin_site_conciergerie.php' => 'site vitrine conciergerie marketing',
    'sites.php' => 'site sites vitrine logement',
    'description_logements.php' => 'description annonce texte logement',
    'machines.php' => 'machine machines laverie lave-linge',
    'villes.php' => 'ville villes commune',
    'import_photos_airbnb.php' => 'photo photos image airbnb',
    'relances_voyageurs.php' => 'relance relances voyageur avis',
    'analyse_marche.php' => 'analyse marché concurrence prix',
    'audit_lcd.php' => 'audit lcd location courte durée réglementation',
    'analyse_concurrence.php' => 'concurrence concurrent benchmark',
];

$qLower = mb_strtolower($q);
foreach ($menu_categories as $cat_name => $cat) {
    foreach ($cat['items'] as $item) {
        $filename = basename($item['chemin']);
        $searchable = mb_strtolower($item['nom'] . ' ' . $cat_name . ' ' . ($page_keywords[$filename] ?? ''));
        if (mb_strpos($searchable, $qLower) !== false) {
            $results[] = [
                'type' => 'page',
                'type_label' => $cat_name,
                'title' => $item['nom'],
                'subtitle' => 'Ouvrir la page',
                'url' => $item['chemin'],
                'icon' => $item['icon'] ?? 'fa-file'
            ];
        }
    }
}

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
    $phoneBind = [];
    $phoneWhere = $isPhoneSearch ? phoneWhereClause('r.telephone', 'rp', $phoneBind, $phoneVariants) : '';
    $sql = "
        SELECT r.id, r.reference, r.prenom, r.nom, r.date_arrivee, l.nom_du_logement
        FROM reservation r
        LEFT JOIN liste_logements l ON r.logement_id = l.id
        WHERE r.reference LIKE :q1 OR r.prenom LIKE :q2 OR r.nom LIKE :q3
           OR r.email LIKE :q4 OR r.telephone LIKE :q5
           OR CONCAT(r.prenom, ' ', r.nom) LIKE :q6
           " . ($phoneWhere ? " OR $phoneWhere" : "") . "
        ORDER BY r.date_arrivee DESC LIMIT :lim
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':q1', $searchTerm);
    $stmt->bindValue(':q2', $searchTerm);
    $stmt->bindValue(':q3', $searchTerm);
    $stmt->bindValue(':q4', $searchTerm);
    $stmt->bindValue(':q5', $searchTerm);
    $stmt->bindValue(':q6', $searchTerm);
    foreach ($phoneBind as $k => $v) $stmt->bindValue($k, $v);
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
    $phoneBind = [];
    $phoneWhere = $isPhoneSearch ? phoneWhereClause('telephone', 'pp', $phoneBind, $phoneVariants) : '';
    $sql = "
        SELECT id, nom, prenom, email, telephone, societe
        FROM FC_proprietaires
        WHERE nom LIKE :q1 OR prenom LIKE :q2 OR email LIKE :q3
           OR telephone LIKE :q4 OR COALESCE(societe,'') LIKE :q5
           OR CONCAT(prenom, ' ', nom) LIKE :q6
           " . ($phoneWhere ? " OR $phoneWhere" : "") . "
        ORDER BY nom LIMIT :lim
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':q1', $searchTerm);
    $stmt->bindValue(':q2', $searchTerm);
    $stmt->bindValue(':q3', $searchTerm);
    $stmt->bindValue(':q4', $searchTerm);
    $stmt->bindValue(':q5', $searchTerm);
    $stmt->bindValue(':q6', $searchTerm);
    foreach ($phoneBind as $k => $v) $stmt->bindValue($k, $v);
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
    $phoneBind = [];
    $phoneWhere = $isPhoneSearch ? phoneWhereClause('telephone', 'ip', $phoneBind, $phoneVariants) : '';
    $sql = "
        SELECT id, nom, role, telephone, email
        FROM intervenant
        WHERE nom LIKE :q1 OR COALESCE(telephone,'') LIKE :q2 OR COALESCE(email,'') LIKE :q3
           " . ($phoneWhere ? " OR $phoneWhere" : "") . "
        ORDER BY nom LIMIT :lim
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':q1', $searchTerm);
    $stmt->bindValue(':q2', $searchTerm);
    $stmt->bindValue(':q3', $searchTerm);
    foreach ($phoneBind as $k => $v) $stmt->bindValue($k, $v);
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
    $phoneBind = [];
    $phoneWhere = $isPhoneSearch ? phoneWhereClause('telephone', 'lp', $phoneBind, $phoneVariants) : '';
    $sql = "
        SELECT id, nom, email, telephone, statut
        FROM prospection_leads
        WHERE nom LIKE :q1 OR COALESCE(email,'') LIKE :q2
           OR COALESCE(telephone,'') LIKE :q3 OR COALESCE(adresse,'') LIKE :q4
           " . ($phoneWhere ? " OR $phoneWhere" : "") . "
        ORDER BY created_at DESC LIMIT :lim
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':q1', $searchTerm);
    $stmt->bindValue(':q2', $searchTerm);
    $stmt->bindValue(':q3', $searchTerm);
    $stmt->bindValue(':q4', $searchTerm);
    foreach ($phoneBind as $k => $v) $stmt->bindValue($k, $v);
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
    $phoneBind = [];
    $phoneWhere = $isPhoneSearch ? phoneWhereClause('r.telephone', 'cp', $phoneBind, $phoneVariants) : '';
    $sql = "
        SELECT
            MAX(r.id) as id,
            GROUP_CONCAT(DISTINCT CONCAT(TRIM(COALESCE(r.prenom,'')), ' ', TRIM(COALESCE(r.nom,''))) SEPARATOR ', ') as names,
            MAX(r.email) as email,
            MAX(r.telephone) as telephone,
            COUNT(*) as nb_resa
        FROM reservation r
        WHERE r.telephone LIKE :q1 OR r.prenom LIKE :q2 OR r.nom LIKE :q3
           OR r.email LIKE :q4 OR CONCAT(r.prenom, ' ', r.nom) LIKE :q5
           " . ($phoneWhere ? " OR $phoneWhere" : "") . "
        GROUP BY REPLACE(REPLACE(REPLACE(r.telephone,' ',''),'.',''),'-','')
        HAVING nb_resa > 0
        ORDER BY nb_resa DESC LIMIT :lim
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':q1', $searchTerm);
    $stmt->bindValue(':q2', $searchTerm);
    $stmt->bindValue(':q3', $searchTerm);
    $stmt->bindValue(':q4', $searchTerm);
    $stmt->bindValue(':q5', $searchTerm);
    foreach ($phoneBind as $k => $v) $stmt->bindValue($k, $v);
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
