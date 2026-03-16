<?php
/**
 * API paginée pour les conversations SMS + vue liste
 * Endpoints :
 *   ?action=conversations&page=1&per_page=30&archived=0&search=...
 *   ?action=list&page=1&per_page=25&search=...&modem=...&date_from=...&date_to=...
 *   ?action=poll&since=2026-03-16T12:00:00  (nouveaux messages depuis timestamp)
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

session_start();
if (!isset($_SESSION['id_intervenant']) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

require_once __DIR__ . '/../includes/rpi_db.php';
require_once __DIR__ . '/../db/connection.php';

try {
    $pdo = getRpiPdo();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur connexion DB']);
    exit;
}

$action   = $_GET['action'] ?? 'conversations';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = min(100, max(1, (int)($_GET['per_page'] ?? 30)));
$search   = trim($_GET['search'] ?? '');
$offset   = ($page - 1) * $per_page;

// ─────────────────────────────────────────────────────────────────────────────
// Normalisation téléphone (réutilise la logique de search_api.php)
// ─────────────────────────────────────────────────────────────────────────────
function normalizePhone($phone) {
    $clean = preg_replace('/[^0-9+]/', '', $phone);
    return preg_replace('/^\+33/', '0', $clean);
}

function phoneVariants($q) {
    $digits = preg_replace('/[^0-9+]/', '', $q);
    $variants = [];
    if (strlen($digits) < 4) return $variants;
    $variants[] = '%' . $digits . '%';
    if (strlen($digits) === 10 && $digits[0] === '0') {
        $variants[] = '%+33' . substr($digits, 1) . '%';
    }
    if (substr($digits, 0, 3) === '+33') {
        $variants[] = '%0' . substr($digits, 3) . '%';
    }
    return $variants;
}

// ─────────────────────────────────────────────────────────────────────────────
// Batch load reservations pour une liste de numéros
// ─────────────────────────────────────────────────────────────────────────────
function batchLoadReservations($phones, $pdo, $conn) {
    if (empty($phones)) return [];

    // Normaliser tous les numéros
    $phoneMap = []; // normalized => [original variants]
    foreach ($phones as $phone) {
        $norm = normalizePhone($phone);
        if (!isset($phoneMap[$norm])) $phoneMap[$norm] = [];
        $phoneMap[$norm][] = $phone;
    }

    // Construire tous les variants à chercher
    $allVariants = [];
    foreach ($phones as $phone) {
        $clean = preg_replace('/[^0-9+]/', '', $phone);
        $allVariants[$clean] = true;
        $norm = preg_replace('/^\+33/', '0', $clean);
        $allVariants[$norm] = true;
        if (strlen($norm) === 10 && $norm[0] === '0') {
            $allVariants['+33' . substr($norm, 1)] = true;
        }
    }
    $allVariants = array_keys($allVariants);
    if (empty($allVariants)) return [];

    // Une seule requête pour toutes les réservations
    $placeholders = implode(',', array_fill(0, count($allVariants), '?'));
    $stmt = $pdo->prepare("
        SELECT id, prenom AS client_name, date_arrivee AS start_date,
               date_depart AS end_date, statut AS status, logement_id, telephone
        FROM reservation
        WHERE telephone IN ($placeholders)
        ORDER BY CASE WHEN date_depart >= CURDATE() THEN 0 ELSE 1 END, date_arrivee DESC
    ");
    $stmt->execute($allVariants);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Grouper par numéro normalisé (garder la meilleure = la première)
    $resByNorm = [];
    foreach ($rows as $row) {
        $norm = normalizePhone($row['telephone']);
        if (!isset($resByNorm[$norm])) {
            $resByNorm[$norm] = $row;
        }
    }

    // Charger les noms de logements en batch
    $logementIds = array_unique(array_filter(array_column($resByNorm, 'logement_id')));
    $logementNames = [];
    if (!empty($logementIds) && isset($conn)) {
        $placeholders = implode(',', array_fill(0, count($logementIds), '?'));
        try {
            $stmtL = $conn->prepare("SELECT id, nom_du_logement FROM liste_logements WHERE id IN ($placeholders)");
            $stmtL->execute(array_values($logementIds));
            foreach ($stmtL->fetchAll(PDO::FETCH_ASSOC) as $l) {
                $logementNames[$l['id']] = $l['nom_du_logement'];
            }
        } catch (PDOException $e) { /* continue sans */ }
    }

    // Enrichir avec le nom du logement
    foreach ($resByNorm as &$r) {
        $r['nom_du_logement'] = $logementNames[$r['logement_id']] ?? '';
    }
    unset($r);

    // Construire le résultat indexé par numéro normalisé
    return $resByNorm;
}

