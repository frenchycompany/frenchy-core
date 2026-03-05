<?php
// pages/sync_reservations_today.php
declare(strict_types=1);

// Forcer affichage erreurs AVANT tout include pour diagnostiquer les 500 vides
ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Paris');

require_once '../config.php'; // $conn = LOCAL (Ionos) — démarre déjà la session

$DEBUG   = isset($_GET['debug'])   && $_GET['debug'] === '1';
$DRY_RUN = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';

// filet de sécu: montrer les fatals en debug
register_shutdown_function(function() use ($DEBUG) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR])) {
        if (!headers_sent()) http_response_code(500);
        $payload = ['status'=>'error','message'=>'Fatal PHP'];
        $payload['ex'] = $e['message'].' in '.$e['file'].':'.$e['line'];
        echo json_encode($payload);
    }
});
// En production, remettre display_errors à 0 après le shutdown handler
ini_set('display_errors', '0');

// helpers JSON
function jerr(int $code, string $msg, array $extra = []) {
    http_response_code($code);
    echo json_encode(['status'=>'error','message'=>$msg] + $extra);
    exit;
}
function jok(array $data) {
    echo json_encode(['status'=>'success'] + $data);
    exit;
}

// auth
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    jerr(403, 'Accès refusé (admin requis).');
}

// Connexion REMOTE (Raspberry) — optionnelle : si indisponible, on utilise uniquement la base locale
require_once __DIR__ . '/../includes/rpi_db.php';
$pdoRemote = null;
try {
    $pdoRemote = getRpiPdo();
} catch (Throwable $e) {
    // RPi indisponible, on continue avec la base locale uniquement
}

// introspection (closeCursor obligatoire avec EMULATE_PREPARES=false)
function table_exists(PDO $c, string $t): bool {
    try { $s = $c->query("SELECT 1 FROM `$t` LIMIT 1"); $s->closeCursor(); return true; }
    catch(Throwable $e){ return false; }
}
function column_exists(PDO $c, string $t, string $col): bool {
    try { $s=$c->prepare("SHOW COLUMNS FROM `$t` LIKE ?"); $s->execute([$col]); $r=(bool)$s->fetch(); $s->closeCursor(); return $r; }
    catch(Throwable $e){ return false; }
}

$today = date('Y-m-d');

// vérif REMOTE (si disponible)
$remoteOk = false;
if ($pdoRemote && table_exists($pdoRemote, 'reservation')) {
    $needRemote = [
      'id'           => column_exists($pdoRemote,'reservation','id'),
      'logement_id'  => column_exists($pdoRemote,'reservation','logement_id'),
      'date_arrivee' => column_exists($pdoRemote,'reservation','date_arrivee'),
      'date_depart'  => column_exists($pdoRemote,'reservation','date_depart'),
      'statut'       => column_exists($pdoRemote,'reservation','statut'),
      'nb_adultes'   => column_exists($pdoRemote,'reservation','nb_adultes'),
      'nb_enfants'   => column_exists($pdoRemote,'reservation','nb_enfants'),
      'nb_bebes'     => column_exists($pdoRemote,'reservation','nb_bebes'),
    ];
    $missing = array_keys(array_filter($needRemote, fn($ok)=>!$ok));
    $remoteOk = empty($missing);
}

// côté LOCAL planning : colonnes optionnelles + tokens
$pl_has_src_id    = column_exists($conn,'planning','source_reservation_id');
$pl_has_src_type  = column_exists($conn,'planning','source_type');
$tokens_table     = table_exists($conn,'intervention_tokens');

// Vérifier si la base locale a une table reservation
$localHasReservation = table_exists($conn, 'reservation');
$localNbPersExpr = '0';
if ($localHasReservation) {
    $hasNb = column_exists($conn, 'reservation', 'nb_adultes')
          && column_exists($conn, 'reservation', 'nb_enfants')
          && column_exists($conn, 'reservation', 'nb_bebes');
    if ($hasNb) {
        $localNbPersExpr = '(COALESCE(r.nb_adultes,0) + COALESCE(r.nb_enfants,0) + COALESCE(r.nb_bebes,0))';
    }
}

