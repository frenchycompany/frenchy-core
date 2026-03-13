<?php
/**
 * Interface de gestion de la configuration Superhote
 * Version simplifiee - Yield Management automatique
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/db.php';

if (!$pdo) {
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=sms_db;charset=utf8mb4",
            "sms_user",
            "password123",
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        die("Erreur de connexion BDD: " . $e->getMessage());
    }
}

require_once __DIR__ . '/../includes/header_minimal.php';

// ============================================================================
// GESTION DU FICHIER CONFIG SUPERHOTE (credentials)
// ============================================================================
$configSuperhoteFile = dirname(dirname(__DIR__)) . '/scripts/selenium/config_superhote.ini';

/**
 * Lit les credentials Superhote depuis le fichier ini
 * Parser manuel pour gerer les caracteres speciaux dans les mots de passe
 */
function getSuperhoteCredentials($configFile) {
    if (!file_exists($configFile)) {
        return ['email' => '', 'password' => '', 'exists' => false];
    }

    $content = file_get_contents($configFile);
    if ($content === false) {
        return ['email' => '', 'password' => '', 'exists' => false];
    }

    $email = '';
    $password = '';

    // Parser manuel ligne par ligne
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        $line = trim($line);
        // Ignorer commentaires et lignes vides
        if (empty($line) || $line[0] === '#' || $line[0] === ';') {
            continue;
        }
        // Chercher email = ...
        if (preg_match('/^email\s*=\s*(.*)$/i', $line, $m)) {
            $email = trim($m[1]);
        }
        // Chercher password = ... (enlever les guillemets si presents)
        if (preg_match('/^password\s*=\s*(.*)$/i', $line, $m)) {
            $password = trim($m[1]);
            // Enlever les guillemets autour du password
            if (strlen($password) >= 2 && $password[0] === '"' && $password[strlen($password)-1] === '"') {
                $password = substr($password, 1, -1);
            }
        }
    }

    return [
        'email' => $email,
        'password' => $password,
        'exists' => true
    ];
}

/**
 * Sauvegarde les credentials dans le fichier ini
 */
function saveSuperhoteCredentials($configFile, $email, $password) {
    $exampleFile = dirname($configFile) . '/config_superhote.ini.example';

    // Lire le fichier exemple ou creer un contenu par defaut
    if (file_exists($exampleFile)) {
        $content = file_get_contents($exampleFile);
    } else {
        $content = <<<INI
# Configuration Superhote pour l'automatisation Selenium

[SUPERHOTE]
login_url = https://app.superhote.com/login
dashboard_url = https://app.superhote.com/dashboard
email = votre_email@example.com
password = votre_mot_de_passe
timeout = 30
page_load_timeout = 60

[SELENIUM]
headless = True
chromedriver_path = /usr/bin/chromedriver
window_width = 1920
window_height = 1080

[DATABASE]
use_main_config = True

[LOGEMENTS]
INI;
    }

    // Remplacer les credentials (guillemets autour du password pour les caracteres speciaux)
    $content = preg_replace('/^email\s*=.*$/m', 'email = ' . $email, $content);
    $content = preg_replace('/^password\s*=.*$/m', 'password = "' . $password . '"', $content);

    // S'assurer que chromedriver_path est configure
    if (strpos($content, 'chromedriver_path') === false) {
        $content = str_replace('[SELENIUM]', "[SELENIUM]\nchromedriver_path = /usr/bin/chromedriver", $content);
    } elseif (preg_match('/^chromedriver_path\s*=\s*$/m', $content)) {
        $content = preg_replace('/^chromedriver_path\s*=.*$/m', 'chromedriver_path = /usr/bin/chromedriver', $content);
    }

    return file_put_contents($configFile, $content) !== false;
}

$superhoteCredentials = getSuperhoteCredentials($configSuperhoteFile);

