<?php
/**
 * Page de connexion — FrenchyConciergerie
 * Compatible avec l'ancien système (table intervenant) ET le nouveau (table users + Auth.php)
 * Si la table users existe → utilise Auth.php
 * Sinon → fallback sur l'ancien système intervenant
 */

require_once __DIR__ . '/includes/env_loader.php';
require_once __DIR__ . '/db/connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Détecter si le nouveau système est disponible (table users)
$useNewAuth = false;
try {
    $conn->query("SELECT 1 FROM users LIMIT 1");
    $useNewAuth = true;
} catch (PDOException $e) {
    // Table users n'existe pas → ancien système
}

// ================================================================
// NOUVEAU SYSTÈME (Auth.php + table users)
// ================================================================
if ($useNewAuth) {
    require_once __DIR__ . '/includes/Auth.php';
    $auth = new Auth($conn);

    // Déjà connecté → rediriger
    if ($auth->check()) {
        header('Location: ' . $auth->getRedirectUrl());
        exit;
    }

    $error = '';
    $success = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        if (!$auth->validateCsrf()) {
            $error = 'Token de sécurité invalide. Veuillez rafraîchir la page.';
        } else {
            $email = trim($_POST['email'] ?? $_POST['nom_utilisateur'] ?? '');
            $password = $_POST['password'] ?? $_POST['mot_de_passe'] ?? '';
            $result = $auth->login($email, $password);
            if ($result['success']) {
                header('Location: ' . $auth->getRedirectUrl());
                exit;
            } else {
                $error = $result['error'];
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
        if (!$auth->validateCsrf()) {
            $error = 'Token de sécurité invalide.';
        } else {
            $email = trim($_POST['reset_email'] ?? '');
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $token = $auth->createResetToken($email);
            }
            $success = 'Si cette adresse existe, vous recevrez un email de réinitialisation.';
        }
    }

    $csrf_token = $auth->csrfToken();

// ================================================================
// ANCIEN SYSTÈME (table intervenant)
// ================================================================
} else {
    // Déjà connecté → rediriger
    if (isset($_SESSION['id_intervenant'])) {
        header('Location: index.php');
        exit;
    }

    $error = '';
    $success = '';
    $max_attempts = 5;
    $lockout_duration = 15 * 60;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['login']) || isset($_POST['nom_utilisateur']))) {
        $nom_utilisateur = trim($_POST['nom_utilisateur'] ?? $_POST['email'] ?? '');
        $mot_de_passe = trim($_POST['mot_de_passe'] ?? $_POST['password'] ?? '');
        $ip_address = $_SERVER['REMOTE_ADDR'];

        // Rate limiting (si la table existe)
        $locked = false;
        try {
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS attempts FROM login_attempts
                 WHERE ip_address = :ip AND attempt_time > NOW() - INTERVAL :duration SECOND"
            );
            $stmt->bindValue(':ip', $ip_address);
            $stmt->bindValue(':duration', $lockout_duration, PDO::PARAM_INT);
            $stmt->execute();
            $locked = (int) $stmt->fetchColumn() >= $max_attempts;
        } catch (PDOException $e) {
            // Table login_attempts n'existe pas — on continue
        }

        if ($locked) {
            $error = "Trop de tentatives de connexion. Réessayez plus tard.";
        } elseif (empty($nom_utilisateur) || empty($mot_de_passe)) {
            $error = "Veuillez remplir tous les champs.";
        } else {
            try {
                $stmt = $conn->prepare(
                    "SELECT id, nom_utilisateur, mot_de_passe, role
                     FROM intervenant
                     WHERE nom_utilisateur = :nom"
                );
                $stmt->execute([':nom' => $nom_utilisateur]);
                $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($utilisateur && password_verify($mot_de_passe, $utilisateur['mot_de_passe'])) {
                    try {
                        $conn->prepare("DELETE FROM login_attempts WHERE ip_address = :ip")
                            ->execute([':ip' => $ip_address]);
                    } catch (PDOException $e) {}

                    $_SESSION['id_intervenant'] = $utilisateur['id'];
                    $_SESSION['nom_utilisateur'] = htmlspecialchars($utilisateur['nom_utilisateur']);
                    $_SESSION['role'] = htmlspecialchars($utilisateur['role']);

                    header('Location: index.php');
                    exit;
                } else {
                    try {
                        $conn->prepare(
                            "INSERT INTO login_attempts (ip_address, nom_utilisateur) VALUES (:ip, :nom)"
                        )->execute([':ip' => $ip_address, ':nom' => $nom_utilisateur]);
                    } catch (PDOException $e) {}
                    $error = "Nom d'utilisateur ou mot de passe incorrect.";
                }
            } catch (PDOException $e) {
                $error = "Erreur interne, veuillez réessayer.";
                error_log("Erreur de connexion : " . $e->getMessage());
            }
        }
    }

    // CSRF token simple pour l'ancien système
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf_token = $_SESSION['csrf_token'];
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
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="email">Identifiant ou email</label>
                    <input type="text" id="email" name="email" required autocomplete="username"
                           placeholder="votre identifiant ou email"
                           value="<?= htmlspecialchars($_POST['email'] ?? $_POST['nom_utilisateur'] ?? '') ?>">
                    <!-- Champs cachés pour compatibilité avec les deux systèmes -->
                    <input type="hidden" name="nom_utilisateur" id="nom_utilisateur_hidden">
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required
                           autocomplete="current-password" placeholder="••••••••">
                    <input type="hidden" name="mot_de_passe" id="mot_de_passe_hidden">
                </div>

                <button type="submit" name="login" class="btn-login">Se connecter</button>

                <?php if ($useNewAuth): ?>
                <div class="forgot-password">
                    <a href="#" onclick="document.getElementById('resetModal').classList.add('active'); return false;">
                        Mot de passe oublié ?
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($useNewAuth): ?>
    <!-- Modal réinitialisation -->
    <div class="modal-overlay" id="resetModal">
        <div class="modal-content">
            <button class="modal-close" onclick="document.getElementById('resetModal').classList.remove('active')">&times;</button>
            <h2>Mot de passe oublié</h2>
            <p style="margin-bottom: 1.5rem; color: #6B7280;">Entrez votre email pour recevoir un lien de réinitialisation.</p>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
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
    <?php endif; ?>

    <script>
        // Synchroniser les champs pour compatibilité ancien/nouveau système
        document.querySelector('form.login-form').addEventListener('submit', function() {
            var email = document.getElementById('email').value;
            var pass = document.getElementById('password').value;
            document.getElementById('nom_utilisateur_hidden').value = email;
            document.getElementById('mot_de_passe_hidden').value = pass;
        });
    </script>
</body>
</html>
