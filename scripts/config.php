<?php
/**
 * Configuration partagée pour les scripts d'automatisation
 * Charge les variables d'environnement et fournit la connexion BDD
 *
 * Usage dans les scripts :
 *   require_once __DIR__ . '/config.php';
 *   // $pdo est maintenant disponible
 */

// Charger l'env_loader unifié
require_once __DIR__ . '/../ionos/gestion/includes/env_loader.php';

// Connexion à la base de données
try {
    $host    = env('DB_HOST', 'localhost');
    $db      = env('DB_NAME', 'frenchyconciergerie');
    $user    = env('DB_USER', 'frenchy_app');
    $pass    = env('DB_PASSWORD', '');
    $port    = env('DB_PORT', '3306');
    $charset = env('DB_CHARSET', 'utf8mb4');

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=$charset",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    // Alias pour compatibilité
    $conn = $pdo;

} catch (PDOException $e) {
    echo "Erreur de connexion BDD : " . $e->getMessage() . "\n";
    exit(1);
}
