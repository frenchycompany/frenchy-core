<?php
/**
 * Historique des contrats de location generes
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';

// Auto-creation table
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS generated_location_contracts (
        id INT AUTO_INCREMENT PRIMARY KEY, user_id INT DEFAULT NULL, logement_id INT NOT NULL,
        template_title VARCHAR(255), logement_nom VARCHAR(255), voyageur_nom VARCHAR(255),
        date_arrivee DATE, date_depart DATE, prix_total DECIMAL(10,2),
        file_path VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {}

$feedback = '';

// === ACTIONS POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    if (isset($_POST['delete_contract'])) {
        $id = (int)$_POST['contract_id'];
        try {
            $stmt = $conn->prepare("SELECT file_path FROM generated_location_contracts WHERE id = ?");
            $stmt->execute([$id]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($contract) {
                $fullPath = __DIR__ . '/../' . $contract['file_path'];
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
                $conn->prepare("DELETE FROM generated_location_contracts WHERE id = ?")->execute([$id]);
                $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Contrat supprime</div>";
            }
        } catch (PDOException $e) {
            $feedback = "<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    if (isset($_POST['delete_selected'])) {
        $ids = $_POST['contract_ids'] ?? [];
        $deleted = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            try {
                $stmt = $conn->prepare("SELECT file_path FROM generated_location_contracts WHERE id = ?");
                $stmt->execute([$id]);
                $contract = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($contract) {
                    $fullPath = __DIR__ . '/../' . $contract['file_path'];
                    if (file_exists($fullPath)) @unlink($fullPath);
                    $conn->prepare("DELETE FROM generated_location_contracts WHERE id = ?")->execute([$id]);
                    $deleted++;
                }
            } catch (PDOException $e) {}
        }
        if ($deleted > 0) {
            $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> $deleted contrat(s) supprime(s)</div>";
        }
    }
}

// === DONNEES ===
$filter_logement = (int)($_GET['logement'] ?? 0);

$sql = "
    SELECT glc.*, l.nom_du_logement, i.nom as intervenant_nom
    FROM generated_location_contracts glc
    LEFT JOIN liste_logements l ON glc.logement_id = l.id
    LEFT JOIN intervenant i ON glc.user_id = i.id
";
$params = [];
if ($filter_logement > 0) {
    $sql .= " WHERE glc.logement_id = ?";
    $params[] = $filter_logement;
}
$sql .= " ORDER BY glc.created_at DESC";

$contrats = [];
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $contrats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

foreach ($contrats as &$c) {
    $c['file_exists'] = file_exists(__DIR__ . '/../' . $c['file_path']);
}
unset($c);

$logements = [];
try {
    $logements = $conn->query("SELECT id, nom_du_logement FROM liste_logements ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$total_contrats = count($contrats);
$contrats_ce_mois = count(array_filter($contrats, fn($c) =>
    date('Y-m', strtotime($c['created_at'])) === date('Y-m')
));
?>

<div class="container-fluid mt-3">

    <?= $feedback ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2><i class="fas fa-file-signature text-warning"></i> Contrats de location generes</h2>
            <p class="text-muted mb-0">Historique de tous les contrats de location</p>
        </div>
        <div>
            <a href="create_location_contract.php" class="btn btn-warning text-dark">
                <i class="fas fa-plus"></i> Nouveau contrat
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body py-2">
                    <div class="h4 mb-0 text-warning"><?= $total_contrats ?></div>
                    <small class="text-muted">Total contrats</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body py-2">
                    <div class="h4 mb-0 text-success"><?= $contrats_ce_mois ?></div>
                    <small class="text-muted">Ce mois</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body py-2">
                    <div class="h4 mb-0"><?= count(array_unique(array_column($contrats, 'logement_id'))) ?></div>
                    <small class="text-muted">Logements</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtre -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="d-flex gap-2 align-items-center">
                <label class="form-label mb-0 me-2">Filtrer :</label>
                <select name="logement" class="form-select form-select-sm" style="width:250px" onchange="this.form.submit()">
                    <option value="">Tous les logements</option>
                    <?php foreach ($logements as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= $filter_logement == $l['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($l['nom_du_logement']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($filter_logement): ?>
                <a href="location_contrats_generes.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i> Reset</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Liste -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($contrats)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-file-alt fa-3x mb-3"></i>
                    <h5>Aucun contrat de location genere</h5>
                    <p>Creez votre premier contrat depuis la page <a href="create_location_contract.php">Creer contrat de location</a></p>
                </div>
            <?php else: ?>
            <form method="POST" id="batchForm">
                <?php echoCsrfField(); ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:30px"><input type="checkbox" id="checkAll" onclick="toggleAll(this)"></th>
                                <th>#</th>
                                <th>Logement</th>
                                <th>Voyageur</th>
                                <th>Sejour</th>
                                <th>Prix</th>
                                <th>Date</th>
                                <th>Fichier</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($contrats as $c): ?>
                        <tr>
                            <td><input type="checkbox" name="contract_ids[]" value="<?= $c['id'] ?>" class="contract-check"></td>
                            <td><strong>#<?= $c['id'] ?></strong></td>
                            <td>
                                <?php if ($c['nom_du_logement'] ?? $c['logement_nom'] ?? null): ?>
                                <span class="badge bg-info"><?= htmlspecialchars($c['nom_du_logement'] ?? $c['logement_nom']) ?></span>
                                <?php else: ?>
                                <span class="text-muted">Logement #<?= $c['logement_id'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($c['voyageur_nom']): ?>
                                <strong><?= htmlspecialchars($c['voyageur_nom']) ?></strong>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-nowrap">
                                <?php if ($c['date_arrivee'] && $c['date_depart']): ?>
                                <?= date('d/m', strtotime($c['date_arrivee'])) ?> - <?= date('d/m/Y', strtotime($c['date_depart'])) ?>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if ($c['prix_total']): ?>
                                <strong><?= number_format($c['prix_total'], 0, ',', ' ') ?> EUR</strong>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                            <td class="small text-nowrap">
                                <?= date('d/m/Y', strtotime($c['created_at'])) ?>
                                <br><span class="text-muted"><?= date('H:i', strtotime($c['created_at'])) ?></span>
                            </td>
                            <td>
                                <?php if ($c['file_exists']): ?>
                                <span class="badge bg-success"><i class="fas fa-check"></i></span>
                                <?php else: ?>
                                <span class="badge bg-danger"><i class="fas fa-times"></i></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <?php if ($c['file_exists']): ?>
                                <a href="../<?= htmlspecialchars($c['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Voir">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="../<?= htmlspecialchars($c['file_path']) ?>" download class="btn btn-sm btn-outline-success" title="Telecharger">
                                    <i class="fas fa-download"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printContract('../<?= htmlspecialchars($c['file_path']) ?>')" title="Imprimer">
                                    <i class="fas fa-print"></i>
                                </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteContract(<?= $c['id'] ?>)" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card-footer d-flex justify-content-between align-items-center">
                    <span class="text-muted small" id="selectedCount">0 selectionne(s)</span>
                    <button type="submit" name="delete_selected" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer les contrats selectionnes ?')" id="btnDeleteSelected" disabled>
                        <i class="fas fa-trash"></i> Supprimer la selection
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Formulaire suppression individuelle -->
<form method="POST" id="deleteForm" style="display:none">
    <?php echoCsrfField(); ?>
    <input type="hidden" name="contract_id" id="deleteContractId">
    <input type="hidden" name="delete_contract" value="1">
</form>

<script>
function deleteContract(id) {
    if (confirm('Supprimer ce contrat ?')) {
        document.getElementById('deleteContractId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

function printContract(url) {
    const w = window.open(url, '_blank');
    w.addEventListener('load', () => { w.print(); });
}

function toggleAll(el) {
    document.querySelectorAll('.contract-check').forEach(cb => { cb.checked = el.checked; });
    updateCount();
}

document.querySelectorAll('.contract-check').forEach(cb => {
    cb.addEventListener('change', updateCount);
});

function updateCount() {
    const checked = document.querySelectorAll('.contract-check:checked').length;
    document.getElementById('selectedCount').textContent = checked + ' selectionne(s)';
    document.getElementById('btnDeleteSelected').disabled = checked === 0;
}
</script>
