<?php
/**
 * API proxy Superhote — Version VPS
 * Gere les operations DB directement via getRpiPdo()
 * Proxy les operations fichiers/scripts vers le RPI
 */

include '../config.php';
require_once __DIR__ . '/../includes/rpi_db.php';

session_start();
if (!isset($_SESSION['id_intervenant'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Non authentifie']);
    exit;
}

header('Content-Type: application/json');

try {
    $pdo = getRpiPdo();
} catch (PDOException $e) {
    error_log('daemon_api.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Une erreur interne est survenue.']);
    exit;
}

$action = $_REQUEST['action'] ?? 'status';

// URL de base du RPI pour les proxys
define('RPI_BASE_URL', 'http://109.219.194.30');

/**
 * Proxy une requete vers le daemon_api.php du RPI
 */
function proxyToRpi($action, $method = 'GET', $postData = null) {
    $url = RPI_BASE_URL . '/pages/daemon_api.php?action=' . urlencode($action);
    $opts = [
        'http' => [
            'method' => $method,
            'timeout' => 10,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        ]
    ];
    if ($method === 'POST' && $postData) {
        $opts['http']['content'] = http_build_query($postData);
    }
    $ctx = stream_context_create($opts);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        return ['success' => false, 'error' => 'RPI injoignable'];
    }
    return json_decode($response, true) ?: ['success' => false, 'error' => 'Reponse invalide du RPI'];
}

/**
 * Stats de la queue depuis la DB RPI
 */
function getQueueStats($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT status, COUNT(*) as count
            FROM superhote_price_updates
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY status
        ");
        $stats = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats[$row['status']] = $row['count'];
        }
        return $stats;
    } catch (PDOException $e) {
        error_log('daemon_api.php getQueueStats: ' . $e->getMessage());
        return ['error' => 'Une erreur interne est survenue.'];
    }
}

switch ($action) {
    case 'schedule_status':
        $queue = getQueueStats($pdo);

        // Recuperer les settings depuis la DB
        $scheduledTime = '07:00';
        $scheduledEnabled = true;
        try {
            $stmt = $pdo->query("SELECT key_name, value FROM superhote_settings WHERE key_name IN ('scheduled_time', 'scheduled_enabled')");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['key_name'] === 'scheduled_time') $scheduledTime = $row['value'];
                if ($row['key_name'] === 'scheduled_enabled') $scheduledEnabled = $row['value'] === '1';
            }
        } catch (PDOException $e) { error_log('daemon_api.php: ' . $e->getMessage()); }

        // Tenter de recuperer le statut de derniere execution depuis le RPI
        $lastRun = ['status' => 'unknown'];
        $rpiData = proxyToRpi('schedule_status');
        if (!empty($rpiData['success']) && isset($rpiData['last_run'])) {
            $lastRun = $rpiData['last_run'];
        }

        echo json_encode([
            'success' => true,
            'last_run' => $lastRun,
            'queue' => $queue,
            'scheduled_time' => $scheduledTime,
            'scheduled_enabled' => $scheduledEnabled
        ]);
        break;

    case 'logs':
        // Proxy vers le RPI pour les logs (fichiers locaux au RPI)
        $rpiData = proxyToRpi('logs');
        echo json_encode($rpiData);
        break;

    case 'clear':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST required']);
            break;
        }
        try {
            $count = $pdo->exec("DELETE FROM superhote_price_updates WHERE status = 'pending'");
            echo json_encode(['success' => true, 'deleted' => $count]);
        } catch (PDOException $e) {
            error_log('daemon_api.php clear: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Une erreur interne est survenue.']);
        }
        break;

    case 'release':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST required']);
            break;
        }
        try {
            $count = $pdo->exec("
                UPDATE superhote_price_updates
                SET status = 'pending', error_message = 'Released manually (VPS)'
                WHERE status = 'processing'
                AND created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            ");
            echo json_encode(['success' => true, 'released' => $count]);
        } catch (PDOException $e) {
            error_log('daemon_api.php release: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Une erreur interne est survenue.']);
        }
        break;

    case 'run_now':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST required']);
            break;
        }
        // Proxy vers le RPI (le script Python est sur le RPI)
        $rpiData = proxyToRpi('run_now', 'POST');
        echo json_encode($rpiData);
        break;

    case 'status':
        $queue = getQueueStats($pdo);
        $rpiData = proxyToRpi('status');
        echo json_encode([
            'success' => true,
            'daemon' => $rpiData['daemon'] ?? ['running' => false, 'pid' => null],
            'queue' => $queue,
            'logs' => $rpiData['logs'] ?? []
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action inconnue: ' . $action]);
}
