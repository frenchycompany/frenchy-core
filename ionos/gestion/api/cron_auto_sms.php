<?php
/**
 * API endpoint pour l'automatisation SMS (appelé par cron RPi via curl)
 *
 * Authentification par token CRON_SECRET dans le header Authorization.
 * Paramètre GET ?type=checkout|checkin|preparation|midstay|custom|all
 *
 * Usage depuis le RPi :
 *   curl -s -H "Authorization: Bearer <CRON_SECRET>" \
 *        "https://gestion.frenchyconciergerie.fr/api/cron_auto_sms.php?type=checkout"
 */

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Paris');

// Charger l'environnement et la BDD
require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/template_helper.php';

// --- Auth par token ---
$cronSecret = env('CRON_SECRET', '');
if (empty($cronSecret)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'CRON_SECRET non configuré sur le serveur']);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$token = '';
if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
    $token = $m[1];
}

if (!hash_equals($cronSecret, $token)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token invalide']);
    exit;
}

// --- Paramètres ---
$runType = strtolower($_GET['type'] ?? 'all');
$validTypes = ['all', 'checkout', 'checkin', 'preparation', 'midstay', 'custom'];
if (!in_array($runType, $validTypes, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => "Type invalide: $runType"]);
    exit;
}

// --- Configuration ---
$config = [
    'enable_checkout' => true,
    'enable_checkin' => true,
    'enable_preparation' => true,
    'enable_midstay' => true,
    'preparation_days' => 4,
    'midstay_min_nights' => 3,
];

// Charger config depuis la BDD si dispo (table automation_config)
try {
    $stmtCfg = $pdo->query("SELECT config_key, config_value FROM automation_config");
    if ($stmtCfg) {
        foreach ($stmtCfg->fetchAll() as $row) {
            $key = $row['config_key'];
            $val = $row['config_value'];
            if (array_key_exists($key, $config)) {
                $config[$key] = is_numeric($val) ? (int)$val : filter_var($val, FILTER_VALIDATE_BOOLEAN);
            }
        }
    }
} catch (PDOException $e) {
    // Table peut ne pas exister, on continue avec les défauts
}

// --- Log ---
$logs = [];
function logMsg($message, $level = 'INFO') {
    global $logs;
    $logs[] = ['time' => date('H:i:s'), 'level' => $level, 'message' => $message];
}

