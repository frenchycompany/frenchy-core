<?php
/**
 * Sert le bookmarklet.js avec l'URL de l'API correctement configuree
 */
header('Content-Type: application/javascript');
header('Cache-Control: no-cache');

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$apiUrl = $protocol . '://' . $host . '/api/market_capture.php';

// Injecter l'URL au debut du script
echo "window.BOOKMARKLET_API_URL = '" . addslashes($apiUrl) . "';\n";

// Inclure le reste du script
readfile(__DIR__ . '/bookmarklet.js');
