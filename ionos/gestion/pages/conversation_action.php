<?php
/**
 * API AJAX — Actions sur les conversations (archive, mark_read, delete, etc.)
 * Adapté depuis raspberry-pi/web/pages/conversation_action.php
 */
require_once __DIR__ . '/../includes/error_handler.php';
require_once __DIR__ . '/../includes/rpi_db.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$phone = $_POST['phone'] ?? $_GET['phone'] ?? '';

if (empty($action) || empty($phone)) {
    echo json_encode(['success' => false, 'error' => 'Paramètres manquants (action, phone)']);
    exit;
}

$phone_clean = preg_replace('/[^0-9+]/', '', $phone);
$phone_0 = preg_replace('/^\+33/', '0', $phone_clean);

try {
    $pdo = getRpiPdo();

    switch ($action) {
        case 'archive':
            $stmt = $pdo->prepare("UPDATE sms_in SET archived = 1 WHERE sender = ? OR sender = ?");
            $stmt->execute([$phone_clean, $phone_0]);
            echo json_encode(['success' => true, 'message' => "Conversation archivée ({$stmt->rowCount()} messages)", 'affected' => $stmt->rowCount()]);
            break;

        case 'unarchive':
            $stmt = $pdo->prepare("UPDATE sms_in SET archived = 0 WHERE sender = ? OR sender = ?");
            $stmt->execute([$phone_clean, $phone_0]);
            echo json_encode(['success' => true, 'message' => "Conversation désarchivée", 'affected' => $stmt->rowCount()]);
            break;

        case 'mark_read':
            $stmt = $pdo->prepare("UPDATE sms_in SET is_read = 1 WHERE sender = ? OR sender = ?");
            $stmt->execute([$phone_clean, $phone_0]);
            echo json_encode(['success' => true, 'message' => "Messages marqués comme lus", 'affected' => $stmt->rowCount()]);
            break;

        case 'mark_unread':
            $stmt = $pdo->prepare("UPDATE sms_in SET is_read = 0 WHERE sender = ? OR sender = ?");
            $stmt->execute([$phone_clean, $phone_0]);
            echo json_encode(['success' => true, 'message' => "Messages marqués comme non lus", 'affected' => $stmt->rowCount()]);
            break;

        case 'star':
            $stmt = $pdo->prepare("UPDATE sms_in SET starred = 1 WHERE sender = ? OR sender = ?");
            $stmt->execute([$phone_clean, $phone_0]);
            echo json_encode(['success' => true, 'message' => "Conversation marquée importante", 'affected' => $stmt->rowCount()]);
            break;

        case 'unstar':
            $stmt = $pdo->prepare("UPDATE sms_in SET starred = 0 WHERE sender = ? OR sender = ?");
            $stmt->execute([$phone_clean, $phone_0]);
            echo json_encode(['success' => true, 'message' => "Marquage retiré", 'affected' => $stmt->rowCount()]);
            break;

        case 'delete':
            $stmt1 = $pdo->prepare("DELETE FROM sms_in WHERE sender = ? OR sender = ?");
            $stmt1->execute([$phone_clean, $phone_0]);
            $stmt2 = $pdo->prepare("DELETE FROM sms_outbox WHERE receiver = ? OR receiver = ?");
            $stmt2->execute([$phone_clean, $phone_0]);
            echo json_encode(['success' => true, 'message' => "Conversation supprimée", 'affected' => $stmt1->rowCount() + $stmt2->rowCount()]);
            break;

        case 'get_stats':
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_received, SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_count, SUM(CASE WHEN starred = 1 THEN 1 ELSE 0 END) as starred_count, MAX(archived) as is_archived FROM sms_in WHERE sender = ? OR sender = ?");
            $stmt->execute([$phone_clean, $phone_0]);
            $stats_in = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt2 = $pdo->prepare("SELECT COUNT(*) as total_sent FROM sms_outbox WHERE receiver = ? OR receiver = ?");
            $stmt2->execute([$phone_clean, $phone_0]);
            $stats_out = $stmt2->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'stats' => [
                'total_received' => (int)($stats_in['total_received'] ?? 0),
                'total_sent' => (int)($stats_out['total_sent'] ?? 0),
                'read_count' => (int)($stats_in['read_count'] ?? 0),
                'starred_count' => (int)($stats_in['starred_count'] ?? 0),
                'is_archived' => (bool)($stats_in['is_archived'] ?? false)
            ]]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Action inconnue: ' . $action]);
    }
} catch (PDOException $e) {
    error_log('conversation_action.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Une erreur interne est survenue.']);
}
