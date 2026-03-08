<?php
/**
 * Deconnexion - Espace Proprietaire
 * Utilise Auth.php unifie
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/Auth.php';

$auth = new Auth($conn);
$auth->logout();

header('Location: login.php');
exit;
