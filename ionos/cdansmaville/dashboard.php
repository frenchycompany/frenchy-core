<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require 'db/connection.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

echo "<h1>Bienvenue sur votre tableau de bord</h1>";

if ($role === 'merchant') {
    echo "<a href='merchant_dashboard.php'>Espace Commerçant</a>";
} elseif ($role === 'visitor') {
    echo "<a href='visitor_dashboard.php'>Espace Visiteur</a>";
}
echo "<a href='logout.php'>Déconnexion</a>";
?>
