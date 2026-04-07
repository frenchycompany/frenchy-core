<?php
/**
 * Dashboard de suivi — Vue globale de tous les logements
 * Dernier checkup, score, taches en attente, inventaire
 */

// Debug : attraper les erreurs fatales silencieuses en production
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo '<pre style="background:#fdd;padding:20px;margin:20px;border:2px solid #c00;border-radius:8px;">';
        echo "<b>Erreur fatale :</b> {$error['message']}\n";
        echo "Fichier : {$error['file']}\n";
        echo "Ligne : {$error['line']}\n";
        echo '</pre>';
    }
});

include '../config.php';
include '../pages/menu.php';

// Tables requises : voir db/install_tables.php

// Donnees par logement
try {
    $logements = $conn->query("
        SELECT l.id, l.nom_du_logement,
            (SELECT COUNT(*) FROM todo_list t WHERE t.logement_id = l.id AND t.statut IN ('en attente','en cours')) AS nb_taches,
            (SELECT cs.id FROM checkup_sessions cs WHERE cs.logement_id = l.id AND cs.statut = 'termine' ORDER BY cs.created_at DESC LIMIT 1) AS last_checkup_id,
            (SELECT cs.created_at FROM checkup_sessions cs WHERE cs.logement_id = l.id AND cs.statut = 'termine' ORDER BY cs.created_at DESC LIMIT 1) AS last_checkup_date,
            (SELECT cs.nb_ok FROM checkup_sessions cs WHERE cs.logement_id = l.id AND cs.statut = 'termine' ORDER BY cs.created_at DESC LIMIT 1) AS last_ok,
            (SELECT cs.nb_problemes FROM checkup_sessions cs WHERE cs.logement_id = l.id AND cs.statut = 'termine' ORDER BY cs.created_at DESC LIMIT 1) AS last_pb,
            (SELECT cs.nb_absents FROM checkup_sessions cs WHERE cs.logement_id = l.id AND cs.statut = 'termine' ORDER BY cs.created_at DESC LIMIT 1) AS last_abs,
            (SELECT cs.id FROM checkup_sessions cs WHERE cs.logement_id = l.id AND cs.statut = 'en_cours' ORDER BY cs.created_at DESC LIMIT 1) AS checkup_en_cours,
            (SELECT s.date_creation FROM sessions_inventaire s WHERE s.logement_id = l.id AND s.statut = 'terminee' ORDER BY s.date_creation DESC LIMIT 1) AS last_inventaire_date,
            (SELECT COUNT(o.id) FROM inventaire_objets o JOIN sessions_inventaire s ON o.session_id = s.id WHERE s.logement_id = l.id AND s.statut = 'terminee') AS nb_objets
        FROM liste_logements l
        WHERE l.actif = 1
        ORDER BY l.nom_du_logement
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $logements = [];
}

// Stats globales
$totalLogements = count($logements);
$logementsAvecCheckup = count(array_filter($logements, fn($l) => $l['last_checkup_id']));
$totalTaches = array_sum(array_column($logements, 'nb_taches'));
$avgScore = 0;
$scored = array_filter($logements, fn($l) => $l['last_checkup_id']);
if (!empty($scored)) {
    $scores = array_map(function($l) {
        $total = ($l['last_ok'] + $l['last_pb'] + $l['last_abs']) ?: 1;
        return round(($l['last_ok'] / $total) * 100);
    }, $scored);
    $avgScore = round(array_sum($scores) / count($scores));
}
$alertes = count(array_filter($logements, fn($l) => $l['last_pb'] > 0));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Suivi</title>
    <style>
        .dash-container { max-width: 1000px; margin: 0 auto; padding: 0 12px 40px; }
        .dash-header {
            background: linear-gradient(135deg, #1976d2, #0d47a1);
            color: #fff; text-align: center; padding: 25px 15px;
            border-radius: 15px; margin: 15px 0 20px;
        }
        .dash-header h2 { margin: 0; font-size: 1.3em; }
        .dash-stats {
            display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;
        }
        .dash-stat {
            flex: 1; min-width: 120px; background: #fff; border-radius: 12px;
            padding: 18px 12px; text-align: center;
            box-shadow: 0 1px 5px rgba(0,0,0,0.07);
        }
        .dash-stat .number { font-size: 2em; font-weight: 800; line-height: 1; }
        .dash-stat .label { font-size: 0.82em; color: #666; margin-top: 4px; }
        .dash-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 12px;
        }
        .dash-card {
            background: #fff; border-radius: 12px; padding: 18px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.06);
            border-left: 4px solid #e0e0e0;
            transition: box-shadow 0.15s;
        }
        .dash-card:hover { box-shadow: 0 3px 15px rgba(0,0,0,0.1); }
        .dash-card.score-high { border-left-color: #43a047; }
        .dash-card.score-mid { border-left-color: #ff9800; }
        .dash-card.score-low { border-left-color: #e53935; }
        .dash-card.no-checkup { border-left-color: #bdbdbd; }
        .dash-card-title {
            font-weight: 700; font-size: 1.05em; color: #333;
            margin-bottom: 10px;
        }
        .dash-card-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 4px 0; font-size: 0.88em;
        }
        .dash-card-row .lbl { color: #888; }
        .dash-card-row .val { font-weight: 600; }
        .dash-score {
            display: inline-block; padding: 4px 12px; border-radius: 20px;
            font-weight: 700; font-size: 0.85em;
        }
        .score-green { background: #e8f5e9; color: #2e7d32; }
        .score-orange { background: #fff3e0; color: #e65100; }
        .score-red { background: #fbe9e7; color: #c62828; }
        .score-none { background: #f5f5f5; color: #999; }
        .dash-card-actions {
            display: flex; gap: 6px; margin-top: 10px;
        }
        .dash-card-actions a {
            flex: 1; padding: 8px; text-align: center; text-decoration: none;
            border-radius: 8px; font-size: 0.82em; font-weight: 600;
        }
        .btn-ck { background: #e3f2fd; color: #1565c0; }
        .btn-rp { background: #e8f5e9; color: #2e7d32; }
        .btn-tk { background: #f3e5f5; color: #7b1fa2; }
        .dash-badge {
            display: inline-block; padding: 2px 8px; border-radius: 12px;
            font-size: 0.75em; font-weight: 600;
        }
        .badge-alert { background: #fbe9e7; color: #c62828; }
        .badge-ok { background: #e8f5e9; color: #2e7d32; }
        .badge-warn { background: #fff3e0; color: #e65100; }
        .badge-info { background: #e3f2fd; color: #1565c0; }
        @media (max-width: 600px) {
            .dash-grid { grid-template-columns: 1fr; }
            .dash-stats { flex-wrap: wrap; }
            .dash-stat { min-width: calc(50% - 8px); }
        }
    </style>
</head>
<body>
<div class="dash-container">
    <div class="dash-header">
        <h2><i class="fas fa-tachometer-alt"></i> Dashboard de Suivi</h2>
    </div>

    <div class="dash-stats">
        <div class="dash-stat">
            <div class="number" style="color:#1976d2"><?= $totalLogements ?></div>
            <div class="label">Logements</div>
        </div>
        <div class="dash-stat">
            <div class="number" style="color:#43a047"><?= $logementsAvecCheckup ?></div>
            <div class="label">Avec checkup</div>
        </div>
        <div class="dash-stat">
            <div class="number" style="color:#1565c0"><?= $avgScore ?>%</div>
            <div class="label">Score moyen</div>
        </div>
        <div class="dash-stat">
            <div class="number" style="color:#7b1fa2"><?= $totalTaches ?></div>
            <div class="label">Taches en attente</div>
        </div>
        <div class="dash-stat">
            <div class="number" style="color:#e53935"><?= $alertes ?></div>
            <div class="label">Alertes</div>
        </div>
    </div>

    <div class="dash-grid">
        <?php foreach ($logements as $l):
            $hasCheckup = !empty($l['last_checkup_id']);
            $total = ($l['last_ok'] + $l['last_pb'] + $l['last_abs']) ?: 1;
            $score = $hasCheckup ? round(($l['last_ok'] / $total) * 100) : -1;
            $scoreClass = $score >= 80 ? 'score-high' : ($score >= 50 ? 'score-mid' : ($score >= 0 ? 'score-low' : 'no-checkup'));
            $scoreLabel = $score >= 80 ? 'score-green' : ($score >= 50 ? 'score-orange' : ($score >= 0 ? 'score-red' : 'score-none'));
        ?>
        <div class="dash-card <?= $scoreClass ?>">
            <div class="dash-card-title">
                <?= htmlspecialchars($l['nom_du_logement']) ?>
                <?php if ($l['checkup_en_cours']): ?>
                    <span class="dash-badge badge-info">En cours</span>
                <?php endif; ?>
            </div>

            <div class="dash-card-row">
                <span class="lbl">Dernier checkup</span>
                <span class="val">
                    <?php if ($hasCheckup): ?>
                        <?= date('d/m/Y', strtotime($l['last_checkup_date'])) ?>
                        <span class="dash-score <?= $scoreLabel ?>"><?= $score ?>%</span>
                    <?php else: ?>
                        <span class="dash-score score-none">Jamais</span>
                    <?php endif; ?>
                </span>
            </div>

            <?php if ($hasCheckup && ($l['last_pb'] > 0 || $l['last_abs'] > 0)): ?>
            <div class="dash-card-row">
                <span class="lbl">Problemes</span>
                <span class="val">
                    <?php if ($l['last_pb'] > 0): ?><span class="dash-badge badge-alert"><?= $l['last_pb'] ?> pb</span> <?php endif; ?>
                    <?php if ($l['last_abs'] > 0): ?><span class="dash-badge badge-warn"><?= $l['last_abs'] ?> abs</span><?php endif; ?>
                </span>
            </div>
            <?php endif; ?>

            <div class="dash-card-row">
                <span class="lbl">Taches</span>
                <span class="val">
                    <?php if ($l['nb_taches'] > 0): ?>
                        <span class="dash-badge badge-warn"><?= $l['nb_taches'] ?> en attente</span>
                    <?php else: ?>
                        <span class="dash-badge badge-ok">RAS</span>
                    <?php endif; ?>
                </span>
            </div>

            <div class="dash-card-row">
                <span class="lbl">Inventaire</span>
                <span class="val">
                    <?php if ($l['last_inventaire_date']): ?>
                        <?= date('d/m/Y', strtotime($l['last_inventaire_date'])) ?>
                        <small>(<?= $l['nb_objets'] ?> obj.)</small>
                    <?php else: ?>
                        <span style="color:#999">Aucun</span>
                    <?php endif; ?>
                </span>
            </div>

            <div class="dash-card-actions">
                <?php if ($l['checkup_en_cours']): ?>
                    <a href="checkup_faire.php?session_id=<?= $l['checkup_en_cours'] ?>" class="btn-ck">
                        <i class="fas fa-play"></i> Reprendre
                    </a>
                <?php else: ?>
                    <a href="checkup_logement.php" class="btn-ck">
                        <i class="fas fa-clipboard-check"></i> Checkup
                    </a>
                <?php endif; ?>
                <?php if ($hasCheckup): ?>
                    <a href="checkup_rapport.php?session_id=<?= $l['last_checkup_id'] ?>" class="btn-rp">
                        <i class="fas fa-file-alt"></i> Rapport
                    </a>
                <?php endif; ?>
                <a href="todo.php?logement_id=<?= $l['id'] ?>" class="btn-tk">
                    <i class="fas fa-tasks"></i> Taches
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