// Creer/mettre a jour les tables
function ensureTablesExist($pdo) {
    // Table superhote_config (simplifiee)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `superhote_config` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `logement_id` INT(11) NOT NULL,
        `superhote_property_id` VARCHAR(100) DEFAULT NULL,
        `superhote_property_name` VARCHAR(255) DEFAULT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `prix_plancher` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix minimum (jour 0)',
        `prix_standard` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix normal (J+14)',
        `weekend_pourcent` DECIMAL(5,2) DEFAULT 10 COMMENT 'Majoration weekend en %',
        `dimanche_reduction` DECIMAL(10,2) DEFAULT 5 COMMENT 'Reduction dimanche en euros',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_logement` (`logement_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ajouter les nouvelles colonnes si elles n'existent pas
    $columns = ['prix_plancher', 'prix_standard', 'weekend_pourcent', 'dimanche_reduction'];
    foreach ($columns as $col) {
        try {
            $pdo->exec("ALTER TABLE `superhote_config` ADD COLUMN `$col` DECIMAL(10,2) DEFAULT NULL");
        } catch (PDOException $e) {}
    }

    // Colonne groupe
    try {
        $pdo->exec("ALTER TABLE `superhote_config` ADD COLUMN `groupe` VARCHAR(100) DEFAULT NULL");
    } catch (PDOException $e) {}

    // Table superhote_groups
    $pdo->exec("CREATE TABLE IF NOT EXISTS `superhote_groups` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `nom` VARCHAR(100) NOT NULL,
        `description` VARCHAR(255) DEFAULT NULL,
        `logement_reference_id` INT(11) DEFAULT NULL COMMENT 'Logement fictif de reference',
        `prix_plancher` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix minimum par defaut (J0)',
        `prix_standard` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix normal par defaut (J14+)',
        `weekend_pourcent` DECIMAL(5,2) DEFAULT 10 COMMENT 'Majoration weekend par defaut en %',
        `dimanche_reduction` DECIMAL(10,2) DEFAULT 5 COMMENT 'Reduction dimanche par defaut en euros',
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_nom` (`nom`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ajouter les colonnes de tarification si elles n'existent pas (migration)
    $groupPricingCols = ['prix_plancher', 'prix_standard', 'weekend_pourcent', 'dimanche_reduction'];
    foreach ($groupPricingCols as $col) {
        try {
            $pdo->exec("ALTER TABLE `superhote_groups` ADD COLUMN `$col` DECIMAL(10,2) DEFAULT NULL");
        } catch (PDOException $e) {}
    }

    // Valeurs par defaut
    try {
        $pdo->exec("UPDATE superhote_config SET weekend_pourcent = 10 WHERE weekend_pourcent IS NULL");
        $pdo->exec("UPDATE superhote_config SET dimanche_reduction = 5 WHERE dimanche_reduction IS NULL");
    } catch (PDOException $e) {}

    // Table superhote_settings (parametres globaux)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `superhote_settings` (
        `key_name` VARCHAR(50) NOT NULL,
        `value` VARCHAR(255) NOT NULL,
        `description` VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (`key_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Inserer les parametres par defaut
    $defaults = [
        ['palier_j1_3_pourcent', '20', 'Pourcentage entre plancher et standard pour J1-3'],
        ['palier_j4_13_pourcent', '40', 'Pourcentage entre plancher et standard pour J4-13'],
        ['palier_j14_30_pourcent', '60', 'Pourcentage entre plancher et standard pour J14-30'],
        ['palier_j31_60_pourcent', '80', 'Pourcentage entre plancher et standard pour J31-60'],
        ['jours_generation', '30', 'Nombre de jours a generer'],
        ['scheduled_time', '07:00', 'Heure de mise a jour quotidienne (HH:MM)'],
        ['scheduled_enabled', '1', 'Activer la mise a jour planifiee'],
        ['max_workers', '2', 'Nombre de workers pour la mise a jour'],
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO superhote_settings (key_name, value, description) VALUES (?, ?, ?)");
    foreach ($defaults as $d) {
        $stmt->execute($d);
    }

    // Table superhote_price_updates
    $pdo->exec("CREATE TABLE IF NOT EXISTS `superhote_price_updates` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `logement_id` INT(11) NOT NULL,
        `superhote_property_id` VARCHAR(100) NOT NULL,
        `nom_du_logement` VARCHAR(255) DEFAULT NULL,
        `date_start` DATE NOT NULL,
        `date_end` DATE NOT NULL,
        `price` DECIMAL(10,2) NOT NULL,
        `rule_name` VARCHAR(100) DEFAULT NULL,
        `status` VARCHAR(20) DEFAULT 'pending',
        `error_message` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try {
        $pdo->exec("ALTER TABLE `superhote_price_updates` ADD COLUMN `nom_du_logement` VARCHAR(255) DEFAULT NULL");
    } catch (PDOException $e) {}
}

try {
    ensureTablesExist($pdo);
} catch (PDOException $e) {}

// Messages
$message = '';
$messageType = '';

// Recuperer les parametres globaux
function getSettings($pdo) {
    $settings = [];
    $rows = $pdo->query("SELECT key_name, value FROM superhote_settings")->fetchAll();
    foreach ($rows as $row) {
        $settings[$row['key_name']] = $row['value'];
    }
    return $settings;
}

// Traitement AJAX pour les regles avancees
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $ajaxAction = $_GET['ajax'];

    try {
        switch ($ajaxAction) {
            case 'toggle_setting':
                $key = $_POST['key'] ?? '';
                $value = $_POST['value'] ?? '0';
                if (!empty($key)) {
                    $stmt = $pdo->prepare("INSERT INTO superhote_settings (key_name, value) VALUES (?, ?)
                                          ON DUPLICATE KEY UPDATE value = ?");
                    $stmt->execute([$key, $value, $value]);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Cle manquante']);
                }
                exit;

            case 'toggle_season':
                $id = intval($_POST['id'] ?? 0);
                $active = intval($_POST['active'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE superhote_seasons SET is_active = ? WHERE id = ?");
                    $stmt->execute([$active, $id]);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'ID invalide']);
                }
                exit;

            case 'toggle_holiday':
                $id = intval($_POST['id'] ?? 0);
                $active = intval($_POST['active'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE superhote_holidays SET is_active = ? WHERE id = ?");
                    $stmt->execute([$active, $id]);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'ID invalide']);
                }
                exit;

            case 'toggle_occupancy':
                $id = intval($_POST['id'] ?? 0);
                $active = intval($_POST['active'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE superhote_occupancy_rules SET is_active = ? WHERE id = ?");
                    $stmt->execute([$active, $id]);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'ID invalide']);
                }
                exit;

            default:
                echo json_encode(['success' => false, 'error' => 'Action inconnue']);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'save_config':
                $logementId = intval($_POST['logement_id'] ?? 0);
                $superhoteId = trim($_POST['superhote_property_id'] ?? '');
                $superhoteName = trim($_POST['superhote_property_name'] ?? '');
                $prixPlancher = floatval($_POST['prix_plancher'] ?? 0);
                $prixStandard = floatval($_POST['prix_standard'] ?? 0);
                $weekendPourcent = floatval($_POST['weekend_pourcent'] ?? 10);
                $dimancheReduction = floatval($_POST['dimanche_reduction'] ?? 5);
                $groupe = trim($_POST['groupe'] ?? '') ?: null;
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                if ($logementId > 0 && !empty($superhoteId)) {
                    $check = $pdo->prepare("SELECT id FROM superhote_config WHERE logement_id = ?");
                    $check->execute([$logementId]);

                    if ($check->fetch()) {
                        $stmt = $pdo->prepare("UPDATE superhote_config SET
                            superhote_property_id = ?,
                            superhote_property_name = ?,
                            prix_plancher = ?,
                            prix_standard = ?,
                            weekend_pourcent = ?,
                            dimanche_reduction = ?,
                            groupe = ?,
                            is_active = ?,
                            updated_at = NOW()
                            WHERE logement_id = ?");
                        $stmt->execute([$superhoteId, $superhoteName, $prixPlancher ?: null, $prixStandard ?: null,
                                       $weekendPourcent, $dimancheReduction, $groupe, $isActive, $logementId]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO superhote_config
                            (logement_id, superhote_property_id, superhote_property_name, prix_plancher, prix_standard,
                             weekend_pourcent, dimanche_reduction, groupe, is_active)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$logementId, $superhoteId, $superhoteName, $prixPlancher ?: null,
                                       $prixStandard ?: null, $weekendPourcent, $dimancheReduction, $groupe, $isActive]);
                    }
                    $message = "Configuration sauvegardee!";
                    $messageType = "success";
                } else {
                    $message = "Veuillez remplir l'ID Superhote.";
                    $messageType = "warning";
                }
                break;

            case 'save_group':
                $groupNom = trim($_POST['group_nom'] ?? '');
                $groupDesc = trim($_POST['group_description'] ?? '');
                $groupRefId = intval($_POST['group_reference_id'] ?? 0) ?: null;
                $groupId = intval($_POST['group_id'] ?? 0);
                $groupPrixPlancher = floatval($_POST['group_prix_plancher'] ?? 0) ?: null;
                $groupPrixStandard = floatval($_POST['group_prix_standard'] ?? 0) ?: null;
                $groupWeekendPourcent = floatval($_POST['group_weekend_pourcent'] ?? 10);
                $groupDimancheReduction = floatval($_POST['group_dimanche_reduction'] ?? 5);

                if (!empty($groupNom)) {
                    if ($groupId > 0) {
                        $stmt = $pdo->prepare("UPDATE superhote_groups SET nom = ?, description = ?, logement_reference_id = ?,
                            prix_plancher = ?, prix_standard = ?, weekend_pourcent = ?, dimanche_reduction = ? WHERE id = ?");
                        $stmt->execute([$groupNom, $groupDesc, $groupRefId, $groupPrixPlancher, $groupPrixStandard,
                                       $groupWeekendPourcent, $groupDimancheReduction, $groupId]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO superhote_groups (nom, description, logement_reference_id,
                            prix_plancher, prix_standard, weekend_pourcent, dimanche_reduction) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$groupNom, $groupDesc, $groupRefId, $groupPrixPlancher, $groupPrixStandard,
                                       $groupWeekendPourcent, $groupDimancheReduction]);
                    }
                    $message = "Groupe sauvegarde!";
                    $messageType = "success";
                } else {
                    $message = "Le nom du groupe est requis.";
                    $messageType = "warning";
                }
                break;

            case 'delete_group':
                $groupId = intval($_POST['group_id'] ?? 0);
                if ($groupId > 0) {
                    // Retirer le groupe des logements associes
                    $groupNom = $pdo->prepare("SELECT nom FROM superhote_groups WHERE id = ?");
                    $groupNom->execute([$groupId]);
                    $nom = $groupNom->fetchColumn();
                    if ($nom) {
                        $pdo->prepare("UPDATE superhote_config SET groupe = NULL WHERE groupe = ?")->execute([$nom]);
                    }
                    $pdo->prepare("DELETE FROM superhote_groups WHERE id = ?")->execute([$groupId]);
                    $message = "Groupe supprime.";
                    $messageType = "success";
                }
                break;

            case 'save_settings':
                $settings = [
                    'palier_j1_3_pourcent' => floatval($_POST['palier_j1_3_pourcent'] ?? 20),
                    'palier_j4_13_pourcent' => floatval($_POST['palier_j4_13_pourcent'] ?? 40),
                    'palier_j14_30_pourcent' => floatval($_POST['palier_j14_30_pourcent'] ?? 60),
                    'palier_j31_60_pourcent' => floatval($_POST['palier_j31_60_pourcent'] ?? 80),
                    'jours_generation' => intval($_POST['jours_generation'] ?? 30),
                ];

                $stmt = $pdo->prepare("UPDATE superhote_settings SET value = ? WHERE key_name = ?");
                foreach ($settings as $key => $value) {
                    $stmt->execute([$value, $key]);
                }
                $message = "Parametres sauvegardes!";
                $messageType = "success";
                break;

            case 'save_schedule':
                $scheduledTimes = $_POST['scheduled_times'] ?? ['07:00'];
                $scheduledEnabled = isset($_POST['scheduled_enabled']) ? '1' : '0';
                $maxWorkers = intval($_POST['max_workers'] ?? 2);

                // Filtrer et valider les heures
                $validTimes = [];
                foreach ($scheduledTimes as $time) {
                    $time = trim($time);
                    if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
                        $validTimes[] = $time;
                    }
                }

                if (!empty($validTimes)) {
                    // Trier les heures
                    sort($validTimes);
                    $timesString = implode(',', $validTimes);

                    $stmt = $pdo->prepare("INSERT INTO superhote_settings (key_name, value) VALUES (?, ?)
                                          ON DUPLICATE KEY UPDATE value = VALUES(value)");
                    $stmt->execute(['scheduled_times', $timesString]);
                    $stmt->execute(['scheduled_time', $validTimes[0]]); // Garder compatibilite
                    $stmt->execute(['scheduled_enabled', $scheduledEnabled]);
                    $stmt->execute(['max_workers', $maxWorkers]);

                    // Mettre a jour les timers systemd si possible
                    $scriptPath = dirname(dirname(__DIR__)) . '/scripts/selenium';
                    $output = shell_exec("cd $scriptPath && ./update_schedule.sh \"$timesString\" 2>&1");

                    $count = count($validTimes);
                    $message = "Planification sauvegardee! $count mise(s) a jour/jour: " . implode(', ', $validTimes);
                    $messageType = "success";
                } else {
                    $message = "Format d'heure invalide (utilisez HH:MM)";
                    $messageType = "danger";
                }
                break;

            case 'run_now':
                // Lancer la mise a jour immediatement
                $scriptDir = dirname(dirname(__DIR__)) . '/scripts/selenium';
                $scriptPath = $scriptDir . '/run_scheduled_update.py';
                $logPath = dirname(dirname(__DIR__)) . '/logs';
                $maxWorkers = intval($settings['max_workers'] ?? 1);

                // Verifier que le script existe
                if (!file_exists($scriptPath)) {
                    $message = "Erreur: script non trouve: $scriptPath";
                    $messageType = "danger";
                    break;
                }

                // Lancer en arriere-plan avec nohup
                $logFile = "$logPath/manual_run.log";
                $cmd = "cd $scriptDir && nohup /usr/bin/python3 run_scheduled_update.py -w $maxWorkers > $logFile 2>&1 &";

                // Utiliser proc_open pour garantir le lancement en arriere-plan
                $descriptorspec = array(
                    0 => array("pipe", "r"),
                    1 => array("pipe", "w"),
                    2 => array("pipe", "w")
                );
                $process = proc_open($cmd, $descriptorspec, $pipes);
                if (is_resource($process)) {
                    fclose($pipes[0]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($process);
                    $message = "Mise a jour lancee en arriere-plan. Consultez les logs pour suivre la progression.";
                    $messageType = "success";
                } else {
                    $message = "Erreur lors du lancement du script.";
                    $messageType = "danger";
                }
                break;

            case 'save_credentials':
                $superhoteEmail = trim($_POST['superhote_email'] ?? '');
                $superhotePassword = trim($_POST['superhote_password'] ?? '');

                if (empty($superhoteEmail) || empty($superhotePassword)) {
                    $message = "Email et mot de passe Superhote requis.";
                    $messageType = "warning";
                } else {
                    if (saveSuperhoteCredentials($configSuperhoteFile, $superhoteEmail, $superhotePassword)) {
                        $superhoteCredentials = getSuperhoteCredentials($configSuperhoteFile);
                        $message = "Identifiants Superhote sauvegardes!";
                        $messageType = "success";
                    } else {
                        $message = "Erreur lors de la sauvegarde du fichier de configuration.";
                        $messageType = "danger";
                    }
                }
                break;

            case 'generate_all':
                $generated = generateAllPrices($pdo);
                $message = "$generated mises a jour de prix generees!";
                $messageType = "success";
                break;

            case 'generate_one':
                $logementId = intval($_POST['logement_id'] ?? 0);
                if ($logementId > 0) {
                    $generated = generatePricesForLogement($pdo, $logementId);
                    $message = "$generated mises a jour generees!";
                    $messageType = "success";
                }
                break;

            case 'clear_pending':
                $pdo->exec("DELETE FROM superhote_price_updates WHERE status = 'pending'");
                $message = "File d'attente videe.";
                $messageType = "success";
                break;
        }
    } catch (PDOException $e) {
        $message = "Erreur: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Fonction pour calculer le prix selon l'anticipation
function calculatePrice($prixPlancher, $prixStandard, $joursAvant, $jourSemaine, $weekendPourcent, $dimancheReduction, $settings) {
    $palierJ1_3 = floatval($settings['palier_j1_3_pourcent'] ?? 20) / 100;
    $palierJ4_13 = floatval($settings['palier_j4_13_pourcent'] ?? 40) / 100;
    $palierJ14_30 = floatval($settings['palier_j14_30_pourcent'] ?? 60) / 100;
    $palierJ31_60 = floatval($settings['palier_j31_60_pourcent'] ?? 80) / 100;

    $ecart = $prixStandard - $prixPlancher;

    // Calcul du prix de base selon l'anticipation
    if ($joursAvant == 0) {
        $prix = $prixPlancher;
        $palier = 'J0 (plancher)';
    } elseif ($joursAvant <= 3) {
        $prix = $prixPlancher + ($ecart * $palierJ1_3);
        $palier = 'J1-3';
    } elseif ($joursAvant <= 13) {
        $prix = $prixPlancher + ($ecart * $palierJ4_13);
        $palier = 'J4-13';
    } elseif ($joursAvant <= 30) {
        $prix = $prixPlancher + ($ecart * $palierJ14_30);
        $palier = 'J14-30';
    } elseif ($joursAvant <= 60) {
        $prix = $prixPlancher + ($ecart * $palierJ31_60);
        $palier = 'J31-60';
    } else {
        $prix = $prixStandard;
        $palier = 'J60+ (standard)';
    }

    // Appliquer majoration weekend (vendredi=5, samedi=6)
    if ($jourSemaine == 5 || $jourSemaine == 6) {
        $prix = $prix * (1 + $weekendPourcent / 100);
        $palier .= ' +WE';
    }
    // Appliquer reduction dimanche (dimanche=0)
    elseif ($jourSemaine == 0) {
        $prix = $prix - $dimancheReduction;
        $palier .= ' -Dim';
    }

    return ['prix' => round($prix, 0), 'palier' => $palier];
}

// Generer les prix pour un logement
function generatePricesForLogement($pdo, $logementId) {
    $settings = getSettings($pdo);
    $joursGeneration = intval($settings['jours_generation'] ?? 30);

    // Recuperer la config du logement avec info groupe
    $stmt = $pdo->prepare("
        SELECT sc.*, l.nom_du_logement,
               g.prix_plancher as groupe_prix_plancher,
               g.prix_standard as groupe_prix_standard,
               g.weekend_pourcent as groupe_weekend_pourcent,
               g.dimanche_reduction as groupe_dimanche_reduction
        FROM superhote_config sc
        JOIN liste_logements l ON sc.logement_id = l.id
        LEFT JOIN superhote_groups g ON sc.groupe = g.nom
        WHERE sc.logement_id = ? AND sc.is_active = 1
    ");
    $stmt->execute([$logementId]);
    $config = $stmt->fetch();

    if (!$config || empty($config['superhote_property_id'])) {
        return 0;
    }

    // Utiliser les prix du groupe si disponibles, sinon ceux du logement
    $prixPlancher = $config['groupe_prix_plancher'] ?? $config['prix_plancher'];
    $prixStandard = $config['groupe_prix_standard'] ?? $config['prix_standard'];
    $weekendPourcent = $config['groupe_weekend_pourcent'] ?? $config['weekend_pourcent'] ?? 10;
    $dimancheReduction = $config['groupe_dimanche_reduction'] ?? $config['dimanche_reduction'] ?? 5;

    if (empty($prixPlancher) || empty($prixStandard)) {
        return 0;
    }

    // Supprimer les anciennes mises a jour pending pour ce logement
    $pdo->prepare("DELETE FROM superhote_price_updates WHERE logement_id = ? AND status = 'pending'")->execute([$logementId]);

    $count = 0;
    $today = new DateTime();

    for ($i = 0; $i <= $joursGeneration; $i++) {
        $date = clone $today;
        $date->modify("+$i days");
        $dateStr = $date->format('Y-m-d');
        $jourSemaine = intval($date->format('w')); // 0=Dim, 6=Sam

        $result = calculatePrice(
            floatval($prixPlancher),
            floatval($prixStandard),
            $i,
            $jourSemaine,
            floatval($weekendPourcent),
            floatval($dimancheReduction),
            $settings
        );

        $stmt = $pdo->prepare("INSERT INTO superhote_price_updates
            (logement_id, superhote_property_id, nom_du_logement, date_start, date_end, price, rule_name, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([
            $logementId,
            $config['superhote_property_id'],
            $config['nom_du_logement'],
            $dateStr,
            $dateStr,
            $result['prix'],
            $result['palier']
        ]);
        $count++;
    }

    return $count;
}

// Generer les prix pour tous les logements actifs
function generateAllPrices($pdo) {
    $configs = $pdo->query("SELECT logement_id FROM superhote_config WHERE is_active = 1")->fetchAll();
    $total = 0;
    foreach ($configs as $config) {
        $total += generatePricesForLogement($pdo, $config['logement_id']);
    }
    return $total;
}

// Recuperer les donnees
$settings = getSettings($pdo);
$logements = [];
$pendingUpdates = [];
$groups = [];

try {
    $logements = $pdo->query("
        SELECT l.*, sc.superhote_property_id, sc.superhote_property_name,
               sc.prix_plancher, sc.prix_standard, sc.weekend_pourcent, sc.dimanche_reduction,
               sc.groupe, sc.is_active as superhote_active,
               g.prix_plancher as groupe_prix_plancher,
               g.prix_standard as groupe_prix_standard,
               g.weekend_pourcent as groupe_weekend_pourcent,
               g.dimanche_reduction as groupe_dimanche_reduction
        FROM liste_logements l
        LEFT JOIN superhote_config sc ON l.id = sc.logement_id
        LEFT JOIN superhote_groups g ON sc.groupe = g.nom
        WHERE l.actif = 1
        ORDER BY l.nom_du_logement
    ")->fetchAll();
} catch (PDOException $e) {
    $logements = [];
}

try {
    $groups = $pdo->query("
        SELECT g.*, l.nom_du_logement as reference_name,
               (SELECT COUNT(*) FROM superhote_config WHERE groupe = g.nom) as membre_count
        FROM superhote_groups g
        LEFT JOIN liste_logements l ON g.logement_reference_id = l.id
        ORDER BY g.nom
    ")->fetchAll();
} catch (PDOException $e) {
    $groups = [];
}

try {
    $pendingUpdates = $pdo->query("
        SELECT * FROM superhote_price_updates
        WHERE status IN ('pending', 'processing')
        ORDER BY nom_du_logement, date_start
        LIMIT 100
    ")->fetchAll();
} catch (PDOException $e) {
    $pendingUpdates = [];
}

// Charger les saisons
$seasons = [];
try {
    $seasons = $pdo->query("SELECT * FROM superhote_seasons ORDER BY priorite DESC, date_debut")->fetchAll();
} catch (PDOException $e) {
    $seasons = [];
}

// Charger les jours feries
$holidays = [];
try {
    $holidays = $pdo->query("SELECT * FROM superhote_holidays ORDER BY date_ferie")->fetchAll();
} catch (PDOException $e) {
    $holidays = [];
}

// Charger les regles d'occupation
$occupancyRules = [];
try {
    $occupancyRules = $pdo->query("SELECT * FROM superhote_occupancy_rules ORDER BY seuil_occupation_min")->fetchAll();
} catch (PDOException $e) {
    $occupancyRules = [];
}
?>

<!-- Header de page -->
<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="display-4">
            <i class="fas fa-robot text-primary"></i> Superhote - Yield Management
        </h1>
        <p class="lead text-muted">Tarification dynamique automatique basee sur l'anticipation</p>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
<?php endif; ?>

<!-- Onglets -->
    <ul class="nav nav-tabs" id="mainTabs">
        <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#tabConfig">
                <i class="fas fa-home"></i> Logements
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tabGroups">
                <i class="fas fa-layer-group"></i> Groupes
                <?php if (count($groups) > 0): ?>
                <span class="badge badge-info"><?= count($groups) ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tabSettings">
                <i class="fas fa-sliders-h"></i> Parametres
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tabAdvanced">
                <i class="fas fa-chart-line"></i> Regles avancees
                <?php if (count($seasons) > 0 || count($holidays) > 0): ?>
                <span class="badge badge-success"><?= count($seasons) + count($holidays) ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tabQueue">
                <i class="fas fa-list"></i> File d'attente
                <?php if (count($pendingUpdates) > 0): ?>
                <span class="badge badge-warning"><?= count($pendingUpdates) ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tabDaemon" id="daemonTab">
                <i class="fas fa-robot"></i> Workers
                <span class="badge badge-secondary" id="daemonStatus">?</span>
            </a>
        </li>
    </ul>

    <div class="tab-content mt-3">
        <!-- Onglet Logements -->
        <div class="tab-pane fade show active" id="tabConfig">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-home"></i> Configuration des Logements</h5>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="generate_all">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-magic"></i> Generer tous les prix
                        </button>
                    </form>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Logement</th>
                                    <th>ID Superhote</th>
                                    <th>Groupe</th>
                                    <th>Prix Plancher</th>
                                    <th>Prix Standard</th>
                                    <th>Weekend +%</th>
                                    <th>Dim -€</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logements as $log): ?>
                                <?php
                                    $hasConfig = !empty($log['superhote_property_id']);
                                    $hasGroupe = !empty($log['groupe']);
                                    // Prix effectifs: du groupe si disponible, sinon du logement
                                    $effPlancher = $log['groupe_prix_plancher'] ?? $log['prix_plancher'];
                                    $effStandard = $log['groupe_prix_standard'] ?? $log['prix_standard'];
                                    $effWeekend = $log['groupe_weekend_pourcent'] ?? $log['weekend_pourcent'];
                                    $effDimanche = $log['groupe_dimanche_reduction'] ?? $log['dimanche_reduction'];
                                    $fromGroupe = $hasGroupe && !empty($log['groupe_prix_plancher']);
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($log['nom_du_logement'] ?? 'N/A') ?></strong></td>
                                    <td>
                                        <?php if ($hasConfig): ?>
                                        <code class="small"><?= htmlspecialchars($log['superhote_property_id']) ?></code>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($hasGroupe): ?>
                                        <span class="badge badge-info"><?= htmlspecialchars($log['groupe']) ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $effPlancher ? number_format($effPlancher, 0) . '€' : '-' ?><?= $fromGroupe ? ' <small class="text-info" title="Herite du groupe">(G)</small>' : '' ?></td>
                                    <td><?= $effStandard ? number_format($effStandard, 0) . '€' : '-' ?><?= $fromGroupe ? ' <small class="text-info" title="Herite du groupe">(G)</small>' : '' ?></td>
                                    <td><?= $effWeekend ? '+' . number_format($effWeekend, 0) . '%' : '-' ?><?= $fromGroupe ? ' <small class="text-info">(G)</small>' : '' ?></td>
                                    <td><?= $effDimanche ? '-' . number_format($effDimanche, 0) . '€' : '-' ?><?= $fromGroupe ? ' <small class="text-info">(G)</small>' : '' ?></td>
                                    <td>
                                        <?php if ($hasConfig && ($log['superhote_active'] ?? 0)): ?>
                                        <span class="badge badge-success">Actif</span>
                                        <?php elseif ($hasConfig): ?>
                                        <span class="badge badge-secondary">Inactif</span>
                                        <?php else: ?>
                                        <span class="badge badge-warning">A configurer</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modalConfig"
                                                onclick="openConfigModal(<?= htmlspecialchars(json_encode($log)) ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($hasConfig && $effPlancher && $effStandard): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="generate_one">
                                            <input type="hidden" name="logement_id" value="<?= $log['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success" title="Generer les prix">
                                                <i class="fas fa-magic"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Explication de la logique -->
            <div class="card mt-3">
                <div class="card-header"><h6><i class="fas fa-info-circle"></i> Logique de calcul des prix</h6></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Anticipation (jours avant)</h6>
                            <table class="table table-sm">
                                <tr><td>J0</td><td>Prix plancher</td></tr>
                                <tr><td>J1-3</td><td>Plancher + <?= $settings['palier_j1_3_pourcent'] ?? 20 ?>% de l'ecart</td></tr>
                                <tr><td>J4-13</td><td>Plancher + <?= $settings['palier_j4_13_pourcent'] ?? 40 ?>% de l'ecart</td></tr>
                                <tr><td>J14-30</td><td>Plancher + <?= $settings['palier_j14_30_pourcent'] ?? 60 ?>% de l'ecart</td></tr>
                                <tr><td>J31-60</td><td>Plancher + <?= $settings['palier_j31_60_pourcent'] ?? 80 ?>% de l'ecart</td></tr>
                                <tr><td>J60+</td><td>Prix standard</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Jours de la semaine</h6>
                            <table class="table table-sm">
                                <tr><td>Lundi - Jeudi</td><td>Prix normal</td></tr>
                                <tr><td>Vendredi - Samedi</td><td>+X% (weekend)</td></tr>
                                <tr><td>Dimanche</td><td>-Y€ (reduction)</td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglet Groupes -->
        <div class="tab-pane fade" id="tabGroups">
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-layer-group"></i> Groupes de logements</h5>
                            <button class="btn btn-sm btn-success" data-toggle="modal" data-target="#modalGroup" onclick="openGroupModal()">
                                <i class="fas fa-plus"></i> Nouveau groupe
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (count($groups) > 0): ?>
                            <table class="table table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Nom</th>
                                        <th>Logement reference</th>
                                        <th>Prix Plancher</th>
                                        <th>Prix Standard</th>
                                        <th>WE +%</th>
                                        <th>Dim -€</th>
                                        <th>Membres</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($groups as $grp): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($grp['nom']) ?></strong>
                                            <?php if ($grp['description']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($grp['description']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($grp['reference_name']): ?>
                                            <span class="badge badge-secondary"><?= htmlspecialchars($grp['reference_name']) ?></span>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $grp['prix_plancher'] ? number_format($grp['prix_plancher'], 0) . '€' : '-' ?></td>
                                        <td><?= $grp['prix_standard'] ? number_format($grp['prix_standard'], 0) . '€' : '-' ?></td>
                                        <td><?= $grp['weekend_pourcent'] ? '+' . number_format($grp['weekend_pourcent'], 0) . '%' : '-' ?></td>
                                        <td><?= $grp['dimanche_reduction'] ? '-' . number_format($grp['dimanche_reduction'], 0) . '€' : '-' ?></td>
                                        <td><span class="badge badge-info"><?= $grp['membre_count'] ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modalGroup"
                                                    onclick="openGroupModal(<?= htmlspecialchars(json_encode($grp)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce groupe?')">
                                                <input type="hidden" name="action" value="delete_group">
                                                <input type="hidden" name="group_id" value="<?= $grp['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <p class="text-center text-muted py-4">
                                Aucun groupe configure.<br>
                                <small>Creez un groupe pour regrouper vos logements et accelerer les mises a jour de prix.</small>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header"><h6><i class="fas fa-info-circle"></i> Comment utiliser les groupes?</h6></div>
                        <div class="card-body">
                            <ol class="small">
                                <li><strong>Creer un logement fictif</strong> sur Superhote (ex: "GROUPE1") sans reservations</li>
                                <li><strong>Creer un groupe</strong> ici et associer le logement fictif comme reference</li>
                                <li><strong>Associer vos logements</strong> au groupe dans l'onglet Logements</li>
                            </ol>
                            <hr>
                            <p class="small text-muted">
                                <strong>Avantage:</strong> Le worker utilisera le logement fictif (sans reservations) pour ouvrir
                                la modale de prix et appliquer le changement a tous les logements du groupe en une seule operation.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglet Parametres -->
        <div class="tab-pane fade" id="tabSettings">
            <div class="card">
                <div class="card-header"><h5><i class="fas fa-sliders-h"></i> Parametres globaux</h5></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_settings">

                        <h6>Paliers d'anticipation</h6>
                        <p class="text-muted small">Pourcentage de l'ecart (standard - plancher) a ajouter au prix plancher</p>

                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>J1-3 (%)</label>
                                    <div class="input-group">
                                        <input type="number" name="palier_j1_3_pourcent" class="form-control"
                                               value="<?= $settings['palier_j1_3_pourcent'] ?? 20 ?>" min="0" max="100">
                                        <div class="input-group-append"><span class="input-group-text">%</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>J4-13 (%)</label>
                                    <div class="input-group">
                                        <input type="number" name="palier_j4_13_pourcent" class="form-control"
                                               value="<?= $settings['palier_j4_13_pourcent'] ?? 40 ?>" min="0" max="100">
                                        <div class="input-group-append"><span class="input-group-text">%</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>J14-30 (%)</label>
                                    <div class="input-group">
                                        <input type="number" name="palier_j14_30_pourcent" class="form-control"
                                               value="<?= $settings['palier_j14_30_pourcent'] ?? 60 ?>" min="0" max="100">
                                        <div class="input-group-append"><span class="input-group-text">%</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>J31-60 (%)</label>
                                    <div class="input-group">
                                        <input type="number" name="palier_j31_60_pourcent" class="form-control"
                                               value="<?= $settings['palier_j31_60_pourcent'] ?? 80 ?>" min="0" max="100">
                                        <div class="input-group-append"><span class="input-group-text">%</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Jours a generer</label>
                                    <input type="number" name="jours_generation" class="form-control"
                                           value="<?= $settings['jours_generation'] ?? 90 ?>" min="7" max="120">
                                </div>
                            </div>
                        </div>

                        <hr>

                        <h6>Exemple de calcul</h6>
                        <div class="alert alert-light">
                            <strong>Logement:</strong> Plancher = 45€, Standard = 70€, Weekend = +10%, Dimanche = -5€<br>
                            <strong>Ecart:</strong> 70 - 45 = 25€<br><br>
                            <table class="table table-sm table-bordered mb-0">
                                <thead>
                                    <tr><th>Anticipation</th><th>Calcul</th><th>Semaine</th><th>Weekend</th><th>Dimanche</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>J0</td>
                                        <td>45€</td>
                                        <td>45€</td>
                                        <td>50€</td>
                                        <td>40€</td>
                                    </tr>
                                    <tr>
                                        <td>J1-3</td>
                                        <td>45 + <?= $settings['palier_j1_3_pourcent'] ?? 20 ?>% × 25</td>
                                        <td><?= 45 + (($settings['palier_j1_3_pourcent'] ?? 20)/100) * 25 ?>€</td>
                                        <td><?= round((45 + (($settings['palier_j1_3_pourcent'] ?? 20)/100) * 25) * 1.1) ?>€</td>
                                        <td><?= (45 + (($settings['palier_j1_3_pourcent'] ?? 20)/100) * 25) - 5 ?>€</td>
                                    </tr>
                                    <tr>
                                        <td>J4-13</td>
                                        <td>45 + <?= $settings['palier_j4_13_pourcent'] ?? 40 ?>% × 25</td>
                                        <td><?= 45 + (($settings['palier_j4_13_pourcent'] ?? 40)/100) * 25 ?>€</td>
                                        <td><?= round((45 + (($settings['palier_j4_13_pourcent'] ?? 40)/100) * 25) * 1.1) ?>€</td>
                                        <td><?= (45 + (($settings['palier_j4_13_pourcent'] ?? 40)/100) * 25) - 5 ?>€</td>
                                    </tr>
                                    <tr>
                                        <td>J14-30</td>
                                        <td>45 + <?= $settings['palier_j14_30_pourcent'] ?? 60 ?>% × 25</td>
                                        <td><?= 45 + (($settings['palier_j14_30_pourcent'] ?? 60)/100) * 25 ?>€</td>
                                        <td><?= round((45 + (($settings['palier_j14_30_pourcent'] ?? 60)/100) * 25) * 1.1) ?>€</td>
                                        <td><?= (45 + (($settings['palier_j14_30_pourcent'] ?? 60)/100) * 25) - 5 ?>€</td>
                                    </tr>
                                    <tr>
                                        <td>J31-60</td>
                                        <td>45 + <?= $settings['palier_j31_60_pourcent'] ?? 80 ?>% × 25</td>
                                        <td><?= 45 + (($settings['palier_j31_60_pourcent'] ?? 80)/100) * 25 ?>€</td>
                                        <td><?= round((45 + (($settings['palier_j31_60_pourcent'] ?? 80)/100) * 25) * 1.1) ?>€</td>
                                        <td><?= (45 + (($settings['palier_j31_60_pourcent'] ?? 80)/100) * 25) - 5 ?>€</td>
                                    </tr>
                                    <tr>
                                        <td>J60+</td>
                                        <td>70€</td>
                                        <td>70€</td>
                                        <td>77€</td>
                                        <td>65€</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Sauvegarder les parametres
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Onglet Regles avancees -->
        <div class="tab-pane fade" id="tabAdvanced">
            <div class="row">
                <!-- Saisons -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-sun"></i> Saisons</h5>
                        </div>
                        <div class="card-body">
                            <div class="custom-control custom-switch mb-3">
                                <input type="checkbox" class="custom-control-input" id="saisonsEnabled"
                                       <?= ($settings['saisons_enabled'] ?? '1') === '1' ? 'checked' : '' ?>
                                       onchange="toggleSetting('saisons_enabled', this.checked)">
                                <label class="custom-control-label" for="saisonsEnabled">Activer les regles saisonnieres</label>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Saison</th>
                                            <th>Periode</th>
                                            <th>Type</th>
                                            <th>Ajustement</th>
                                            <th>Actif</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($seasons)): ?>
                                        <tr><td colspan="5" class="text-muted text-center">
                                            <em>Executez la migration 006 pour creer les tables</em>
                                        </td></tr>
                                        <?php else: ?>
                                        <?php foreach ($seasons as $season): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($season['nom']) ?></strong></td>
                                            <td><?= date('d/m', strtotime($season['date_debut'])) ?> - <?= date('d/m', strtotime($season['date_fin'])) ?></td>
                                            <td>
                                                <?php if ($season['type_saison'] === 'haute'): ?>
                                                <span class="badge badge-danger">Haute</span>
                                                <?php elseif ($season['type_saison'] === 'basse'): ?>
                                                <span class="badge badge-success">Basse</span>
                                                <?php else: ?>
                                                <span class="badge badge-warning">Moyenne</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($season['majoration_pourcent'] > 0): ?>
                                                <span class="text-danger">+<?= $season['majoration_pourcent'] ?>%</span>
                                                <?php elseif ($season['reduction_pourcent'] > 0): ?>
                                                <span class="text-success">-<?= $season['reduction_pourcent'] ?>%</span>
                                                <?php else: ?>
                                                <span class="text-muted">0%</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input"
                                                           id="season_<?= $season['id'] ?>"
                                                           <?= $season['is_active'] ? 'checked' : '' ?>
                                                           onchange="toggleSeason(<?= $season['id'] ?>, this.checked)">
                                                    <label class="custom-control-label" for="season_<?= $season['id'] ?>"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Jours feries -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-calendar-day"></i> Jours feries</h5>
                        </div>
                        <div class="card-body">
                            <div class="custom-control custom-switch mb-3">
                                <input type="checkbox" class="custom-control-input" id="holidaysEnabled"
                                       <?= ($settings['holidays_enabled'] ?? '1') === '1' ? 'checked' : '' ?>
                                       onchange="toggleSetting('holidays_enabled', this.checked)">
                                <label class="custom-control-label" for="holidaysEnabled">Activer les majorations jours feries</label>
                            </div>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Jour ferie</th>
                                            <th>Date</th>
                                            <th>Majoration</th>
                                            <th>Actif</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($holidays)): ?>
                                        <tr><td colspan="4" class="text-muted text-center">
                                            <em>Executez la migration 006 pour creer les tables</em>
                                        </td></tr>
                                        <?php else: ?>
                                        <?php foreach ($holidays as $holiday): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($holiday['nom']) ?></td>
                                            <td><?= date('d/m', strtotime($holiday['date_ferie'])) ?>
                                                <?php if ($holiday['jours_autour'] > 0): ?>
                                                <small class="text-muted">(±<?= $holiday['jours_autour'] ?>j)</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="text-danger">+<?= $holiday['majoration_pourcent'] ?>%</span></td>
                                            <td>
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input"
                                                           id="holiday_<?= $holiday['id'] ?>"
                                                           <?= $holiday['is_active'] ? 'checked' : '' ?>
                                                           onchange="toggleHoliday(<?= $holiday['id'] ?>, this.checked)">
                                                    <label class="custom-control-label" for="holiday_<?= $holiday['id'] ?>"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Regles d'occupation -->
            <div class="card mb-4">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="fas fa-percentage"></i> Regles d'occupation</h5>
                </div>
                <div class="card-body">
                    <div class="custom-control custom-switch mb-3">
                        <input type="checkbox" class="custom-control-input" id="occupancyEnabled"
                               <?= ($settings['occupancy_enabled'] ?? '1') === '1' ? 'checked' : '' ?>
                               onchange="toggleSetting('occupancy_enabled', this.checked)">
                        <label class="custom-control-label" for="occupancyEnabled">Activer l'ajustement selon le taux d'occupation</label>
                    </div>
                    <p class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        Le taux d'occupation est calcule sur les <?= $settings['occupancy_calculation_days'] ?? 14 ?> prochains jours.
                        Si l'occupation est faible, les prix baissent pour attirer des reservations.
                    </p>
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="thead-light">
                                <tr>
                                    <th>Regle</th>
                                    <th>Seuil occupation</th>
                                    <th>Ajustement</th>
                                    <th>Actif</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($occupancyRules)): ?>
                                <tr><td colspan="4" class="text-muted text-center">
                                    <em>Executez la migration 006 pour creer les tables</em>
                                </td></tr>
                                <?php else: ?>
                                <?php foreach ($occupancyRules as $rule): ?>
                                <tr>
                                    <td><?= htmlspecialchars($rule['nom']) ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar <?= $rule['ajustement_pourcent'] < 0 ? 'bg-success' : ($rule['ajustement_pourcent'] > 0 ? 'bg-danger' : 'bg-secondary') ?>"
                                                 style="width: <?= $rule['seuil_occupation_max'] ?>%;">
                                                <?= $rule['seuil_occupation_min'] ?>% - <?= $rule['seuil_occupation_max'] ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($rule['ajustement_pourcent'] > 0): ?>
                                        <span class="badge badge-danger">+<?= $rule['ajustement_pourcent'] ?>%</span>
                                        <?php elseif ($rule['ajustement_pourcent'] < 0): ?>
                                        <span class="badge badge-success"><?= $rule['ajustement_pourcent'] ?>%</span>
                                        <?php else: ?>
                                        <span class="badge badge-secondary">0%</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input"
                                                   id="occupancy_<?= $rule['id'] ?>"
                                                   <?= $rule['is_active'] ? 'checked' : '' ?>
                                                   onchange="toggleOccupancy(<?= $rule['id'] ?>, this.checked)">
                                            <label class="custom-control-label" for="occupancy_<?= $rule['id'] ?>"></label>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Resume formule -->
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-calculator"></i> Formule de calcul complete</h5>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded"><code>Prix final = Prix base (anticipation)
           × (1 + weekend%)           // Si vendredi/samedi
           - reduction dimanche       // Si dimanche
           × (1 + saison%)            // Si haute saison
           × (1 - saison%)            // Si basse saison
           × (1 + ferie%)             // Si jour ferie
           × (1 + occupation%)        // Selon taux occupation</code></pre>
                    <p class="text-muted mb-0">
                        <i class="fas fa-lightbulb"></i> Les regles sont cumulatives. Un samedi en haute saison pendant un jour ferie
                        avec une occupation faible appliquera tous les ajustements.
                    </p>
                </div>
            </div>
        </div>

        <!-- Onglet File d'attente -->
        <div class="tab-pane fade" id="tabQueue">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>File d'attente (<?= count($pendingUpdates) ?> en attente)</h5>
                    <?php if (count($pendingUpdates) > 0): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Vider toute la file d\'attente?')">
                        <input type="hidden" name="action" value="clear_pending">
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-trash"></i> Vider
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (count($pendingUpdates) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Logement</th>
                                    <th>Date</th>
                                    <th>Prix</th>
                                    <th>Palier</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingUpdates as $up): ?>
                                <tr>
                                    <td><?= htmlspecialchars($up['nom_du_logement'] ?? '-') ?></td>
                                    <td><?= date('d/m/Y (D)', strtotime($up['date_start'])) ?></td>
                                    <td><strong><?= number_format($up['price'], 0) ?>€</strong></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($up['rule_name'] ?? '-') ?></small></td>
                                    <td>
                                        <span class="badge badge-<?= $up['status'] == 'processing' ? 'info' : 'warning' ?>">
                                            <?= $up['status'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted py-4">Aucune mise a jour en attente</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Onglet Workers/Planification -->
        <div class="tab-pane fade" id="tabDaemon">
            <!-- Alerte si credentials non configures -->
            <?php if (!$superhoteCredentials['exists'] || empty($superhoteCredentials['email'])): ?>
            <div class="alert alert-warning mb-3">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Configuration requise:</strong> Les identifiants Superhote ne sont pas configures.
                Le yield management ne pourra pas fonctionner sans ces identifiants.
            </div>
            <?php endif; ?>

            <!-- Carte Credentials Superhote -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-key"></i> Identifiants Superhote</h5>
                    <?php if ($superhoteCredentials['exists'] && !empty($superhoteCredentials['email'])): ?>
                    <span class="badge badge-success"><i class="fas fa-check"></i> Configure</span>
                    <?php else: ?>
                    <span class="badge badge-warning"><i class="fas fa-times"></i> Non configure</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_credentials">

                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i> Email Superhote</label>
                                    <input type="email" name="superhote_email" class="form-control"
                                           value="<?= htmlspecialchars($superhoteCredentials['email']) ?>"
                                           placeholder="votre_email@example.com" required>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label><i class="fas fa-lock"></i> Mot de passe Superhote</label>
                                    <input type="password" name="superhote_password" class="form-control"
                                           value="<?= htmlspecialchars($superhoteCredentials['password']) ?>"
                                           placeholder="********" required>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="form-group w-100">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-save"></i> Sauvegarder
                                    </button>
                                </div>
                            </div>
                        </div>

                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            Ces identifiants sont utilises par le robot Selenium pour se connecter a Superhote et modifier les prix automatiquement.
                        </small>
                    </form>
                </div>
            </div>

            <div class="row">
                <!-- Colonne gauche: Planification -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-clock"></i> Mise a jour planifiee</h5>
                            <span id="scheduleStatusBadge" class="badge badge-<?= ($settings['scheduled_enabled'] ?? '1') == '1' ? 'success' : 'secondary' ?>">
                                <?= ($settings['scheduled_enabled'] ?? '1') == '1' ? 'Active' : 'Desactivee' ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="save_schedule">

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Mode planifie:</strong> Les prix sont generes et appliques automatiquement
                                    chaque jour aux heures configurees. Plus stable que le daemon permanent.
                                </div>

                                <div class="form-group">
                                    <label><i class="fas fa-clock"></i> Heures d'execution (plusieurs possibles)</label>
                                    <div id="scheduledTimesContainer">
                                        <?php
                                        $scheduledTimes = explode(',', $settings['scheduled_times'] ?? $settings['scheduled_time'] ?? '07:00');
                                        foreach ($scheduledTimes as $idx => $time):
                                            $time = trim($time);
                                            if (empty($time)) continue;
                                        ?>
                                        <div class="input-group mb-2 scheduled-time-row">
                                            <input type="time" name="scheduled_times[]" class="form-control" value="<?= htmlspecialchars($time) ?>">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-danger remove-time-btn" onclick="removeTimeRow(this)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addTimeRow()">
                                        <i class="fas fa-plus"></i> Ajouter une heure
                                    </button>
                                    <small class="form-text text-muted">Ex: 7h00, 12h00, 19h00 pour 3 mises a jour par jour</small>
                                </div>

                                <div class="form-row align-items-end">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><i class="fas fa-users-cog"></i> Workers paralleles</label>
                                            <input type="number" name="max_workers" class="form-control"
                                                   value="<?= intval($settings['max_workers'] ?? 2) ?>" min="1" max="5">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <div class="custom-control custom-switch mt-4">
                                                <input type="checkbox" class="custom-control-input" name="scheduled_enabled"
                                                       id="scheduled_enabled" <?= ($settings['scheduled_enabled'] ?? '1') == '1' ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="scheduled_enabled">Activer la planification</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Sauvegarder la planification
                                </button>
                            </form>

                            <hr>

                            <h6><i class="fas fa-play-circle"></i> Execution manuelle</h6>
                            <p class="text-muted small">Lancer une mise a jour immediate sans attendre l'heure planifiee.</p>

                            <form method="POST" onsubmit="return confirm('Lancer la mise a jour maintenant?')">
                                <input type="hidden" name="action" value="run_now">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-rocket"></i> Lancer maintenant
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="refreshScheduleStatus()">
                                    <i class="fas fa-sync"></i> Actualiser
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Derniere execution -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5><i class="fas fa-history"></i> Derniere execution</h5>
                        </div>
                        <div class="card-body">
                            <div id="lastRunInfo">
                                <p class="text-muted">Chargement...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Colonne droite: Stats et logs -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-bar"></i> Queue Stats (24h)</h5>
                        </div>
                        <div class="card-body">
                            <div id="queueStats">
                                <p class="text-muted">Chargement...</p>
                            </div>

                            <hr>

                            <button class="btn btn-sm btn-outline-warning" onclick="releaseStuckTasks()">
                                <i class="fas fa-unlock"></i> Liberer taches bloquees
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="clearQueue()">
                                <i class="fas fa-trash"></i> Vider la queue
                            </button>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between">
                            <h5><i class="fas fa-terminal"></i> Logs recents</h5>
                            <button class="btn btn-sm btn-outline-secondary" onclick="refreshLogs()">
                                <i class="fas fa-sync"></i>
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <pre id="daemonLogs" class="bg-dark text-light p-2 m-0" style="max-height:300px;overflow-y:auto;font-size:0.75rem;">Chargement...</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Modal Configuration -->
<div class="modal fade" id="modalConfig" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="save_config">
                <input type="hidden" name="logement_id" id="cfg_logement_id">

                <div class="modal-header">
                    <h5 class="modal-title">Configuration: <span id="cfg_logement_name"></span></h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>ID Superhote *</label>
                        <input type="text" name="superhote_property_id" id="cfg_superhote_id" class="form-control" required>
                        <small class="text-muted">Nom exact sur Superhote (ex: Delphin - ZEN - 1)</small>
                    </div>

                    <div class="form-group">
                        <label>Nom affiche</label>
                        <input type="text" name="superhote_property_name" id="cfg_superhote_name" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Groupe</label>
                        <select name="groupe" id="cfg_groupe" class="form-control">
                            <option value="">-- Aucun groupe --</option>
                            <?php foreach ($groups as $grp): ?>
                            <option value="<?= htmlspecialchars($grp['nom']) ?>"><?= htmlspecialchars($grp['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Associer a un groupe pour les mises a jour en lot</small>
                    </div>

                    <hr>
                    <h6>Tarification</h6>

                    <div class="alert alert-info" id="cfg_groupe_info" style="display:none;">
                        <i class="fas fa-info-circle"></i>
                        <strong>Prix herites du groupe:</strong> <span id="cfg_groupe_prix_info"></span>
                        <br><small>Modifiez les prix dans l'onglet Groupes pour les changer.</small>
                    </div>

                    <div id="cfg_tarification_fields">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label>Prix Plancher (J0)</label>
                                    <div class="input-group">
                                        <input type="number" name="prix_plancher" id="cfg_prix_plancher" class="form-control" min="0">
                                        <div class="input-group-append"><span class="input-group-text">€</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label>Prix Standard (J14+)</label>
                                    <div class="input-group">
                                        <input type="number" name="prix_standard" id="cfg_prix_standard" class="form-control" min="0">
                                        <div class="input-group-append"><span class="input-group-text">€</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label>Majoration Weekend</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text">+</span></div>
                                        <input type="number" name="weekend_pourcent" id="cfg_weekend_pourcent" class="form-control" value="10" min="0" max="100">
                                        <div class="input-group-append"><span class="input-group-text">%</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label>Reduction Dimanche</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text">-</span></div>
                                        <input type="number" name="dimanche_reduction" id="cfg_dimanche_reduction" class="form-control" value="5" min="0">
                                        <div class="input-group-append"><span class="input-group-text">€</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="is_active" id="cfg_is_active" checked>
                        <label class="custom-control-label" for="cfg_is_active">Actif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Sauvegarder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Groupe -->
<div class="modal fade" id="modalGroup" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="save_group">
                <input type="hidden" name="group_id" id="grp_id">

                <div class="modal-header">
                    <h5 class="modal-title" id="grp_modal_title">Nouveau groupe</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nom du groupe *</label>
                                <input type="text" name="group_nom" id="grp_nom" class="form-control" required>
                                <small class="text-muted">Ex: GROUPE1, GROUPE_DELPHIN</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Description</label>
                                <input type="text" name="group_description" id="grp_description" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Logement de reference</label>
                        <select name="group_reference_id" id="grp_reference_id" class="form-control">
                            <option value="">-- Selectionner --</option>
                            <?php foreach ($logements as $log): ?>
                            <option value="<?= $log['id'] ?>"><?= htmlspecialchars($log['nom_du_logement'] ?? 'N/A') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Logement fictif utilise pour ouvrir les modales (sans reservations)</small>
                    </div>

                    <hr>
                    <h6><i class="fas fa-euro-sign"></i> Tarification par defaut du groupe</h6>
                    <p class="text-muted small">Ces valeurs seront automatiquement appliquees aux logements lors de leur assignation au groupe.</p>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Prix Plancher (J0)</label>
                                <div class="input-group">
                                    <input type="number" name="group_prix_plancher" id="grp_prix_plancher" class="form-control" min="0">
                                    <div class="input-group-append"><span class="input-group-text">€</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Prix Standard (J14+)</label>
                                <div class="input-group">
                                    <input type="number" name="group_prix_standard" id="grp_prix_standard" class="form-control" min="0">
                                    <div class="input-group-append"><span class="input-group-text">€</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Majoration Weekend</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">+</span></div>
                                    <input type="number" name="group_weekend_pourcent" id="grp_weekend_pourcent" class="form-control" value="10" min="0" max="100">
                                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Reduction Dimanche</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">-</span></div>
                                    <input type="number" name="group_dimanche_reduction" id="grp_dimanche_reduction" class="form-control" value="5" min="0">
                                    <div class="input-group-append"><span class="input-group-text">€</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Sauvegarder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Donnees des groupes pour l'auto-remplissage
var groupsData = <?= json_encode(array_column($groups, null, 'nom')) ?>;

// ============================================================================
// Fonctions pour la gestion des heures de planification
// ============================================================================

function addTimeRow() {
    var container = document.getElementById('scheduledTimesContainer');
    var div = document.createElement('div');
    div.className = 'input-group mb-2 scheduled-time-row';
    div.innerHTML = '<input type="time" name="scheduled_times[]" class="form-control" value="12:00">' +
                    '<div class="input-group-append">' +
                    '<button type="button" class="btn btn-outline-danger remove-time-btn" onclick="removeTimeRow(this)">' +
                    '<i class="fas fa-times"></i></button></div>';
    container.appendChild(div);
}

function removeTimeRow(btn) {
    var container = document.getElementById('scheduledTimesContainer');
    var rows = container.querySelectorAll('.scheduled-time-row');
    if (rows.length > 1) {
        btn.closest('.scheduled-time-row').remove();
    } else {
        alert('Il faut au moins une heure de planification');
    }
}

// ============================================================================
// Fonctions pour les regles avancees (saisons, feries, occupation)
// ============================================================================

function toggleSetting(key, enabled) {
    fetch('?ajax=toggle_setting', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'key=' + encodeURIComponent(key) + '&value=' + (enabled ? '1' : '0')
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('Erreur: ' + (data.error || 'Impossible de modifier le parametre'));
        }
    })
    .catch(err => console.error('Erreur:', err));
}

function toggleSeason(id, enabled) {
    fetch('?ajax=toggle_season', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id + '&active=' + (enabled ? '1' : '0')
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('Erreur: ' + (data.error || 'Impossible de modifier la saison'));
        }
    })
    .catch(err => console.error('Erreur:', err));
}

function toggleHoliday(id, enabled) {
    fetch('?ajax=toggle_holiday', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id + '&active=' + (enabled ? '1' : '0')
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('Erreur: ' + (data.error || 'Impossible de modifier le jour ferie'));
        }
    })
    .catch(err => console.error('Erreur:', err));
}

function toggleOccupancy(id, enabled) {
    fetch('?ajax=toggle_occupancy', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id + '&active=' + (enabled ? '1' : '0')
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('Erreur: ' + (data.error || 'Impossible de modifier la regle'));
        }
    })
    .catch(err => console.error('Erreur:', err));
}

// ============================================================================

// Fonction pour mettre a jour l'affichage des champs de tarification selon le groupe
function updateTarificationDisplay(groupNom) {
    var infoDiv = document.getElementById('cfg_groupe_info');
    var fieldsDiv = document.getElementById('cfg_tarification_fields');
    var priceInfoSpan = document.getElementById('cfg_groupe_prix_info');

    if (groupNom && groupsData[groupNom] && groupsData[groupNom].prix_plancher) {
        var group = groupsData[groupNom];
        // Groupe avec prix definis: afficher info, masquer champs
        infoDiv.style.display = 'block';
        fieldsDiv.style.display = 'none';
        priceInfoSpan.innerHTML = group.prix_plancher + '€ (plancher) / ' + group.prix_standard + '€ (standard) / +' +
            (group.weekend_pourcent || 10) + '% WE / -' + (group.dimanche_reduction || 5) + '€ dim';
    } else {
        // Pas de groupe ou groupe sans prix: masquer info, afficher champs
        infoDiv.style.display = 'none';
        fieldsDiv.style.display = 'block';
    }
}

function openConfigModal(logement) {
    document.getElementById('cfg_logement_id').value = logement.id;
    document.getElementById('cfg_logement_name').textContent = logement.nom_du_logement || 'Logement';
    document.getElementById('cfg_superhote_id').value = logement.superhote_property_id || logement.nom_du_logement || '';
    document.getElementById('cfg_superhote_name').value = logement.superhote_property_name || '';
    document.getElementById('cfg_prix_plancher').value = logement.prix_plancher || '';
    document.getElementById('cfg_prix_standard').value = logement.prix_standard || '';
    document.getElementById('cfg_weekend_pourcent').value = logement.weekend_pourcent || 10;
    document.getElementById('cfg_dimanche_reduction').value = logement.dimanche_reduction || 5;
    document.getElementById('cfg_groupe').value = logement.groupe || '';
    document.getElementById('cfg_is_active').checked = logement.superhote_active != 0;

    // Mettre a jour l'affichage selon le groupe
    updateTarificationDisplay(logement.groupe);
}

function openGroupModal(group) {
    if (group) {
        document.getElementById('grp_modal_title').textContent = 'Modifier le groupe';
        document.getElementById('grp_id').value = group.id;
        document.getElementById('grp_nom').value = group.nom || '';
        document.getElementById('grp_description').value = group.description || '';
        document.getElementById('grp_reference_id').value = group.logement_reference_id || '';
        document.getElementById('grp_prix_plancher').value = group.prix_plancher || '';
        document.getElementById('grp_prix_standard').value = group.prix_standard || '';
        document.getElementById('grp_weekend_pourcent').value = group.weekend_pourcent || 10;
        document.getElementById('grp_dimanche_reduction').value = group.dimanche_reduction || 5;
    } else {
        document.getElementById('grp_modal_title').textContent = 'Nouveau groupe';
        document.getElementById('grp_id').value = '';
        document.getElementById('grp_nom').value = '';
        document.getElementById('grp_description').value = '';
        document.getElementById('grp_reference_id').value = '';
        document.getElementById('grp_prix_plancher').value = '';
        document.getElementById('grp_prix_standard').value = '';
        document.getElementById('grp_weekend_pourcent').value = 10;
        document.getElementById('grp_dimanche_reduction').value = 5;
    }
}

// Mise a jour de l'affichage quand on change de groupe
document.getElementById('cfg_groupe').addEventListener('change', function() {
    var groupNom = this.value;
    updateTarificationDisplay(groupNom);
});

// ============================================================
// SCHEDULE & QUEUE CONTROLS
// ============================================================

function refreshScheduleStatus() {
    fetch('daemon_api.php?action=schedule_status')
        .then(r => r.json())
        .then(data => {
            // Derniere execution
            var lastRun = data.last_run || {};
            var infoHtml = '';

            if (lastRun.status === 'never_run') {
                infoHtml = '<p class="text-muted"><i class="fas fa-clock"></i> Jamais execute</p>';
            } else if (lastRun.status === 'running') {
                infoHtml = '<div class="alert alert-info mb-0">';
                infoHtml += '<i class="fas fa-spinner fa-spin"></i> <strong>En cours...</strong>';
                infoHtml += '<br><small>Demarre: ' + lastRun.started_at + '</small>';
                infoHtml += '</div>';
            } else if (lastRun.status === 'completed') {
                var steps = lastRun.steps || {};
                infoHtml = '<div class="alert alert-success mb-2">';
                infoHtml += '<i class="fas fa-check-circle"></i> <strong>Termine</strong>';
                infoHtml += '<br><small>' + (lastRun.ended_at || lastRun.updated_at) + '</small>';
                infoHtml += '</div>';
                infoHtml += '<table class="table table-sm mb-0">';
                if (steps.generate) {
                    infoHtml += '<tr><td>Logements</td><td>' + (steps.generate.logements || 0) + '</td></tr>';
                    infoHtml += '<tr><td>Mises a jour</td><td>' + (steps.generate.updates || 0) + '</td></tr>';
                }
                if (lastRun.duration_seconds) {
                    infoHtml += '<tr><td>Duree</td><td>' + Math.round(lastRun.duration_seconds) + 's</td></tr>';
                }
                if (steps.final_stats) {
                    var stats = steps.final_stats;
                    infoHtml += '<tr><td>Succes</td><td><span class="badge badge-success">' + (stats.completed || 0) + '</span></td></tr>';
                    infoHtml += '<tr><td>Echecs</td><td><span class="badge badge-danger">' + (stats.failed || 0) + '</span></td></tr>';
                }
                infoHtml += '</table>';
            } else if (lastRun.status === 'error') {
                infoHtml = '<div class="alert alert-danger mb-0">';
                infoHtml += '<i class="fas fa-exclamation-triangle"></i> <strong>Erreur</strong>';
                infoHtml += '<br><small>' + (lastRun.error || 'Erreur inconnue') + '</small>';
                infoHtml += '</div>';
            }

            document.getElementById('lastRunInfo').innerHTML = infoHtml;

            // Stats queue
            var queue = data.queue || {};
            var statsHtml = '<table class="table table-sm mb-0">';
            statsHtml += '<tr><td>En attente</td><td><span class="badge badge-warning">' + (queue.pending || 0) + '</span></td></tr>';
            statsHtml += '<tr><td>En cours</td><td><span class="badge badge-info">' + (queue.processing || 0) + '</span></td></tr>';
            statsHtml += '<tr><td>Terminees</td><td><span class="badge badge-success">' + (queue.completed || 0) + '</span></td></tr>';
            statsHtml += '<tr><td>Echouees</td><td><span class="badge badge-danger">' + (queue.failed || 0) + '</span></td></tr>';
            statsHtml += '</table>';
            document.getElementById('queueStats').innerHTML = statsHtml;
        })
        .catch(err => {
            console.error('Erreur:', err);
            document.getElementById('lastRunInfo').innerHTML = '<p class="text-danger">Erreur de connexion</p>';
        });
}

function refreshLogs() {
    fetch('daemon_api.php?action=logs')
        .then(r => r.json())
        .then(data => {
            var logs = data.logs || [];
            document.getElementById('daemonLogs').textContent = logs.slice(-50).join('\n') || 'Aucun log';
            var logsDiv = document.getElementById('daemonLogs');
            logsDiv.scrollTop = logsDiv.scrollHeight;
        })
        .catch(err => {
            document.getElementById('daemonLogs').textContent = 'Erreur: ' + err;
        });
}

function releaseStuckTasks() {
    fetch('daemon_api.php?action=release', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            alert('Libere: ' + (data.released || 0) + ' taches');
            refreshScheduleStatus();
        });
}

function clearQueue() {
    if (!confirm('Vider toute la queue pending?')) return;

    fetch('daemon_api.php?action=clear', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            alert('Supprime: ' + (data.deleted || 0) + ' taches');
            refreshScheduleStatus();
            location.reload();
        });
}

// Charger le statut au clic sur l'onglet
document.getElementById('daemonTab').addEventListener('click', function() {
    refreshScheduleStatus();
    refreshLogs();
});

// Refresh auto toutes les 10 secondes si l'onglet est actif
setInterval(function() {
    if (document.getElementById('tabDaemon').classList.contains('active')) {
        refreshScheduleStatus();
    }
}, 10000);

// Refresh logs toutes les 30 secondes
setInterval(function() {
    if (document.getElementById('tabDaemon').classList.contains('active')) {
        refreshLogs();
    }
}, 30000);
</script>

<?php require_once __DIR__ . '/../includes/footer_minimal.php'; ?>
