<?php
require_once '../db/connection.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logement_id'])) {
    $logement_id = intval($_POST['logement_id']);
    $session_id = substr(md5(uniqid()), 0, 8);
    $stmt = $conn->prepare("INSERT INTO sessions_inventaire (id, logement_id) VALUES (?, ?)");
    $stmt->execute([$session_id, $logement_id]);

    header("Location: inventaire_saisie.php?session_id=" . $session_id);
    exit;
} else {
    echo "Erreur : logement non spécifié.";
}
