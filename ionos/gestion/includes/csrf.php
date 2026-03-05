<?php
/**
 * Protection CSRF (Cross-Site Request Forgery)
 * Fonctions utilitaires pour la gestion des tokens CSRF
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function getCsrfToken() {
    return $_SESSION['csrf_token'] ?? generateCsrfToken();
}

function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function validateCsrfToken() {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        die('Erreur CSRF : Token invalide. Veuillez réessayer.');
    }
}

function csrfField() {
    $token = getCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

function echoCsrfField() {
    echo csrfField();
}
