<?php
/**
 * Gestionnaire d'erreurs centralisé
 * Configure l'affichage des erreurs en fonction de l'environnement
 */

// Charger les variables d'environnement si pas déjà fait
if (!function_exists('env')) {
    require_once __DIR__ . '/env_loader.php';
}

// Récupérer la configuration depuis .env
$app_env = env('APP_ENV', 'production');
$app_debug = env('APP_DEBUG', false);

// Configuration selon l'environnement
if ($app_env === 'development' || $app_debug === true || $app_debug === 'true') {
    // Mode développement : afficher toutes les erreurs
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    // Mode production : masquer les erreurs, logger seulement
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);

    // Définir le fichier de log si pas déjà défini
    $log_file = __DIR__ . '/../../logs/error.log';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    ini_set('error_log', $log_file);
}

/**
 * Gestionnaire d'erreurs personnalisé pour production
 */
function productionErrorHandler($errno, $errstr, $errfile, $errline) {
    // Logger l'erreur
    error_log("[$errno] $errstr in $errfile:$errline");

    // En production, afficher un message générique
    if (env('APP_ENV', 'production') === 'production') {
        if ($errno === E_ERROR || $errno === E_USER_ERROR) {
            http_response_code(500);
            echo '<div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px;">';
            echo '<strong>Une erreur est survenue.</strong><br>';
            echo 'Veuillez contacter l\'administrateur si le problème persiste.';
            echo '</div>';
            exit;
        }
    }

    return false; // Laisser le gestionnaire par défaut continuer
}

// Définir le gestionnaire d'erreurs en production
if (env('APP_ENV', 'production') === 'production') {
    set_error_handler('productionErrorHandler');
}