function getReservationForPhone($phone, &$resByNorm) {
    $norm = normalizePhone($phone);
    return $resByNorm[$norm] ?? null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Vérifier les colonnes optionnelles
// ─────────────────────────────────────────────────────────────────────────────
$has_archived = false;
try {
    $check = $pdo->query("SELECT archived FROM sms_in LIMIT 1");
    $has_archived = ($check !== false);
} catch (PDOException $e) { $has_archived = false; }

// ═══════════════════════════════════════════════════════════════════════════════
// ACTION: conversations
// ═══════════════════════════════════════════════════════════════════════════════
if ($action === 'conversations') {
    $show_archived = ($_GET['archived'] ?? '0') === '1';

    // Clause de recherche
    $searchWhere = '';
    $searchBindings = [];
    if ($search !== '') {
        $variants = phoneVariants($search);
        $searchClauses = ["phone LIKE :sq"];
        $searchBindings[':sq'] = '%' . $search . '%';
        foreach ($variants as $i => $v) {
            $searchClauses[] = "phone LIKE :spv$i";
            $searchBindings[":spv$i"] = $v;
        }
        $searchWhere = 'AND (' . implode(' OR ', $searchClauses) . ')';
    }

    $archivedFilter = '';
    if ($has_archived) {
        $archivedFilter = $show_archived
            ? "AND archived = 1"
            : "AND (archived = 0 OR archived IS NULL)";
    }

    // Compter le total
    $countSql = "
        SELECT COUNT(*) FROM (
            SELECT phone FROM (
                SELECT sender AS phone FROM sms_in
                WHERE sender IS NOT NULL AND sender != ''
                $archivedFilter
                UNION
                SELECT receiver AS phone FROM sms_outbox
                WHERE receiver IS NOT NULL AND receiver != ''
            ) AS all_phones
            WHERE 1=1 $searchWhere
            GROUP BY phone
        ) AS cnt
    ";
    $stmtCount = $pdo->prepare($countSql);
    foreach ($searchBindings as $k => $v) $stmtCount->bindValue($k, $v);
    $stmtCount->execute();
    $total = (int)$stmtCount->fetchColumn();

    // Conversations paginées — requête simplifiée sans sous-requêtes corrélées
    $sql = "
        SELECT
            phone,
            MAX(last_date) AS last_date,
            SUBSTRING_INDEX(GROUP_CONCAT(last_message ORDER BY last_date DESC SEPARATOR '|||'), '|||', 1) AS last_message,
            SUBSTRING_INDEX(GROUP_CONCAT(last_direction ORDER BY last_date DESC SEPARATOR '|||'), '|||', 1) AS last_direction,
            SUM(msg_count) AS total_count
        FROM (
            SELECT
                sender AS phone,
                message AS last_message,
                received_at AS last_date,
                'in' AS last_direction,
                1 AS msg_count
            FROM sms_in
            WHERE sender IS NOT NULL AND sender != ''
            $archivedFilter

            UNION ALL

            SELECT
                receiver AS phone,
                message AS last_message,
                COALESCE(sent_at, created_at) AS last_date,
                'out' AS last_direction,
                1 AS msg_count
            FROM sms_outbox
            WHERE receiver IS NOT NULL AND receiver != ''
        ) AS all_messages
        WHERE 1=1 $searchWhere
        GROUP BY phone
        ORDER BY last_date DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($searchBindings as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rawConversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dédupliquer les numéros (formats +33 vs 0)
    $seen = [];
    $conversations = [];
    $phones = [];
    foreach ($rawConversations as $conv) {
        $norm = normalizePhone($conv['phone']);
        if (isset($seen[$norm])) continue;
        $seen[$norm] = true;
        $phones[] = $conv['phone'];
        $conversations[] = $conv;
    }

    // Batch load reservations
    $resByNorm = batchLoadReservations($phones, $pdo, $conn);

    // Enrichir les conversations
    foreach ($conversations as &$conv) {
        $conv['reservation'] = getReservationForPhone($conv['phone'], $resByNorm);
        $is_outgoing = ($conv['last_direction'] ?? 'in') === 'out';
        if ($is_outgoing) {
            $conv['last_message'] = 'Vous: ' . $conv['last_message'];
        }
    }
    unset($conv);

    // Compter les archivées
    $archived_count = 0;
    if ($has_archived) {
        $stmtArc = $pdo->query("SELECT COUNT(DISTINCT sender) FROM sms_in WHERE archived = 1");
        $archived_count = $stmtArc ? (int)$stmtArc->fetchColumn() : 0;
    }

    echo json_encode([
        'conversations' => $conversations,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => max(1, ceil($total / $per_page)),
        'archived_count' => $archived_count,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
// ACTION: list (vue liste paginée)
// ═══════════════════════════════════════════════════════════════════════════════
if ($action === 'list') {
    $modem     = trim($_GET['modem'] ?? '');
    $dateFrom  = trim($_GET['date_from'] ?? '');
    $dateTo    = trim($_GET['date_to'] ?? '');

    $where = "WHERE 1=1";
    $bindings = [];

    if ($search !== '') {
        $variants = phoneVariants($search);
        $searchClauses = ["sender LIKE :sq", "message LIKE :smq"];
        $bindings[':sq'] = '%' . $search . '%';
        $bindings[':smq'] = '%' . $search . '%';
        foreach ($variants as $i => $v) {
            $searchClauses[] = "sender LIKE :spv$i";
            $bindings[":spv$i"] = $v;
        }
        $where .= ' AND (' . implode(' OR ', $searchClauses) . ')';
    }
    if ($modem !== '') {
        $where .= ' AND modem = :modem';
        $bindings[':modem'] = $modem;
    }
    if ($dateFrom !== '') {
        $where .= ' AND DATE(received_at) >= :df';
        $bindings[':df'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where .= ' AND DATE(received_at) <= :dt';
        $bindings[':dt'] = $dateTo;
    }

    // Count
    $stmtC = $pdo->prepare("SELECT COUNT(*) FROM sms_in $where");
    foreach ($bindings as $k => $v) $stmtC->bindValue($k, $v);
    $stmtC->execute();
    $total = (int)$stmtC->fetchColumn();

    // Data
    $stmtD = $pdo->prepare("
        SELECT id, sender, message, received_at, modem
        FROM sms_in $where
        ORDER BY received_at DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($bindings as $k => $v) $stmtD->bindValue($k, $v);
    $stmtD->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmtD->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtD->execute();
    $sms_list = $stmtD->fetchAll(PDO::FETCH_ASSOC);

    // Batch reservations
    $phones = array_unique(array_filter(array_column($sms_list, 'sender')));
    $resByNorm = batchLoadReservations($phones, $pdo, $conn);
    foreach ($sms_list as &$sms) {
        $sms['reservation'] = getReservationForPhone($sms['sender'] ?? '', $resByNorm);
    }
    unset($sms);

    // Modems distincts (pour le filtre)
    $modems = $pdo->query("SELECT DISTINCT modem FROM sms_in WHERE modem IS NOT NULL AND modem != '' ORDER BY modem")
        ->fetchAll(PDO::FETCH_COLUMN) ?: [];

    echo json_encode([
        'sms_list' => $sms_list,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => max(1, ceil($total / $per_page)),
        'modems' => $modems,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
// ACTION: poll (nouveaux messages depuis un timestamp)
// ═══════════════════════════════════════════════════════════════════════════════
if ($action === 'poll') {
    $since = trim($_GET['since'] ?? '');
    if ($since === '') {
        echo json_encode(['new_in' => 0, 'new_out' => 0, 'messages' => []]);
        exit;
    }

    // Nouveaux messages reçus
    $stmtIn = $pdo->prepare("
        SELECT id, sender AS phone, message, received_at AS date, 'in' AS direction
        FROM sms_in WHERE received_at > :since ORDER BY received_at ASC
    ");
    $stmtIn->execute([':since' => $since]);
    $newIn = $stmtIn->fetchAll(PDO::FETCH_ASSOC);

    // Nouveaux messages envoyés
    $stmtOut = $pdo->prepare("
        SELECT id, receiver AS phone, message, COALESCE(sent_at, created_at) AS date, 'out' AS direction, status
        FROM sms_outbox WHERE COALESCE(sent_at, created_at) > :since ORDER BY COALESCE(sent_at, created_at) ASC
    ");
    $stmtOut->execute([':since' => $since]);
    $newOut = $stmtOut->fetchAll(PDO::FETCH_ASSOC);

    $all = array_merge($newIn, $newOut);
    usort($all, fn($a, $b) => strcmp($a['date'], $b['date']));

    echo json_encode([
        'new_in' => count($newIn),
        'new_out' => count($newOut),
        'messages' => $all,
        'server_time' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error' => 'Action inconnue: ' . $action]);
