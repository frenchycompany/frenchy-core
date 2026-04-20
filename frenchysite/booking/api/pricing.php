<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pricing_engine.php';

$input = json_decode(file_get_contents('php://input'), true);

$logementId = (int) ($input['logement_id'] ?? $_GET['logement_id'] ?? 0);
$periods = $input['periods'] ?? [];

if ($logementId <= 0 || empty($periods)) {
    http_response_code(400);
    echo json_encode(['error' => 'logement_id et periods[] requis']);
    exit;
}

foreach ($periods as &$p) {
    if (empty($p['checkin']) || empty($p['checkout'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Chaque periode doit avoir checkin et checkout']);
        exit;
    }
    $p['checkin'] = date('Y-m-d', strtotime($p['checkin']));
    $p['checkout'] = date('Y-m-d', strtotime($p['checkout']));

    if ($p['checkout'] <= $p['checkin']) {
        http_response_code(400);
        echo json_encode(['error' => 'checkout doit etre apres checkin']);
        exit;
    }
}
unset($p);

try {
    $pdo = getBookingPdo();
    $engine = new PricingEngine($pdo);
    $result = $engine->calculateMultiPeriods($logementId, $periods);

    $stmt = $pdo->prepare("SELECT nom_du_logement FROM liste_logements WHERE id = ?");
    $stmt->execute([$logementId]);
    $logement = $stmt->fetch();

    $result['logement'] = $logement ? $logement['nom_du_logement'] : 'Logement #' . $logementId;

    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
