<?php
/**
 * Espace Propriétaire - Tâches
 */
require_once __DIR__ . '/auth.php';

// Récupérer les tâches des logements du propriétaire
$taches = [];
if (!empty($logement_ids)) {
    $stmt = $conn->prepare("SELECT t.*, l.nom_du_logement
        FROM todo_list t
        JOIN liste_logements l ON t.logement_id = l.id
        WHERE t.logement_id IN ($placeholders)
        ORDER BY FIELD(t.statut, 'en attente', 'en cours', 'terminee'), t.date_limite ASC");
    $stmt->execute($logement_ids);
    $taches = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taches - Espace Proprietaire</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="proprio.css">
</head>
<body>
<div class="dashboard-container">
    <?php proprioSidebar($proprietaire, $currentPage, $has_sites); ?>

    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-tasks"></i> Taches</h1>
        </div>

        <?php if (empty($taches)): ?>
            <div class="card"><p class="empty-state">Aucune tache pour vos logements.</p></div>
        <?php else: ?>
            <?php
            $groupes = ['en attente' => [], 'en cours' => [], 'terminee' => []];
            foreach ($taches as $t) {
                $groupes[$t['statut']][] = $t;
            }
            ?>

            <?php if (!empty($groupes['en attente']) || !empty($groupes['en cours'])): ?>
            <div class="card" style="margin-bottom:1.5rem;">
                <div class="card-header"><h2>En attente / En cours</h2></div>
                <?php foreach (array_merge($groupes['en attente'], $groupes['en cours']) as $t): ?>
                <div class="list-item">
                    <div>
                        <h4><?= e($t['description']) ?></h4>
                        <small>
                            <?= e($t['nom_du_logement']) ?>
                            <?= $t['date_limite'] ? ' &middot; Echeance : ' . date('d/m/Y', strtotime($t['date_limite'])) : '' ?>
                            <?= $t['responsable'] ? ' &middot; ' . e($t['responsable']) : '' ?>
                        </small>
                    </div>
                    <span class="badge <?= $t['statut'] === 'en cours' ? 'badge-info' : 'badge-warning' ?>">
                        <?= e($t['statut']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($groupes['terminee'])): ?>
            <div class="card">
                <div class="card-header"><h2>Terminees</h2></div>
                <?php foreach ($groupes['terminee'] as $t): ?>
                <div class="list-item" style="opacity:0.6;">
                    <div>
                        <h4 style="text-decoration:line-through;"><?= e($t['description']) ?></h4>
                        <small><?= e($t['nom_du_logement']) ?></small>
                    </div>
                    <span class="badge badge-success">Terminee</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
