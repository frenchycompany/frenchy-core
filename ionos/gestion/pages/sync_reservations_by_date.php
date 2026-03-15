<?php
// pages/sync_reservations_by_date.php
declare(strict_types=1);

// Forcer affichage erreurs AVANT tout include pour diagnostiquer les 500 vides
ini_set('display_errors', '1');

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Paris');

require_once '../config.php'; // $conn = LOCAL (Ionos) — démarre déjà la session

$DEBUG   = (isset($_GET['debug']) && $_GET['debug'] === '1') || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
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

// auth (compatible ancien système $_SESSION['role'] et nouveau $_SESSION['user_role'])
$sessionRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if ($sessionRole !== 'admin' && $sessionRole !== 'super_admin' && $sessionRole !== 'gestionnaire') {
    jerr(403, 'Accès refusé (admin requis).');
}

// Connexion REMOTE (Raspberry) — optionnelle : si indisponible, on utilise uniquement la base locale
require_once __DIR__ . '/../includes/rpi_db.php';
$pdoRemote = null;
try {
    $pdoRemote = getRpiPdo();
    $remoteOk = true;
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

// Charger les noms et capacités des logements
$logements = [];
try {
    $stLog = $conn->query("SELECT id, nom_du_logement, nombre_de_personnes FROM liste_logements");
    $rows = $stLog->fetchAll(PDO::FETCH_ASSOC);
    $stLog->closeCursor();
    foreach ($rows as $lg) {
        $logements[(int)$lg['id']] = [
            'nom' => $lg['nom_du_logement'] ?? ('Logement #'.$lg['id']),
            'capacite' => (int)($lg['nombre_de_personnes'] ?? 0),
        ];
    }
} catch (Throwable $e) { /* ignore */ }

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
            FROM reservation r WHERE r.statut = 'confirmée' AND DATE(r.date_depart) = :d
            ORDER BY r.date_depart ASC
        ";
        $stDeps = $pdoRemote->prepare($sqlDeps);
        $stDeps->execute([':d'=>$target]);
        $deps = $stDeps->fetchAll(PDO::FETCH_ASSOC);
        $sourceLabel = 'REMOTE';
    } catch (Throwable $e) {
        if ($DEBUG) error_log('sync_by_date: remote deps error: '.$e->getMessage());
    }

    try {
        $sqlArrs = "
            SELECT r.id AS resa_id, r.logement_id, DATE(r.date_arrivee) AS date_arrivee,
                   DATE(r.date_depart) AS date_depart,
                   (COALESCE(r.nb_adultes,0)+COALESCE(r.nb_enfants,0)+COALESCE(r.nb_bebes,0)) AS nb_pers,
                   GREATEST(DATEDIFF(r.date_depart, r.date_arrivee), 0) AS nb_jours
            FROM reservation r WHERE r.statut = 'confirmée' AND DATE(r.date_arrivee) = :d
            ORDER BY r.date_arrivee ASC
        ";
        $stArrs = $pdoRemote->prepare($sqlArrs);
        $stArrs->execute([':d'=>$target]);
        $arrs = $stArrs->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        if ($DEBUG) error_log('sync_by_date: remote arrs error: '.$e->getMessage());
    }
}

// 2) Lire/fusionner depuis la base LOCALE
if ($localHasReservation) {
    try {
        $sqlLocalDeps = "
            SELECT r.id AS resa_id, r.logement_id, DATE(r.date_arrivee) AS date_arrivee,
                   DATE(r.date_depart) AS date_depart, {$localNbPersExpr} AS nb_pers,
                   GREATEST(DATEDIFF(r.date_depart, r.date_arrivee), 0) AS nb_jours
            FROM reservation r WHERE r.statut = 'confirmée' AND DATE(r.date_depart) = :d
            ORDER BY r.date_depart ASC
        ";
        $stLD = $conn->prepare($sqlLocalDeps);
        $stLD->execute([':d' => $target]);
        $localDeps = $stLD->fetchAll(PDO::FETCH_ASSOC);
        $stLD->closeCursor();
        $remoteKeys = array_map(fn($d) => $d['logement_id'] . '_' . $d['date_depart'], $deps);
        foreach ($localDeps as $ld) {
            if (!in_array($ld['logement_id'] . '_' . $ld['date_depart'], $remoteKeys)) {
                $ld['_source'] = 'LOCAL';
                $deps[] = $ld;
            }
        }
        $sourceLabel = $sourceLabel ? $sourceLabel.'+LOCAL' : 'LOCAL';
    } catch (Throwable $e) { error_log('sync_reservations_by_date.php: ' . $e->getMessage()); }

    try {
        $sqlLocalArrs = "
            SELECT r.id AS resa_id, r.logement_id, DATE(r.date_arrivee) AS date_arrivee,
                   DATE(r.date_depart) AS date_depart, {$localNbPersExpr} AS nb_pers,
                   GREATEST(DATEDIFF(r.date_depart, r.date_arrivee), 0) AS nb_jours
            FROM reservation r WHERE r.statut = 'confirmée' AND DATE(r.date_arrivee) = :d
            ORDER BY r.date_arrivee ASC
        ";
        $stLA = $conn->prepare($sqlLocalArrs);
        $stLA->execute([':d' => $target]);
        $localArrs = $stLA->fetchAll(PDO::FETCH_ASSOC);
        $stLA->closeCursor();
        $remoteArrKeys = array_map(fn($a) => $a['logement_id'] . '_' . $a['date_arrivee'], $arrs);
        foreach ($localArrs as $la) {
            if (!in_array($la['logement_id'] . '_' . $la['date_arrivee'], $remoteArrKeys)) {
                $la['_source'] = 'LOCAL';
                $arrs[] = $la;
            }
        }
    } catch (Throwable $e) { error_log('sync_reservations_by_date.php: ' . $e->getMessage()); }
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
    } catch (Throwable $e) { error_log('sync_reservations_by_date.php: ' . $e->getMessage()); }
}

