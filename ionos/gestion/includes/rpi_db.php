<?php
/**
 * Connexion à la BDD SMS/réservations
 *
 * Après migration : les tables SMS/réservations sont sur la même base VPS.
 * getRpiPdo() retourne maintenant la connexion principale ($conn) du VPS.
 *
 * Le Raspberry Pi (daemon envoyer_sms.py) se connecte désormais au VPS
 * en distant pour lire sms_outbox.
 */

function getRpiPdo() {
    // Utiliser la connexion principale VPS (les tables RPi y sont maintenant)
    global $conn;

    if ($conn instanceof PDO) {
        return $conn;
    }

    // Fallback : si $conn n'est pas encore initialisé (appel hors contexte normal),
    // créer une connexion avec les mêmes paramètres que connection.php
    static $pdoLocal = null;
    if ($pdoLocal === null) {
        $host = env('DB_HOST', 'localhost');
        $port = env('DB_PORT', '3306');
        $name = env('DB_NAME', 'frenchyconciergerie');
        $user = env('DB_USER', 'frenchy_app');
        $pass = env('DB_PASSWORD', '');
        $pdoLocal = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdoLocal;
}
