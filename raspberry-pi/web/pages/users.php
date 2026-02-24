<?php
/**
 * Gestion des utilisateurs
 * Page d'administration des comptes utilisateurs
 */

require_once __DIR__ . '/../includes/header_minimal.php';
require_once __DIR__ . '/../includes/db.php';

$message = '';
$error = '';
$current_user_id = getCurrentUserId();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $email = trim($_POST['email'] ?? '');
            $nom = trim($_POST['nom'] ?? '');
            $prenom = trim($_POST['prenom'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';

            if (empty($email) || empty($password)) {
                $error = "L'email et le mot de passe sont obligatoires.";
            } elseif (strlen($password) < 8) {
                $error = "Le mot de passe doit contenir au moins 8 caracteres.";
            } else {
                try {
                    // Verifier si l'email existe deja
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $error = "Un utilisateur avec cet email existe deja.";
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (email, password, nom, prenom, role, active) VALUES (?, ?, ?, ?, ?, 1)");
                        $stmt->execute([$email, $hashed_password, $nom, $prenom, $role]);
                        $message = "Utilisateur cree avec succes.";
                    }
                } catch (PDOException $e) {
                    $error = "Erreur lors de la creation : " . $e->getMessage();
                }
            }
            break;

        case 'update':
            $user_id = (int)($_POST['user_id'] ?? 0);
            $email = trim($_POST['email'] ?? '');
            $nom = trim($_POST['nom'] ?? '');
            $prenom = trim($_POST['prenom'] ?? '');
            $role = $_POST['role'] ?? 'user';
            $active = isset($_POST['active']) ? 1 : 0;

            if ($user_id === $current_user_id && !$active) {
                $error = "Vous ne pouvez pas desactiver votre propre compte.";
            } elseif (empty($email)) {
                $error = "L'email est obligatoire.";
            } else {
                try {
                    // Verifier si l'email existe deja pour un autre utilisateur
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $user_id]);
                    if ($stmt->fetch()) {
                        $error = "Un autre utilisateur utilise deja cet email.";
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET email = ?, nom = ?, prenom = ?, role = ?, active = ? WHERE id = ?");
                        $stmt->execute([$email, $nom, $prenom, $role, $active, $user_id]);
                        $message = "Utilisateur mis a jour avec succes.";
                    }
                } catch (PDOException $e) {
                    $error = "Erreur lors de la mise a jour : " . $e->getMessage();
                }
            }
            break;

        case 'reset_password':
            $user_id = (int)($_POST['user_id'] ?? 0);
            $new_password = $_POST['new_password'] ?? '';

            if (strlen($new_password) < 8) {
                $error = "Le mot de passe doit contenir au moins 8 caracteres.";
            } else {
                try {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    $message = "Mot de passe reinitialise avec succes.";
                } catch (PDOException $e) {
                    $error = "Erreur lors de la reinitialisation : " . $e->getMessage();
                }
            }
            break;

        case 'delete':
            $user_id = (int)($_POST['user_id'] ?? 0);

            if ($user_id === $current_user_id) {
                $error = "Vous ne pouvez pas supprimer votre propre compte.";
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $message = "Utilisateur supprime avec succes.";
                } catch (PDOException $e) {
                    $error = "Erreur lors de la suppression : " . $e->getMessage();
                }
            }
            break;
    }
}

// Recuperer tous les utilisateurs
try {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
    $error = "Erreur lors de la recuperation des utilisateurs.";
}

// Recuperer un utilisateur specifique pour edition
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($users as $u) {
        if ($u['id'] == $edit_id) {
            $edit_user = $u;
            break;
        }
    }
}
?>

<!-- Header de page -->
<div class="row mb-4">
    <div class="col-md-8">
        <h1 class="display-4">
            <i class="fas fa-users text-primary"></i> Gestion des utilisateurs
        </h1>
        <p class="lead text-muted">Gerez les comptes utilisateurs et leurs permissions</p>
    </div>
    <div class="col-md-4 text-right d-flex align-items-center justify-content-end">
        <button class="btn btn-primary" data-toggle="modal" data-target="#addUserModal">
            <i class="fas fa-plus"></i> Nouvel utilisateur
        </button>
    </div>
</div>

