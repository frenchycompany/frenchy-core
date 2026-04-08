<?php
/**
 * Cache - Système de cache simple basé sur fichiers
 */

class Cache
{
    private static $instance = null;
    private $cacheDir;
    private $enabled;
    private $defaultLifetime;

    /**
     * Constructeur privé (Singleton)
     */
    private function __construct()
    {
        $this->cacheDir = BASE_PATH . '/cache';
        $this->enabled = Config::get('CACHE_ENABLED', true);
        $this->defaultLifetime = Config::get('CACHE_LIFETIME', 3600); // 1 heure par défaut

        // Créer le dossier de cache s'il n'existe pas
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Récupère l'instance unique (Singleton)
     *
     * @return Cache
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Récupère une valeur du cache
     *
     * @param string $key Clé du cache
     * @param mixed $default Valeur par défaut si non trouvé
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (!$this->enabled) {
            return $default;
        }

        $filename = $this->getFilename($key);

        if (!file_exists($filename)) {
            return $default;
        }

        $data = json_decode(file_get_contents($filename), true);

        if ($data === null) {
            $this->delete($key);
            return $default;
        }

        // Vérifier l'expiration
        if ($data['expires'] > 0 && time() > $data['expires']) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    /**
     * Stocke une valeur dans le cache
     *
     * @param string $key Clé du cache
     * @param mixed $value Valeur à stocker
     * @param int|null $lifetime Durée de vie en secondes (null = par défaut)
     * @return bool
     */
    public function set($key, $value, $lifetime = null)
    {
        if (!$this->enabled) {
            return false;
        }

        if ($lifetime === null) {
            $lifetime = $this->defaultLifetime;
        }

        $data = [
            'value' => $value,
            'expires' => $lifetime > 0 ? time() + $lifetime : 0,
            'created' => time(),
        ];

        $filename = $this->getFilename($key);

        return file_put_contents($filename, json_encode($data)) !== false;
    }

    /**
     * Vérifie si une clé existe dans le cache
     *
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        if (!$this->enabled) {
            return false;
        }

        $filename = $this->getFilename($key);

        if (!file_exists($filename)) {
            return false;
        }

        // Vérifier l'expiration
        $data = json_decode(file_get_contents($filename), true);

        if ($data === null || ($data['expires'] > 0 && time() > $data['expires'])) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    /**
     * Supprime une entrée du cache
     *
     * @param string $key
     * @return bool
     */
    public function delete($key)
    {
        $filename = $this->getFilename($key);

        if (file_exists($filename)) {
            return unlink($filename);
        }

        return false;
    }

    /**
     * Récupère ou stocke une valeur via callback
     *
     * @param string $key Clé du cache
     * @param callable $callback Fonction pour générer la valeur si non en cache
     * @param int|null $lifetime Durée de vie
     * @return mixed
     */
    public function remember($key, $callback, $lifetime = null)
    {
        // Si la valeur existe en cache, la retourner
        if ($this->has($key)) {
            return $this->get($key);
        }

        // Sinon, exécuter le callback et stocker le résultat
        $value = $callback();
        $this->set($key, $value, $lifetime);

        return $value;
    }

    /**
     * Vide tout le cache
     *
     * @return bool
     */
    public function flush()
    {
        $files = glob($this->cacheDir . '/*');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * Nettoie les entrées expirées
     *
     * @return int Nombre d'entrées supprimées
     */
    public function clean()
    {
        $count = 0;
        $files = glob($this->cacheDir . '/*');

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $data = json_decode(file_get_contents($file), true);

            if ($data === null || ($data['expires'] > 0 && time() > $data['expires'])) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Récupère les statistiques du cache
     *
     * @return array
     */
    public function stats()
    {
        $files = glob($this->cacheDir . '/*');
        $stats = [
            'total' => 0,
            'expired' => 0,
            'valid' => 0,
            'size' => 0,
        ];

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $stats['total']++;
            $stats['size'] += filesize($file);

            $data = json_decode(file_get_contents($file), true);

            if ($data === null || ($data['expires'] > 0 && time() > $data['expires'])) {
                $stats['expired']++;
            } else {
                $stats['valid']++;
            }
        }

        return $stats;
    }

    /**
     * Génère le nom de fichier pour une clé
     *
     * @param string $key
     * @return string
     */
    private function getFilename($key)
    {
        $hash = md5($key);
        return $this->cacheDir . '/' . $hash . '.cache';
    }

    /**
     * Empêche le clonage
     */
    private function __clone() {}

    /**
     * Empêche la désérialisation
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Helpers de cache
 */

/**
 * Récupère une valeur du cache
 */
function cache_get($key, $default = null)
{
    return Cache::getInstance()->get($key, $default);
}

/**
 * Stocke une valeur dans le cache
 */
function cache_set($key, $value, $lifetime = null)
{
    return Cache::getInstance()->set($key, $value, $lifetime);
}

/**
 * Récupère ou stocke via callback
 */
function cache_remember($key, $callback, $lifetime = null)
{
    return Cache::getInstance()->remember($key, $callback, $lifetime);
}

/**
 * Supprime une entrée du cache
 */
function cache_forget($key)
{
    return Cache::getInstance()->delete($key);
}

/**
 * Vide tout le cache
 */
function cache_flush()
{
    return Cache::getInstance()->flush();
}
