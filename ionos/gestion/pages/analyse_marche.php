<?php
/**
 * Analyse de marché — Page unifiée
 * Capture et suivi des concurrents Airbnb/Booking
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_bridge.php';

// Récupérer les concurrents
$competitors = [];
try {
    $competitors = $pdo->query("
        SELECT mc.*, l.nom_du_logement,
               (SELECT COUNT(*) FROM market_prices mp WHERE mp.competitor_id = mc.id) as nb_prix,
               (SELECT MAX(mp.date_collected) FROM market_prices mp WHERE mp.competitor_id = mc.id) as dernier_prix
        FROM market_competitors mc
        LEFT JOIN liste_logements l ON mc.logement_id = l.id
        ORDER BY l.nom_du_logement, mc.name
    ")->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Derniers prix collectés
$recent_prices = [];
try {
    $recent_prices = $pdo->query("
        SELECT mp.*, mc.name as competitor_name, mc.platform
        FROM market_prices mp
        JOIN market_competitors mc ON mp.competitor_id = mc.id
        ORDER BY mp.date_collected DESC
        LIMIT 50
    ")->fetchAll();
} catch (PDOException $e) { /* ignore */ }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analyse de marché — FrenchyConciergerie</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container-fluid mt-4">
    <h2><i class="fas fa-chart-line text-primary"></i> Analyse de marché</h2>
    <p class="text-muted"><?= count($competitors) ?> concurrent(s) suivis</p>

    <!-- Concurrents -->
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Concurrents suivis</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Nom</th><th>Plateforme</th><th>Logement lié</th><th>Nb prix</th><th>Dernier prix</th><th>URL</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($competitors as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['name'] ?? '') ?></strong></td>
                            <td><span class="badge bg-info"><?= htmlspecialchars($c['platform'] ?? '') ?></span></td>
                            <td><?= htmlspecialchars($c['nom_du_logement'] ?? '—') ?></td>
                            <td><span class="badge bg-secondary"><?= $c['nb_prix'] ?? 0 ?></span></td>
                            <td><small><?= $c['dernier_prix'] ? date('d/m/Y', strtotime($c['dernier_prix'])) : '—' ?></small></td>
                            <td><?php if (!empty($c['url'])): ?><a href="<?= htmlspecialchars($c['url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt"></i></a><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($competitors)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Aucun concurrent configuré.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Derniers prix -->
    <?php if (!empty($recent_prices)): ?>
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Derniers prix collectés</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Date collecte</th><th>Concurrent</th><th>Plateforme</th><th>Date nuitée</th><th>Prix</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent_prices as $p): ?>
                        <tr>
                            <td><small><?= date('d/m H:i', strtotime($p['date_collected'])) ?></small></td>
                            <td><?= htmlspecialchars($p['competitor_name'] ?? '') ?></td>
                            <td><span class="badge bg-info"><?= htmlspecialchars($p['platform'] ?? '') ?></span></td>
                            <td><?= !empty($p['date_nuit']) ? date('d/m/Y', strtotime($p['date_nuit'])) : '—' ?></td>
                            <td><strong><?= number_format($p['price'] ?? 0, 0) ?> €</strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
