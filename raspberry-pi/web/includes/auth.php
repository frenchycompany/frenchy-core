<?php
/**
 * Système d'authentification simple et sécurisé
 */

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifier si l'utilisateur est connecté
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
}

/**
 * Obtenir l'ID de l'utilisateur connecté
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Obtenir l'email de l'utilisateur connecté
 */
function getCurrentUserEmail() {
    return $_SESSION['user_email'] ?? null;
}

/**
 * Obtenir le nom de l'utilisateur connecté
 */
function getCurrentUserName() {
    return $_SESSION['user_name'] ?? null;
}

/**
 * Connexion utilisateur
 */
function login($pdo, $email, $password) {
    try {
        $stmt = $pdo->prepare("SELECT id, email, password, nom, prenom FROM users WHERE email = ? AND active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Régénérer l'ID de session pour éviter la fixation de session
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
            $_SESSION['login_time'] = time();

            // Mettre à jour la dernière connexion
            $stmt_update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt_update->execute([$user['id']]);

            return true;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Erreur de connexion : " . $e->getMessage());
        return false;
    }
}

/**
 * Déconnexion utilisateur
 */
function logout() {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}

/**
 * Protéger une page (rediriger vers login si non connecté)
 */
function requireAuth() {
    if (!isLoggedIn()) {
        $current_url = $_SERVER['REQUEST_URI'];
        header('Location: /pages/login.php?redirect=' . urlencode($current_url));
        exit;
    }
}

/**
 * Vérifier le timeout de session (30 minutes d'inactivité)
 */
function checkSessionTimeout($timeout = 1800) {
    if (isLoggedIn() && isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > $timeout) {
            logout();
            return false;
        }
        // Renouveler le timestamp
        $_SESSION['login_time'] = time();
    }
    return true;
}
