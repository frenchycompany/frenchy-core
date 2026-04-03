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

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';
$userMessage = trim($input['message'] ?? '');

if (!$token || !$userMessage) {
    echo json_encode(['error' => 'Parametres manquants']);
    exit;
}

// Limiter la taille du message
if (mb_strlen($userMessage) > 1000) {
    $userMessage = mb_substr($userMessage, 0, 1000);
}

// Charger le HUB (sans incrementer le compteur — c'est deja fait a la page)
$stmt = $pdo->prepare("
    SELECT ht.id AS hub_token_id, ht.reservation_id, ht.logement_id,
           r.prenom, r.nom, r.telephone, r.email,
           r.date_arrivee, r.heure_arrivee, r.date_depart, r.heure_depart,
           r.plateforme,
           l.nom_du_logement, l.adresse
    FROM hub_tokens ht
    JOIN reservation r ON ht.reservation_id = r.id
    JOIN liste_logements l ON ht.logement_id = l.id
    WHERE ht.token = ? AND ht.active = 1
");
$stmt->execute([$token]);
$hub = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hub) {
    echo json_encode(['error' => 'Token invalide']);
    exit;
}

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
$apiKey = env('OPENAI_API_KEY', '');
if (!$apiKey) {
    // Fallback : notification a l'equipe + message generique
    $adminPhone = env('ADMIN_PHONE', '');
    if ($adminPhone) {
        $notifMsg = "💬 Question HUB (pas d'IA)\nVoyageur : {$hub['prenom']} {$hub['nom']}\nMessage : " . mb_substr($userMessage, 0, 200);
        sendMessage($pdo, $adminPhone, $notifMsg, $hub['reservation_id']);
    }
    echo json_encode([
        'ok' => true,
        'reply' => "Merci pour votre message ! Notre equipe a ete notifiee et vous recontactera rapidement."
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
$result = callOpenAI($systemPrompt, $messages);

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
        $adminPhone = env('ADMIN_PHONE', '');
        if ($adminPhone) {
            $notifMsg = "🤖 FrenchyBot ne sait pas repondre\nVoyageur : {$hub['prenom']} {$hub['nom']}\nQuestion : " . mb_substr($userMessage, 0, 200);
            sendMessage($pdo, $adminPhone, $notifMsg, $hub['reservation_id']);
        }
    }

    echo json_encode(['ok' => true, 'reply' => $reply]);
} else {
    // Erreur API → fallback notification
    error_log('OpenAI error: ' . ($result['error'] ?? 'Unknown'));

    $adminPhone = env('ADMIN_PHONE', '');
    if ($adminPhone) {
        $notifMsg = "⚠ Erreur IA HUB\nVoyageur : {$hub['prenom']} {$hub['nom']}\nMessage : " . mb_substr($userMessage, 0, 200);
        sendMessage($pdo, $adminPhone, $notifMsg, $hub['reservation_id']);
    }

    echo json_encode([
        'ok' => true,
        'reply' => "Merci pour votre message ! Notre equipe a ete notifiee et vous recontactera rapidement."
    ]);
}
