#!/usr/bin/env php
<?php
/**
 * Script d'automatisation d'envoi de SMS pour les réservations
 *
 * Ce script est appelé par cron et génère automatiquement les SMS pour :
 * - Check-out du jour (SMS de départ)
 * - Check-in du jour (SMS d'accueil)
 * - Arrivées dans 4 jours (SMS de préparation)
 * - Mi-parcours (SMS pendant le séjour)
 *
 * Usage:
 *   php auto_send_sms.php                  # Tout traiter
 *   php auto_send_sms.php --type=checkout  # Seulement les check-out
 *   php auto_send_sms.php --type=checkin   # Seulement les check-in
 *   php auto_send_sms.php --type=preparation # Seulement les préparations (J-4)
 *   php auto_send_sms.php --type=midstay   # Seulement les mi-parcours
 *   php auto_send_sms.php --type=custom    # Seulement les automatisations personnalisées
 *
 * Crons recommandés:
 *   0 9 * * *  php auto_send_sms.php --type=checkout      # Check-out à 9h
 *   0 20 * * * php auto_send_sms.php --type=checkin       # Check-in à 20h
 *   0 10 * * * php auto_send_sms.php --type=preparation   # Préparation à 10h
 *   0 12 * * * php auto_send_sms.php --type=midstay       # Mi-parcours à 12h
 *   0 11 * * * php auto_send_sms.php --type=custom        # Custom à 11h
 */

// Parse command line arguments
$options = getopt('', ['type::', 'help']);

if (isset($options['help'])) {
    echo "Usage: php auto_send_sms.php [--type=TYPE]\n";
    echo "Types disponibles: checkout, checkin, preparation, midstay, custom, all\n";
    echo "Sans argument, tous les types sont traités.\n";
    exit(0);
}

$runType = isset($options['type']) ? strtolower($options['type']) : 'all';
$validTypes = ['all', 'checkout', 'checkin', 'preparation', 'midstay', 'custom'];
if (!in_array($runType, $validTypes)) {
    echo "Type invalide: $runType\n";
    echo "Types valides: " . implode(', ', $validTypes) . "\n";
    exit(1);
}

// Configuration
define('LOG_FILE', __DIR__ . '/../logs/auto_send_sms.log');

// Charger la configuration depuis le fichier généré par l'interface web
$config_file = __DIR__ . '/auto_send_sms_config.php';
$config = [
    'enable_checkout' => true,
    'enable_checkin' => true,
    'enable_preparation' => true,
    'enable_midstay' => true,
    'preparation_days' => 4,
    'midstay_min_nights' => 3,
];

if (file_exists($config_file)) {
    include $config_file;
}

define('ENABLE_CHECKOUT', $config['enable_checkout']);
define('ENABLE_CHECKIN', $config['enable_checkin']);
define('ENABLE_PREPARATION', $config['enable_preparation']);
define('ENABLE_MIDSTAY', $config['enable_midstay'] ?? true);
define('PREPARATION_DAYS', $config['preparation_days'] ?? 4);
define('MIDSTAY_MIN_NIGHTS', $config['midstay_min_nights'] ?? 3);

// Charger les dépendances
require_once __DIR__ . '/../web/includes/db.php';
require_once __DIR__ . '/../web/includes/template_helper.php';

/**
 * Logger avec timestamp
 */
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] [$level] $message\n";

    // Afficher en console
    echo $logLine;

    // Écrire dans le fichier
    file_put_contents(LOG_FILE, $logLine, FILE_APPEND);
}

/**
 * Envoyer un SMS automatique pour une réservation
 */
