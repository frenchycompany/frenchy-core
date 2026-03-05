<?php
// pages/diag_sync.php — Diagnostic de la synchro planning
// Appeler via : /pages/diag_sync.php (admin requis)
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Paris');

require_once '../config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin requis']);
    exit;
}

$diag = [];
$today = date('Y-m-d');
$diag['date'] = $today;

// 1) Vérif connexion REMOTE
require_once __DIR__ . '/../includes/rpi_db.php';
try {
    $pdoRemote = getRpiPdo();
    $diag['remote_connection'] = 'OK';
} catch (Throwable $e) {
    $diag['remote_connection'] = 'ERREUR: ' . $e->getMessage();
    echo json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 2) Logements REMOTE (ceux référencés dans les réservations du jour)
try {
    $st = $pdoRemote->prepare("
        SELECT DISTINCT r.logement_id
        FROM reservation r
        WHERE r.statut = 'confirmée'
          AND (DATE(r.date_depart) = :d1 OR DATE(r.date_arrivee) = :d2)
    ");
    $st->execute([':d1' => $today, ':d2' => $today]);
    $remoteLogementIds = $st->fetchAll(PDO::FETCH_COLUMN);
    $diag['remote_logement_ids_today'] = $remoteLogementIds;
} catch (Throwable $e) {
    $diag['remote_reservations_error'] = $e->getMessage();
    echo json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 3) Logements LOCAL (liste_logements)
try {
    $localLogements = $conn->query("SELECT id, nom_du_logement FROM liste_logements ORDER BY id")
        ->fetchAll(PDO::FETCH_ASSOC);
    $localIds = array_column($localLogements, 'id');
    $diag['local_logements'] = $localLogements;
    $diag['local_logement_ids'] = $localIds;
} catch (Throwable $e) {
    $diag['local_logements_error'] = $e->getMessage();
    echo json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 4) Logements REMOTE (table logement/liste_logements si elle existe)
try {
    // Tester plusieurs noms de table possibles
    $remoteLogTable = null;
    foreach (['liste_logements', 'logement', 'logements'] as $t) {
        try {
            $pdoRemote->query("SELECT 1 FROM `$t` LIMIT 1");
            $remoteLogTable = $t;
            break;
        } catch (Throwable $e) {}
    }
    if ($remoteLogTable) {
        $remoteLogements = $pdoRemote->query("SELECT * FROM `$remoteLogTable` ORDER BY id")
            ->fetchAll(PDO::FETCH_ASSOC);
        $diag['remote_logements_table'] = $remoteLogTable;
        $diag['remote_logements'] = $remoteLogements;
    } else {
        $diag['remote_logements_table'] = 'AUCUNE TABLE TROUVEE';
    }
} catch (Throwable $e) {
    $diag['remote_logements_error'] = $e->getMessage();
}

// 5) IDs manquants : logements référencés par les réservations du jour mais absents de liste_logements LOCAL
$missingIds = array_values(array_diff(array_map('intval', $remoteLogementIds), array_map('intval', $localIds)));
$diag['missing_logement_ids'] = $missingIds;
$diag['missing_count'] = count($missingIds);

if (count($missingIds) > 0) {
    $diag['diagnostic'] = "PROBLEME : " . count($missingIds) . " logement(s) référencés dans les réservations du jour n'existent PAS dans liste_logements LOCAL. IDs manquants : " . implode(', ', $missingIds);
} else {
    $diag['diagnostic'] = count($remoteLogementIds) > 0
        ? "OK : Tous les logements des réservations du jour existent en local."
        : "Aucune réservation (départ ou arrivée) pour aujourd'hui.";
}

// 6) Vérif contrainte FK sur planning.logement_id
try {
    $fkCheck = $conn->query("
        SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'planning'
          AND COLUMN_NAME = 'logement_id'
          AND REFERENCED_TABLE_NAME IS NOT NULL
    ")->fetchAll(PDO::FETCH_ASSOC);
    $diag['planning_fk_constraints'] = $fkCheck;
} catch (Throwable $e) {
    $diag['fk_check_error'] = $e->getMessage();
}

// 7) Détail des réservations du jour (pour debug)
try {
    $st = $pdoRemote->prepare("
        SELECT r.id, r.logement_id, DATE(r.date_arrivee) AS arrivee, DATE(r.date_depart) AS depart,
               r.statut, r.nb_adultes, r.nb_enfants, r.nb_bebes
        FROM reservation r
        WHERE r.statut = 'confirmée'
          AND (DATE(r.date_depart) = :d1 OR DATE(r.date_arrivee) = :d2)
        ORDER BY r.date_depart, r.date_arrivee
    ");
    $st->execute([':d1' => $today, ':d2' => $today]);
    $diag['reservations_today'] = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $diag['reservations_today_error'] = $e->getMessage();
}

// 8) Colonnes de planning
try {
    $cols = $conn->query("SHOW COLUMNS FROM planning")->fetchAll(PDO::FETCH_COLUMN);
    $diag['planning_columns'] = $cols;
} catch (Throwable $e) {
    $diag['planning_columns_error'] = $e->getMessage();
}

echo json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
