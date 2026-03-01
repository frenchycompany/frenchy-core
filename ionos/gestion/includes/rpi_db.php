<?php
/**
 * Connexion à la BDD du Raspberry Pi (pour sms_outbox)
 * Le RPi héberge le daemon envoyer_sms.py qui lit sms_outbox en local.
 * Le VPS insère directement dans la BDD du RPi via cette connexion.
 */

function getRpiPdo() {
    static $pdoRpi = null;
    if ($pdoRpi === null) {
        $host = env('RPI_DB_HOST', '109.219.194.30');
        $port = env('RPI_DB_PORT', '3306');
        $name = env('RPI_DB_NAME', 'sms_db');
        $user = env('RPI_DB_USER', 'remote');
        $pass = env('RPI_DB_PASSWORD', 'remoteionos25');
        $pdoRpi = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdoRpi;
}
