<?php
/**
 * API JSON pour le calendrier des réservations
 * Combine : table reservation (RPi) + ical_reservations (RPi)
 * Enrichit avec les noms de logements + tarifs (VPS)
 */
header('Content-Type: application/json; charset=utf-8');

include '../config.php';
require_once __DIR__ . '/../includes/rpi_db.php';

// Vérifier la session
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['id_intervenant'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

$start       = $_GET['start'] ?? date('Y-m-01');
$end         = $_GET['end']   ?? date('Y-m-t', strtotime('+3 months'));
$logement_id = isset($_GET['logement_id']) && $_GET['logement_id'] !== '' ? (int) $_GET['logement_id'] : null;

try {
    // ── Connexion RPI (nécessaire pour superhote + réservations) ──
    $pdoRpi = null;
    try {
        $pdoRpi = getRpiPdo();
    } catch (Exception $e) {
        error_log('calendrier_api RPi connection: ' . $e->getMessage());
    }

    // ── 1. Logements (VPS) + tarifs (RPI) ──
    $logementNames = [];
    $logementPricing = [];

    // D'abord charger les noms de logements actifs
    try {
        $rows = $conn->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $logementNames[$r['id']] = $r['nom_du_logement'];
        }
    } catch (PDOException $e) {
        // Fallback si colonne actif n'existe pas
        try {
            $rows = $conn->query("SELECT id, nom_du_logement FROM liste_logements ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $logementNames[$r['id']] = $r['nom_du_logement'];
            }
        } catch (PDOException $e2) {
            error_log('calendrier_api logements: ' . $e2->getMessage());
        }
    }

    // Enrichir avec les tarifs depuis la base RPI (superhote_config + superhote_groups)
    if ($pdoRpi) try {
        $configRows = $pdoRpi->query("
            SELECT sc.logement_id,
                   sc.prix_plancher, sc.prix_standard, sc.weekend_pourcent, sc.dimanche_reduction,
                   sc.nuits_minimum, sc.groupe,
                   g.prix_plancher AS g_prix_plancher, g.prix_standard AS g_prix_standard,
                   g.weekend_pourcent AS g_weekend_pourcent, g.dimanche_reduction AS g_dimanche_reduction,
                   g.nuits_minimum AS g_nuits_minimum
            FROM superhote_config sc
            LEFT JOIN superhote_groups g ON sc.groupe = g.nom
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($configRows as $r) {
            $lid = (int) $r['logement_id'];
            if (!isset($logementNames[$lid])) continue; // ignorer les logements inactifs
            $logementPricing[$lid] = [
                'prix_plancher'      => (float) ($r['g_prix_plancher'] ?? $r['prix_plancher'] ?? 0),
                'prix_standard'      => (float) ($r['g_prix_standard'] ?? $r['prix_standard'] ?? 0),
                'weekend_pourcent'   => (float) ($r['g_weekend_pourcent'] ?? $r['weekend_pourcent'] ?? 10),
                'dimanche_reduction' => (float) ($r['g_dimanche_reduction'] ?? $r['dimanche_reduction'] ?? 5),
                'nuits_minimum'      => (int)   ($r['g_nuits_minimum'] ?? $r['nuits_minimum'] ?? 1),
                'groupe'             => $r['groupe'] ?? null,
            ];
        }
    } catch (PDOException $e) {
        error_log('calendrier_api pricing: ' . $e->getMessage());
    }

    // ── 2. Calcul dynamique des prix par jour (même algorithme que generate_prices.php) ──
    $dailyPrices = []; // [logement_id][date] = prix
    $superhoteSettings = [];
    $seasons = [];
    $holidays = [];
    $occupancyRules = [];

    if ($pdoRpi) try {
        $settingsRows = $pdoRpi->query("SELECT key_name, value FROM superhote_settings")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($settingsRows as $sr) {
            $superhoteSettings[$sr['key_name']] = $sr['value'];
        }
    } catch (PDOException $e) { /* table might not exist */ }

    // Charger les saisons actives
    $saisonsEnabled = ($superhoteSettings['saisons_enabled'] ?? '1') === '1';
    if ($saisonsEnabled && $pdoRpi) try {
        $seasons = $pdoRpi->query("SELECT * FROM superhote_seasons WHERE is_active = 1 ORDER BY priorite DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { /* table might not exist */ }

    // Charger les jours fériés actifs
    $holidaysEnabled = ($superhoteSettings['holidays_enabled'] ?? '1') === '1';
    if ($holidaysEnabled && $pdoRpi) try {
        $holidays = $pdoRpi->query("SELECT * FROM superhote_holidays WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { /* table might not exist */ }

    // Charger les règles d'occupation
    $occupancyEnabled = ($superhoteSettings['occupancy_enabled'] ?? '1') === '1';
    if ($occupancyEnabled && $pdoRpi) try {
        $occupancyRules = $pdoRpi->query("SELECT * FROM superhote_occupancy_rules WHERE is_active = 1 ORDER BY seuil_occupation_min ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { /* table might not exist */ }

    $palierJ1_3 = floatval($superhoteSettings['palier_j1_3_pourcent'] ?? 25) / 100;
    $palierJ4_6 = floatval($superhoteSettings['palier_j4_6_pourcent'] ?? 50) / 100;
    $palierJ7_13 = floatval($superhoteSettings['palier_j7_13_pourcent'] ?? 75) / 100;

    $today = new DateTime('today');
    $dateStart = new DateTime($start);
    $dateEnd = new DateTime($end);

    // Pré-calculer les taux d'occupation par logement
    $occupancyRates = [];
    if ($occupancyEnabled && !empty($occupancyRules) && $pdoRpi) {
        $occupancyDays = intval($superhoteSettings['occupancy_calculation_days'] ?? 14);
        foreach ($logementPricing as $lid => $lp) {
            try {
                $occStmt = $pdoRpi->prepare("
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
                $occStmt->execute([$occupancyDays, $lid]);
                $occResult = $occStmt->fetch(PDO::FETCH_ASSOC);
                $joursOccupes = intval($occResult['jours_occupes'] ?? 0);
                $occupancyRates[$lid] = round(($joursOccupes / $occupancyDays) * 100, 2);
            } catch (PDOException $e) {
                $occupancyRates[$lid] = 50; // Valeur par défaut
            }
        }
    }

    foreach ($logementPricing as $lid => $lp) {
        if ($lp['prix_standard'] <= 0) continue;
        $ecart = $lp['prix_standard'] - $lp['prix_plancher'];
        $cursor = clone $dateStart;
        while ($cursor <= $dateEnd) {
            $joursAvant = max(0, (int) $today->diff($cursor)->format('%r%a'));
            $jourSemaine = (int) $cursor->format('w'); // 0=dim, 5=ven, 6=sam

            // 1. Prix de base selon anticipation
            if ($joursAvant == 0) {
                $prix = $lp['prix_plancher'];
            } elseif ($joursAvant <= 3) {
                $prix = $lp['prix_plancher'] + ($ecart * $palierJ1_3);
            } elseif ($joursAvant <= 6) {
                $prix = $lp['prix_plancher'] + ($ecart * $palierJ4_6);
            } elseif ($joursAvant <= 13) {
                $prix = $lp['prix_plancher'] + ($ecart * $palierJ7_13);
            } else {
                $prix = $lp['prix_standard'];
            }

            // 2. Weekend (vendredi/samedi)
            if ($jourSemaine == 5 || $jourSemaine == 6) {
                $prix = $prix * (1 + $lp['weekend_pourcent'] / 100);
            }
            // Dimanche
            elseif ($jourSemaine == 0) {
                $prix = $prix - $lp['dimanche_reduction'];
            }

            // 3. Ajustement saisonnier
            if ($saisonsEnabled && !empty($seasons)) {
                $month = intval($cursor->format('m'));
                $day = intval($cursor->format('d'));
                $dateCompare = sprintf('2000-%02d-%02d', $month, $day);
                foreach ($seasons as $season) {
                    $debut = $season['date_debut'];
                    $fin = $season['date_fin'];
                    $inSeason = false;
                    if ($debut > $fin) {
                        $inSeason = ($dateCompare >= $debut || $dateCompare <= $fin);
                    } else {
                        $inSeason = ($dateCompare >= $debut && $dateCompare <= $fin);
                    }
                    if ($inSeason) {
                        $majoration = floatval($season['majoration_pourcent'] ?? 0);
                        $reduction = floatval($season['reduction_pourcent'] ?? 0);
                        if ($majoration > 0) {
                            $prix = $prix * (1 + $majoration / 100);
                        } elseif ($reduction > 0) {
                            $prix = $prix * (1 - $reduction / 100);
                        }
                        break; // Priorité la plus haute d'abord (ORDER BY priorite DESC)
                    }
                }
            }

            // 4. Ajustement jours fériés
            if ($holidaysEnabled && !empty($holidays)) {
                $year = intval($cursor->format('Y'));
                foreach ($holidays as $holiday) {
                    $ferieMonth = intval(substr($holiday['date_ferie'], 5, 2));
                    $ferieDay = intval(substr($holiday['date_ferie'], 8, 2));
                    $joursAutour = intval($holiday['jours_autour'] ?? 0);
                    $ferieDate = new DateTime("$year-$ferieMonth-$ferieDay");
                    $diff = intval($cursor->diff($ferieDate)->format('%r%a'));
                    if (abs($diff) <= $joursAutour) {
                        $prix = $prix * (1 + floatval($holiday['majoration_pourcent'] ?? 0) / 100);
                        break;
                    }
                }
            }

            // 5. Ajustement selon occupation
            if ($occupancyEnabled && !empty($occupancyRules) && isset($occupancyRates[$lid])) {
                $occRate = $occupancyRates[$lid];
                foreach ($occupancyRules as $rule) {
                    if ($occRate >= floatval($rule['seuil_occupation_min']) &&
                        $occRate < floatval($rule['seuil_occupation_max'])) {
                        $ajustement = floatval($rule['ajustement_pourcent'] ?? 0);
                        if ($ajustement != 0) {
                            $prix = $prix * (1 + $ajustement / 100);
                        }
                        break;
                    }
                }
            }

            $dailyPrices[$lid][$cursor->format('Y-m-d')] = round($prix, 0);
            $cursor->modify('+1 day');
        }
    }

    // ── 2b. Fallback : superhote_price_updates (VPS) pour logements sans calcul dynamique ──
    if (count($dailyPrices) === 0) {
        try {
            $priceStmt = $conn->prepare("
                SELECT logement_id, date_start, price
                FROM superhote_price_updates
                WHERE date_start >= ? AND date_start <= ? AND status IN ('pending','completed')
                ORDER BY date_start
            ");
            $priceStmt->execute([$start, $end]);
            foreach ($priceStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $lid = (int) $p['logement_id'];
                $dailyPrices[$lid][$p['date_start']] = (float) $p['price'];
            }
        } catch (PDOException $e) {
            // superhote_price_updates might not exist on VPS
        }

        // Aussi fallback pour $logementPricing depuis superhote_price_updates
        if (count($logementPricing) === 0 && count($dailyPrices) > 0) {
            foreach ($dailyPrices as $lid => $prices) {
                if (!isset($logementNames[$lid])) continue;
                $avg = array_sum($prices) / count($prices);
                $logementPricing[$lid] = [
                    'prix_plancher'      => round(min($prices)),
                    'prix_standard'      => round($avg),
                    'weekend_pourcent'   => 10,
                    'dimanche_reduction' => 5,
                    'nuits_minimum'      => 1,
                    'groupe'             => null,
                ];
            }
        }
    }

    $events = [];

    // ── 3. Table reservation (réservations manuelles / SMS) ──
    if ($pdoRpi) try {
        $sql = "
            SELECT id, reference, logement_id, date_arrivee, date_depart,
                   prenom, nom, plateforme, telephone, email,
                   nb_adultes, nb_enfants, nb_bebes, statut,
                   DATEDIFF(date_depart, date_arrivee) AS num_nights
            FROM reservation
            WHERE date_arrivee <= :end AND date_depart >= :start
        ";
        $params = [':start' => $start, ':end' => $end];

        if ($logement_id) {
            $sql .= " AND logement_id = :lid";
            $params[':lid'] = $logement_id;
        }
        $sql .= " ORDER BY date_arrivee";

        $stmt = $pdoRpi->prepare($sql);
        $stmt->execute($params);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reservations as $r) {
            $guestName = trim(($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? ''));
            $lid = (int) $r['logement_id'];
            $events[] = [
                'id'            => 'resa_' . $r['id'],
                'title'         => $guestName ?: ($r['reference'] ?: 'Réservation'),
                'start'         => $r['date_arrivee'],
                'end'           => $r['date_depart'],
                'logement_id'   => $lid,
                'logement_name' => $logementNames[$lid] ?? '',
                'guest_name'    => $guestName,
                'plateforme'    => $r['plateforme'] ?? '',
                'telephone'     => $r['telephone'] ?? '',
                'nb_adultes'    => (int) ($r['nb_adultes'] ?? 0),
                'nb_enfants'    => (int) ($r['nb_enfants'] ?? 0),
                'num_nights'    => (int) ($r['num_nights'] ?? 0),
                'statut'        => $r['statut'] ?? '',
                'is_blocked'    => false,
                'source'        => 'reservation',
            ];
        }
    } catch (PDOException $e) {
        error_log('calendrier_api reservation: ' . $e->getMessage());
    }

    // ── 4. Table ical_reservations (sync iCal) ──
    if ($pdoRpi) try {
        $icalSql = "
            SELECT ir.id, ir.summary, ir.start_date, ir.end_date,
                   ir.guest_name, ir.guest_email, ir.guest_phone,
                   ir.status, ir.is_blocked, ir.num_nights,
                   ir.connection_id,
                   tl.external_listing_id,
                   tac.account_name
            FROM ical_reservations ir
            LEFT JOIN travel_account_connections tac ON ir.connection_id = tac.id
            LEFT JOIN travel_listings tl ON tac.id = tl.connection_id
            WHERE ir.start_date <= :end AND ir.end_date >= :start
        ";
        $icalParams = [':start' => $start, ':end' => $end];
        $stmt = $pdoRpi->prepare($icalSql);
        $stmt->execute($icalParams);
        $icalReservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($icalReservations as $ir) {
            $mappedLogementId = null;

            if (!empty($ir['account_name'])) {
                foreach ($logementNames as $lid => $lname) {
                    if (stripos($ir['account_name'], $lname) !== false || stripos($lname, $ir['account_name']) !== false) {
                        $mappedLogementId = $lid;
                        break;
                    }
                }
            }

            if ($logement_id && $mappedLogementId !== $logement_id) {
                continue;
            }

            // Dédoublonnage
            $isDuplicate = false;
            foreach ($events as $existing) {
                if ($existing['start'] === $ir['start_date'] &&
                    $existing['end'] === $ir['end_date'] &&
                    $existing['logement_id'] === $mappedLogementId) {
                    $isDuplicate = true;
                    break;
                }
            }
            if ($isDuplicate) continue;

            $events[] = [
                'id'            => 'ical_' . $ir['id'],
                'title'         => $ir['guest_name'] ?: ($ir['summary'] ?: 'Réservation iCal'),
                'start'         => $ir['start_date'],
                'end'           => $ir['end_date'],
                'logement_id'   => $mappedLogementId,
                'logement_name' => $mappedLogementId ? ($logementNames[$mappedLogementId] ?? '') : ($ir['account_name'] ?? ''),
                'guest_name'    => $ir['guest_name'] ?? '',
                'plateforme'    => $ir['account_name'] ?? 'iCal',
                'telephone'     => $ir['guest_phone'] ?? '',
                'nb_adultes'    => 0,
                'nb_enfants'    => 0,
                'num_nights'    => (int) ($ir['num_nights'] ?? 0),
                'statut'        => $ir['status'] ?? '',
                'is_blocked'    => (bool) ($ir['is_blocked'] ?? false),
                'source'        => 'ical',
            ];
        }
    } catch (PDOException $e) {
        error_log('calendrier_api ical: ' . $e->getMessage());
    }

    // Trier par date
    usort($events, function($a, $b) {
        return strcmp($a['start'], $b['start']);
    });

    echo json_encode([
        'success'      => true,
        'events'       => $events,
        'count'        => count($events),
        'pricing'      => (object) $logementPricing,
        'daily_prices' => (object) $dailyPrices,
        '_debug'       => [
            'rpi_connected'     => $pdoRpi !== null,
            'logements_count'   => count($logementNames),
            'pricing_count'     => count($logementPricing),
            'daily_prices_count'=> count($dailyPrices),
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Erreur base de données : ' . $e->getMessage(),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
