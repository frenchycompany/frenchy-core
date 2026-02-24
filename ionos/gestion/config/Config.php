<?php
/**
 * Classe de gestion de la configuration
 * Charge et gère les variables d'environnement depuis le fichier .env
 */
class Config
{
    private static $instance = null;
    private static $config = [];
    private static $loaded = false;

    /**
     * Constructeur privé (Singleton)
     */
    private function __construct()
    {
        $this->loadEnv();
    }

    /**
     * Récupère l'instance unique de Config (Singleton)
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Charge le fichier .env
     */
    private function loadEnv()
    {
        if (self::$loaded) {
            return;
        }

        $envPath = dirname(__DIR__) . '/.env';

        // Si .env n'existe pas, charger les valeurs par défaut depuis .env.example
        if (!file_exists($envPath)) {
            $envPath = dirname(__DIR__) . '/.env.example';
            if (!file_exists($envPath)) {
                throw new Exception("Fichier .env introuvable. Copiez .env.example en .env et configurez vos paramètres.");
            }
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Ignorer les commentaires
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parser les lignes KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Retirer les guillemets si présents
                $value = trim($value, '"\'');

                self::$config[$key] = $value;

                // Définir aussi comme variable d'environnement
                if (!getenv($key)) {
                    putenv("$key=$value");
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Récupère une valeur de configuration
     *
     * @param string $key Clé de configuration
     * @param mixed $default Valeur par défaut si la clé n'existe pas
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        self::getInstance();

        // Chercher d'abord dans les variables d'environnement
        $value = getenv($key);
        if ($value !== false) {
            return self::castValue($value);
        }

        // Sinon dans le tableau de config
        if (isset(self::$config[$key])) {
            return self::castValue(self::$config[$key]);
        }

        return $default;
    }

    /**
     * Vérifie si une clé existe
     *
     * @param string $key
     * @return bool
     */
    public static function has($key)
    {
        self::getInstance();
        return isset(self::$config[$key]) || getenv($key) !== false;
    }

    /**
     * Définit une valeur de configuration (runtime seulement)
     *
     * @param string $key
     * @param mixed $value
     */
    public static function set($key, $value)
    {
        self::getInstance();
        self::$config[$key] = $value;
        putenv("$key=$value");
    }

    /**
     * Récupère toutes les configurations
     *
     * @return array
     */
    public static function all()
    {
        self::getInstance();
        return self::$config;
    }

    /**
     * Convertit les valeurs string en types appropriés
     *
     * @param string $value
     * @return mixed
     */
    private static function castValue($value)
    {
        $value = trim($value);

        // Booléens
        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }

        // Null
        if (strtolower($value) === 'null') {
            return null;
        }

        // Nombres
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }

        return $value;
    }

    /**
     * Récupère la configuration de la base de données
     *
     * @return array
     */
    public static function database()
    {
        return [
            'host' => self::get('DB_HOST', 'localhost'),
            'name' => self::get('DB_NAME', ''),
            'user' => self::get('DB_USER', ''),
            'password' => self::get('DB_PASSWORD', ''),
            'charset' => self::get('DB_CHARSET', 'utf8mb4'),
        ];
    }

    /**
     * Récupère la configuration de la base de données distante
     *
     * @return array
     */
    public static function remoteDatabase()
    {
        return [
            'host' => self::get('REMOTE_DB_HOST', ''),
            'name' => self::get('REMOTE_DB_NAME', ''),
            'user' => self::get('REMOTE_DB_USER', ''),
            'password' => self::get('REMOTE_DB_PASSWORD', ''),
            'charset' => self::get('DB_CHARSET', 'utf8mb4'),
        ];
    }

    /**
     * Vérifie si l'application est en mode debug
     *
     * @return bool
     */
    public static function isDebug()
    {
        return self::get('APP_DEBUG', false) === true;
    }

    /**
     * Récupère l'environnement actuel
     *
     * @return string
     */
    public static function environment()
    {
        return self::get('APP_ENV', 'production');
    }

    /**
     * Vérifie si l'environnement est production
     *
     * @return bool
     */
    public static function isProduction()
    {
        return self::environment() === 'production';
    }

    /**
     * Vérifie si l'environnement est development
     *
     * @return bool
     */
    public static function isDevelopment()
    {
        return self::environment() === 'development';
    }
}
