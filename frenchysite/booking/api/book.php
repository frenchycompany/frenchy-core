<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pricing_engine.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST uniquement']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$required = ['logement_id', 'periods', 'prenom', 'nom', 'email', 'telephone', 'payment_method'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Champ '$field' requis"]);
        exit;
    }
}

$logementId = (int) $input['logement_id'];
$periods = $input['periods'];
$paymentMethod = $input['payment_method'];
$isPro = !empty($input['is_pro']);

$guest = [
    'prenom' => htmlspecialchars(trim($input['prenom']), ENT_QUOTES, 'UTF-8'),
    'nom' => htmlspecialchars(trim($input['nom']), ENT_QUOTES, 'UTF-8'),
    'email' => filter_var($input['email'], FILTER_VALIDATE_EMAIL),
    'telephone' => preg_replace('/[^\d+]/', '', $input['telephone']),
    'nb_adultes' => (int) ($input['nb_adultes'] ?? 1),
    'nb_enfants' => (int) ($input['nb_enfants'] ?? 0),
];

if (!$guest['email']) {
    http_response_code(400);
    echo json_encode(['error' => 'Email invalide']);
    exit;
}

$proInfo = null;
if ($isPro) {
    $proFields = ['raison_sociale', 'siret', 'adresse_facturation'];
    foreach ($proFields as $f) {
        if (empty($input[$f])) {
            http_response_code(400);
            echo json_encode(['error' => "Champ pro '$f' requis"]);
            exit;
        }
    }
    $proInfo = [
        'raison_sociale' => htmlspecialchars(trim($input['raison_sociale']), ENT_QUOTES, 'UTF-8'),
        'siret' => preg_replace('/\s/', '', $input['siret']),
        'adresse_facturation' => htmlspecialchars(trim($input['adresse_facturation']), ENT_QUOTES, 'UTF-8'),
        'tva_intracommunautaire' => htmlspecialchars(trim($input['tva_intracommunautaire'] ?? ''), ENT_QUOTES, 'UTF-8'),
    ];
}

try {
    $pdo = getBookingPdo();
    $engine = new PricingEngine($pdo);
    $pricing = $engine->calculateMultiPeriods($logementId, $periods);

    $pdo->beginTransaction();

    $bookingRef = 'FC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

    $stmtInsert = $pdo->prepare("
        INSERT INTO reservation (
            reference, logement_id, date_arrivee, heure_arrivee, date_depart, heure_depart,
            statut, plateforme, prenom, nom, telephone, email,
            nb_adultes, nb_enfants, date_reservation
        ) VALUES (?, ?, ?, '15:00', ?, '11:00', 'confirmée', 'direct_pro', ?, ?, ?, ?, ?, ?, CURDATE())
    ");

    $reservationIds = [];
    foreach ($periods as $p) {
        $ref = $bookingRef . '-' . (count($reservationIds) + 1);
        $stmtInsert->execute([
            $ref, $logementId, $p['checkin'], $p['checkout'],
            $guest['prenom'], $guest['nom'], $guest['telephone'], $guest['email'],
            $guest['nb_adultes'], $guest['nb_enfants'],
        ]);
        $reservationIds[] = $pdo->lastInsertId();
    }

    $stmtBooking = $pdo->prepare("
        INSERT INTO direct_bookings (
            booking_ref, logement_id, reservation_ids, guest_email, guest_name,
            is_pro, pro_info, periods_json, pricing_json,
            total_amount, payment_method, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmtBooking->execute([
        $bookingRef, $logementId, json_encode($reservationIds),
        $guest['email'], $guest['prenom'] . ' ' . $guest['nom'],
        $isPro ? 1 : 0, $proInfo ? json_encode($proInfo) : null,
        json_encode($periods), json_encode($pricing),
        $pricing['total'], $paymentMethod,
    ]);
    $directBookingId = $pdo->lastInsertId();

    $pdo->commit();

    $response = [
        'success' => true,
        'booking_ref' => $bookingRef,
        'booking_id' => $directBookingId,
        'total' => $pricing['total'],
        'total_nights' => $pricing['total_nights'],
        'nb_periods' => count($periods),
    ];

    if ($paymentMethod === 'stripe') {
        $response['payment_url'] = createStripeSession($pdo, $bookingRef, $pricing, $guest, $logementId);
    } elseif ($paymentMethod === 'virement') {
        $response['virement_info'] = getVirementInfo($pdo, $bookingRef, $pricing['total']);
    }

    echo json_encode($response);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la reservation']);
}

function createStripeSession(PDO $pdo, string $ref, array $pricing, array $guest, int $logementId): string {
    $stmt = $pdo->prepare("SELECT setting_value FROM bot_settings WHERE setting_key = 'stripe_secret_key'");
    $stmt->execute();
    $row = $stmt->fetch();
    $stripeKey = $row ? $row['setting_value'] : '';

    if (!$stripeKey) return '';

    $stmt = $pdo->prepare("SELECT nom_du_logement FROM liste_logements WHERE id = ?");
    $stmt->execute([$logementId]);
    $logement = $stmt->fetch();
    $name = $logement ? $logement['nom_du_logement'] : 'Sejour';

    $lineItems = [];
    foreach ($pricing['periods'] as $p) {
        $lineItems[] = [
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => (int) round($p['total'] * 100),
                'product_data' => [
                    'name' => $name . ' - ' . $p['checkin'] . ' au ' . $p['checkout'],
                    'description' => $p['nb_nights'] . ' nuit(s)',
                ],
            ],
            'quantity' => 1,
        ];
    }

    if ($pricing['long_stay_discount_amount'] > 0) {
        $lineItems[] = [
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => -(int) round($pricing['long_stay_discount_amount'] * 100),
                'product_data' => [
                    'name' => 'Remise sejour long (-' . $pricing['long_stay_discount_percent'] . '%)',
                ],
            ],
            'quantity' => 1,
        ];
    }

    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => $stripeKey . ':',
        CURLOPT_POSTFIELDS => http_build_query([
            'mode' => 'payment',
            'success_url' => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/frenchysite/booking/?success=1&ref=' . $ref,
            'cancel_url' => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/frenchysite/booking/?cancelled=1&ref=' . $ref,
            'customer_email' => $guest['email'],
            'line_items' => $lineItems,
            'metadata' => ['booking_ref' => $ref],
        ]),
    ]);

    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);

    return $resp['url'] ?? '';
}

function getVirementInfo(PDO $pdo, string $ref, float $amount): array {
    return [
        'beneficiaire' => 'FrenchyConciergerie',
        'iban' => 'FR76 XXXX XXXX XXXX XXXX XXXX XXX',
        'bic' => 'XXXXXXXX',
        'reference' => $ref,
        'montant' => $amount,
        'devise' => 'EUR',
        'instruction' => "Virement a effectuer sous 48h avec la reference $ref en objet.",
    ];
}
