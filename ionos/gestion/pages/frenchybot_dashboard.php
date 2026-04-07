<?php
/**
 * FrenchyBot — Dashboard Tracking Unifie (P7)
 * Vue globale : taux de clic SMS, scans QR, interactions HUB, conversions upsell, revenus
 */
include '../config.php';
include '../pages/menu.php';

// --- Periode ---
$period = $_GET['period'] ?? '30';
$periodDays = in_array($period, ['7','14','30','90','365']) ? (int)$period : 30;
$dateFrom = date('Y-m-d', strtotime("-{$periodDays} days"));
$dateTo = date('Y-m-d');

// --- KPIs principaux ---
try {
    // HUB
    $hubActifs = $pdo->query("SELECT COUNT(*) FROM hub_tokens WHERE active = 1")->fetchColumn();
    $hubTotalViews = $pdo->prepare("SELECT COALESCE(SUM(access_count), 0) FROM hub_tokens WHERE created_at >= ?");
    $hubTotalViews->execute([$dateFrom]);
    $hubTotalViews = $hubTotalViews->fetchColumn();

    // Interactions (hors vues)
    $stmtInter = $pdo->prepare("SELECT COUNT(*) FROM hub_interactions WHERE action_type != 'view' AND created_at >= ?");
    $stmtInter->execute([$dateFrom]);
    $totalInteractions = $stmtInter->fetchColumn();

    // Chat IA
    $stmtChat = $pdo->prepare("SELECT COUNT(*) FROM bot_conversations WHERE role = 'user' AND created_at >= ?");
    $stmtChat->execute([$dateFrom]);
    $totalChats = $stmtChat->fetchColumn();

    // QR Scans
    $stmtQr = $pdo->prepare("SELECT COUNT(*) FROM hub_qr_scans WHERE scanned_at >= ?");
    $stmtQr->execute([$dateFrom]);
    $totalQrScans = $stmtQr->fetchColumn();

    // SMS envoyes (auto-messages)
    $stmtSms = $pdo->prepare("SELECT COUNT(*) FROM auto_messages_log WHERE status = 'sent' AND sent_at >= ?");
    $stmtSms->execute([$dateFrom]);
    $totalSmsSent = $stmtSms->fetchColumn();

    $stmtSmsFailed = $pdo->prepare("SELECT COUNT(*) FROM auto_messages_log WHERE status = 'failed' AND sent_at >= ?");
    $stmtSmsFailed->execute([$dateFrom]);
    $totalSmsFailed = $stmtSmsFailed->fetchColumn();

    // Upsells
    $stmtUpsellRev = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM upsell_orders WHERE status = 'paid' AND paid_at >= ?");
    $stmtUpsellRev->execute([$dateFrom]);
    $upsellRevenue = $stmtUpsellRev->fetchColumn();

    $stmtUpsellCount = $pdo->prepare("SELECT COUNT(*) FROM upsell_orders WHERE status = 'paid' AND paid_at >= ?");
    $stmtUpsellCount->execute([$dateFrom]);
    $upsellPaidCount = $stmtUpsellCount->fetchColumn();

    $stmtUpsellPending = $pdo->prepare("SELECT COUNT(*) FROM upsell_orders WHERE status = 'pending' AND created_at >= ?");
    $stmtUpsellPending->execute([$dateFrom]);
    $upsellPendingCount = $stmtUpsellPending->fetchColumn();

    // Taux de clic SMS -> HUB
    $smsClickRate = ($totalSmsSent > 0) ? round(($hubTotalViews / $totalSmsSent) * 100, 1) : 0;

} catch (\PDOException $e) {
    $hubActifs = $hubTotalViews = $totalInteractions = $totalChats = 0;
    $totalQrScans = $totalSmsSent = $totalSmsFailed = 0;
    $upsellRevenue = $upsellPaidCount = $upsellPendingCount = 0;
    $smsClickRate = 0;
}

