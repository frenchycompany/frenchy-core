<?php
/**
 * FrenchyBot — Admin QR Codes
 * Genere et gere les QR codes par logement (redirigent vers le HUB de la resa active)
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';

$appUrl = env('APP_URL', 'https://gestion.frenchyconciergerie.fr');

// --- Donnees ---
$logements = $pdo->query("
    SELECT l.id, l.nom_du_logement, l.adresse,
           (SELECT COUNT(*) FROM hub_qr_scans WHERE logement_id = l.id) AS nb_scans,
           (SELECT MAX(scanned_at) FROM hub_qr_scans WHERE logement_id = l.id) AS last_scan
    FROM liste_logements l
    WHERE l.actif = 1
    ORDER BY l.nom_du_logement
")->fetchAll(PDO::FETCH_ASSOC);

// Stats globales
try {
    $totalScans = $pdo->query("SELECT COUNT(*) FROM hub_qr_scans")->fetchColumn();
    $scansToday = $pdo->query("SELECT COUNT(*) FROM hub_qr_scans WHERE DATE(scanned_at) = CURDATE()")->fetchColumn();
    $scansWeek = $pdo->query("SELECT COUNT(*) FROM hub_qr_scans WHERE scanned_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
} catch (\PDOException $e) {
    $totalScans = $scansToday = $scansWeek = 0;
}
?>

<div class="container-fluid py-4">
    <h2 class="mb-4"><i class="fas fa-qrcode text-primary"></i> QR Codes par logement</h2>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold text-primary"><?= $totalScans ?></div>
                    <div class="text-muted small">Scans totaux</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold text-success"><?= $scansToday ?></div>
                    <div class="text-muted small">Scans aujourd'hui</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold text-info"><?= $scansWeek ?></div>
                    <div class="text-muted small">Scans cette semaine</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Info -->
    <div class="alert alert-info mb-4">
        <i class="fas fa-info-circle"></i>
        Chaque QR code redirige vers le HUB de la reservation active du logement.
        S'il n'y a pas de reservation en cours, le voyageur voit une page generique avec les infos wifi.
    </div>

    <!-- Logements -->
    <div class="row g-3">
        <?php foreach ($logements as $l):
            $qrUrl = rtrim($appUrl, '/') . '/frenchybot/hub/qr.php?logement=' . $l['id'];
            $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrUrl);
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header">
                    <strong><i class="fas fa-home text-primary"></i> <?= htmlspecialchars($l['nom_du_logement']) ?></strong>
                </div>
                <div class="card-body text-center">
                    <!-- QR Code via API publique -->
                    <img src="<?= htmlspecialchars($qrImageUrl) ?>" alt="QR Code" class="img-fluid mb-3" style="max-width:200px; border-radius:8px;">

                    <div class="mb-3">
                        <input type="text" class="form-control form-control-sm text-center" value="<?= htmlspecialchars($qrUrl) ?>" readonly onclick="this.select()">
                    </div>

                    <div class="d-flex gap-2 justify-content-center">
                        <button class="btn btn-sm btn-outline-primary" onclick="copyUrl('<?= htmlspecialchars($qrUrl) ?>', this)">
                            <i class="fas fa-copy"></i> Copier URL
                        </button>
                        <a href="<?= htmlspecialchars($qrImageUrl) ?>" download="qr-<?= $l['id'] ?>.png" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-download"></i> Telecharger
                        </a>
                        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </div>
                <div class="card-footer text-muted small">
                    <i class="fas fa-chart-bar"></i> <?= $l['nb_scans'] ?> scans
                    <?php if ($l['last_scan']): ?>
                        | Dernier : <?= date('d/m H:i', strtotime($l['last_scan'])) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Impression en masse -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <h6><i class="fas fa-print"></i> Impression</h6>
            <p class="small text-muted">Pour imprimer tous les QR codes, utilisez la fonction d'impression du navigateur (Ctrl+P). Chaque carte sera imprimee sur une page.</p>
            <button class="btn btn-outline-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Imprimer tous les QR codes
            </button>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .navbar, .alert, .card-footer, .btn { display: none !important; }
    .card { break-inside: avoid; page-break-inside: avoid; margin-bottom: 20px; }
    .card-header, .card-body { display: block !important; }
    .card-body img { max-width: 250px !important; }
}
</style>

<script>
function copyUrl(url, btn) {
    navigator.clipboard.writeText(url).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copie !';
        setTimeout(() => btn.innerHTML = orig, 2000);
    });
}
</script>

