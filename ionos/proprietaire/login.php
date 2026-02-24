<?php
/**
 * Page de connexion - Espace Propriétaire
 * Frenchy Conciergerie
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

$security = new Security($conn);
$settings = getAllSettings($conn);

// Redirection si déjà connecté
if (isset($_SESSION['proprietaire_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Traitement de la connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Rate limiting
    $rateCheck = $security->checkRateLimit('login');
    if (!$rateCheck['allowed']) {
        $error = $rateCheck['message'];
    } elseif (!$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide. Veuillez rafraîchir la page.';
    } else {
        $email = $security->sanitize($_POST['email'] ?? '', 'email');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Veuillez remplir tous les champs.';
            $security->recordAttempt('login');
        } else {
            $stmt = $conn->prepare("SELECT * FROM FC_proprietaires WHERE email = ? AND actif = 1");
            $stmt->execute([$email]);
            $proprietaire = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($proprietaire && $security->verifyPassword($password, $proprietaire['password_hash'])) {
                // Connexion réussie
                $_SESSION['proprietaire_id'] = $proprietaire['id'];
                $_SESSION['proprietaire_nom'] = $proprietaire['nom'];

                $security->resetAttempts('login');
                $security->trackConversion('login', 'proprietaire');

                // Mise à jour dernière connexion
                $stmt = $conn->prepare("UPDATE FC_proprietaires SET derniere_connexion = NOW() WHERE id = ?");
                $stmt->execute([$proprietaire['id']]);

                header('Location: index.php');
                exit;
            } else {
                $error = 'Email ou mot de passe incorrect.';
                $security->recordAttempt('login');
            }
        }
    }
}

// Traitement de réinitialisation de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    $email = $security->sanitize($_POST['reset_email'] ?? '', 'email');

    if (empty($email) || !$security->validateEmail($email)) {
        $error = 'Veuillez entrer une adresse email valide.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM FC_proprietaires WHERE email = ? AND actif = 1");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            // Génération du token
            $token = $security->generateToken();
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $conn->prepare("UPDATE FC_proprietaires SET token_reset = ?, token_reset_expire = ? WHERE email = ?");
            $stmt->execute([$token, $expires, $email]);

            // En production, envoyer un email
            // mail($email, 'Réinitialisation de mot de passe', "Lien: .../reset.php?token=$token");
        }

        $success = 'Si cette adresse existe dans notre base, vous recevrez un email de réinitialisation.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Espace Propriétaire - <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></title>
    <style>
        :root {
            --bleu-frenchy: #1E3A8A;
            --bleu-clair: #3B82F6;
            --rouge-frenchy: #EF4444;
            --gris-clair: #F3F4F6;
            --gris-fonce: #1F2937;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--bleu-frenchy) 0%, var(--bleu-clair) 100%);
            padding: 1rem;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
        }

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
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 1rem;
        }

        .login-header h1 {
            color: var(--bleu-frenchy);
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #6B7280;
            font-size: 0.9rem;
        }

        .login-form {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

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

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .back-link {
            text-align: center;
            padding: 1.5rem;
            background: var(--gris-clair);
        }

        .back-link a {
            color: var(--bleu-frenchy);
            text-decoration: none;
            font-size: 0.9rem;
        }

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

        /* Modal reset password */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-overlay.active {
            display: flex;
        }

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
                <img src="../frenchyconciergerie.png.png" alt="Logo" onerror="this.style.display='none'">
                <h1>Espace Propriétaire</h1>
                <p>Connectez-vous pour accéder à votre tableau de bord</p>
            </div>

            <form method="POST" class="login-form">
                <?= $security->csrfField() ?>

                <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success"><?= e($success) ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="email">Adresse email</label>
                    <input type="email" id="email" name="email" required autocomplete="email" placeholder="votre@email.fr">
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                </div>

                <button type="submit" name="login" class="btn-login">Se connecter</button>

                <div class="forgot-password">
                    <a href="#" onclick="document.getElementById('resetModal').classList.add('active'); return false;">Mot de passe oublié ?</a>
                </div>
            </form>

            <div class="back-link">
                <a href="../index.php">← Retour au site</a>
            </div>
        </div>
    </div>

    <!-- Modal réinitialisation -->
    <div class="modal-overlay" id="resetModal">
        <div class="modal-content">
            <button class="modal-close" onclick="document.getElementById('resetModal').classList.remove('active')">&times;</button>
            <h2>Réinitialiser le mot de passe</h2>
            <p style="margin-bottom: 1.5rem; color: #6B7280;">Entrez votre email pour recevoir un lien de réinitialisation.</p>

            <form method="POST">
                <?= $security->csrfField() ?>
                <div class="form-group">
                    <label for="reset_email">Email</label>
                    <input type="email" id="reset_email" name="reset_email" required>
                </div>
                <button type="submit" name="reset" class="btn-login">Envoyer</button>
            </form>
        </div>
    </div>

    <script>
        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('resetModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    </script>
</body>
</html>