function sendAutoSMS($pdo, $reservation, $type) {
    $resId = $reservation['id'];
    $telephone = $reservation['telephone'];
    $prenom = $reservation['prenom'];
    $nom = $reservation['nom'];
    $logement_id = $reservation['logement_id'];

    // Vérifier que le téléphone est présent
    if (empty($telephone)) {
        logMessage("Réservation #$resId : Pas de numéro de téléphone", 'WARNING');
        return false;
    }

    // Déterminer le template et le flag selon le type
    switch ($type) {
        case 'checkout':
            $tplName = 'checkout';
            $flagField = 'dep_sent';
            break;
        case 'checkin':
            $tplName = 'accueil';
            $flagField = 'j1_sent';
            break;
        case 'preparation':
            $tplName = 'preparation';
            $flagField = 'start_sent';
            break;
        case 'midstay':
            $tplName = 'mi_parcours';
            $flagField = 'mid_sent';
            break;
        default:
            logMessage("Type inconnu : $type", 'ERROR');
            return false;
    }

    // Vérifier si le SMS a déjà été envoyé
    if ((int)$reservation[$flagField] === 1) {
        logMessage("Réservation #$resId : SMS $tplName déjà envoyé (flag=$flagField)", 'INFO');
        return false;
    }

    // Récupérer le message personnalisé
    $message = get_personalized_sms($pdo, $tplName, [
        'prenom' => $prenom,
        'nom' => $nom
    ], $logement_id);

    if ($message === null) {
        logMessage("Réservation #$resId : Template '$tplName' non trouvé", 'ERROR');
        return false;
    }

    try {
        $pdo->beginTransaction();

        // 1) Insérer dans sms_outbox
        $stmt = $pdo->prepare("
            INSERT INTO sms_outbox (receiver, message, modem, status, sent_at)
            VALUES (:receiver, :message, '/dev/ttyUSB0', 'pending', NOW())
        ");
        $stmt->execute([
            ':receiver' => $telephone,
            ':message' => $message
        ]);
        $smsId = $pdo->lastInsertId();

        // 2) Marquer le flag sur la réservation
        $allowedFields = ['dep_sent', 'j1_sent', 'start_sent', 'mid_sent'];
        if (!in_array($flagField, $allowedFields, true)) {
            throw new Exception("Nom de champ non autorisé : $flagField");
        }

        $stmt = $pdo->prepare("
            UPDATE reservation
            SET {$flagField} = 1
            WHERE id = :id
        ");
        $stmt->execute([':id' => $resId]);

        // 3) Historique (optionnel)
        $stmt = $pdo->prepare("
            INSERT INTO satisfaction_conversations (sender, role, message)
            VALUES (:sender, 'assistant', :message)
        ");
        $stmt->execute([
            ':sender' => $telephone,
            ':message' => $message
        ]);

        $pdo->commit();

        logMessage("✅ Réservation #$resId ($prenom $nom) : SMS '$tplName' créé (ID=$smsId)", 'SUCCESS');
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logMessage("❌ Réservation #$resId : Erreur - " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Traiter les check-out du jour
 */
function processCheckouts($pdo) {
    if (!ENABLE_CHECKOUT) {
        logMessage("Check-out désactivé dans la config", 'INFO');
        return;
    }

    $today = date('Y-m-d');
    logMessage("=== Traitement des check-out du $today ===", 'INFO');

    $stmt = $pdo->prepare("
        SELECT r.id, r.prenom, r.nom, r.telephone, r.logement_id, r.dep_sent
        FROM reservation r
        WHERE r.date_depart = :date
        AND r.statut = 'confirmée'
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([':date' => $today]);
    $reservations = $stmt->fetchAll();

    logMessage("Trouvé " . count($reservations) . " check-out(s) pour aujourd'hui", 'INFO');

    $sent = 0;
    foreach ($reservations as $res) {
        if (sendAutoSMS($pdo, $res, 'checkout')) {
            $sent++;
        }
    }

    logMessage("✅ $sent SMS de check-out créé(s)", 'SUCCESS');
}

/**
 * Traiter les check-in du jour
 */
function processCheckins($pdo) {
    if (!ENABLE_CHECKIN) {
        logMessage("Check-in désactivé dans la config", 'INFO');
        return;
    }

    $today = date('Y-m-d');
    logMessage("=== Traitement des check-in du $today ===", 'INFO');

    $stmt = $pdo->prepare("
        SELECT r.id, r.prenom, r.nom, r.telephone, r.logement_id, r.j1_sent
        FROM reservation r
        WHERE r.date_arrivee = :date
        AND r.statut = 'confirmée'
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([':date' => $today]);
    $reservations = $stmt->fetchAll();

    logMessage("Trouvé " . count($reservations) . " check-in(s) pour aujourd'hui", 'INFO');

    $sent = 0;
    foreach ($reservations as $res) {
        if (sendAutoSMS($pdo, $res, 'checkin')) {
            $sent++;
        }
    }

    logMessage("✅ $sent SMS de check-in créé(s)", 'SUCCESS');
}

/**
 * Traiter les préparations (arrivées dans 4 jours)
 */
function processPreparations($pdo) {
    if (!ENABLE_PREPARATION) {
        logMessage("Préparation désactivée dans la config", 'INFO');
        return;
    }

    $days = PREPARATION_DAYS;
    $inXDays = date('Y-m-d', strtotime("+{$days} days"));
    logMessage("=== Traitement des préparations pour le $inXDays ($days jours) ===", 'INFO');

    $stmt = $pdo->prepare("
        SELECT r.id, r.prenom, r.nom, r.telephone, r.logement_id, r.start_sent
        FROM reservation r
        WHERE r.date_arrivee = :date
        AND r.statut = 'confirmée'
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([':date' => $inXDays]);
    $reservations = $stmt->fetchAll();

    logMessage("Trouvé " . count($reservations) . " arrivée(s) dans $days jours", 'INFO');

    $sent = 0;
    foreach ($reservations as $res) {
        if (sendAutoSMS($pdo, $res, 'preparation')) {
            $sent++;
        }
    }

    logMessage("✅ $sent SMS de préparation créé(s)", 'SUCCESS');
}

/**
 * Traiter les SMS mi-parcours (séjours de 3+ nuits)
 * Envoie un SMS au milieu du séjour pour prendre des nouvelles
 */
function processMidStay($pdo) {
    if (!ENABLE_MIDSTAY) {
        logMessage("Mi-parcours désactivé dans la config", 'INFO');
        return;
    }

    $minNights = MIDSTAY_MIN_NIGHTS;
    $today = date('Y-m-d');
    logMessage("=== Traitement des SMS mi-parcours (séjours >= $minNights nuits) ===", 'INFO');

    // Trouver les réservations en cours avec au moins X nuits
    // où aujourd'hui = date_arrivee + (durée/2) jours
    $stmt = $pdo->prepare("
        SELECT r.id, r.prenom, r.nom, r.telephone, r.logement_id, r.mid_sent,
               r.date_arrivee, r.date_depart,
               DATEDIFF(r.date_depart, r.date_arrivee) as duree_sejour
        FROM reservation r
        WHERE r.statut = 'confirmée'
        AND r.date_arrivee < :today
        AND r.date_depart > :today
        AND DATEDIFF(r.date_depart, r.date_arrivee) >= :min_nights
        ORDER BY r.date_arrivee
    ");
    $stmt->execute([':today' => $today, ':min_nights' => $minNights]);
    $reservations = $stmt->fetchAll();

    logMessage("Trouvé " . count($reservations) . " séjour(s) en cours de $minNights+ nuits", 'INFO');

    $sent = 0;
    foreach ($reservations as $res) {
        $duree = (int)$res['duree_sejour'];
        $jourMilieu = floor($duree / 2);
        $dateMilieu = date('Y-m-d', strtotime($res['date_arrivee'] . " + $jourMilieu days"));

        // Vérifier si aujourd'hui est le jour du milieu
        if ($dateMilieu !== $today) {
            continue;
        }

        logMessage("Réservation #" . $res['id'] . " : Jour $jourMilieu sur $duree (mi-parcours)", 'INFO');

        if (sendAutoSMS($pdo, $res, 'midstay')) {
            $sent++;
        }
    }

    logMessage("✅ $sent SMS de mi-parcours créé(s)", 'SUCCESS');
}

/**
 * Traiter les automatisations personnalisées
 */
function processCustomAutomations($pdo) {
    logMessage("=== Traitement des automatisations personnalisées ===", 'INFO');

    try {
        // Récupérer toutes les automatisations actives
        $stmt = $pdo->query("
            SELECT * FROM sms_automations
            WHERE actif = 1
            ORDER BY id
        ");
        $automations = $stmt->fetchAll();

        if (count($automations) === 0) {
            logMessage("Aucune automatisation personnalisée active", 'INFO');
            return;
        }

        logMessage("Trouvé " . count($automations) . " automatisation(s) personnalisée(s) active(s)", 'INFO');

        foreach ($automations as $auto) {
            $autoId = $auto['id'];
            $autoNom = $auto['nom'];
            $declencheurType = $auto['declencheur_type'];
            $declencheurJours = (int)$auto['declencheur_jours'];
            $templateName = $auto['template_name'];
            $conditionStatut = $auto['condition_statut'];
            $flagField = $auto['flag_field'];
            $logementId = !empty($auto['logement_id']) ? (int)$auto['logement_id'] : null;

            $logementInfo = $logementId ? " (logement ID=$logementId)" : " (tous logements)";
            logMessage("--- Traitement automatisation: $autoNom (ID=$autoId)$logementInfo ---", 'INFO');

            // Calculer la date cible
            if ($declencheurJours == 0) {
                $targetDate = date('Y-m-d');
            } elseif ($declencheurJours < 0) {
                // X jours AVANT
                $targetDate = date('Y-m-d', strtotime(abs($declencheurJours) . ' days'));
            } else {
                // X jours APRES
                $targetDate = date('Y-m-d', strtotime('-' . $declencheurJours . ' days'));
            }

            // Déterminer la colonne de date selon le type de déclencheur
            $dateColumn = match($declencheurType) {
                'date_arrivee' => 'date_arrivee',
                'date_depart' => 'date_depart',
                'date_reservation' => 'date_reservation',
                default => 'date_arrivee'
            };

            // Construire la requête
            $sql = "
                SELECT r.id, r.prenom, r.nom, r.telephone, r.logement_id, r.{$flagField}
                FROM reservation r
                WHERE r.{$dateColumn} = :target_date
            ";

            // Ajouter condition sur le statut si défini
            $params = [':target_date' => $targetDate];
            if (!empty($conditionStatut)) {
                $sql .= " AND r.statut = :statut";
                $params[':statut'] = $conditionStatut;
            }

            // Ajouter condition sur le logement si défini
            if ($logementId !== null) {
                $sql .= " AND r.logement_id = :logement_id";
                $params[':logement_id'] = $logementId;
            }

            $sql .= " ORDER BY r.created_at DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $reservations = $stmt->fetchAll();

            logMessage("Date cible: $targetDate ($dateColumn), " . count($reservations) . " réservation(s) trouvée(s)", 'INFO');

            $sent = 0;
            foreach ($reservations as $res) {
                // Vérifier si le SMS a déjà été envoyé
                if ((int)$res[$flagField] === 1) {
                    logMessage("Réservation #" . $res['id'] . " : SMS déjà envoyé (flag=$flagField)", 'INFO');
                    continue;
                }

                $resId = $res['id'];
                $telephone = $res['telephone'];
                $prenom = $res['prenom'];
                $nom = $res['nom'];
                $logement_id = $res['logement_id'];

                if (empty($telephone)) {
                    logMessage("Réservation #$resId : Pas de numéro de téléphone", 'WARNING');
                    continue;
                }

                // Récupérer le message personnalisé
                $message = get_personalized_sms($pdo, $templateName, [
                    'prenom' => $prenom,
                    'nom' => $nom
                ], $logement_id);

                if ($message === null) {
                    logMessage("Réservation #$resId : Template '$templateName' non trouvé", 'ERROR');
                    continue;
                }

                try {
                    $pdo->beginTransaction();

                    // 1) Insérer dans sms_outbox
                    $stmt = $pdo->prepare("
                        INSERT INTO sms_outbox (receiver, message, modem, status, sent_at)
                        VALUES (:receiver, :message, '/dev/ttyUSB0', 'pending', NOW())
                    ");
                    $stmt->execute([
                        ':receiver' => $telephone,
                        ':message' => $message
                    ]);
                    $smsId = $pdo->lastInsertId();

                    // 2) Marquer le flag sur la réservation
                    $stmt = $pdo->prepare("
                        UPDATE reservation
                        SET {$flagField} = 1
                        WHERE id = :id
                    ");
                    $stmt->execute([':id' => $resId]);

                    // 3) Historique (optionnel)
                    $stmt = $pdo->prepare("
                        INSERT INTO satisfaction_conversations (sender, role, message)
                        VALUES (:sender, 'assistant', :message)
                    ");
                    $stmt->execute([
                        ':sender' => $telephone,
                        ':message' => $message
                    ]);

                    $pdo->commit();

                    logMessage("✅ Réservation #$resId ($prenom $nom) : SMS '$templateName' créé (ID=$smsId)", 'SUCCESS');
                    $sent++;

                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    logMessage("❌ Réservation #$resId : Erreur - " . $e->getMessage(), 'ERROR');
                }
            }

            logMessage("✅ Automatisation '$autoNom' : $sent SMS créé(s)", 'SUCCESS');
        }

    } catch (PDOException $e) {
        logMessage("❌ Erreur lors du traitement des automatisations personnalisées : " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Main
 */
function main() {
    global $pdo, $runType;

    logMessage("========================================", 'INFO');
    logMessage("Démarrage du script d'automatisation SMS (type: $runType)", 'INFO');
    logMessage("========================================", 'INFO');

    // Vérifier la connexion PDO
    if (!($pdo instanceof PDO)) {
        logMessage("Erreur: PDO non disponible", 'ERROR');
        exit(1);
    }

    try {
        // Traiter les types demandés
        if ($runType === 'all' || $runType === 'checkout') {
            processCheckouts($pdo);
        }
        if ($runType === 'all' || $runType === 'checkin') {
            processCheckins($pdo);
        }
        if ($runType === 'all' || $runType === 'preparation') {
            processPreparations($pdo);
        }
        if ($runType === 'all' || $runType === 'midstay') {
            processMidStay($pdo);
        }
        if ($runType === 'all' || $runType === 'custom') {
            processCustomAutomations($pdo);
        }

        logMessage("========================================", 'INFO');
        logMessage("Script terminé avec succès (type: $runType)", 'SUCCESS');
        logMessage("========================================", 'INFO');

    } catch (Exception $e) {
        logMessage("Erreur fatale : " . $e->getMessage(), 'ERROR');
        exit(1);
    }
}

// Exécuter le script
main();
