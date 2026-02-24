<?php
// Vérifier si une session est déjà active avant de l'initialiser
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../db/connection.php';
?>
