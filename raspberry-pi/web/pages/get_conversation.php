<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);  // pas d'HTML parasite
ini_set('log_errors', 1);

function logDebug($m) { error_log('[get_conversation.php] ' . $m); }

// ———————————————————————————————————————
// Helpers communs
// ———————————————————————————————————————
function normalize_messages(array $rows, string $direction): array {
    foreach ($rows as &$r) {
        // champs attendus par le front
        $r['phone']     = isset($r['phone']) ? (string)$r['phone'] : '';
        $r['message']   = isset($r['message']) && $r['message'] !== null ? (string)$r['message'] : '';
        $r['date']      = isset($r['date']) && $r['date'] !== null ? (string)$r['date'] : '';
        $r['direction'] = $direction === 'in' ? 'in' : 'out';
        $r['status']    = isset($r['status']) && $r['status'] !== null ? (string)$r['status'] : 'pending';
        // id facultatif mais utile
        if (!isset($r['id'])) { $r['id'] = null; }
    }
    unset($r);
    return $rows;
}

try {
    // 1) Connexion
    include '../includes/db.php';
    if (!isset($conn)) {
        throw new Exception('Variable $conn non définie par includes/db.php');
    }

    $isPDO    = class_exists('PDO') && ($conn instanceof PDO);
    $isMySQLi = class_exists('mysqli') && ($conn instanceof mysqli);

    if (!$isPDO && !$isMySQLi) {
        throw new Exception('Type de $conn non supporté (ni PDO, ni mysqli).');
    }

    // 2) Paramètres
    $sender_raw = $_GET['sender'] ?? '';
    if ($sender_raw === '') {
        echo json_encode(['error' => 'Numéro de téléphone manquant'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // normalisations
    $sender_e164 = preg_replace('/[^0-9+]/', '', $sender_raw);
    $sender_0    = preg_replace('/^\+33/', '0', $sender_e164);
    logDebug("Sender: raw={$sender_raw} e164={$sender_e164} local={$sender_0}");

    $messages_in  = [];
    $messages_out = [];
    $table_exists = false;

    // ———————————————————————————————————————
    // 3) Branches PDO / MySQLi
    // ———————————————————————————————————————
    if ($isPDO) {
        // Sécurité : mode exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // IN (réception)
        $sql_in = "
            SELECT
                id,
                sender AS phone,
                message,
                received_at AS date,
                'in' AS direction,
                'delivered' AS status
            FROM sms_in
            WHERE sender = :s1 OR sender = :s2
            ORDER BY received_at ASC
        ";
        $st_in = $conn->prepare($sql_in);
        $st_in->execute([':s1' => $sender_e164, ':s2' => $sender_0]);
        $messages_in = $st_in->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // existence sms_outbox
        $sql_chk = "SELECT 1
                    FROM information_schema.tables
                    WHERE table_schema = DATABASE() AND table_name = 'sms_outbox'
                    LIMIT 1";
        $table_exists = (bool)$conn->query($sql_chk)->fetchColumn();

        if ($table_exists) {
            $sql_out = "
                SELECT
                    id,
                    receiver AS phone,
                    message,
                    sent_at AS date,
                    'out' AS direction,
                    status
                FROM sms_outbox
                WHERE receiver = :s1 OR receiver = :s2
                ORDER BY sent_at ASC
            ";
            $st_out = $conn->prepare($sql_out);
            $st_out->execute([':s1' => $sender_e164, ':s2' => $sender_0]);
            $messages_out = $st_out->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

    } else { // mysqli
        // IN (réception)
        $sql_in = "
            SELECT
                id,
                sender AS phone,
                message,
                received_at AS date,
                'in' AS direction,
                'delivered' AS status
            FROM sms_in
            WHERE sender = ? OR sender = ?
            ORDER BY received_at ASC
        ";
        if (!$st_in = $conn->prepare($sql_in)) {
            throw new Exception('Prepare IN failed: ' . $conn->error);
        }
        $st_in->bind_param('ss', $sender_e164, $sender_0);
        if (!$st_in->execute()) {
            throw new Exception('Execute IN failed: ' . $st_in->error);
        }
        $res_in = $st_in->get_result();
        $messages_in = $res_in ? $res_in->fetch_all(MYSQLI_ASSOC) : [];
        $st_in->close();

        // existence sms_outbox
        $sql_chk = "SHOW TABLES LIKE 'sms_outbox'";
        if (!$res_chk = $conn->query($sql_chk)) {
            throw new Exception('SHOW TABLES failed: ' . $conn->error);
        }
        $table_exists = ($res_chk->num_rows > 0);
        $res_chk->free();

        if ($table_exists) {
            $sql_out = "
                SELECT
                    id,
                    receiver AS phone,
                    message,
                    sent_at AS date,
                    'out' AS direction,
                    status
                FROM sms_outbox
                WHERE receiver = ? OR receiver = ?
                ORDER BY sent_at ASC
            ";
            if (!$st_out = $conn->prepare($sql_out)) {
                throw new Exception('Prepare OUT failed: ' . $conn->error);
            }
            $st_out->bind_param('ss', $sender_e164, $sender_0);
            if (!$st_out->execute()) {
                throw new Exception('Execute OUT failed: ' . $st_out->error);
            }
            $res_out = $st_out->get_result();
            $messages_out = $res_out ? $res_out->fetch_all(MYSQLI_ASSOC) : [];
            $st_out->close();
        }
    }

    logDebug('IN: ' . count($messages_in) . ' | OUT: ' . count($messages_out) . ' | outbox=' . ($table_exists ? 'YES' : 'NO'));

    // 4) Normalisation des champs attendus par le front
    $messages_in  = normalize_messages($messages_in,  'in');
    $messages_out = normalize_messages($messages_out, 'out');

    // 5) Fusion + tri
    $all = array_merge($messages_in, $messages_out);
    usort($all, function ($a, $b) {
        $ta = !empty($a['date']) ? strtotime($a['date']) : 0;
        $tb = !empty($b['date']) ? strtotime($b['date']) : 0;
        if ($ta === $tb) return 0;
        return ($ta < $tb) ? -1 : 1;
    });

    // 6) Sortie JSON propre
    echo json_encode($all, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    // Attrape aussi les erreurs fatales PHP 7+/8 (Error/TypeError…)
    logDebug('FATAL: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
