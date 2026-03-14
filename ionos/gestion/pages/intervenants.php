<?php
/**
 * Gestion des Intervenants — FrenchyConciergerie
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';

// Vérification admin
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../error.php?message=" . urlencode('Accès réservé aux administrateurs.'));
    exit;
}

// Auto-migration : colonne actif
try {
    $cols = array_column($conn->query("SHOW COLUMNS FROM intervenant")->fetchAll(), 'Field');
    if (!in_array('actif', $cols)) {
        $conn->exec("ALTER TABLE intervenant ADD COLUMN actif TINYINT(1) NOT NULL DEFAULT 1");
    }
} catch (PDOException $e) { /* table n'existe pas encore */ }

// Auto-sync : ajouter dans la table `pages` toutes les pages du menu si absentes
if (isset($menu_categories)) {
    try {
        $existingPages = $conn->query("SELECT chemin FROM pages")->fetchAll(PDO::FETCH_COLUMN);
        $existingBasenames = array_map('basename', $existingPages);
        $stmtInsert = $conn->prepare("INSERT INTO pages (nom, chemin, afficher_menu) VALUES (?, ?, 1)");

        foreach ($menu_categories as $catItems) {
            foreach ($catItems['items'] as $item) {
                if (!in_array(basename($item['chemin']), $existingBasenames)) {
                    $stmtInsert->execute([$item['nom'], $item['chemin']]);
                    $existingBasenames[] = basename($item['chemin']);
                }
            }
        }
    } catch (PDOException $e) { /* table pages n'existe pas encore */ }
}

$feedback = '';

