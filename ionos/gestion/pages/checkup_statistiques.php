<?php
/**
 * Statistiques Checkup — Graphiques d'evolution des scores par logement
 * Utilise Chart.js pour les visualisations
 */
include '../config.php';
include '../pages/menu.php';

$logement_filter = isset($_GET['logement_id']) ? (int)$_GET['logement_id'] : null;

// Logements
$logements = $conn->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);

// Donnees pour les graphiques
// 1. Evolution des scores dans le temps
$scoreQuery = "
    SELECT cs.id, cs.logement_id, l.nom_du_logement,
           cs.nb_ok, cs.nb_problemes, cs.nb_absents, cs.nb_taches_faites,
           cs.created_at
    FROM checkup_sessions cs
    JOIN liste_logements l ON cs.logement_id = l.id
    WHERE cs.statut = 'termine'
";
$params = [];
if ($logement_filter) {
    $scoreQuery .= " AND cs.logement_id = :lid";
    $params[':lid'] = $logement_filter;
}
$scoreQuery .= " ORDER BY cs.created_at ASC";
$stmt = $conn->prepare($scoreQuery);
$stmt->execute($params);
$checkups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preparer les donnees pour Chart.js
$chartLabels = [];
$chartScores = [];
$chartProblemes = [];
$chartAbsents = [];
$chartTaches = [];

// Par logement pour multi-ligne
$parLogement = [];
foreach ($checkups as $c) {
    $total = $c['nb_ok'] + $c['nb_problemes'] + $c['nb_absents'];
    $score = $total > 0 ? round(($c['nb_ok'] / $total) * 100) : 0;
    $nom = $c['nom_du_logement'];

    if (!isset($parLogement[$nom])) {
        $parLogement[$nom] = ['labels' => [], 'scores' => [], 'problemes' => [], 'absents' => []];
    }
    $parLogement[$nom]['labels'][] = date('d/m/Y', strtotime($c['created_at']));
    $parLogement[$nom]['scores'][] = $score;
    $parLogement[$nom]['problemes'][] = (int)$c['nb_problemes'];
    $parLogement[$nom]['absents'][] = (int)$c['nb_absents'];

    $chartLabels[] = date('d/m', strtotime($c['created_at']));
    $chartScores[] = $score;
    $chartProblemes[] = (int)$c['nb_problemes'];
    $chartAbsents[] = (int)$c['nb_absents'];
    $chartTaches[] = (int)$c['nb_taches_faites'];
}

