<?php
/**
 * API pour controler le Superhote - Mode Planifie
 *
 * Actions:
 *   GET  ?action=status          - Statut du daemon (legacy)
 *   GET  ?action=schedule_status - Statut de la planification et derniere execution
 *   GET  ?action=logs            - Recuperer les logs recents
 *   POST ?action=start           - Demarrer le daemon (legacy)
 *   POST ?action=stop            - Arreter le daemon (legacy)
 *   POST ?action=clear           - Vider la queue pending
 *   POST ?action=release         - Liberer les taches bloquees
 *   POST ?action=run_now         - Lancer une mise a jour immediate
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Auth: session OU token API (appels inter-serveurs)
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$tokenParam = $_GET['token'] ?? '';
$cronSecret = env('CRON_SECRET', '');
$authenticated = false;
if ($cronSecret) {
    if ($tokenParam && hash_equals($cronSecret, $tokenParam)) {
        $authenticated = true;
    } elseif (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m) && hash_equals($cronSecret, $m[1])) {
        $authenticated = true;
    }
}
if (!$authenticated) {
    requireAuth();
}

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? 'status';

// Configuration
$scriptDir = realpath(__DIR__ . '/../../scripts/selenium');
$logsDir = realpath(__DIR__ . '/../../logs');
$pidFile = "$scriptDir/daemon.pid";
$logFile = "$logsDir/superhote_daemon_v2.log";
$scheduledLogFile = "$logsDir/scheduled_update_" . date('Y-m-d') . ".log";
$statusFile = "$logsDir/scheduled_update_status.json";

/**
 * Verifie si le daemon est en cours
 */
function isDaemonRunning($pidFile) {
    if (!file_exists($pidFile)) {
        return ['running' => false, 'pid' => null];
    }

    $pid = trim(file_get_contents($pidFile));
    if (!$pid) {
        return ['running' => false, 'pid' => null];
    }

    // Verifier si le process existe
    $result = shell_exec("ps -p $pid -o comm= 2>/dev/null");
    $running = !empty(trim($result));

    return ['running' => $running, 'pid' => $running ? $pid : null];
}

/**
 * Recupere les stats de la queue
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
        while ($row = $stmt->fetch()) {
            $stats[$row['status']] = $row['count'];
        }
        return $stats;
    } catch (PDOException $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Recupere les derniers logs
 */
function getRecentLogs($logFile, $lines = 50) {
    if (!file_exists($logFile)) {
        return [];
    }

    $output = shell_exec("tail -n $lines " . escapeshellarg($logFile) . " 2>/dev/null");
    return $output ? explode("\n", trim($output)) : [];
}

/**
 * Recupere le statut de la derniere execution planifiee
 */
function getLastRunStatus($statusFile) {
    if (!file_exists($statusFile)) {
        return ['status' => 'never_run'];
    }

    $content = file_get_contents($statusFile);
    if (!$content) {
        return ['status' => 'never_run'];
    }

    $data = json_decode($content, true);
    return $data ?: ['status' => 'error', 'error' => 'Invalid JSON'];
}

/**
 * Recupere les logs de plusieurs fichiers (scheduled + pool)
 */
function getAllRecentLogs($logsDir, $lines = 50) {
    $logs = [];
    $today = date('Y-m-d');

    // Logs du jour
    $logFiles = [
        "$logsDir/superhote_automation.log",
        "$logsDir/scheduled_update_$today.log",
        "$logsDir/superhote_worker_pool.log",
        "$logsDir/superhote_daemon_v2.log",
        "$logsDir/manual_run.log"
    ];

    foreach ($logFiles as $file) {
        if (file_exists($file)) {
            $output = shell_exec("tail -n 20 " . escapeshellarg($file) . " 2>/dev/null");
            if ($output) {
                $basename = basename($file);
                $logs[] = "=== $basename ===";
                $logs = array_merge($logs, explode("\n", trim($output)));
                $logs[] = "";
            }
        }
    }

    return array_slice($logs, -$lines);
}

