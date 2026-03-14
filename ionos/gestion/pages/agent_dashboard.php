<?php
/**
 * Agent Com — Dashboard unifie
 * Suivi des actions agents (SMS, reponses, upsell)
 * Integre dans l'interface FrenchyConciergerie
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_db.php';

$pdo_rpi = getRpiPdo();

// Fonctions utilitaires
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$intervenant_id = (int)($_SESSION['id_intervenant'] ?? $_SESSION['user_id'] ?? 0);
$is_admin = ($_SESSION['role'] ?? '') === 'admin';

// Tables requises : voir db/install_tables.php

// Tarifs
$rates = [];
try {
    $stmt = $pdo_rpi->query("SELECT action_type, rate_eur FROM agent_action_rates");
    foreach ($stmt as $row) {
        $rates[(string)$row['action_type']] = (float)$row['rate_eur'];
    }
} catch (PDOException $e) {
    error_log('agent_dashboard.php: ' . $e->getMessage());
}

$actionTypesDefault = [
    "SMS_envoye", "SMS_recu_repondu", "Airbnb_reponse",
    "Booking_reponse", "Pulse_reponse", "Demande_enregistree",
    "Relance_upsell", "Autre"
];
$actionTypes = !empty($rates) ? array_keys($rates) : $actionTypesDefault;

// Reservations recentes
$reservations = [];
try {
    $reservations = $pdo_rpi->query("
        SELECT r.id, r.reference, r.prenom, r.nom, r.telephone, r.plateforme,
               r.logement_id, r.date_arrivee, r.date_depart, l.nom_du_logement
        FROM reservation r
        LEFT JOIN liste_logements l ON l.id = r.logement_id
        WHERE (r.statut = 'confirmée' OR r.statut IS NULL)
          AND (r.date_arrivee IS NULL OR r.date_arrivee >= DATE_SUB(CURDATE(), INTERVAL 7 DAY))
        ORDER BY (r.date_arrivee IS NULL) ASC, r.date_arrivee ASC, r.id DESC
        LIMIT 250
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('agent_dashboard.php: ' . $e->getMessage()); }

$feedback = '';

// Enregistrer une action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_action'])) {
    validateCsrfToken();

    $action_type = trim($_POST['action_type'] ?? 'Autre');
    $channel = $_POST['channel'] ?? 'autre';
    $reservation_ref = trim($_POST['reservation_ref'] ?? '');
    $logement = trim($_POST['logement'] ?? '');
    $client_name = trim($_POST['client_name'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!in_array($action_type, $actionTypes, true)) $action_type = "Autre";

    try {
        $pdo_rpi->prepare("
            INSERT INTO agent_actions (agent_user_id, action_type, channel, reservation_ref, logement, client_name, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $intervenant_id,
            $action_type,
            $channel,
            $reservation_ref ?: null,
            $logement ?: null,
            $client_name ?: null,
            $notes ?: null,
        ]);
        $feedback = "<div class='alert alert-success alert-dismissible fade show'><i class='fas fa-check-circle'></i> Action enregistree <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } catch (PDOException $e) {
        $feedback = "<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Gestion tarifs (admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_rates']) && $is_admin) {
    validateCsrfToken();
    foreach ($_POST['rate'] ?? [] as $type => $rate) {
        try {
            $pdo_rpi->prepare("INSERT INTO agent_action_rates (action_type, rate_eur) VALUES (?, ?) ON DUPLICATE KEY UPDATE rate_eur = ?")
                ->execute([$type, (float)$rate, (float)$rate]);
        } catch (PDOException $e) { error_log('agent_dashboard.php: ' . $e->getMessage()); }
    }
    // Recharger
    $rates = [];
    $stmt = $pdo_rpi->query("SELECT action_type, rate_eur FROM agent_action_rates");
    foreach ($stmt as $row) {
        $rates[(string)$row['action_type']] = (float)$row['rate_eur'];
    }
    $feedback = "<div class='alert alert-success'>Tarifs mis a jour</div>";
}

// Reporting periode
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-t');

$agentFilter = $is_admin ? (int)($_GET['agent'] ?? 0) : $intervenant_id;

$sql = "SELECT a.* FROM agent_actions a WHERE a.created_at BETWEEN ? AND ? ";
$params = [$from . " 00:00:00", $to . " 23:59:59"];
if ($agentFilter > 0) {
    $sql .= " AND a.agent_user_id = ?";
    $params[] = $agentFilter;
}
$sql .= " ORDER BY a.id DESC LIMIT 200";

$rows = [];
try {
    $stmt = $pdo_rpi->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('agent_dashboard.php: ' . $e->getMessage()); }

$totalActions = count($rows);
$totalEur = 0.0;
foreach ($rows as $r) {
    $totalEur += (float)($rates[(string)$r['action_type']] ?? 0.0);
}

// Stats par type
$statsByType = [];
foreach ($rows as $r) {
    $t = $r['action_type'];
    if (!isset($statsByType[$t])) $statsByType[$t] = ['count' => 0, 'eur' => 0];
    $statsByType[$t]['count']++;
    $statsByType[$t]['eur'] += (float)($rates[$t] ?? 0);
}
arsort($statsByType);

// Liste agents (admin)
$agents = [];
if ($is_admin) {
    try {
        $agents = $pdo_rpi->query("SELECT DISTINCT agent_user_id FROM agent_actions ORDER BY agent_user_id")->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) { error_log('agent_dashboard.php: ' . $e->getMessage()); }
}

// Liens outils
$links = [
    "SuperHote Calendrier" => "https://app.superhote.com/#/calendar/month",
    "SuperHote Messagerie" => "https://app.superhote.com/#/messages",
    "Planning Frenchy"     => "planning.php",
    "Reservations"         => "reservations.php",
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Dashboard — FrenchyConciergerie</title>
    <style>
        .kpi-card { text-align: center; }
        .kpi-card .h3 { margin-bottom: 0; }
        .action-form .form-label { font-size: 0.85em; margin-bottom: 2px; }
    </style>
</head>
<body>
<div class="container-fluid mt-3">

    <?= $feedback ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2><i class="fas fa-headset"></i> Agent Dashboard</h2>
            <p class="text-muted mb-0">Suivi des actions et remuneration</p>
        </div>
        <div class="d-flex gap-2">
            <?php foreach ($links as $lbl => $url): ?>
            <a href="<?= h($url) ?>" <?= str_starts_with($url, 'http') ? 'target="_blank"' : '' ?> class="btn btn-outline-secondary btn-sm">
                <?= h($lbl) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card kpi-card">
                <div class="card-body py-2">
                    <div class="h3 text-primary"><?= $totalActions ?></div>
                    <small class="text-muted">Actions (periode)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card">
                <div class="card-body py-2">
                    <div class="h3 text-success"><?= number_format($totalEur, 2) ?> &euro;</div>
                    <small class="text-muted">A payer</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card">
                <div class="card-body py-2">
                    <div class="h3"><?= count($statsByType) ?></div>
                    <small class="text-muted">Types d'actions</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card">
                <div class="card-body py-2">
                    <div class="h3"><?= count($reservations) ?></div>
                    <small class="text-muted">Reservations actives</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Colonne gauche : Enregistrer action -->
        <div class="col-md-4">
            <div class="card mb-3 action-form">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-plus-circle"></i> Enregistrer une action</h6></div>
                <div class="card-body">
                    <form method="POST">
                        <?php echoCsrfField(); ?>

                        <div class="mb-2">
                            <label class="form-label">Reservation</label>
                            <select id="reservation_select" class="form-select form-select-sm">
                                <option value="">-- Choisir (auto-remplissage) --</option>
                                <?php foreach ($reservations as $r):
                                    $ref = $r['reference'] ?? '';
                                    $fullName = trim(($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? ''));
                                    $arr = $r['date_arrivee'] ?? '';
                                    $dep = $r['date_depart'] ?? '';
                                    $logName = $r['nom_du_logement'] ?? '';
                                    $label = ($ref ? "$ref — " : '') . ($fullName ?: 'Client') . ($arr ? " ($arr)" : '') . ($logName ? " — $logName" : '');
                                ?>
                                <option value="<?= h((string)$r['id']) ?>"
                                        data-reference="<?= h($ref) ?>"
                                        data-client="<?= h($fullName) ?>"
                                        data-logement="<?= h($logName) ?>"
                                        data-plateforme="<?= h($r['plateforme'] ?? '') ?>"
                                        data-tel="<?= h($r['telephone'] ?? '') ?>"
                                        data-arr="<?= h($arr) ?>"
                                        data-dep="<?= h($dep) ?>"
                                ><?= h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row mb-2">
                            <div class="col">
                                <label class="form-label">Type d'action</label>
                                <select name="action_type" class="form-select form-select-sm" required>
                                    <?php foreach ($actionTypes as $t): ?>
                                    <option value="<?= h($t) ?>"><?= h($t) ?> (<?= number_format($rates[$t] ?? 0, 2) ?>&euro;)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col">
                                <label class="form-label">Canal</label>
                                <select name="channel" class="form-select form-select-sm" required>
                                    <option value="sms">SMS</option>
                                    <option value="airbnb">Airbnb</option>
                                    <option value="booking">Booking</option>
                                    <option value="pulse">Pulse</option>
                                    <option value="autre">Autre</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col">
                                <label class="form-label">Ref reservation</label>
                                <input type="text" name="reservation_ref" id="reservation_ref" class="form-control form-control-sm" placeholder="AB-123">
                            </div>
                            <div class="col">
                                <label class="form-label">Logement</label>
                                <input type="text" name="logement" id="logement" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Nom client</label>
                            <input type="text" name="client_name" id="client_name" class="form-control form-control-sm">
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" id="notes" class="form-control form-control-sm" rows="3" placeholder="Resume + reponse + prochaine action"></textarea>
                        </div>

                        <button type="submit" name="save_action" value="1" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-check"></i> Valider l'action
                        </button>
                    </form>
                </div>
            </div>

            <!-- Stats par type -->
            <?php if (!empty($statsByType)): ?>
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-chart-pie"></i> Repartition</h6></div>
                <div class="card-body p-2">
                    <table class="table table-sm mb-0">
                        <?php foreach ($statsByType as $type => $s): ?>
                        <tr>
                            <td class="small"><?= h($type) ?></td>
                            <td class="text-center"><span class="badge bg-primary"><?= $s['count'] ?></span></td>
                            <td class="text-end small"><?= number_format($s['eur'], 2) ?>&euro;</td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Gestion tarifs (admin) -->
            <?php if ($is_admin): ?>
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-cog"></i> Tarifs (admin)</h6></div>
                <div class="card-body">
                    <form method="POST">
                        <?php echoCsrfField(); ?>
                        <?php foreach ($actionTypesDefault as $t): ?>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="flex-grow-1 small"><?= h($t) ?></span>
                            <input type="number" step="0.01" name="rate[<?= h($t) ?>]" class="form-control form-control-sm" style="width:80px" value="<?= number_format($rates[$t] ?? 0, 2, '.', '') ?>">
                            <span class="small">&euro;</span>
                        </div>
                        <?php endforeach; ?>
                        <button type="submit" name="save_rates" class="btn btn-sm btn-outline-primary w-100 mt-2">
                            <i class="fas fa-save"></i> Enregistrer tarifs
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Colonne droite : Historique -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-history"></i> Historique des actions</h6>
                        <form method="GET" class="d-flex gap-2 align-items-end">
                            <input type="date" name="from" value="<?= h($from) ?>" class="form-control form-control-sm" style="width:140px">
                            <input type="date" name="to" value="<?= h($to) ?>" class="form-control form-control-sm" style="width:140px">
                            <?php if ($is_admin && !empty($agents)): ?>
                            <select name="agent" class="form-select form-select-sm" style="width:120px">
                                <option value="0">Tous</option>
                                <?php foreach ($agents as $aid): ?>
                                <option value="<?= $aid ?>" <?= $agentFilter == $aid ? 'selected' : '' ?>>Agent #<?= $aid ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i></button>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Action</th>
                                    <th>Canal</th>
                                    <th>Ref / Logement</th>
                                    <th>Client / Notes</th>
                                    <th class="text-end">&euro;</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r):
                                    $eur = (float)($rates[(string)$r['action_type']] ?? 0);
                                ?>
                                <tr>
                                    <td class="small text-nowrap"><?= date('d/m H:i', strtotime($r['created_at'])) ?></td>
                                    <td><span class="badge bg-secondary"><?= h($r['action_type']) ?></span></td>
                                    <td class="small"><?= h($r['channel']) ?></td>
                                    <td class="small">
                                        <?= h($r['reservation_ref'] ?? '') ?>
                                        <?php if ($r['logement']): ?><br><span class="text-muted"><?= h($r['logement']) ?></span><?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?php if ($r['client_name']): ?><strong><?= h($r['client_name']) ?></strong><br><?php endif; ?>
                                        <span class="text-muted"><?= nl2br(h($r['notes'] ?? '')) ?></span>
                                    </td>
                                    <td class="text-end small"><?= number_format($eur, 2) ?>&euro;</td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($rows)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">Aucune action sur cette periode</td></tr>
                                <?php endif; ?>
                            </tbody>
                            <?php if ($totalActions > 0): ?>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="5" class="text-end"><strong>Total</strong></td>
                                    <td class="text-end"><strong><?= number_format($totalEur, 2) ?>&euro;</strong></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const sel = document.getElementById('reservation_select');
    const ref = document.getElementById('reservation_ref');
    const logement = document.getElementById('logement');
    const client = document.getElementById('client_name');
    const notes = document.getElementById('notes');
    if(!sel) return;

    let notesTouched = false;
    if(notes) notes.addEventListener('input', () => { notesTouched = true; });

    sel.addEventListener('change', function(){
        const opt = sel.options[sel.selectedIndex];
        if(!opt || !opt.value) return;

        if(opt.dataset.reference) ref.value = opt.dataset.reference;
        if(opt.dataset.logement) logement.value = opt.dataset.logement;
        if(opt.dataset.client) client.value = opt.dataset.client;

        if(notes && !notesTouched) {
            const parts = [];
            if(opt.dataset.plateforme) parts.push('Plateforme: ' + opt.dataset.plateforme);
            if(opt.dataset.tel) parts.push('Tel: ' + opt.dataset.tel);
            if(opt.dataset.arr) parts.push('Sejour: ' + opt.dataset.arr + ' → ' + (opt.dataset.dep || '?'));
            notes.value = parts.join(' | ');
        }
    });
})();
</script>

</body>
</html>
