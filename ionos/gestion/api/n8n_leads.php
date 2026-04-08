<?php
/**
 * API Leads pour n8n — Prospection proprietaires
 *
 * Endpoints :
 *   GET    /api/n8n_leads.php                    → Liste leads (filtres: statut, source, score_min, limit, offset)
 *   GET    /api/n8n_leads.php?id=X               → Detail d'un lead
 *   POST   /api/n8n_leads.php                    → Creer un lead (ou update si doublon email/telephone)
 *   PUT    /api/n8n_leads.php?id=X               → Modifier un lead
 *   POST   /api/n8n_leads.php?action=interaction → Ajouter une interaction
 *   GET    /api/n8n_leads.php?action=relances    → Leads necessitant une relance
 *   POST   /api/n8n_leads.php?action=bulk        → Import en masse (tableau de leads)
 *
 * Auth : X-API-Key header (meme systeme que index.php)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/lead_scoring.php';

// --- Auth (meme logique que index.php) ---
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

// --- Helpers ---
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonBody(): array {
    return json_decode(file_get_contents('php://input'), true) ?: [];
}

/**
 * Cherche un lead existant par email ou telephone (deduplication)
 */
function findExistingLead(PDO $conn, array $data): ?int {
    if (!empty($data['email'])) {
        $stmt = $conn->prepare("SELECT id FROM prospection_leads WHERE email = ? LIMIT 1");
        $stmt->execute([$data['email']]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
    }
    if (!empty($data['telephone'])) {
        $stmt = $conn->prepare("SELECT id FROM prospection_leads WHERE telephone = ? LIMIT 1");
        $stmt->execute([$data['telephone']]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
    }
    return null;
}

// --- Router ---
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

    // === GET leads necessitant relance ===
    if ($method === 'GET' && $action === 'relances') {
        $leads = getLeadsNeedingFollowUp($conn);
        jsonResponse(['data' => $leads, 'count' => count($leads)]);
    }

    // === GET un lead ou liste ===
    if ($method === 'GET' && !$action) {
        if ($id) {
            $stmt = $conn->prepare("SELECT * FROM prospection_leads WHERE id = ?");
            $stmt->execute([$id]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$lead) jsonResponse(['error' => 'Lead non trouve'], 404);

            // Ajouter les interactions
            $stmt = $conn->prepare("SELECT * FROM prospection_interactions WHERE lead_id = ? ORDER BY created_at DESC");
            $stmt->execute([$id]);
            $lead['interactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            jsonResponse(['data' => $lead]);
        }

        // Liste avec filtres
        $where = [];
        $params = [];

        if (!empty($_GET['statut'])) {
            $where[] = "statut = ?";
            $params[] = $_GET['statut'];
        }
        if (!empty($_GET['source'])) {
            $where[] = "source = ?";
            $params[] = $_GET['source'];
        }
        if (!empty($_GET['score_min'])) {
            $where[] = "score >= ?";
            $params[] = (int)$_GET['score_min'];
        }
        if (!empty($_GET['ville'])) {
            $where[] = "ville LIKE ?";
            $params[] = '%' . $_GET['ville'] . '%';
        }
        if (!empty($_GET['since'])) {
            $where[] = "created_at >= ?";
            $params[] = $_GET['since'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit = min((int)($_GET['limit'] ?? 100), 500);
        $offset = max((int)($_GET['offset'] ?? 0), 0);

        $stmt = $conn->prepare("
            SELECT * FROM prospection_leads
            $whereClause
            ORDER BY score DESC, created_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count total
        $countStmt = $conn->prepare("SELECT COUNT(*) FROM prospection_leads $whereClause");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        jsonResponse(['data' => $leads, 'total' => $total, 'limit' => $limit, 'offset' => $offset]);
    }

    // === POST creer un lead (avec deduplication) ===
    if ($method === 'POST' && !$action) {
        $body = getJsonBody();

        if (empty($body['nom']) && empty($body['email']) && empty($body['telephone'])) {
            jsonResponse(['error' => 'Au moins nom, email ou telephone requis'], 400);
        }

        // Deduplication
        $existingId = findExistingLead($conn, $body);

        if ($existingId) {
            // Mettre a jour le lead existant avec les nouvelles infos
            $updates = [];
            $params = [];
            $updatableFields = ['nom','prenom','ville','surface','capacite','tarif_nuit_estime',
                                'revenu_mensuel_estime','nb_annonces','note_moyenne','host_profile_id','notes'];

            foreach ($updatableFields as $f) {
                if (!empty($body[$f])) {
                    $updates[] = "$f = ?";
                    $params[] = $body[$f];
                }
            }

            // Toujours mettre a jour la date de derniere interaction
            $updates[] = "date_derniere_interaction = NOW()";
            $updates[] = "updated_at = NOW()";
            $params[] = $existingId;

            if (!empty($updates)) {
                $conn->prepare("UPDATE prospection_leads SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
            }

            // Ajouter une interaction automatique
            $conn->prepare("INSERT INTO prospection_interactions (lead_id, type, contenu) VALUES (?, 'note', ?)")
                ->execute([$existingId, 'Mise a jour via n8n — source: ' . ($body['source'] ?? 'n8n')]);

            // Recalculer le score
            $newScore = updateLeadScore($conn, $existingId);

            jsonResponse([
                'data' => ['id' => $existingId, 'score' => $newScore],
                'is_new' => false,
                'message' => 'Lead existant mis a jour'
            ]);
        }

        // Nouveau lead
        $body['source'] = $body['source'] ?? 'n8n_airbnb';
        $body['statut'] = $body['statut'] ?? 'nouveau';
        $body['priorite'] = $body['priorite'] ?? 'moyenne';

        $leadId = createLead($conn, $body);
        if (!$leadId) {
            jsonResponse(['error' => 'Erreur creation lead'], 500);
        }

        // Interaction initiale
        $conn->prepare("INSERT INTO prospection_interactions (lead_id, type, contenu) VALUES (?, 'note', ?)")
            ->execute([$leadId, 'Lead cree via n8n — source: ' . $body['source']]);

        $stmt = $conn->prepare("SELECT * FROM prospection_leads WHERE id = ?");
        $stmt->execute([$leadId]);

        jsonResponse([
            'data' => $stmt->fetch(PDO::FETCH_ASSOC),
            'is_new' => true,
            'message' => 'Lead cree'
        ], 201);
    }

    // === POST interaction ===
    if ($method === 'POST' && $action === 'interaction') {
        $body = getJsonBody();

        if (empty($body['lead_id'])) jsonResponse(['error' => 'lead_id requis'], 400);
        if (empty($body['type'])) jsonResponse(['error' => 'type requis (note|appel|email|sms|rdv|relance|proposition|contrat)'], 400);

        $validTypes = ['note','appel','email','sms','rdv','relance','proposition','contrat','conversion'];
        if (!in_array($body['type'], $validTypes)) {
            jsonResponse(['error' => 'Type invalide. Valeurs: ' . implode(', ', $validTypes)], 400);
        }

        $stmt = $conn->prepare("SELECT id FROM prospection_leads WHERE id = ?");
        $stmt->execute([(int)$body['lead_id']]);
        if (!$stmt->fetchColumn()) jsonResponse(['error' => 'Lead non trouve'], 404);

        $conn->prepare("INSERT INTO prospection_interactions (lead_id, type, contenu) VALUES (?, ?, ?)")
            ->execute([(int)$body['lead_id'], $body['type'], $body['contenu'] ?? '']);

        // Mettre a jour la date de derniere interaction
        $conn->prepare("UPDATE prospection_leads SET date_derniere_interaction = NOW(), updated_at = NOW() WHERE id = ?")
            ->execute([(int)$body['lead_id']]);

        // Recalculer le score
        $newScore = updateLeadScore($conn, (int)$body['lead_id']);

        jsonResponse([
            'message' => 'Interaction ajoutee',
            'score' => $newScore
        ], 201);
    }

    // === POST bulk import ===
    if ($method === 'POST' && $action === 'bulk') {
        $body = getJsonBody();

        if (empty($body['leads']) || !is_array($body['leads'])) {
            jsonResponse(['error' => 'Champ "leads" requis (tableau)'], 400);
        }

        $results = ['created' => 0, 'updated' => 0, 'errors' => 0, 'details' => []];

        foreach ($body['leads'] as $i => $leadData) {
            try {
                $existingId = findExistingLead($conn, $leadData);

                if ($existingId) {
                    // Update
                    $conn->prepare("UPDATE prospection_leads SET date_derniere_interaction = NOW(), updated_at = NOW() WHERE id = ?")
                        ->execute([$existingId]);
                    updateLeadScore($conn, $existingId);
                    $results['updated']++;
                    $results['details'][] = ['index' => $i, 'id' => $existingId, 'action' => 'updated'];
                } else {
                    $leadData['source'] = $leadData['source'] ?? 'n8n_airbnb';
                    $leadData['statut'] = $leadData['statut'] ?? 'nouveau';
                    $leadId = createLead($conn, $leadData);
                    $results['created']++;
                    $results['details'][] = ['index' => $i, 'id' => $leadId, 'action' => 'created'];
                }
            } catch (Exception $e) {
                $results['errors']++;
                $results['details'][] = ['index' => $i, 'error' => $e->getMessage()];
            }
        }

        jsonResponse(['data' => $results, 'message' => "Import: {$results['created']} crees, {$results['updated']} mis a jour, {$results['errors']} erreurs"], 201);
    }

    // === PUT modifier un lead ===
    if ($method === 'PUT' && $id) {
        $body = getJsonBody();

        $stmt = $conn->prepare("SELECT id FROM prospection_leads WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) jsonResponse(['error' => 'Lead non trouve'], 404);

        $updatableFields = ['nom','prenom','email','telephone','ville','source','surface','capacite',
                            'tarif_nuit_estime','revenu_mensuel_estime','equipements','statut','priorite',
                            'date_rdv','type_rdv','message_rdv','notes','host_profile_id','nb_annonces','note_moyenne'];

        $updates = [];
        $params = [];
        foreach ($updatableFields as $f) {
            if (array_key_exists($f, $body)) {
                $updates[] = "$f = ?";
                $params[] = $body[$f];
            }
        }

        if (empty($updates)) jsonResponse(['error' => 'Aucun champ a modifier'], 400);

        $updates[] = "updated_at = NOW()";
        $params[] = $id;

        $conn->prepare("UPDATE prospection_leads SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);

        $newScore = updateLeadScore($conn, $id);

        $stmt = $conn->prepare("SELECT * FROM prospection_leads WHERE id = ?");
        $stmt->execute([$id]);

        jsonResponse(['data' => $stmt->fetch(PDO::FETCH_ASSOC), 'message' => 'Lead mis a jour']);
    }

    // === DELETE un lead ===
    if ($method === 'DELETE' && $id) {
        $stmt = $conn->prepare("SELECT id FROM prospection_leads WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) jsonResponse(['error' => 'Lead non trouve'], 404);

        $conn->prepare("DELETE FROM prospection_interactions WHERE lead_id = ?")->execute([$id]);
        $conn->prepare("DELETE FROM prospection_leads WHERE id = ?")->execute([$id]);

        jsonResponse(['message' => 'Lead supprime']);
    }

    jsonResponse(['error' => 'Methode ou action non supportee'], 405);

} catch (PDOException $e) {
    jsonResponse(['error' => 'Erreur BDD: ' . $e->getMessage()], 500);
}
