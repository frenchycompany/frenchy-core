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

// --- Recherche fuzzy dans les pages/outils du menu ---
require_once __DIR__ . '/../pages/menu_categories.php';

/**
 * Normalise une chaîne pour recherche fuzzy :
 * minuscules, supprime accents, supprime caractères spéciaux
 */
function fuzzyNormalize($str) {
    $str = mb_strtolower($str);
    $str = transliterator_transliterate('Any-Latin; Latin-ASCII', $str);
    if ($str === false) {
        // Fallback si intl pas dispo
        $str = strtr(mb_strtolower($str), [
            'à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','ñ'=>'n','œ'=>'oe','æ'=>'ae'
        ]);
    }
    // Garder lettres, chiffres, espaces
    $str = preg_replace('/[^a-z0-9 ]/', ' ', $str);
    $str = preg_replace('/\s+/', ' ', trim($str));
    return $str;
}

/**
 * Vérifie si la requête matche un texte (fuzzy) :
 * - Normalise accents des 2 côtés
 * - Matching partiel (substring)
 */
function fuzzyMatch($query, $text) {
    $nq = fuzzyNormalize($query);
    $nt = fuzzyNormalize($text);
    // Chaque mot de la requête doit être trouvé dans le texte
    $words = explode(' ', $nq);
    foreach ($words as $w) {
        if ($w === '') continue;
        if (strpos($nt, $w) === false) return false;
    }
    return true;
}

foreach ($menu_categories as $cat_name => $cat) {
    foreach ($cat['items'] as $item) {
        // Chercher dans : nom de la page + nom du fichier + catégorie
        $filename = pathinfo(basename($item['chemin']), PATHINFO_FILENAME);
        $searchable = $item['nom'] . ' ' . $cat_name . ' ' . str_replace('_', ' ', $filename);
        if (fuzzyMatch($q, $searchable)) {
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

// --- SMS (conversations) ---
try {
    require_once __DIR__ . '/../includes/rpi_db.php';
    $pdoSms = getRpiPdo();

    $phoneBind = [];
    $phoneWhere = $isPhoneSearch ? phoneWhereClause('sender', 'sp', $phoneBind, $phoneVariants) : '';
    $sql = "
        SELECT sender AS phone, message, received_at,
               COUNT(*) AS nb_messages
        FROM sms_in
        WHERE sender LIKE :q1 OR message LIKE :q2
           " . ($phoneWhere ? " OR $phoneWhere" : "") . "
        GROUP BY sender
        ORDER BY MAX(received_at) DESC LIMIT :lim
    ";
    $stmt = $pdoSms->prepare($sql);
    $stmt->bindValue(':q1', $searchTerm);
    $stmt->bindValue(':q2', $searchTerm);
    foreach ($phoneBind as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'type' => 'sms',
            'type_label' => 'SMS',
            'title' => $row['phone'],
            'subtitle' => $row['nb_messages'] . ' message(s) · ' . mb_substr($row['message'] ?? '', 0, 50),
            'url' => 'pages/sms_recus.php?view=conversations&sender=' . urlencode($row['phone'])
        ];
    }
} catch (Exception $e) { error_log('search_api sms: ' . $e->getMessage()); }

echo json_encode(['results' => $results, 'query' => $q]);
