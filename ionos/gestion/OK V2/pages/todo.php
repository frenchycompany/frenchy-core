<?php
include '../config.php'; // Connexion à la base de données
include '../pages/menu.php'; // Inclusion du menu

$logement_id = isset($_GET['logement_id']) ? (int)$_GET['logement_id'] : null;

// Récupération des intervenants
$intervenants_query = $conn->query("SELECT id, nom FROM intervenant");
$intervenants = $intervenants_query->fetchAll(PDO::FETCH_ASSOC);

// Gestion des tâches
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_task'])) {
        // Mise à jour d'une tâche existante
        $task_id = (int)$_POST['task_id'];
        $description = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING));
        $date_limite = $_POST['date_limite'] ?? null;
        $responsable = $_POST['responsable'] ?? null;
        $prix_vente = (float)$_POST['prix_vente'];

        $stmt = $conn->prepare("UPDATE todo_list SET description = ?, date_limite = ?, responsable = ?, prix_vente = ? WHERE id = ?");
        $stmt->execute([$description, $date_limite, $responsable, $prix_vente, $task_id]);
    } elseif (isset($_POST['add_task'])) {
        // Ajout d'une nouvelle tâche
        $description = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING));
        $date_limite = $_POST['date_limite'] ?? null;
        $responsable = $_POST['responsable'] ?? null;
        $prix_vente = (float)$_POST['prix_vente'];

        $stmt = $conn->prepare("INSERT INTO todo_list (logement_id, description, date_limite, responsable, prix_vente, statut) VALUES (?, ?, ?, ?, ?, 'en attente')");
        $stmt->execute([$logement_id, $description, $date_limite, $responsable, $prix_vente]);
    } elseif (isset($_POST['update_status'])) {
        // Mise à jour du statut uniquement
        $task_id = (int)$_POST['task_id'];
        $new_status = $_POST['statut'];

        $stmt = $conn->prepare("UPDATE todo_list SET statut = ? WHERE id = ?");
        $stmt->execute([$new_status, $task_id]);
        echo json_encode(['status' => 'success']); // Réponse pour AJAX
        exit;
    }
}

// Récupération des tâches associées au logement
if ($logement_id) {
    $tasks_query = $conn->prepare("
        SELECT t.*, i.nom AS responsable_nom 
        FROM todo_list t 
        LEFT JOIN intervenant i ON t.responsable = i.id
        WHERE t.logement_id = ?
    ");
    $tasks_query->execute([$logement_id]);
    $tasks = $tasks_query->fetchAll(PDO::FETCH_ASSOC);
} else {
    $tasks = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Tâches</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h2 class="text-center">Tâches pour le logement</h2>

    <!-- Liste des tâches -->
    <table class="table table-striped">
        <thead>
        <tr>
            <th>Description</th>
            <th>Date Limite</th>
            <th>Responsable</th>
            <th>Prix Vente</th>
            <th>Statut</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($tasks): ?>
            <?php foreach ($tasks as $task): ?>
                <tr>
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
                    <td>
                        <button type="button" class="btn btn-primary btn-sm modifier-tache" 
                                data-id="<?= $task['id'] ?>" 
                                data-description="<?= htmlspecialchars($task['description']) ?>" 
                                data-date_limite="<?= htmlspecialchars($task['date_limite']) ?>" 
                                data-responsable="<?= $task['responsable'] ?>" 
                                data-prix_vente="<?= $task['prix_vente'] ?>">
                            Modifier
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6" class="text-center">Aucune tâche trouvée.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Formulaire pour ajouter une nouvelle tâche -->
    <h3 id="form-title">Ajouter une Nouvelle Tâche</h3>
    <form method="POST" action="">
        <input type="hidden" name="task_id" id="task-id">
        <div class="form-group">
            <label for="description">Description</label>
            <input type="text" name="description" id="description" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="date_limite">Date Limite</label>
            <input type="date" name="date_limite" id="date_limite" class="form-control">
        </div>
        <div class="form-group">
            <label for="responsable">Responsable</label>
            <select name="responsable" id="responsable" class="form-control">
                <option value="">-- Sélectionnez --</option>
                <?php foreach ($intervenants as $intervenant): ?>
                    <option value="<?= $intervenant['id'] ?>"><?= htmlspecialchars($intervenant['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="prix_vente">Prix Vente (€)</label>
            <input type="number" step="0.01" name="prix_vente" id="prix_vente" class="form-control" value="0">
        </div>
        <button type="submit" name="add_task" id="submit-btn" class="btn btn-success">Ajouter la tâche</button>
    </form>
</div>

<!-- Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function () {
        // Gestion du bouton "Modifier"
        $('.modifier-tache').click(function () {
            const taskId = $(this).data('id');
            const description = $(this).data('description');
            const dateLimite = $(this).data('date_limite');
            const responsable = $(this).data('responsable');
            const prixVente = $(this).data('prix_vente');

            $('#task-id').val(taskId);
            $('#description').val(description);
            $('#date_limite').val(dateLimite);
            $('#responsable').val(responsable);
            $('#prix_vente').val(prixVente);

            $('#form-title').text('Modifier la Tâche');
            $('#submit-btn').text('Sauvegarder').attr('name', 'save_task').removeClass('btn-success').addClass('btn-primary');
        });

        // Gestion AJAX pour la mise à jour des statuts
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
                success: function () {
                    alert('Statut mis à jour avec succès.');
                },
                error: function () {
                    alert('Erreur lors de la mise à jour du statut.');
                }
            });
        });
    });
</script>
</body>
</html>
