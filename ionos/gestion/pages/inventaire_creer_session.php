<?php
require_once '../db/connection.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Auto-migration : ajouter intervenant_id si absent
try { $conn->exec("ALTER TABLE sessions_inventaire ADD COLUMN intervenant_id INT DEFAULT NULL AFTER logement_id"); } catch (PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logement_id'])) {
    $logement_id = intval($_POST['logement_id']);
    $intervenant_id = !empty($_POST['intervenant_id']) ? intval($_POST['intervenant_id']) : null;
    $session_id = substr(md5(uniqid()), 0, 8);
    $stmt = $conn->prepare("INSERT INTO sessions_inventaire (id, logement_id, intervenant_id) VALUES (?, ?, ?)");
    $stmt->execute([$session_id, $logement_id, $intervenant_id]);

    header("Location: inventaire_saisie.php?session_id=" . $session_id);
    exit;
} else {
    echo "Erreur : logement non spécifié.";
}
