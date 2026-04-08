<?php
/**
 * API REST interne — FrenchyConciergerie
 * Point d'entree unique pour les endpoints API
 *
 * Endpoints :
 *   GET  /api/?endpoint=logements           → Liste des logements
 *   GET  /api/?endpoint=logements&id=X      → Detail d'un logement
 *   GET  /api/?endpoint=reservations        → Reservations (filtres: logement_id, date_debut, date_fin)
 *   GET  /api/?endpoint=planning            → Planning (filtres: date, logement_id)
 *   GET  /api/?endpoint=proprietaires       → Liste des proprietaires
 *   GET  /api/?endpoint=stats               → Statistiques globales
 *   POST /api/?endpoint=planning            → Creer/modifier une intervention
 *   POST /api/?endpoint=reservations        → Creer une reservation
 *
 * Auth : API key via header X-API-Key uniquement
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

// Authentification API
function apiAuth(PDO $conn): bool {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

    if (empty($apiKey)) {
        return false;
    }

    // Verifier en BDD
    try {
        $stmt = $conn->prepare("SELECT id, nom FROM api_keys WHERE api_key = ? AND actif = 1 LIMIT 1");
        $stmt->execute([$apiKey]);
        $key = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($key) {
            // Mettre a jour last_used
            $conn->prepare("UPDATE api_keys SET last_used = NOW(), nb_calls = nb_calls + 1 WHERE id = ?")->execute([$key['id']]);
            return true;
        }
    } catch (PDOException $e) {
        // Table n'existe pas encore, verifier env
    }

    // Fallback : cle dans .env
    $envKey = env('API_KEY', '');
    return !empty($envKey) && hash_equals($envKey, $apiKey);
}

// Auto-creation table api_keys
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS api_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            api_key VARCHAR(64) NOT NULL UNIQUE,
            actif TINYINT(1) DEFAULT 1,
            nb_calls INT DEFAULT 0,
            last_used TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (PDOException $e) {}

// Verifier auth
if (!apiAuth($conn)) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorise. Fournissez une API key valide via le header X-API-Key']);
    exit;
}

// Router
$endpoint = $_GET['endpoint'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function getJsonBody(): array {
    $body = file_get_contents('php://input');
    return json_decode($body, true) ?: [];
}

try {
    switch ($endpoint) {

        // === LOGEMENTS ===
        case 'logements':
            if ($method === 'GET') {
                if ($id) {
                    $stmt = $conn->prepare("
                        SELECT l.*, p.nom as proprietaire_nom, p.prenom as proprietaire_prenom
                        FROM liste_logements l
                        LEFT JOIN FC_proprietaires p ON l.proprietaire_id = p.id
                        WHERE l.id = ?
                    ");
                    $stmt->execute([$id]);
                    $logement = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$logement) jsonResponse(['error' => 'Logement non trouve'], 404);
                    jsonResponse(['data' => $logement]);
                } else {
                    $stmt = $conn->query("
                        SELECT l.id, l.nom_du_logement, l.adresse, l.capacite_voyageurs,
                               l.nbre_chambres_couchages, l.actif, l.proprietaire_id,
                               p.nom as proprietaire_nom
                        FROM liste_logements l
                        LEFT JOIN FC_proprietaires p ON l.proprietaire_id = p.id
                        ORDER BY l.nom_du_logement
                    ");
                    jsonResponse(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                }
            }
            break;

        // === RESERVATIONS ===
        case 'reservations':
            if ($method === 'GET') {
                $where = [];
                $params = [];

                if (!empty($_GET['logement_id'])) {
                    $where[] = "r.logement_id = ?";
                    $params[] = (int)$_GET['logement_id'];
                }
                if (!empty($_GET['date_debut'])) {
                    $where[] = "r.date_arrivee >= ?";
                    $params[] = $_GET['date_debut'];
                }
                if (!empty($_GET['date_fin'])) {
                    $where[] = "r.date_depart <= ?";
                    $params[] = $_GET['date_fin'];
                }
                if (!empty($_GET['statut'])) {
                    $where[] = "r.statut = ?";
                    $params[] = $_GET['statut'];
                }

                $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
                $limit = min((int)($_GET['limit'] ?? 100), 500);
                $offset = max((int)($_GET['offset'] ?? 0), 0);

                $stmt = $conn->prepare("
                    SELECT r.*, l.nom_du_logement
                    FROM reservations r
                    LEFT JOIN liste_logements l ON r.logement_id = l.id
                    $whereClause
                    ORDER BY r.date_arrivee DESC
                    LIMIT $limit OFFSET $offset
                ");
                $stmt->execute($params);
                jsonResponse(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'limit' => $limit, 'offset' => $offset]);

            } elseif ($method === 'POST') {
                $body = getJsonBody();
                $required = ['logement_id', 'date_arrivee', 'date_depart'];
                foreach ($required as $field) {
                    if (empty($body[$field])) {
                        jsonResponse(['error' => "Champ requis manquant : $field"], 400);
                    }
                }

                $stmt = $conn->prepare("
                    INSERT INTO reservations (logement_id, date_arrivee, date_depart, nom_voyageur, telephone, plateforme, statut, nombre_voyageurs)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    (int)$body['logement_id'],
                    $body['date_arrivee'],
                    $body['date_depart'],
                    $body['nom_voyageur'] ?? null,
                    $body['telephone'] ?? null,
                    $body['plateforme'] ?? 'direct',
                    $body['statut'] ?? 'confirmee',
                    $body['nombre_voyageurs'] ?? null,
                ]);

                jsonResponse(['data' => ['id' => (int)$conn->lastInsertId()], 'message' => 'Reservation creee'], 201);
            }
            break;

        // === PLANNING ===
        case 'planning':
            if ($method === 'GET') {
                $date = $_GET['date'] ?? date('Y-m-d');
                $logement_id = !empty($_GET['logement_id']) ? (int)$_GET['logement_id'] : null;

                $sql = "
                    SELECT p.*, l.nom_du_logement,
                           c.nom AS conducteur_nom,
                           fm1.nom AS fm1_nom, fm2.nom AS fm2_nom
                    FROM planning p
                    JOIN liste_logements l ON p.logement_id = l.id
                    LEFT JOIN intervenant c ON p.conducteur = c.id
                    LEFT JOIN intervenant fm1 ON p.femme_de_menage_1 = fm1.id
                    LEFT JOIN intervenant fm2 ON p.femme_de_menage_2 = fm2.id
                    WHERE p.date = ?
                ";
                $params = [$date];

                if ($logement_id) {
                    $sql .= " AND p.logement_id = ?";
                    $params[] = $logement_id;
                }

                $sql .= " ORDER BY l.nom_du_logement";

                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                jsonResponse(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'date' => $date]);

            } elseif ($method === 'POST') {
                $body = getJsonBody();
                if (empty($body['logement_id']) || empty($body['date'])) {
                    jsonResponse(['error' => 'logement_id et date requis'], 400);
                }

                $stmt = $conn->prepare("
                    INSERT INTO planning (logement_id, date, statut, conducteur, femme_de_menage_1, femme_de_menage_2, note)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        statut = VALUES(statut), conducteur = VALUES(conducteur),
                        femme_de_menage_1 = VALUES(femme_de_menage_1),
                        femme_de_menage_2 = VALUES(femme_de_menage_2),
                        note = VALUES(note)
                ");
                $stmt->execute([
                    (int)$body['logement_id'],
                    $body['date'],
                    $body['statut'] ?? 'À Faire',
                    $body['conducteur'] ?? null,
                    $body['femme_de_menage_1'] ?? null,
                    $body['femme_de_menage_2'] ?? null,
                    $body['note'] ?? null,
                ]);

                jsonResponse(['message' => 'Planning mis a jour'], 200);
            }
            break;

        // === PROPRIETAIRES ===
        case 'proprietaires':
            if ($method === 'GET') {
                $stmt = $conn->query("
                    SELECT p.*, COUNT(l.id) as nb_logements
                    FROM FC_proprietaires p
                    LEFT JOIN liste_logements l ON l.proprietaire_id = p.id
                    WHERE p.actif = 1
                    GROUP BY p.id
                    ORDER BY p.nom
                ");
                jsonResponse(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            }
            break;

        // === INTERVENANTS ===
        case 'intervenants':
            if ($method === 'GET') {
                $stmt = $conn->query("SELECT id, nom, telephone, email, role FROM intervenant ORDER BY nom");
                jsonResponse(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            }
            break;

        // === STATS ===
        case 'stats':
            $today = date('Y-m-d');
            $mois = date('Y-m');

            $stats = [
                'date' => $today,
            ];

            // Planning du jour
            $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(statut = 'Fait') as fait, SUM(statut = 'À Faire') as a_faire FROM planning WHERE date = ?");
            $stmt->execute([$today]);
            $stats['planning_jour'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Logements
            $stats['nb_logements'] = (int)$conn->query("SELECT COUNT(*) FROM liste_logements WHERE actif = 1")->fetchColumn();

            // Reservations du mois
            $stmt = $conn->prepare("SELECT COUNT(*) FROM reservations WHERE date_arrivee LIKE ?");
            $stmt->execute(["$mois%"]);
            $stats['reservations_mois'] = (int)$stmt->fetchColumn();

            jsonResponse(['data' => $stats]);

        default:
            jsonResponse([
                'error' => 'Endpoint inconnu',
                'endpoints_disponibles' => [
                    'GET logements', 'GET logements&id=X',
                    'GET/POST reservations',
                    'GET/POST planning',
                    'GET proprietaires',
                    'GET intervenants',
                    'GET stats',
                ]
            ], 404);
    }

} catch (PDOException $e) {
    jsonResponse(['error' => 'Erreur base de donnees : ' . $e->getMessage()], 500);
} catch (Exception $e) {
    jsonResponse(['error' => 'Erreur : ' . $e->getMessage()], 500);
}
