<?php
// pages/sync_reservations_by_date.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Paris');

require_once '../config.php'; // $conn = LOCAL (Ionos)
session_start();

$DEBUG   = isset($_GET['debug'])   && $_GET['debug'] === '1';
$DRY_RUN = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';

// ---- lecture et validation de la date cible ----
$target = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $target)) {
  http_response_code(400);
  echo json_encode(['status'=>'error','message'=>"Paramètre 'date' invalide (attendu YYYY-MM-DD)."]);
  exit;
}

// filet de sécu: montrer les fatals en debug
register_shutdown_function(function() use ($DEBUG) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR])) {
        http_response_code(500);
        $payload = ['status'=>'error','message'=>'Fatal PHP'];
        if ($DEBUG) $payload['ex'] = $e['message'].' in '.$e['file'].':'.$e['line'];
        echo json_encode($payload);
    }
});
ini_set('display_errors', '0');
error_reporting(E_ALL);

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

// Connexion REMOTE (Raspberry) via helper centralisé
require_once __DIR__ . '/../includes/rpi_db.php';
try {
    $pdoRemote = getRpiPdo();
} catch (Throwable $e) {
    jerr(500, 'Connexion distante impossible.', $DEBUG?['ex'=>$e->getMessage()]:[]);
}

// introspection
function table_exists(PDO $c, string $t): bool { try { $c->query("SELECT 1 FROM `$t` LIMIT 1"); return true; } catch(Throwable $e){ return false; } }
function column_exists(PDO $c, string $t, string $col): bool { try { $s=$c->prepare("SHOW COLUMNS FROM `$t` LIKE ?"); $s->execute([$col]); return (bool)$s->fetch(); } catch(Throwable $e){ return false; } }

// vérif REMOTE
if (!table_exists($pdoRemote, 'reservation')) jerr(500, "Table 'reservation' introuvable sur la base distante.");

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
if ($missing) jerr(500, 'Colonnes manquantes (REMOTE reservation)', ['missing'=>$missing]);

// côté LOCAL planning : colonnes optionnelles + tokens
$pl_has_src_id    = column_exists($conn,'planning','source_reservation_id');
$pl_has_src_type  = column_exists($conn,'planning','source_type');
$tokens_table     = table_exists($conn,'intervention_tokens');

// Lire les DEPARTS = $target
try {
    $sqlDeps = "
        SELECT 
            r.id AS resa_id,
            r.logement_id AS logement_id,
            DATE(r.date_arrivee) AS date_arrivee,
            DATE(r.date_depart)  AS date_depart,
            (COALESCE(r.nb_adultes,0) + COALESCE(r.nb_enfants,0) + COALESCE(r.nb_bebes,0)) AS nb_pers,
            GREATEST(DATEDIFF(r.date_depart, r.date_arrivee), 0) AS nb_jours
        FROM reservation r
        WHERE r.statut = 'confirmée'
          AND DATE(r.date_depart) = :d
        ORDER BY r.date_depart ASC
    ";
    $stDeps = $pdoRemote->prepare($sqlDeps);
    $stDeps->execute([':d'=>$target]);
    $deps = $stDeps->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    jerr(500, 'Erreur SQL lecture départs (REMOTE)', $DEBUG?['ex'=>$e->getMessage()]:[]);
}

// Lire les ARRIVÉES = $target
try {
    $sqlArrs = "
        SELECT 
            r.id AS resa_id,
            r.logement_id AS logement_id,
            DATE(r.date_arrivee) AS date_arrivee,
            DATE(r.date_depart)  AS date_depart,
            (COALESCE(r.nb_adultes,0) + COALESCE(r.nb_enfants,0) + COALESCE(r.nb_bebes,0)) AS nb_pers,
            GREATEST(DATEDIFF(r.date_depart, r.date_arrivee), 0) AS nb_jours
        FROM reservation r
        WHERE r.statut = 'confirmée'
          AND DATE(r.date_arrivee) = :d
        ORDER BY r.date_arrivee ASC
    ";
    $stArrs = $pdoRemote->prepare($sqlArrs);
    $stArrs->execute([':d'=>$target]);
    $arrs = $stArrs->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    jerr(500, 'Erreur SQL lecture arrivées (REMOTE)', $DEBUG?['ex'=>$e->getMessage()]:[]);
}

// helper : token si possible
function ensure_token_if_possible(PDO $c, int $pid, bool $enabled): void {
    if (!$enabled) return;
    try {
        $ch = $c->prepare("SELECT 1 FROM intervention_tokens WHERE intervention_id = ? LIMIT 1");
        $ch->execute([$pid]);
        if ($ch->fetch()) return;
        $token = bin2hex(random_bytes(16));
        $exp = date('Y-m-d H:i:s', strtotime('+7 days'));
        $ins = $c->prepare("INSERT INTO intervention_tokens (intervention_id, token, expires_at) VALUES (?, ?, ?)");
        $ins->execute([$pid, $token, $exp]);
    } catch (Throwable $e) { /* ignore */ }
}

// statements LOCAL
$findBySourceCheckout = $pl_has_src_id && $pl_has_src_type
    ? $conn->prepare("SELECT id, statut FROM planning WHERE source_reservation_id = ? AND source_type = 'AUTO_CHECKOUT' LIMIT 1")
    : null;
$findBySourceArrival = $pl_has_src_id && $pl_has_src_type
    ? $conn->prepare("SELECT id, statut FROM planning WHERE source_reservation_id = ? AND source_type = 'AUTO_ARRIVAL' LIMIT 1")
    : null;

