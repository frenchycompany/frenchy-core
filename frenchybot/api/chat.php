<?php
/**
 * API Chat — Endpoint pour le chatbot IA du HUB
 * POST /frenchybot/api/chat.php
 * Body: {token: string, message: string}
 * Response: {ok: bool, reply: string} ou {error: string}
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../ionos/gestion/includes/env_loader.php';
require_once __DIR__ . '/../../ionos/gestion/db/connection.php';
require_once __DIR__ . '/../includes/hub-functions.php';
require_once __DIR__ . '/../includes/openai.php';
require_once __DIR__ . '/../includes/channels.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/api-security.php';

// Securite
apiSecurityHeaders();
apiRateLimit('chat', 15, 60); // 15 messages/min par IP

$input = apiValidateJson([
    'token' => 'required|string|min:16|max:128',
    'message' => 'required|string|min:1|max:1000',
]);
$token = $input['token'];
$userMessage = $input['message'];

// Charger le HUB (sans incrementer le compteur — c'est deja fait a la page)
$hub = apiValidateHubToken($pdo, $token);

// Completer avec les champs necessaires au chat
$stmt = $pdo->prepare("
    SELECT r.date_arrivee, r.heure_arrivee, r.date_depart, r.heure_depart,
           r.nb_adultes, r.nb_enfants, r.nb_bebes, r.plateforme,
           l.nom_du_logement, l.adresse
    FROM reservation r
    JOIN liste_logements l ON r.logement_id = l.id
    WHERE r.id = ?
");
$stmt->execute([$hub['reservation_id']]);
$extra = $stmt->fetch(PDO::FETCH_ASSOC);
$hub = array_merge($hub, $extra ?: []);

// Charger les equipements
try {
    $eq = $pdo->prepare("SELECT * FROM logement_equipements WHERE logement_id = ?");
    $eq->execute([$hub['logement_id']]);
    $hub['equipements'] = $eq->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (\PDOException $e) {
    $hub['equipements'] = [];
}

// Tracker l'interaction
trackInteraction($pdo, $hub['hub_token_id'], $hub['reservation_id'], 'chat', ['message' => $userMessage]);

// Verifier si OpenAI est configure
$apiKey = botSetting($pdo, 'openai_api_key');
if (!$apiKey) {
    // Fallback : notification a l'equipe + message generique
    $adminPhone = botSetting($pdo, 'admin_phone');
    if ($adminPhone) {
        $notifMsg = "💬 Question HUB (pas d'IA)\nVoyageur : {$hub['prenom']} {$hub['nom']}\nMessage : " . mb_substr($userMessage, 0, 200);
        sendMessage($pdo, $adminPhone, $notifMsg, $hub['reservation_id']);
    }
    echo json_encode([
        'ok' => true,
        'reply' => "Merci pour votre message ! Notre equipe a ete notifiee et vous recontactera rapidement.",
        'debug_error' => 'no_api_key'
    ]);
    exit;
}

// Charger l'historique
$history = loadChatHistory($pdo, $hub['hub_token_id']);

// Sauvegarder le message utilisateur
saveChatMessage($pdo, $hub['hub_token_id'], $hub['reservation_id'], 'user', $userMessage);

// Construire les messages pour l'API
$messages = [];
foreach ($history as $msg) {
    $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
}
$messages[] = ['role' => 'user', 'content' => $userMessage];

// Appeler OpenAI
$systemPrompt = buildSystemPrompt($pdo, $hub);
$result = callOpenAI($systemPrompt, $messages, $pdo);

if ($result['success']) {
    $reply = $result['message'];

    // Sauvegarder la reponse
    saveChatMessage($pdo, $hub['hub_token_id'], $hub['reservation_id'], 'assistant', $reply);

    // Detecter si le bot ne sait pas repondre → notifier l'equipe
    $unsurePatterns = ['je ne sais pas', 'je ne connais pas', 'contacter l\'equipe', 'contacter notre equipe', 'je n\'ai pas cette information'];
    $shouldNotify = false;
    foreach ($unsurePatterns as $pattern) {
        if (stripos($reply, $pattern) !== false) {
            $shouldNotify = true;
            break;
        }
    }

    if ($shouldNotify) {
        $adminPhone = botSetting($pdo, 'admin_phone');
        if ($adminPhone) {
            $notifMsg = "🤖 FrenchyBot ne sait pas repondre\nVoyageur : {$hub['prenom']} {$hub['nom']}\nQuestion : " . mb_substr($userMessage, 0, 200);
            sendMessage($pdo, $adminPhone, $notifMsg, $hub['reservation_id']);
        }
    }

    echo json_encode(['ok' => true, 'reply' => $reply]);
} else {
    // Erreur API → fallback notification
    error_log('OpenAI error: ' . ($result['error'] ?? 'Unknown'));

    $adminPhone = botSetting($pdo, 'admin_phone');
    if ($adminPhone) {
        $notifMsg = "⚠ Erreur IA HUB\nVoyageur : {$hub['prenom']} {$hub['nom']}\nMessage : " . mb_substr($userMessage, 0, 200);
        sendMessage($pdo, $adminPhone, $notifMsg, $hub['reservation_id']);
    }

    echo json_encode([
        'ok' => false,
        'reply' => "Desole, une erreur technique est survenue. Notre equipe a ete notifiee.",
        'debug_error' => $result['error'] ?? 'Unknown'
    ]);
}
