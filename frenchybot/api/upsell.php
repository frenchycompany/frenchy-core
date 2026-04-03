<?php
/**
 * API Upsell — Cree une session Stripe Checkout pour un achat upsell
 * POST JSON : {token, upsell_id}
 * Retourne : {checkout_url} ou {message: error}
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../ionos/gestion/includes/env_loader.php';
require_once __DIR__ . '/../../ionos/gestion/db/connection.php';
require_once __DIR__ . '/../includes/hub-functions.php';
require_once __DIR__ . '/../includes/settings.php';

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';
$upsellId = (int)($input['upsell_id'] ?? 0);

if (!$token || !$upsellId) {
    echo json_encode(['message' => 'Parametres manquants']);
    exit;
}

// Verifier le token
$stmt = $pdo->prepare("
    SELECT ht.id AS hub_token_id, ht.reservation_id, ht.logement_id, ht.token,
           r.prenom, r.nom, r.email
    FROM hub_tokens ht
    JOIN reservation r ON ht.reservation_id = r.id
    WHERE ht.token = ? AND ht.active = 1
");
$stmt->execute([$token]);
$hub = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hub) {
    echo json_encode(['message' => 'Lien invalide ou expire']);
    exit;
}

// Charger l'upsell
$stmt = $pdo->prepare("SELECT * FROM upsells WHERE id = ? AND active = 1");
$stmt->execute([$upsellId]);
$upsell = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$upsell) {
    echo json_encode(['message' => 'Service non disponible']);
    exit;
}

// Verifier Stripe
$stripeKey = botSetting($pdo, 'stripe_secret_key');
if (!$stripeKey) {
    // Pas de Stripe → enregistrer la commande en pending et notifier
    try {
        $pdo->prepare("
            INSERT INTO upsell_orders (upsell_id, reservation_id, hub_token_id, amount, currency, status, customer_email)
            VALUES (?, ?, ?, ?, ?, 'pending', ?)
        ")->execute([$upsellId, $hub['reservation_id'], $hub['hub_token_id'], $upsell['price'], $upsell['currency'], $hub['email']]);
    } catch (\PDOException $e) { /* ignore */ }

    trackInteraction($pdo, $hub['hub_token_id'], $hub['reservation_id'], 'upsell_request', [
        'upsell_id' => $upsellId,
        'upsell_name' => $upsell['name'],
        'amount' => $upsell['price'],
    ]);

    echo json_encode(['message' => 'Demande enregistree ! Notre equipe vous contactera pour confirmer. (' . $upsell['label'] . ' — ' . number_format($upsell['price'], 0) . ' €)']);
    exit;
}

// Creer la session Stripe Checkout
$hubUrl = getHubUrl($hub['token'], $pdo);

$sessionData = [
    'payment_method_types' => ['card'],
    'line_items' => [[
        'price_data' => [
            'currency' => strtolower($upsell['currency']),
            'unit_amount' => (int)($upsell['price'] * 100),
            'product_data' => [
                'name' => $upsell['label'],
                'description' => $upsell['description'] ?? '',
            ],
        ],
        'quantity' => 1,
    ]],
    'mode' => 'payment',
    'success_url' => $hubUrl . '&upsell=success',
    'cancel_url' => $hubUrl . '&upsell=cancel',
    'customer_email' => $hub['email'] ?: null,
    'metadata' => [
        'upsell_id' => $upsellId,
        'reservation_id' => $hub['reservation_id'],
        'hub_token_id' => $hub['hub_token_id'],
    ],
];

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_USERPWD => $stripeKey . ':',
    CURLOPT_POSTFIELDS => http_build_query($sessionData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$session = json_decode($response, true);

if ($httpCode >= 200 && $httpCode < 300 && !empty($session['url'])) {
    // Enregistrer la commande
    try {
        $pdo->prepare("
            INSERT INTO upsell_orders (upsell_id, reservation_id, hub_token_id, amount, currency, status, stripe_session_id, customer_email)
            VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
        ")->execute([
            $upsellId, $hub['reservation_id'], $hub['hub_token_id'],
            $upsell['price'], $upsell['currency'],
            $session['id'], $hub['email']
        ]);
    } catch (\PDOException $e) { /* ignore */ }

    trackInteraction($pdo, $hub['hub_token_id'], $hub['reservation_id'], 'upsell_checkout', [
        'upsell_id' => $upsellId,
        'stripe_session_id' => $session['id'],
    ]);

    echo json_encode(['checkout_url' => $session['url']]);
} else {
    $error = $session['error']['message'] ?? 'Erreur Stripe';
    error_log('Stripe error: ' . $response);
    echo json_encode(['message' => 'Paiement temporairement indisponible.']);
}
