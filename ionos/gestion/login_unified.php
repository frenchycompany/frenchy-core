<?php
/**
 * Page de connexion unifiée — FrenchyConciergerie
 * Tous les rôles passent par cette page unique.
 * Redirection automatique selon le rôle après connexion.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Auth.php';

$auth = new Auth($conn);

// Déjà connecté → rediriger
if ($auth->check()) {
    header('Location: ' . $auth->getRedirectUrl());
    exit;
}

$error = '';
$success = '';

// Traitement connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!$auth->validateCsrf()) {
        $error = 'Token de sécurité invalide. Veuillez rafraîchir la page.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = $auth->login($email, $password);

        if ($result['success']) {
            header('Location: ' . $auth->getRedirectUrl());
            exit;
        } else {
            $error = $result['error'];
        }
    }
}

// Traitement reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    if (!$auth->validateCsrf()) {
        $error = 'Token de sécurité invalide.';
    } else {
        $email = trim($_POST['reset_email'] ?? '');
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $token = $auth->createResetToken($email);
            // En production : envoyer l'email avec le token
            // if ($token) { mail($email, 'Reset', "...?token=$token"); }
        }
        $success = 'Si cette adresse existe, vous recevrez un email de réinitialisation.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — FrenchyConciergerie</title>
    <style>
        :root {
            --bleu-frenchy: #1E3A8A;
            --bleu-clair: #3B82F6;
            --rouge-frenchy: #EF4444;
            --gris-clair: #F3F4F6;
            --gris-fonce: #1F2937;
            --vert: #10B981;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--bleu-frenchy) 0%, var(--bleu-clair) 100%);
            padding: 1rem;
        }

        .login-container { width: 100%; max-width: 420px; }

        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .login-header {
            background: var(--gris-clair);
            padding: 2rem;
            text-align: center;
        }

        .login-header img {
            width: 80px; height: 80px;
            border-radius: 50%;
            margin-bottom: 1rem;
        }

        .login-header h1 {
            color: var(--bleu-frenchy);
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }

        .login-header p {
            color: #6B7280;
            font-size: 0.9rem;
        }

        .login-form { padding: 2rem; }

        .form-group { margin-bottom: 1.5rem; }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gris-fonce);
        }

        .form-group input {
            width: 100%;
            padding: 0.9rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--bleu-clair);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--bleu-frenchy), var(--bleu-clair));
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(30, 58, 138, 0.4);
        }

        .forgot-password {
            text-align: center;
            margin-top: 1rem;
        }

        .forgot-password a {
            color: var(--bleu-clair);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .forgot-password a:hover { text-decoration: underline; }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }

        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        /* Modal reset */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-overlay.active { display: flex; }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            width: 90%;
            max-width: 400px;
        }

        .modal-content h2 {
            color: var(--bleu-frenchy);
            margin-bottom: 1rem;
        }

        .modal-close {
            float: right;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6B7280;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="frenchyconciergerie.png.png" alt="Logo" onerror="this.style.display='none'">
                <h1>FrenchyConciergerie</h1>
                <p>Connectez-vous pour accéder à votre espace</p>
            </div>

            <form method="POST" class="login-form">
                <?= $auth->csrfField() ?>

                <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="email">Adresse email</label>
                    <input type="email" id="email" name="email" required autocomplete="email"
                           placeholder="votre@email.fr"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required
                           autocomplete="current-password" placeholder="••••••••">
                </div>

                <button type="submit" name="login" class="btn-login">Se connecter</button>

                <div class="forgot-password">
                    <a href="#" onclick="document.getElementById('resetModal').classList.add('active'); return false;">
                        Mot de passe oublié ?
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal réinitialisation -->
    <div class="modal-overlay" id="resetModal">
        <div class="modal-content">
            <button class="modal-close" onclick="document.getElementById('resetModal').classList.remove('active')">&times;</button>
            <h2>Mot de passe oublié</h2>
            <p style="margin-bottom: 1.5rem; color: #6B7280;">Entrez votre email pour recevoir un lien de réinitialisation.</p>

            <form method="POST">
                <?= $auth->csrfField() ?>
                <div class="form-group">
                    <label for="reset_email">Email</label>
                    <input type="email" id="reset_email" name="reset_email" required>
                </div>
                <button type="submit" name="reset" class="btn-login">Envoyer</button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('resetModal').addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });
    </script>
</body>
</html>
