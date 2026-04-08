<?php
/**
 * Webhook generique n8n → FrenchyConciergerie
 *
 * Point d'entree unique pour les notifications n8n.
 * Recoit des evenements et les route vers les actions appropriees.
 *
 * POST /api/n8n_webhook.php
 * Body JSON : { "event": "...", "data": {...} }
 *
 * Evenements supportes :
 *   - lead.status_change    → Changer le statut d'un lead
 *   - lead.score_refresh    → Recalculer le score d'un lead
 *   - lead.assign_rdv       → Planifier un RDV pour un lead
 *   - competitor.new        → Nouveau concurrent detecte (notification)
 *   - sms.send              → Envoyer un SMS via la file d'attente
 *   - ping                  → Test de connectivite
 *
 * Auth : X-API-Key
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST uniquement']);
    exit;
}

require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/lead_scoring.php';

// --- Auth ---
function apiAuth(PDO $conn): bool {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    if (empty($apiKey)) return false;
    try {
        $stmt = $conn->prepare("SELECT id FROM api_keys WHERE api_key = ? AND actif = 1 LIMIT 1");
        $stmt->execute([$apiKey]);
        $key = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($key) {
            $conn->prepare("UPDATE api_keys SET last_used = NOW(), nb_calls = nb_calls + 1 WHERE id = ?")->execute([$key['id']]);
            return true;
        }
    } catch (PDOException $e) {}
    $envKey = env('API_KEY', '');
    return !empty($envKey) && hash_equals($envKey, $apiKey);
}

if (!apiAuth($conn)) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorise']);
    exit;
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || empty($body['event'])) {
    jsonResponse(['error' => 'Champ "event" requis'], 400);
}

$event = $body['event'];
$data = $body['data'] ?? [];

// --- Log webhook ---
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS n8n_webhook_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event VARCHAR(50) NOT NULL,
            payload JSON,
            status VARCHAR(20) DEFAULT 'received',
            result TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $conn->prepare("INSERT INTO n8n_webhook_log (event, payload) VALUES (?, ?)")
        ->execute([$event, json_encode($body)]);
    $logId = (int)$conn->lastInsertId();
} catch (PDOException $e) {
    $logId = 0;
}

try {
    switch ($event) {

        // === Ping / test ===
        case 'ping':
            $result = ['pong' => true, 'timestamp' => date('c'), 'log_id' => $logId];
            if ($logId) {
                $conn->prepare("UPDATE n8n_webhook_log SET status = 'ok', result = 'pong' WHERE id = ?")->execute([$logId]);
            }
            jsonResponse($result);

        // === Changer le statut d'un lead ===
        case 'lead.status_change':
            if (empty($data['lead_id']) || empty($data['statut'])) {
                jsonResponse(['error' => 'lead_id et statut requis'], 400);
            }

            $validStatuts = ['nouveau','contacte','rdv_planifie','rdv_fait','proposition','negocie','converti','perdu'];
            if (!in_array($data['statut'], $validStatuts)) {
                jsonResponse(['error' => 'Statut invalide. Valeurs: ' . implode(', ', $validStatuts)], 400);
            }

            $stmt = $conn->prepare("SELECT id, statut FROM prospection_leads WHERE id = ?");
            $stmt->execute([(int)$data['lead_id']]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$lead) jsonResponse(['error' => 'Lead non trouve'], 404);

            $oldStatut = $lead['statut'];
            $conn->prepare("UPDATE prospection_leads SET statut = ?, updated_at = NOW(), date_derniere_interaction = NOW() WHERE id = ?")
                ->execute([$data['statut'], (int)$data['lead_id']]);

            // Logger l'interaction
            $conn->prepare("INSERT INTO prospection_interactions (lead_id, type, contenu) VALUES (?, 'note', ?)")
                ->execute([(int)$data['lead_id'], "Statut change: $oldStatut → {$data['statut']} (via n8n)"]);

            $newScore = updateLeadScore($conn, (int)$data['lead_id']);

            if ($logId) {
                $conn->prepare("UPDATE n8n_webhook_log SET status = 'ok', result = ? WHERE id = ?")
                    ->execute(["Lead {$data['lead_id']}: $oldStatut → {$data['statut']}", $logId]);
            }

            jsonResponse(['message' => 'Statut mis a jour', 'old' => $oldStatut, 'new' => $data['statut'], 'score' => $newScore]);

        // === Recalculer le score ===
        case 'lead.score_refresh':
            if (empty($data['lead_id'])) jsonResponse(['error' => 'lead_id requis'], 400);

            $newScore = updateLeadScore($conn, (int)$data['lead_id']);
            jsonResponse(['message' => 'Score recalcule', 'score' => $newScore]);

        // === Planifier un RDV ===
        case 'lead.assign_rdv':
            if (empty($data['lead_id']) || empty($data['date_rdv'])) {
                jsonResponse(['error' => 'lead_id et date_rdv requis'], 400);
            }

            $conn->prepare("
                UPDATE prospection_leads
                SET date_rdv = ?, type_rdv = ?, message_rdv = ?, statut = 'rdv_planifie',
                    date_derniere_interaction = NOW(), updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $data['date_rdv'],
                $data['type_rdv'] ?? 'telephone',
                $data['message_rdv'] ?? null,
                (int)$data['lead_id']
            ]);

            $conn->prepare("INSERT INTO prospection_interactions (lead_id, type, contenu) VALUES (?, 'rdv', ?)")
                ->execute([(int)$data['lead_id'], "RDV planifie le {$data['date_rdv']} ({$data['type_rdv']}) via n8n"]);

            $newScore = updateLeadScore($conn, (int)$data['lead_id']);
            jsonResponse(['message' => 'RDV planifie', 'score' => $newScore]);

        // === Notification nouveau concurrent ===
        case 'competitor.new':
            // Stocker pour revue dans le dashboard
            if ($logId) {
                $conn->prepare("UPDATE n8n_webhook_log SET status = 'ok', result = ? WHERE id = ?")
                    ->execute(['Notification concurrent enregistree', $logId]);
            }
            jsonResponse(['message' => 'Notification enregistree', 'log_id' => $logId]);

        // === Envoyer un SMS ===
        case 'sms.send':
            if (empty($data['destinataire']) || empty($data['message'])) {
                jsonResponse(['error' => 'destinataire et message requis'], 400);
            }

            // Inserer dans la file sms_outbox
            $conn->prepare("INSERT INTO sms_outbox (destinataire, message, statut) VALUES (?, ?, 'pending')")
                ->execute([$data['destinataire'], $data['message']]);

            if ($logId) {
                $conn->prepare("UPDATE n8n_webhook_log SET status = 'ok', result = ? WHERE id = ?")
                    ->execute(["SMS en file: {$data['destinataire']}", $logId]);
            }

            jsonResponse(['message' => 'SMS ajoute a la file d\'envoi'], 201);

        default:
            if ($logId) {
                $conn->prepare("UPDATE n8n_webhook_log SET status = 'unknown_event' WHERE id = ?")->execute([$logId]);
            }
            jsonResponse(['error' => "Evenement inconnu: $event", 'events_supportes' => [
                'ping', 'lead.status_change', 'lead.score_refresh', 'lead.assign_rdv',
                'competitor.new', 'sms.send'
            ]], 400);
    }

} catch (PDOException $e) {
    if ($logId) {
        $conn->prepare("UPDATE n8n_webhook_log SET status = 'error', result = ? WHERE id = ?")->execute([$e->getMessage(), $logId]);
    }
    jsonResponse(['error' => 'Erreur BDD: ' . $e->getMessage()], 500);
}
