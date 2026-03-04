<?php
/**
 * Gestion des Pages — FrenchyConciergerie
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';

// Vérification admin
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../error.php?message=" . urlencode('Accès réservé aux administrateurs.'));
    exit;
}

$feedback = '';

// ============================================================
// ACTIONS POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    // --- Ajouter une page ---
    if (isset($_POST['add_page'])) {
        $nom    = trim($_POST['nom'] ?? '');
        $chemin = trim($_POST['chemin'] ?? '');
        $visible = isset($_POST['afficher_menu']) ? 1 : 0;

        if (empty($nom) || empty($chemin)) {
            $feedback = '<div class="alert alert-danger">Le nom et le chemin sont obligatoires.</div>';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO pages (nom, chemin, afficher_menu) VALUES (?, ?, ?)");
                $stmt->execute([$nom, $chemin, $visible]);
                $feedback = '<div class="alert alert-success alert-dismissible fade show">Page ajoutée.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            } catch (PDOException $e) {
                $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    // --- Modifier une page ---
    if (isset($_POST['save_page'])) {
        $page_id = (int) $_POST['page_id'];
        $nom     = trim($_POST['nom'] ?? '');
        $chemin  = trim($_POST['chemin'] ?? '');
        $visible = isset($_POST['afficher_menu']) ? 1 : 0;

        if (empty($nom) || empty($chemin)) {
            $feedback = '<div class="alert alert-danger">Le nom et le chemin sont obligatoires.</div>';
        } else {
            try {
                $stmt = $conn->prepare("UPDATE pages SET nom = ?, chemin = ?, afficher_menu = ? WHERE id = ?");
                $stmt->execute([$nom, $chemin, $visible, $page_id]);
                $feedback = '<div class="alert alert-success alert-dismissible fade show">Page mise à jour.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            } catch (PDOException $e) {
                $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    // --- Supprimer une page ---
    if (isset($_POST['delete_page'])) {
        $page_id = (int) $_POST['page_id'];
        try {
            // Vérifier si des intervenants utilisent cette page
            $count = $conn->prepare("SELECT COUNT(*) FROM intervenants_pages WHERE page_id = ?");
            $count->execute([$page_id]);
            $nb = $count->fetchColumn();

            if ($nb > 0) {
                $feedback = '<div class="alert alert-warning">Impossible de supprimer : ' . $nb . ' intervenant(s) ont accès à cette page.</div>';
            } else {
                $conn->prepare("DELETE FROM pages WHERE id = ?")->execute([$page_id]);
                $feedback = '<div class="alert alert-success alert-dismissible fade show">Page supprimée.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            }
        } catch (PDOException $e) {
            $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    // --- Toggle visibilité ---
    if (isset($_POST['toggle_visible'])) {
        $page_id = (int) $_POST['page_id'];
        try {
            $conn->prepare("UPDATE pages SET afficher_menu = NOT afficher_menu WHERE id = ?")->execute([$page_id]);
            $feedback = '<div class="alert alert-success alert-dismissible fade show">Visibilité modifiée.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (PDOException $e) {
            $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// ============================================================
// DONNÉES
// ============================================================
$pages = $conn->query("SELECT * FROM pages ORDER BY afficher_menu DESC, nom ASC")->fetchAll(PDO::FETCH_ASSOC);
$nb_visibles = count(array_filter($pages, fn($p) => !empty($p['afficher_menu'])));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Pages — FrenchyConciergerie</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .page-hidden { opacity: 0.55; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">

    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-file-alt text-secondary"></i> Gestion des Pages</h2>
            <p class="text-muted">
                <?= count($pages) ?> page(s) — <span class="text-success"><?= $nb_visibles ?> visible(s)</span> dans le menu
            </p>
        </div>
        <div class="col-auto">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#pageModal" onclick="resetPageModal()">
                <i class="fas fa-plus"></i> Nouvelle page
            </button>
        </div>
    </div>

    <?= $feedback ?>

    <!-- Tableau -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Chemin</th>
                            <th>Menu</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pages as $p): ?>
                        <tr class="<?= empty($p['afficher_menu']) ? 'page-hidden' : '' ?>">
                            <td><strong>#<?= $p['id'] ?></strong></td>
                            <td><?= htmlspecialchars($p['nom']) ?></td>
                            <td><code><?= htmlspecialchars($p['chemin']) ?></code></td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <?php echoCsrfField(); ?>
                                    <input type="hidden" name="page_id" value="<?= $p['id'] ?>">
                                    <?php if (!empty($p['afficher_menu'])): ?>
                                        <button type="submit" name="toggle_visible" class="btn btn-sm btn-success" title="Masquer du menu">
                                            <i class="fas fa-eye"></i> Visible
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="toggle_visible" class="btn btn-sm btn-secondary" title="Afficher dans le menu">
                                            <i class="fas fa-eye-slash"></i> Masqué
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                            <td class="text-nowrap">
                                <button type="button" class="btn btn-sm btn-warning"
                                        onclick="editPage(<?= htmlspecialchars(json_encode($p)) ?>)" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette page ?')">
                                    <?php echoCsrfField(); ?>
                                    <input type="hidden" name="page_id" value="<?= $p['id'] ?>">
                                    <button type="submit" name="delete_page" class="btn btn-sm btn-danger" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pages)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucune page enregistrée.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════ -->
<!-- MODAL : Ajouter / Modifier une page                     -->
<!-- ════════════════════════════════════════════════════════ -->
<div class="modal fade" id="pageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white" id="page-modal-header">
                <h5 class="modal-title" id="page-modal-title"><i class="fas fa-plus"></i> Nouvelle page</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echoCsrfField(); ?>
                <input type="hidden" name="page_id" id="p_id" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom *</label>
                        <input type="text" class="form-control" name="nom" id="p_nom" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Chemin *</label>
                        <input type="text" class="form-control" name="chemin" id="p_chemin" required placeholder="pages/ma_page.php">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="afficher_menu" id="p_visible" checked>
                        <label class="form-check-label" for="p_visible">Afficher dans le menu</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="add_page" class="btn btn-success" id="p_submit">
                        <i class="fas fa-plus"></i> Ajouter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetPageModal() {
    document.getElementById('p_id').value = '';
    document.getElementById('p_nom').value = '';
    document.getElementById('p_chemin').value = '';
    document.getElementById('p_visible').checked = true;
    document.getElementById('page-modal-title').innerHTML = '<i class="fas fa-plus"></i> Nouvelle page';
    document.getElementById('page-modal-header').className = 'modal-header bg-success text-white';
    var btn = document.getElementById('p_submit');
    btn.name = 'add_page';
    btn.innerHTML = '<i class="fas fa-plus"></i> Ajouter';
    btn.className = 'btn btn-success';
}

function editPage(p) {
    document.getElementById('p_id').value = p.id;
    document.getElementById('p_nom').value = p.nom || '';
    document.getElementById('p_chemin').value = p.chemin || '';
    document.getElementById('p_visible').checked = !!parseInt(p.afficher_menu);
    document.getElementById('page-modal-title').innerHTML = '<i class="fas fa-edit"></i> Modifier : ' + (p.nom || '');
    document.getElementById('page-modal-header').className = 'modal-header bg-warning text-dark';
    var btn = document.getElementById('p_submit');
    btn.name = 'save_page';
    btn.innerHTML = '<i class="fas fa-save"></i> Enregistrer';
    btn.className = 'btn btn-warning';
    new bootstrap.Modal(document.getElementById('pageModal')).show();
}
</script>
</body>
</html>
