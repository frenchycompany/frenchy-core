<?php
/**
 * Configuration principale de l'application unifiée FrenchyConciergerie
 * Point d'entrée unique : charge l'environnement, les erreurs, la BDD et la session
 */

// Gestion des erreurs (charge aussi env_loader.php)
require_once __DIR__ . '/includes/error_handler.php';

// Démarrer la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    $session_timeout = (int) env('SESSION_TIMEOUT', 1800);

    session_set_cookie_params([
        'httponly'  => true,
        'samesite'  => 'Strict',
        'secure'    => env('APP_ENV') === 'production',
    ]);

    session_start();
}

// Connexion à la base de données (fournit $conn et $pdo)
require_once __DIR__ . '/db/connection.php';

// Protection CSRF
require_once __DIR__ . '/includes/csrf.php';
