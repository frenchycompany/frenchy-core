<?php
/**
 * Espace Propriétaire - Résultats des checkups
 */
require_once __DIR__ . '/auth.php';

$checkups = [];
if (!empty($logement_ids)) {
    try {
        $stmt = $conn->prepare("SELECT cs.*, l.nom_du_logement
            FROM checkup_sessions cs
            JOIN liste_logements l ON cs.logement_id = l.id
            WHERE cs.logement_id IN ($placeholders)
            ORDER BY cs.created_at DESC
            LIMIT 50");
        $stmt->execute($logement_ids);
        $checkups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

// Détail d'un checkup
$detail = null;
$detail_items = [];
if (!empty($_GET['id'])) {
    $checkup_id = (int)$_GET['id'];
    // Vérifier que le checkup appartient à un logement du propriétaire
    try {
        $stmt = $conn->prepare("SELECT cs.*, l.nom_du_logement
            FROM checkup_sessions cs
            JOIN liste_logements l ON cs.logement_id = l.id
            WHERE cs.id = ? AND cs.logement_id IN ($placeholders)");
        $stmt->execute(array_merge([$checkup_id], $logement_ids));
        $detail = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($detail) {
            // Récupérer les items du checkup
            try {
                $stmt = $conn->prepare("SELECT * FROM checkup_items WHERE session_id = ? ORDER BY id");
                $stmt->execute([$checkup_id]);
                $detail_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {}
        }
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkups - Espace Proprietaire</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="proprio.css">
    <style>
        .score-bar { height: 8px; border-radius: 4px; background: #E5E7EB; overflow: hidden; margin-top: 4px; }
        .score-fill { height: 100%; border-radius: 4px; }
        .score-good { background: #10B981; }
        .score-warn { background: #F59E0B; }
        .score-bad { background: #EF4444; }
        .checkup-detail { margin-top: 1rem; }
        .item-row { display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0; border-bottom: 1px solid #F3F4F6; }
        .item-row:last-child { border-bottom: none; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php proprioSidebar($proprietaire, $currentPage, $has_sites); ?>

    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-clipboard-check"></i> Checkups</h1>
            <?php if ($detail): ?>
                <a href="checkups.php" style="color:#3B82F6; text-decoration:none;">&larr; Retour a la liste</a>
            <?php endif; ?>
        </div>

        <?php if ($detail): ?>
            <!-- Détail d'un checkup -->
            <div class="card">
                <div class="card-header">
                    <h2><?= e($detail['nom_du_logement']) ?></h2>
                    <span style="color:#6B7280;"><?= date('d/m/Y H:i', strtotime($detail['created_at'])) ?></span>
                </div>

                <div class="stats-grid" style="margin-bottom:1.5rem;">
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fas fa-check"></i></div>
                        <div class="stat-content">
                            <h3><?= (int)$detail['nb_ok'] ?></h3>
                            <p>OK</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background:rgba(239,68,68,0.1);color:#EF4444;"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="stat-content">
                            <h3><?= (int)$detail['nb_problemes'] ?></h3>
                            <p>Problemes</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background:rgba(107,114,128,0.1);color:#6B7280;"><i class="fas fa-minus-circle"></i></div>
                        <div class="stat-content">
                            <h3><?= (int)$detail['nb_absents'] ?></h3>
                            <p>Absents</p>
                        </div>
                    </div>
                </div>

                <?php if (!empty($detail['commentaire_general'])): ?>
                <div style="background:#F3F4F6; padding:1rem; border-radius:8px; margin-bottom:1rem;">
                    <strong>Commentaire :</strong> <?= e($detail['commentaire_general']) ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($detail_items)): ?>
                <div class="checkup-detail">
                    <?php foreach ($detail_items as $item): ?>
                    <div class="item-row">
                        <span><?= e($item['nom'] ?? $item['description'] ?? 'Element') ?></span>
                        <?php
                        $s = $item['statut'] ?? $item['etat'] ?? '';
                        $cls = 'badge-info';
                        if (in_array($s, ['ok', 'bon', 'conforme'])) $cls = 'badge-success';
                        elseif (in_array($s, ['probleme', 'mauvais', 'non_conforme'])) $cls = 'badge-danger';
                        elseif (in_array($s, ['absent', 'manquant'])) $cls = 'badge-warning';
                        ?>
                        <span class="badge <?= $cls ?>"><?= e($s) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Liste des checkups -->
            <?php if (empty($checkups)): ?>
                <div class="card"><p class="empty-state">Aucun checkup enregistre pour vos logements.</p></div>
            <?php else: ?>
                <div class="card">
                <?php foreach ($checkups as $c):
                    $total = (int)$c['nb_ok'] + (int)$c['nb_problemes'] + (int)$c['nb_absents'];
                    $pct = $total > 0 ? round(($c['nb_ok'] / $total) * 100) : 0;
                    $scoreClass = $pct >= 80 ? 'score-good' : ($pct >= 50 ? 'score-warn' : 'score-bad');
                ?>
                <a href="?id=<?= (int)$c['id'] ?>" class="list-item" style="text-decoration:none; display:flex;">
                    <div style="flex:1;">
                        <h4><?= e($c['nom_du_logement']) ?></h4>
                        <small><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></small>
                        <div class="score-bar" style="width:150px;">
                            <div class="score-fill <?= $scoreClass ?>" style="width:<?= $pct ?>%;"></div>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span class="badge badge-success"><?= (int)$c['nb_ok'] ?> OK</span>
                        <?php if ($c['nb_problemes'] > 0): ?>
                            <span class="badge badge-danger"><?= (int)$c['nb_problemes'] ?> pb</span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
