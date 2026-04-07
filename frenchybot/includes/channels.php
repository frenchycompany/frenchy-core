<?php
/**
 * Abstraction des canaux de communication : SMS (RPi modem) + WhatsApp (Meta Cloud API)
 * Detection automatique : FR mobile → SMS, etranger → WhatsApp
 */

/**
 * Detecte le canal optimal pour un numero de telephone
 * @return 'sms'|'whatsapp'
 */
function detectChannel(string $phone): string
{
    $clean = preg_replace('/[\s\-\.\(\)]/', '', $phone);

    // Numeros francais mobiles → SMS via modem RPi
    if (preg_match('/^(?:\+33|0033|0)[67]\d{8}$/', $clean)) {
        return 'sms';
    }

    // Tout le reste → WhatsApp
    return 'whatsapp';
}

/**
 * Normalise un numero au format international
 */
function normalizePhone(string $phone): string
{
    $clean = preg_replace('/[\s\-\.\(\)]/', '', $phone);

    // FR : 06/07 → +33
    if (preg_match('/^0([67]\d{8})$/', $clean, $m)) {
        return '+33' . $m[1];
    }
    // Deja en +33
    if (preg_match('/^(?:0033)([67]\d{8})$/', $clean, $m)) {
        return '+33' . $m[1];
    }
    // Deja en format international
    if (str_starts_with($clean, '+')) {
        return $clean;
    }

    return '+' . $clean;
}

/**
 * Envoie un message via le canal adapte
 * SMS → insert dans sms_outbox (le daemon RPi envoie)
 * WhatsApp → appel Meta Cloud API
 *
 * @return array{success: bool, channel: string, error?: string}
 */
function sendMessage(PDO $pdo, string $phone, string $message, ?int $reservationId = null): array
{
    $channel = detectChannel($phone);
    $normalized = normalizePhone($phone);

    if ($channel === 'sms') {
        return sendSms($pdo, $normalized, $message, $reservationId);
    }

    return sendWhatsApp($normalized, $message, $reservationId, $pdo);
}

/**
 * SMS via sms_outbox (le daemon RPi lit et envoie)
 */
function sendSms(PDO $pdo, string $phone, string $message, ?int $reservationId = null): array
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sms_outbox (receiver, message, modem, status, reservation_id, created_at)
            VALUES (?, ?, 'modem1', 'pending', ?, NOW())
        ");
        $stmt->execute([$phone, $message, $reservationId]);
        return ['success' => true, 'channel' => 'sms'];
    } catch (\PDOException $e) {
        return ['success' => false, 'channel' => 'sms', 'error' => $e->getMessage()];
    }
}

/**
 * WhatsApp via Meta Cloud API
 * Config requise dans .env : WHATSAPP_TOKEN, WHATSAPP_PHONE_ID
 */
function sendWhatsApp(string $phone, string $message, ?int $reservationId = null, ?PDO $pdo = null): array
{
    $token = $pdo ? botSetting($pdo, 'whatsapp_token') : env('WHATSAPP_TOKEN', '');
    $phoneId = $pdo ? botSetting($pdo, 'whatsapp_phone_id') : env('WHATSAPP_PHONE_ID', '');

    if (!$token || !$phoneId) {
        return ['success' => false, 'channel' => 'whatsapp', 'error' => 'WhatsApp non configure (WHATSAPP_TOKEN / WHATSAPP_PHONE_ID manquants)'];
    }

    // Retirer le + pour l'API Meta
    $waPhone = ltrim($phone, '+');

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $waPhone,
        'type' => 'text',
        'text' => ['body' => $message],
    ];

    $ch = curl_init("https://graph.facebook.com/v21.0/$phoneId/messages");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'channel' => 'whatsapp', 'error' => 'cURL: ' . $curlError];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'channel' => 'whatsapp'];
    }

    $decoded = json_decode($response, true);
    $errorMsg = $decoded['error']['message'] ?? "HTTP $httpCode";
    return ['success' => false, 'channel' => 'whatsapp', 'error' => $errorMsg];
}

/**
 * Personnalise un template de message avec les variables de reservation
 */
function personalizeHubMessage(string $template, array $reservation, string $hubUrl = ''): string
{
    $replacements = [
        '{prenom}' => $reservation['prenom'] ?? '',
        '{nom}' => $reservation['nom'] ?? '',
        '{logement}' => $reservation['nom_du_logement'] ?? '',
        '{date_arrivee}' => isset($reservation['date_arrivee']) ? date('d/m/Y', strtotime($reservation['date_arrivee'])) : '',
        '{date_depart}' => isset($reservation['date_depart']) ? date('d/m/Y', strtotime($reservation['date_depart'])) : '',
        '{heure_checkin}' => $reservation['heure_checkin'] ?? $reservation['heure_arrivee'] ?? '16:00',
        '{heure_checkout}' => $reservation['heure_checkout'] ?? $reservation['heure_depart'] ?? '10:00',
        '{hub_url}' => $hubUrl,
        '{telephone}' => $reservation['telephone'] ?? '',
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $template);
}
