<?php
/**
 * Analyse de la concurrence — Page unifiée
 * Comparaison des prix avec les concurrents par logement
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_bridge.php';

// Récupérer les logements avec concurrents
$logements = [];
try {
    $logements = $pdo->query("
        SELECT l.id, l.nom_du_logement,
               COUNT(DISTINCT mc.id) as nb_concurrents,
               AVG(mp.price) as prix_moyen_concurrent,
               sc.default_price as notre_prix
        FROM liste_logements l
        LEFT JOIN market_competitors mc ON l.id = mc.logement_id
        LEFT JOIN market_prices mp ON mc.id = mp.competitor_id AND mp.date_collected >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        LEFT JOIN superhote_config sc ON l.id = sc.logement_id
        WHERE l.actif = 1
        GROUP BY l.id
        ORDER BY l.nom_du_logement
    ")->fetchAll();
} catch (PDOException $e) { /* ignore */ }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Concurrence — FrenchyConciergerie</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container-fluid mt-4">
    <h2><i class="fas fa-chart-bar text-primary"></i> Analyse concurrentielle</h2>
    <p class="text-muted">Comparaison des prix sur 30 jours par logement</p>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Logement</th><th>Notre prix</th><th>Prix moyen concurrence</th><th>Écart</th><th>Concurrents</th><th>Position</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logements as $l): ?>
                        <?php
                        $notre_prix = $l['notre_prix'] ?? 0;
                        $prix_concurrent = $l['prix_moyen_concurrent'] ?? 0;
                        $ecart = $notre_prix && $prix_concurrent ? round((($notre_prix - $prix_concurrent) / $prix_concurrent) * 100, 1) : null;
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($l['nom_du_logement']) ?></strong></td>
                            <td><?= $notre_prix ? number_format($notre_prix, 0) . ' €' : '<span class="text-muted">N/C</span>' ?></td>
                            <td><?= $prix_concurrent ? number_format($prix_concurrent, 0) . ' €' : '<span class="text-muted">N/C</span>' ?></td>
                            <td>
                                <?php if ($ecart !== null): ?>
                                    <span class="badge bg-<?= $ecart > 0 ? 'danger' : ($ecart < -5 ? 'success' : 'warning') ?>">
                                        <?= ($ecart > 0 ? '+' : '') . $ecart ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-info"><?= $l['nb_concurrents'] ?></span></td>
                            <td>
                                <?php if ($ecart !== null): ?>
                                    <?= $ecart > 5 ? '<span class="text-danger">Au-dessus du marché</span>' : ($ecart < -5 ? '<span class="text-success">Compétitif</span>' : '<span class="text-warning">Dans la moyenne</span>') ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
