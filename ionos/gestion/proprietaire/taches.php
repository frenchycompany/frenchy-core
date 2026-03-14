<?php
/**
 * Espace Propriétaire - Tâches
 */
require_once __DIR__ . '/auth.php';

$msg_success = '';
$msg_error = '';

// Traitement de la création d'une tâche
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    if (!proprio_validate_csrf($_POST['csrf_token'] ?? '')) {
        $msg_error = 'Token de sécurité invalide.';
    } else {
        $task_logement = (int)($_POST['logement_id'] ?? 0);
        $task_desc = trim($_POST['description'] ?? '');
        $task_date = $_POST['date_limite'] ?? null;
        $task_date = !empty($task_date) ? $task_date : null;

        if (empty($task_desc)) {
            $msg_error = 'La description est obligatoire.';
        } elseif (!in_array($task_logement, $logement_ids)) {
            $msg_error = 'Logement invalide.';
        } else {
            $stmt = $conn->prepare("INSERT INTO todo_list (logement_id, description, statut, date_limite, responsable) VALUES (?, ?, 'en attente', ?, ?)");
            $stmt->execute([$task_logement, $task_desc, $task_date, $proprietaire['nom']]);
            $msg_success = 'Tache ajoutee avec succes.';
        }
    }
}

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
    <style>
        .task-form { display: grid; grid-template-columns: 1fr 2fr 1fr auto; gap: 0.8rem; align-items: end; }
        .task-form .form-group { display: flex; flex-direction: column; gap: 0.3rem; }
        .task-form label { font-size: 0.85rem; font-weight: 600; color: #374151; }
        .task-form select, .task-form input, .task-form textarea { padding: 0.6rem 0.8rem; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 0.9rem; }
        .task-form select:focus, .task-form input:focus, .task-form textarea:focus { outline: none; border-color: #3B82F6; }
        .btn-add { padding: 0.6rem 1.2rem; background: linear-gradient(135deg, #1E3A8A, #3B82F6); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .btn-add:hover { transform: translateY(-1px); box-shadow: 0 3px 10px rgba(30,58,138,0.3); }
        .alert { padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .alert-success { background: #D1FAE5; color: #065F46; }
        .alert-error { background: #FEE2E2; color: #991B1B; }
        @media (max-width: 900px) { .task-form { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php proprioSidebar($proprietaire, $currentPage, $has_sites); ?>

    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-tasks"></i> Taches</h1>
        </div>

        <?php if ($msg_success): ?>
            <div class="alert alert-success"><?= e($msg_success) ?></div>
        <?php endif; ?>
        <?php if ($msg_error): ?>
            <div class="alert alert-error"><?= e($msg_error) ?></div>
        <?php endif; ?>

        <?php if (!empty($logements)): ?>
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="card-header"><h2><i class="fas fa-plus-circle"></i> Nouvelle tache</h2></div>
            <form method="POST" class="task-form">
                <?= proprio_csrf_field() ?>
                <div class="form-group">
                    <label for="logement_id">Logement</label>
                    <select name="logement_id" id="logement_id" required>
                        <?php foreach ($logements as $l): ?>
                            <option value="<?= (int)$l['id'] ?>"><?= e($l['nom_du_logement']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <input type="text" name="description" id="description" required placeholder="Ex: Remplacer l'ampoule du salon">
                </div>
                <div class="form-group">
                    <label for="date_limite">Echeance</label>
                    <input type="date" name="date_limite" id="date_limite">
                </div>
                <button type="submit" name="add_task" class="btn-add"><i class="fas fa-plus"></i> Ajouter</button>
            </form>
        </div>
        <?php endif; ?>

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
