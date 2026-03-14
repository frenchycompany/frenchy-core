<?php
/**
 * Connexion à la BDD centralisée sur le VPS IONOS.
 * Le RPi se connecte en TCP au VPS (pas de BDD locale).
 */

// Charger les variables d'environnement
require_once __DIR__ . '/env_loader.php';

// Paramètres de connexion vers le VPS
$host = env('DB_HOST', '87.106.246.151');
$port = env('DB_PORT', '3306');
$user = env('DB_USER', 'sms_user');
$password = env('DB_PASSWORD', '');
$database = env('DB_NAME', 'frenchyconciergerie');

$conn = null;
$pdo = null;
$db_error = null;

try {
    // Connexion TCP vers le VPS
    @$conn = new mysqli($host, $user, $password, $database, (int)$port);

    if ($conn->connect_error) {
        throw new Exception($conn->connect_error);
    }

    $conn->set_charset("utf8mb4");

    // Créer aussi l'objet PDO pour les pages qui l'utilisent
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

} catch (Exception $e) {
    $db_error = $e->getMessage();
    error_log("Erreur connexion BDD VPS: " . $db_error);
}
?>
