<?php
/**
 * Connexion à la BDD du Raspberry Pi (pour sms_outbox)
 * Le RPi héberge le daemon envoyer_sms.py qui lit sms_outbox en local.
 * Le VPS insère directement dans la BDD du RPi via cette connexion.
 */

function getRpiPdo() {
    static $pdoRpi = null;
    if ($pdoRpi === null) {
        $host = env('RPI_DB_HOST', 'localhost');
        $port = env('RPI_DB_PORT', '3306');
        $name = env('RPI_DB_NAME', 'sms_db');
        $user = env('RPI_DB_USER', '');
        $pass = env('RPI_DB_PASSWORD', '');
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ];
        if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
            $opts[PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = 5;
        }
        $pdoRpi = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            $opts
        );
    }
    return $pdoRpi;
}
