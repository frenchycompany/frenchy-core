<?php
/**
 * Connexion à la base de données
 * Utilise le pattern centralisé env_loader pour lire les credentials depuis .env
 */

// Timezone par défaut (France)
date_default_timezone_set('Europe/Paris');

// Charger env_loader centralisé si disponible, sinon loader local
$envLoaderPath = __DIR__ . '/../../ionos/gestion/includes/env_loader.php';
if (file_exists($envLoaderPath)) {
    require_once $envLoaderPath;
} else {
    // Fallback : charger les variables depuis .env local
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                if (!isset($_ENV[$key]) && !getenv($key)) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                }
            }
        }
    }
    if (!function_exists('env')) {
        function env($key, $default = null) {
            $value = getenv($key);
            return $value !== false ? $value : ($_ENV[$key] ?? $default);
        }
    }
}

// Configuration de la base de données
$host     = env('DB_HOST', '');
$db       = env('DB_NAME', '');
$user     = env('DB_USER', '');
$password = env('DB_PASS', '') ?: env('DB_PASSWORD', '');

if (!$host || !$db || !$user) {
    if (isset($_POST['ajax']) || isset($_POST['save_simulation'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Configuration BDD manquante']);
        exit;
    }
    die("Fichier .env manquant ou incomplet. Veuillez configurer les accès à la base de données.");
}

$conn = null;

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('[VF] DB connection failed: ' . $e->getMessage());
    if (isset($_POST['ajax']) || isset($_POST['save_simulation'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Connexion BDD impossible']);
        exit;
    }
    die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
}
?>
