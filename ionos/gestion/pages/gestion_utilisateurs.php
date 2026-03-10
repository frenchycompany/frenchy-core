<?php
/**
 * Gestion des Utilisateurs — FrenchyConciergerie
 * CRUD complet sur la table `users` + assignation des permissions par page.
 * Remplace la gestion des accès via intervenants.php.
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$auth = new Auth($conn);
$auth->requireAdmin('login.php');

$feedback = '';

// ============================================================
// ACTIONS POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    // --- Créer un utilisateur ---
    if (isset($_POST['create_user'])) {
        $email    = trim(strtolower($_POST['email'] ?? ''));
        $nom      = trim($_POST['nom'] ?? '');
        $prenom   = trim($_POST['prenom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $role_user = $_POST['role'] ?? 'staff';
        $password  = $_POST['password'] ?? '';
        $numero   = trim($_POST['numero'] ?? '');
        $role1    = trim($_POST['role1'] ?? '');
        $role2    = trim($_POST['role2'] ?? '');
        $role3    = trim($_POST['role3'] ?? '');
        $pages_sel = $_POST['pages_accessibles'] ?? [];

        if (empty($email) || empty($nom) || empty($password)) {
            $feedback = '<div class="alert alert-danger">Email, nom et mot de passe sont obligatoires.</div>';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $feedback = '<div class="alert alert-danger">Format d\'email invalide.</div>';
        } elseif (strlen($password) < 8) {
            $feedback = '<div class="alert alert-danger">Le mot de passe doit faire au moins 8 caractères.</div>';
        } else {
            try {
                // Vérifier unicité email
                $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $check->execute([$email]);
                if ($check->fetch()) {
                    $feedback = '<div class="alert alert-danger">Cet email est déjà utilisé.</div>';
                } else {
                    $newId = $auth->createUser([
                        'email' => $email,
                        'password' => $password,
                        'nom' => $nom,
                        'prenom' => $prenom,
                        'telephone' => $telephone ?: null,
                        'role' => $role_user,
                        'numero' => $numero ?: null,
                        'role1' => $role1 ?: null,
                        'role2' => $role2 ?: null,
                        'role3' => $role3 ?: null,
                    ]);

                    // Permissions pages
                    if (!empty($pages_sel) && $newId) {
                        $stmtPerm = $conn->prepare("INSERT INTO user_permissions (user_id, page_id) VALUES (?, ?)");
                        foreach ($pages_sel as $pid) {
                            $stmtPerm->execute([$newId, (int)$pid]);
                        }
                    }

                    $feedback = '<div class="alert alert-success alert-dismissible fade show">Utilisateur <strong>' . htmlspecialchars($nom) . '</strong> créé avec succès.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                }
            } catch (PDOException $e) {
                $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    // --- Modifier un utilisateur ---
    if (isset($_POST['update_user'])) {
        $uid      = (int)$_POST['user_id'];
        $email    = trim(strtolower($_POST['email'] ?? ''));
        $nom      = trim($_POST['nom'] ?? '');
        $prenom   = trim($_POST['prenom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $role_user = $_POST['role'] ?? 'staff';
        $password  = $_POST['password'] ?? '';
        $numero   = trim($_POST['numero'] ?? '');
        $role1    = trim($_POST['role1'] ?? '');
        $role2    = trim($_POST['role2'] ?? '');
        $role3    = trim($_POST['role3'] ?? '');
        $pages_sel = $_POST['pages_accessibles'] ?? [];

        if (empty($email) || empty($nom)) {
            $feedback = '<div class="alert alert-danger">Email et nom sont obligatoires.</div>';
        } else {
            try {
                // Vérifier unicité email (hors cet user)
                $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check->execute([$email, $uid]);
                if ($check->fetch()) {
                    $feedback = '<div class="alert alert-danger">Cet email est déjà utilisé par un autre compte.</div>';
                } else {
                    $data = [
                        'email' => $email,
                        'nom' => $nom,
                        'prenom' => $prenom,
                        'telephone' => $telephone ?: null,
                        'role' => $role_user,
                        'numero' => $numero ?: null,
                        'role1' => $role1 ?: null,
                        'role2' => $role2 ?: null,
                        'role3' => $role3 ?: null,
                    ];
                    if (!empty($password)) {
                        if (strlen($password) < 8) {
                            $feedback = '<div class="alert alert-danger">Le mot de passe doit faire au moins 8 caractères.</div>';
                        } else {
                            $data['password'] = $password;
                        }
                    }

                    if (empty($feedback)) {
                        $auth->updateUser($uid, $data);

                        // Permissions pages — remplacer
                        $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$uid]);
                        if (!empty($pages_sel)) {
                            $stmtPerm = $conn->prepare("INSERT INTO user_permissions (user_id, page_id) VALUES (?, ?)");
                            foreach ($pages_sel as $pid) {
                                $stmtPerm->execute([$uid, (int)$pid]);
                            }
                        }

                        $feedback = '<div class="alert alert-success alert-dismissible fade show">Utilisateur <strong>' . htmlspecialchars($nom) . '</strong> mis à jour.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    }
                }
            } catch (PDOException $e) {
                $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    // --- Toggle actif/inactif ---
    if (isset($_POST['toggle_actif'])) {
        $uid = (int)$_POST['user_id'];
        // Ne pas se désactiver soi-même
        if ($uid === (int)($_SESSION['user_id'] ?? 0)) {
            $feedback = '<div class="alert alert-warning">Vous ne pouvez pas vous désactiver vous-même.</div>';
        } else {
            try {
                $conn->prepare("UPDATE users SET actif = NOT actif WHERE id = ?")->execute([$uid]);
                $feedback = '<div class="alert alert-success alert-dismissible fade show">Statut modifié.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            } catch (PDOException $e) {
                $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    // --- Migrer un intervenant vers users ---
    if (isset($_POST['migrate_intervenant'])) {
        $intId = (int)$_POST['intervenant_id'];
        try {
            // Vérifier si déjà migré
            $check = $conn->prepare("SELECT id FROM users WHERE legacy_intervenant_id = ?");
            $check->execute([$intId]);
            if ($check->fetch()) {
                $feedback = '<div class="alert alert-info">Cet intervenant est déjà lié à un compte utilisateur.</div>';
            } else {
                $stmt = $conn->prepare("SELECT * FROM intervenant WHERE id = ?");
                $stmt->execute([$intId]);
                $inter = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$inter) {
                    $feedback = '<div class="alert alert-danger">Intervenant introuvable.</div>';
                } else {
                    $email = $inter['nom_utilisateur'] ?? '';
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $email = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $inter['nom'] ?? 'user')) . '@frenchyconciergerie.local';
                    }

                    // Créer le user
                    $hash = password_hash('Changez-moi-2024!', PASSWORD_ARGON2ID, [
                        'memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3
                    ]);
                    $stmtU = $conn->prepare(
                        "INSERT INTO users (email, password_hash, nom, role, numero, role1, role2, role3, legacy_intervenant_id, actif)
                         VALUES (?, ?, ?, 'staff', ?, ?, ?, ?, ?, ?)"
                    );
                    $stmtU->execute([
                        trim(strtolower($email)), $hash, $inter['nom'] ?? 'Sans nom',
                        $inter['numero'] ?? null, $inter['role1'] ?? null,
                        $inter['role2'] ?? null, $inter['role3'] ?? null,
                        $intId, $inter['actif'] ?? 1
                    ]);
                    $newUserId = (int)$conn->lastInsertId();

                    // Migrer les permissions de intervenants_pages vers user_permissions
                    $stmtPages = $conn->prepare("SELECT page_id FROM intervenants_pages WHERE intervenant_id = ?");
                    $stmtPages->execute([$intId]);
                    $oldPages = $stmtPages->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($oldPages)) {
                        $stmtPerm = $conn->prepare("INSERT IGNORE INTO user_permissions (user_id, page_id) VALUES (?, ?)");
                        foreach ($oldPages as $pid) {
                            $stmtPerm->execute([$newUserId, (int)$pid]);
                        }
                    }

                    $feedback = '<div class="alert alert-success alert-dismissible fade show">'
                        . 'Intervenant <strong>' . htmlspecialchars($inter['nom']) . '</strong> migré vers users.'
                        . ' Email : <code>' . htmlspecialchars($email) . '</code>'
                        . ' — Mot de passe temporaire : <code>Changez-moi-2024!</code>'
                        . '<br><small>Pensez à modifier l\'email et le mot de passe.</small>'
                        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                }
            }
        } catch (PDOException $e) {
            $feedback = '<div class="alert alert-danger">Erreur migration : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// ============================================================
// DONNÉES
// ============================================================

// Tous les users
$users = $conn->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM user_permissions up WHERE up.user_id = u.id) AS nb_pages
    FROM users u
    ORDER BY u.actif DESC, u.role ASC, u.nom ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Toutes les pages visibles
$allPages = $conn->query("SELECT id, nom, chemin FROM pages WHERE afficher_menu = 1 ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);

// Map des catégories par chemin
require_once __DIR__ . '/menu_categories.php';
$chemin_to_category = [];
foreach ($menu_categories as $cat_name => $cat) {
    foreach ($cat['items'] as $item) {
        $chemin_to_category[$item['chemin']] = $cat_name;
    }
}

// Intervenants pas encore migrés
$non_migres = [];
try {
    $non_migres = $conn->query("
        SELECT i.*
        FROM intervenant i
        LEFT JOIN users u ON u.legacy_intervenant_id = i.id
        WHERE u.id IS NULL
        ORDER BY i.nom ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* table intervenant n'existe pas encore */ }

