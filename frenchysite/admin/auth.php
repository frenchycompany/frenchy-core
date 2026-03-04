<?php
/**
 * Admin Auth — Login, CSRF, rate-limiting, session
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
        // Block expired, reset
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

// ── Password check (bcrypt ou plaintext) ──
function vf_check_password($input, $stored) {
    // Si le hash commence par $2y$ c'est du bcrypt
    if (strpos($stored, '$2y$') === 0) {
        return password_verify($input, $stored);
    }
    // Sinon comparaison directe (pour migration)
    return hash_equals($stored, $input);
}
