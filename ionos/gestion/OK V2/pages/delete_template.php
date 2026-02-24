<?php
include '../config.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM contract_templates WHERE id = :id");
        $stmt->execute([':id' => $_GET['id']]);
        header("Location: list_templates.php");
        exit;
    } catch (PDOException $e) {
        echo "Erreur : " . $e->getMessage();
    }
} else {
    echo "ID invalide.";
}
?>
