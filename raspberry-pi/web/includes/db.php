<?php
// Charger les variables d'environnement
require_once __DIR__ . '/env_loader.php';

// Paramètres de connexion à la base de données depuis .env
$host = env('DB_HOST', 'localhost');
$user = env('DB_USER', 'sms_user');
$password = env('DB_PASSWORD', 'password123');
$database = env('DB_NAME', 'sms_db');

$conn = null;
$pdo = null;
$db_error = null;

// Essayer plusieurs méthodes de connexion
try {
    // Méthode 1: Connexion via socket Unix (plus courante sur Raspberry Pi)
    @$conn = new mysqli($host, $user, $password, $database, null, '/var/run/mysqld/mysqld.sock');

    if ($conn->connect_error) {
        // Méthode 2: Connexion via socket alternatif
        @$conn = new mysqli($host, $user, $password, $database, null, '/tmp/mysql.sock');

        if ($conn->connect_error) {
            // Méthode 3: Connexion TCP
            @$conn = new mysqli('127.0.0.1', $user, $password, $database, 3306);

            if ($conn->connect_error) {
                throw new Exception($conn->connect_error);
            }
        }
    }

    $conn->set_charset("utf8mb4");

    // Créer aussi l'objet PDO pour les pages qui l'utilisent
    // Essayer plusieurs méthodes de connexion PDO
    $pdo_connected = false;

    // Méthode 1: Socket Unix principal
    try {
        $pdo = new PDO(
            "mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=$database;charset=utf8mb4",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        $pdo_connected = true;
    } catch (PDOException $e) {
        // Essayer la méthode suivante
    }

    // Méthode 2: Socket Unix alternatif
    if (!$pdo_connected) {
        try {
            $pdo = new PDO(
                "mysql:unix_socket=/tmp/mysql.sock;dbname=$database;charset=utf8mb4",
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            $pdo_connected = true;
        } catch (PDOException $e) {
            // Essayer la méthode suivante
        }
    }

    // Méthode 3: Connexion TCP
    if (!$pdo_connected) {
        try {
            $pdo = new PDO(
                "mysql:host=127.0.0.1;port=3306;dbname=$database;charset=utf8mb4",
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            $pdo_connected = true;
        } catch (PDOException $e) {
            // Si toutes les méthodes échouent, logger l'erreur
            error_log("Erreur connexion PDO: " . $e->getMessage());
        }
    }

} catch (Exception $e) {
    $db_error = $e->getMessage();
    // Pour les pages qui nécessitent absolument la base de données
    // On peut décommenter la ligne suivante:
    // die("❌ Erreur de connexion à la base de données : " . $db_error);
}
?>
