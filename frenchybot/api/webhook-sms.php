<?php
/**
 * Webhook SMS entrant — Auto-reponse intelligente
 *
 * Le daemon RPi insere les SMS recus dans sms_in.
 * Ce script est appele par CRON (toutes les 2 min) pour traiter les nouveaux SMS :
 * 1. Identifier la reservation associee au numero
 * 2. Si HUB existe → repondre avec le lien HUB
 * 3. Si question → repondre via IA (si configuree)
 * 4. Sinon → notifier l'admin
 *
 * CRON : */2 * * * * php /var/www/frenchy-core/frenchybot/api/webhook-sms.php
 *
 * Peut aussi etre appele en POST JSON par un webhook externe :
 * POST {sender: "+33612345678", message: "texte", modem: "modem1"}
 */

require_once __DIR__ . '/../../ionos/gestion/includes/env_loader.php';
require_once __DIR__ . '/../../ionos/gestion/db/connection.php';
require_once __DIR__ . '/../includes/hub-functions.php';
require_once __DIR__ . '/../includes/channels.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/openai.php';
require_once __DIR__ . '/../includes/api-security.php';

$isCli = php_sapi_name() === 'cli';
$isPost = !$isCli && $_SERVER['REQUEST_METHOD'] === 'POST';

// Mode webhook HTTP : valider le bearer token
if ($isPost) {
    header('Content-Type: application/json');

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
    $cronSecret = env('CRON_SECRET', '');
    if ($cronSecret && $authHeader !== "Bearer $cronSecret" && $authHeader !== $cronSecret) {
        http_response_code(401);
        echo json_encode(['error' => 'Non autorise']);
        exit;
    }

    // Traiter un SMS entrant specifique via webhook
    $input = json_decode(file_get_contents('php://input'), true);
    if (!empty($input['sender']) && !empty($input['message'])) {
        $result = processIncomingSms($pdo, $input['sender'], $input['message']);
        echo json_encode($result);
        exit;
    }
}

// Mode CRON : traiter tous les SMS non traites
if ($isCli) {
    echo "[" . date('Y-m-d H:i:s') . "] Webhook SMS — traitement des SMS entrants\n";
}

$processed = 0;
$errors = 0;

