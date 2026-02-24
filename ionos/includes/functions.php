<?php
/**
 * Fonctions utilitaires pour Frenchy Conciergerie
 */

/**
 * Récupère un paramètre du site
 */
function getSetting($conn, $key, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM FC_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : $default;
}

/**
 * Récupère tous les paramètres du site
 */
function getAllSettings($conn) {
    $stmt = $conn->query("SELECT setting_key, setting_value FROM FC_settings");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

/**
 * Récupère les services actifs
 */
function getServices($conn) {
    $stmt = $conn->query("SELECT * FROM FC_services WHERE actif = 1 ORDER BY ordre ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les tarifs actifs
 */
function getTarifs($conn) {
    $stmt = $conn->query("SELECT * FROM FC_tarifs WHERE actif = 1 ORDER BY ordre ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les logements actifs
 */
function getLogements($conn) {
    $stmt = $conn->query("SELECT * FROM FC_logements WHERE actif = 1 ORDER BY ordre ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les avis actifs
 */
function getAvis($conn) {
    $stmt = $conn->query("SELECT * FROM FC_avis WHERE actif = 1 ORDER BY date_avis DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les distinctions actives
 */
function getDistinctions($conn) {
    $stmt = $conn->query("SELECT * FROM FC_distinctions WHERE actif = 1 ORDER BY ordre ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les articles actifs
 */
function getArticles($conn, $limit = 10) {
    $stmt = $conn->prepare("SELECT * FROM FC_articles WHERE actif = 1 ORDER BY date_publication DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère un article par son slug
 */
function getArticleBySlug($conn, $slug) {
    $stmt = $conn->prepare("SELECT * FROM FC_articles WHERE slug = ? AND actif = 1");
    $stmt->execute([$slug]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère les informations légales
 */
function getLegalInfo($conn) {
    $stmt = $conn->query("SELECT * FROM FC_legal WHERE actif = 1 ORDER BY ordre ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Enregistre un message de contact
 */
function saveContact($conn, $nom, $email, $telephone, $sujet, $message) {
    $stmt = $conn->prepare("INSERT INTO FC_contacts (nom, email, telephone, sujet, message) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$nom, $email, $telephone, $sujet, $message]);
}

/**
 * Génère les étoiles pour une note
 */
function renderStars($note) {
    $stars = '';
    for ($i = 0; $i < 5; $i++) {
        $stars .= $i < $note ? '★' : '☆';
    }
    return $stars;
}

/**
 * Formate une date en français
 */
function formatDateFr($date) {
    if (empty($date)) {
        return '';
    }
    $mois = [
        '01' => 'janvier', '02' => 'février', '03' => 'mars', '04' => 'avril',
        '05' => 'mai', '06' => 'juin', '07' => 'juillet', '08' => 'août',
        '09' => 'septembre', '10' => 'octobre', '11' => 'novembre', '12' => 'décembre'
    ];
    try {
        $d = new DateTime($date);
        return $d->format('d') . ' ' . $mois[$d->format('m')] . ' ' . $d->format('Y');
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Échappe le HTML
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>
