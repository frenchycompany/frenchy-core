<?php
/**
 * Espace Propriétaire - Interventions (15 derniers jours)
 */
require_once __DIR__ . '/auth.php';

$interventions = [];
if (!empty($logement_ids)) {
    try {
        $stmt = $conn->prepare("SELECT p.id, p.date, p.statut, p.commentaire_menage, l.nom_du_logement
            FROM planning p
            JOIN liste_logements l ON p.logement_id = l.id
            WHERE p.logement_id IN ($placeholders) AND p.date >= DATE_SUB(CURDATE(), INTERVAL 15 DAY)
            ORDER BY p.date DESC");
        $stmt->execute($logement_ids);
        $interventions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    // Récupération des vidéos associées
    $uploadDir = __DIR__ . '/../../gestion/OK V2/uploads/';
    foreach ($interventions as &$int) {
        $pattern = $uploadDir . $int['id'] . '_*';
        $files = glob($pattern);
        if ($files) {
            usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
            $int['video'] = basename($files[0]);
        } else {
            $int['video'] = null;
        }
    }
    unset($int);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interventions - Espace Proprietaire</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="proprio.css">
    <style>
        .video-modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7);
            z-index: 10000; align-items: center; justify-content: center;
        }
        .video-modal-overlay.active { display: flex; }
        .video-modal {
            background: white; border-radius: 12px; padding: 1.5rem; max-width: 720px;
            width: 90%; position: relative;
        }
        .video-modal video { width: 100%; border-radius: 8px; }
        .video-modal-close {
            position: absolute; top: 0.5rem; right: 0.8rem; background: none; border: none;
            font-size: 1.5rem; cursor: pointer; color: #6B7280;
        }
        .video-modal-close:hover { color: #1F2937; }
        .btn-video {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 4px 12px; border-radius: 6px; border: 1px solid #3B82F6;
            background: rgba(59,130,246,0.08); color: #3B82F6; font-size: 0.82rem;
            cursor: pointer; text-decoration: none; transition: background 0.2s;
        }
        .btn-video:hover { background: rgba(59,130,246,0.18); }
        .intervention-comment { color: #6B7280; font-size: 0.83rem; margin-top: 2px; font-style: italic; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php proprioSidebar($proprietaire, $currentPage, $has_sites); ?>

    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-broom"></i> Interventions</h1>
            <span style="color:#6B7280;">15 derniers jours</span>
        </div>

        <?php if (empty($interventions)): ?>
            <div class="card"><p class="empty-state">Aucune intervention sur les 15 derniers jours.</p></div>
        <?php else: ?>
            <div class="card">
            <?php foreach ($interventions as $int):
                $statutClass = 'badge-info';
                $s = $int['statut'] ?? '';
                if (in_array($s, ['termine', 'terminé', 'validé', 'valide'])) $statutClass = 'badge-success';
                elseif (in_array($s, ['en_cours', 'en cours', 'planifié', 'planifie'])) $statutClass = 'badge-warning';
                elseif (in_array($s, ['annulé', 'annule', 'probleme'])) $statutClass = 'badge-danger';
            ?>
            <div class="list-item">
                <div style="flex:1;">
                    <h4><?= e($int['nom_du_logement']) ?></h4>
                    <small><?= date('d/m/Y', strtotime($int['date'])) ?></small>
                    <?php if (!empty($int['commentaire_menage'])): ?>
                        <div class="intervention-comment"><?= e($int['commentaire_menage']) ?></div>
                    <?php endif; ?>
                </div>
                <div style="display:flex; align-items:center; gap:0.8rem;">
                    <?php if ($int['video']): ?>
                        <button class="btn-video" onclick="openVideo('<?= e($int['video']) ?>')">
                            <i class="fas fa-play-circle"></i> Video
                        </button>
                    <?php endif; ?>
                    <span class="badge <?= $statutClass ?>"><?= e($s ?: 'inconnu') ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<div class="video-modal-overlay" id="videoOverlay" onclick="closeVideo(event)">
    <div class="video-modal">
        <button class="video-modal-close" onclick="closeVideo(event)">&times;</button>
        <video id="videoPlayer" controls></video>
    </div>
</div>

<script>
function openVideo(filename) {
    var player = document.getElementById('videoPlayer');
    player.src = '../../gestion/OK V2/uploads/' + encodeURIComponent(filename);
    document.getElementById('videoOverlay').classList.add('active');
    player.play();
}
function closeVideo(e) {
    if (e.target === document.getElementById('videoOverlay') || e.currentTarget.classList.contains('video-modal-close')) {
        var player = document.getElementById('videoPlayer');
        player.pause();
        player.src = '';
        document.getElementById('videoOverlay').classList.remove('active');
    }
}
</script>
</body>
</html>
