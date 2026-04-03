<?php
/**
 * Traitement des actions transactionnelles du HUB
 * Recoit les clics sur les boutons d'action et repond en JSON
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../ionos/gestion/includes/env_loader.php';
require_once __DIR__ . '/../../ionos/gestion/db/connection.php';
require_once __DIR__ . '/../includes/hub-functions.php';
require_once __DIR__ . '/../includes/channels.php';
require_once __DIR__ . '/../includes/settings.php';

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';
$action = $input['action'] ?? '';

if (!$token || !$action) {
    echo json_encode(['error' => 'Parametres manquants']);
    exit;
}

// Charger le HUB sans incrementer le compteur de vues
$stmt = $pdo->prepare("
    SELECT ht.id AS hub_token_id, ht.reservation_id, ht.logement_id,
           r.prenom, r.nom, r.telephone
    FROM hub_tokens ht
    JOIN reservation r ON ht.reservation_id = r.id
    WHERE ht.token = ? AND ht.active = 1
");
$stmt->execute([$token]);
$hub = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hub) {
    echo json_encode(['error' => 'Token invalide']);
    exit;
}

// Tracker l'interaction
trackInteraction($pdo, $hub['hub_token_id'], $hub['reservation_id'], $action);

// Actions de copie (wifi, code porte) — juste du tracking
if (str_ends_with($action, '_copy')) {
    echo json_encode(['ok' => true]);
    exit;
}

// Actions rapides
$quickActions = getQuickActions();
$matched = null;
foreach ($quickActions as $qa) {
    if ($qa['id'] === $action) {
        $matched = $qa;
        break;
    }
}

$response = ['ok' => true];

if ($matched) {
    // Action "infos depart" → renvoyer les instructions
    if ($action === 'checkout_info') {
        $eq = $pdo->prepare("SELECT instructions_depart, heure_checkout FROM logement_equipements WHERE logement_id = ?");
        $eq->execute([$hub['logement_id']]);
        $equip = $eq->fetch(PDO::FETCH_ASSOC);
        $response['show_departure_info'] = true;
        $depInfo = '<strong>Depart prevu avant ' . htmlspecialchars($equip['heure_checkout'] ?? '10:00') . '</strong>';
        if (!empty($equip['instructions_depart'])) {
            $depInfo .= '<br><br>' . nl2br(htmlspecialchars($equip['instructions_depart']));
        }
        $response['departure_info'] = $depInfo;
    } else {
        $response['response'] = $matched['response'];
    }

    // Notifier l'equipe si necessaire (SMS/WhatsApp a l'admin)
    if ($matched['notify']) {
        $adminPhone = botSetting($pdo, 'admin_phone');
        if ($adminPhone) {
            $notifMsg = "⚠ HUB Sejour — " . $matched['label'] . "\n"
                . "Voyageur : " . $hub['prenom'] . " " . ($hub['nom'] ?? '') . "\n"
                . "Tel : " . ($hub['telephone'] ?? 'N/A');
            sendMessage($pdo, $adminPhone, $notifMsg, $hub['reservation_id']);
        }
    }
} else {
    $response['response'] = 'Votre demande a ete transmise a notre equipe.';
}

echo json_encode($response);