// Au moins une source de réservations doit être disponible
if (!$remoteOk && !$localHasReservation) {
    jerr(500, 'Aucune source de réservations disponible (ni RPi, ni table locale reservation).');
}

$deps = [];
$arrs = [];
$sourceLabel = '';

// 1) Lire depuis le REMOTE (RPi) si disponible
if ($remoteOk) {
    try {
        $sqlDeps = "
            SELECT r.id AS resa_id, r.logement_id, DATE(r.date_arrivee) AS date_arrivee,
                   DATE(r.date_depart) AS date_depart,
                   (COALESCE(r.nb_adultes,0)+COALESCE(r.nb_enfants,0)+COALESCE(r.nb_bebes,0)) AS nb_pers,
                   GREATEST(DATEDIFF(r.date_depart, r.date_arrivee), 0) AS nb_jours
            FROM reservation r WHERE r.statut = 'confirmée' AND DATE(r.date_depart) = :today
            ORDER BY r.date_depart ASC
        ";
        $stDeps = $pdoRemote->prepare($sqlDeps);
        $stDeps->execute([':today'=>$today]);
        $deps = $stDeps->fetchAll(PDO::FETCH_ASSOC);
        $sourceLabel = 'REMOTE';
    } catch (Throwable $e) {
        if ($DEBUG) error_log('sync_today: remote deps error: '.$e->getMessage());
    }

    try {
        $sqlArrs = "
            SELECT r.id AS resa_id, r.logement_id, DATE(r.date_arrivee) AS date_arrivee,
                   DATE(r.date_depart) AS date_depart,
                   (COALESCE(r.nb_adultes,0)+COALESCE(r.nb_enfants,0)+COALESCE(r.nb_bebes,0)) AS nb_pers,
                   GREATEST(DATEDIFF(r.date_depart, r.date_arrivee), 0) AS nb_jours
            FROM reservation r WHERE r.statut = 'confirmée' AND DATE(r.date_arrivee) = :today
            ORDER BY r.date_arrivee ASC
        ";
        $stArrs = $pdoRemote->prepare($sqlArrs);
        $stArrs->execute([':today'=>$today]);
        $arrs = $stArrs->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        if ($DEBUG) error_log('sync_today: remote arrs error: '.$e->getMessage());
    }
}

// 2) Lire/fusionner depuis la base LOCALE
if ($localHasReservation) {
    try {
        $sqlLocalDeps = "
            SELECT r.id AS resa_id, r.logement_id, DATE(r.date_arrivee) AS date_arrivee,
                   DATE(r.date_depart) AS date_depart, {$localNbPersExpr} AS nb_pers,
                   GREATEST(DATEDIFF(r.date_depart, r.date_arrivee), 0) AS nb_jours
            FROM reservation r WHERE r.statut = 'confirmée' AND DATE(r.date_depart) = :today
            ORDER BY r.date_depart ASC
        ";
        $stLD = $conn->prepare($sqlLocalDeps);
        $stLD->execute([':today' => $today]);
        $localDeps = $stLD->fetchAll(PDO::FETCH_ASSOC);
        $remoteKeys = array_map(fn($d) => $d['logement_id'] . '_' . $d['date_depart'], $deps);
        foreach ($localDeps as $ld) {
            if (!in_array($ld['logement_id'] . '_' . $ld['date_depart'], $remoteKeys)) {
                $ld['_source'] = 'LOCAL';
                $deps[] = $ld;
            }
        }
        $sourceLabel = $sourceLabel ? $sourceLabel.'+LOCAL' : 'LOCAL';
    } catch (Throwable $e) { /* table locale incompatible, on ignore */ }

    try {
        $sqlLocalArrs = "
            SELECT r.id AS resa_id, r.logement_id, DATE(r.date_arrivee) AS date_arrivee,
                   DATE(r.date_depart) AS date_depart, {$localNbPersExpr} AS nb_pers,
                   GREATEST(DATEDIFF(r.date_depart, r.date_arrivee), 0) AS nb_jours
            FROM reservation r WHERE r.statut = 'confirmée' AND DATE(r.date_arrivee) = :today
            ORDER BY r.date_arrivee ASC
        ";
        $stLA = $conn->prepare($sqlLocalArrs);
        $stLA->execute([':today' => $today]);
        $localArrs = $stLA->fetchAll(PDO::FETCH_ASSOC);
        $remoteArrKeys = array_map(fn($a) => $a['logement_id'] . '_' . $a['date_arrivee'], $arrs);
        foreach ($localArrs as $la) {
            if (!in_array($la['logement_id'] . '_' . $la['date_arrivee'], $remoteArrKeys)) {
                $la['_source'] = 'LOCAL';
                $arrs[] = $la;
            }
        }
    } catch (Throwable $e) { /* table locale incompatible, on ignore */ }
}