// --- Envoi SMS automatique ---
function sendAutoSMS($pdo, $reservation, $type) {
    $resId = $reservation['id'];
    $telephone = $reservation['telephone'];
    $prenom = $reservation['prenom'];
    $nom = $reservation['nom'];
    $logement_id = $reservation['logement_id'];

    if (empty($telephone)) {
        logMsg("Réservation #$resId : Pas de numéro de téléphone", 'WARNING');
        return false;
    }

    $mapping = [
        'checkout'    => ['tpl' => 'checkout',     'flag' => 'dep_sent'],
        'checkin'     => ['tpl' => 'accueil',      'flag' => 'j1_sent'],
        'preparation' => ['tpl' => 'preparation',  'flag' => 'start_sent'],
        'midstay'     => ['tpl' => 'mi_parcours',  'flag' => 'mid_sent'],
    ];

    if (!isset($mapping[$type])) {
        logMsg("Type inconnu : $type", 'ERROR');
        return false;
    }

    $tplName = $mapping[$type]['tpl'];
    $flagField = $mapping[$type]['flag'];

    if ((int)($reservation[$flagField] ?? 0) === 1) {
        logMsg("Réservation #$resId : SMS '$tplName' déjà envoyé", 'INFO');
        return false;
    }

    $message = get_personalized_sms($pdo, $tplName, [
        'prenom' => $prenom,
        'nom' => $nom
    ], $logement_id);

    if ($message === null) {
        logMsg("Réservation #$resId : Template '$tplName' non trouvé", 'ERROR');
        return false;
    }

    try {
        $pdo->beginTransaction();

        // Insérer dans sms_outbox (le daemon RPi envoyer_sms.py le lira)
        $stmt = $pdo->prepare("
            INSERT INTO sms_outbox (receiver, message, modem, status)
            VALUES (:receiver, :message, 'modem1', 'pending')
        ");
        $stmt->execute([':receiver' => $telephone, ':message' => $message]);
        $smsId = $pdo->lastInsertId();

        // Marquer le flag
        $allowedFields = ['dep_sent', 'j1_sent', 'start_sent', 'mid_sent'];
        if (!in_array($flagField, $allowedFields, true)) {
            throw new Exception("Champ non autorisé : $flagField");
        }
        $stmt = $pdo->prepare("UPDATE reservation SET {$flagField} = 1 WHERE id = :id");
        $stmt->execute([':id' => $resId]);

        // Historique conversation
        $stmt = $pdo->prepare("
            INSERT INTO satisfaction_conversations (sender, role, message)
            VALUES (:sender, 'assistant', :message)
        ");
        $stmt->execute([':sender' => $telephone, ':message' => $message]);

        $pdo->commit();
        logMsg("Réservation #$resId ($prenom $nom) : SMS '$tplName' créé (ID=$smsId)", 'SUCCESS');
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        logMsg("Réservation #$resId : Erreur - " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// --- Processeurs par type ---
function processCheckouts($pdo) {
    global $config;
    if (!$config['enable_checkout']) { logMsg("Check-out désactivé"); return 0; }

    $today = date('Y-m-d');
    logMsg("=== Check-out du $today ===");

    $stmt = $pdo->prepare("
        SELECT id, prenom, nom, telephone, logement_id, dep_sent
        FROM reservation WHERE date_depart = :date AND statut = 'confirmée'
    ");
    $stmt->execute([':date' => $today]);
    $reservations = $stmt->fetchAll();
    logMsg("Trouvé " . count($reservations) . " check-out(s)");

    $sent = 0;
    foreach ($reservations as $res) {
        if (sendAutoSMS($pdo, $res, 'checkout')) $sent++;
    }
    logMsg("$sent SMS de check-out créé(s)", 'SUCCESS');
    return $sent;
}

function processCheckins($pdo) {
    global $config;
    if (!$config['enable_checkin']) { logMsg("Check-in désactivé"); return 0; }

    $today = date('Y-m-d');
    logMsg("=== Check-in du $today ===");

    $stmt = $pdo->prepare("
        SELECT id, prenom, nom, telephone, logement_id, j1_sent
        FROM reservation WHERE date_arrivee = :date AND statut = 'confirmée'
    ");
    $stmt->execute([':date' => $today]);
    $reservations = $stmt->fetchAll();
    logMsg("Trouvé " . count($reservations) . " check-in(s)");

    $sent = 0;
    foreach ($reservations as $res) {
        if (sendAutoSMS($pdo, $res, 'checkin')) $sent++;
    }
    logMsg("$sent SMS de check-in créé(s)", 'SUCCESS');
    return $sent;
}

function processPreparations($pdo) {
    global $config;
    if (!$config['enable_preparation']) { logMsg("Préparation désactivée"); return 0; }

    $days = $config['preparation_days'];
    $targetDate = date('Y-m-d', strtotime("+{$days} days"));
    logMsg("=== Préparations pour le $targetDate (J-$days) ===");

    $stmt = $pdo->prepare("
        SELECT id, prenom, nom, telephone, logement_id, start_sent
        FROM reservation WHERE date_arrivee = :date AND statut = 'confirmée'
    ");
    $stmt->execute([':date' => $targetDate]);
    $reservations = $stmt->fetchAll();
    logMsg("Trouvé " . count($reservations) . " arrivée(s) dans $days jours");

    $sent = 0;
    foreach ($reservations as $res) {
        if (sendAutoSMS($pdo, $res, 'preparation')) $sent++;
    }
    logMsg("$sent SMS de préparation créé(s)", 'SUCCESS');
    return $sent;
}

function processMidStay($pdo) {
    global $config;
    if (!$config['enable_midstay']) { logMsg("Mi-parcours désactivé"); return 0; }

    $minNights = $config['midstay_min_nights'];
    $today = date('Y-m-d');
    logMsg("=== Mi-parcours (séjours >= $minNights nuits) ===");

    $stmt = $pdo->prepare("
        SELECT id, prenom, nom, telephone, logement_id, mid_sent,
               date_arrivee, date_depart,
               DATEDIFF(date_depart, date_arrivee) as duree_sejour
        FROM reservation
        WHERE statut = 'confirmée'
          AND date_arrivee < :today AND date_depart > :today
          AND DATEDIFF(date_depart, date_arrivee) >= :min_nights
    ");
    $stmt->execute([':today' => $today, ':min_nights' => $minNights]);
    $reservations = $stmt->fetchAll();
    logMsg("Trouvé " . count($reservations) . " séjour(s) en cours");

    $sent = 0;
    foreach ($reservations as $res) {
        $duree = (int)$res['duree_sejour'];
        $jourMilieu = (int)floor($duree / 2);
        $dateMilieu = date('Y-m-d', strtotime($res['date_arrivee'] . " + $jourMilieu days"));
        if ($dateMilieu !== $today) continue;

        if (sendAutoSMS($pdo, $res, 'midstay')) $sent++;
    }
    logMsg("$sent SMS de mi-parcours créé(s)", 'SUCCESS');
    return $sent;
}

function processCustomAutomations($pdo) {
    logMsg("=== Automatisations personnalisées ===");

    try {
        $stmt = $pdo->query("SELECT * FROM sms_automations WHERE actif = 1 ORDER BY id");
        $automations = $stmt->fetchAll();
    } catch (PDOException $e) {
        logMsg("Table sms_automations non accessible: " . $e->getMessage(), 'WARNING');
        return 0;
    }

    if (count($automations) === 0) {
        logMsg("Aucune automatisation personnalisée active");
        return 0;
    }

    logMsg(count($automations) . " automatisation(s) active(s)");
    $totalSent = 0;

    foreach ($automations as $auto) {
        $autoId = $auto['id'];
        $autoNom = $auto['nom'];
        $declencheurType = $auto['declencheur_type'];
        $declencheurJours = (int)$auto['declencheur_jours'];
        $templateName = $auto['template_name'];
        $conditionStatut = $auto['condition_statut'];
        $flagField = $auto['flag_field'];
        $logementId = !empty($auto['logement_id']) ? (int)$auto['logement_id'] : null;

        logMsg("--- Automatisation: $autoNom (ID=$autoId) ---");

        // Calculer la date cible
        if ($declencheurJours == 0) {
            $targetDate = date('Y-m-d');
        } elseif ($declencheurJours < 0) {
            $targetDate = date('Y-m-d', strtotime(abs($declencheurJours) . ' days'));
        } else {
            $targetDate = date('Y-m-d', strtotime('-' . $declencheurJours . ' days'));
        }

        $dateColumn = match($declencheurType) {
            'date_arrivee' => 'date_arrivee',
            'date_depart' => 'date_depart',
            'date_reservation' => 'date_reservation',
            default => 'date_arrivee'
        };

        $sql = "SELECT id, prenom, nom, telephone, logement_id, {$flagField}
                FROM reservation WHERE {$dateColumn} = :target_date";
        $params = [':target_date' => $targetDate];

        if (!empty($conditionStatut)) {
            $sql .= " AND statut = :statut";
            $params[':statut'] = $conditionStatut;
        }
        if ($logementId !== null) {
            $sql .= " AND logement_id = :logement_id";
            $params[':logement_id'] = $logementId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reservations = $stmt->fetchAll();

        logMsg("Date cible: $targetDate, " . count($reservations) . " réservation(s)");

        $sent = 0;
        foreach ($reservations as $res) {
            if ((int)($res[$flagField] ?? 0) === 1) continue;
            if (empty($res['telephone'])) {
                logMsg("Réservation #" . $res['id'] . " : Pas de téléphone", 'WARNING');
                continue;
            }

            $message = get_personalized_sms($pdo, $templateName, [
                'prenom' => $res['prenom'],
                'nom' => $res['nom']
            ], $res['logement_id']);

            if ($message === null) {
                logMsg("Réservation #" . $res['id'] . " : Template '$templateName' non trouvé", 'ERROR');
                continue;
            }

            try {
                $pdo->beginTransaction();

                $stmtIns = $pdo->prepare("
                    INSERT INTO sms_outbox (receiver, message, modem, status)
                    VALUES (:receiver, :message, 'modem1', 'pending')
                ");
                $stmtIns->execute([':receiver' => $res['telephone'], ':message' => $message]);
                $smsId = $pdo->lastInsertId();

                $stmtUpd = $pdo->prepare("UPDATE reservation SET {$flagField} = 1 WHERE id = :id");
                $stmtUpd->execute([':id' => $res['id']]);

                $stmtHist = $pdo->prepare("
                    INSERT INTO satisfaction_conversations (sender, role, message)
                    VALUES (:sender, 'assistant', :message)
                ");
                $stmtHist->execute([':sender' => $res['telephone'], ':message' => $message]);

                $pdo->commit();
                logMsg("Réservation #" . $res['id'] . " : SMS '$templateName' créé (ID=$smsId)", 'SUCCESS');
                $sent++;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                logMsg("Réservation #" . $res['id'] . " : Erreur - " . $e->getMessage(), 'ERROR');
            }
        }

        logMsg("Automatisation '$autoNom' : $sent SMS créé(s)", 'SUCCESS');
        $totalSent += $sent;
    }

    return $totalSent;
}

// --- Exécution ---
logMsg("Démarrage automatisation SMS (type: $runType)");

$totalSent = 0;

if ($runType === 'all' || $runType === 'checkout')    $totalSent += processCheckouts($pdo);
if ($runType === 'all' || $runType === 'checkin')     $totalSent += processCheckins($pdo);
if ($runType === 'all' || $runType === 'preparation') $totalSent += processPreparations($pdo);
if ($runType === 'all' || $runType === 'midstay')     $totalSent += processMidStay($pdo);
if ($runType === 'all' || $runType === 'custom')      $totalSent += processCustomAutomations($pdo);

logMsg("Terminé. Total SMS créés: $totalSent", 'SUCCESS');

echo json_encode([
    'status' => 'success',
    'type' => $runType,
    'sms_created' => $totalSent,
    'logs' => $logs
]);
