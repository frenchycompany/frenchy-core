<?php
/**
 * Liste des modeles de contrat - Bootstrap 5
 * Supporte les types : conciergerie et location
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/contract_config.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../error.php?message=" . urlencode('Acces reserve aux administrateurs.'));
    exit;
}

$type = detectContractType();
$config = getContractConfig($type);
$table = $config['table_templates'];
$table_fields = $config['table_fields'];

$feedback = '';
if (isset($_GET['saved'])) $feedback = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Modele enregistre avec succes</div>';
if (isset($_GET['deleted'])) $feedback = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Modele supprime</div>';
if (isset($_GET['duplicated'])) $feedback = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Modele duplique</div>';

// Check if fields table has field_group column (for location type)
$hasFieldGroup = false;
if ($type === 'location') {
    try {
        $cols = array_column($conn->query("SHOW COLUMNS FROM `$table_fields`")->fetchAll(), 'Field');
        $hasFieldGroup = in_array('field_group', $cols);
    } catch (PDOException $e) { error_log('list_templates.php: ' . $e->getMessage()); }
}

$templates = [];
try {
    $stmt = $conn->query("SELECT id, title, placeholders, updated_at, created_at FROM `$table` ORDER BY updated_at DESC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('list_templates.php: ' . $e->getMessage());
    $feedback = '<div class="alert alert-danger">Une erreur interne est survenue.</div>';
}

// If location type with field_group, load group info for templates
$fieldGroups = [];
if ($type === 'location' && $hasFieldGroup) {
    try {
        $stmt = $conn->query("SELECT DISTINCT field_group FROM `$table_fields` WHERE field_group IS NOT NULL AND field_group != '' ORDER BY field_group");
        $fieldGroups = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) { error_log('list_templates.php: ' . $e->getMessage()); }
}
?>

<div class="container-fluid mt-4">

    <?= renderContractTypeTabs($type, 'list_templates.php') ?>

    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas <?= $config['icon'] ?> text-<?= $config['color'] ?>"></i> Modeles de contrat — <?= htmlspecialchars($config['label']) ?></h2>
            <p class="text-muted"><?= count($templates) ?> modele(s) disponible(s)</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="create_template.php?type=<?= $type ?>" class="btn btn-success">
                <i class="fas fa-plus"></i> Nouveau modele
            </a>
            <a href="create_contract.php?type=<?= $type ?>" class="btn btn-<?= $config['color'] ?>">
                <i class="fas <?= $config['icon'] ?>"></i> Creer un contrat
            </a>
        </div>
    </div>

    <?= $feedback ?>

    <?php if ($type === 'location' && $hasFieldGroup && !empty($fieldGroups)): ?>
    <div class="alert alert-info mb-3">
        <i class="fas fa-info-circle"></i> Groupes de champs disponibles :
        <?php foreach ($fieldGroups as $group): ?>
            <span class="badge bg-warning text-dark me-1"><?= htmlspecialchars($group) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($templates)): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">Aucun modele de contrat</h4>
                <p class="text-muted">Creez votre premier modele pour commencer a generer des contrats</p>
                <a href="create_template.php?type=<?= $type ?>" class="btn btn-success btn-lg">
                    <i class="fas fa-plus"></i> Creer un modele
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Titre</th>
                                <th>Placeholders</th>
                                <th>Derniere MAJ</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $t): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= $t['id'] ?></span></td>
                                    <td>
                                        <strong><?= htmlspecialchars($t['title']) ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $placeholders = array_filter(array_map('trim', explode(',', $t['placeholders'] ?? '')));
                                        foreach (array_slice($placeholders, 0, 5) as $ph):
                                        ?>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($ph) ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($placeholders) > 5): ?>
                                            <span class="badge bg-info">+<?= count($placeholders) - 5 ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= $t['updated_at'] ? date('d/m/Y H:i', strtotime($t['updated_at'])) : '-' ?>
                                        </small>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit_template.php?type=<?= $type ?>&id=<?= $t['id'] ?>" class="btn btn-outline-primary" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="duplicate_template.php?type=<?= $type ?>&id=<?= $t['id'] ?>" class="btn btn-outline-secondary" title="Dupliquer">
                                                <i class="fas fa-copy"></i>
                                            </a>
                                            <a href="delete_template.php?type=<?= $type ?>&id=<?= $t['id'] ?>" class="btn btn-outline-danger" title="Supprimer"
                                               onclick="return confirm('Supprimer ce modele ?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