// 2. Stats globales par logement
$globalStats = $conn->query("
    SELECT l.nom_du_logement,
           COUNT(cs.id) AS nb_checkups,
           ROUND(AVG(cs.nb_ok / GREATEST(cs.nb_ok + cs.nb_problemes + cs.nb_absents, 1) * 100)) AS avg_score,
           SUM(cs.nb_problemes) AS total_pb,
           SUM(cs.nb_absents) AS total_abs,
           MAX(cs.created_at) AS dernier_checkup
    FROM checkup_sessions cs
    JOIN liste_logements l ON cs.logement_id = l.id
    WHERE cs.statut = 'termine'
    GROUP BY l.id, l.nom_du_logement
    ORDER BY avg_score ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 3. Problemes les plus frequents
$topProblemes = $conn->query("
    SELECT ci.nom_item, ci.categorie, COUNT(*) AS nb
    FROM checkup_items ci
    JOIN checkup_sessions cs ON ci.session_id = cs.id
    WHERE ci.statut = 'probleme' AND cs.statut = 'termine'
    GROUP BY ci.nom_item, ci.categorie
    ORDER BY nb DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// 4. Absents les plus frequents
$topAbsents = $conn->query("
    SELECT ci.nom_item, ci.categorie, COUNT(*) AS nb
    FROM checkup_items ci
    JOIN checkup_sessions cs ON ci.session_id = cs.id
    WHERE ci.statut = 'absent' AND cs.statut = 'termine'
    GROUP BY ci.nom_item, ci.categorie
    ORDER BY nb DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Couleurs pour les logements
$colors = ['#1976d2','#e53935','#43a047','#ff9800','#7b1fa2','#00897b','#f44336','#2196f3','#4caf50','#ff5722'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques Checkup</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .stat-container { max-width: 900px; margin: 0 auto; padding: 0 12px 40px; }
        .stat-header {
            background: linear-gradient(135deg, #e65100, #bf360c);
            color: #fff; text-align: center; padding: 25px 15px;
            border-radius: 15px; margin: 15px 0 20px;
        }
        .stat-header h2 { margin: 0; font-size: 1.3em; }
        .stat-filter {
            background: #fff; border-radius: 12px; padding: 15px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.07); margin-bottom: 20px;
            display: flex; gap: 10px; align-items: center;
        }
        .stat-filter select {
            flex: 1; padding: 10px; font-size: 1em;
            border: 1px solid #ddd; border-radius: 8px;
        }
        .stat-filter .btn-filter {
            padding: 10px 20px; background: #e65100; color: #fff;
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer;
        }
        .chart-card {
            background: #fff; border-radius: 12px; padding: 20px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.07); margin-bottom: 20px;
        }
        .chart-card h3 { margin: 0 0 15px; font-size: 1.05em; color: #333; }
        .chart-card canvas { max-height: 300px; }
        .ranking-table {
            width: 100%; border-collapse: collapse;
        }
        .ranking-table th {
            text-align: left; padding: 10px; background: #f5f5f5;
            font-size: 0.85em; color: #666;
        }
        .ranking-table td {
            padding: 10px; border-bottom: 1px solid #f0f0f0;
            font-size: 0.9em;
        }
        .score-bar {
            display: inline-block; height: 8px; border-radius: 4px;
            margin-right: 8px; vertical-align: middle;
        }
        .top-list { list-style: none; padding: 0; }
        .top-list li {
            display: flex; justify-content: space-between; align-items: center;
            padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 0.9em;
        }
        .top-list li:last-child { border-bottom: none; }
        .top-badge {
            display: inline-block; padding: 2px 10px; border-radius: 12px;
            font-size: 0.82em; font-weight: 600;
        }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        @media (max-width: 600px) { .two-col { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="stat-container">
    <div class="stat-header">
        <h2><i class="fas fa-chart-line"></i> Statistiques Checkup</h2>
    </div>

    <div class="stat-filter">
        <form method="GET" style="display:flex;gap:10px;width:100%;align-items:center;">
            <select name="logement_id">
                <option value="">Tous les logements</option>
                <?php foreach ($logements as $l): ?>
                <option value="<?= $l['id'] ?>" <?= $logement_filter == $l['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($l['nom_du_logement']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrer</button>
        </form>
    </div>

    <?php if (empty($checkups)): ?>
    <div class="chart-card" style="text-align:center;color:#999;padding:40px;">
        <i class="fas fa-chart-bar" style="font-size:2em;color:#ddd;display:block;margin-bottom:10px;"></i>
        Aucun checkup termine pour generer des statistiques.
    </div>
    <?php else: ?>

    <!-- Graphique : evolution du score -->
    <div class="chart-card">
        <h3><i class="fas fa-chart-line"></i> Evolution du score</h3>
        <canvas id="scoreChart"></canvas>
    </div>

    <!-- Graphique : problemes et absents -->
    <div class="chart-card">
        <h3><i class="fas fa-chart-bar"></i> Problemes et absents par checkup</h3>
        <canvas id="issuesChart"></canvas>
    </div>

    <!-- Classement par logement -->
    <?php if (!$logement_filter && !empty($globalStats)): ?>
    <div class="chart-card">
        <h3><i class="fas fa-ranking-star"></i> Classement par logement</h3>
        <table class="ranking-table">
            <tr>
                <th>Logement</th>
                <th>Checkups</th>
                <th>Score moyen</th>
                <th>Problemes</th>
                <th>Dernier</th>
            </tr>
            <?php foreach ($globalStats as $gs):
                $barColor = $gs['avg_score'] >= 80 ? '#43a047' : ($gs['avg_score'] >= 50 ? '#ff9800' : '#e53935');
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($gs['nom_du_logement']) ?></strong></td>
                <td><?= $gs['nb_checkups'] ?></td>
                <td>
                    <span class="score-bar" style="width:<?= $gs['avg_score'] ?>px;background:<?= $barColor ?>"></span>
                    <?= $gs['avg_score'] ?>%
                </td>
                <td><?= $gs['total_pb'] ?> pb / <?= $gs['total_abs'] ?> abs</td>
                <td><?= date('d/m/Y', strtotime($gs['dernier_checkup'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- Top problemes et absents -->
    <div class="two-col">
        <?php if (!empty($topProblemes)): ?>
        <div class="chart-card">
            <h3><i class="fas fa-exclamation-triangle" style="color:#e53935"></i> Top problemes</h3>
            <ul class="top-list">
                <?php foreach ($topProblemes as $tp): ?>
                <li>
                    <span><?= htmlspecialchars($tp['nom_item']) ?> <small style="color:#888">[<?= htmlspecialchars($tp['categorie']) ?>]</small></span>
                    <span class="top-badge" style="background:#fbe9e7;color:#c62828"><?= $tp['nb'] ?>x</span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($topAbsents)): ?>
        <div class="chart-card">
            <h3><i class="fas fa-times-circle" style="color:#ff9800"></i> Top absents</h3>
            <ul class="top-list">
                <?php foreach ($topAbsents as $ta): ?>
                <li>
                    <span><?= htmlspecialchars($ta['nom_item']) ?> <small style="color:#888">[<?= htmlspecialchars($ta['categorie']) ?>]</small></span>
                    <span class="top-badge" style="background:#fff3e0;color:#e65100"><?= $ta['nb'] ?>x</span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>

<?php if (!empty($checkups)): ?>
<script>
// Donnees
const labels = <?= json_encode($chartLabels) ?>;
const scores = <?= json_encode($chartScores) ?>;
const problemes = <?= json_encode($chartProblemes) ?>;
const absents = <?= json_encode($chartAbsents) ?>;

// Multi-logements
const parLogement = <?= json_encode($parLogement) ?>;
const colors = <?= json_encode($colors) ?>;

// Chart 1 : Score
const scoreCtx = document.getElementById('scoreChart').getContext('2d');
<?php if (!$logement_filter && count($parLogement) > 1): ?>
// Multi-lignes par logement
const scoreDatasets = [];
let ci = 0;
for (const [nom, data] of Object.entries(parLogement)) {
    scoreDatasets.push({
        label: nom,
        data: data.scores,
        borderColor: colors[ci % colors.length],
        backgroundColor: colors[ci % colors.length] + '20',
        tension: 0.3,
        fill: false,
        pointRadius: 4
    });
    ci++;
}
new Chart(scoreCtx, {
    type: 'line',
    data: { labels: Object.values(parLogement)[0].labels, datasets: scoreDatasets },
    options: {
        responsive: true,
        scales: { y: { min: 0, max: 100, ticks: { callback: v => v + '%' } } },
        plugins: { legend: { position: 'bottom' } }
    }
});
<?php else: ?>
new Chart(scoreCtx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Score (%)',
            data: scores,
            borderColor: '#1976d2',
            backgroundColor: 'rgba(25,118,210,0.1)',
            tension: 0.3,
            fill: true,
            pointRadius: 5,
            pointBackgroundColor: scores.map(s => s >= 80 ? '#43a047' : s >= 50 ? '#ff9800' : '#e53935')
        }]
    },
    options: {
        responsive: true,
        scales: { y: { min: 0, max: 100, ticks: { callback: v => v + '%' } } }
    }
});
<?php endif; ?>

// Chart 2 : Problemes et absents
const issuesCtx = document.getElementById('issuesChart').getContext('2d');
new Chart(issuesCtx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            { label: 'Problemes', data: problemes, backgroundColor: '#ef5350' },
            { label: 'Absents', data: absents, backgroundColor: '#ffa726' }
        ]
    },
    options: {
        responsive: true,
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>
<?php endif; ?>
</body>
</html>
