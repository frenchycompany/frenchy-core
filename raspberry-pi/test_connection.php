<?php
/**
 * Script de test de connexion à la base de données
 */

echo "=== Test de connexion à la base de données ===\n\n";

// 1. Vérifier le fichier .env
echo "1. Vérification du fichier .env...\n";
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    echo "✅ Fichier .env trouvé: $envPath\n";
    $envContent = file_get_contents($envPath);
    echo "Contenu:\n";
    foreach (explode("\n", $envContent) as $line) {
        if (trim($line) && !str_starts_with(trim($line), '#')) {
            echo "  $line\n";
        }
    }
} else {
    echo "❌ Fichier .env non trouvé à: $envPath\n";
}
echo "\n";

// 2. Charger les variables d'environnement
echo "2. Chargement de env_loader.php...\n";
require_once __DIR__ . '/web/includes/env_loader.php';
echo "✅ env_loader.php chargé\n\n";

// 3. Afficher les variables chargées
echo "3. Variables d'environnement chargées:\n";
$vars = ['DB_HOST', 'DB_USER', 'DB_PASSWORD', 'DB_NAME'];
foreach ($vars as $var) {
    $value = env($var, 'NON_DEFINI');
    if ($var === 'DB_PASSWORD') {
        $value = str_repeat('*', strlen($value));
    }
    echo "  $var = $value\n";
}
echo "\n";

// 4. Test de connexion PDO
echo "4. Test de connexion PDO...\n";
$host = env('DB_HOST', 'localhost');
$user = env('DB_USER', 'sms_user');
$password = env('DB_PASSWORD', 'password123');
$database = env('DB_NAME', 'sms_db');

// Essayer différentes méthodes
$methods = [
    "mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=$database;charset=utf8mb4",
    "mysql:unix_socket=/tmp/mysql.sock;dbname=$database;charset=utf8mb4",
    "mysql:host=127.0.0.1;port=3306;dbname=$database;charset=utf8mb4",
    "mysql:host=localhost;dbname=$database;charset=utf8mb4",
];

$connected = false;
foreach ($methods as $dsn) {
    echo "  Essai: $dsn\n";
    try {
        $pdo = new PDO(
            $dsn,
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        echo "  ✅ SUCCÈS avec cette méthode!\n\n";
        $connected = true;
        break;
    } catch (PDOException $e) {
        echo "  ❌ Échec: " . $e->getMessage() . "\n";
    }
}

if (!$connected) {
    echo "\n❌ ERREUR: Impossible de se connecter à la base de données\n";
    echo "Vérifiez que MariaDB/MySQL est en cours d'exécution.\n";
    exit(1);
}

// 5. Test de requête
echo "5. Test de requête SQL...\n";
try {
    $stmt = $pdo->query("SELECT DATABASE() as db, VERSION() as version");
    $result = $stmt->fetch();
    echo "✅ Base de données active: " . $result['db'] . "\n";
    echo "✅ Version: " . $result['version'] . "\n\n";
} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n\n";
    exit(1);
}

echo "=== Connexion réussie! ===\n";
