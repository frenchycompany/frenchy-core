<?php
/**
 * Superhôte / Yield Management — Page unifiée
 * Configuration des tarifs dynamiques et automatisation Superhôte
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_bridge.php';

// Récupérer la configuration Superhôte par logement
$configs = [];
try {
    $configs = $pdo->query("
        SELECT l.id, l.nom_du_logement, l.actif,
               sc.id as config_id, sc.superhote_property_id, sc.superhote_property_name,
               sc.is_active, sc.default_price, sc.weekend_price, sc.min_price, sc.max_price,
               sc.auto_sync, sc.last_sync_at
        FROM liste_logements l
        LEFT JOIN superhote_config sc ON l.id = sc.logement_id
        WHERE l.actif = 1
        ORDER BY l.nom_du_logement
    ")->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Mises à jour de prix en attente
$pending_updates = [];
try {
    $pending_updates = $pdo->query("
        SELECT spu.*, l.nom_du_logement
        FROM superhote_price_updates spu
        LEFT JOIN liste_logements l ON spu.logement_id = l.id
        WHERE spu.status IN ('pending', 'processing')
        ORDER BY spu.priority DESC, spu.created_at ASC
        LIMIT 20
    ")->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Historique récent
$recent_history = [];
try {
    $recent_history = $pdo->query("
        SELECT sph.*, l.nom_du_logement
        FROM superhote_price_history sph
        LEFT JOIN liste_logements l ON sph.logement_id = l.id
        ORDER BY sph.created_at DESC
        LIMIT 20
    ")->fetchAll();
} catch (PDOException $e) { /* ignore */ }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superhôte — FrenchyConciergerie</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container-fluid mt-4">
    <h2><i class="fas fa-euro-sign text-primary"></i> Superhôte — Yield Management</h2>
    <p class="text-muted"><?= count($configs) ?> logement(s) configuré(s)</p>

    <!-- Configuration par logement -->
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Configuration des tarifs</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Logement</th><th>ID Superhôte</th><th>Prix défaut</th><th>Prix WE</th><th>Min</th><th>Max</th><th>Auto-sync</th><th>Dernière sync</th><th>Statut</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($configs as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['nom_du_logement']) ?></strong></td>
                            <td><small><?= htmlspecialchars($c['superhote_property_id'] ?? '—') ?></small></td>
                            <td><?= $c['default_price'] ? number_format($c['default_price'], 0) . ' €' : '—' ?></td>
                            <td><?= $c['weekend_price'] ? number_format($c['weekend_price'], 0) . ' €' : '—' ?></td>
                            <td><?= $c['min_price'] ? number_format($c['min_price'], 0) . ' €' : '—' ?></td>
                            <td><?= $c['max_price'] ? number_format($c['max_price'], 0) . ' €' : '—' ?></td>
                            <td><?= !empty($c['auto_sync']) ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-muted"></i>' ?></td>
                            <td><small><?= $c['last_sync_at'] ? date('d/m H:i', strtotime($c['last_sync_at'])) : '—' ?></small></td>
                            <td>
                                <?php if ($c['config_id']): ?>
                                    <span class="badge bg-<?= $c['is_active'] ? 'success' : 'secondary' ?>"><?= $c['is_active'] ? 'Actif' : 'Inactif' ?></span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Non configuré</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Mises à jour en attente -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-clock text-warning"></i> Mises à jour en attente (<?= count($pending_updates) ?>)</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr><th>Logement</th><th>Période</th><th>Prix</th><th>Statut</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pending_updates as $u): ?>
                                <tr>
                                    <td><small><?= htmlspecialchars($u['nom_du_logement'] ?? '') ?></small></td>
                                    <td><small><?= date('d/m', strtotime($u['date_start'])) ?> → <?= date('d/m', strtotime($u['date_end'])) ?></small></td>
                                    <td><strong><?= number_format($u['price'], 0) ?> <?= $u['currency'] ?? '€' ?></strong></td>
                                    <td><span class="badge bg-warning"><?= htmlspecialchars($u['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($pending_updates)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Aucune mise à jour en attente.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historique récent -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-history text-info"></i> Historique récent</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr><th>Date</th><th>Logement</th><th>Prix</th><th>Résultat</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recent_history as $h): ?>
                                <tr>
                                    <td><small><?= date('d/m H:i', strtotime($h['created_at'])) ?></small></td>
                                    <td><small><?= htmlspecialchars($h['nom_du_logement'] ?? '') ?></small></td>
                                    <td><?= number_format($h['price'] ?? $h['new_price'] ?? 0, 0) ?> €</td>
                                    <td><?= !empty($h['success']) ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_history)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Aucun historique.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