try {
    // Chercher les SMS entrants non traites (auto_replied = 0, ai_handled = 0)
    $stmt = $pdo->prepare("
        SELECT id, sender, message, received_at
        FROM sms_in
        WHERE auto_replied = 0
          AND ai_handled = 0
          AND received_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        ORDER BY received_at ASC
        LIMIT 20
    ");
    $stmt->execute();
    $smsEntrants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($isCli) echo count($smsEntrants) . " SMS a traiter\n";

    foreach ($smsEntrants as $sms) {
        try {
            $result = processIncomingSms($pdo, $sms['sender'], $sms['message'], $sms['id']);

            // Marquer comme traite
            $pdo->prepare("UPDATE sms_in SET ai_handled = 1, auto_replied = ? WHERE id = ?")
                ->execute([$result['replied'] ? 1 : 0, $sms['id']]);

            $processed++;
            if ($isCli) {
                echo "  " . ($result['replied'] ? 'R' : 'N') . " [{$sms['sender']}] " . mb_substr($sms['message'], 0, 50) . "\n";
            }
        } catch (\Exception $e) {
            $errors++;
            // Marquer comme traite pour eviter les boucles
            $pdo->prepare("UPDATE sms_in SET ai_handled = 1 WHERE id = ?")->execute([$sms['id']]);
            if ($isCli) echo "  E [{$sms['sender']}] " . $e->getMessage() . "\n";
            error_log("webhook-sms error: " . $e->getMessage());
        }
    }
} catch (\PDOException $e) {
    if ($isCli) echo "ERREUR DB: " . $e->getMessage() . "\n";
    error_log("webhook-sms DB error: " . $e->getMessage());
}

if ($isCli) echo "Termine : $processed traite(s), $errors erreur(s)\n";
if ($isPost && empty($input['sender'])) {
    echo json_encode(['processed' => $processed, 'errors' => $errors]);
}

// ============================================================
// LOGIQUE DE TRAITEMENT
// ============================================================

/**
 * Traite un SMS entrant : identifie la reservation, repond si possible
 * @return array{replied: bool, channel: string, response: string}
 */
function processIncomingSms(PDO $pdo, string $sender, string $message, ?int $smsInId = null): array
{
    $sender = normalizePhone($sender);
    $message = trim($message);

    // 1. Trouver la reservation active pour ce numero
    $resa = findReservationByPhone($pdo, $sender);

    if (!$resa) {
        // Pas de reservation connue → notifier l'admin
        notifyAdmin($pdo, $sender, $message, null);
        return ['replied' => false, 'channel' => 'sms', 'response' => 'no_reservation'];
    }

    // 2. Obtenir/creer le token HUB
    $token = getOrCreateHubToken($pdo, $resa['id'], $resa['logement_id']);
    $hubUrl = getHubUrl($token, $pdo);

    // 3. Tracker l'interaction
    try {
        $htStmt = $pdo->prepare("SELECT id FROM hub_tokens WHERE token = ?");
        $htStmt->execute([$token]);
        $htId = (int)$htStmt->fetchColumn();
        if ($htId) {
            trackInteraction($pdo, $htId, $resa['id'], 'sms_in', ['message' => mb_substr($message, 0, 500), 'sender' => $sender]);
        }
    } catch (\PDOException $e) { /* ignore */ }

    // 4. Analyser le message pour auto-reponse
    $response = generateSmsResponse($pdo, $resa, $message, $hubUrl);

    if ($response) {
        // Envoyer la reponse
        $sendResult = sendSms($pdo, $sender, $response, $resa['id']);

        if ($sendResult['success']) {
            return ['replied' => true, 'channel' => 'sms', 'response' => $response];
        }
    }

    // Fallback : notifier l'admin
    notifyAdmin($pdo, $sender, $message, $resa);
    return ['replied' => false, 'channel' => 'sms', 'response' => 'forwarded_to_admin'];
}

/**
 * Trouve la reservation active/recente pour un numero de telephone
 */
function findReservationByPhone(PDO $pdo, string $phone): ?array
{
    // Essayer le format exact et sans le +
    $phoneVariants = [$phone];
    if (str_starts_with($phone, '+33')) {
        $phoneVariants[] = '0' . substr($phone, 3);
        $phoneVariants[] = '0033' . substr($phone, 3);
    }

    $placeholders = implode(',', array_fill(0, count($phoneVariants), '?'));

    // D'abord la reservation active (en cours)
    $stmt = $pdo->prepare("
        SELECT r.id, r.prenom, r.nom, r.telephone, r.email,
               r.date_arrivee, r.heure_arrivee, r.date_depart, r.heure_depart,
               r.logement_id, l.nom_du_logement
        FROM reservation r
        JOIN liste_logements l ON r.logement_id = l.id
        WHERE r.telephone IN ($placeholders)
          AND r.statut = 'confirmée'
          AND r.date_arrivee <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)
          AND r.date_depart >= CURDATE()
        ORDER BY r.date_arrivee DESC
        LIMIT 1
    ");
    $stmt->execute($phoneVariants);
    $resa = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($resa) return $resa;

    // Sinon la prochaine reservation (J-3)
    $stmt = $pdo->prepare("
        SELECT r.id, r.prenom, r.nom, r.telephone, r.email,
               r.date_arrivee, r.heure_arrivee, r.date_depart, r.heure_depart,
               r.logement_id, l.nom_du_logement
        FROM reservation r
        JOIN liste_logements l ON r.logement_id = l.id
        WHERE r.telephone IN ($placeholders)
          AND r.statut = 'confirmée'
          AND r.date_arrivee BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
        ORDER BY r.date_arrivee ASC
        LIMIT 1
    ");
    $stmt->execute($phoneVariants);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Genere une reponse automatique au SMS
 * Utilise l'IA si configuree, sinon detection par mots-cles
 */
function generateSmsResponse(PDO $pdo, array $resa, string $message, string $hubUrl): ?string
{
    $msgLower = mb_strtolower($message);

    // Mots-cles de reponse rapide (sans IA)
    $quickReplies = [
        // Acces / codes
        ['keywords' => ['code', 'acces', 'entrer', 'porte', 'cle', 'clef', 'ouvrir', 'serrure'],
         'response' => "Bonjour {$resa['prenom']} ! Tous les codes et instructions d'acces sont sur votre HUB sejour : {$hubUrl} — Frenchy Conciergerie"],

        // Wifi
        ['keywords' => ['wifi', 'internet', 'connexion', 'reseau', 'mot de passe wifi'],
         'response' => "Bonjour {$resa['prenom']} ! Les identifiants wifi sont sur votre HUB : {$hubUrl} — Frenchy Conciergerie"],

        // Depart / checkout
        ['keywords' => ['depart', 'checkout', 'check-out', 'quitter', 'partir', 'heure depart'],
         'response' => "Bonjour {$resa['prenom']} ! Les instructions de depart sont sur votre HUB : {$hubUrl} — Frenchy Conciergerie"],

        // Arrivee / checkin
        ['keywords' => ['arrivee', 'checkin', 'check-in', 'heure arrivee', 'venir'],
         'response' => "Bonjour {$resa['prenom']} ! Toutes les infos d'arrivee sont sur votre HUB : {$hubUrl} — Frenchy Conciergerie"],

        // Merci / ok (pas de reponse necessaire)
        ['keywords' => ['merci', 'ok', 'parfait', 'super', 'genial', 'top', 'nickel', 'd\'accord', 'compris'],
         'response' => null],
    ];

    foreach ($quickReplies as $qr) {
        foreach ($qr['keywords'] as $kw) {
            if (str_contains($msgLower, $kw)) {
                return $qr['response']; // null = pas de reponse (merci, ok...)
            }
        }
    }

    // Essayer l'IA si configuree
    $apiKey = botSetting($pdo, 'openai_api_key');
    if ($apiKey) {
        return generateAiSmsResponse($pdo, $resa, $message, $hubUrl);
    }

    // Fallback generique : envoyer le lien HUB
    return "Bonjour {$resa['prenom']} ! Retrouvez toutes les infos de votre sejour ici : {$hubUrl} — Si besoin, notre equipe vous recontacte rapidement. Frenchy Conciergerie";
}

/**
 * Genere une reponse SMS via OpenAI
 */
function generateAiSmsResponse(PDO $pdo, array $resa, string $message, string $hubUrl): ?string
{
    // Charger les equipements
    $equip = [];
    try {
        $eq = $pdo->prepare("SELECT * FROM logement_equipements WHERE logement_id = ?");
        $eq->execute([$resa['logement_id']]);
        $equip = $eq->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (\PDOException $e) {}

    $botName = botSetting($pdo, 'bot_name', 'Frenchy');
    $adminPhone = botSetting($pdo, 'admin_phone', '');

    $systemPrompt = "Tu es $botName, assistant SMS de Frenchy Conciergerie. Tu reponds aux SMS des voyageurs.

REGLES :
- Reponds en 1-2 phrases MAX (SMS = court)
- Sois amical et professionnel
- Si tu as l'info, reponds directement
- Sinon, renvoie vers le HUB : $hubUrl
- Ne donne JAMAIS d'infos que tu n'as pas
- Si tu ne peux pas aider, donne le numero du gestionnaire" . ($adminPhone ? " : $adminPhone" : "") . "
- Pas d'emoji excessif, 1 max
- Signe toujours : Frenchy Conciergerie

CONTEXTE :
- Voyageur : {$resa['prenom']} {$resa['nom']}
- Logement : {$resa['nom_du_logement']}
- Sejour : {$resa['date_arrivee']} → {$resa['date_depart']}
- HUB : $hubUrl";

    if (!empty($equip['code_porte'])) $systemPrompt .= "\n- Code porte : {$equip['code_porte']}";
    if (!empty($equip['nom_wifi'])) $systemPrompt .= "\n- Wifi : {$equip['nom_wifi']} / {$equip['code_wifi']}";
    if (!empty($equip['heure_checkin'])) $systemPrompt .= "\n- Checkin : {$equip['heure_checkin']}";
    if (!empty($equip['heure_checkout'])) $systemPrompt .= "\n- Checkout : {$equip['heure_checkout']}";

    $result = callOpenAI($systemPrompt, [['role' => 'user', 'content' => $message]], $pdo);

    if ($result['success'] && !empty($result['message'])) {
        // Tronquer si trop long pour un SMS (160 chars max en ascii, 70 en unicode)
        $reply = $result['message'];
        if (mb_strlen($reply) > 300) {
            $reply = mb_substr($reply, 0, 290) . '...';
        }
        return $reply;
    }

    return null;
}

/**
 * Notifie l'admin d'un SMS non traite
 */
function notifyAdmin(PDO $pdo, string $sender, string $message, ?array $resa): void
{
    $adminPhone = botSetting($pdo, 'admin_phone');
    if (!$adminPhone) return;

    $notif = "SMS recu de $sender";
    if ($resa) {
        $notif .= " ({$resa['prenom']} {$resa['nom']})";
    }
    $notif .= "\n" . mb_substr($message, 0, 140);

    sendSms($pdo, $adminPhone, $notif);
}
