<?php
/**
 * Gestion des Tâches (Todo) — FrenchyConciergerie
 * Vue unifiée : filtres + CRUD + changement de statut
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';

$feedback = '';

// ============================================================
// ACTIONS POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    // --- Changement de statut (AJAX ou classique) ---
    if (isset($_POST['update_status'])) {
        $task_id = (int) $_POST['task_id'];
        $statut  = trim($_POST['statut'] ?? '');
        $allowed = ['en attente', 'en cours', 'terminée'];
        if (in_array($statut, $allowed)) {
            try {
                $conn->prepare("UPDATE todo_list SET statut = ? WHERE id = ?")->execute([$statut, $task_id]);
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'success']);
                    exit;
                }
                $feedback = '<div class="alert alert-success alert-dismissible fade show">Statut mis à jour.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            } catch (PDOException $e) {
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    http_response_code(500);
                    echo json_encode(['error' => $e->getMessage()]);
                    exit;
                }
                $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    // --- Ajouter une tâche ---
    if (isset($_POST['add_task'])) {
        $logement_id = (int) ($_POST['logement_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $date_limite = trim($_POST['date_limite'] ?? '') ?: null;
        $responsable = ((int) ($_POST['responsable'] ?? 0)) ?: null;
        $prix_vente  = (float) ($_POST['prix_vente'] ?? 0);

        if (empty($description)) {
            $feedback = '<div class="alert alert-danger">La description est obligatoire.</div>';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO todo_list (logement_id, description, date_limite, responsable, prix_vente, statut) VALUES (?, ?, ?, ?, ?, 'en attente')");
                $stmt->execute([$logement_id ?: null, $description, $date_limite, $responsable, $prix_vente]);
                $feedback = '<div class="alert alert-success alert-dismissible fade show">Tâche ajoutée.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            } catch (PDOException $e) {
                $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    // --- Modifier une tâche ---
    if (isset($_POST['save_task'])) {
        $task_id     = (int) $_POST['task_id'];
        $logement_id = (int) ($_POST['logement_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $date_limite = trim($_POST['date_limite'] ?? '') ?: null;
        $responsable = ((int) ($_POST['responsable'] ?? 0)) ?: null;
        $prix_vente  = (float) ($_POST['prix_vente'] ?? 0);

        if (empty($description)) {
            $feedback = '<div class="alert alert-danger">La description est obligatoire.</div>';
        } else {
            try {
                $stmt = $conn->prepare("UPDATE todo_list SET logement_id = ?, description = ?, date_limite = ?, responsable = ?, prix_vente = ? WHERE id = ?");
                $stmt->execute([$logement_id ?: null, $description, $date_limite, $responsable, $prix_vente, $task_id]);
                $feedback = '<div class="alert alert-success alert-dismissible fade show">Tâche mise à jour.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            } catch (PDOException $e) {
                $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    // --- Supprimer une tâche ---
    if (isset($_POST['delete_task'])) {
        $task_id = (int) $_POST['task_id'];
        try {
            $conn->prepare("DELETE FROM todo_list WHERE id = ?")->execute([$task_id]);
            $feedback = '<div class="alert alert-success alert-dismissible fade show">Tâche supprimée.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (PDOException $e) {
            $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// ============================================================
// FILTRES
// ============================================================
$logement_filter = isset($_GET['logement_id']) ? (int) $_GET['logement_id'] : null;
$status_filter   = isset($_GET['statut']) && $_GET['statut'] !== '' ? $_GET['statut'] : null;

// ============================================================
// DONNÉES
// ============================================================
$logements = $conn->query("SELECT id, nom_du_logement FROM liste_logements ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);
$intervenants = $conn->query("SELECT id, nom FROM intervenant WHERE actif = 1 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// Tâches avec filtres
$sql = "
    SELECT t.*, l.nom_du_logement, i.nom AS responsable_nom
    FROM todo_list t
    LEFT JOIN liste_logements l ON t.logement_id = l.id
    LEFT JOIN intervenant i ON t.responsable = i.id
    WHERE 1 = 1
";
$params = [];
if ($logement_filter) {
    $sql .= " AND t.logement_id = ?";
    $params[] = $logement_filter;
}
if ($status_filter !== null) {
    $sql .= " AND t.statut = ?";
    $params[] = $status_filter;
}
$sql .= " ORDER BY FIELD(t.statut, 'en attente', 'en cours', 'terminée'), t.date_limite ASC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compteurs
$counts = ['en attente' => 0, 'en cours' => 0, 'terminée' => 0];
foreach ($tasks as $t) {
    if (isset($counts[$t['statut']])) $counts[$t['statut']]++;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tâches — FrenchyConciergerie</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .status-badge { font-size: 0.85rem; cursor: pointer; }
        .task-done { opacity: 0.5; text-decoration: line-through; }
        .counter-card { border-radius: 10px; text-align: center; padding: 0.75rem; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">

    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-tasks text-warning"></i> Gestion des Tâches</h2>
            <p class="text-muted"><?= count($tasks) ?> tâche(s) affichée(s)</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#taskModal" onclick="resetTaskModal()">
                <i class="fas fa-plus"></i> Nouvelle tâche
            </button>
        </div>
    </div>

    <?= $feedback ?>

    <!-- Compteurs -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="counter-card bg-warning bg-opacity-25">
                <strong class="fs-4"><?= $counts['en attente'] ?></strong><br>
                <small>En attente</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="counter-card bg-primary bg-opacity-25">
                <strong class="fs-4"><?= $counts['en cours'] ?></strong><br>
                <small>En cours</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="counter-card bg-success bg-opacity-25">
                <strong class="fs-4"><?= $counts['terminée'] ?></strong><br>
                <small>Terminées</small>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">Logement</label>
                    <select name="logement_id" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        <?php foreach ($logements as $log): ?>
                            <option value="<?= $log['id'] ?>" <?= $logement_filter == $log['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($log['nom_du_logement']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Statut</label>
                    <select name="statut" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        <option value="en attente" <?= $status_filter === 'en attente' ? 'selected' : '' ?>>En attente</option>
                        <option value="en cours" <?= $status_filter === 'en cours' ? 'selected' : '' ?>>En cours</option>
                        <option value="terminée" <?= $status_filter === 'terminée' ? 'selected' : '' ?>>Terminée</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-filter"></i> Filtrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tableau des tâches -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Logement</th>
                            <th>Description</th>
                            <th>Échéance</th>
                            <th>Responsable</th>
                            <th>Prix</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tasks as $task): ?>
                        <tr class="<?= $task['statut'] === 'terminée' ? 'task-done' : '' ?>">
                            <td><?= htmlspecialchars($task['nom_du_logement'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($task['description']) ?></td>
                            <td>
                                <?php if ($task['date_limite']): ?>
                                    <?php
                                    $date = $task['date_limite'];
                                    $isLate = ($task['statut'] !== 'terminée' && $date < date('Y-m-d'));
                                    ?>
                                    <span class="<?= $isLate ? 'text-danger fw-bold' : '' ?>">
                                        <?= date('d/m/Y', strtotime($date)) ?>
                                    </span>
                                    <?php if ($isLate): ?><i class="fas fa-exclamation-triangle text-danger" title="En retard"></i><?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($task['responsable_nom'] ?? '—') ?></td>
                            <td><?= $task['prix_vente'] ? number_format((float) $task['prix_vente'], 2, ',', ' ') . ' €' : '—' ?></td>
                            <td>
                                <form method="POST" class="status-form">
                                    <?php echoCsrfField(); ?>
                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                    <select name="statut" class="form-select form-select-sm change-status" style="width:130px">
                                        <option value="en attente" <?= $task['statut'] === 'en attente' ? 'selected' : '' ?>>En attente</option>
                                        <option value="en cours" <?= $task['statut'] === 'en cours' ? 'selected' : '' ?>>En cours</option>
                                        <option value="terminée" <?= $task['statut'] === 'terminée' ? 'selected' : '' ?>>Terminée</option>
                                    </select>
                                </form>
                            </td>
                            <td class="text-nowrap">
                                <button type="button" class="btn btn-sm btn-warning"
                                        onclick="editTask(<?= htmlspecialchars(json_encode($task)) ?>)" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette tâche ?')">
                                    <?php echoCsrfField(); ?>
                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                    <button type="submit" name="delete_task" class="btn btn-sm btn-danger" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tasks)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucune tâche trouvée.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════ -->
<!-- MODAL : Ajouter / Modifier une tâche                    -->
<!-- ════════════════════════════════════════════════════════ -->
<div class="modal fade" id="taskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white" id="task-modal-header">
                <h5 class="modal-title" id="task-modal-title"><i class="fas fa-plus"></i> Nouvelle tâche</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echoCsrfField(); ?>
                <input type="hidden" name="task_id" id="t_id" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Logement</label>
                        <select class="form-select" name="logement_id" id="t_logement">
                            <option value="">— Aucun —</option>
                            <?php foreach ($logements as $log): ?>
                                <option value="<?= $log['id'] ?>"><?= htmlspecialchars($log['nom_du_logement']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description *</label>
                        <textarea class="form-control" name="description" id="t_desc" rows="3" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Date limite</label>
                            <input type="date" class="form-control" name="date_limite" id="t_date">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Prix (€)</label>
                            <input type="number" step="0.01" class="form-control" name="prix_vente" id="t_prix" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Responsable</label>
                        <select class="form-select" name="responsable" id="t_resp">
                            <option value="">— Aucun —</option>
                            <?php foreach ($intervenants as $int): ?>
                                <option value="<?= $int['id'] ?>"><?= htmlspecialchars($int['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="add_task" class="btn btn-success" id="t_submit">
                        <i class="fas fa-plus"></i> Ajouter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Changement de statut en AJAX
document.querySelectorAll('.change-status').forEach(function(sel) {
    sel.addEventListener('change', function() {
        var form = this.closest('.status-form');
        var data = new FormData(form);
        data.append('update_status', '1');
        fetch('', { method: 'POST', body: data, headers: {'X-Requested-With': 'XMLHttpRequest'} })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                var row = sel.closest('tr');
                if (sel.value === 'terminée') {
                    row.classList.add('task-done');
                } else {
                    row.classList.remove('task-done');
                }
            })
            .catch(function() { location.reload(); });
    });
});

function resetTaskModal() {
    document.getElementById('t_id').value = '';
    document.getElementById('t_logement').value = '<?= $logement_filter ?: '' ?>';
    document.getElementById('t_desc').value = '';
    document.getElementById('t_date').value = '';
    document.getElementById('t_prix').value = '0';
    document.getElementById('t_resp').value = '';
    document.getElementById('task-modal-title').innerHTML = '<i class="fas fa-plus"></i> Nouvelle tâche';
    document.getElementById('task-modal-header').className = 'modal-header bg-success text-white';
    var btn = document.getElementById('t_submit');
    btn.name = 'add_task';
    btn.innerHTML = '<i class="fas fa-plus"></i> Ajouter';
    btn.className = 'btn btn-success';
}

function editTask(t) {
    document.getElementById('t_id').value = t.id;
    document.getElementById('t_logement').value = t.logement_id || '';
    document.getElementById('t_desc').value = t.description || '';
    document.getElementById('t_date').value = t.date_limite || '';
    document.getElementById('t_prix').value = t.prix_vente || 0;
    document.getElementById('t_resp').value = t.responsable || '';
    document.getElementById('task-modal-title').innerHTML = '<i class="fas fa-edit"></i> Modifier la tâche';
    document.getElementById('task-modal-header').className = 'modal-header bg-warning text-dark';
    var btn = document.getElementById('t_submit');
    btn.name = 'save_task';
    btn.innerHTML = '<i class="fas fa-save"></i> Enregistrer';
    btn.className = 'btn btn-warning';
    new bootstrap.Modal(document.getElementById('taskModal')).show();
}
</script>
</body>
</html>
