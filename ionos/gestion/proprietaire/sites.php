<?php
/**
 * Espace Propriétaire - Sites vitrine
 */
require_once __DIR__ . '/auth.php';

$sites = [];
if (!empty($logement_ids)) {
    try {
        $stmt = $conn->prepare("SELECT fi.*, l.nom_du_logement
            FROM frenchysite_instances fi
            JOIN liste_logements l ON fi.logement_id = l.id
            WHERE fi.logement_id IN ($placeholders) AND fi.actif = 1
            ORDER BY fi.site_name");
        $stmt->execute($logement_ids);
        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sites vitrine - Espace Proprietaire</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="proprio.css">
    <style>
        .site-card {
            background: white; border: 1px solid #E5E7EB; border-radius: 12px;
            padding: 1.5rem; margin-bottom: 1rem;
            display: flex; align-items: center; gap: 1.5rem;
            transition: box-shadow 0.2s;
        }
        .site-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .site-icon {
            width: 60px; height: 60px; border-radius: 12px;
            background: linear-gradient(135deg, #3B82F6, #1E3A8A);
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 1.5rem; flex-shrink: 0;
        }
        .site-info { flex: 1; }
        .site-info h3 { color: #1F2937; margin-bottom: 0.3rem; }
        .site-info p { color: #6B7280; font-size: 0.9rem; }
        .site-actions a {
            display: inline-block; padding: 8px 20px; border-radius: 8px;
            background: #3B82F6; color: white; text-decoration: none;
            font-weight: 600; font-size: 0.9rem; transition: background 0.2s;
        }
        .site-actions a:hover { background: #1E3A8A; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php proprioSidebar($proprietaire, $currentPage, $has_sites); ?>

    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-globe"></i> Sites vitrine</h1>
        </div>

        <?php if (empty($sites)): ?>
            <div class="card"><p class="empty-state">Aucun site vitrine associe a vos logements.</p></div>
        <?php else: ?>
            <?php foreach ($sites as $site): ?>
            <div class="site-card">
                <div class="site-icon"><i class="fas fa-globe"></i></div>
                <div class="site-info">
                    <h3><?= e($site['site_name']) ?></h3>
                    <p><i class="fas fa-home"></i> <?= e($site['nom_du_logement']) ?></p>
                    <?php if (!empty($site['site_url'])): ?>
                        <p><i class="fas fa-link"></i> <?= e($site['site_url']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="site-actions">
                    <?php if (!empty($site['site_url'])): ?>
                        <a href="<?= e($site['site_url']) ?>" target="_blank" rel="noopener">
                            <i class="fas fa-external-link-alt"></i> Voir le site
                        </a>
                    <?php else: ?>
                        <span class="badge badge-warning">En construction</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
