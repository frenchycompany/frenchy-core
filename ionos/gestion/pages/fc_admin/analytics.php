<?php
/** Analytics — Page FC Admin */
try {
    $totalVisites = $conn->query("SELECT COUNT(*) FROM FC_visites")->fetchColumn();
    $visitesAujourdhui = $conn->query("SELECT COUNT(*) FROM FC_visites WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $visitesSemaine = $conn->query("SELECT COUNT(*) FROM FC_visites WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $visitesMois = $conn->query("SELECT COUNT(*) FROM FC_visites WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

    $topPages = $conn->query("SELECT page, COUNT(*) as nb FROM FC_visites GROUP BY page ORDER BY nb DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    $visitesParJour = $conn->query("SELECT DATE(created_at) as jour, COUNT(*) as nb FROM FC_visites WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY jour ORDER BY jour ASC")->fetchAll(PDO::FETCH_ASSOC);

    $totalConversions = $conn->query("SELECT COUNT(*) FROM FC_conversions")->fetchColumn();
    $conversionsMois = $conn->query("SELECT COUNT(*) FROM FC_conversions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $conversionsTypes = $conn->query("SELECT type, COUNT(*) as nb FROM FC_conversions GROUP BY type ORDER BY nb DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $totalVisites = $visitesAujourdhui = $visitesSemaine = $visitesMois = 0;
    $topPages = $visitesParJour = $conversionsTypes = [];
    $totalConversions = $conversionsMois = 0;
}
?>
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card text-center p-3"><h2 class="text-primary"><?= $visitesAujourdhui ?></h2><small>Aujourd'hui</small></div></div>
    <div class="col-md-3"><div class="card text-center p-3"><h2 class="text-info"><?= $visitesSemaine ?></h2><small>7 derniers jours</small></div></div>
    <div class="col-md-3"><div class="card text-center p-3"><h2 class="text-success"><?= $visitesMois ?></h2><small>30 derniers jours</small></div></div>
    <div class="col-md-3"><div class="card text-center p-3"><h2 class="text-warning"><?= $totalVisites ?></h2><small>Total visites</small></div></div>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-2"><div class="card text-center p-3"><h3 class="text-danger"><?= $totalConversions ?></h3><small>Conversions totales</small></div></div>
    <div class="col-md-2"><div class="card text-center p-3"><h3 class="text-success"><?= $conversionsMois ?></h3><small>Conversions / mois</small></div></div>
    <?php foreach ($conversionsTypes as $ct): ?>
    <div class="col-md-2"><div class="card text-center p-3"><h3><?= $ct['nb'] ?></h3><small><?= e($ct['type']) ?></small></div></div>
    <?php endforeach; ?>
</div>
<div class="row g-3">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white"><h6 class="mb-0"><i class="fas fa-chart-line"></i> Visites (30 derniers jours)</h6></div>
            <div class="card-body">
                <?php if (!empty($visitesParJour)): ?>
                <div style="display:flex;align-items:flex-end;gap:2px;height:200px;">
                    <?php
                    $maxVisites = max(array_column($visitesParJour, 'nb'));
                    foreach ($visitesParJour as $v):
                        $pct = $maxVisites > 0 ? ($v['nb'] / $maxVisites) * 100 : 0;
                    ?>
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;" title="<?= $v['jour'] ?>: <?= $v['nb'] ?> visites">
                        <small style="font-size:0.6rem;writing-mode:vertical-rl;margin-bottom:4px;"><?= $v['nb'] ?></small>
                        <div style="width:100%;background:linear-gradient(180deg,#3B82F6,#1E3A8A);border-radius:3px 3px 0 0;height:<?= max($pct, 2) ?>%;"></div>
                        <small style="font-size:0.5rem;margin-top:2px;"><?= date('d/m', strtotime($v['jour'])) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted text-center">Aucune donnee de visite.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white"><h6 class="mb-0"><i class="fas fa-trophy"></i> Pages populaires</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Page</th><th>Visites</th></tr></thead>
                    <tbody>
                    <?php foreach ($topPages as $tp): ?>
                        <tr><td><small><?= e($tp['page'] ?: '/') ?></small></td><td><strong><?= $tp['nb'] ?></strong></td></tr>
                    <?php endforeach; ?>
                    <?php if (empty($topPages)): ?><tr><td colspan="2" class="text-center text-muted">Aucune donnee</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
