<?php
/**
 * Liste des modeles de contrat de location — FrenchyConciergerie
 */
include '../config.php';
include '../pages/menu.php';

// Auto-creation des tables si necessaire
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS location_contract_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        placeholders TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {}

$feedback = '';
if (isset($_GET['saved'])) $feedback = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Modele enregistre avec succes</div>';
if (isset($_GET['deleted'])) $feedback = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Modele supprime</div>';
if (isset($_GET['duplicated'])) $feedback = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Modele duplique</div>';

$templates = [];
try {
    $stmt = $conn->query("SELECT id, title, placeholders, updated_at, created_at FROM location_contract_templates ORDER BY updated_at DESC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $feedback = '<div class="alert alert-danger">Erreur: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="fas fa-file-alt text-warning"></i> Modeles de contrat de location</h2>
            <p class="text-muted"><?= count($templates) ?> modele(s) disponible(s)</p>
        </div>
        <div class="col-md-6 text-end">
            <a href="create_location_template.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Nouveau modele
            </a>
            <a href="create_location_contract.php" class="btn btn-warning text-dark">
                <i class="fas fa-file-signature"></i> Creer un contrat
            </a>
            <a href="location_logement_details.php" class="btn btn-outline-info">
                <i class="fas fa-home"></i> Details logements
            </a>
        </div>
    </div>

    <?= $feedback ?>

    <?php if (empty($templates)): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">Aucun modele de contrat de location</h4>
                <p class="text-muted">Creez votre premier modele pour commencer a generer des contrats de location</p>
                <a href="create_location_template.php" class="btn btn-success btn-lg">
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
                                    <td><strong><?= htmlspecialchars($t['title']) ?></strong></td>
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
                                            <a href="edit_location_template.php?id=<?= $t['id'] ?>" class="btn btn-outline-primary" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="duplicate_location_template.php?id=<?= $t['id'] ?>" class="btn btn-outline-secondary" title="Dupliquer">
                                                <i class="fas fa-copy"></i>
                                            </a>
                                            <a href="delete_location_template.php?id=<?= $t['id'] ?>" class="btn btn-outline-danger" title="Supprimer"
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
