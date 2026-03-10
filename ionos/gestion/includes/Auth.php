<?php
/**
 * Auth — Système d'authentification unifié FrenchyConciergerie
 *
 * Remplace les 4 systèmes précédents :
 * - ionos/gestion/login.php (staff via table intervenant)
 * - ionos/gestion/proprietaire/login.php (proprios via FC_proprietaires)
 * - ionos/admin/index.php (admin hardcodé)
 * - ionos/includes/security.php (classe Security pour proprios)
 *
 * Rôles disponibles : super_admin, gestionnaire, femme_de_menage, proprietaire, voyageur
 */

class Auth
{
    private PDO $conn;

    // Rate limiting
    private int $maxLoginAttempts = 5;
    private int $lockoutDuration = 900; // 15 minutes

    // Session
    private int $sessionTimeout;
    private int $sessionRegenerateInterval = 300; // 5 minutes

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->sessionTimeout = (int) env('SESSION_TIMEOUT', 1800);

        // Pas de session en CLI (migrations, scripts)
        if (php_sapi_name() !== 'cli') {
            $this->ensureSession();
        }
    }

    // ========================================================
    // SESSION MANAGEMENT
    // ========================================================

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => env('APP_ENV') === 'production',
                'httponly'  => true,
                'samesite'  => 'Strict',
            ]);
            session_start();
        }

        // Régénération périodique de l'ID de session
        if (!isset($_SESSION['_auth_last_regen'])) {
            $_SESSION['_auth_last_regen'] = time();
        } elseif (time() - $_SESSION['_auth_last_regen'] > $this->sessionRegenerateInterval) {
            session_regenerate_id(true);
            $_SESSION['_auth_last_regen'] = time();
        }

        // Vérification du fingerprint (anti-hijacking)
        $fp = $this->fingerprint();
        if (!isset($_SESSION['_auth_fingerprint'])) {
            $_SESSION['_auth_fingerprint'] = $fp;
        } elseif (!hash_equals($_SESSION['_auth_fingerprint'], $fp)) {
            $this->destroySession();
        }

        // Timeout de session
        if (isset($_SESSION['_auth_last_activity'])) {
            if (time() - $_SESSION['_auth_last_activity'] > $this->sessionTimeout) {
                $this->destroySession();
                return;
            }
        }
        $_SESSION['_auth_last_activity'] = time();
    }

    private function fingerprint(): string
    {
        return hash('sha256',
            ($_SERVER['HTTP_USER_AGENT'] ?? '') .
            ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
        );
    }

    private function destroySession(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        session_start(); // Redémarre une session vierge
    }

    // ========================================================
    // AUTHENTICATION
    // ========================================================

    /**
     * Tente de connecter un utilisateur.
     * Retourne ['success' => true, 'user' => [...]] ou ['success' => false, 'error' => '...']
     */
    public function login(string $email, string $password): array
    {
        $email = trim(strtolower($email));
        $ip = $this->getClientIP();

        // Rate limiting
        if ($this->isLockedOut($ip)) {
            return [
                'success' => false,
                'error' => 'Trop de tentatives de connexion. Réessayez dans quelques minutes.'
            ];
        }

        if (empty($email) || empty($password)) {
            return ['success' => false, 'error' => 'Veuillez remplir tous les champs.'];
        }

        // Recherche de l'utilisateur par email ou nom_utilisateur (intervenant)
        $stmt = $this->conn->prepare(
            "SELECT u.id, u.email, u.password_hash, u.nom, u.prenom, u.role, u.actif,
                    u.numero, u.role1, u.role2, u.role3,
                    u.legacy_intervenant_id, u.legacy_proprietaire_id
             FROM users u
             LEFT JOIN intervenant i ON u.legacy_intervenant_id = i.id
             WHERE u.email = ? OR LOWER(i.nom_utilisateur) = ?"
        );
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->recordFailedAttempt($ip, $email);
            return ['success' => false, 'error' => 'Email ou mot de passe incorrect.'];
        }

        if (!$user['actif']) {
            return ['success' => false, 'error' => 'Ce compte est désactivé. Contactez l\'administrateur.'];
        }

        // Succès — nettoyer les tentatives et créer la session
        $this->clearAttempts($ip);
        $this->createSession($user);

        // Mettre à jour la dernière connexion
        $this->conn->prepare("UPDATE users SET derniere_connexion = NOW() WHERE id = ?")
            ->execute([$user['id']]);

        // Rehash si nécessaire (migration bcrypt → argon2id)
        if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3
        ])) {
            $newHash = $this->hashPassword($password);
            $this->conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                ->execute([$newHash, $user['id']]);
        }

        return ['success' => true, 'user' => $user];
    }

    /**
     * Crée la session utilisateur après login réussi.
     */
    private function createSession(array $user): void
    {
        session_regenerate_id(true);

        $_SESSION['user_id']     = $user['id'];
        $_SESSION['user_email']  = $user['email'];
        $_SESSION['user_nom']    = $user['nom'];
        $_SESSION['user_prenom'] = $user['prenom'] ?? '';
        $_SESSION['user_role']   = $user['role'];

        // Compatibilité avec l'ancien système staff
        if (in_array($user['role'], ['super_admin', 'gestionnaire', 'femme_de_menage'])) {
            $_SESSION['id_intervenant'] = $user['legacy_intervenant_id'] ?? $user['id'];
            $_SESSION['nom_utilisateur'] = $user['nom'];
            $_SESSION['role'] = ($user['role'] === 'femme_de_menage') ? 'user' : 'admin';
        }

        // Compatibilité avec l'ancien système propriétaire
        if ($user['role'] === 'proprietaire') {
            $_SESSION['proprietaire_id'] = $user['legacy_proprietaire_id'] ?? $user['id'];
            $_SESSION['proprietaire_nom'] = $user['nom'];
        }

        // CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $_SESSION['_auth_last_activity'] = time();
        $_SESSION['_auth_last_regen'] = time();
        $_SESSION['_auth_fingerprint'] = $this->fingerprint();
    }

    /**
     * Déconnecte l'utilisateur.
     */
    public function logout(): void
    {
        $this->destroySession();
    }

    // ========================================================
    // SESSION CHECKS (middleware)
    // ========================================================

    /**
     * Vérifie si un utilisateur est connecté. Retourne le user_id ou null.
     */
    public function check(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Retourne l'utilisateur courant depuis la DB, ou null.
     */
    public function user(): ?array
    {
        $id = $this->check();
        if (!$id) return null;

        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = ? AND actif = 1");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->destroySession();
            return null;
        }

        return $user;
    }

    /**
     * Retourne le rôle de l'utilisateur courant.
     */
    public function role(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Vérifie si l'utilisateur courant est admin (gestionnaire ou super_admin).
     */
    public function isAdmin(): bool
    {
        return in_array($this->role(), ['gestionnaire', 'super_admin']);
    }

    /**
     * Vérifie si l'utilisateur courant est staff (femme_de_menage, gestionnaire, super_admin).
     */
    public function isStaff(): bool
    {
        return in_array($this->role(), ['femme_de_menage', 'gestionnaire', 'super_admin']);
    }

    /**
     * Vérifie si l'utilisateur courant est propriétaire.
     */
    public function isProprietaire(): bool
    {
        return $this->role() === 'proprietaire';
    }

    /**
     * Vérifie si l'utilisateur courant est voyageur.
     */
    public function isVoyageur(): bool
    {
        return $this->role() === 'voyageur';
    }

    /**
     * Vérifie si l'utilisateur a le rôle spécifié (ou un rôle supérieur).
     */
    public function hasRole(string $role): bool
    {
        $hierarchy = [
            'super_admin' => 5,
            'gestionnaire' => 4,
            'femme_de_menage' => 3,
            'proprietaire' => 2,
            'voyageur' => 1,
        ];

        $userLevel = $hierarchy[$this->role()] ?? 0;
        $requiredLevel = $hierarchy[$role] ?? 99;

        // Pour les rôles internes, la hiérarchie s'applique
        if (in_array($role, ['femme_de_menage', 'gestionnaire', 'super_admin'])) {
            return $userLevel >= $requiredLevel;
        }

        // Pour les rôles proprio/voyageur, on vérifie l'exact match ou si c'est un admin
        return $this->role() === $role || $this->isAdmin();
    }

    // ========================================================
    // GUARDS (redirections)
    // ========================================================

    /**
     * Redirige vers login si pas connecté.
     */
    public function requireAuth(string $redirectTo = 'login.php'): void
    {
        if (!$this->check()) {
            header("Location: $redirectTo");
            exit;
        }
    }

    /**
     * Redirige vers login si pas staff (staff/admin/super_admin).
     */
    public function requireStaff(string $redirectTo = 'login.php'): void
    {
        $this->requireAuth($redirectTo);
        if (!$this->isStaff()) {
            http_response_code(403);
            die('Accès réservé au personnel.');
        }
    }

    /**
     * Redirige si pas admin.
     */
    public function requireAdmin(string $redirectTo = 'login.php'): void
    {
        $this->requireAuth($redirectTo);
        if (!$this->isAdmin()) {
            http_response_code(403);
            die('Accès réservé aux administrateurs.');
        }
    }

    /**
     * Redirige si pas propriétaire.
     */
    public function requireProprietaire(string $redirectTo = 'login.php'): void
    {
        $this->requireAuth($redirectTo);
        if (!$this->isProprietaire() && !$this->isAdmin()) {
            http_response_code(403);
            die('Accès réservé aux propriétaires.');
        }
    }

    /**
     * Retourne l'URL de redirection post-login selon le rôle.
     */
    public function getRedirectUrl(): string
    {
        $role = $this->role();
        if (in_array($role, ['super_admin', 'gestionnaire', 'femme_de_menage'])) {
            return 'index.php';
        }
        if ($role === 'proprietaire') {
            return 'proprietaire/index.php';
        }
        if ($role === 'voyageur') {
            return 'voyageur/index.php';
        }
        return 'login.php';
    }

    // ========================================================
    // PAGE PERMISSIONS (staff)
    // ========================================================

    /**
     * Vérifie si le staff a accès à une page spécifique.
     */
    public function canAccessPage(int $pageId): bool
    {
        if ($this->isAdmin()) return true;

        $userId = $this->check();
        if (!$userId) return false;

        $stmt = $this->conn->prepare(
            "SELECT 1 FROM user_permissions WHERE user_id = ? AND page_id = ?"
        );
        $stmt->execute([$userId, $pageId]);
        return (bool) $stmt->fetch();
    }

    /**
     * Retourne la liste des pages accessibles par le staff courant.
     */
    public function getAccessiblePages(): array
    {
        $userId = $this->check();
        if (!$userId) return [];

        if ($this->isAdmin()) {
            $stmt = $this->conn->query("SELECT id, nom, chemin FROM pages WHERE afficher_menu = 1");
        } else {
            $stmt = $this->conn->prepare(
                "SELECT p.id, p.nom, p.chemin
                 FROM pages p
                 INNER JOIN user_permissions up ON p.id = up.page_id
                 WHERE up.user_id = ? AND p.afficher_menu = 1"
            );
            $stmt->execute([$userId]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========================================================
    // RATE LIMITING
    // ========================================================

    private function isLockedOut(string $ip): bool
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT COUNT(*) FROM auth_rate_limit
                 WHERE ip_address = ? AND action = 'login'
                   AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)"
            );
            $stmt->execute([$ip, $this->lockoutDuration]);
            return (int) $stmt->fetchColumn() >= $this->maxLoginAttempts;
        } catch (PDOException $e) {
            // Table pas encore créée — on laisse passer
            $this->ensureRateLimitTable();
            return false;
        }
    }

    private function recordFailedAttempt(string $ip, string $email): void
    {
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO auth_rate_limit (ip_address, action, identifier) VALUES (?, 'login', ?)"
            );
            $stmt->execute([$ip, $email]);
        } catch (PDOException $e) {
            // Table pas encore créée — ignorer silencieusement
        }
    }

    private function clearAttempts(string $ip): void
    {
        try {
            $stmt = $this->conn->prepare(
                "DELETE FROM auth_rate_limit WHERE ip_address = ? AND action = 'login'"
            );
            $stmt->execute([$ip]);
        } catch (PDOException $e) {
            // Table pas encore créée — ignorer silencieusement
        }
    }

    /**
     * Crée la table auth_rate_limit si elle n'existe pas.
     */
    private function ensureRateLimitTable(): void
    {
        try {
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS `auth_rate_limit` (
                    `id` INT NOT NULL AUTO_INCREMENT,
                    `ip_address` VARCHAR(45) NOT NULL,
                    `action` VARCHAR(50) NOT NULL DEFAULT 'login',
                    `identifier` VARCHAR(255) DEFAULT NULL,
                    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_ip_action` (`ip_address`, `action`, `attempted_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (PDOException $e) {
            error_log("Auth: impossible de créer auth_rate_limit: " . $e->getMessage());
        }
    }

    // ========================================================
    // CSRF
    // ========================================================

    public function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($this->csrfToken()) . '">';
    }

    public function validateCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public function requireCsrf(): void
    {
        if (!$this->validateCsrf()) {
            http_response_code(403);
            die('Erreur CSRF : Token invalide. Veuillez réessayer.');
        }
    }

    // ========================================================
    // PASSWORD
    // ========================================================

    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 3,
        ]);
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    // ========================================================
    // PASSWORD RESET
    // ========================================================

    /**
     * Génère un token de reset et le stocke.
     * Retourne le token ou null si l'email n'existe pas.
     */
    public function createResetToken(string $email): ?string
    {
        $email = trim(strtolower($email));

        $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ? AND actif = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) return null;

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 heure

        $stmt = $this->conn->prepare(
            "UPDATE users SET token_reset = ?, token_reset_expire = ? WHERE id = ?"
        );
        $stmt->execute([$token, $expires, $user['id']]);

        return $token;
    }

    /**
     * Valide un token de reset et retourne le user_id, ou null.
     */
    public function validateResetToken(string $token): ?int
    {
        $stmt = $this->conn->prepare(
            "SELECT id FROM users WHERE token_reset = ? AND token_reset_expire > NOW() AND actif = 1"
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        return $user ? (int) $user['id'] : null;
    }

    /**
     * Réinitialise le mot de passe avec un token valide.
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $userId = $this->validateResetToken($token);
        if (!$userId) return false;

        $hash = $this->hashPassword($newPassword);
        $stmt = $this->conn->prepare(
            "UPDATE users SET password_hash = ?, token_reset = NULL, token_reset_expire = NULL WHERE id = ?"
        );
        $stmt->execute([$hash, $userId]);

        return true;
    }

    // ========================================================
    // USER MANAGEMENT
    // ========================================================

    /**
     * Crée un nouvel utilisateur.
     */
    public function createUser(array $data): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO users (email, password_hash, nom, prenom, telephone, adresse, role,
                                numero, role1, role2, role3,
                                societe, siret, rib_iban, rib_bic, rib_banque,
                                commission, notes_admin, actif)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            trim(strtolower($data['email'])),
            $this->hashPassword($data['password']),
            $data['nom'],
            $data['prenom'] ?? null,
            $data['telephone'] ?? null,
            $data['adresse'] ?? null,
            $data['role'] ?? 'femme_de_menage',
            $data['numero'] ?? null,
            $data['role1'] ?? null,
            $data['role2'] ?? null,
            $data['role3'] ?? null,
            $data['societe'] ?? null,
            $data['siret'] ?? null,
            $data['rib_iban'] ?? null,
            $data['rib_bic'] ?? null,
            $data['rib_banque'] ?? null,
            $data['commission'] ?? null,
            $data['notes_admin'] ?? null,
            $data['actif'] ?? 1,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    /**
     * Met à jour un utilisateur (sans toucher au mot de passe sauf si fourni).
     */
    public function updateUser(int $userId, array $data): void
    {
        $fields = [];
        $values = [];

        $allowed = [
            'email', 'nom', 'prenom', 'telephone', 'adresse', 'photo', 'role',
            'numero', 'role1', 'role2', 'role3',
            'societe', 'siret', 'rib_iban', 'rib_bic', 'rib_banque',
            'commission', 'notes_admin', 'actif',
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`$field` = ?";
                $values[] = ($field === 'email') ? trim(strtolower($data[$field])) : $data[$field];
            }
        }

        if (!empty($data['password'])) {
            $fields[] = "`password_hash` = ?";
            $values[] = $this->hashPassword($data['password']);
        }

        if (empty($fields)) return;

        $values[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $this->conn->prepare($sql)->execute($values);
    }

    // ========================================================
    // UTILITIES
    // ========================================================

    public function getClientIP(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $header) {
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
     * Nettoie les données expirées.
     */
    public function cleanup(): void
    {
        $this->conn->exec(
            "DELETE FROM auth_rate_limit WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
    }
}
