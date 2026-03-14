<?php
/**
 * Deconnexion - Espace Proprietaire
 * Compatible ancien et nouveau système
 */
require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../db/connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tenter le nouveau système si disponible
try {
    $conn->query("SELECT 1 FROM users LIMIT 1");
    require_once __DIR__ . '/../includes/Auth.php';
    $auth = new Auth($conn);
    $auth->logout();
} catch (PDOException $e) {
    // Ancien système : détruire la session manuellement
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

header('Location: login.php');
exit;
