<?php
/**
 * Connexion à la BDD SMS.
 * Avant : connexion distante au RPi. Maintenant : BDD unique sur le VPS (localhost).
 * Le RPi se connecte à distance à cette même BDD.
 */

function getRpiPdo() {
    static $pdoRpi = null;
    if ($pdoRpi === null) {
        $host = env('RPI_DB_HOST', 'localhost');
        $port = env('RPI_DB_PORT', '3306');
        $name = env('RPI_DB_NAME', 'frenchyconciergerie');
        $user = env('RPI_DB_USER', env('DB_USER', ''));
        $pass = env('RPI_DB_PASSWORD', env('DB_PASSWORD', ''));
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $pdoRpi = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            $opts
        );
    }
    return $pdoRpi;
}
