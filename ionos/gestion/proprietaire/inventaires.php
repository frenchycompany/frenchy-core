<?php
/**
 * Espace Propriétaire - Inventaires
 */
require_once __DIR__ . '/auth.php';

$sessions = [];
if (!empty($logement_ids)) {
    try {
        $stmt = $conn->prepare("SELECT s.*, l.nom_du_logement,
                (SELECT COUNT(*) FROM inventaire_objets WHERE session_id = s.id) AS nb_objets
            FROM sessions_inventaire s
            JOIN liste_logements l ON s.logement_id = l.id
            WHERE s.logement_id IN ($placeholders)
            ORDER BY s.date_creation DESC
            LIMIT 50");
        $stmt->execute($logement_ids);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

// Détail d'une session
$detail = null;
$objets = [];
if (!empty($_GET['session_id'])) {
    $sid = $_GET['session_id'];
    try {
        $stmt = $conn->prepare("SELECT s.*, l.nom_du_logement
            FROM sessions_inventaire s
            JOIN liste_logements l ON s.logement_id = l.id
            WHERE s.id = ? AND s.logement_id IN ($placeholders)");
        $stmt->execute(array_merge([$sid], $logement_ids));
        $detail = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($detail) {
            $stmt = $conn->prepare("SELECT * FROM inventaire_objets WHERE session_id = ? ORDER BY id");
            $stmt->execute([$sid]);
            $objets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventaires - Espace Proprietaire</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="proprio.css">
    <style>
        .obj-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; }
        .obj-card { background: #F9FAFB; border-radius: 10px; padding: 1rem; text-align: center; }
        .obj-card img { width: 100%; height: 120px; object-fit: cover; border-radius: 8px; margin-bottom: 0.5rem; background: #E5E7EB; }
        .obj-card h4 { font-size: 0.9rem; color: #1F2937; margin-bottom: 0.3rem; }
        .obj-card small { color: #6B7280; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php proprioSidebar($proprietaire, $currentPage, $has_sites); ?>

    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-boxes-stacked"></i> Inventaires</h1>
            <?php if ($detail): ?>
                <a href="inventaires.php" style="color:#3B82F6; text-decoration:none;">&larr; Retour a la liste</a>
            <?php endif; ?>
        </div>

        <?php if ($detail): ?>
            <div class="card" style="margin-bottom:1.5rem;">
                <div class="card-header">
                    <h2><?= e($detail['nom_du_logement']) ?></h2>
                    <div>
                        <span class="badge <?= $detail['statut'] === 'terminee' ? 'badge-success' : 'badge-warning' ?>">
                            <?= e($detail['statut']) ?>
                        </span>
                        <small style="color:#6B7280; margin-left:0.5rem;"><?= date('d/m/Y H:i', strtotime($detail['date_creation'])) ?></small>
                    </div>
                </div>

                <p style="color:#6B7280; margin-bottom:1rem;"><?= count($objets) ?> objet(s) inventorie(s)</p>

                <?php if (empty($objets)): ?>
                    <p class="empty-state">Aucun objet dans cet inventaire.</p>
                <?php else: ?>
                    <div class="obj-grid">
                    <?php foreach ($objets as $obj): ?>
                        <div class="obj-card">
                            <?php if (!empty($obj['photo_path'])): ?>
                                <img src="../<?= e($obj['photo_path']) ?>" alt="<?= e($obj['nom'] ?? 'Objet') ?>" onerror="this.style.display='none'">
                            <?php endif; ?>
                            <h4><?= e($obj['nom'] ?? 'Objet sans nom') ?></h4>
                            <small>
                                <?= !empty($obj['piece']) ? e($obj['piece']) : '' ?>
                                <?= !empty($obj['quantite']) ? ' &middot; Qte: ' . (int)$obj['quantite'] : '' ?>
                                <?= !empty($obj['etat']) ? ' &middot; ' . e($obj['etat']) : '' ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <?php if (empty($sessions)): ?>
                <div class="card"><p class="empty-state">Aucun inventaire pour vos logements.</p></div>
            <?php else: ?>
                <div class="card">
                <?php foreach ($sessions as $s): ?>
                <a href="?session_id=<?= urlencode($s['id']) ?>" class="list-item" style="text-decoration:none; display:flex;">
                    <div style="flex:1;">
                        <h4><?= e($s['nom_du_logement']) ?></h4>
                        <small><?= date('d/m/Y H:i', strtotime($s['date_creation'])) ?></small>
                    </div>
                    <div>
                        <span class="badge badge-info"><?= (int)$s['nb_objets'] ?> obj.</span>
                        <span class="badge <?= $s['statut'] === 'terminee' ? 'badge-success' : 'badge-warning' ?>">
                            <?= e($s['statut']) ?>
                        </span>
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
