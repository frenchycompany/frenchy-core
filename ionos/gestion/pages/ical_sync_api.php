<?php
/**
 * API for syncing iCal calendars and importing reservations
 */

header('Content-Type: application/json; charset=utf-8');

include '../config.php';
require_once __DIR__ . '/../includes/rpi_db.php';
$pdo = getRpiPdo();
require_once 'ical_parser.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'sync_ical':
            if ($method === 'POST') {
                syncIcal($pdo);
            } else {
                throw new Exception('Method not allowed');
            }
            break;

        case 'sync_all_icals':
            if ($method === 'POST') {
                syncAllIcals($pdo);
            } else {
                throw new Exception('Method not allowed');
            }
            break;

        case 'get_reservations':
            getReservations($pdo);
            break;

        case 'get_calendar_events':
            getCalendarEvents($pdo);
            break;

        case 'delete_reservation':
            if ($method === 'POST') {
                deleteReservation($pdo);
            } else {
                throw new Exception('Method not allowed');
            }
            break;

        case 'get_sync_logs':
            getSyncLogs($pdo);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log('ical_sync_api.php: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Une erreur interne est survenue.'
    ]);
}

/**
 * Sync iCal calendar for a specific connection
 */
function syncIcal($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $connection_id = $input['connection_id'] ?? null;

    if (!$connection_id) {
        throw new Exception('Connection ID is required');
    }

    // Get connection details
    $stmt = $pdo->prepare("
        SELECT c.*, p.code as platform_code
        FROM travel_account_connections c
        JOIN travel_platforms p ON c.platform_id = p.id
        WHERE c.id = ?
    ");
    $stmt->execute([$connection_id]);
    $connection = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$connection) {
        throw new Exception('Connection not found');
    }

    if (empty($connection['ical_url'])) {
        throw new Exception('No iCal URL configured for this connection');
    }

    $startTime = microtime(true);

    try {
        // Parse iCal
        $parser = new ICalParser();
        $events = $parser->parseFromUrl($connection['ical_url']);

        $eventsFound = count($events);
        $eventsImported = 0;
        $eventsUpdated = 0;
        $eventsSkipped = 0;

        // Import events as reservations
        foreach ($events as $event) {
            if (empty($event['uid']) || empty($event['start_date']) || empty($event['end_date'])) {
                $eventsSkipped++;
                continue;
            }

            // Check if event already exists
            $stmt = $pdo->prepare("
                SELECT id FROM ical_reservations
                WHERE connection_id = ? AND ical_uid = ?
            ");
            $stmt->execute([$connection_id, $event['uid']]);
            $existingId = $stmt->fetchColumn();

            $status = 'confirmed';
            if (isset($event['status'])) {
                $statusMap = [
                    'CONFIRMED' => 'confirmed',
                    'TENTATIVE' => 'pending',
                    'CANCELLED' => 'cancelled'
                ];
                $status = $statusMap[strtoupper($event['status'])] ?? 'confirmed';
            }

            if ($existingId) {
                // Update existing reservation
                $stmt = $pdo->prepare("
                    UPDATE ical_reservations SET
                        summary = ?,
                        description = ?,
                        start_date = ?,
                        end_date = ?,
                        guest_name = ?,
                        guest_email = ?,
                        guest_phone = ?,
                        status = ?,
                        is_blocked = ?,
                        platform_reservation_id = ?,
                        num_nights = ?,
                        metadata = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");

                $stmt->execute([
                    $event['summary'],
                    $event['description'],
                    $event['start_date'],
                    $event['end_date'],
                    $event['guest_name'],
                    $event['guest_email'],
                    $event['guest_phone'],
                    $status,
                    $event['is_blocked'] ? 1 : 0,
                    $event['platform_reservation_id'],
                    $event['num_nights'],
                    json_encode($event['raw_data']),
                    $existingId
                ]);

                $eventsUpdated++;
            } else {
                // Insert new reservation
                $stmt = $pdo->prepare("
                    INSERT INTO ical_reservations (
                        connection_id, ical_uid, summary, description,
                        start_date, end_date, guest_name, guest_email, guest_phone,
                        status, is_blocked, platform_reservation_id, num_nights, metadata
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $connection_id,
                    $event['uid'],
                    $event['summary'],
                    $event['description'],
                    $event['start_date'],
                    $event['end_date'],
                    $event['guest_name'],
                    $event['guest_email'],
                    $event['guest_phone'],
                    $status,
                    $event['is_blocked'] ? 1 : 0,
                    $event['platform_reservation_id'],
                    $event['num_nights'],
                    json_encode($event['raw_data'])
                ]);

                $eventsImported++;
            }
        }

        $duration = (microtime(true) - $startTime) * 1000;

        // Update connection sync status
        $stmt = $pdo->prepare("
            UPDATE travel_account_connections SET
                ical_last_sync = CURRENT_TIMESTAMP,
                ical_sync_status = 'success',
                ical_error_message = NULL
            WHERE id = ?
        ");
        $stmt->execute([$connection_id]);

        // Log sync
        $stmt = $pdo->prepare("
            INSERT INTO ical_sync_log (
                connection_id, ical_url, sync_status,
                events_found, events_imported, events_updated, events_skipped,
                raw_ical_data, sync_duration_ms
            ) VALUES (?, ?, 'success', ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $connection_id,
            $connection['ical_url'],
            $eventsFound,
            $eventsImported,
            $eventsUpdated,
            $eventsSkipped,
            $parser->getRawData(),
            round($duration)
        ]);

        echo json_encode([
            'success' => true,
            'message' => "Synchronisation réussie : $eventsImported nouvelle(s), $eventsUpdated mise(s) à jour",
            'stats' => [
                'events_found' => $eventsFound,
                'events_imported' => $eventsImported,
                'events_updated' => $eventsUpdated,
                'events_skipped' => $eventsSkipped,
                'duration_ms' => round($duration)
            ]
        ]);

    } catch (Exception $e) {
        $duration = (microtime(true) - $startTime) * 1000;

        // Update connection with error
        $stmt = $pdo->prepare("
            UPDATE travel_account_connections SET
                ical_last_sync = CURRENT_TIMESTAMP,
                ical_sync_status = 'error',
                ical_error_message = ?
            WHERE id = ?
        ");
        $stmt->execute([$e->getMessage(), $connection_id]);

        // Log error
        $stmt = $pdo->prepare("
            INSERT INTO ical_sync_log (
                connection_id, ical_url, sync_status, error_message, sync_duration_ms
            ) VALUES (?, ?, 'error', ?, ?)
        ");
        $stmt->execute([
            $connection_id,
            $connection['ical_url'],
            $e->getMessage(),
            round($duration)
        ]);

        throw $e;
    }
}

/**
 * Sync all iCal calendars
 */
function syncAllIcals($pdo) {
    $stmt = $pdo->query("
        SELECT id, account_name, ical_url
        FROM travel_account_connections
        WHERE ical_url IS NOT NULL AND ical_url != ''
    ");

    $connections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results = [];

    foreach ($connections as $conn) {
        try {
            // Simulate the sync request
            $_POST = json_encode(['connection_id' => $conn['id']]);
            ob_start();
            syncIcal($pdo);
            $output = ob_get_clean();
            $result = json_decode($output, true);

            $results[] = [
                'connection_id' => $conn['id'],
                'account_name' => $conn['account_name'],
                'success' => true,
                'stats' => $result['stats'] ?? []
            ];
        } catch (Exception $e) {
            error_log('ical_sync_api.php syncAllIcals: ' . $e->getMessage());
            $results[] = [
                'connection_id' => $conn['id'],
                'account_name' => $conn['account_name'],
                'success' => false,
                'error' => 'Une erreur interne est survenue.'
            ];
        }
    }

    $successCount = count(array_filter($results, fn($r) => $r['success']));
    $totalCount = count($results);

    echo json_encode([
        'success' => true,
        'message' => "Synchronisé $successCount/$totalCount calendrier(s)",
        'results' => $results
    ]);
}

/**
 * Get all reservations
 */
function getReservations($pdo) {
    $connection_id = $_GET['connection_id'] ?? null;
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    $status = $_GET['status'] ?? null;
    $period = $_GET['period'] ?? null; // upcoming, current, past

    $sql = "SELECT * FROM v_all_reservations WHERE 1=1";
    $params = [];

    if ($connection_id) {
        $sql .= " AND connection_id = ?";
        $params[] = $connection_id;
    }

    if ($start_date && $end_date) {
        $sql .= " AND start_date <= ? AND end_date >= ?";
        $params[] = $end_date;
        $params[] = $start_date;
    }

    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }

    if ($period) {
        $sql .= " AND reservation_period = ?";
        $params[] = $period;
    }

    $sql .= " ORDER BY start_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'reservations' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

/**
 * Get calendar events (for calendar view)
 */
function getCalendarEvents($pdo) {
    $start = $_GET['start'] ?? date('Y-m-01');
    $end = $_GET['end'] ?? date('Y-m-t', strtotime('+3 months'));

    $stmt = $pdo->prepare("
        SELECT
            id,
            ical_uid,
            summary as title,
            start_date as start,
            end_date as end,
            guest_name,
            status,
            is_blocked,
            platform_name,
            platform_code,
            listing_title,
            CASE
                WHEN is_blocked = 1 THEN '#95a5a6'
                WHEN platform_code = 'airbnb' THEN '#ff385c'
                WHEN platform_code = 'booking' THEN '#003b95'
                WHEN platform_code = 'direct' THEN '#2e7d32'
                ELSE '#667eea'
            END as color
        FROM v_all_reservations
        WHERE start_date <= ? AND end_date >= ?
        ORDER BY start_date
    ");

    $stmt->execute([$end, $start]);

    echo json_encode([
        'success' => true,
        'events' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

/**
 * Delete a reservation
 */
function deleteReservation($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $reservation_id = $input['reservation_id'] ?? null;

    if (!$reservation_id) {
        throw new Exception('Reservation ID is required');
    }

    $stmt = $pdo->prepare("DELETE FROM ical_reservations WHERE id = ?");
    $stmt->execute([$reservation_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Réservation supprimée'
    ]);
}

/**
 * Get sync logs
 */
function getSyncLogs($pdo) {
    $connection_id = $_GET['connection_id'] ?? null;
    $limit = $_GET['limit'] ?? 50;

    $sql = "
        SELECT
            l.*,
            c.account_name,
            p.name as platform_name
        FROM ical_sync_log l
        JOIN travel_account_connections c ON l.connection_id = c.id
        JOIN travel_platforms p ON c.platform_id = p.id
        WHERE 1=1
    ";

    $params = [];

    if ($connection_id) {
        $sql .= " AND l.connection_id = ?";
        $params[] = $connection_id;
    }

    $sql .= " ORDER BY l.synced_at DESC LIMIT ?";
    $params[] = (int)$limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Remove raw_ical_data from response (too large)
    foreach ($logs as &$log) {
        unset($log['raw_ical_data']);
    }

    echo json_encode([
        'success' => true,
        'logs' => $logs
    ]);
}