// --- Graphique : interactions par jour ---
try {
    $stmtDaily = $pdo->prepare("
        SELECT DATE(created_at) AS jour, action_type, COUNT(*) AS nb
        FROM hub_interactions
        WHERE created_at >= ?
        GROUP BY jour, action_type
        ORDER BY jour ASC
    ");
    $stmtDaily->execute([$dateFrom]);
    $dailyRaw = $stmtDaily->fetchAll(PDO::FETCH_ASSOC);

    $dailyData = [];
    foreach ($dailyRaw as $r) {
        $dailyData[$r['jour']][$r['action_type']] = (int)$r['nb'];
    }
} catch (\PDOException $e) {
    $dailyData = [];
}

// --- Top actions ---
try {
    $stmtTopActions = $pdo->prepare("
        SELECT action_type, COUNT(*) AS nb
        FROM hub_interactions
        WHERE action_type != 'view' AND created_at >= ?
        GROUP BY action_type
        ORDER BY nb DESC
        LIMIT 10
    ");
    $stmtTopActions->execute([$dateFrom]);
    $topActions = $stmtTopActions->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $topActions = [];
}

// --- Top logements par interactions ---
try {
    $stmtTopLog = $pdo->prepare("
        SELECT l.nom_du_logement, COUNT(hi.id) AS nb_interactions,
               COUNT(DISTINCT ht.reservation_id) AS nb_sejours
        FROM hub_interactions hi
        JOIN hub_tokens ht ON hi.hub_token_id = ht.id
        JOIN liste_logements l ON ht.logement_id = l.id
        WHERE hi.created_at >= ?
        GROUP BY l.id
        ORDER BY nb_interactions DESC
        LIMIT 10
    ");
    $stmtTopLog->execute([$dateFrom]);
    $topLogements = $stmtTopLog->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $topLogements = [];
}

// --- Upsells par type ---
try {
    $stmtUpsellBreak = $pdo->prepare("
        SELECT u.label, COUNT(uo.id) AS nb_orders,
               SUM(CASE WHEN uo.status = 'paid' THEN 1 ELSE 0 END) AS nb_paid,
               COALESCE(SUM(CASE WHEN uo.status = 'paid' THEN uo.amount ELSE 0 END), 0) AS revenue
        FROM upsell_orders uo
        JOIN upsells u ON uo.upsell_id = u.id
        WHERE uo.created_at >= ?
        GROUP BY u.id
        ORDER BY revenue DESC
    ");
    $stmtUpsellBreak->execute([$dateFrom]);
    $upsellBreakdown = $stmtUpsellBreak->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $upsellBreakdown = [];
}

// --- Dernieres interactions importantes ---
try {
    $stmtRecent = $pdo->prepare("
        SELECT hi.created_at, hi.action_type, hi.action_data,
               r.prenom, r.nom, r.telephone,
               l.nom_du_logement
        FROM hub_interactions hi
        JOIN hub_tokens ht ON hi.hub_token_id = ht.id
        JOIN reservation r ON hi.reservation_id = r.id
        JOIN liste_logements l ON ht.logement_id = l.id
        WHERE hi.action_type != 'view' AND hi.created_at >= ?
        ORDER BY hi.created_at DESC
        LIMIT 20
    ");
    $stmtRecent->execute([$dateFrom]);
    $recentInteractions = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $recentInteractions = [];
}

$actionLabels = [
    'view' => ['label' => 'Vue HUB', 'badge' => 'bg-light text-dark', 'icon' => 'fa-eye'],
    'cleaning_request' => ['label' => 'Demande menage', 'badge' => 'bg-warning', 'icon' => 'fa-broom'],
    'access_problem' => ['label' => 'Probleme acces', 'badge' => 'bg-danger', 'icon' => 'fa-key'],
    'wifi_help' => ['label' => 'Probleme wifi', 'badge' => 'bg-warning', 'icon' => 'fa-wifi'],
    'checkout_info' => ['label' => 'Infos depart', 'badge' => 'bg-info', 'icon' => 'fa-door-open'],
    'other' => ['label' => 'Autre question', 'badge' => 'bg-primary', 'icon' => 'fa-comment'],
    'chat' => ['label' => 'Chat IA', 'badge' => 'bg-success', 'icon' => 'fa-robot'],
    'upsell_request' => ['label' => 'Demande upsell', 'badge' => 'bg-success', 'icon' => 'fa-shopping-cart'],
];

// Preparer les donnees pour le graphique JS
$chartLabels = [];
$chartViews = [];
$chartActions = [];
$chartChats = [];
$cursor = new DateTime($dateFrom);
$end = new DateTime($dateTo);
while ($cursor <= $end) {
    $d = $cursor->format('Y-m-d');
    $chartLabels[] = $cursor->format('d/m');
    $dayData = $dailyData[$d] ?? [];
    $chartViews[] = $dayData['view'] ?? 0;
    $chartActions[] = array_sum(array_filter($dayData, fn($v, $k) => $k !== 'view' && $k !== 'chat', ARRAY_FILTER_USE_BOTH));
    $chartChats[] = $dayData['chat'] ?? 0;
    $cursor->modify('+1 day');
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-chart-line text-primary"></i> Dashboard FrenchyBot</h2>
        <div class="btn-group">
            <?php foreach (['7' => '7j', '14' => '14j', '30' => '30j', '90' => '3 mois', '365' => '1 an'] as $pv => $pl): ?>
                <a href="?page=frenchybot_dashboard&period=<?= $pv ?>" class="btn btn-sm <?= $period == $pv ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $pl ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- KPIs principaux -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small mb-1"><i class="fas fa-eye"></i> Vues HUB</div>
                    <div class="fs-3 fw-bold text-primary"><?= number_format($hubTotalViews) ?></div>
                    <div class="small text-muted"><?= $hubActifs ?> HUB actifs</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small mb-1"><i class="fas fa-hand-pointer"></i> Interactions</div>
                    <div class="fs-3 fw-bold text-warning"><?= number_format($totalInteractions) ?></div>
                    <div class="small text-muted"><?= $totalChats ?> chats IA</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small mb-1"><i class="fas fa-sms"></i> SMS envoyes</div>
                    <div class="fs-3 fw-bold text-info"><?= number_format($totalSmsSent) ?></div>
                    <div class="small <?= $totalSmsFailed > 0 ? 'text-danger' : 'text-muted' ?>"><?= $totalSmsFailed ?> echec(s)</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small mb-1"><i class="fas fa-euro-sign"></i> Revenus upsell</div>
                    <div class="fs-3 fw-bold text-success"><?= number_format($upsellRevenue, 0, ',', ' ') ?> &euro;</div>
                    <div class="small text-muted"><?= $upsellPaidCount ?> vente(s)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs secondaires -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="small text-muted"><i class="fas fa-qrcode"></i> Scans QR</div>
                    <div class="fs-4 fw-bold"><?= number_format($totalQrScans) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="small text-muted"><i class="fas fa-percentage"></i> Taux clic SMS</div>
                    <div class="fs-4 fw-bold <?= $smsClickRate > 50 ? 'text-success' : ($smsClickRate > 20 ? 'text-warning' : 'text-danger') ?>"><?= $smsClickRate ?>%</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="small text-muted"><i class="fas fa-robot"></i> Questions IA</div>
                    <div class="fs-4 fw-bold"><?= number_format($totalChats) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="small text-muted"><i class="fas fa-clock"></i> Upsells en attente</div>
                    <div class="fs-4 fw-bold text-warning"><?= $upsellPendingCount ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Graphique activite -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-chart-area text-primary"></i> Activite quotidienne</h5>
        </div>
        <div class="card-body">
            <canvas id="activityChart" height="80"></canvas>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Top actions -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-bolt text-warning"></i> Types d'interactions</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                            <?php foreach ($topActions as $ta):
                                $al = $actionLabels[$ta['action_type']] ?? ['label' => $ta['action_type'], 'badge' => 'bg-secondary', 'icon' => 'fa-circle'];
                            ?>
                                <tr>
                                    <td class="ps-3">
                                        <i class="fas <?= $al['icon'] ?> text-muted me-1"></i>
                                        <span class="badge <?= $al['badge'] ?>"><?= $al['label'] ?></span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <strong><?= number_format($ta['nb']) ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($topActions)): ?>
                                <tr><td class="text-center text-muted py-3" colspan="2">Aucune interaction sur la periode.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top logements -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-home text-primary"></i> Top logements</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr><th class="ps-3">Logement</th><th>Sejours</th><th class="text-end pe-3">Interactions</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($topLogements as $tl): ?>
                                <tr>
                                    <td class="ps-3"><?= htmlspecialchars($tl['nom_du_logement']) ?></td>
                                    <td><span class="badge bg-info"><?= $tl['nb_sejours'] ?></span></td>
                                    <td class="text-end pe-3"><strong><?= number_format($tl['nb_interactions']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($topLogements)): ?>
                                <tr><td class="text-center text-muted py-3" colspan="3">Aucune donnee.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upsells breakdown -->
    <?php if (!empty($upsellBreakdown)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-shopping-cart text-success"></i> Performance Upsells</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Upsell</th>
                            <th>Demandes</th>
                            <th>Payees</th>
                            <th>Taux conversion</th>
                            <th class="text-end pe-3">Revenus</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($upsellBreakdown as $ub):
                        $convRate = $ub['nb_orders'] > 0 ? round(($ub['nb_paid'] / $ub['nb_orders']) * 100) : 0;
                    ?>
                        <tr>
                            <td class="ps-3"><strong><?= htmlspecialchars($ub['label']) ?></strong></td>
                            <td><span class="badge bg-info"><?= $ub['nb_orders'] ?></span></td>
                            <td><span class="badge bg-success"><?= $ub['nb_paid'] ?></span></td>
                            <td>
                                <div class="progress" style="height:18px; width:100px;">
                                    <div class="progress-bar <?= $convRate >= 50 ? 'bg-success' : ($convRate >= 20 ? 'bg-warning' : 'bg-danger') ?>" style="width:<?= $convRate ?>%"><?= $convRate ?>%</div>
                                </div>
                            </td>
                            <td class="text-end pe-3 fw-bold text-success"><?= number_format($ub['revenue'], 2, ',', ' ') ?> &euro;</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Dernieres interactions -->
    <div class="card border-0 shadow-sm">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-bell text-danger"></i> Dernieres interactions</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Voyageur</th>
                            <th>Logement</th>
                            <th>Action</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentInteractions as $ri):
                        $al = $actionLabels[$ri['action_type']] ?? ['label' => $ri['action_type'], 'badge' => 'bg-secondary', 'icon' => 'fa-circle'];
                        $details = '';
                        if ($ri['action_data']) {
                            $d = json_decode($ri['action_data'], true);
                            if (isset($d['message'])) $details = mb_substr($d['message'], 0, 80);
                            elseif (isset($d['upsell_name'])) $details = $d['upsell_name'];
                        }
                    ?>
                        <tr>
                            <td class="text-nowrap"><?= date('d/m H:i', strtotime($ri['created_at'])) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($ri['prenom'] . ' ' . ($ri['nom'] ?? '')) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($ri['telephone'] ?? '') ?></small>
                            </td>
                            <td><?= htmlspecialchars($ri['nom_du_logement']) ?></td>
                            <td><span class="badge <?= $al['badge'] ?>"><?= $al['label'] ?></span></td>
                            <td class="small text-muted"><?= htmlspecialchars($details) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentInteractions)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucune interaction sur la periode.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('activityChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
            {
                label: 'Vues HUB',
                data: <?= json_encode($chartViews) ?>,
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59,130,246,0.08)',
                fill: true,
                tension: 0.3,
                pointRadius: 2,
            },
            {
                label: 'Interactions',
                data: <?= json_encode($chartActions) ?>,
                borderColor: '#F59E0B',
                backgroundColor: 'rgba(245,158,11,0.08)',
                fill: true,
                tension: 0.3,
                pointRadius: 2,
            },
            {
                label: 'Chats IA',
                data: <?= json_encode($chartChats) ?>,
                borderColor: '#10B981',
                backgroundColor: 'rgba(16,185,129,0.08)',
                fill: true,
                tension: 0.3,
                pointRadius: 2,
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } },
            x: { ticks: { maxTicksLimit: 15 } }
        },
        plugins: {
            legend: { position: 'top' }
        }
    }
});
</script>
