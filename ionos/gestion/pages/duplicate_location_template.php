<?php
include '../config.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $stmt = $conn->prepare("SELECT title, content, placeholders FROM location_contract_templates WHERE id = :id");
        $stmt->execute([':id' => (int)$_GET['id']]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($template) {
            $stmt = $conn->prepare("INSERT INTO location_contract_templates (title, content, placeholders, created_at, updated_at) VALUES (:title, :content, :placeholders, NOW(), NOW())");
            $stmt->execute([
                ':title' => $template['title'] . ' (Copie)',
                ':content' => $template['content'],
                ':placeholders' => $template['placeholders'] ?? ''
            ]);
            header("Location: list_location_templates.php?duplicated=1");
            exit;
        }
    } catch (PDOException $e) {
        echo "Erreur : " . htmlspecialchars($e->getMessage());
    }
}

header("Location: list_location_templates.php");
exit;
