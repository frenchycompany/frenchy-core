<?php
/**
 * Bridge de compatibilité Raspberry Pi → Gestion unifiée
 *
 * Ce fichier remplace les includes RPi (header_minimal, db, auth, csrf)
 * par les équivalents du système unifié.
 *
 * Usage dans les pages intégrées :
 *   include '../config.php';
 *   include '../pages/menu.php';
 *   require_once __DIR__ . '/../includes/rpi_bridge.php';
 *
 * Fournit : $conn, $pdo, les fonctions CSRF, les fonctions d'auth RPi
 */

// S'assurer que $conn et $pdo existent (déjà chargés par config.php)
if (!isset($conn) || !($conn instanceof PDO)) {
    die('Erreur : connexion BDD non disponible. Vérifiez config.php.');
}

// Alias $pdo = $conn pour compatibilité RPi (les pages RPi utilisent $pdo)
if (!isset($pdo)) {
    $pdo = $conn;
}

// Fonctions d'authentification RPi (compatibilité)
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['id_intervenant']);
    }
}

if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
        return $_SESSION['id_intervenant'] ?? null;
    }
}

if (!function_exists('getCurrentUserName')) {
    function getCurrentUserName() {
        return $_SESSION['nom_utilisateur'] ?? null;
    }
}

if (!function_exists('getCurrentUserEmail')) {
    function getCurrentUserEmail() {
        return $_SESSION['user_email'] ?? null;
    }
}

if (!function_exists('requireAuth')) {
    function requireAuth() {
        if (!isset($_SESSION['id_intervenant'])) {
            header('Location: /login.php');
            exit;
        }
    }
}

if (!function_exists('checkSessionTimeout')) {
    function checkSessionTimeout($timeout = 1800) {
        return true; // Géré par le système de session principal
    }
}
