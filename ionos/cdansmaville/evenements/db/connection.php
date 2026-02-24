<?php
// db/connection.php
$host = 'db5016790207.hosting-data.io';
$db = 'dbs13572887';
$user = 'dbu1677919'; // Remplacez par votre nom d'utilisateur MySQL
$password = '**Baycpq25**'; // Remplacez par votre mot de passe MySQL

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


} catch (PDOException $e) {

    // Send a JSON error message! NOT plain text/HTML
    echo json_encode(['db_error' => 'Database connection failed: ' . $e->getMessage()]);
    exit(); // Terminate immediately after sending the error

}
?>