// ============================================================
// ACTIONS POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    // --- Toggle actif/inactif ---
    if (isset($_POST['toggle_actif'])) {
        $tid = (int) $_POST['toggle_actif'];
        try {
            $conn->prepare("UPDATE intervenant SET actif = NOT actif WHERE id = ?")->execute([$tid]);
            $feedback = '<div class="alert alert-success alert-dismissible fade show">Statut mis à jour.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (PDOException $e) {
            $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    // --- Créer ou modifier un intervenant ---
    if (isset($_POST['save_intervenant'])) {
        $intervenant_id = (int) ($_POST['intervenant_id'] ?? 0);
        $nom      = trim($_POST['nom'] ?? '');
        $numero   = trim($_POST['numero'] ?? '');
        $role1    = trim($_POST['role1'] ?? '');
        $role2    = trim($_POST['role2'] ?? '');
        $role3    = trim($_POST['role3'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $pages_accessibles = $_POST['pages_accessibles'] ?? [];

        if (empty($nom)) {
            $feedback = '<div class="alert alert-danger">Le nom est obligatoire.</div>';
        } else {
            try {
                if ($intervenant_id > 0) {
                    // Mise à jour
                    if (!empty($password)) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE intervenant SET nom = ?, numero = ?, role1 = ?, role2 = ?, role3 = ?, nom_utilisateur = ?, mot_de_passe = ? WHERE id = ?");
                        $stmt->execute([$nom, $numero, $role1, $role2, $role3, $username, $hash, $intervenant_id]);
                    } else {
                        $stmt = $conn->prepare("UPDATE intervenant SET nom = ?, numero = ?, role1 = ?, role2 = ?, role3 = ?, nom_utilisateur = ? WHERE id = ?");
                        $stmt->execute([$nom, $numero, $role1, $role2, $role3, $username, $intervenant_id]);
                    }

                    // Sync vers la table users (système d'auth unifié)
                    $stmtUser = $conn->prepare("SELECT id FROM users WHERE legacy_intervenant_id = ?");
                    $stmtUser->execute([$intervenant_id]);
                    $linkedUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
                    if ($linkedUser) {
                        $userFields = "nom = ?, numero = ?, role1 = ?, role2 = ?, role3 = ?";
                        $userValues = [$nom, $numero, $role1, $role2, $role3];
                        if (!empty($password)) {
                            $userFields .= ", password_hash = ?";
                            $userValues[] = password_hash($password, PASSWORD_ARGON2ID, [
                                'memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3
                            ]);
                        }
                        if (!empty($username) && filter_var($username, FILTER_VALIDATE_EMAIL)) {
                            $userFields .= ", email = ?";
                            $userValues[] = trim(strtolower($username));
                        }
                        $userValues[] = $linkedUser['id'];
                        $conn->prepare("UPDATE users SET $userFields WHERE id = ?")->execute($userValues);
                    }

                    $feedback = '<div class="alert alert-success alert-dismissible fade show">Intervenant mis à jour.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                } else {
                    // Création
                    if (empty($password)) {
                        $feedback = '<div class="alert alert-danger">Le mot de passe est obligatoire pour un nouvel intervenant.</div>';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("INSERT INTO intervenant (nom, numero, role1, role2, role3, nom_utilisateur, mot_de_passe) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$nom, $numero, $role1, $role2, $role3, $username, $hash]);
                        $intervenant_id = $conn->lastInsertId();

                        // Créer aussi dans la table users si un email est fourni
                        if (!empty($username) && filter_var($username, FILTER_VALIDATE_EMAIL)) {
                            $userHash = password_hash($password, PASSWORD_ARGON2ID, [
                                'memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3
                            ]);
                            $stmtUser = $conn->prepare(
                                "INSERT INTO users (email, password_hash, nom, role, numero, role1, role2, role3, legacy_intervenant_id, actif)
                                 VALUES (?, ?, ?, 'staff', ?, ?, ?, ?, ?, 1)"
                            );
                            $stmtUser->execute([
                                trim(strtolower($username)), $userHash, $nom,
                                $numero, $role1, $role2, $role3, $intervenant_id
                            ]);
                        }

                        $feedback = '<div class="alert alert-success alert-dismissible fade show">Intervenant créé.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    }
                }

                // Pages accessibles
                if ($intervenant_id > 0) {
                    $conn->prepare("DELETE FROM intervenants_pages WHERE intervenant_id = ?")->execute([$intervenant_id]);
                    if (!empty($pages_accessibles)) {
                        $stmt = $conn->prepare("INSERT INTO intervenants_pages (intervenant_id, page_id) VALUES (?, ?)");
                        foreach ($pages_accessibles as $page_id) {
                            $stmt->execute([$intervenant_id, (int) $page_id]);
                        }
                    }
                }
            } catch (PDOException $e) {
                $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }
}

// ============================================================
// DONNÉES
// ============================================================
$intervenants = $conn->query("SELECT * FROM intervenant ORDER BY actif DESC, nom ASC")->fetchAll(PDO::FETCH_ASSOC);
$roles_disponibles = ['Conducteur', 'Femme de ménage', 'Laverie', 'Maintenance', 'Superviseur'];
$pages_disponibles = $conn->query("SELECT id, nom, chemin FROM pages ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// Pré-charger les pages accessibles par intervenant
$pages_par_intervenant = [];
try {
    $rows = $conn->query("SELECT intervenant_id, page_id FROM intervenants_pages")->fetchAll();
    foreach ($rows as $r) {
        $pages_par_intervenant[$r['intervenant_id']][] = $r['page_id'];
    }
} catch (PDOException $e) { /* table n'existe pas encore */ }

$nb_actifs = count(array_filter($intervenants, fn($i) => !empty($i['actif'])));
$nb_inactifs = count($intervenants) - $nb_actifs;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Intervenants — FrenchyConciergerie</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .intervenant-inactif { opacity: 0.55; }
        .role-badge { font-size: 0.8rem; }
        .pages-list { font-size: 0.8rem; max-height: 80px; overflow-y: auto; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">

    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-users text-info"></i> Gestion des Intervenants</h2>
            <p class="text-muted">
                <?= count($intervenants) ?> intervenant(s) —
                <span class="text-success"><?= $nb_actifs ?> actif(s)</span>
                <?php if ($nb_inactifs > 0): ?>
                    / <span class="text-secondary"><?= $nb_inactifs ?> inactif(s)</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="col-auto">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#intervenantModal" onclick="resetModal()">
                <i class="fas fa-plus"></i> Nouvel intervenant
            </button>
        </div>
    </div>

    <?= $feedback ?>

    <!-- Tableau des intervenants -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nom</th>
                            <th>Téléphone</th>
                            <th>Identifiant</th>
                            <th>Rôles</th>
                            <th>Pages</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($intervenants as $i): ?>
                        <?php $iPages = $pages_par_intervenant[$i['id']] ?? []; ?>
                        <tr class="<?= empty($i['actif']) ? 'intervenant-inactif' : '' ?>">
                            <td><strong><?= htmlspecialchars($i['nom']) ?></strong></td>
                            <td><?= htmlspecialchars($i['numero']) ?></td>
                            <td><code><?= htmlspecialchars($i['nom_utilisateur']) ?></code></td>
                            <td>
                                <?php foreach (['role1', 'role2', 'role3'] as $rk): ?>
                                    <?php if (!empty($i[$rk])): ?>
                                        <span class="badge bg-primary role-badge"><?= htmlspecialchars($i[$rk]) ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (empty($i['role1']) && empty($i['role2']) && empty($i['role3'])): ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="pages-list">
                                    <?php
                                    $count = count($iPages);
                                    if ($count > 0) {
                                        echo '<span class="badge bg-info">' . $count . ' page' . ($count > 1 ? 's' : '') . '</span>';
                                    } else {
                                        echo '<span class="text-muted">Aucune</span>';
                                    }
                                    ?>
                                </div>
                            </td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <?php echoCsrfField(); ?>
                                    <input type="hidden" name="toggle_actif" value="<?= $i['id'] ?>">
                                    <?php if (!empty($i['actif'])): ?>
                                        <button type="submit" class="btn btn-sm btn-success" title="Cliquer pour désactiver">
                                            <i class="fas fa-check-circle"></i> Actif
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-sm btn-secondary" title="Cliquer pour activer">
                                            <i class="fas fa-pause-circle"></i> Inactif
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning"
                                        onclick="editIntervenant(<?= htmlspecialchars(json_encode(array_merge($i, ['pages' => $iPages]))) ?>)"
                                        title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($intervenants)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucun intervenant enregistré.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════ -->
<!-- MODAL : Créer / Modifier un intervenant                 -->
<!-- ════════════════════════════════════════════════════════ -->
<div class="modal fade" id="intervenantModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white" id="modal-header">
                <h5 class="modal-title" id="modal-title"><i class="fas fa-plus"></i> Nouvel intervenant</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echoCsrfField(); ?>
                <input type="hidden" name="intervenant_id" id="m_id" value="0">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Identité</h6>
                            <div class="mb-3">
                                <label class="form-label">Nom *</label>
                                <input type="text" class="form-control" name="nom" id="m_nom" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" name="numero" id="m_numero">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nom d'utilisateur *</label>
                                <input type="text" class="form-control" name="username" id="m_username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" id="m_password_label">Mot de passe *</label>
                                <input type="password" class="form-control" name="password" id="m_password">
                                <small class="form-text text-muted" id="m_password_hint" style="display:none">Laisser vide = inchangé</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Rôles</h6>
                            <?php for ($r = 1; $r <= 3; $r++): ?>
                            <div class="mb-3">
                                <label class="form-label">Rôle <?= $r ?></label>
                                <select class="form-select" name="role<?= $r ?>" id="m_role<?= $r ?>">
                                    <option value="">— Aucun —</option>
                                    <?php foreach ($roles_disponibles as $role): ?>
                                        <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endfor; ?>

                            <h6 class="text-muted mb-3 mt-3">Pages accessibles</h6>
                            <div style="max-height:200px; overflow-y:auto; border:1px solid #dee2e6; border-radius:6px; padding:0.75rem;">
                                <?php foreach ($pages_disponibles as $p): ?>
                                <div class="form-check">
                                    <input class="form-check-input page-check" type="checkbox" name="pages_accessibles[]"
                                           value="<?= $p['id'] ?>" id="page_<?= $p['id'] ?>">
                                    <label class="form-check-label" for="page_<?= $p['id'] ?>">
                                        <?= htmlspecialchars($p['nom'] ?: basename($p['chemin'])) ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="save_intervenant" class="btn btn-primary" id="m_submit">
                        <i class="fas fa-save"></i> Créer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetModal() {
    document.getElementById('m_id').value = '0';
    document.getElementById('m_nom').value = '';
    document.getElementById('m_numero').value = '';
    document.getElementById('m_username').value = '';
    document.getElementById('m_password').value = '';
    document.getElementById('m_password').required = true;
    document.getElementById('m_password_label').textContent = 'Mot de passe *';
    document.getElementById('m_password_hint').style.display = 'none';
    document.getElementById('m_role1').value = '';
    document.getElementById('m_role2').value = '';
    document.getElementById('m_role3').value = '';
    document.querySelectorAll('.page-check').forEach(c => c.checked = false);
    document.getElementById('modal-title').innerHTML = '<i class="fas fa-plus"></i> Nouvel intervenant';
    document.getElementById('modal-header').className = 'modal-header bg-success text-white';
    document.getElementById('m_submit').innerHTML = '<i class="fas fa-plus"></i> Créer';
    document.getElementById('m_submit').className = 'btn btn-success';
}

function editIntervenant(i) {
    document.getElementById('m_id').value = i.id;
    document.getElementById('m_nom').value = i.nom || '';
    document.getElementById('m_numero').value = i.numero || '';
    document.getElementById('m_username').value = i.nom_utilisateur || '';
    document.getElementById('m_password').value = '';
    document.getElementById('m_password').required = false;
    document.getElementById('m_password_label').textContent = 'Mot de passe';
    document.getElementById('m_password_hint').style.display = 'block';
    document.getElementById('m_role1').value = i.role1 || '';
    document.getElementById('m_role2').value = i.role2 || '';
    document.getElementById('m_role3').value = i.role3 || '';

    document.querySelectorAll('.page-check').forEach(c => {
        c.checked = (i.pages || []).includes(parseInt(c.value));
    });

    document.getElementById('modal-title').innerHTML = '<i class="fas fa-edit"></i> Modifier : ' + (i.nom || '');
    document.getElementById('modal-header').className = 'modal-header bg-warning text-dark';
    document.getElementById('m_submit').innerHTML = '<i class="fas fa-save"></i> Enregistrer';
    document.getElementById('m_submit').className = 'btn btn-warning';

    new bootstrap.Modal(document.getElementById('intervenantModal')).show();
}
</script>
</body>
</html>