<div class="row">
    <div class="col-12">

            <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Liste des utilisateurs -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list"></i> Liste des utilisateurs (<?= count($users) ?>)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Utilisateur</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Statut</th>
                                    <th>Derniere connexion</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr class="<?= !$user['active'] ? 'table-secondary' : '' ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle mr-2" style="width: 35px; height: 35px; background: <?= $user['active'] ? '#10b981' : '#9ca3af' ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                                <?= strtoupper(substr($user['prenom'] ?? $user['email'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars(trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?: 'Sans nom') ?></strong>
                                                <?php if ($user['id'] == $current_user_id): ?>
                                                    <span class="badge badge-info ml-1">Vous</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <?php if (($user['role'] ?? 'user') === 'admin'): ?>
                                            <span class="badge badge-danger"><i class="fas fa-crown"></i> Admin</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Utilisateur</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['active']): ?>
                                            <span class="badge badge-success"><i class="fas fa-check"></i> Actif</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger"><i class="fas fa-times"></i> Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['last_login']): ?>
                                            <small class="text-muted">
                                                <?= date('d/m/Y H:i', strtotime($user['last_login'])) ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">Jamais</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?edit=<?= $user['id'] ?>" class="btn btn-outline-primary" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-warning" title="Reinitialiser mot de passe"
                                                    data-toggle="modal" data-target="#resetPasswordModal"
                                                    data-userid="<?= $user['id'] ?>"
                                                    data-username="<?= htmlspecialchars($user['email']) ?>">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <?php if ($user['id'] != $current_user_id): ?>
                                            <button type="button" class="btn btn-outline-danger" title="Supprimer"
                                                    data-toggle="modal" data-target="#deleteUserModal"
                                                    data-userid="<?= $user['id'] ?>"
                                                    data-username="<?= htmlspecialchars($user['email']) ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-users fa-2x mb-2"></i><br>
                                        Aucun utilisateur trouve.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulaire d'edition -->
        <div class="col-lg-4">
            <?php if ($edit_user): ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-edit"></i> Modifier l'utilisateur
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="user_id" value="<?= $edit_user['id'] ?>">

                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email *</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($edit_user['email']) ?>" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-6">
                                <label><i class="fas fa-user"></i> Prenom</label>
                                <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($edit_user['prenom'] ?? '') ?>">
                            </div>
                            <div class="form-group col-6">
                                <label>Nom</label>
                                <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($edit_user['nom'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-user-shield"></i> Role</label>
                            <select name="role" class="form-control">
                                <option value="user" <?= ($edit_user['role'] ?? 'user') === 'user' ? 'selected' : '' ?>>Utilisateur</option>
                                <option value="admin" <?= ($edit_user['role'] ?? 'user') === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="active" name="active"
                                       <?= $edit_user['active'] ? 'checked' : '' ?>
                                       <?= $edit_user['id'] == $current_user_id ? 'disabled' : '' ?>>
                                <label class="custom-control-label" for="active">Compte actif</label>
                            </div>
                            <?php if ($edit_user['id'] == $current_user_id): ?>
                            <small class="text-muted">Vous ne pouvez pas desactiver votre propre compte.</small>
                            <?php endif; ?>
                        </div>

                        <div class="form-group mb-0">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-save"></i> Enregistrer
                            </button>
                            <a href="users.php" class="btn btn-secondary btn-block">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-muted small">
                    <i class="fas fa-clock"></i> Cree le <?= date('d/m/Y H:i', strtotime($edit_user['created_at'])) ?>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-info-circle"></i> Informations
                </div>
                <div class="card-body">
                    <p class="text-muted mb-0">
                        Selectionnez un utilisateur dans la liste pour le modifier, ou cliquez sur "Nouvel utilisateur" pour en creer un.
                    </p>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <i class="fas fa-shield-alt"></i> Securite
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2"><i class="fas fa-check text-success"></i> Mots de passe chiffres (bcrypt)</li>
                        <li class="mb-2"><i class="fas fa-check text-success"></i> Session securisee</li>
                        <li class="mb-2"><i class="fas fa-check text-success"></i> Timeout apres 30min d'inactivite</li>
                        <li><i class="fas fa-check text-success"></i> Protection contre auto-suppression</li>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

<!-- Modal Ajouter utilisateur -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Nouvel utilisateur</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email *</label>
                        <input type="email" name="email" class="form-control" required placeholder="email@exemple.com">
                    </div>

                    <div class="form-row">
                        <div class="form-group col-6">
                            <label><i class="fas fa-user"></i> Prenom</label>
                            <input type="text" name="prenom" class="form-control" placeholder="Jean">
                        </div>
                        <div class="form-group col-6">
                            <label>Nom</label>
                            <input type="text" name="nom" class="form-control" placeholder="Dupont">
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Mot de passe *</label>
                        <div class="input-group">
                            <input type="password" name="password" id="newPassword" class="form-control" required
                                   minlength="8" placeholder="Minimum 8 caracteres">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('newPassword')">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="generatePassword()">
                                    <i class="fas fa-magic"></i>
                                </button>
                            </div>
                        </div>
                        <small class="text-muted">Cliquez sur <i class="fas fa-magic"></i> pour generer un mot de passe</small>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-user-shield"></i> Role</label>
                        <select name="role" class="form-control">
                            <option value="user">Utilisateur</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Creer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Reinitialiser mot de passe -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="resetUserId">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-key"></i> Reinitialiser le mot de passe</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Vous allez reinitialiser le mot de passe de : <strong id="resetUserName"></strong></p>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Nouveau mot de passe *</label>
                        <div class="input-group">
                            <input type="password" name="new_password" id="resetPassword" class="form-control" required
                                   minlength="8" placeholder="Minimum 8 caracteres">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('resetPassword')">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="generatePasswordFor('resetPassword')">
                                    <i class="fas fa-magic"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-key"></i> Reinitialiser</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Supprimer utilisateur -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="deleteUserId">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirmer la suppression</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Etes-vous sur de vouloir supprimer l'utilisateur : <strong id="deleteUserName"></strong> ?</p>
                    <p class="text-danger mb-0"><i class="fas fa-exclamation-triangle"></i> Cette action est irreversible.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialiser les modals
$('#resetPasswordModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget);
    $('#resetUserId').val(button.data('userid'));
    $('#resetUserName').text(button.data('username'));
    $('#resetPassword').val('');
});

$('#deleteUserModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget);
    $('#deleteUserId').val(button.data('userid'));
    $('#deleteUserName').text(button.data('username'));
});

// Toggle password visibility
function togglePassword(inputId) {
    var input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
}

// Generate random password
function generatePassword() {
    generatePasswordFor('newPassword');
}

function generatePasswordFor(inputId) {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%';
    var password = '';
    for (var i = 0; i < 12; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    var input = document.getElementById(inputId);
    input.value = password;
    input.type = 'text';
}
</script>

<?php require_once __DIR__ . '/../includes/footer_minimal.php'; ?>