// Permissions existantes par user (pour pré-cocher)
$permsByUser = [];
try {
    $rows = $conn->query("SELECT user_id, page_id FROM user_permissions")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $permsByUser[(int)$r['user_id']][] = (int)$r['page_id'];
    }
} catch (PDOException $e) {}

$roles_labels = [
    'super_admin' => 'Super Admin',
    'admin' => 'Admin',
    'staff' => 'Staff',
    'proprietaire_full' => 'Proprio. Conciergerie',
    'proprietaire_opti' => 'Proprio. Optimisation',
];

$roles_colors = [
    'super_admin' => 'danger',
    'admin' => 'warning',
    'staff' => 'primary',
    'proprietaire_full' => 'success',
    'proprietaire_opti' => 'info',
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs — FrenchyConciergerie</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .user-inactive { opacity: 0.5; }
        .perm-grid { max-height: 350px; overflow-y: auto; }
        .perm-grid .form-check { padding: 4px 0 4px 1.8rem; }
        .perm-cat-title { font-weight: 700; font-size: 0.85em; color: #1976d2; margin-top: 8px; margin-bottom: 2px; }
        .migrate-card { border-left: 4px solid #ff9800; background: #fff8e1; }
        .stat-pills .badge { font-size: 0.85em; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2><i class="fas fa-users-cog text-primary"></i> Gestion des Utilisateurs</h2>
            <p class="text-muted mb-0">
                <?= count($users) ?> utilisateur(s)
                — <span class="text-success"><?= count(array_filter($users, fn($u) => $u['actif'])) ?> actif(s)</span>
            </p>
        </div>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetUserModal()">
            <i class="fas fa-user-plus"></i> Nouvel utilisateur
        </button>
    </div>

    <?= $feedback ?>

    <!-- ════════════════ MIGRATION INTERVENANTS ════════════════ -->
    <?php if (!empty($non_migres)): ?>
    <div class="card mb-4 migrate-card">
        <div class="card-header bg-warning text-dark">
            <i class="fas fa-exchange-alt"></i> <?= count($non_migres) ?> intervenant(s) non migrés vers le nouveau système
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Identifiant</th>
                            <th>Roles métier</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($non_migres as $inter): ?>
                        <tr>
                            <td>#<?= $inter['id'] ?></td>
                            <td><?= htmlspecialchars($inter['nom'] ?? '') ?></td>
                            <td><code><?= htmlspecialchars($inter['nom_utilisateur'] ?? '-') ?></code></td>
                            <td>
                                <?php foreach (['role1','role2','role3'] as $rk):
                                    if (!empty($inter[$rk])): ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($inter[$rk]) ?></span>
                                <?php endif; endforeach; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Migrer cet intervenant vers la table users ?')">
                                    <?php echoCsrfField(); ?>
                                    <input type="hidden" name="intervenant_id" value="<?= $inter['id'] ?>">
                                    <button type="submit" name="migrate_intervenant" class="btn btn-sm btn-warning">
                                        <i class="fas fa-arrow-right"></i> Migrer
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ════════════════ TABLEAU UTILISATEURS ════════════════ -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Rôles métier</th>
                            <th>Pages</th>
                            <th>Dernière co.</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u):
                        $roleColor = $roles_colors[$u['role']] ?? 'secondary';
                        $roleLabel = $roles_labels[$u['role']] ?? $u['role'];
                        $perms = $permsByUser[(int)$u['id']] ?? [];
                    ?>
                        <tr class="<?= !$u['actif'] ? 'user-inactive' : '' ?>">
                            <td><strong>#<?= $u['id'] ?></strong></td>
                            <td>
                                <?= htmlspecialchars($u['nom']) ?>
                                <?php if ($u['prenom']): ?>
                                    <small class="text-muted"><?= htmlspecialchars($u['prenom']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><code><?= htmlspecialchars($u['email']) ?></code></td>
                            <td><span class="badge bg-<?= $roleColor ?>"><?= $roleLabel ?></span></td>
                            <td>
                                <?php foreach (['role1','role2','role3'] as $rk):
                                    if (!empty($u[$rk])): ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($u[$rk]) ?></span>
                                <?php endif; endforeach; ?>
                            </td>
                            <td>
                                <?php if (in_array($u['role'], ['admin', 'super_admin'])): ?>
                                    <span class="badge bg-success"><i class="fas fa-infinity"></i> Toutes</span>
                                <?php else: ?>
                                    <span class="badge bg-<?= $u['nb_pages'] > 0 ? 'info' : 'light text-muted' ?>">
                                        <?= (int)$u['nb_pages'] ?> page<?= $u['nb_pages'] > 1 ? 's' : '' ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['derniere_connexion']): ?>
                                    <small><?= date('d/m/Y H:i', strtotime($u['derniere_connexion'])) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">Jamais</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <?php echoCsrfField(); ?>
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <?php if ($u['actif']): ?>
                                        <button type="submit" name="toggle_actif" class="btn btn-sm btn-success" title="Désactiver">
                                            <i class="fas fa-check-circle"></i> Actif
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="toggle_actif" class="btn btn-sm btn-secondary" title="Activer">
                                            <i class="fas fa-ban"></i> Inactif
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning"
                                        onclick='editUser(<?= json_encode([
                                            "id" => $u["id"],
                                            "email" => $u["email"],
                                            "nom" => $u["nom"],
                                            "prenom" => $u["prenom"] ?? "",
                                            "telephone" => $u["telephone"] ?? "",
                                            "role" => $u["role"],
                                            "numero" => $u["numero"] ?? "",
                                            "role1" => $u["role1"] ?? "",
                                            "role2" => $u["role2"] ?? "",
                                            "role3" => $u["role3"] ?? "",
                                            "perms" => $perms,
                                        ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">Aucun utilisateur.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════ -->
<!-- MODAL : Créer / Modifier un utilisateur                  -->
<!-- ════════════════════════════════════════════════════════ -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white" id="u-modal-header">
                <h5 class="modal-title" id="u-modal-title"><i class="fas fa-user-plus"></i> Nouvel utilisateur</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="userForm">
                <?php echoCsrfField(); ?>
                <input type="hidden" name="user_id" id="u_id" value="">
                <div class="modal-body">
                    <div class="row">
                        <!-- Colonne gauche : infos -->
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3"><i class="fas fa-id-card"></i> Informations</h6>
                            <div class="mb-2">
                                <label class="form-label">Nom *</label>
                                <input type="text" class="form-control" name="nom" id="u_nom" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Prénom</label>
                                <input type="text" class="form-control" name="prenom" id="u_prenom">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" id="u_email" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" name="telephone" id="u_telephone">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Mot de passe <span id="u_pwd_hint">(obligatoire)</span></label>
                                <input type="password" class="form-control" name="password" id="u_password" minlength="8">
                            </div>
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <label class="form-label">Rôle système *</label>
                                    <select class="form-select" name="role" id="u_role">
                                        <?php foreach ($roles_labels as $rv => $rl): ?>
                                            <option value="<?= $rv ?>"><?= $rl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="form-label">N° interne</label>
                                    <input type="text" class="form-control" name="numero" id="u_numero">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-4 mb-2">
                                    <label class="form-label">Rôle métier 1</label>
                                    <input type="text" class="form-control form-control-sm" name="role1" id="u_role1" placeholder="Conducteur...">
                                </div>
                                <div class="col-4 mb-2">
                                    <label class="form-label">Rôle métier 2</label>
                                    <input type="text" class="form-control form-control-sm" name="role2" id="u_role2">
                                </div>
                                <div class="col-4 mb-2">
                                    <label class="form-label">Rôle métier 3</label>
                                    <input type="text" class="form-control form-control-sm" name="role3" id="u_role3">
                                </div>
                            </div>
                        </div>

                        <!-- Colonne droite : permissions pages -->
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-primary mb-0"><i class="fas fa-key"></i> Pages accessibles</h6>
                                <div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="toggleAllPages(true)">Tout</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllPages(false)">Aucun</button>
                                </div>
                            </div>
                            <div id="u_perms_note" class="alert alert-info py-1 px-2 mb-2" style="font-size:0.85em; display:none;">
                                <i class="fas fa-info-circle"></i> Les admins ont accès à toutes les pages automatiquement.
                            </div>
                            <div class="perm-grid border rounded p-2" id="u_perm_container">
                                <?php
                                // Grouper les pages par catégorie
                                $pages_by_cat = ['Autres' => []];
                                foreach ($allPages as $pg) {
                                    $cat = $chemin_to_category[$pg['chemin']] ?? 'Autres';
                                    $pages_by_cat[$cat][] = $pg;
                                }
                                foreach ($pages_by_cat as $cat_name => $cat_pages):
                                    if (empty($cat_pages)) continue;
                                ?>
                                    <div class="perm-cat-title"><?= htmlspecialchars($cat_name) ?></div>
                                    <?php foreach ($cat_pages as $pg): ?>
                                        <div class="form-check">
                                            <input class="form-check-input perm-check" type="checkbox"
                                                   name="pages_accessibles[]" value="<?= $pg['id'] ?>"
                                                   id="perm_<?= $pg['id'] ?>">
                                            <label class="form-check-label" for="perm_<?= $pg['id'] ?>">
                                                <?= htmlspecialchars($pg['nom']) ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="create_user" class="btn btn-success" id="u_submit">
                        <i class="fas fa-user-plus"></i> Créer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetUserModal() {
    document.getElementById('u_id').value = '';
    document.getElementById('u_nom').value = '';
    document.getElementById('u_prenom').value = '';
    document.getElementById('u_email').value = '';
    document.getElementById('u_telephone').value = '';
    document.getElementById('u_password').value = '';
    document.getElementById('u_password').required = true;
    document.getElementById('u_pwd_hint').textContent = '(obligatoire)';
    document.getElementById('u_role').value = 'staff';
    document.getElementById('u_numero').value = '';
    document.getElementById('u_role1').value = '';
    document.getElementById('u_role2').value = '';
    document.getElementById('u_role3').value = '';

    document.getElementById('u-modal-title').innerHTML = '<i class="fas fa-user-plus"></i> Nouvel utilisateur';
    document.getElementById('u-modal-header').className = 'modal-header bg-success text-white';
    var btn = document.getElementById('u_submit');
    btn.name = 'create_user';
    btn.innerHTML = '<i class="fas fa-user-plus"></i> Créer';
    btn.className = 'btn btn-success';

    toggleAllPages(false);
    updatePermsVisibility();
}

function editUser(u) {
    document.getElementById('u_id').value = u.id;
    document.getElementById('u_nom').value = u.nom || '';
    document.getElementById('u_prenom').value = u.prenom || '';
    document.getElementById('u_email').value = u.email || '';
    document.getElementById('u_telephone').value = u.telephone || '';
    document.getElementById('u_password').value = '';
    document.getElementById('u_password').required = false;
    document.getElementById('u_pwd_hint').textContent = '(laisser vide pour ne pas changer)';
    document.getElementById('u_role').value = u.role || 'staff';
    document.getElementById('u_numero').value = u.numero || '';
    document.getElementById('u_role1').value = u.role1 || '';
    document.getElementById('u_role2').value = u.role2 || '';
    document.getElementById('u_role3').value = u.role3 || '';

    document.getElementById('u-modal-title').innerHTML = '<i class="fas fa-edit"></i> Modifier : ' + (u.nom || '');
    document.getElementById('u-modal-header').className = 'modal-header bg-warning text-dark';
    var btn = document.getElementById('u_submit');
    btn.name = 'update_user';
    btn.innerHTML = '<i class="fas fa-save"></i> Enregistrer';
    btn.className = 'btn btn-warning';

    // Cocher les pages
    toggleAllPages(false);
    if (u.perms && u.perms.length) {
        u.perms.forEach(function(pid) {
            var cb = document.getElementById('perm_' + pid);
            if (cb) cb.checked = true;
        });
    }

    updatePermsVisibility();
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

function toggleAllPages(checked) {
    document.querySelectorAll('.perm-check').forEach(function(cb) {
        cb.checked = checked;
    });
}

function updatePermsVisibility() {
    var role = document.getElementById('u_role').value;
    var isAdmin = (role === 'admin' || role === 'super_admin');
    document.getElementById('u_perms_note').style.display = isAdmin ? 'block' : 'none';
    document.getElementById('u_perm_container').style.opacity = isAdmin ? '0.4' : '1';
}

document.getElementById('u_role').addEventListener('change', updatePermsVisibility);
</script>
</body>
</html>
