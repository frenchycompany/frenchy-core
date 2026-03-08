<?php
include '../config.php';
require_once __DIR__ . '/../includes/contract_config.php';

$type = detectContractType();
$config = getContractConfig($type);
$table = $config['table_templates'];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $stmt = $conn->prepare("SELECT title, content, placeholders FROM `$table` WHERE id = :id");
        $stmt->execute([':id' => (int)$_GET['id']]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($template) {
            $stmt = $conn->prepare("INSERT INTO `$table` (title, content, placeholders, created_at, updated_at) VALUES (:title, :content, :placeholders, NOW(), NOW())");
            $stmt->execute([
                ':title' => $template['title'] . ' (Copie)',
                ':content' => $template['content'],
                ':placeholders' => $template['placeholders'] ?? ''
            ]);
            header("Location: list_templates.php?type=$type&duplicated=1");
            exit;
        }
    } catch (PDOException $e) {
        error_log('duplicate_template.php: ' . $e->getMessage());
        echo "Une erreur interne est survenue.";
    }
}

header("Location: list_templates.php?type=$type");
exit;
