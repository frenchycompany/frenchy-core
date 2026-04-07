<?php
/** Utilisateurs FC — Page FC Admin */
try {
    $fcUsers = $conn->query("SELECT * FROM FC_users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $fcUsers = []; }
?>
<div class="row g-3">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white"><h6 class="mb-0"><i class="fas fa-user-plus"></i> Ajouter un utilisateur</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?= fcCsrfField() ?>
                    <div class="mb-2"><label class="form-label">Nom d'utilisateur *</label><input type="text" name="new_username" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Email *</label><input type="email" name="new_email" class="form-control" required></div>
                    <div class="row mb-2">
                        <div class="col"><label class="form-label">Prenom</label><input type="text" name="new_prenom" class="form-control"></div>
                        <div class="col"><label class="form-label">Nom</label><input type="text" name="new_nom" class="form-control"></div>
                    </div>
                    <div class="mb-2"><label class="form-label">Mot de passe *</label><input type="password" name="new_password" class="form-control" minlength="8" required></div>
                    <div class="mb-2">
                        <label class="form-label">Role</label>
                        <select name="new_role" class="form-select">
                            <option value="viewer">Lecture seule</option>
                            <option value="editor">Editeur</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                    <button type="submit" name="add_fc_user" class="btn btn-success w-100">Creer l'utilisateur</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white"><h6 class="mb-0"><i class="fas fa-users"></i> Utilisateurs (<?= count($fcUsers) ?>)</h6></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Utilisateur</th><th>Email</th><th>Role</th><th>Statut</th><th>Derniere connexion</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($fcUsers as $u): ?>
                        <tr>
                            <td>
                                <strong><?= e($u['username']) ?></strong>
                                <?php if ($u['prenom'] || $u['nom']): ?><br><small class="text-muted"><?= e(trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''))) ?></small><?php endif; ?>
                            </td>
                            <td><small><?= e($u['email']) ?></small></td>
                            <td>
                                <?php
                                $roleColors = ['admin' => 'danger', 'editor' => 'warning text-dark', 'viewer' => 'secondary'];
                                $roleLabels = ['admin' => 'Admin', 'editor' => 'Editeur', 'viewer' => 'Lecture'];
                                $r = $u['role'] ?? 'viewer';
                                ?>
                                <span class="badge bg-<?= $roleColors[$r] ?? 'secondary' ?>"><?= $roleLabels[$r] ?? $r ?></span>
                            </td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <?= fcCsrfField() ?>
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" name="toggle_fc_user" class="btn btn-sm <?= $u['actif'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                                        <?= $u['actif'] ? 'Actif' : 'Inactif' ?>
                                    </button>
                                </form>
                            </td>
                            <td><small><?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : 'Jamais' ?></small></td>
                            <td>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cet utilisateur ?')">
                                    <?= fcCsrfField() ?>
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" name="delete_fc_user" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($fcUsers)): ?><tr><td colspan="6" class="text-center text-muted">Aucun utilisateur</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
