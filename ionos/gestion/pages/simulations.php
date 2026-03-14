<?php
/**
 * Simulations — Dashboard des soumissions du simulateur marketing
 * FrenchyConciergerie
 */
ob_start();

include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/lead_scoring.php';

// Acces admin uniquement
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../error.php?message=" . urlencode('Acces reserve aux administrateurs.'));
    exit;
}

$feedback = '';

// ============================================================
// ACTIONS POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    // --- Marquer comme contacte ---
    if (isset($_POST['mark_contacted'])) {
        $simId = (int) $_POST['mark_contacted'];
        try {
            $conn->prepare("UPDATE FC_simulations SET contacted = 1 WHERE id = ?")->execute([$simId]);
            $feedback = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-1"></i>Simulation marquee comme contactee.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (PDOException $e) {
            error_log("simulations.php mark_contacted error: " . $e->getMessage());
            $feedback = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-1"></i>Une erreur est survenue lors de la mise a jour.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    }

    // --- Ajouter / modifier une note ---
    if (isset($_POST['add_note'])) {
        $simId = (int) $_POST['simulation_id'];
        $notes = trim($_POST['notes'] ?? '');
        try {
            $conn->prepare("UPDATE FC_simulations SET notes = ? WHERE id = ?")->execute([$notes, $simId]);
            $feedback = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-1"></i>Note mise a jour.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (PDOException $e) {
            error_log("simulations.php add_note error: " . $e->getMessage());
            $feedback = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-1"></i>Une erreur est survenue lors de la mise a jour de la note.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    }

    // --- Creer un lead depuis une simulation ---
    if (isset($_POST['create_lead'])) {
        $simId = (int) $_POST['create_lead'];
        try {
            $stmt = $conn->prepare("SELECT * FROM FC_simulations WHERE id = ?");
            $stmt->execute([$simId]);
            $sim = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sim) {
                $feedback = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-1"></i>Simulation introuvable.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            } else {
                // Verifier si un lead existe deja pour cette simulation
                $checkStmt = $conn->prepare("SELECT id FROM prospection_leads WHERE legacy_simulation_id = ?");
                $checkStmt->execute([$simId]);
                if ($checkStmt->fetch()) {
                    $feedback = '<div class="alert alert-warning alert-dismissible fade show"><i class="fas fa-info-circle me-1"></i>Un lead existe deja pour cette simulation.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                } else {
                    $leadData = [
                        'source'                => 'simulateur',
                        'email'                 => $sim['email'] ?? '',
                        'telephone'             => $sim['telephone'] ?? '',
                        'ville'                 => $sim['ville'] ?? '',
                        'surface'               => $sim['surface'] ?? null,
                        'capacite'              => $sim['capacite'] ?? null,
                        'tarif_nuit_estime'     => $sim['tarif_nuit_estime'] ?? null,
                        'revenu_mensuel_estime' => $sim['revenu_mensuel_estime'] ?? null,
                        'legacy_simulation_id'  => $simId,
                    ];

                    $leadId = createLead($conn, $leadData);

                    if ($leadId) {
                        // Marquer comme contacte automatiquement
                        $conn->prepare("UPDATE FC_simulations SET contacted = 1 WHERE id = ?")->execute([$simId]);
                        $feedback = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-1"></i>Lead cree avec succes (ID: ' . (int)$leadId . ').<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    } else {
                        $feedback = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-1"></i>Erreur lors de la creation du lead.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("simulations.php create_lead error: " . $e->getMessage());
            $feedback = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-1"></i>Une erreur est survenue lors de la creation du lead.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    }

    // --- Import en masse : creer des leads pour toutes les simulations non contactees ---
    if (isset($_POST['bulk_create_leads'])) {
        try {
            $stmt = $conn->query("
                SELECT s.* FROM FC_simulations s
                LEFT JOIN prospection_leads pl ON pl.legacy_simulation_id = s.id
                WHERE s.contacted = 0 AND pl.id IS NULL
            ");
            $simulations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $created = 0;
            $errors = 0;
            foreach ($simulations as $sim) {
                $leadData = [
                    'source'                => 'simulateur',
                    'email'                 => $sim['email'] ?? '',
                    'telephone'             => $sim['telephone'] ?? '',
                    'ville'                 => $sim['ville'] ?? '',
                    'surface'               => $sim['surface'] ?? null,
                    'capacite'              => $sim['capacite'] ?? null,
                    'tarif_nuit_estime'     => $sim['tarif_nuit_estime'] ?? null,
                    'revenu_mensuel_estime' => $sim['revenu_mensuel_estime'] ?? null,
                    'legacy_simulation_id'  => $sim['id'],
                ];

                $leadId = createLead($conn, $leadData);
                if ($leadId) {
                    $conn->prepare("UPDATE FC_simulations SET contacted = 1 WHERE id = ?")->execute([$sim['id']]);
                    $created++;
                } else {
                    $errors++;
                }
            }

            if ($created > 0) {
                $feedback = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-1"></i>' . $created . ' lead(s) cree(s) avec succes.';
                if ($errors > 0) {
                    $feedback .= ' ' . $errors . ' erreur(s).';
                }
                $feedback .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            } elseif (count($simulations) === 0) {
                $feedback = '<div class="alert alert-info alert-dismissible fade show"><i class="fas fa-info-circle me-1"></i>Aucune simulation non contactee a importer.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            } else {
                $feedback = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-1"></i>Erreur lors de l\'import en masse.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            }
        } catch (PDOException $e) {
            error_log("simulations.php bulk_create_leads error: " . $e->getMessage());
            $feedback = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-1"></i>Une erreur est survenue lors de l\'import en masse.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    }
}

// ============================================================
// FILTRES & PAGINATION
// ============================================================
$search = trim($_GET['search'] ?? '');
$filterContacted = $_GET['contacted'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$conditions = [];
$params = [];

if ($search !== '') {
    $conditions[] = "(email LIKE :search OR ville LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if ($filterContacted !== '') {
    $conditions[] = "contacted = :contacted";
    $params[':contacted'] = (int) $filterContacted;
}

$whereClause = count($conditions) > 0 ? "WHERE " . implode(" AND ", $conditions) : "";

// ============================================================
// STATISTIQUES
// ============================================================
try {
    $totalSimulations = (int) $conn->query("SELECT COUNT(*) FROM FC_simulations")->fetchColumn();

    $nonContactees = (int) $conn->query("SELECT COUNT(*) FROM FC_simulations WHERE contacted = 0")->fetchColumn();

    $revenuMoyen = $conn->query("SELECT AVG(revenu_mensuel_estime) FROM FC_simulations WHERE revenu_mensuel_estime > 0")->fetchColumn();
    $revenuMoyen = $revenuMoyen ? round((float)$revenuMoyen, 0) : 0;

    $topVilles = $conn->query("
        SELECT ville, COUNT(*) as nb FROM FC_simulations
        WHERE ville IS NOT NULL AND ville != ''
        GROUP BY ville ORDER BY nb DESC LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("simulations.php stats error: " . $e->getMessage());
    $totalSimulations = 0;
    $nonContactees = 0;
    $revenuMoyen = 0;
    $topVilles = [];
}

// ============================================================
// DONNEES TABLEAU
// ============================================================
try {
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM FC_simulations $whereClause");
    $countStmt->execute($params);
    $totalFiltered = (int) $countStmt->fetchColumn();

    $totalPages = max(1, ceil($totalFiltered / $perPage));
    if ($page > $totalPages) $page = $totalPages;

    $dataStmt = $conn->prepare("
        SELECT * FROM FC_simulations
        $whereClause
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $key => $val) {
        $dataStmt->bindValue($key, $val);
    }
    $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    $simulations = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("simulations.php data error: " . $e->getMessage());
    $simulations = [];
    $totalFiltered = 0;
    $totalPages = 1;
}

// Construire l'URL de base pour la pagination
$queryParams = [];
if ($search !== '') $queryParams['search'] = $search;
if ($filterContacted !== '') $queryParams['contacted'] = $filterContacted;
$baseUrl = 'simulations.php?' . http_build_query($queryParams);
?>

<style>
    .row-contacted { background-color: rgba(25, 135, 84, 0.08); }
    .stat-card { border-left: 4px solid; }
    .stat-card .stat-value { font-size: 1.5rem; font-weight: 700; }
    .table th { white-space: nowrap; }
    .badge-ville { font-size: 0.8rem; }
</style>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-chart-line me-2"></i>Simulations</h2>
        <form method="post" class="d-inline" onsubmit="return confirm('Importer toutes les simulations non contactees comme leads ?');">
            <?php echoCsrfField(); ?>
            <button type="submit" name="bulk_create_leads" value="1" class="btn btn-outline-primary">
                <i class="fas fa-file-import me-1"></i>Import masse vers leads
            </button>
        </form>
    </div>

    <?= $feedback ?>

    <!-- ========== STATS ========== -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card border-primary">
                <div class="card-body">
                    <div class="text-muted small"><i class="fas fa-database me-1"></i>Total simulations</div>
                    <div class="stat-value text-primary"><?= $totalSimulations ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-warning">
                <div class="card-body">
                    <div class="text-muted small"><i class="fas fa-clock me-1"></i>Non contactees</div>
                    <div class="stat-value text-warning"><?= $nonContactees ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-success">
                <div class="card-body">
                    <div class="text-muted small"><i class="fas fa-euro-sign me-1"></i>Revenu moyen estime</div>
                    <div class="stat-value text-success"><?= number_format($revenuMoyen, 0, ',', ' ') ?> &euro;/mois</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-info">
                <div class="card-body">
                    <div class="text-muted small"><i class="fas fa-map-marker-alt me-1"></i>Villes frequentes</div>
                    <div class="mt-1">
                        <?php if (!empty($topVilles)): ?>
                            <?php foreach ($topVilles as $v): ?>
                                <span class="badge bg-info badge-ville me-1"><?= htmlspecialchars($v['ville']) ?> (<?= $v['nb'] ?>)</span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">Aucune donnee</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== FILTRES ========== -->
    <div class="card mb-4">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-0">Recherche (email, ville)</label>
                    <input type="text" name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher...">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0">Statut contact</label>
                    <select name="contacted" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        <option value="0" <?= $filterContacted === '0' ? 'selected' : '' ?>>Non contactees</option>
                        <option value="1" <?= $filterContacted === '1' ? 'selected' : '' ?>>Contactees</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-search me-1"></i>Filtrer</button>
                </div>
                <div class="col-md-2">
                    <a href="simulations.php" class="btn btn-sm btn-outline-secondary w-100"><i class="fas fa-times me-1"></i>Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- ========== TABLEAU ========== -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list me-1"></i><?= $totalFiltered ?> simulation(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th>Email</th>
                        <th>Telephone</th>
                        <th>Ville</th>
                        <th>Surface</th>
                        <th>Capacite</th>
                        <th>Tarif/nuit</th>
                        <th>Revenu/mois</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($simulations)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>Aucune simulation trouvee.</td></tr>
                    <?php else: ?>
                        <?php foreach ($simulations as $sim): ?>
                            <tr class="<?= $sim['contacted'] ? 'row-contacted' : '' ?>">
                                <td class="small"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($sim['created_at']))) ?></td>
                                <td><?= htmlspecialchars($sim['email'] ?? '') ?></td>
                                <td><?= htmlspecialchars($sim['telephone'] ?? '') ?></td>
                                <td><?= htmlspecialchars($sim['ville'] ?? '') ?></td>
                                <td><?= htmlspecialchars($sim['surface'] ?? '-') ?> m&sup2;</td>
                                <td><?= htmlspecialchars($sim['capacite'] ?? '-') ?></td>
                                <td><?= $sim['tarif_nuit_estime'] ? number_format((float)$sim['tarif_nuit_estime'], 0, ',', ' ') . ' &euro;' : '-' ?></td>
                                <td><?= $sim['revenu_mensuel_estime'] ? number_format((float)$sim['revenu_mensuel_estime'], 0, ',', ' ') . ' &euro;' : '-' ?></td>
                                <td>
                                    <?php if ($sim['contacted']): ?>
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i>Contactee</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="fas fa-clock me-1"></i>En attente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if (!$sim['contacted']): ?>
                                            <form method="post" class="d-inline">
                                                <?php echoCsrfField(); ?>
                                                <button type="submit" name="mark_contacted" value="<?= (int)$sim['id'] ?>" class="btn btn-sm btn-outline-success" title="Marquer contactee">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <button type="button" class="btn btn-sm btn-outline-info" title="Notes" data-bs-toggle="modal" data-bs-target="#noteModal<?= (int)$sim['id'] ?>">
                                            <i class="fas fa-sticky-note"></i>
                                        </button>

                                        <form method="post" class="d-inline" onsubmit="return confirm('Creer un lead depuis cette simulation ?');">
                                            <?php echoCsrfField(); ?>
                                            <button type="submit" name="create_lead" value="<?= (int)$sim['id'] ?>" class="btn btn-sm btn-outline-primary" title="Creer un lead">
                                                <i class="fas fa-user-plus"></i>
                                            </button>
                                        </form>

                                        <button type="button" class="btn btn-sm btn-outline-secondary" title="Details" data-bs-toggle="modal" data-bs-target="#detailModal<?= (int)$sim['id'] ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= htmlspecialchars($baseUrl . '&page=' . ($page - 1)) ?>">&laquo;</a>
                        </li>
                        <?php
                        $start = max(1, $page - 3);
                        $end = min($totalPages, $page + 3);
                        if ($start > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?= htmlspecialchars($baseUrl . '&page=1') ?>">1</a></li>
                            <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                        <?php endif; ?>
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars($baseUrl . '&page=' . $i) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?= htmlspecialchars($baseUrl . '&page=' . $totalPages) ?>"><?= $totalPages ?></a></li>
                        <?php endif; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= htmlspecialchars($baseUrl . '&page=' . ($page + 1)) ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========== MODALS: Notes & Details ========== -->
<?php foreach ($simulations as $sim): ?>
    <!-- Modal Notes -->
    <div class="modal fade" id="noteModal<?= (int)$sim['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <?php echoCsrfField(); ?>
                    <input type="hidden" name="simulation_id" value="<?= (int)$sim['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-sticky-note me-2"></i>Notes — <?= htmlspecialchars($sim['email'] ?? 'Simulation #' . $sim['id']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <textarea name="notes" class="form-control" rows="5" placeholder="Ajouter des notes..."><?= htmlspecialchars($sim['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="add_note" value="1" class="btn btn-primary"><i class="fas fa-save me-1"></i>Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Details -->
    <div class="modal fade" id="detailModal<?= (int)$sim['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Details — Simulation #<?= (int)$sim['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted">Contact</h6>
                            <table class="table table-sm">
                                <tr><th>Email</th><td><?= htmlspecialchars($sim['email'] ?? '-') ?></td></tr>
                                <tr><th>Telephone</th><td><?= htmlspecialchars($sim['telephone'] ?? '-') ?></td></tr>
                                <tr><th>Date</th><td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($sim['created_at']))) ?></td></tr>
                                <tr><th>Statut</th><td><?= $sim['contacted'] ? '<span class="badge bg-success">Contactee</span>' : '<span class="badge bg-secondary">En attente</span>' ?></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Bien</h6>
                            <table class="table table-sm">
                                <tr><th>Ville</th><td><?= htmlspecialchars($sim['ville'] ?? '-') ?></td></tr>
                                <tr><th>Surface</th><td><?= htmlspecialchars($sim['surface'] ?? '-') ?> m&sup2;</td></tr>
                                <tr><th>Capacite</th><td><?= htmlspecialchars($sim['capacite'] ?? '-') ?> pers.</td></tr>
                                <tr><th>Centre-ville</th><td><?= isset($sim['centre_ville']) ? ($sim['centre_ville'] ? 'Oui' : 'Non') : '-' ?></td></tr>
                                <tr><th>Fibre</th><td><?= isset($sim['fibre']) ? ($sim['fibre'] ? 'Oui' : 'Non') : '-' ?></td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <h6 class="text-muted">Equipements</h6>
                            <table class="table table-sm">
                                <tr><th>Equipements speciaux</th><td><?= htmlspecialchars($sim['equipements_speciaux'] ?? '-') ?></td></tr>
                                <tr><th>Machine a cafe</th><td><?= isset($sim['machine_cafe']) ? ($sim['machine_cafe'] ? 'Oui' : 'Non') : '-' ?></td></tr>
                                <tr><th>Machine a laver</th><td><?= isset($sim['machine_laver']) ? ($sim['machine_laver'] ? 'Oui' : 'Non') : '-' ?></td></tr>
                                <tr><th>Autre equipement</th><td><?= htmlspecialchars($sim['autre_equipement'] ?? '-') ?></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Estimation</h6>
                            <table class="table table-sm">
                                <tr><th>Tarif/nuit</th><td><?= $sim['tarif_nuit_estime'] ? number_format((float)$sim['tarif_nuit_estime'], 0, ',', ' ') . ' &euro;' : '-' ?></td></tr>
                                <tr><th>Revenu/mois</th><td><?= $sim['revenu_mensuel_estime'] ? number_format((float)$sim['revenu_mensuel_estime'], 0, ',', ' ') . ' &euro;' : '-' ?></td></tr>
                            </table>
                            <?php if (!empty($sim['notes'])): ?>
                                <h6 class="text-muted">Notes</h6>
                                <p class="bg-light p-2 rounded"><?= nl2br(htmlspecialchars($sim['notes'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php ob_end_flush(); ?>
