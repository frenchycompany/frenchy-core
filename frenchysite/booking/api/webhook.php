<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/invoice_generator.php';
require_once __DIR__ . '/../includes/email.php';

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $pdo = getBookingPdo();

    $stmt = $pdo->prepare("SELECT setting_value FROM bot_settings WHERE setting_key = ?");

    $stmt->execute(['stripe_webhook_secret']);
    $row = $stmt->fetch();
    $webhookSecret = $row ? $row['setting_value'] : '';

    if ($webhookSecret && $sigHeader) {
        $parts = [];
        foreach (explode(',', $sigHeader) as $part) {
            [$key, $value] = explode('=', $part, 2);
            $parts[$key] = $value;
        }

        $signedPayload = ($parts['t'] ?? '') . '.' . $payload;
        $expected = hash_hmac('sha256', $signedPayload, $webhookSecret);

        if (!hash_equals($expected, $parts['v1'] ?? '')) {
            http_response_code(400);
            exit('Signature invalide');
        }
    }

    $event = json_decode($payload, true);

    if (!$event || !isset($event['type'])) {
        http_response_code(400);
        exit('Payload invalide');
    }

    if ($event['type'] === 'checkout.session.completed') {
        $session = $event['data']['object'];
        $bookingRef = $session['metadata']['booking_ref'] ?? '';

        if (!$bookingRef) {
            http_response_code(200);
            exit('OK (pas de booking_ref)');
        }

        $stmt = $pdo->prepare("SELECT * FROM direct_bookings WHERE booking_ref = ?");
        $stmt->execute([$bookingRef]);
        $booking = $stmt->fetch();

        if (!$booking) {
            http_response_code(200);
            exit('OK (booking introuvable)');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE direct_bookings
            SET status = 'paid',
                stripe_session_id = ?,
                stripe_payment_intent = ?,
                paid_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $session['id'],
            $session['payment_intent'] ?? null,
            $booking['id'],
        ]);

        $reservationIds = json_decode($booking['reservation_ids'], true) ?: [];
        if ($reservationIds) {
            $placeholders = implode(',', array_fill(0, count($reservationIds), '?'));
            $stmt = $pdo->prepare("UPDATE reservation SET statut = 'confirmée' WHERE id IN ($placeholders)");
            $stmt->execute($reservationIds);
        }

        $pdo->commit();

        $booking['status'] = 'paid';
        $booking['paid_at'] = date('Y-m-d H:i:s');
        $booking['stripe_session_id'] = $session['id'];

        $generator = new InvoiceGenerator($pdo);
        $invoicePath = $generator->generate($booking);

        $mailer = new BookingMailer($pdo);
        $mailer->sendConfirmation($booking, $invoicePath);
        $mailer->notifyAdmin($booking);

        http_response_code(200);
        echo 'OK';
    } elseif ($event['type'] === 'checkout.session.expired') {
        $session = $event['data']['object'];
        $bookingRef = $session['metadata']['booking_ref'] ?? '';

        if ($bookingRef) {
            $stmt = $pdo->prepare("UPDATE direct_bookings SET status = 'cancelled' WHERE booking_ref = ? AND status = 'pending'");
            $stmt->execute([$bookingRef]);

            $stmt = $pdo->prepare("SELECT reservation_ids FROM direct_bookings WHERE booking_ref = ?");
            $stmt->execute([$bookingRef]);
            $booking = $stmt->fetch();

            if ($booking) {
                $reservationIds = json_decode($booking['reservation_ids'], true) ?: [];
                if ($reservationIds) {
                    $placeholders = implode(',', array_fill(0, count($reservationIds), '?'));
                    $pdo->prepare("UPDATE reservation SET statut = 'annulée' WHERE id IN ($placeholders)")->execute($reservationIds);
                }
            }
        }

        http_response_code(200);
        echo 'OK (session expired)';
    } else {
        http_response_code(200);
        echo 'OK (event ignored)';
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo 'Erreur serveur';
}
