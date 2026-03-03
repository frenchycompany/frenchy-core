<?php
// pages/todo_list_complete.php
include '../config.php'; // Inclut la configuration de la base de données
include '../pages/menu.php'; // Inclut le menu de navigation

// Gestion des filtres
$logement_filter = isset($_GET['logement_id']) ? (int)$_GET['logement_id'] : null;
$status_filter = isset($_GET['statut']) ? $_GET['statut'] : null;

// Récupération des logements pour le filtre
$logements_query = $conn->query("SELECT id, nom_du_logement FROM liste_logements");
$logements = $logements_query->fetchAll(PDO::FETCH_ASSOC);

// Construction de la requête principale
$query = "
    SELECT t.*, l.nom_du_logement, i.nom AS responsable_nom
    FROM todo_list t
    LEFT JOIN liste_logements l ON t.logement_id = l.id
    LEFT JOIN intervenant i ON t.responsable = i.id
    WHERE 1 = 1
";

// Ajout des filtres dynamiquement
$params = [];
if ($logement_filter) {
    $query .= " AND t.logement_id = :logement_id";
    $params[':logement_id'] = $logement_filter;
}
if ($status_filter) {
    $query .= " AND t.statut = :statut";
    $params[':statut'] = $status_filter;
}

$query .= " ORDER BY t.date_limite ASC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todo Liste Complète</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="container mt-4">
    <h2 class="text-center">Todo Liste Complète</h2>

    <!-- Filtres -->
    <form method="GET" action="" class="mb-3">
        <div class="row">
            <div class="col-md-4">
                <label for="logement_id">Filtrer par logement :</label>
                <select name="logement_id" id="logement_id" class="form-control">
                    <option value="">Tous les logements</option>
                    <?php foreach ($logements as $logement): ?>
                        <option value="<?= $logement['id'] ?>" <?= $logement_filter == $logement['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($logement['nom_du_logement']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="statut">Filtrer par statut :</label>
                <select name="statut" id="statut" class="form-control">
                    <option value="">Tous les statuts</option>
                    <option value="en attente" <?= $status_filter === 'en attente' ? 'selected' : '' ?>>En attente</option>
                    <option value="en cours" <?= $status_filter === 'en cours' ? 'selected' : '' ?>>En cours</option>
                    <option value="terminée" <?= $status_filter === 'terminée' ? 'selected' : '' ?>>Terminée</option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
            </div>
        </div>
    </form>

    <!-- Liste des tâches -->
    <table class="table table-striped">
        <thead>
        <tr>
            <th>Logement</th>
            <th>Description</th>
            <th>Date Limite</th>
            <th>Responsable</th>
            <th>Prix Vente</th>
            <th>Statut</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($tasks): ?>
            <?php foreach ($tasks as $task): ?>
                <tr>
                    <td><?= htmlspecialchars($task['nom_du_logement']) ?></td>
                    <td><?= htmlspecialchars($task['description']) ?></td>
                    <td><?= htmlspecialchars($task['date_limite']) ?></td>
                    <td><?= htmlspecialchars($task['responsable_nom']) ?></td>
                    <td><?= number_format((float)$task['prix_vente'], 2, ',', ' ') ?> €</td>
                    <td>
                        <select class="form-control form-control-sm change-status" data-task-id="<?= $task['id'] ?>">
                            <option value="en attente" <?= $task['statut'] === 'en attente' ? 'selected' : '' ?>>En attente</option>
                            <option value="en cours" <?= $task['statut'] === 'en cours' ? 'selected' : '' ?>>En cours</option>
                            <option value="terminée" <?= $task['statut'] === 'terminée' ? 'selected' : '' ?>>Terminée</option>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6" class="text-center">Aucune tâche trouvée.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).on('change', '.change-status', function () {
        const taskId = $(this).data('task-id');
        const newStatus = $(this).val();

        $.ajax({
            url: '',
            type: 'POST',
            data: {
                update_status: true,
                task_id: taskId,
                statut: newStatus
            },
            success: function (response) {
                alert('Statut mis à jour avec succès.');
            },
            error: function () {
                alert('Erreur lors de la mise à jour du statut.');
            }
        });
    });
</script>
</body>
</html>
