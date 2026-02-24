<?php
$env_file = __DIR__ . '/.env';

// Vérifier que le fichier .env existe
if (!file_exists($env_file)) {
    die("Erreur : Le fichier .env est introuvable dans " . __DIR__);
}

$env = parse_ini_file($env_file);

// Vérifier que les variables sont bien chargées
if (!$env) {
    die("Erreur : Impossible de charger les variables d'environnement.");
}

// Définir les constantes si elles existent
define('DB_HOST', $env['DB_HOST'] ?? '');
define('DB_NAME', $env['DB_NAME'] ?? '');
define('DB_USER', $env['DB_USER'] ?? '');
define('DB_PASS', $env['DB_PASS'] ?? '');

// Vérifier que toutes les constantes sont bien définies
if (empty(DB_HOST) || empty(DB_NAME) || empty(DB_USER) || empty(DB_PASS)) {
    die("Erreur : Certaines constantes de connexion ne sont pas définies.");
}
?>
