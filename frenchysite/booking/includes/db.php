<?php
require_once __DIR__ . '/../../../ionos/gestion/includes/env_loader.php';

function getBookingPdo(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = env('DB_HOST', '127.0.0.1');
    $name = env('DB_NAME', 'frenchyconciergerie');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASSWORD', '');
    $port = env('DB_PORT', '3306');

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
        $user, $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $pdo;
}
