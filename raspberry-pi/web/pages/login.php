<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$error = '';
$redirect = $_GET['redirect'] ?? '/pages/reservation_list.php';

// Valider que la redirection est un chemin interne (pas d'URL externe)
if (!preg_match('#^/[a-zA-Z0-9._/-]*$#', $redirect)) {
    $redirect = '/pages/reservation_list.php';
}

// Si déjà connecté, rediriger
if (isLoggedIn()) {
    header('Location: ' . $redirect);
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        if (login($pdo, $email, $password)) {
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - SMS Management</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px;
            font-weight: bold;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-lock"></i>
            <h2>Connexion</h2>
            <p class="text-muted">SMS Management System</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email</label>
                <input type="email"
                       class="form-control"
                       id="email"
                       name="email"
                       placeholder="votre@email.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required
                       autofocus>
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-key"></i> Mot de passe</label>
                <input type="password"
                       class="form-control"
                       id="password"
                       name="password"
                       placeholder="••••••••"
                       required>
            </div>

            <button type="submit" class="btn btn-login btn-block">
                <i class="fas fa-sign-in-alt"></i> Se connecter
            </button>
        </form>

        <div class="mt-4 text-center text-muted small">
            <p><i class="fas fa-info-circle"></i> Contactez l'administrateur si vous avez oublie vos identifiants.</p>
        </div>
    </div>
</body>
</html>
