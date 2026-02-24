
<?php
session_start();
require_once 'config.php';
require_once '../db/connection.php';

// Vérifier si l'ID du client est passé en paramètre
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: clients.php");
    exit();
}

$id = intval($_GET['id']);

// Supprimer le client
$stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
$stmt->execute([$id]);

header("Location: clients.php");
exit();
?>
