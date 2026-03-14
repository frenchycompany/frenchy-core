<?php
/**
 * Connexion à la base de données
 * Utilise les variables d'environnement (.env) via env_loader
 */

require_once __DIR__ . '/../gestion/includes/env_loader.php';

$host     = env('DB_HOST', 'localhost');
$db       = env('DB_NAME', 'frenchyconciergerie');
$user     = env('DB_USER', 'frenchy_app');
$password = env('DB_PASSWORD', '');
$port     = env('DB_PORT', '3306');
$charset  = env('DB_CHARSET', 'utf8mb4');

$conn = null;
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
    $conn = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log("Erreur de connexion BDD (site vitrine) : " . $e->getMessage());
    // En mode AJAX, on retourne JSON; sinon on affiche l'erreur
    if (isset($_POST['ajax']) || isset($_POST['save_simulation'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'DB connection failed']);
        exit;
    } else {
        if (env('APP_DEBUG', false)) {
            die("Erreur de connexion : " . $e->getMessage());
        } else {
            die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
        }
    }
}
?>
