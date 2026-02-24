<?php
/**
 * API pour les actions sur les conversations
 * Actions: archive, unarchive, mark_read, star, delete
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

// Récupérer les paramètres
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$phone = $_POST['phone'] ?? $_GET['phone'] ?? '';

if (empty($action) || empty($phone)) {
    echo json_encode(['success' => false, 'error' => 'Paramètres manquants (action, phone)']);
    exit;
}

// Normaliser le numéro de téléphone
$phone_clean = preg_replace('/[^0-9+]/', '', $phone);
$phone_0 = preg_replace('/^\+33/', '0', $phone_clean);

try {
    switch ($action) {
        case 'archive':
            $stmt = $pdo->prepare("UPDATE sms_in SET archived = 1 WHERE sender = ? OR sender = ?");
            $stmt->execute([$phone_clean, $phone_0]);
            $affected = $stmt->rowCount();
            echo json_encode([
                'success' => true,
                'message' => "Conversation archivée ($affected messages)",
                'affected' => $affected
            ]);
            break;

        case 'unarchive':
            $stmt = $pdo->prepare("UPDATE sms_in SET archived = 0 WHERE sender = ? OR sender = ?");
            $stmt->execute([$phone_clean, $phone_0]);
            $affected = $stmt->rowCount();
            echo json_encode([
                'success' => true,
                'message' => "Conversation désarchivée ($affected messages)",
                'affected' => $affected
            ]);
            break;

        case 'mark_read':
            $stmt = $pdo->prepare("UPDATE sms_in SET is_read = 1 WHERE sender = ? OR sender = ?");
            $stmt->execute([$phone_clean, $phone_0]);
            $affected = $stmt->rowCount();
            echo json_encode([
                'success' => true,
                'message' => "Messages marqués comme lus ($affected)",
                'affected' => $affected
            ]);
            break;

        case 'mark_unread':
            $stmt = $pdo->prepare("UPDATE sms_in SET is_read = 0 WHERE sender = ? OR sender = ?");
            $stmt->execute([$phone_clean, $phone_0]);
            $affected = $stmt->rowCount();
            echo json_encode([
                'success' => true,
                'message' => "Messages marqués comme non lus ($affected)",
                'affected' => $affected
            ]);
            break;

        case 'star':
            $stmt = $pdo->prepare("UPDATE sms_in SET starred = 1 WHERE sender = ? OR sender = ?");
            $stmt->execute([$phone_clean, $phone_0]);
            $affected = $stmt->rowCount();
            echo json_encode([
                'success' => true,
                'message' => "Conversation marquée importante ($affected messages)",
                'affected' => $affected
            ]);
            break;

        case 'unstar':
            $stmt = $pdo->prepare("UPDATE sms_in SET starred = 0 WHERE sender = ? OR sender = ?");
            $stmt->execute([$phone_clean, $phone_0]);
            $affected = $stmt->rowCount();
            echo json_encode([
                'success' => true,
                'message' => "Marquage important retiré ($affected messages)",
                'affected' => $affected
            ]);
            break;

        case 'delete':
            // Supprimer les messages reçus
            $stmt1 = $pdo->prepare("DELETE FROM sms_in WHERE sender = ? OR sender = ?");
            $stmt1->execute([$phone_clean, $phone_0]);
            $affected_in = $stmt1->rowCount();

            // Supprimer les messages envoyés
            $stmt2 = $pdo->prepare("DELETE FROM sms_outbox WHERE receiver = ? OR receiver = ?");
            $stmt2->execute([$phone_clean, $phone_0]);
            $affected_out = $stmt2->rowCount();

            echo json_encode([
                'success' => true,
                'message' => "Conversation supprimée ($affected_in reçus, $affected_out envoyés)",
                'affected' => $affected_in + $affected_out
            ]);
            break;

        case 'get_stats':
            // Statistiques de la conversation
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*) as total_received,
                    SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_count,
                    SUM(CASE WHEN starred = 1 THEN 1 ELSE 0 END) as starred_count,
                    MAX(archived) as is_archived
                FROM sms_in
                WHERE sender = ? OR sender = ?
            ");
            $stmt->execute([$phone_clean, $phone_0]);
            $stats_in = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt2 = $pdo->prepare("
                SELECT COUNT(*) as total_sent
                FROM sms_outbox
                WHERE receiver = ? OR receiver = ?
            ");
            $stmt2->execute([$phone_clean, $phone_0]);
            $stats_out = $stmt2->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'stats' => [
                    'total_received' => (int)($stats_in['total_received'] ?? 0),
                    'total_sent' => (int)($stats_out['total_sent'] ?? 0),
                    'read_count' => (int)($stats_in['read_count'] ?? 0),
                    'starred_count' => (int)($stats_in['starred_count'] ?? 0),
                    'is_archived' => (bool)($stats_in['is_archived'] ?? false)
                ]
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Action inconnue: ' . $action]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur base de données: ' . $e->getMessage()]);
}
