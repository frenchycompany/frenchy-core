<?php
/**
 * Admin Auth — Login via table users (unifié avec gestion), CSRF, rate-limiting, session
 * Accepte aussi un token temporaire pour accès direct depuis gestion.
 */

session_start();

// ── CSRF Token ──
function vf_csrf_token() {
    if (empty($_SESSION['vf_csrf'])) {
        $_SESSION['vf_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['vf_csrf'];
}

function vf_csrf_field() {
    return '<input type="hidden" name="_csrf" value="' . vf_csrf_token() . '">';
}

function vf_csrf_verify() {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals(vf_csrf_token(), $token);
}

// ── Rate Limiting (session-based) ──
function vf_rate_limit_check() {
    $attempts = $_SESSION['vf_login_attempts'] ?? 0;
    $blocked_until = $_SESSION['vf_login_blocked'] ?? 0;

    if ($blocked_until > time()) {
        $remaining = $blocked_until - time();
        return "Trop de tentatives. Réessayez dans {$remaining} secondes.";
    }

    if ($blocked_until > 0 && $blocked_until <= time()) {
        $_SESSION['vf_login_attempts'] = 0;
        $_SESSION['vf_login_blocked'] = 0;
    }

    return null;
}

function vf_rate_limit_fail() {
    $_SESSION['vf_login_attempts'] = ($_SESSION['vf_login_attempts'] ?? 0) + 1;
    if ($_SESSION['vf_login_attempts'] >= 5) {
        $_SESSION['vf_login_blocked'] = time() + 900; // 15 minutes
    }
}

function vf_rate_limit_success() {
    $_SESSION['vf_login_attempts'] = 0;
    $_SESSION['vf_login_blocked'] = 0;
}

// ── Auth unifiée via table users (même DB que gestion) ──

/**
 * Authentifie un utilisateur via la table users (rôle admin requis).
 * Retourne le user array en cas de succès, false sinon.
 */
function vf_auth_login(PDO $conn, string $email, string $password) {
    $email = trim(strtolower($email));
    if (empty($email) || empty($password)) return false;

    try {
        $stmt = $conn->prepare(
            "SELECT id, email, password_hash, nom, prenom, role, actif
             FROM users
             WHERE (email = :email OR LOWER(CONCAT(prenom, ' ', nom)) = :email2)
             AND actif = 1"
        );
        $stmt->execute([':email' => $email, ':email2' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return false;

        // Vérifier le rôle admin (super_admin ou gestionnaire)
        if (!in_array($user['role'], ['super_admin', 'gestionnaire'])) return false;

        // Vérifier le mot de passe (argon2id ou bcrypt)
        if (!password_verify($password, $user['password_hash'])) return false;

        return $user;
    } catch (PDOException $e) {
        error_log('[VF Admin] Auth error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Vérifie un token temporaire de bridge depuis gestion.
 * Le token est à usage unique et expire après 5 minutes.
 */
function vf_auth_check_bridge_token(PDO $conn, string $token) {
    if (empty($token) || strlen($token) < 32) return false;

    try {
        // Créer la table si elle n'existe pas
        $conn->exec("CREATE TABLE IF NOT EXISTS admin_bridge_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            token VARCHAR(128) NOT NULL UNIQUE,
            user_id INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            used_at DATETIME DEFAULT NULL,
            INDEX idx_token (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $conn->prepare(
            "SELECT t.id AS token_id, t.user_id, u.email, u.nom, u.prenom, u.role
             FROM admin_bridge_tokens t
             JOIN users u ON u.id = t.user_id AND u.actif = 1
             WHERE t.token = :token
               AND t.used_at IS NULL
               AND t.created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
        );
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return false;
        if (!in_array($row['role'], ['super_admin', 'gestionnaire'])) return false;

        // Marquer le token comme utilisé (usage unique)
        $upd = $conn->prepare("UPDATE admin_bridge_tokens SET used_at = NOW() WHERE id = :id");
        $upd->execute([':id' => $row['token_id']]);

        // Nettoyer les vieux tokens (> 1 heure)
        $conn->exec("DELETE FROM admin_bridge_tokens WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");

        return $row;
    } catch (PDOException $e) {
        error_log('[VF Admin] Bridge token error: ' . $e->getMessage());
        return false;
    }
}