// helper : token si possible
function ensure_token_if_possible(PDO $c, int $pid, bool $enabled): void {
    if (!$enabled) return;
    try {
        $ch = $c->prepare("SELECT 1 FROM intervention_tokens WHERE intervention_id = ? LIMIT 1");
        $ch->execute([$pid]);
        $exists = (bool)$ch->fetch();
        $ch->closeCursor();
        if ($exists) return;
        $token = bin2hex(random_bytes(16));
        $exp = date('Y-m-d H:i:s', strtotime('+7 days'));
        $ins = $c->prepare("INSERT INTO intervention_tokens (intervention_id, token, expires_at) VALUES (?, ?, ?)");
        $ins->execute([$pid, $token, $exp]);
    } catch (Throwable $e) { /* ignore */ }
}

// Prépare les statements LOCAL pour recherche/création
try {
    $findBySourceCheckout = $pl_has_src_id && $pl_has_src_type
        ? $conn->prepare("SELECT id, statut FROM planning WHERE source_reservation_id = ? AND source_type = 'AUTO_CHECKOUT' LIMIT 1")
        : null;
    $findByHeuristic = $conn->prepare("
        SELECT id, statut
        FROM planning
        WHERE logement_id = ?
          AND date = ?
          AND note LIKE ?
        LIMIT 1
    ");

    // existe-t-il déjà une intervention pour CE logement AUJOURD'HUI (peu importe la source/note) ?
    $findAnyToday = $conn->prepare("
        SELECT id FROM planning WHERE logement_id = ? AND date = ? LIMIT 1
    ");

    // dernière intervention pour un logement (pour calculer le gap)
    $findLastIntervention = $conn->prepare("
        SELECT MAX(date) AS last_date FROM planning WHERE logement_id = ? AND date < ?
    ");

    $insertPlanningCheckout = $conn->prepare("
        INSERT INTO planning (
            logement_id, date, nombre_de_personnes, nombre_de_jours_reservation, statut, note
            ".($pl_has_src_id ? ", source_reservation_id" : "")."
            ".($pl_has_src_type ? ", source_type" : "")."
        ) VALUES (
            :logement_id, :date, :nb_pers, :nb_jours, 'À Faire', :note
            ".($pl_has_src_id ? ", :resa_id" : "")."
            ".($pl_has_src_type ? ", 'AUTO_CHECKOUT'" : "")."
        )
    ");

    $insertPlanningVerif = $conn->prepare("
        INSERT INTO planning (
            logement_id, date, nombre_de_personnes, nombre_de_jours_reservation, statut, note
            ".($pl_has_src_id ? ", source_reservation_id" : "")."
            ".($pl_has_src_type ? ", source_type" : "")."
        ) VALUES (
            :logement_id, :date, :nb_pers, :nb_jours, 'À Vérifier', :note
            ".($pl_has_src_id ? ", :resa_id" : "")."
            ".($pl_has_src_type ? ", 'AUTO_VERIF'" : "")."
        )
    ");
} catch (Throwable $e) {
    jerr(500, 'Erreur préparation requêtes SQL.', $DEBUG?['ex'=>$e->getMessage()]:[]);
}

$report = [
  'departures' => [],
  'arrivals'   => [],
];
$inserted = 0; $skipped = 0; $updated = 0;

try {
    if (!$DRY_RUN) $conn->beginTransaction();

    // === 1) TRAITEMENT DES DÉPARTS (comme avant) ===
    foreach ($deps as $r) {
        $resaId  = (int)$r['resa_id'];
        $logId   = (int)$r['logement_id'];
        $depart  = $r['date_depart']; // = today
        $nbPers  = max(0, (int)$r['nb_pers']);
        $nbJours = max(0, (int)$r['nb_jours']);
        $srcLabel = $r['_source'] ?? 'REMOTE';
        $note    = "Auto: ménage de sortie (resa #{$resaId}) [{$srcLabel}]";

        $existing = null;

        if ($findBySourceCheckout) {
            $findBySourceCheckout->execute([$resaId]);
            $existing = $findBySourceCheckout->fetch(PDO::FETCH_ASSOC) ?: null;
            $findBySourceCheckout->closeCursor();
        }
        if (!$existing) {
            $like = "Auto: ménage de sortie (resa #{$resaId})%";
            $findByHeuristic->execute([$logId, $depart, $like]);
            $existing = $findByHeuristic->fetch(PDO::FETCH_ASSOC) ?: null;
            $findByHeuristic->closeCursor();
        }

        $hadBefore = (bool)$existing;
        $interventionId = $existing['id'] ?? null;
        $createdNow = false;

        if (!$hadBefore) {
            if (!$DRY_RUN) {
                $params = [
                    ':logement_id'=>$logId,
                    ':date'=>$depart,
                    ':nb_pers'=>$nbPers,
                    ':nb_jours'=>$nbJours,
                    ':note'=>$note,
                ];
                if ($pl_has_src_id) $params[':resa_id'] = $resaId;
                $insertPlanningCheckout->execute($params);
                $interventionId = (int)$conn->lastInsertId();
                ensure_token_if_possible($conn, $interventionId, $tokens_table);
            }
            $createdNow = true;
            $inserted++;
        } else {
            $skipped++;
        }

        $report['departures'][] = [
            'reservation_id'         => $resaId,
            'logement_id'            => $logId,
            'date_depart'            => $depart,
            'had_intervention_before'=> $hadBefore,
            'created_now'            => $createdNow && !$DRY_RUN,
            'intervention_id'        => $interventionId,
        ];
    }

    // === 2) TRAITEMENT DES ARRIVÉES ===
    // Logique : PAS de ménage pour un check-in.
    // Si le logement a un check-out le même jour, le ménage de sortie suffit.
    // Si le logement est resté vide 2+ jours sans ménage → "À Vérifier" (vérif poussière).
    $depLogIds = array_map(fn($d) => (int)$d['logement_id'], $deps);

    foreach ($arrs as $r) {
        $resaId  = (int)$r['resa_id'];
        $logId   = (int)$r['logement_id'];
        $arrivee = $r['date_arrivee']; // = today
        $nbPers  = max(0, (int)$r['nb_pers']);
        $nbJours = max(0, (int)$r['nb_jours']);
        $srcLabel = $r['_source'] ?? 'REMOTE';

        // 0) Ce logement a un check-out aujourd'hui → le ménage de sortie couvre, on skip
        if (in_array($logId, $depLogIds)) {
            $skipped++;
            $report['arrivals'][] = [
                'reservation_id'  => $resaId,
                'logement_id'     => $logId,
                'date_arrivee'    => $arrivee,
                'reason'          => 'checkout_same_day_covers_it',
                'created_now'     => false,
                'intervention_id' => null,
            ];
            continue;
        }

        // 1) Il existe déjà une intervention aujourd'hui pour ce logement → skip
        $findAnyToday->execute([$logId, $today]);
        $alreadyExists = $findAnyToday->fetch();
        $findAnyToday->closeCursor();
        if ($alreadyExists) {
            $skipped++;
            $report['arrivals'][] = [
                'reservation_id'  => $resaId,
                'logement_id'     => $logId,
                'date_arrivee'    => $arrivee,
                'reason'          => 'intervention_already_exists_today',
                'created_now'     => false,
                'intervention_id' => null,
            ];
            continue;
        }

        // 2) Vérifier depuis combien de jours le logement n'a pas eu d'intervention
        $findLastIntervention->execute([$logId, $today]);
        $lastRow = $findLastIntervention->fetch(PDO::FETCH_ASSOC);
        $findLastIntervention->closeCursor();
        $lastDate = $lastRow['last_date'] ?? null;

        if ($lastDate) {
            $gapDays = (int)((strtotime($today) - strtotime($lastDate)) / 86400);
        } else {
            $gapDays = 999; // jamais eu d'intervention → considéré comme longtemps
        }

        // Si le logement a été vérifié/nettoyé il y a moins de 2 jours → pas besoin
        if ($gapDays < 2) {
            $skipped++;
            $report['arrivals'][] = [
                'reservation_id'  => $resaId,
                'logement_id'     => $logId,
                'date_arrivee'    => $arrivee,
                'reason'          => 'recently_cleaned_' . $gapDays . 'd_ago',
                'created_now'     => false,
                'intervention_id' => null,
            ];
            continue;
        }

        // 3) Logement vide 2+ jours → créer "À Vérifier" (vérif poussière, pas ménage complet)
        $note = "Auto: à vérifier avant arrivée — vide depuis {$gapDays}j (resa #{$resaId}) [{$srcLabel}]";

        // Vérifier si une telle intervention existe déjà
        $existing = null;
        $like = "Auto: à vérifier avant arrivée%resa #{$resaId}%";
        $findByHeuristic->execute([$logId, $today, $like]);
        $existing = $findByHeuristic->fetch(PDO::FETCH_ASSOC) ?: null;
        $findByHeuristic->closeCursor();

        $hadBefore = (bool)$existing;
        $interventionId = $existing['id'] ?? null;
        $createdNow = false;

        if (!$hadBefore) {
            if (!$DRY_RUN) {
                $params = [
                    ':logement_id'=>$logId,
                    ':date'=>$today,
                    ':nb_pers'=>$nbPers,
                    ':nb_jours'=>$nbJours,
                    ':note'=>$note,
                ];
                if ($pl_has_src_id) $params[':resa_id'] = $resaId;
                $insertPlanningVerif->execute($params);
                $interventionId = (int)$conn->lastInsertId();
                ensure_token_if_possible($conn, $interventionId, $tokens_table);
            }
            $createdNow = true;
            $inserted++;
        } else {
            $skipped++;
        }

        $report['arrivals'][] = [
            'reservation_id'         => $resaId,
            'logement_id'            => $logId,
            'date_arrivee'           => $arrivee,
            'gap_days'               => $gapDays,
            'type'                   => 'a_verifier',
            'had_intervention_before'=> $hadBefore,
            'created_now'            => $createdNow && !$DRY_RUN,
            'intervention_id'        => $interventionId,
        ];
    }

    if (!$DRY_RUN) $conn->commit();

    jok([
        'day'       => $today,
        'dry_run'   => $DRY_RUN,
        'inserted'  => $inserted,
        'updated'   => $updated,
        'skipped'   => $skipped,
        'details'   => $report,
        'mode'      => ($pl_has_src_id && $pl_has_src_type) ? 'with_source_keys' : 'compat',
        'tokens'    => $tokens_table ? 'used' : 'skipped_no_table',
        'source'    => $sourceLabel ?: 'LOCAL',
        'target'    => 'LOCAL:planning'
    ]);
} catch (Throwable $e) {
    if (!$DRY_RUN && $conn->inTransaction()) $conn->rollBack();
    jerr(500, 'Erreur serveur pendant la synchro du jour.', $DEBUG?['ex'=>$e->getMessage()]:[]);
}
