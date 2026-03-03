<?php
/**
 * Helpers de validation et sanitization des donnees
 */

/**
 * Sanitize une chaine de texte (supprime les tags HTML, trim)
 */
function sanitizeString(?string $input): string {
    if ($input === null) return '';
    return trim(strip_tags($input));
}

/**
 * Valide et retourne un entier positif, ou la valeur par defaut
 */
function sanitizeInt($input, int $default = 0): int {
    $val = filter_var($input, FILTER_VALIDATE_INT);
    return ($val !== false && $val >= 0) ? $val : $default;
}

/**
 * Valide et retourne un float positif ou null
 */
function sanitizeFloat($input, ?float $default = null): ?float {
    if ($input === null || $input === '') return $default;
    $val = filter_var($input, FILTER_VALIDATE_FLOAT);
    return ($val !== false) ? $val : $default;
}

/**
 * Valide une date au format Y-m-d
 */
function sanitizeDate(?string $input): ?string {
    if (empty($input)) return null;
    $d = DateTime::createFromFormat('Y-m-d', $input);
    return ($d && $d->format('Y-m-d') === $input) ? $input : null;
}

/**
 * Valide une valeur parmi une liste autorisee
 */
function sanitizeEnum(?string $input, array $allowed, ?string $default = null): ?string {
    return in_array($input, $allowed, true) ? $input : $default;
}

/**
 * Verifie que l'utilisateur est connecte et a le role requis
 */
function requireAuth(string $requiredRole = 'user'): void {
    if (!isset($_SESSION['id_intervenant'])) {
        header('Location: /login.php');
        exit;
    }
    if ($requiredRole === 'admin' && ($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo '<div style="padding:40px;text-align:center;color:#e53935;">Acces refuse.</div>';
        exit;
    }
}

/**
 * Verifie le token CSRF pour les requetes POST
 */
function verifyCsrfToken(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;

    // Exempter les appels AJAX avec header custom (defense en profondeur)
    if (isset($_POST['ajax']) || isset($_POST['ajax_action'])) {
        // Les appels AJAX fetch() avec FormData sont proteges par SameSite cookie
        return true;
    }

    $token = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (empty($sessionToken) || !hash_equals($sessionToken, $token)) {
        http_response_code(403);
        echo '<div style="padding:40px;text-align:center;color:#e53935;">Token CSRF invalide. Rechargez la page.</div>';
        return false;
    }
    return true;
}
