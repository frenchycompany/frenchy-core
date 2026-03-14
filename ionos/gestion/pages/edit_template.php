<?php
/**
 * Modifier un modele de contrat - Bootstrap 5
 * Supporte les types : conciergerie et location
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/contract_config.php';

$type = detectContractType();
$config = getContractConfig($type);
$table = $config['table_templates'];
$table_fields = $config['table_fields'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: list_templates.php?type=$type");
    exit;
}

$template_id = (int)$_GET['id'];

// Recuperer le modele
$template = null;
try {
    $stmt = $conn->prepare("SELECT id, title, content, placeholders FROM `$table` WHERE id = :id");
    $stmt->execute([':id' => $template_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('edit_template.php: ' . $e->getMessage()); }

if (!$template) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Modele introuvable.</div><a href="list_templates.php?type=' . $type . '" class="btn btn-secondary">Retour</a></div>';
    exit;
}

// Recuperer les champs dynamiques disponibles
$fields = [];
try {
    $stmt = $conn->query("SELECT field_name, description FROM `$table_fields` ORDER BY field_name");
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('edit_template.php: ' . $e->getMessage()); }

// For location type, check if fields have field_group
$fieldsByGroup = [];
if ($type === 'location') {
    try {
        $cols = array_column($conn->query("SHOW COLUMNS FROM `$table_fields`")->fetchAll(), 'Field');
        if (in_array('field_group', $cols)) {
            $stmt = $conn->query("SELECT field_name, description, field_group FROM `$table_fields` ORDER BY field_group, field_name");
            $fieldsWithGroup = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($fieldsWithGroup as $f) {
                $group = $f['field_group'] ?: 'Autres';
                $fieldsByGroup[$group][] = $f;
            }
        }
    } catch (PDOException $e) { error_log('edit_template.php: ' . $e->getMessage()); }
}
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-edit text-<?= $config['color'] ?>"></i> Modifier le modele (<?= htmlspecialchars($config['label']) ?>)</h2>
            <p class="text-muted">Modele #<?= $template['id'] ?> — <?= htmlspecialchars($template['title']) ?></p>
        </div>
        <div class="col-md-4 text-end">
            <a href="list_templates.php?type=<?= $type ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <form action="save_template.php?type=<?= $type ?>" method="POST">
        <?php echoCsrfField(); ?>
        <input type="hidden" name="id" value="<?= $template['id'] ?>">
        <input type="hidden" name="contract_type" value="<?= $type ?>">

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
                            <textarea name="content" id="content" class="form-control font-monospace" rows="20" required><?= htmlspecialchars($template['content']) ?></textarea>
                        </div>

                        <?php
                        $currentPlaceholders = array_filter(array_map('trim', explode(',', $template['placeholders'] ?? '')));
                        if (!empty($currentPlaceholders)):
                        ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Placeholders detectes</label>
                            <div>
                                <?php foreach ($currentPlaceholders as $ph): ?>
                                    <span class="badge bg-<?= $config['color'] ?> me-1 mb-1"><?= htmlspecialchars($ph) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted">Ces placeholders seront automatiquement mis a jour lors de la sauvegarde</small>
                        </div>
                        <?php endif; ?>

                        <div class="text-end">
                            <a href="list_templates.php?type=<?= $type ?>" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-save"></i> Enregistrer
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-start border-info border-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-code"></i> Placeholders disponibles</h5>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-3">Cliquez pour inserer dans le contenu</p>

                        <?php if (!empty($fieldsByGroup)): ?>
                            <?php foreach ($fieldsByGroup as $group => $groupFields): ?>
                                <h6 class="mt-3 mb-2 text-<?= $config['color'] ?>"><?= htmlspecialchars($group) ?></h6>
                                <div class="list-group list-group-flush mb-2">
                                    <?php foreach ($groupFields as $f): ?>
                                        <a href="#" class="list-group-item list-group-item-action shortcode-btn py-2"
                                           data-code="{{<?= htmlspecialchars($f['field_name']) ?>}}">
                                            <code class="text-primary">{{<?= htmlspecialchars($f['field_name']) ?>}}</code>
                                            <br><small class="text-muted"><?= htmlspecialchars($f['description']) ?></small>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif (!empty($fields)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($fields as $f): ?>
                                    <a href="#" class="list-group-item list-group-item-action shortcode-btn py-2"
                                       data-code="{{<?= htmlspecialchars($f['field_name']) ?>}}">
                                        <code class="text-primary">{{<?= htmlspecialchars($f['field_name']) ?>}}</code>
                                        <br><small class="text-muted"><?= htmlspecialchars($f['description']) ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
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
