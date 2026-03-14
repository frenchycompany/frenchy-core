<?php
/**
 * Connexion à la base de données
 * Lit les credentials depuis le fichier .env (avec fallback)
 */

// Charger les variables d'environnement depuis .env
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

$host     = getenv('DB_HOST') ?: '';
$db       = getenv('DB_NAME') ?: '';
$user     = getenv('DB_USER') ?: '';
$password = getenv('DB_PASS') ?: '';

if (!$host || !$db || !$user) {
    die("Fichier .env manquant ou incomplet. Veuillez configurer les accès à la base de données.");
}

$conn = null;

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("DB connection failed: " . $e->getMessage());
    die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
}
?>
