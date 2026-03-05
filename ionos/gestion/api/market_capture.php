<?php
/**
 * API endpoint pour capturer les donnees Airbnb via bookmarklet
 * Recoit les donnees en POST JSON et les stocke dans la base
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/rpi_db.php';
$pdo = getRpiPdo();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Lire le JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

try {
    // Extraire l'ID Airbnb de l'URL
    $airbnbId = null;
    if (!empty($data['url'])) {
        if (preg_match('/rooms\/(\d+)/', $data['url'], $matches)) {
            $airbnbId = $matches[1];
        }
    }

    // Verifier si le concurrent existe deja
    $existingId = null;
    if ($airbnbId) {
        $stmt = $pdo->prepare("SELECT id FROM market_competitors WHERE airbnb_id = ?");
        $stmt->execute([$airbnbId]);
        $existingId = $stmt->fetchColumn();
    }

    if ($existingId) {
        // Mettre a jour le concurrent existant
        $stmt = $pdo->prepare("
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
                photo_url = COALESCE(?, photo_url),
                equipements = COALESCE(?, equipements),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $data['nom'] ?? null,
            $data['url'] ?? null,
            $data['ville'] ?? null,
            $data['quartier'] ?? null,
            $data['type_logement'] ?? null,
            $data['capacite'] ?? null,
            $data['chambres'] ?? null,
            $data['lits'] ?? null,
            $data['salles_bain'] ?? null,
            $data['note_moyenne'] ?? null,
            $data['nb_avis'] ?? null,
            isset($data['superhost']) ? ($data['superhost'] ? 1 : 0) : null,
            $data['photo_url'] ?? null,
            isset($data['equipements']) ? json_encode($data['equipements']) : null,
            $existingId
        ]);
        $competitorId = $existingId;
        $isNew = false;
    } else {
        // Creer un nouveau concurrent
        $stmt = $pdo->prepare("
            INSERT INTO market_competitors
            (airbnb_id, nom, url, ville, quartier, type_logement, capacite, chambres, lits, salles_bain, note_moyenne, nb_avis, superhost, photo_url, equipements)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $airbnbId,
            $data['nom'] ?? 'Annonce sans nom',
            $data['url'] ?? null,
            $data['ville'] ?? null,
            $data['quartier'] ?? null,
            $data['type_logement'] ?? 'appartement',
            $data['capacite'] ?? null,
            $data['chambres'] ?? null,
            $data['lits'] ?? null,
            $data['salles_bain'] ?? null,
            $data['note_moyenne'] ?? null,
            $data['nb_avis'] ?? null,
            isset($data['superhost']) ? ($data['superhost'] ? 1 : 0) : 0,
            $data['photo_url'] ?? null,
            isset($data['equipements']) ? json_encode($data['equipements']) : null
        ]);
        $competitorId = $pdo->lastInsertId();
        $isNew = true;
    }

    // Ajouter le prix si fourni
    $priceAdded = false;
    if (!empty($data['prix_nuit']) && !empty($data['date_sejour'])) {
        // Verifier si un prix existe deja pour cette date
        $stmt = $pdo->prepare("
            SELECT id FROM market_prices
            WHERE competitor_id = ? AND date_sejour = ?
        ");
        $stmt->execute([$competitorId, $data['date_sejour']]);
        $existingPrice = $stmt->fetchColumn();

        if ($existingPrice) {
            // Mettre a jour
            $stmt = $pdo->prepare("
                UPDATE market_prices SET
                    prix_nuit = ?,
                    prix_total = ?,
                    frais_menage = ?,
                    frais_service = ?,
                    disponible = ?,
                    source = 'bookmarklet'
                WHERE id = ?
            ");
            $stmt->execute([
                $data['prix_nuit'],
                $data['prix_total'] ?? null,
                $data['frais_menage'] ?? null,
                $data['frais_service'] ?? null,
                isset($data['disponible']) ? ($data['disponible'] ? 1 : 0) : 1,
                $existingPrice
            ]);
        } else {
            // Inserer
            $stmt = $pdo->prepare("
                INSERT INTO market_prices
                (competitor_id, date_sejour, prix_nuit, prix_total, frais_menage, frais_service, disponible, source)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'bookmarklet')
            ");
            $stmt->execute([
                $competitorId,
                $data['date_sejour'],
                $data['prix_nuit'],
                $data['prix_total'] ?? null,
                $data['frais_menage'] ?? null,
                $data['frais_service'] ?? null,
                isset($data['disponible']) ? ($data['disponible'] ? 1 : 0) : 1
            ]);
        }
        $priceAdded = true;
    }

    echo json_encode([
        'success' => true,
        'competitor_id' => $competitorId,
        'is_new' => $isNew,
        'price_added' => $priceAdded,
        'message' => $isNew ? 'Nouveau concurrent ajoute' : 'Concurrent mis a jour'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
