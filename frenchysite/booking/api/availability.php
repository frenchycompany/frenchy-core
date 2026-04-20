<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/db.php';

$logementId = (int) ($_GET['logement_id'] ?? 0);
$monthStart = $_GET['start'] ?? date('Y-m-01');
$monthEnd = $_GET['end'] ?? date('Y-m-t', strtotime($monthStart));

if ($logementId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'logement_id requis']);
    exit;
}

try {
    $pdo = getBookingPdo();

    $stmt = $pdo->prepare("
        SELECT date_arrivee AS checkin, date_depart AS checkout, statut, plateforme, prenom
        FROM reservation
        WHERE logement_id = ?
          AND statut = 'confirmée'
          AND date_arrivee <= ?
          AND date_depart >= ?
    ");
    $stmt->execute([$logementId, $monthEnd, $monthStart]);
    $reservations = $stmt->fetchAll();

    $stmt2 = $pdo->prepare("
        SELECT ir.start_date AS checkin, ir.end_date AS checkout, ir.status, ir.is_blocked
        FROM ical_reservations ir
        JOIN travel_account_connections tac ON ir.connection_id = tac.id
        WHERE ir.listing_id IN (
            SELECT tl.id FROM travel_listings tl WHERE tl.logement_id = ?
        )
          AND ir.status IN ('confirmed', 'blocked')
          AND ir.start_date <= ?
          AND ir.end_date >= ?
    ");
    $stmt2->execute([$logementId, $monthEnd, $monthStart]);
    $icalBlocked = $stmt2->fetchAll();

    $bookedDates = [];
    $allBookings = array_merge($reservations, $icalBlocked);

    foreach ($allBookings as $b) {
        $start = new DateTime($b['checkin']);
        $end = new DateTime($b['checkout']);
        while ($start < $end) {
            $bookedDates[$start->format('Y-m-d')] = true;
            $start->modify('+1 day');
        }
    }

    require_once __DIR__ . '/../includes/pricing_engine.php';
    $engine = new PricingEngine($pdo);
    $baseConfig = $engine->getBasePrice($logementId);

    $prices = [];
    $current = new DateTime($monthStart);
    $endDt = new DateTime($monthEnd);
    $endDt->modify('+1 day');

    while ($current < $endDt) {
        $d = $current->format('Y-m-d');
        if (!isset($bookedDates[$d])) {
            $nightInfo = $engine->calculateNightPrice($logementId, $d, $baseConfig);
            $prices[$d] = $nightInfo['final_price'];
        }
        $current->modify('+1 day');
    }

    echo json_encode([
        'logement_id' => $logementId,
        'start' => $monthStart,
        'end' => $monthEnd,
        'booked' => array_keys($bookedDates),
        'prices' => $prices,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
