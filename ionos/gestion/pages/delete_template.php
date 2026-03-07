<?php
include '../config.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM contract_templates WHERE id = :id");
        $stmt->execute([':id' => (int)$_GET['id']]);
        header("Location: list_templates.php?deleted=1");
        exit;
    } catch (PDOException $e) {
        echo "Erreur : " . htmlspecialchars($e->getMessage());
    }
} else {
    header("Location: list_templates.php");
    exit;
}
