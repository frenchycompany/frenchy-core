<?php
/**
 * Templates SMS — Page unifiée
 * Gestion des modèles de messages SMS par type et par logement
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_bridge.php';
require_once __DIR__ . '/../includes/rpi_db.php';

$feedback = '';

// Ajouter/modifier un template (RPi)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    if (isset($_POST['save_template'])) {
        $id = !empty($_POST['template_id']) ? (int)$_POST['template_id'] : null;
        $name = trim($_POST['name'] ?? '');
        $template = trim($_POST['template'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!empty($name) && !empty($template)) {
            try {
                $pdoRpi = getRpiPdo();
                if ($id) {
                    $stmt = $pdoRpi->prepare("UPDATE sms_templates SET name = ?, template = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $template, $description, $id]);
                } else {
                    $stmt = $pdoRpi->prepare("INSERT INTO sms_templates (name, template, description) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $template, $description]);
                }
                $feedback = '<div class="alert alert-success">Template enregistré.</div>';
            } catch (PDOException $e) {
                $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    if (isset($_POST['delete_template'])) {
        $id = (int)$_POST['template_id'];
        try {
            $pdoRpi = getRpiPdo();
            $pdoRpi->prepare("DELETE FROM sms_templates WHERE id = ?")->execute([$id]);
            $feedback = '<div class="alert alert-success">Template supprimé.</div>';
        } catch (PDOException $e) {
            $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Récupérer les templates (RPi)
$templates = [];
try {
    $pdoRpi = getRpiPdo();
    $templates = $pdoRpi->query("SELECT * FROM sms_templates ORDER BY name")->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Templates par logement (RPi + lookup VPS pour noms)
$logement_templates = [];
try {
    $pdoRpi = getRpiPdo();
    $logement_templates = $pdoRpi->query("SELECT * FROM sms_logement_templates ORDER BY logement_id, type_message")->fetchAll();
    // Enrichir avec noms de logements (VPS)
    if (!empty($logement_templates)) {
        $logIds = array_unique(array_filter(array_column($logement_templates, 'logement_id')));
        $logNames = [];
        if (!empty($logIds)) {
            $ph = implode(',', array_fill(0, count($logIds), '?'));
            $stmt = $pdo->prepare("SELECT id, nom_du_logement FROM liste_logements WHERE id IN ($ph)");
            $stmt->execute(array_values($logIds));
            foreach ($stmt->fetchAll() as $l) { $logNames[$l['id']] = $l['nom_du_logement']; }
        }
        foreach ($logement_templates as &$lt) {
            $lt['nom_du_logement'] = $logNames[$lt['logement_id'] ?? 0] ?? '';
        }
        unset($lt);
    }
} catch (PDOException $e) { /* ignore */ }

// Logements (VPS)
$logements = [];
try {
    $logements = $pdo->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll();
} catch (PDOException $e) { /* ignore */ }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Templates SMS — FrenchyConciergerie</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-file-alt text-primary"></i> Templates SMS</h2>
            <p class="text-muted"><?= count($templates) ?> template(s) génériques, <?= count($logement_templates) ?> personnalisé(s) par logement</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus"></i> Nouveau template
            </button>
        </div>
    </div>

    <?= $feedback ?>

    <div class="mb-3">
        <small class="text-muted">
            Variables disponibles : <code>{prenom}</code> <code>{nom}</code> <code>{logement}</code> <code>{date_arrivee}</code> <code>{date_depart}</code> <code>{duree}</code>
        </small>
    </div>

    <!-- Templates génériques -->
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Templates génériques</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Nom</th><th>Template</th><th>Description</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($templates as $t): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                            <td><small><code><?= htmlspecialchars(mb_substr($t['template'], 0, 100)) ?></code></small></td>
                            <td><small><?= htmlspecialchars($t['description'] ?? '') ?></small></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-warning" onclick="editTemplate(<?= htmlspecialchars(json_encode($t)) ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')">
                                    <?php echoCsrfField(); ?>
                                    <input type="hidden" name="template_id" value="<?= $t['id'] ?>">
                                    <button type="submit" name="delete_template" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($templates)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">Aucun template.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Templates par logement -->
    <?php if (!empty($logement_templates)): ?>
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Templates personnalisés par logement</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Logement</th><th>Type</th><th>Message</th><th>Actif</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logement_templates as $lt): ?>
                        <tr>
                            <td><?= htmlspecialchars($lt['nom_du_logement'] ?? '') ?></td>
                            <td><span class="badge bg-info"><?= htmlspecialchars($lt['type_message']) ?></span></td>
                            <td><small><?= htmlspecialchars(mb_substr($lt['message'], 0, 80)) ?></small></td>
                            <td><?= $lt['actif'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-muted"></i>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal ajout/édition -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalTitle"><i class="fas fa-plus"></i> Nouveau template</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echoCsrfField(); ?>
                <input type="hidden" name="template_id" id="edit_template_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom *</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required placeholder="ex: checkout, accueil, preparation">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Template *</label>
                        <textarea class="form-control" name="template" id="edit_template" rows="5" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="description" id="edit_description">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="save_template" class="btn btn-success"><i class="fas fa-save"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editTemplate(t) {
    document.getElementById('edit_template_id').value = t.id;
    document.getElementById('edit_name').value = t.name;
    document.getElementById('edit_template').value = t.template;
    document.getElementById('edit_description').value = t.description || '';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Modifier template';
    new bootstrap.Modal(document.getElementById('addModal')).show();
}
</script>
</body>
</html>
