<?php
// db/connection.php
$host = 'db5016690401.hosting-data.io';
$db = 'dbs13515816';
$user = 'dbu275936'; // Remplacez par votre nom d'utilisateur MySQL
$password = '**Baycpq25**'; // Remplacez par votre mot de passe MySQL

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Erreur de connexion : " . $e->getMessage();
}
?>
