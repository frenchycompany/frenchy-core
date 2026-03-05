<?php
/**
 * Redirige vers todo.php (page unifiée)
 */
$params = $_GET ? '?' . http_build_query($_GET) : '';
header('Location: todo.php' . $params);
exit;
