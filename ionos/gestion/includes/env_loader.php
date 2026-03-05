<?php
/**
 * Chargeur de variables d'environnement (.env)
 * Charge les variables depuis le fichier .env à la racine du projet
 */

function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            $value = trim($value, '"\'');

            if (!array_key_exists($name, $_ENV)) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
    return true;
}

/**
 * Récupérer une variable d'environnement avec valeur par défaut
 */
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    if (is_string($value)) {
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
        }
    }

    return $value;
}

// Chercher le .env en remontant les répertoires
$envPaths = [
    __DIR__ . '/../../../.env',       // Depuis ionos/gestion/includes/ → racine repo
    __DIR__ . '/../../.env',          // Depuis ionos/gestion/ → racine repo
    '/etc/frenchy/app.env',           // Emplacement système (VPS production)
];

foreach ($envPaths as $envPath) {
    if (loadEnv($envPath)) {
        break;
    }
}
