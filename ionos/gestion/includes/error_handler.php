<?php
/**
 * Gestionnaire d'erreurs centralisé
 * Configure l'affichage des erreurs en fonction de l'environnement
 */

if (!function_exists('env')) {
    require_once __DIR__ . '/env_loader.php';
}

$app_env = env('APP_ENV', 'production');
$app_debug = env('APP_DEBUG', false);

if ($app_env === 'development' || $app_debug === true) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);

    $log_file = __DIR__ . '/../../logs/error.log';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    ini_set('error_log', $log_file);
}

function productionErrorHandler($errno, $errstr, $errfile, $errline) {
    error_log("[$errno] $errstr in $errfile:$errline");

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

    return false;
}

if (env('APP_ENV', 'production') === 'production') {
    set_error_handler('productionErrorHandler');
}
