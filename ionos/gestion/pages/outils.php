<?php
ini_set('display_errors','1');
error_reporting(E_ALL);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../includes/db.php'; // fournit $pdo (PDO)

if (!empty($db_error)) die("DB_ERROR: ".$db_error);
if (empty($pdo)) die("PDO VIDE");

echo "OK PDO";
$stmt = $pdo->query("SELECT DATABASE() as db");
$row = $stmt->fetch();
echo "<pre>"; var_dump($row); echo "</pre>";