// statements LOCAL (dans try-catch car EMULATE_PREPARES=false peut échouer si curseur ouvert)
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

    $findAnyOnDate = $conn->prepare("
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
            :logement_id, :date, :nb_pers, :nb_jours, 'A Faire', :note
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
            :logement_id, :date, :nb_pers, :nb_jours, :statut, :note
            ".($pl_has_src_id ? ", :resa_id" : "")."
            ".($pl_has_src_type ? ", 'AUTO_VERIF'" : "")."
        )
    ");
} catch (Throwable $e) {
    jerr(500, 'Erreur préparation requêtes SQL.', $DEBUG?['ex'=>$e->getMessage()]:[]);
}

// Dernière intervention sur un logement (pour déterminer si "À Vérifier")
$findLastIntervention = $conn->prepare("
    SELECT date FROM planning WHERE logement_id = ? AND date < ? ORDER BY date DESC LIMIT 1
");

// Set des logements qui ont un départ ce jour (pour savoir si checkout existe)
$logementsAvecDepart = [];
foreach ($deps as $d) {
    $logementsAvecDepart[(int)$d['logement_id']] = true;
}

// Charger les tâches actives par logement (pour les inclure dans la note)
$taches_par_logement = [];
try {
    $stmtTaches = $conn->query("SELECT logement_id, description FROM todo_list WHERE statut IN ('en attente', 'en cours') ORDER BY date_limite ASC");
    foreach ($stmtTaches->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $taches_par_logement[(int)$t['logement_id']][] = $t['description'];
    }
} catch (PDOException $e) { error_log('sync_reservations_by_date.php: ' . $e->getMessage()); }

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
        // Fallback : capacité max du logement si nb_pers inconnu
        if ($nbPers === 0 && isset($logements[$logId])) {
            $nbPers = $logements[$logId]['capacite'];
        }
        $nbJours = max(0, (int)$r['nb_jours']);
        // Si nb_pers = 0, utiliser la capacité du logement
        if ($nbPers === 0 && isset($logements[$logId])) {
            $nbPers = $logements[$logId]['capacite'];
        }
        $logNom = $logements[$logId]['nom'] ?? '';
        $note   = "Ménage de sortie" . ($logNom ? " — {$logNom}" : '');

        $existing = null;
        if ($findBySourceCheckout) {
            $findBySourceCheckout->execute([$resaId]);
            $existing = $findBySourceCheckout->fetch(PDO::FETCH_ASSOC) ?: null;
            $findBySourceCheckout->closeCursor();
        }
        if (!$existing) {
            // Chercher par nouveau format
            $findByHeuristic->execute([$logId, $depart, 'Ménage de sortie%']);
            $existing = $findByHeuristic->fetch(PDO::FETCH_ASSOC) ?: null;
            $findByHeuristic->closeCursor();
        }
        if (!$existing) {
            // Rétrocompat ancien format
            $findByHeuristic->execute([$logId, $depart, "Auto: ménage de sortie (resa #{$resaId})%"]);
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

    // 2) ARRIVÉES = $target
    // Logique : PAS de ménage pour un check-in.
    // Si le logement a un check-out le même jour, le ménage de sortie suffit.
    // Si le logement est resté vide 2+ jours sans ménage → "À Vérifier" (vérif poussière).
    $depLogIds = array_map(fn($d) => (int)$d['logement_id'], $deps);

    foreach ($arrs as $r) {
        $resaId  = (int)$r['resa_id'];
        $logId   = (int)$r['logement_id'];
        $nbPers  = max(0, (int)$r['nb_pers']);
        // Fallback : capacité max du logement si nb_pers inconnu
        if ($nbPers === 0 && isset($logements[$logId])) {
            $nbPers = $logements[$logId]['capacite'];
        }
        $nbJours = max(0, (int)$r['nb_jours']);
        $srcLabel = $r['_source'] ?? 'REMOTE';

        // 0) Ce logement a un check-out le même jour → le ménage de sortie couvre, on skip
        if (in_array($logId, $depLogIds)) {
            $skipped++;
            $report['arrivals'][] = [
                'reservation_id'  => $resaId,
                'logement_id'     => $logId,
                'date'            => $target,
                'reason'          => 'checkout_same_day_covers_it',
                'created_now'     => false,
                'intervention_id' => null,
            ];
            continue;
        }

        // 1) Il existe déjà une intervention ce jour pour ce logement → skip
        $findAnyOnDate->execute([$logId, $target]);
        $alreadyExists = $findAnyOnDate->fetch();
        $findAnyOnDate->closeCursor();
        if ($alreadyExists) {
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

        // 2) Vérifier depuis combien de jours le logement n'a pas eu d'intervention
        $findLastIntervention->execute([$logId, $target]);
        $lastRow = $findLastIntervention->fetch(PDO::FETCH_ASSOC);
        $findLastIntervention->closeCursor();
        $lastDate = $lastRow['last_date'] ?? null;

        if ($lastDate) {
            $gapDays = (int)((strtotime($target) - strtotime($lastDate)) / 86400);
        } else {
            $gapDays = 999; // jamais eu d'intervention → considéré comme longtemps
        }

        // Si le logement a été vérifié/nettoyé il y a moins de 2 jours → pas besoin
        if ($gapDays < 2) {
            $skipped++;
            $report['arrivals'][] = [
                'reservation_id'  => $resaId,
                'logement_id'     => $logId,
                'date'            => $target,
                'reason'          => 'recently_cleaned_' . $gapDays . 'd_ago',
                'created_now'     => false,
                'intervention_id' => null,
            ];
            continue;
        }

        // 3) Logement vide 2+ jours → créer "À Vérifier" (vérif poussière, pas ménage complet)
        // Si nb_pers = 0, utiliser la capacité du logement
        if ($nbPers === 0 && isset($logements[$logId])) {
            $nbPers = $logements[$logId]['capacite'];
        }
        $logNom = $logements[$logId]['nom'] ?? '';
        $note = "À vérifier (vide {$gapDays}j)" . ($logNom ? " — {$logNom}" : '');

        // Vérifier si une telle intervention existe déjà
        $existing = null;
        $findByHeuristic->execute([$logId, $target, 'À vérifier%']);
        $existing = $findByHeuristic->fetch(PDO::FETCH_ASSOC) ?: null;
        $findByHeuristic->closeCursor();

        $hadBefore = (bool)$existing;
        $interventionId = $existing['id'] ?? null;
        $createdNow = false;

        if (!$hadBefore) {
            // Checkout ce jour → "À Faire"
            // Pas de checkout → vérifier dernière intervention :
            //   - ≥ 2 jours → "À Vérifier"
            //   - < 2 jours → rien (intervention récente, pas besoin)
            if (isset($logementsAvecDepart[$logId])) {
                $statut = 'À Faire';
            } else {
                $findLastIntervention->execute([$logId, $target]);
                $lastRow = $findLastIntervention->fetch(PDO::FETCH_ASSOC);
                $findLastIntervention->closeCursor();
                if (!$lastRow || (strtotime($target) - strtotime($lastRow['date'])) >= 2 * 86400) {
                    $statut = 'À Vérifier';
                } else {
                    // Intervention récente, pas besoin d'en créer une
                    $skipped++;
                    $report['arrivals'][] = [
                        'reservation_id'         => $resaId,
                        'logement_id'            => $logId,
                        'date'                   => $target,
                        'had_intervention_before'=> false,
                        'created_now'            => false,
                        'intervention_id'        => null,
                    ];
                    continue;
                }
            }

            if (!$DRY_RUN) {
                $params = [
                    ':logement_id'=>$logId,
                    ':date'=>$target,
                    ':nb_pers'=>$nbPers,
                    ':nb_jours'=>$nbJours,
                    ':statut'=>$statut,
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
            'date'                   => $target,
            'gap_days'               => $gapDays,
            'type'                   => 'a_verifier',
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
        'source'    => $sourceLabel ?: 'LOCAL',
        'target'    => 'LOCAL:planning'
    ]);
} catch (Throwable $e) {
    if (!$DRY_RUN && $conn->inTransaction()) $conn->rollBack();
    jerr(500, 'Erreur serveur pendant la synchro.', ['ex'=>$e->getMessage()]);
}
