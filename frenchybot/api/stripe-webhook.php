<?php
/**
 * Webhook Stripe — Confirmation automatique des paiements upsell
 *
 * Configure dans le dashboard Stripe :
 * URL : https://gestion.frenchyconciergerie.fr/frenchybot/api/stripe-webhook.php
 * Events : checkout.session.completed
 *
 * Le webhook verifie la signature Stripe pour securiser les appels.
 */

require_once __DIR__ . '/../../ionos/gestion/includes/env_loader.php';
require_once __DIR__ . '/../../ionos/gestion/db/connection.php';
require_once __DIR__ . '/../includes/settings.php';

header('Content-Type: application/json');

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$webhookSecret = botSetting($pdo, 'stripe_webhook_secret');

// Verifier la signature si le secret est configure
if ($webhookSecret) {
    $event = verifyStripeSignature($payload, $sigHeader, $webhookSecret);
    if (!$event) {
        http_response_code(400);
        echo json_encode(['error' => 'Signature invalide']);
        exit;
    }
} else {
    // Pas de secret configure → parser quand meme mais logguer un warning
    $event = json_decode($payload, true);
    if (!$event) {
        http_response_code(400);
        echo json_encode(['error' => 'Payload invalide']);
        exit;
    }
    error_log('stripe-webhook: ATTENTION — webhook_secret non configure, signature non verifiee');
}

// Traiter uniquement checkout.session.completed
$type = $event['type'] ?? '';
if ($type !== 'checkout.session.completed') {
    echo json_encode(['received' => true, 'type' => $type, 'action' => 'ignored']);
    exit;
}

$session = $event['data']['object'] ?? [];
$orderId = (int)($session['client_reference_id'] ?? $session['metadata']['order_id'] ?? 0);
$paymentIntent = $session['payment_intent'] ?? null;
$customerEmail = $session['customer_email'] ?? $session['customer_details']['email'] ?? null;
$amountTotal = isset($session['amount_total']) ? $session['amount_total'] / 100 : null;

if (!$orderId) {
    error_log('stripe-webhook: order_id introuvable dans la session ' . ($session['id'] ?? '?'));
    echo json_encode(['received' => true, 'error' => 'no_order_id']);
    exit;
}

// Mettre a jour la commande
try {
    $stmt = $pdo->prepare("
        UPDATE upsell_orders
        SET status = 'paid',
            stripe_session_id = ?,
            stripe_payment_intent = ?,
            customer_email = COALESCE(?, customer_email),
            paid_at = NOW()
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([
        $session['id'] ?? null,
        $paymentIntent,
        $customerEmail,
        $orderId,
    ]);

    $updated = $stmt->rowCount();

    if ($updated > 0) {
        // Charger les details de la commande pour notification
        $order = $pdo->prepare("
            SELECT uo.*, u.label AS upsell_label, u.name AS upsell_name,
                   r.prenom, r.nom, r.telephone,
                   l.nom_du_logement
            FROM upsell_orders uo
            JOIN upsells u ON uo.upsell_id = u.id
            JOIN reservation r ON uo.reservation_id = r.id
            JOIN hub_tokens ht ON uo.hub_token_id = ht.id
            JOIN liste_logements l ON ht.logement_id = l.id
            WHERE uo.id = ?
        ")->fetch(PDO::FETCH_ASSOC) ?: null;

        // Pas de fetch sans execute — corrigeons
        $stmtOrder = $pdo->prepare("
            SELECT uo.*, u.label AS upsell_label, u.name AS upsell_name,
                   r.prenom, r.nom, r.telephone,
                   l.nom_du_logement
            FROM upsell_orders uo
            JOIN upsells u ON uo.upsell_id = u.id
            JOIN reservation r ON uo.reservation_id = r.id
            JOIN hub_tokens ht ON uo.hub_token_id = ht.id
            JOIN liste_logements l ON ht.logement_id = l.id
            WHERE uo.id = ?
        ");
        $stmtOrder->execute([$orderId]);
        $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

        // Notifier l'admin par SMS
        if ($order) {
            $adminPhone = botSetting($pdo, 'admin_phone');
            if ($adminPhone) {
                require_once __DIR__ . '/../includes/channels.php';
                $notif = "Paiement upsell recu !\n"
                    . "{$order['upsell_label']} — " . number_format($order['amount'], 2) . " EUR\n"
                    . "Voyageur : {$order['prenom']} {$order['nom']}\n"
                    . "Logement : {$order['nom_du_logement']}";
                sendMessage($pdo, $adminPhone, $notif, $order['reservation_id']);
            }
        }

        // Tracker l'interaction
        if ($order) {
            try {
                require_once __DIR__ . '/../includes/hub-functions.php';
                trackInteraction($pdo, $order['hub_token_id'], $order['reservation_id'], 'upsell_paid', [
                    'order_id' => $orderId,
                    'upsell_name' => $order['upsell_name'],
                    'amount' => $order['amount'],
                ]);
            } catch (\Exception $e) { /* ignore */ }
        }

        error_log("stripe-webhook: commande #$orderId marquee comme payee");
    } else {
        error_log("stripe-webhook: commande #$orderId non trouvee ou deja payee");
    }
} catch (\PDOException $e) {
    error_log('stripe-webhook DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
    exit;
}

echo json_encode(['received' => true, 'order_id' => $orderId, 'status' => 'paid']);

// ============================================================
// VERIFICATION SIGNATURE STRIPE
// ============================================================

/**
 * Verifie la signature d'un webhook Stripe (Stripe-Signature header)
 * @return array|null L'event decode ou null si invalide
 */
function verifyStripeSignature(string $payload, string $sigHeader, string $secret): ?array
{
    if (empty($sigHeader)) return null;

    // Parser le header : t=timestamp,v1=signature
    $parts = [];
    foreach (explode(',', $sigHeader) as $part) {
        $kv = explode('=', $part, 2);
        if (count($kv) === 2) {
            $parts[$kv[0]] = $kv[1];
        }
    }

    $timestamp = $parts['t'] ?? '';
    $signature = $parts['v1'] ?? '';

    if (!$timestamp || !$signature) return null;

    // Verifier que le timestamp n'est pas trop ancien (5 min)
    if (abs(time() - (int)$timestamp) > 300) {
        error_log('stripe-webhook: timestamp trop ancien');
        return null;
    }

    // Calculer la signature attendue
    $signedPayload = $timestamp . '.' . $payload;
    $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

    if (!hash_equals($expectedSignature, $signature)) {
        error_log('stripe-webhook: signature invalide');
        return null;
    }

    return json_decode($payload, true);
}
