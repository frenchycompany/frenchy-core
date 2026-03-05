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
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!empty($newPassword)) {
            if ($newPassword !== $confirmPassword) {
                $message = '<div class="alert alert-danger">Les mots de passe ne correspondent pas.</div>';
            } elseif (strlen($newPassword) < 6) {
                $message = '<div class="alert alert-danger">Le mot de passe doit contenir au moins 6 caractères.</div>';
            } else {
                try {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE intervenant SET mot_de_passe = ? WHERE id = ?");
                    $stmt->execute([$hash, $id_intervenant]);
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
    <div class="card">
        <div class="card-header">Changer le mot de passe</div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <div class="mb-3">
                    <label for="new_password" class="form-label">Nouveau mot de passe</label>
                    <input type="password" name="new_password" id="new_password" class="form-control" required minlength="6">
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="6">
                </div>
                <button type="submit" class="btn btn-primary">Mettre à jour</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
