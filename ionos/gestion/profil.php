<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/pages/menu.php';

if (!isset($_SESSION['id_intervenant'])) {
    header('Location: login.php');
    exit;
}

$id_intervenant  = $_SESSION['id_intervenant'];
$nom_utilisateur = $_SESSION['nom_utilisateur'] ?? '';
$role            = $_SESSION['role'] ?? 'user';

// Traitement du formulaire de mise à jour
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $message = '<div class="alert alert-danger">Jeton CSRF invalide.</div>';
    } else {
        $action = $_POST['form_action'] ?? 'password';

        // Mise à jour du téléphone
        if ($action === 'telephone') {
            $telephone = trim($_POST['telephone'] ?? '');
            if (!empty($telephone) && !preg_match('/^\+?[0-9\s]{10,20}$/', $telephone)) {
                $message = '<div class="alert alert-danger">Format de téléphone invalide.</div>';
            } else {
                try {
                    // Mettre à jour dans la table users
                    $userId = $_SESSION['user_id'] ?? null;
                    if ($userId) {
                        $stmt = $conn->prepare("UPDATE users SET telephone = ? WHERE id = ?");
                        $stmt->execute([$telephone ?: null, $userId]);
                        $message = '<div class="alert alert-success">Numéro de téléphone mis à jour.</div>';
                    }
                } catch (PDOException $e) {
                    error_log('Erreur profil.php telephone : ' . $e->getMessage());
                    $message = '<div class="alert alert-danger">Erreur lors de la mise à jour.</div>';
                }
            }
        }

        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($action === 'password' && !empty($newPassword)) {
            if ($newPassword !== $confirmPassword) {
                $message = '<div class="alert alert-danger">Les mots de passe ne correspondent pas.</div>';
            } elseif (strlen($newPassword) < 8) {
                $message = '<div class="alert alert-danger">Le mot de passe doit contenir au moins 8 caractères.</div>';
            } else {
                try {
                    $hash = password_hash($newPassword, PASSWORD_ARGON2ID, [
                        'memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3
                    ]);
                    $stmt = $conn->prepare("UPDATE intervenant SET mot_de_passe = ? WHERE id = ?");
                    $stmt->execute([$hash, $id_intervenant]);

                    // Sync vers la table users (système d'auth unifié)
                    $stmtUser = $conn->prepare("UPDATE users SET password_hash = ? WHERE legacy_intervenant_id = ?");
                    $stmtUser->execute([$hash, $id_intervenant]);

                    $message = '<div class="alert alert-success">Mot de passe mis à jour avec succès.</div>';
                } catch (PDOException $e) {
                    error_log('Erreur profil.php : ' . $e->getMessage());
                    $message = '<div class="alert alert-danger">Erreur lors de la mise à jour.</div>';
                }
            }
        }
    }
}

// Récupération des infos de l'intervenant
try {
    $stmt = $conn->prepare("SELECT nom, nom_utilisateur, role FROM intervenant WHERE id = ?");
    $stmt->execute([$id_intervenant]);
    $intervenant = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Erreur profil.php : ' . $e->getMessage());
    $intervenant = ['nom' => $nom_utilisateur, 'nom_utilisateur' => $nom_utilisateur, 'role' => $role];
}

// Récupérer le téléphone depuis la table users
$userPhone = '';
$userId = $_SESSION['user_id'] ?? null;
if ($userId) {
    try {
        $stmt = $conn->prepare("SELECT telephone FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userPhone = $stmt->fetchColumn() ?: '';
    } catch (PDOException $e) {
        // Table users pas encore migrée, pas grave
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil</title>
    <!-- Bootstrap CSS/JS déjà chargés par menu.php -->
</head>
<body>
<div class="container mt-4">
    <h2>Mon Profil</h2>
    <?= $message ?>
    <div class="card mb-4">
        <div class="card-body">
            <p><strong>Nom :</strong> <?= htmlspecialchars($intervenant['nom'] ?? '') ?></p>
            <p><strong>Identifiant :</strong> <?= htmlspecialchars($intervenant['nom_utilisateur'] ?? '') ?></p>
            <p><strong>Rôle :</strong> <?= htmlspecialchars($intervenant['role'] ?? '') ?></p>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-phone me-2"></i>Téléphone (utilisé pour le coffre-fort 2FA)</div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="form_action" value="telephone">
                <div class="mb-3">
                    <label for="telephone" class="form-label">Numéro de téléphone</label>
                    <input type="tel" name="telephone" id="telephone" class="form-control"
                           value="<?= htmlspecialchars($userPhone) ?>" placeholder="+33612345678">
                    <div class="form-text">Ce numéro recevra les codes d'accès au coffre-fort numérique.</div>
                </div>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header">Changer le mot de passe</div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="form_action" value="password">
                <div class="mb-3">
                    <label for="new_password" class="form-label">Nouveau mot de passe</label>
                    <input type="password" name="new_password" id="new_password" class="form-control" required minlength="8">
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="8">
                </div>
                <button type="submit" class="btn btn-primary">Mettre à jour</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
