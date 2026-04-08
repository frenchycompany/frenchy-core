<?php
/**
 * API Competitors pour n8n — Veille concurrentielle Airbnb
 *
 * Endpoints :
 *   GET    /api/n8n_competitors.php                          → Liste concurrents (filtres: ville, superhost, min_avis)
 *   GET    /api/n8n_competitors.php?id=X                     → Detail concurrent + prix
 *   POST   /api/n8n_competitors.php                          → Creer/update concurrent (dedup par airbnb_id)
 *   POST   /api/n8n_competitors.php?action=price             → Ajouter un prix
 *   POST   /api/n8n_competitors.php?action=bulk              → Import en masse
 *   GET    /api/n8n_competitors.php?action=multi_hosts       → Proprietaires multi-annonces (prospects)
 *   POST   /api/n8n_competitors.php?action=to_lead           → Convertir un host en lead prospection
 *
 * Auth : X-API-Key
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

function getJsonBody(): array {
    return json_decode(file_get_contents('php://input'), true) ?: [];
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

    // === GET multi_hosts — Proprietaires avec plusieurs annonces (prospects potentiels) ===
    if ($method === 'GET' && $action === 'multi_hosts') {
        $minAnnonces = max((int)($_GET['min'] ?? 2), 1);

        $stmt = $conn->prepare("
            SELECT host_name, host_profile_id,
                   COUNT(*) as nb_annonces,
                   GROUP_CONCAT(nom SEPARATOR ' | ') as annonces,
                   AVG(note_moyenne) as note_moy,
                   SUM(nb_avis) as total_avis,
                   MAX(superhost) as is_superhost
            FROM market_competitors
            WHERE host_name IS NOT NULL AND host_name != ''
            GROUP BY host_name, host_profile_id
            HAVING nb_annonces >= ?
            ORDER BY nb_annonces DESC
        ");
        $stmt->execute([$minAnnonces]);
        $hosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Verifier lesquels sont deja des leads
        foreach ($hosts as &$host) {
            $stmt = $conn->prepare("
                SELECT id, statut, score FROM prospection_leads
                WHERE host_profile_id = ? OR nom = ?
                LIMIT 1
            ");
            $stmt->execute([$host['host_profile_id'], $host['host_name']]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC);
            $host['lead_id'] = $lead ? (int)$lead['id'] : null;
            $host['lead_statut'] = $lead['statut'] ?? null;
            $host['lead_score'] = $lead ? (int)$lead['score'] : null;
        }

        jsonResponse(['data' => $hosts, 'count' => count($hosts)]);
    }

    // === POST convertir un host en lead ===
    if ($method === 'POST' && $action === 'to_lead') {
        $body = getJsonBody();

        if (empty($body['host_name']) && empty($body['host_profile_id'])) {
            jsonResponse(['error' => 'host_name ou host_profile_id requis'], 400);
        }

        // Chercher les infos du host
        $where = [];
        $params = [];
        if (!empty($body['host_profile_id'])) {
            $where[] = "host_profile_id = ?";
            $params[] = $body['host_profile_id'];
        } else {
            $where[] = "host_name = ?";
            $params[] = $body['host_name'];
        }

        $stmt = $conn->prepare("
            SELECT host_name, host_profile_id, COUNT(*) as nb_annonces,
                   AVG(note_moyenne) as note_moy, MAX(superhost) as is_superhost,
                   GROUP_CONCAT(DISTINCT ville) as villes
            FROM market_competitors
            WHERE " . implode(' AND ', $where) . "
            GROUP BY host_name, host_profile_id
        ");
        $stmt->execute($params);
        $hostInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$hostInfo) jsonResponse(['error' => 'Host non trouve dans market_competitors'], 404);

        // Verifier si deja un lead
        $stmt = $conn->prepare("SELECT id FROM prospection_leads WHERE host_profile_id = ? LIMIT 1");
        $stmt->execute([$hostInfo['host_profile_id']]);
        $existingLead = $stmt->fetchColumn();

        if ($existingLead) {
            jsonResponse(['data' => ['id' => (int)$existingLead], 'is_new' => false, 'message' => 'Lead existant']);
        }

        // Creer le lead
        $leadData = [
            'nom' => $hostInfo['host_name'],
            'ville' => $hostInfo['villes'],
            'source' => 'n8n_airbnb',
            'host_profile_id' => $hostInfo['host_profile_id'],
            'nb_annonces' => (int)$hostInfo['nb_annonces'],
            'note_moyenne' => round((float)$hostInfo['note_moy'], 2),
            'notes' => "Proprietaire Airbnb avec {$hostInfo['nb_annonces']} annonce(s). " .
                       ($hostInfo['is_superhost'] ? 'Superhost. ' : '') .
                       "Converti depuis veille concurrentielle.",
            'email' => $body['email'] ?? null,
            'telephone' => $body['telephone'] ?? null,
        ];

        $leadId = createLead($conn, $leadData);

        $conn->prepare("INSERT INTO prospection_interactions (lead_id, type, contenu) VALUES (?, 'note', ?)")
            ->execute([$leadId, "Lead cree depuis host Airbnb ({$hostInfo['nb_annonces']} annonces) via n8n"]);

        jsonResponse(['data' => ['id' => $leadId], 'is_new' => true, 'message' => 'Lead cree depuis host Airbnb'], 201);
    }

    // === GET liste ou detail ===
    if ($method === 'GET' && !$action) {
        if ($id) {
            $stmt = $conn->prepare("SELECT * FROM market_competitors WHERE id = ?");
            $stmt->execute([$id]);
            $comp = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$comp) jsonResponse(['error' => 'Concurrent non trouve'], 404);

            // Prix recents
            $stmt = $conn->prepare("
                SELECT * FROM market_prices WHERE competitor_id = ? ORDER BY date_sejour DESC LIMIT 30
            ");
            $stmt->execute([$id]);
            $comp['prices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            jsonResponse(['data' => $comp]);
        }

        $where = [];
        $params = [];
        if (!empty($_GET['ville'])) {
            $where[] = "ville LIKE ?";
            $params[] = '%' . $_GET['ville'] . '%';
        }
        if (isset($_GET['superhost'])) {
            $where[] = "superhost = ?";
            $params[] = (int)$_GET['superhost'];
        }
        if (!empty($_GET['min_avis'])) {
            $where[] = "nb_avis >= ?";
            $params[] = (int)$_GET['min_avis'];
        }
        if (!empty($_GET['host_name'])) {
            $where[] = "host_name LIKE ?";
            $params[] = '%' . $_GET['host_name'] . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit = min((int)($_GET['limit'] ?? 100), 500);
        $offset = max((int)($_GET['offset'] ?? 0), 0);

        $stmt = $conn->prepare("SELECT * FROM market_competitors $whereClause ORDER BY nb_avis DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);

        jsonResponse(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'limit' => $limit, 'offset' => $offset]);
    }

    // === POST creer/update concurrent ===
    if ($method === 'POST' && !$action) {
        $body = getJsonBody();

        // Extraction airbnb_id depuis URL
        $airbnbId = $body['airbnb_id'] ?? null;
        if (!$airbnbId && !empty($body['url'])) {
            if (preg_match('/rooms\/(\d+)/', $body['url'], $matches)) {
                $airbnbId = $matches[1];
            }
        }

        // Deduplication par airbnb_id
        $existingId = null;
        if ($airbnbId) {
            $stmt = $conn->prepare("SELECT id FROM market_competitors WHERE airbnb_id = ?");
            $stmt->execute([$airbnbId]);
            $existingId = $stmt->fetchColumn();
        }

        if ($existingId) {
            $stmt = $conn->prepare("
                UPDATE market_competitors SET
                    nom = COALESCE(?, nom),
                    url = COALESCE(?, url),
                    ville = COALESCE(?, ville),
                    quartier = COALESCE(?, quartier),
                    type_logement = COALESCE(?, type_logement),
                    capacite = COALESCE(?, capacite),
                    chambres = COALESCE(?, chambres),
                    lits = COALESCE(?, lits),
                    salles_bain = COALESCE(?, salles_bain),
                    note_moyenne = COALESCE(?, note_moyenne),
                    nb_avis = COALESCE(?, nb_avis),
                    superhost = COALESCE(?, superhost),
                    host_name = COALESCE(?, host_name),
                    host_profile_id = COALESCE(?, host_profile_id),
                    photo_url = COALESCE(?, photo_url),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $body['nom'] ?? null, $body['url'] ?? null, $body['ville'] ?? null,
                $body['quartier'] ?? null, $body['type_logement'] ?? null,
                $body['capacite'] ?? null, $body['chambres'] ?? null,
                $body['lits'] ?? null, $body['salles_bain'] ?? null,
                $body['note_moyenne'] ?? null, $body['nb_avis'] ?? null,
                isset($body['superhost']) ? ($body['superhost'] ? 1 : 0) : null,
                $body['host_name'] ?? null, $body['host_profile_id'] ?? null,
                $body['photo_url'] ?? null, $existingId
            ]);

            jsonResponse(['data' => ['id' => (int)$existingId], 'is_new' => false, 'message' => 'Concurrent mis a jour']);
        }

        // Nouveau concurrent
        $stmt = $conn->prepare("
            INSERT INTO market_competitors
            (airbnb_id, nom, url, ville, quartier, type_logement, capacite, chambres, lits, salles_bain,
             note_moyenne, nb_avis, superhost, host_name, host_profile_id, photo_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $airbnbId, $body['nom'] ?? 'Annonce sans nom', $body['url'] ?? null,
            $body['ville'] ?? null, $body['quartier'] ?? null,
            $body['type_logement'] ?? 'appartement',
            $body['capacite'] ?? null, $body['chambres'] ?? null,
            $body['lits'] ?? null, $body['salles_bain'] ?? null,
            $body['note_moyenne'] ?? null, $body['nb_avis'] ?? null,
            isset($body['superhost']) ? ($body['superhost'] ? 1 : 0) : 0,
            $body['host_name'] ?? null, $body['host_profile_id'] ?? null,
            $body['photo_url'] ?? null
        ]);

        jsonResponse(['data' => ['id' => (int)$conn->lastInsertId()], 'is_new' => true, 'message' => 'Concurrent cree'], 201);
    }

    // === POST prix ===
    if ($method === 'POST' && $action === 'price') {
        $body = getJsonBody();

        if (empty($body['competitor_id']) || empty($body['date_sejour']) || !isset($body['prix_nuit'])) {
            jsonResponse(['error' => 'competitor_id, date_sejour et prix_nuit requis'], 400);
        }

        // Upsert prix
        $stmt = $conn->prepare("SELECT id FROM market_prices WHERE competitor_id = ? AND date_sejour = ?");
        $stmt->execute([(int)$body['competitor_id'], $body['date_sejour']]);
        $existingPrice = $stmt->fetchColumn();

        if ($existingPrice) {
            $conn->prepare("UPDATE market_prices SET prix_nuit = ?, source = ? WHERE id = ?")
                ->execute([$body['prix_nuit'], $body['source'] ?? 'n8n', $existingPrice]);
        } else {
            $conn->prepare("INSERT INTO market_prices (competitor_id, date_sejour, prix_nuit, source) VALUES (?, ?, ?, ?)")
                ->execute([(int)$body['competitor_id'], $body['date_sejour'], $body['prix_nuit'], $body['source'] ?? 'n8n']);
        }

        jsonResponse(['message' => 'Prix enregistre']);
    }

    // === POST bulk ===
    if ($method === 'POST' && $action === 'bulk') {
        $body = getJsonBody();

        if (empty($body['competitors']) || !is_array($body['competitors'])) {
            jsonResponse(['error' => 'Champ "competitors" requis (tableau)'], 400);
        }

        $results = ['created' => 0, 'updated' => 0, 'errors' => 0];

        foreach ($body['competitors'] as $comp) {
            try {
                $airbnbId = $comp['airbnb_id'] ?? null;
                if (!$airbnbId && !empty($comp['url'])) {
                    if (preg_match('/rooms\/(\d+)/', $comp['url'], $m)) $airbnbId = $m[1];
                }

                $existing = null;
                if ($airbnbId) {
                    $stmt = $conn->prepare("SELECT id FROM market_competitors WHERE airbnb_id = ?");
                    $stmt->execute([$airbnbId]);
                    $existing = $stmt->fetchColumn();
                }

                if ($existing) {
                    $conn->prepare("UPDATE market_competitors SET nom = COALESCE(?, nom), host_name = COALESCE(?, host_name), updated_at = NOW() WHERE id = ?")
                        ->execute([$comp['nom'] ?? null, $comp['host_name'] ?? null, $existing]);
                    $results['updated']++;
                } else {
                    $conn->prepare("INSERT INTO market_competitors (airbnb_id, nom, url, ville, host_name, host_profile_id) VALUES (?, ?, ?, ?, ?, ?)")
                        ->execute([$airbnbId, $comp['nom'] ?? 'Sans nom', $comp['url'] ?? null, $comp['ville'] ?? null, $comp['host_name'] ?? null, $comp['host_profile_id'] ?? null]);
                    $results['created']++;
                }
            } catch (Exception $e) {
                $results['errors']++;
            }
        }

        jsonResponse(['data' => $results]);
    }

    jsonResponse(['error' => 'Methode ou action non supportee'], 405);

} catch (PDOException $e) {
    jsonResponse(['error' => 'Erreur BDD: ' . $e->getMessage()], 500);
}
