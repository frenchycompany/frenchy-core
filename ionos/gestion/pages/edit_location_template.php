<?php
/**
 * Modifier un modele de contrat de location
 */
include '../config.php';
include '../pages/menu.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: list_location_templates.php");
    exit;
}

$template_id = (int)$_GET['id'];

$template = null;
try {
    $stmt = $conn->prepare("SELECT id, title, content, placeholders FROM location_contract_templates WHERE id = :id");
    $stmt->execute([':id' => $template_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

if (!$template) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Modele introuvable.</div><a href="list_location_templates.php" class="btn btn-secondary">Retour</a></div>';
    exit;
}

// Recuperer les champs dynamiques disponibles
$fields = [];
try {
    $stmt = $conn->query("SELECT field_name, description, field_group FROM location_contract_fields ORDER BY sort_order, field_name");
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$fieldsByGroup = [];
foreach ($fields as $f) {
    $fieldsByGroup[$f['field_group']][] = $f;
}
$groupLabels = ['voyageur' => 'Voyageur', 'reservation' => 'Reservation', 'logement' => 'Logement', 'autre' => 'Autre'];
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-edit text-warning"></i> Modifier le modele de location</h2>
            <p class="text-muted">Modele #<?= $template['id'] ?> — <?= htmlspecialchars($template['title']) ?></p>
        </div>
        <div class="col-md-4 text-end">
            <a href="list_location_templates.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <form action="save_location_template.php" method="POST">
        <?php echoCsrfField(); ?>
        <input type="hidden" name="id" value="<?= $template['id'] ?>">

        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-alt"></i> Contenu du modele</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="title" class="form-label fw-bold">Titre</label>
                            <input type="text" name="title" id="title" class="form-control form-control-lg"
                                   value="<?= htmlspecialchars($template['title']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label fw-bold">Contenu (HTML autorise)</label>
                            <textarea name="content" id="content" class="form-control font-monospace" rows="25" required><?= htmlspecialchars($template['content']) ?></textarea>
                        </div>

                        <?php
                        $currentPlaceholders = array_filter(array_map('trim', explode(',', $template['placeholders'] ?? '')));
                        if (!empty($currentPlaceholders)):
                        ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Placeholders detectes</label>
                            <div>
                                <?php foreach ($currentPlaceholders as $ph): ?>
                                    <span class="badge bg-warning text-dark me-1 mb-1"><?= htmlspecialchars($ph) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted">Ces placeholders seront automatiquement mis a jour lors de la sauvegarde</small>
                        </div>
                        <?php endif; ?>

                        <div class="text-end">
                            <a href="list_location_templates.php" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-save"></i> Enregistrer
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-start border-warning border-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-code"></i> Placeholders disponibles</h5>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-3">Cliquez pour inserer dans le contenu</p>

                        <?php if (!empty($fieldsByGroup)): ?>
                            <?php foreach ($groupLabels as $groupKey => $groupLabel): ?>
                                <?php if (!empty($fieldsByGroup[$groupKey])): ?>
                                    <h6 class="text-uppercase text-muted mt-3 mb-2"><small><?= $groupLabel ?></small></h6>
                                    <div class="list-group list-group-flush mb-2">
                                        <?php foreach ($fieldsByGroup[$groupKey] as $f): ?>
                                            <a href="#" class="list-group-item list-group-item-action shortcode-btn py-1"
                                               data-code="{{<?= htmlspecialchars($f['field_name']) ?>}}">
                                                <code class="text-primary small">{{<?= htmlspecialchars($f['field_name']) ?>}}</code>
                                                <br><small class="text-muted"><?= htmlspecialchars($f['description']) ?></small>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-muted small">
                                <p>Aucun champ predefini. Utilisez <code>{{nom}}</code> dans le contenu.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.querySelectorAll('.shortcode-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const code = this.dataset.code;
        const textarea = document.getElementById('content');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        textarea.value = text.substring(0, start) + code + text.substring(end);
        textarea.selectionStart = textarea.selectionEnd = start + code.length;
        textarea.focus();
    });
});
</script>
