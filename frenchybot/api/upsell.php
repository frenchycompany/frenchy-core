<?php
/**
 * API Upsell — Cree une session Stripe Checkout pour un achat upsell
 * POST JSON : {token, upsell_id}
 * Retourne : {checkout_url} ou {message: error}
 *
 * Priorite :
 * 1. Stripe Checkout Session (si stripe_secret_key configuree)
 * 2. Stripe Payment Link (si stripe_link configuree sur l'upsell)
 * 3. Mode manuel (notification equipe)
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../ionos/gestion/includes/env_loader.php';
require_once __DIR__ . '/../../ionos/gestion/db/connection.php';
require_once __DIR__ . '/../includes/hub-functions.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/api-security.php';

// Securite
apiSecurityHeaders();
apiRateLimit('upsell', 10, 60); // 10 req/min par IP

$input = apiValidateJson([
    'token' => 'required|string|min:16|max:128',
    'upsell_id' => 'required|integer|min:1',
]);
$token = $input['token'];
$upsellId = $input['upsell_id'];

// Verifier le token
$hub = apiValidateHubToken($pdo, $token);

// Charger l'upsell
$stmt = $pdo->prepare("SELECT * FROM upsells WHERE id = ? AND active = 1");
$stmt->execute([$upsellId]);
$upsell = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$upsell) {
    echo json_encode(['message' => 'Service non disponible']);
    exit;
}

// Verifier doublon (meme upsell deja achete pour cette resa)
$stmtDup = $pdo->prepare("
    SELECT id FROM upsell_orders
    WHERE upsell_id = ? AND reservation_id = ? AND status IN ('paid','pending')
    LIMIT 1
");
$stmtDup->execute([$upsellId, $hub['reservation_id']]);
if ($stmtDup->fetchColumn()) {
    echo json_encode(['message' => 'Vous avez deja demande ce service pour ce sejour.']);
    exit;
}

// Enregistrer la commande
$orderId = null;
try {
    $pdo->prepare("
        INSERT INTO upsell_orders (upsell_id, reservation_id, hub_token_id, amount, currency, status, customer_email)
        VALUES (?, ?, ?, ?, ?, 'pending', ?)
    ")->execute([$upsellId, $hub['reservation_id'], $hub['hub_token_id'], $upsell['price'], $upsell['currency'] ?? 'EUR', $hub['email'] ?? null]);
    $orderId = (int)$pdo->lastInsertId();
} catch (\PDOException $e) {
    error_log('upsell order insert error: ' . $e->getMessage());
}

trackInteraction($pdo, $hub['hub_token_id'], $hub['reservation_id'], 'upsell_request', [
    'upsell_id' => $upsellId,
    'upsell_name' => $upsell['name'],
    'amount' => $upsell['price'],
    'order_id' => $orderId,
]);

// --- 1. Stripe Checkout Session (priorite) ---
$stripeSecretKey = botSetting($pdo, 'stripe_secret_key');
if ($stripeSecretKey && $orderId) {
    $checkoutResult = createStripeCheckoutSession($stripeSecretKey, $upsell, $hub, $orderId, $token, $pdo);
    if ($checkoutResult) {
        echo json_encode($checkoutResult);
        exit;
    }
    // Si erreur Stripe → fallback aux autres methodes
}

// --- 2. Stripe Payment Link (fallback) ---
$stripeLink = $upsell['stripe_link'] ?? '';
if ($stripeLink) {
    // Ajouter les metadata en query params si c'est un lien Stripe
    $separator = str_contains($stripeLink, '?') ? '&' : '?';
    $stripeLink .= $separator . 'client_reference_id=' . ($orderId ?? 'unknown');
    echo json_encode(['checkout_url' => $stripeLink]);
    exit;
}

// --- 3. Mode manuel ---
// Notifier l'admin
try {
    require_once __DIR__ . '/../includes/channels.php';
    $adminPhone = botSetting($pdo, 'admin_phone');
    if ($adminPhone) {
        $notif = "Demande upsell : {$upsell['label']} ({$upsell['price']} EUR)\n"
            . "Voyageur : {$hub['prenom']} {$hub['nom']}\n"
            . "Tel : " . ($hub['telephone'] ?? 'N/A');
        sendMessage($pdo, $adminPhone, $notif, $hub['reservation_id']);
    }
} catch (\Exception $e) { /* ignore */ }

echo json_encode([
    'message' => 'Demande enregistree ! Notre equipe vous contactera pour confirmer. ('
        . $upsell['label'] . ' — ' . number_format($upsell['price'], 0) . ' EUR)'
]);

// ============================================================
// STRIPE CHECKOUT SESSION
// ============================================================

/**
 * Cree une session Stripe Checkout et retourne l'URL
 * @return array|null {checkout_url: string} ou null si erreur
 */
function createStripeCheckoutSession(
    string $secretKey,
    array $upsell,
    array $hub,
    int $orderId,
    string $hubToken,
    PDO $pdo
): ?array {
    $appUrl = botSetting($pdo, 'app_url', 'https://gestion.frenchyconciergerie.fr');
    $hubUrl = rtrim($appUrl, '/') . '/frenchybot/hub/?id=' . urlencode($hubToken);

    $payload = [
        'mode' => 'payment',
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => strtolower($upsell['currency'] ?? 'eur'),
                'product_data' => [
                    'name' => $upsell['label'],
                    'description' => $upsell['description'] ?? '',
                ],
                'unit_amount' => (int)($upsell['price'] * 100), // Stripe = centimes
            ],
            'quantity' => 1,
        ]],
        'client_reference_id' => (string)$orderId,
        'customer_email' => $hub['email'] ?? null,
        'success_url' => $hubUrl . '&payment=success&order=' . $orderId,
        'cancel_url' => $hubUrl . '&payment=cancelled',
        'metadata' => [
            'order_id' => $orderId,
            'reservation_id' => $hub['reservation_id'],
            'upsell_id' => $upsell['id'],
            'upsell_name' => $upsell['name'],
            'voyageur' => ($hub['prenom'] ?? '') . ' ' . ($hub['nom'] ?? ''),
        ],
    ];

    // Retirer customer_email si vide
    if (empty($payload['customer_email'])) {
        unset($payload['customer_email']);
    }

    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_POSTFIELDS => http_build_query(flattenArray($payload)),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("Stripe cURL error: $curlError");
        return null;
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && !empty($decoded['url'])) {
        // Sauvegarder le stripe_session_id
        try {
            $pdo->prepare("UPDATE upsell_orders SET stripe_session_id = ? WHERE id = ?")
                ->execute([$decoded['id'], $orderId]);
        } catch (\PDOException $e) { /* ignore */ }

        return ['checkout_url' => $decoded['url']];
    }

    $error = $decoded['error']['message'] ?? "HTTP $httpCode";
    error_log("Stripe Checkout error: $error");
    return null;
}

/**
 * Aplatit un tableau PHP imbriqué en format compatible http_build_query pour Stripe
 * Convertit ['line_items' => [['price_data' => ...]]] en line_items[0][price_data][...]=...
 */
function flattenArray(array $array, string $prefix = ''): array
{
    $result = [];
    foreach ($array as $key => $value) {
        $newKey = $prefix ? "{$prefix}[{$key}]" : $key;
        if (is_array($value)) {
            $result = array_merge($result, flattenArray($value, $newKey));
        } else {
            $result[$newKey] = $value;
        }
    }
    return $result;
}
