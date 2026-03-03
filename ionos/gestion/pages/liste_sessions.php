<?php
/**
 * Liste des sessions d'inventaire — en cours + terminees
 */
include '../config.php';
include '../pages/menu.php';

// Sessions en cours
$enCours = $conn->query("
    SELECT s.id, s.date_creation, s.statut, l.nom_du_logement,
           (SELECT COUNT(*) FROM inventaire_objets WHERE session_id = s.id) AS nb_objets
    FROM sessions_inventaire s
    JOIN liste_logements l ON s.logement_id = l.id
    WHERE s.statut = 'en_cours'
    ORDER BY s.date_creation DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Sessions terminees (les 30 dernieres)
$terminees = $conn->query("
    SELECT s.id, s.date_creation, s.statut, l.nom_du_logement,
           (SELECT COUNT(*) FROM inventaire_objets WHERE session_id = s.id) AS nb_objets
    FROM sessions_inventaire s
    JOIN liste_logements l ON s.logement_id = l.id
    WHERE s.statut = 'terminee'
    ORDER BY s.date_creation DESC
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sessions d'inventaire</title>
    <style>
        .sessions-container {
            max-width: 650px;
            margin: 0 auto;
            padding: 0 12px 30px;
        }
        .sessions-header {
            background: linear-gradient(135deg, #1976d2, #1565c0);
            color: #fff;
            text-align: center;
            padding: 22px 15px;
            border-radius: 15px;
            margin: 15px 0 20px;
        }
        .sessions-header h2 { margin: 0; font-size: 1.3em; }
        .section-title {
            font-size: 1.05em;
            font-weight: 700;
            color: #555;
            margin: 20px 0 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title .badge {
            background: #e3f2fd;
            color: #1565c0;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.8em;
        }
        .session-card {
            background: #fff;
            border-radius: 12px;
            padding: 15px 18px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.06);
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-decoration: none;
            color: inherit;
            transition: box-shadow 0.15s;
        }
        .session-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.12); }
        .session-info h4 { margin: 0 0 4px; font-size: 1em; color: #333; }
        .session-info small { color: #888; }
        .session-stats {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .stat-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.82em;
            font-weight: 600;
        }
        .stat-encours { background: #fff3e0; color: #e65100; }
        .stat-terminee { background: #e8f5e9; color: #2e7d32; }
        .stat-objets { background: #e3f2fd; color: #1565c0; }
        .empty-msg {
            text-align: center;
            color: #999;
            padding: 20px 0;
            font-size: 0.95em;
        }
        .btn-new {
            display: inline-block;
            background: linear-gradient(135deg, #43a047, #388e3c);
            color: #fff;
            padding: 14px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1em;
            margin-top: 15px;
        }
        @media (max-width: 600px) {
            .sessions-container { padding: 0 6px 30px; }
            .session-card { flex-direction: column; align-items: flex-start; gap: 8px; }
        }
    </style>
</head>
<body>
<div class="sessions-container">
    <div class="sessions-header">
        <h2><i class="fas fa-clipboard-list"></i> Sessions d'inventaire</h2>
    </div>

    <!-- En cours -->
    <div class="section-title">
        <i class="fas fa-spinner" style="color:#e65100"></i>
        En cours
        <span class="badge"><?= count($enCours) ?></span>
    </div>
    <?php if (empty($enCours)): ?>
        <p class="empty-msg">Aucune session en cours.</p>
    <?php else: ?>
        <?php foreach ($enCours as $s): ?>
        <a class="session-card" href="inventaire_saisie.php?session_id=<?= urlencode($s['id']) ?>">
            <div class="session-info">
                <h4><?= htmlspecialchars($s['nom_du_logement']) ?></h4>
                <small><?= date('d/m/Y H:i', strtotime($s['date_creation'])) ?></small>
            </div>
            <div class="session-stats">
                <span class="stat-badge stat-objets"><?= (int)$s['nb_objets'] ?> obj.</span>
                <span class="stat-badge stat-encours">En cours</span>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Terminees -->
    <div class="section-title">
        <i class="fas fa-check-circle" style="color:#2e7d32"></i>
        Terminees
        <span class="badge"><?= count($terminees) ?></span>
    </div>
    <?php if (empty($terminees)): ?>
        <p class="empty-msg">Aucune session terminee.</p>
    <?php else: ?>
        <?php foreach ($terminees as $s): ?>
        <a class="session-card" href="inventaire_saisie.php?session_id=<?= urlencode($s['id']) ?>">
            <div class="session-info">
                <h4><?= htmlspecialchars($s['nom_du_logement']) ?></h4>
                <small><?= date('d/m/Y H:i', strtotime($s['date_creation'])) ?></small>
            </div>
            <div class="session-stats">
                <span class="stat-badge stat-objets"><?= (int)$s['nb_objets'] ?> obj.</span>
                <span class="stat-badge stat-terminee">Terminee</span>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <div style="text-align:center">
        <a href="inventaire_lancer.php" class="btn-new"><i class="fas fa-plus"></i> Nouveau inventaire</a>
    </div>
</div>
</body>
</html>
