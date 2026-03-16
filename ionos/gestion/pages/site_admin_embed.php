<?php
/**
 * Wrapper page: embeds a frenchysite admin inside the gestion panel
 * so the sidebar menu remains visible.
 */
include '../config.php';
include '../pages/menu.php';

$siteId = (int)($_GET['site_id'] ?? 0);
$bridgeToken = $_GET['bridge_token'] ?? '';

if (!$siteId || !$bridgeToken) {
    echo "<div class='fc-main'><div class='container mt-4'><div class='alert alert-danger'>Parametres manquants.</div></div></div>";
    exit;
}

// Recuperer l'URL du site
$siteUrl = '';
$siteName = '';
try {
    $stmt = $conn->prepare("SELECT site_url, nom_du_logement FROM frenchysite_instances s LEFT JOIN liste_logements l ON s.logement_id = l.id WHERE s.id = :id");
    $stmt->execute([':id' => $siteId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $siteUrl = $row['site_url'];
        $siteName = $row['nom_du_logement'] ?? '';
    }
} catch (PDOException $e) {}

if (!$siteUrl) {
    echo "<div class='fc-main'><div class='container mt-4'><div class='alert alert-danger'>Site introuvable.</div></div></div>";
    exit;
}

$adminUrl = rtrim($siteUrl, '/') . '/admin.php?bridge_token=' . urlencode($bridgeToken);
?>
<div class="fc-main">
    <div class="container-fluid mt-2">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0"><i class="fas fa-globe"></i> Admin site vitrine <?= htmlspecialchars($siteName) ?></h5>
            <div>
                <a href="<?= htmlspecialchars($adminUrl) ?>" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fas fa-external-link-alt"></i> Ouvrir en plein ecran</a>
                <a href="?page=pages/sites.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Retour</a>
            </div>
        </div>
        <iframe src="<?= htmlspecialchars($adminUrl) ?>" style="width:100%;height:calc(100vh - 120px);border:1px solid #dee2e6;border-radius:8px;" allowfullscreen></iframe>
    </div>
</div>