$findByHeuristic = $conn->prepare("
    SELECT id, statut 
    FROM planning
    WHERE logement_id = ?
      AND date = ?
      AND note LIKE ?
    LIMIT 1
");

$findAnyOnDate = $conn->prepare("
    SELECT id FROM planning WHERE logement_id = ? AND date = ? LIMIT 1
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
$insertPlanningArrival = $conn->prepare("
    INSERT INTO planning (
        logement_id, date, nombre_de_personnes, nombre_de_jours_reservation, statut, note
        ".($pl_has_src_id ? ", source_reservation_id" : "")."
        ".($pl_has_src_type ? ", source_type" : "")."
    ) VALUES (
        :logement_id, :date, :nb_pers, :nb_jours, 'À Faire', :note
        ".($pl_has_src_id ? ", :resa_id" : "")."
        ".($pl_has_src_type ? ", 'AUTO_ARRIVAL'" : "")."
    )
");

$report = [
  'date'      => $target,
  'departures'=> [],
  'arrivals'  => [],
];
$inserted = 0; $skipped = 0; $updated = 0;

try {
    if (!$DRY_RUN) $conn->beginTransaction();

    // 1) DÉPARTS = $target
    foreach ($deps as $r) {
        $resaId  = (int)$r['resa_id'];
        $logId   = (int)$r['logement_id'];
        $depart  = $r['date_depart']; // = $target
        $nbPers  = max(0, (int)$r['nb_pers']);
        $nbJours = max(0, (int)$r['nb_jours']);
        $note    = "Auto: ménage de sortie (resa #{$resaId}) [REMOTE]";

        $existing = null;
        if ($findBySourceCheckout) {
            $findBySourceCheckout->execute([$resaId]);
            $existing = $findBySourceCheckout->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$existing) {
            $like = "Auto: ménage de sortie (resa #{$resaId})%";
            $findByHeuristic->execute([$logId, $depart, $like]);
            $existing = $findByHeuristic->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $hadBefore = (bool)$existing;
        $interventionId = $existing['id'] ?? null;
        $createdNow = false;

        if (!$hadBefore) {
            if (!$DRY_RUN) {
                $insertPlanningCheckout->execute([
                    ':logement_id'=>$logId,
                    ':date'=>$depart,
                    ':nb_pers'=>$nbPers,
                    ':nb_jours'=>$nbJours,
                    ':note'=>$note,
                    ...($pl_has_src_id ? [':resa_id'=>$resaId] : []),
                ]);
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

    // 2) ARRIVÉES = $target (créer si rien n’existe déjà ce jour pour le logement)
    foreach ($arrs as $r) {
        $resaId  = (int)$r['resa_id'];
        $logId   = (int)$r['logement_id'];
        $nbPers  = max(0, (int)$r['nb_pers']);
        $nbJours = max(0, (int)$r['nb_jours']);
        $note    = "Auto: ménage avant arrivée (resa #{$resaId}) [REMOTE]";

        // si une intervention existe déjà ce jour → ne rien créer
        $findAnyOnDate->execute([$logId, $target]);
        if ($findAnyOnDate->fetch()) {
            $skipped++;
            $report['arrivals'][] = [
                'reservation_id'  => $resaId,
                'logement_id'     => $logId,
                'date'            => $target,
                'reason'          => 'intervention_already_exists_on_date',
                'created_now'     => false,
                'intervention_id' => null,
            ];
            continue;
        }

        $existing = null;
        if ($findBySourceArrival) {
            $findBySourceArrival->execute([$resaId]);
            $existing = $findBySourceArrival->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$existing) {
            $like = "Auto: ménage avant arrivée (resa #{$resaId})%";
            $findByHeuristic->execute([$logId, $target, $like]);
            $existing = $findByHeuristic->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $hadBefore = (bool)$existing;
        $interventionId = $existing['id'] ?? null;
        $createdNow = false;

        if (!$hadBefore) {
            if (!$DRY_RUN) {
                $insertPlanningArrival->execute([
                    ':logement_id'=>$logId,
                    ':date'=>$target,
                    ':nb_pers'=>$nbPers,
                    ':nb_jours'=>$nbJours,
                    ':note'=>$note,
                    ...($pl_has_src_id ? [':resa_id'=>$resaId] : []),
                ]);
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
            'date'                   => $target,
            'had_intervention_before'=> $hadBefore,
            'created_now'            => $createdNow && !$DRY_RUN,
            'intervention_id'        => $interventionId,
        ];
    }

    if (!$DRY_RUN) $conn->commit();

    jok([
        'day'       => $target,
        'dry_run'   => $DRY_RUN,
        'inserted'  => $inserted,
        'updated'   => $updated,
        'skipped'   => $skipped,
        'details'   => $report,
        'mode'      => ($pl_has_src_id && $pl_has_src_type) ? 'with_source_keys' : 'compat',
        'tokens'    => $tokens_table ? 'used' : 'skipped_no_table',
        'source'    => 'REMOTE:sms_db.reservation',
        'target'    => 'LOCAL:planning'
    ]);
} catch (Throwable $e) {
    if (!$DRY_RUN && $conn->inTransaction()) $conn->rollBack();
    jerr(500, 'Erreur serveur pendant la synchro.', $DEBUG?['ex'=>$e->getMessage()]:[]);
}
