<?php
/**
 * Classe de gestion de la sécurité
 * Validation, upload de fichiers, sanitization, etc.
 */

class Security
{
    /**
     * Valide et sécurise un upload de fichier
     *
     * @param array $file Le fichier depuis $_FILES
     * @param array $options Options de validation
     * @return array ['success' => bool, 'message' => string, 'path' => string|null]
     */
    public static function validateUpload($file, $options = [])
    {
        $defaults = [
            'max_size' => Config::get('MAX_UPLOAD_SIZE', 5242880), // 5MB
            'allowed_extensions' => explode(',', Config::get('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,pdf')),
            'allowed_mime_types' => [
                'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ],
            'upload_path' => Config::get('UPLOAD_PATH', 'uploads'),
            'create_subdirs' => true,
            'overwrite' => false,
        ];

        $options = array_merge($defaults, $options);

        // Vérifier qu'un fichier a été uploadé
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'message' => 'Paramètres invalides.', 'path' => null];
        }

        // Vérifier les erreurs d'upload
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['success' => false, 'message' => 'Le fichier dépasse la taille maximale autorisée.', 'path' => null];
            case UPLOAD_ERR_NO_FILE:
                return ['success' => false, 'message' => 'Aucun fichier n\'a été uploadé.', 'path' => null];
            default:
                return ['success' => false, 'message' => 'Erreur inconnue lors de l\'upload.', 'path' => null];
        }

        // Vérifier la taille du fichier
        if ($file['size'] > $options['max_size']) {
            $maxMB = round($options['max_size'] / 1024 / 1024, 2);
            return ['success' => false, 'message' => "Le fichier dépasse la taille maximale de {$maxMB}MB.", 'path' => null];
        }

        // Vérifier l'extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $options['allowed_extensions'])) {
            return ['success' => false, 'message' => 'Type de fichier non autorisé.', 'path' => null];
        }

        // Vérifier le type MIME réel
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $options['allowed_mime_types'])) {
            return ['success' => false, 'message' => 'Type MIME non autorisé.', 'path' => null];
        }

        // Générer un nom de fichier sécurisé
        $filename = self::sanitizeFilename($file['name']);
        $uniqueName = uniqid() . '_' . $filename;

        // Créer le chemin de destination
        $uploadPath = BASE_PATH . '/' . $options['upload_path'];

        if ($options['create_subdirs']) {
            // Créer des sous-dossiers par année/mois
            $subdir = date('Y') . '/' . date('m');
            $uploadPath .= '/' . $subdir;

            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
        }

        $destination = $uploadPath . '/' . $uniqueName;

        // Vérifier si le fichier existe déjà (si overwrite = false)
        if (!$options['overwrite'] && file_exists($destination)) {
            return ['success' => false, 'message' => 'Un fichier avec ce nom existe déjà.', 'path' => null];
        }

        // Déplacer le fichier uploadé
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement du fichier.', 'path' => null];
        }

        // Définir les permissions appropriées
        chmod($destination, 0644);

        // Retourner le chemin relatif
        $relativePath = str_replace(BASE_PATH . '/', '', $destination);

        return [
            'success' => true,
            'message' => 'Fichier uploadé avec succès.',
            'path' => $relativePath
        ];
    }

    /**
     * Sanitize un nom de fichier
     *
     * @param string $filename
     * @return string
     */
    public static function sanitizeFilename($filename)
    {
        // Obtenir l'extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        // Supprimer les caractères dangereux
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);

        // Limiter la longueur
        $basename = substr($basename, 0, 50);

        return $basename . '.' . $extension;
    }

    /**
     * Valide une adresse email
     *
     * @param string $email
     * @return bool
     */
    public static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Valide un numéro de téléphone français
     *
     * @param string $phone
     * @return bool
     */
    public static function validatePhone($phone)
    {
        // Nettoyer le numéro
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Format français : 0X XX XX XX XX ou +33 X XX XX XX XX
        return preg_match('/^(0[1-9]|\\+33[1-9])[0-9]{8}$/', $phone);
    }

    /**
     * Sanitize une chaîne pour l'affichage HTML
     *
     * @param string $string
     * @return string
     */
    public static function sanitizeHtml($string)
    {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize une chaîne pour utilisation en SQL (en plus de prepared statements)
     *
     * @param string $string
     * @return string
     */
    public static function sanitizeString($string)
    {
        return trim(strip_tags($string));
    }

    /**
     * Valide un token CSRF
     *
     * @param string|null $token
     * @return bool
     */
    public static function validateCsrfToken($token = null)
    {
        // Si pas de token fourni, chercher dans POST
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? '';
        }

        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Génère un token CSRF
     *
     * @return string
     */
    public static function generateCsrfToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Génère un champ input caché pour le token CSRF
     *
     * @return string
     */
    public static function csrfField()
    {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    /**
     * Vérifie si une IP est en liste noire (pour protection brute force)
     *
     * @param string $ip
     * @param int $maxAttempts
     * @param int $timeWindow en secondes
     * @return array ['allowed' => bool, 'attempts' => int, 'wait_time' => int]
     */
    public static function checkRateLimit($ip, $maxAttempts = null, $timeWindow = null)
    {
        if ($maxAttempts === null) {
            $maxAttempts = Config::get('MAX_LOGIN_ATTEMPTS', 5);
        }
        if ($timeWindow === null) {
            $timeWindow = Config::get('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
        }

        // Utiliser la session pour stocker les tentatives
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = [];
        }

        $now = time();
        $attempts = $_SESSION['login_attempts'][$ip] ?? [];

        // Nettoyer les tentatives anciennes
        $attempts = array_filter($attempts, function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });

        $_SESSION['login_attempts'][$ip] = $attempts;

        $attemptsCount = count($attempts);

        if ($attemptsCount >= $maxAttempts) {
            $oldestAttempt = min($attempts);
            $waitTime = $timeWindow - ($now - $oldestAttempt);

            return [
                'allowed' => false,
                'attempts' => $attemptsCount,
                'wait_time' => max(0, $waitTime)
            ];
        }

        return [
            'allowed' => true,
            'attempts' => $attemptsCount,
            'wait_time' => 0
        ];
    }

    /**
     * Enregistre une tentative de connexion
     *
     * @param string $ip
     */
    public static function recordLoginAttempt($ip)
    {
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = [];
        }

        if (!isset($_SESSION['login_attempts'][$ip])) {
            $_SESSION['login_attempts'][$ip] = [];
        }

        $_SESSION['login_attempts'][$ip][] = time();
    }

    /**
     * Réinitialise les tentatives de connexion pour une IP
     *
     * @param string $ip
     */
    public static function resetLoginAttempts($ip)
    {
        if (isset($_SESSION['login_attempts'][$ip])) {
            unset($_SESSION['login_attempts'][$ip]);
        }
    }

    /**
     * Hash un mot de passe de manière sécurisée
     *
     * @param string $password
     * @return string
     */
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Vérifie un mot de passe
     *
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Génère un mot de passe aléatoire sécurisé
     *
     * @param int $length
     * @return string
     */
    public static function generatePassword($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }
}
