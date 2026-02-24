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
        // Ignorer les commentaires
        if (strpos(trim($line), '#') === 0) continue;

        // Parser la ligne KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Ne pas écraser les variables existantes
            if (!isset($_ENV[$key]) && !getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

// Configuration de la base de données (avec fallback production)
$host = getenv('DB_HOST') ?: 'db5016690401.hosting-data.io';
$db = getenv('DB_NAME') ?: 'dbs13515816';
$user = getenv('DB_USER') ?: 'dbu275936';
$password = getenv('DB_PASS') ?: '**Baycpq25**';

$conn = null;
try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // En mode AJAX, on retourne JSON; sinon on affiche l'erreur
    if (isset($_POST['ajax']) || isset($_POST['save_simulation'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'DB connection failed']);
        exit;
    } else {
        // En production, ne pas afficher les détails de l'erreur
        $debug = getenv('APP_DEBUG') === 'true';
        if ($debug) {
            echo "Erreur de connexion : " . $e->getMessage();
        } else {
            echo "Erreur de connexion à la base de données. Veuillez réessayer plus tard.";
        }
    }
}
?>
