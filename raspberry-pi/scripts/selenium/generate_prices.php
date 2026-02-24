#!/usr/bin/env php
<?php
/**
 * Script CLI pour generer les prix Superhote
 * Usage: php generate_prices.php [--all|--logement-id=X]
 */

// Configuration
$dbHost = 'localhost';
$dbName = 'sms_db';
$dbUser = 'sms_user';
$dbPass = 'password123';

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Erreur de connexion BDD: " . $e->getMessage() . "\n");
    exit(1);
}

// Recuperer les parametres globaux
function getSettings($pdo) {
    $settings = [];
    $rows = $pdo->query("SELECT key_name, value FROM superhote_settings")->fetchAll();
    foreach ($rows as $row) {
        $settings[$row['key_name']] = $row['value'];
    }
    return $settings;
}

// Recuperer les saisons actives
function getSeasons($pdo) {
    try {
        return $pdo->query("SELECT * FROM superhote_seasons WHERE is_active = 1 ORDER BY priorite DESC")->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Recuperer les jours feries actifs
function getHolidays($pdo) {
    try {
        return $pdo->query("SELECT * FROM superhote_holidays WHERE is_active = 1")->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Recuperer les regles d'occupation
function getOccupancyRules($pdo) {
    try {
        return $pdo->query("SELECT * FROM superhote_occupancy_rules WHERE is_active = 1 ORDER BY seuil_occupation_min ASC")->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Calculer le taux d'occupation pour un logement
function getOccupancyRate($pdo, $logementId, $days = 14) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT DATE(d.date)) as jours_occupes
            FROM (
                SELECT DATE_ADD(CURDATE(), INTERVAL n DAY) as date
                FROM (
                    SELECT a.N + b.N * 10 as n
                    FROM (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
                         (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3) b
                ) numbers
                WHERE n < ?
            ) d
            INNER JOIN reservation r ON d.date >= r.date_arrivee AND d.date < r.date_depart
            WHERE r.logement_id = ? AND r.statut != 'annulee'
        ");
        $stmt->execute([$days, $logementId]);
        $result = $stmt->fetch();
        $joursOccupes = intval($result['jours_occupes'] ?? 0);
        return round(($joursOccupes / $days) * 100, 2);
    } catch (PDOException $e) {
        return 50; // Valeur par defaut si erreur
    }
}

// Verifier si une date est dans une saison
function getSeasonAdjustment($date, $seasons) {
    $month = intval($date->format('m'));
    $day = intval($date->format('d'));
    $dateCompare = sprintf('2000-%02d-%02d', $month, $day);

    foreach ($seasons as $season) {
        $debut = $season['date_debut'];
        $fin = $season['date_fin'];

        // Gerer le cas ou la saison chevauche le nouvel an
        if ($debut > $fin) {
            // Ex: 2000-12-20 a 2000-01-05
            if ($dateCompare >= $debut || $dateCompare <= $fin) {
                return [
                    'nom' => $season['nom'],
                    'type' => $season['type_saison'],
                    'majoration' => floatval($season['majoration_pourcent']),
                    'reduction' => floatval($season['reduction_pourcent'])
                ];
            }
        } else {
            if ($dateCompare >= $debut && $dateCompare <= $fin) {
                return [
                    'nom' => $season['nom'],
                    'type' => $season['type_saison'],
                    'majoration' => floatval($season['majoration_pourcent']),
                    'reduction' => floatval($season['reduction_pourcent'])
                ];
            }
        }
    }
    return null;
}

// Verifier si une date est un jour ferie (ou proche)
function getHolidayAdjustment($date, $holidays) {
    $month = intval($date->format('m'));
    $day = intval($date->format('d'));
    $year = intval($date->format('Y'));

    foreach ($holidays as $holiday) {
        $ferieMonth = intval(substr($holiday['date_ferie'], 5, 2));
        $ferieDay = intval(substr($holiday['date_ferie'], 8, 2));
        $joursAutour = intval($holiday['jours_autour']);

        // Creer la date du ferie pour cette annee
        $ferieDate = new DateTime("$year-$ferieMonth-$ferieDay");

        // Verifier si la date est le ferie ou dans les jours autour
        $diff = intval($date->diff($ferieDate)->format('%r%a'));

        if (abs($diff) <= $joursAutour) {
            return [
                'nom' => $holiday['nom'],
                'majoration' => floatval($holiday['majoration_pourcent']),
                'is_exact' => ($diff == 0)
            ];
        }
    }
    return null;
}

// Obtenir l'ajustement selon le taux d'occupation
function getOccupancyAdjustment($occupancyRate, $occupancyRules) {
    foreach ($occupancyRules as $rule) {
        if ($occupancyRate >= floatval($rule['seuil_occupation_min']) &&
            $occupancyRate < floatval($rule['seuil_occupation_max'])) {
            return [
                'nom' => $rule['nom'],
                'ajustement' => floatval($rule['ajustement_pourcent'])
            ];
        }
    }
    return null;
}

// Calculer le prix selon l'anticipation et les nouvelles regles
function calculatePrice($prixPlancher, $prixStandard, $joursAvant, $jourSemaine, $weekendPourcent, $dimancheReduction, $settings, $date = null, $seasons = [], $holidays = [], $occupancyRate = null, $occupancyRules = []) {
    $palierJ1_3 = floatval($settings['palier_j1_3_pourcent'] ?? 25) / 100;
    $palierJ4_6 = floatval($settings['palier_j4_6_pourcent'] ?? 50) / 100;
    $palierJ7_13 = floatval($settings['palier_j7_13_pourcent'] ?? 75) / 100;

    $ecart = $prixStandard - $prixPlancher;
    $details = [];

    // 1. Prix de base selon anticipation
    if ($joursAvant == 0) {
        $prix = $prixPlancher;
        $palier = 'J0';
    } elseif ($joursAvant <= 3) {
        $prix = $prixPlancher + ($ecart * $palierJ1_3);
        $palier = 'J1-3';
    } elseif ($joursAvant <= 6) {
        $prix = $prixPlancher + ($ecart * $palierJ4_6);
        $palier = 'J4-6';
    } elseif ($joursAvant <= 13) {
        $prix = $prixPlancher + ($ecart * $palierJ7_13);
        $palier = 'J7-13';
    } else {
        $prix = $prixStandard;
        $palier = 'J14+';
    }

    // 2. Majoration weekend (vendredi=5, samedi=6)
    if ($jourSemaine == 5 || $jourSemaine == 6) {
        $prix = $prix * (1 + $weekendPourcent / 100);
        $palier .= ' +WE';
    }
    // Reduction dimanche (dimanche=0)
    elseif ($jourSemaine == 0) {
        $prix = $prix - $dimancheReduction;
        $palier .= ' -Dim';
    }

    // 3. Ajustement saisonnier (si active)
    $saisonsEnabled = ($settings['saisons_enabled'] ?? '1') === '1';
    if ($saisonsEnabled && $date && !empty($seasons)) {
        $seasonAdj = getSeasonAdjustment($date, $seasons);
        if ($seasonAdj) {
            if ($seasonAdj['majoration'] > 0) {
                $prix = $prix * (1 + $seasonAdj['majoration'] / 100);
                $palier .= ' +' . substr($seasonAdj['type'], 0, 1) . 's';
            } elseif ($seasonAdj['reduction'] > 0) {
                $prix = $prix * (1 - $seasonAdj['reduction'] / 100);
                $palier .= ' -bs';
            }
        }
    }

    // 4. Ajustement jours feries (si active)
    $holidaysEnabled = ($settings['holidays_enabled'] ?? '1') === '1';
    if ($holidaysEnabled && $date && !empty($holidays)) {
        $holidayAdj = getHolidayAdjustment($date, $holidays);
        if ($holidayAdj) {
            $prix = $prix * (1 + $holidayAdj['majoration'] / 100);
            $palier .= ' +JF';
        }
    }

    // 5. Ajustement selon occupation (si active)
    $occupancyEnabled = ($settings['occupancy_enabled'] ?? '1') === '1';
    if ($occupancyEnabled && $occupancyRate !== null && !empty($occupancyRules)) {
        $occAdj = getOccupancyAdjustment($occupancyRate, $occupancyRules);
        if ($occAdj && $occAdj['ajustement'] != 0) {
            $prix = $prix * (1 + $occAdj['ajustement'] / 100);
            if ($occAdj['ajustement'] > 0) {
                $palier .= ' +occ';
            } else {
                $palier .= ' -occ';
            }
        }
    }

    return ['prix' => round($prix, 0), 'palier' => $palier];
}

// Generer les prix pour un logement
function generatePricesForLogement($pdo, $logementId, $settings, $seasons = null, $holidays = null, $occupancyRules = null) {
    $joursGeneration = intval($settings['jours_generation'] ?? 30);

    // Charger les donnees de regles si non fournies
    if ($seasons === null) $seasons = getSeasons($pdo);
    if ($holidays === null) $holidays = getHolidays($pdo);
    if ($occupancyRules === null) $occupancyRules = getOccupancyRules($pdo);

    // Recuperer config du logement avec info groupe
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

    // Verifier qu'on a les prix necessaires
    if (empty($prixPlancher) || empty($prixStandard)) {
        return 0;
    }

    // Calculer le taux d'occupation du logement
    $occupancyDays = intval($settings['occupancy_calculation_days'] ?? 14);
    $occupancyRate = getOccupancyRate($pdo, $logementId, $occupancyDays);

    // Supprimer les anciennes mises a jour pending
    $pdo->prepare("DELETE FROM superhote_price_updates WHERE logement_id = ? AND status = 'pending'")->execute([$logementId]);

    $count = 0;
    $today = new DateTime();

    for ($i = 0; $i <= $joursGeneration; $i++) {
        $date = clone $today;
        $date->modify("+$i days");
        $dateStr = $date->format('Y-m-d');
        $jourSemaine = intval($date->format('w'));

        $result = calculatePrice(
            floatval($prixPlancher),
            floatval($prixStandard),
            $i,
            $jourSemaine,
            floatval($weekendPourcent),
            floatval($dimancheReduction),
            $settings,
            $date,
            $seasons,
            $holidays,
            $occupancyRate,
            $occupancyRules
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

// Generer pour tous les logements actifs
function generateAllPrices($pdo, $settings) {
    // Pre-charger les regles une seule fois
    $seasons = getSeasons($pdo);
    $holidays = getHolidays($pdo);
    $occupancyRules = getOccupancyRules($pdo);

    echo "  Regles chargees: " . count($seasons) . " saisons, " . count($holidays) . " jours feries, " . count($occupancyRules) . " regles occupation\n";

    $configs = $pdo->query("SELECT logement_id FROM superhote_config WHERE is_active = 1")->fetchAll();
    $total = 0;
    foreach ($configs as $config) {
        $count = generatePricesForLogement($pdo, $config['logement_id'], $settings, $seasons, $holidays, $occupancyRules);
        if ($count > 0) {
            echo "  - Logement {$config['logement_id']}: $count mises a jour\n";
        }
        $total += $count;
    }
    return $total;
}

// Main
$settings = getSettings($pdo);

// Parser les arguments
$options = getopt('', ['all', 'logement-id:', 'help']);

if (isset($options['help']) || $argc < 2) {
    echo "Usage: php generate_prices.php [OPTIONS]\n";
    echo "Options:\n";
    echo "  --all              Generer pour tous les logements actifs\n";
    echo "  --logement-id=X    Generer pour un logement specifique\n";
    echo "  --help             Afficher cette aide\n";
    exit(0);
}

$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Generation des prix Superhote\n";

if (isset($options['all'])) {
    $total = generateAllPrices($pdo, $settings);
    echo "[" . date('Y-m-d H:i:s') . "] Total: $total mises a jour generees\n";
} elseif (isset($options['logement-id'])) {
    $logementId = intval($options['logement-id']);
    $count = generatePricesForLogement($pdo, $logementId, $settings);
    echo "[" . date('Y-m-d H:i:s') . "] Logement $logementId: $count mises a jour generees\n";
}

$elapsed = round(microtime(true) - $startTime, 2);
echo "[" . date('Y-m-d H:i:s') . "] Termine en {$elapsed}s\n";
