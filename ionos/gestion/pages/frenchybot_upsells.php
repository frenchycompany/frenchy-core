<?php
/**
 * FrenchyBot — Admin Upsells
 * Gestion des services additionnels proposés aux voyageurs dans le HUB
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';

// --- Actions POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_upsell') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $icon = trim($_POST['icon'] ?? 'fa-gift');
        $stripeLink = trim($_POST['stripe_link'] ?? '');
        $logementId = !empty($_POST['logement_id']) ? (int)$_POST['logement_id'] : null;
        $active = isset($_POST['active']) ? 1 : 0;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($name && $label && $price > 0) {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE upsells SET name=?, label=?, description=?, price=?, icon=?, stripe_link=?, logement_id=?, active=?, sort_order=? WHERE id=?");
                $stmt->execute([$name, $label, $description, $price, $icon, $stripeLink, $logementId, $active, $sortOrder, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO upsells (name, label, description, price, icon, stripe_link, logement_id, active, sort_order) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$name, $label, $description, $price, $icon, $stripeLink, $logementId, $active, $sortOrder]);
            }
            $_SESSION['flash'] = 'Upsell sauvegarde.';
        }
    }

    if ($action === 'delete_upsell') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM upsells WHERE id = ?")->execute([$id]);
            $_SESSION['flash'] = 'Upsell supprime.';
        }
    }

    if ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE upsells SET active = NOT active WHERE id = ?")->execute([$id]);
        }
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// --- Donnees ---
$upsells = $pdo->query("
    SELECT u.*, l.nom_du_logement,
           (SELECT COUNT(*) FROM upsell_orders WHERE upsell_id = u.id) AS nb_orders,
           (SELECT COUNT(*) FROM upsell_orders WHERE upsell_id = u.id AND status = 'paid') AS nb_paid,
           (SELECT COALESCE(SUM(amount), 0) FROM upsell_orders WHERE upsell_id = u.id AND status = 'paid') AS revenue
    FROM upsells u
    LEFT JOIN liste_logements l ON u.logement_id = l.id
    ORDER BY u.sort_order, u.id
")->fetchAll(PDO::FETCH_ASSOC);

$logements = $pdo->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);

// Stats globales
try {
    $totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM upsell_orders WHERE status = 'paid'")->fetchColumn();
    $totalOrders = $pdo->query("SELECT COUNT(*) FROM upsell_orders WHERE status = 'paid'")->fetchColumn();
    $pendingOrders = $pdo->query("SELECT COUNT(*) FROM upsell_orders WHERE status = 'pending'")->fetchColumn();
} catch (\PDOException $e) {
    $totalRevenue = $totalOrders = $pendingOrders = 0;
}
?>

<div class="container-fluid py-4">
    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-shopping-cart text-primary"></i> Upsells</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editModal" onclick="editUpsell(null)">
            <i class="fas fa-plus"></i> Nouvel upsell
        </button>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold text-success"><?= number_format($totalRevenue, 2) ?> €</div>
                    <div class="text-muted small">Revenus totaux</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold text-primary"><?= $totalOrders ?></div>
                    <div class="text-muted small">Commandes payees</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold text-warning"><?= $pendingOrders ?></div>
                    <div class="text-muted small">En attente</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des upsells -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ordre</th>
                            <th>Upsell</th>
                            <th>Logement</th>
                            <th>Prix</th>
                            <th>Commandes</th>
                            <th>Revenus</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($upsells as $u): ?>
                        <tr class="<?= $u['active'] ? '' : 'opacity-50' ?>">
                            <td><?= $u['sort_order'] ?></td>
                            <td>
                                <i class="fas <?= htmlspecialchars($u['icon'] ?? 'fa-gift') ?> text-primary me-1"></i>
                                <strong><?= htmlspecialchars($u['label']) ?></strong>
                                <div class="small text-muted"><?= htmlspecialchars($u['description'] ?? '') ?></div>
                            </td>
                            <td><?= $u['nom_du_logement'] ? htmlspecialchars($u['nom_du_logement']) : '<span class="text-muted">Tous</span>' ?></td>
                            <td><strong><?= number_format($u['price'], 2) ?> €</strong></td>
                            <td><span class="badge bg-info"><?= $u['nb_orders'] ?></span></td>
                            <td><?= number_format($u['revenue'], 2) ?> €</td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $u['active'] ? 'btn-success' : 'btn-secondary' ?>">
                                        <?= $u['active'] ? 'ON' : 'OFF' ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick='editUpsell(<?= json_encode($u) ?>)' data-bs-toggle="modal" data-bs-target="#editModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cet upsell ?')">
                                    <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                    <input type="hidden" name="action" value="delete_upsell">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($upsells)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Aucun upsell configure.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Stripe Config -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <h6><i class="fas fa-credit-card"></i> Configuration Stripe</h6>
            <?php if (env('STRIPE_SECRET_KEY', '')): ?>
                <span class="badge bg-success"><i class="fas fa-check"></i> Stripe configure</span>
            <?php else: ?>
                <span class="badge bg-warning"><i class="fas fa-exclamation-triangle"></i> Stripe non configure</span>
                <p class="small text-muted mt-1">Ajoutez <code>STRIPE_SECRET_KEY=sk_live_...</code> dans votre fichier .env pour activer les paiements.</p>
                <p class="small text-muted">Sans Stripe, les commandes sont enregistrees en statut "pending" et vous pouvez les gerer manuellement.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Edition -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                <input type="hidden" name="action" value="save_upsell">
                <input type="hidden" name="id" id="edit_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalTitle">Nouvel upsell</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nom technique</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required placeholder="early_checkin">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Label affiche</label>
                            <input type="text" name="label" id="edit_label" class="form-control" required placeholder="Early Check-in">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" id="edit_description" class="form-control" placeholder="Arrivez des 14h au lieu de 16h">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Prix (€)</label>
                            <input type="number" name="price" id="edit_price" class="form-control" required step="0.01" min="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Icone FA</label>
                            <input type="text" name="icon" id="edit_icon" class="form-control" value="fa-gift" placeholder="fa-clock">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ordre</label>
                            <input type="number" name="sort_order" id="edit_sort" class="form-control" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Lien de paiement Stripe</label>
                            <input type="url" name="stripe_link" id="edit_stripe_link" class="form-control" placeholder="https://buy.stripe.com/...">
                            <div class="form-text">Collez votre lien Stripe Payment Link. Le voyageur sera redirige vers ce lien pour payer. <a href="https://dashboard.stripe.com/payment-links" target="_blank">Creer un lien</a></div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Logement</label>
                            <select name="logement_id" id="edit_logement" class="form-select">
                                <option value="">Tous les logements</option>
                                <?php foreach ($logements as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nom_du_logement']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="active" id="edit_active" class="form-check-input" checked>
                                <label class="form-check-label" for="edit_active">Actif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Sauvegarder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUpsell(u) {
    if (u) {
        document.getElementById('editModalTitle').textContent = 'Modifier l\'upsell';
        document.getElementById('edit_id').value = u.id;
        document.getElementById('edit_name').value = u.name;
        document.getElementById('edit_label').value = u.label;
        document.getElementById('edit_description').value = u.description || '';
        document.getElementById('edit_price').value = u.price;
        document.getElementById('edit_icon').value = u.icon || 'fa-gift';
        document.getElementById('edit_stripe_link').value = u.stripe_link || '';
        document.getElementById('edit_sort').value = u.sort_order || 0;
        document.getElementById('edit_logement').value = u.logement_id || '';
        document.getElementById('edit_active').checked = !!u.active;
    } else {
        document.getElementById('editModalTitle').textContent = 'Nouvel upsell';
        document.getElementById('edit_id').value = 0;
        document.getElementById('edit_name').value = '';
        document.getElementById('edit_label').value = '';
        document.getElementById('edit_description').value = '';
        document.getElementById('edit_price').value = '';
        document.getElementById('edit_icon').value = 'fa-gift';
        document.getElementById('edit_stripe_link').value = '';
        document.getElementById('edit_sort').value = 0;
        document.getElementById('edit_logement').value = '';
        document.getElementById('edit_active').checked = true;
    }
}
</script>

