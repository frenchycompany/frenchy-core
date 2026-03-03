<?php
// Export des réservations en CSV
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DB loaded via config.php

if (!($pdo instanceof PDO)) {
    die('Erreur: PDO non disponible.');
}

// Même logique de filtrage que reservations_listing.php
$filtre_logement = isset($_GET['logement']) && $_GET['logement'] !== '' ? (int)$_GET['logement'] : null;
$filtre_statut = isset($_GET['statut']) && $_GET['statut'] !== '' ? $_GET['statut'] : null;
$filtre_date_debut = isset($_GET['date_debut']) && $_GET['date_debut'] !== '' ? $_GET['date_debut'] : null;
$filtre_date_fin = isset($_GET['date_fin']) && $_GET['date_fin'] !== '' ? $_GET['date_fin'] : null;
$filtre_plateforme = isset($_GET['plateforme']) && $_GET['plateforme'] !== '' ? $_GET['plateforme'] : null;

$sql = "
    SELECT
        r.reference,
        r.prenom,
        r.nom,
        r.telephone,
        r.ville,
        r.logement as nom_du_logement,
        r.date_reservation,
        r.date_arrivee,
        r.date_depart,
        DATEDIFF(r.date_depart, r.date_arrivee) as duree_sejour,
        r.plateforme,
        r.statut,
        r.dep_sent,
        r.j1_sent,
        r.start_sent
    FROM reservation r
    WHERE 1=1
";

$params = [];

if ($filtre_logement !== null) {
    $sql .= " AND r.logement = :logement";
    $params[':logement'] = $filtre_logement;
}

if ($filtre_statut !== null) {
    $sql .= " AND r.statut = :statut";
    $params[':statut'] = $filtre_statut;
}

if ($filtre_date_debut !== null) {
    $sql .= " AND r.date_arrivee >= :date_debut";
    $params[':date_debut'] = $filtre_date_debut;
}

if ($filtre_date_fin !== null) {
    $sql .= " AND r.date_depart <= :date_fin";
    $params[':date_fin'] = $filtre_date_fin;
}

if ($filtre_plateforme !== null) {
    $sql .= " AND r.plateforme = :plateforme";
    $params[':plateforme'] = $filtre_plateforme;
}

$sql .= " ORDER BY r.date_arrivee DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll();

    // Générer le CSV
    $filename = 'reservations_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // BOM UTF-8 pour Excel
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // En-têtes
    fputcsv($output, [
        'Référence',
        'Prénom',
        'Nom',
        'Téléphone',
        'Ville client',
        'Logement',
        'Date réservation',
        'Date arrivée',
        'Date départ',
        'Durée (nuits)',
        'Plateforme',
        'Statut',
        'SMS préparation envoyé',
        'SMS accueil envoyé',
        'SMS départ envoyé'
    ], ';');

    // Données
    foreach ($reservations as $res) {
        fputcsv($output, [
            $res['reference'] ?? '',
            $res['prenom'] ?? '',
            $res['nom'] ?? '',
            $res['telephone'] ?? '',
            $res['ville'] ?? '',
            $res['nom_du_logement'] ?? '',
            $res['date_reservation'] ?? '',
            $res['date_arrivee'] ?? '',
            $res['date_depart'] ?? '',
            $res['duree_sejour'] ?? 0,
            $res['plateforme'] ?? '',
            $res['statut'] ?? '',
            $res['start_sent'] ? 'Oui' : 'Non',
            $res['j1_sent'] ? 'Oui' : 'Non',
            $res['dep_sent'] ? 'Oui' : 'Non'
        ], ';');
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    die('Erreur lors de l\'export: ' . $e->getMessage());
}
?>
