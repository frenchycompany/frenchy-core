<?php
ini_set('display_errors','1');
error_reporting(E_ALL);

include '../config.php';
require_once __DIR__ . '/../includes/rpi_db.php';
$pdo = getRpiPdo();
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require __DIR__ . '/../../vendor/autoload.php';
}

echo "OK PDO";
$stmt = $pdo->query("SELECT DATABASE() as db");
$row = $stmt->fetch();
echo "<pre>"; var_dump($row); echo "</pre>";
