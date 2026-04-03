<?php
/**
 * CRON : Envoi automatique des messages (SMS/WhatsApp) lies aux sejours
 * A executer toutes les heures : php /path/to/frenchybot/cron/auto-messages.php
 *
 * Logique :
 * - Lit auto_messages actifs
 * - Pour chaque reservation concernee, verifie si le message a deja ete envoye
 * - Detecte le canal (FR → SMS, etranger → WhatsApp)
 * - Envoie et logue dans auto_messages_log
 */

require_once __DIR__ . '/../../ionos/gestion/includes/env_loader.php';
require_once __DIR__ . '/../../ionos/gestion/db/connection.php';
require_once __DIR__ . '/../includes/hub-functions.php';
require_once __DIR__ . '/../includes/channels.php';
require_once __DIR__ . '/../includes/settings.php';

$now = new DateTime();
$today = $now->format('Y-m-d');
$currentHour = (int)$now->format('H');

echo "[" . $now->format('Y-m-d H:i:s') . "] CRON auto-messages demarre\n";

// Charger les messages actifs
$msgs = $pdo->query("SELECT * FROM auto_messages WHERE active = 1")->fetchAll(PDO::FETCH_ASSOC);
echo count($msgs) . " message(s) actif(s) configure(s)\n";

$sent = 0;
$skipped = 0;
$errors = 0;

foreach ($msgs as $msg) {
    // Trouver les reservations concernees selon le trigger_type
    $sql = "
        SELECT r.id, r.prenom, r.nom, r.telephone, r.email,
               r.date_arrivee, r.heure_arrivee, r.date_depart, r.heure_depart,
               r.logement_id,
               l.nom_du_logement
        FROM reservation r
        JOIN liste_logements l ON r.logement_id = l.id
        LEFT JOIN auto_messages_log aml ON aml.auto_message_id = ? AND aml.reservation_id = r.id
        WHERE r.statut = 'confirmée'
          AND r.telephone IS NOT NULL AND r.telephone != ''
          AND aml.id IS NULL
    ";
    $params = [$msg['id']];

    // Filtre logement si specifique
    if ($msg['logement_id']) {
        $sql .= " AND r.logement_id = ?";
        $params[] = $msg['logement_id'];
    }

    // Filtre selon trigger_type
    $offsetHours = (int)$msg['trigger_offset_hours'];
    switch ($msg['trigger_type']) {
        case 'before_checkin':
            // Ex: offset=-24 → envoyer 24h avant le checkin
            // On cherche les reservations dont date_arrivee = demain (si offset=-24 et on est a l'heure)
            $targetDate = (clone $now)->modify(abs($offsetHours) . ' hours')->format('Y-m-d');
            $sql .= " AND r.date_arrivee = ?";
            $params[] = $targetDate;
            break;

        case 'checkin_day':
            $sql .= " AND r.date_arrivee = ?";
            $params[] = $today;
            break;

        case 'during_stay':
            $sql .= " AND r.date_arrivee < ? AND r.date_depart > ?";
            $params[] = $today;
            $params[] = $today;
            break;

        case 'checkout_day':
            $sql .= " AND r.date_depart = ?";
            $params[] = $today;
            break;

        case 'after_checkout':
            $targetDate = (clone $now)->modify('-' . abs($offsetHours) . ' hours')->format('Y-m-d');
            $sql .= " AND r.date_depart = ?";
            $params[] = $targetDate;
            break;

        default:
            continue 2;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reservations as $resa) {
        // Generer le token HUB et l'URL
        try {
            $token = getOrCreateHubToken($pdo, $resa['id'], $resa['logement_id']);
            $hubUrl = getHubUrl($token, $pdo);
        } catch (\PDOException $e) {
            $hubUrl = '';
        }

        // Charger les equipements pour les variables
        $eqData = [];
        try {
            $eq = $pdo->prepare("SELECT heure_checkin, heure_checkout FROM logement_equipements WHERE logement_id = ?");
            $eq->execute([$resa['logement_id']]);
            $eqData = $eq->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) { /* ignore */ }

        $resa['heure_checkin'] = $eqData['heure_checkin'] ?? '16:00';
        $resa['heure_checkout'] = $eqData['heure_checkout'] ?? '10:00';

        // Personnaliser le message
        $text = personalizeHubMessage($msg['template'], $resa, $hubUrl);

        // Detecter le canal
        $channel = ($msg['channel'] === 'auto') ? detectChannel($resa['telephone']) : $msg['channel'];

        // Envoyer
        $result = sendMessage($pdo, $resa['telephone'], $text, $resa['id']);

        // Logger
        $status = $result['success'] ? 'sent' : 'failed';
        $errorMsg = $result['error'] ?? null;

        try {
            $pdo->prepare("
                INSERT INTO auto_messages_log (auto_message_id, reservation_id, channel, status, error_message)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$msg['id'], $resa['id'], $channel, $status, $errorMsg]);
        } catch (\PDOException $e) { /* doublon ignore */ }

        if ($result['success']) {
            $sent++;
            echo "  ✓ [{$channel}] {$resa['prenom']} {$resa['nom']} ({$resa['telephone']}) — {$msg['name']}\n";
        } else {
            $errors++;
            echo "  ✗ [{$channel}] {$resa['prenom']} — ERREUR: {$errorMsg}\n";
        }
    }
}

echo "\nTermine : $sent envoye(s), $skipped ignore(s), $errors erreur(s)\n";
