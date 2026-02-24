<?php
/**
 * Système de sécurité Frenchy Conciergerie
 * CSRF, Rate Limiting, Sessions sécurisées
 */

class Security {
    private $conn;
    private $csrf_token_lifetime = 3600; // 1 heure
    private $rate_limit_window = 3600; // 1 heure
    private $max_attempts = [
        'contact' => 5,
        'login' => 5,
        'newsletter' => 3,
        'calculateur' => 10
    ];
    private $block_duration = 1800; // 30 minutes

    public function __construct($conn) {
        $this->conn = $conn;
        $this->initSession();
    }

    /**
     * Initialise une session sécurisée
     */
    public function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Configuration sécurisée des cookies de session
            $cookieParams = [
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ];
            session_set_cookie_params($cookieParams);
            session_start();
        }

        // Régénération de l'ID de session périodique
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }

        // Vérification du fingerprint
        $fingerprint = $this->getFingerprint();
        if (!isset($_SESSION['fingerprint'])) {
            $_SESSION['fingerprint'] = $fingerprint;
        } elseif ($_SESSION['fingerprint'] !== $fingerprint) {
            // Fingerprint différent, possible vol de session
            session_destroy();
            session_start();
            $_SESSION['fingerprint'] = $fingerprint;
        }
    }

    /**
     * Génère un fingerprint unique pour la session
     */
    private function getFingerprint() {
        return hash('sha256',
            ($_SERVER['HTTP_USER_AGENT'] ?? '') .
            ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
        );
    }

    /**
     * Génère un token CSRF
     */
    public function generateCSRFToken() {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + $this->csrf_token_lifetime);

        // Stockage en base de données
        $stmt = $this->conn->prepare("INSERT INTO FC_csrf_tokens (token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $token,
            $this->getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $expires
        ]);

        // Stockage en session également
        $_SESSION['csrf_token'] = $token;

        return $token;
    }

    /**
     * Vérifie un token CSRF
     */
    public function validateCSRFToken($token) {
        if (empty($token)) {
            return false;
        }

        // Vérification en session
        if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
            return true;
        }

        // Vérification en base de données
        $stmt = $this->conn->prepare("SELECT id FROM FC_csrf_tokens WHERE token = ? AND expires_at > NOW() AND ip_address = ?");
        $stmt->execute([$token, $this->getClientIP()]);

        if ($stmt->fetch()) {
            // Supprimer le token utilisé
            $stmt = $this->conn->prepare("DELETE FROM FC_csrf_tokens WHERE token = ?");
            $stmt->execute([$token]);
            return true;
        }

        return false;
    }

    /**
     * Génère le champ HTML pour le token CSRF
     */
    public function csrfField() {
        $token = $this->generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Vérifie le rate limiting
     */
    public function checkRateLimit($action) {
        $ip = $this->getClientIP();
        $maxAttempts = $this->max_attempts[$action] ?? 10;

        // Vérifier si l'IP est bloquée
        $stmt = $this->conn->prepare("SELECT blocked_until FROM FC_rate_limit WHERE ip_address = ? AND action = ? AND blocked_until > NOW()");
        $stmt->execute([$ip, $action]);
        $blocked = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($blocked) {
            return [
                'allowed' => false,
                'message' => 'Trop de tentatives. Réessayez dans ' . $this->getTimeRemaining($blocked['blocked_until']) . '.',
                'blocked_until' => $blocked['blocked_until']
            ];
        }

        // Compter les tentatives récentes
        $stmt = $this->conn->prepare("SELECT attempts, first_attempt FROM FC_rate_limit WHERE ip_address = ? AND action = ? AND first_attempt > DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $stmt->execute([$ip, $action, $this->rate_limit_window]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record && $record['attempts'] >= $maxAttempts) {
            // Bloquer l'IP
            $blockedUntil = date('Y-m-d H:i:s', time() + $this->block_duration);
            $stmt = $this->conn->prepare("UPDATE FC_rate_limit SET blocked_until = ? WHERE ip_address = ? AND action = ?");
            $stmt->execute([$blockedUntil, $ip, $action]);

            return [
                'allowed' => false,
                'message' => 'Trop de tentatives. Réessayez dans ' . ceil($this->block_duration / 60) . ' minutes.',
                'blocked_until' => $blockedUntil
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Enregistre une tentative
     */
    public function recordAttempt($action) {
        $ip = $this->getClientIP();

        $stmt = $this->conn->prepare("SELECT id FROM FC_rate_limit WHERE ip_address = ? AND action = ? AND first_attempt > DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $stmt->execute([$ip, $action, $this->rate_limit_window]);

        if ($stmt->fetch()) {
            $stmt = $this->conn->prepare("UPDATE FC_rate_limit SET attempts = attempts + 1, last_attempt = NOW() WHERE ip_address = ? AND action = ?");
            $stmt->execute([$ip, $action]);
        } else {
            $stmt = $this->conn->prepare("INSERT INTO FC_rate_limit (ip_address, action, attempts, first_attempt, last_attempt) VALUES (?, ?, 1, NOW(), NOW())");
            $stmt->execute([$ip, $action]);
        }
    }

    /**
     * Réinitialise le compteur après succès
     */
    public function resetAttempts($action) {
        $ip = $this->getClientIP();
        $stmt = $this->conn->prepare("DELETE FROM FC_rate_limit WHERE ip_address = ? AND action = ?");
        $stmt->execute([$ip, $action]);
    }

    /**
     * Récupère l'adresse IP du client
     */
    public function getClientIP() {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Calcule le temps restant avant déblocage
     */
    private function getTimeRemaining($blockedUntil) {
        $remaining = strtotime($blockedUntil) - time();
        if ($remaining < 60) {
            return $remaining . ' secondes';
        } elseif ($remaining < 3600) {
            return ceil($remaining / 60) . ' minutes';
        } else {
            return ceil($remaining / 3600) . ' heures';
        }
    }

    /**
     * Nettoie les tokens et rate limits expirés
     */
    public function cleanup() {
        // Supprimer les tokens CSRF expirés
        $this->conn->exec("DELETE FROM FC_csrf_tokens WHERE expires_at < NOW()");

        // Supprimer les rate limits anciens
        $this->conn->exec("DELETE FROM FC_rate_limit WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR) AND (blocked_until IS NULL OR blocked_until < NOW())");
    }

    /**
     * Hash un mot de passe de manière sécurisée
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    /**
     * Vérifie un mot de passe
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Génère un token aléatoire sécurisé
     */
    public function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    /**
     * Valide et nettoie une entrée
     */
    public function sanitize($input, $type = 'string') {
        if ($input === null) return '';

        switch ($type) {
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            case 'html':
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Valide un email
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Valide un numéro de téléphone français
     */
    public function validatePhone($phone) {
        $phone = preg_replace('/[\s\.\-]/', '', $phone);
        return preg_match('/^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/', $phone);
    }

    /**
     * Enregistre une visite pour les statistiques
     */
    public function trackVisit($page) {
        $stmt = $this->conn->prepare("INSERT INTO FC_visites (page, ip_address, user_agent, referer, session_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $page,
            $this->getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_REFERER'] ?? '',
            session_id()
        ]);
    }

    /**
     * Enregistre une conversion
     */
    public function trackConversion($type, $source = null, $donnees = null) {
        $stmt = $this->conn->prepare("INSERT INTO FC_conversions (type, source, ip_address, donnees) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $type,
            $source,
            $this->getClientIP(),
            $donnees ? json_encode($donnees) : null
        ]);
    }
}

/**
 * Classe pour la gestion du cache
 */
class Cache {
    private $conn;
    private $cache_dir;
    private $default_ttl = 3600;

    public function __construct($conn, $cache_dir = null) {
        $this->conn = $conn;
        $this->cache_dir = $cache_dir ?? __DIR__ . '/../cache';

        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }

    /**
     * Récupère une valeur du cache
     */
    public function get($key) {
        $file = $this->getCacheFile($key);

        if (!file_exists($file)) {
            return null;
        }

        $data = json_decode(file_get_contents($file), true);

        if ($data['expires'] < time()) {
            unlink($file);
            return null;
        }

        return $data['value'];
    }

    /**
     * Stocke une valeur dans le cache
     */
    public function set($key, $value, $ttl = null) {
        $file = $this->getCacheFile($key);
        $ttl = $ttl ?? $this->default_ttl;

        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];

        file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /**
     * Supprime une valeur du cache
     */
    public function delete($key) {
        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Vide tout le cache
     */
    public function clear() {
        $files = glob($this->cache_dir . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * Récupère le chemin du fichier cache
     */
    private function getCacheFile($key) {
        return $this->cache_dir . '/' . md5($key) . '.cache';
    }

    /**
     * Cache le résultat d'une requête DB
     */
    public function remember($key, $callback, $ttl = null) {
        $value = $this->get($key);

        if ($value === null) {
            $value = $callback();
            $this->set($key, $value, $ttl);
        }

        return $value;
    }
}
?>
