<?php
/**
 * Connexion à la base de données unifiée
 * Utilise les variables d'environnement (.env) au lieu de credentials en dur
 */

require_once __DIR__ . '/../includes/env_loader.php';

$host     = env('DB_HOST', 'localhost');
$db       = env('DB_NAME', 'frenchyconciergerie');
$user     = env('DB_USER', 'frenchy_app');
$password = env('DB_PASSWORD', '');
$port     = env('DB_PORT', '3306');
$charset  = env('DB_CHARSET', 'utf8mb4');

$conn = null;
$pdo = null;

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
    $conn = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Alias $pdo pour compatibilité avec les pages Raspberry Pi
    $pdo = $conn;

} catch (PDOException $e) {
    error_log("Erreur de connexion BDD : " . $e->getMessage());
    if (env('APP_DEBUG', false)) {
        die("Erreur de connexion : " . $e->getMessage());
    } else {
        die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
    }
}