// Traitement selon l'action
switch ($action) {
    case 'status':
        $daemon = isDaemonRunning($pidFile);
        $queue = getQueueStats($pdo);
        $logs = getRecentLogs($logFile, 20);

        echo json_encode([
            'success' => true,
            'daemon' => $daemon,
            'queue' => $queue,
            'logs' => $logs
        ]);
        break;

    case 'start':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST required']);
            break;
        }

        $daemon = isDaemonRunning($pidFile);
        if ($daemon['running']) {
            echo json_encode(['success' => false, 'error' => 'Daemon deja en cours', 'pid' => $daemon['pid']]);
            break;
        }

        $numWorkers = intval($_POST['workers'] ?? 2);
        $interval = intval($_POST['interval'] ?? 30);
        $useGroups = isset($_POST['groups']) && $_POST['groups'] === 'true';

        $cmd = "cd $scriptDir && NUM_WORKERS=$numWorkers POLL_INTERVAL=$interval ";
        if ($useGroups) {
            $cmd .= "USE_GROUPS=true ";
        }
        $cmd .= "bash daemon_ctl.sh start 2>&1";

        $output = shell_exec($cmd);

        sleep(2);
        $daemon = isDaemonRunning($pidFile);

        echo json_encode([
            'success' => $daemon['running'],
            'pid' => $daemon['pid'],
            'output' => $output
        ]);
        break;

    case 'stop':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST required']);
            break;
        }

        $output = shell_exec("cd $scriptDir && bash daemon_ctl.sh stop 2>&1");

        sleep(2);
        $daemon = isDaemonRunning($pidFile);

        echo json_encode([
            'success' => !$daemon['running'],
            'output' => $output
        ]);
        break;

    case 'clear':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST required']);
            break;
        }

        try {
            $stmt = $pdo->exec("DELETE FROM superhote_price_updates WHERE status = 'pending'");
            echo json_encode(['success' => true, 'deleted' => $stmt]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'release':
        // Libere les taches bloquees en 'processing'
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST required']);
            break;
        }

        try {
            $stmt = $pdo->exec("
                UPDATE superhote_price_updates
                SET status = 'pending', error_message = 'Released manually'
                WHERE status = 'processing'
                AND updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            ");
            echo json_encode(['success' => true, 'released' => $stmt]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'schedule_status':
        // Statut de la planification et derniere execution
        $lastRun = getLastRunStatus($statusFile);
        $queue = getQueueStats($pdo);

        // Recuperer l'heure planifiee depuis la BDD
        $scheduledTime = '07:00';
        $scheduledEnabled = true;
        try {
            $stmt = $pdo->query("SELECT key_name, value FROM superhote_settings WHERE key_name IN ('scheduled_time', 'scheduled_enabled')");
            while ($row = $stmt->fetch()) {
                if ($row['key_name'] === 'scheduled_time') $scheduledTime = $row['value'];
                if ($row['key_name'] === 'scheduled_enabled') $scheduledEnabled = $row['value'] === '1';
            }
        } catch (PDOException $e) {}

        echo json_encode([
            'success' => true,
            'last_run' => $lastRun,
            'queue' => $queue,
            'scheduled_time' => $scheduledTime,
            'scheduled_enabled' => $scheduledEnabled
        ]);
        break;

    case 'logs':
        // Recuperer les logs recents
        $logs = getAllRecentLogs($logsDir, 100);
        echo json_encode([
            'success' => true,
            'logs' => $logs
        ]);
        break;

    case 'run_now':
        // Lancer une mise a jour immediate
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST required']);
            break;
        }

        // Recuperer le nombre de workers
        $maxWorkers = 1; // 1 par defaut pour economiser la memoire
        try {
            $stmt = $pdo->query("SELECT value FROM superhote_settings WHERE key_name = 'max_workers'");
            $row = $stmt->fetch();
            if ($row) $maxWorkers = intval($row['value']);
        } catch (PDOException $e) {}

        // Verifier que le script existe
        $scriptPath = "$scriptDir/run_scheduled_update.py";
        if (!file_exists($scriptPath)) {
            echo json_encode(['success' => false, 'error' => 'Script non trouve: ' . $scriptPath]);
            break;
        }

        // Option workers-only: ne pas regenerer les prix (utile quand le VPS a deja genere)
        $workersOnly = isset($_POST['workers_only']) || isset($_GET['workers_only']);
        $extraArgs = $workersOnly ? ' --workers-only' : '';

        // Option logement_id: ne traiter que ce logement
        $logementId = intval($_POST['logement_id'] ?? $_GET['logement_id'] ?? 0);
        if ($logementId > 0) {
            $extraArgs .= ' --logement-id ' . $logementId;
        }

        // Lancer en arriere-plan avec nohup pour garantir l'execution
        $logFile = "$logsDir/manual_run.log";
        $cmd = "cd $scriptDir && nohup /usr/bin/python3 run_scheduled_update.py -w $maxWorkers$extraArgs > $logFile 2>&1 &";

        // Log de debug pour tracer la commande exacte
        error_log("daemon_api.php run_now: cmd=$cmd workers_only=$workersOnly logement_id=$logementId");

        // Utiliser shell_exec avec descriptors pour forcer le background
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w")
        );
        $process = proc_open($cmd, $descriptorspec, $pipes);
        if (is_resource($process)) {
            // Fermer immediatement pour que le processus continue en arriere-plan
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Mise a jour lancee en arriere-plan',
            'workers' => $maxWorkers,
            'log_file' => $logFile
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action inconnue']);
}
