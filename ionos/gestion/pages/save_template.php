<?php
include '../config.php';
require_once __DIR__ . '/../includes/contract_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    $type = detectContractType();
    $config = getContractConfig($type);
    $table = $config['table_templates'];

    $template_id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : 0;
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';

    if (!$template_id || empty($title) || empty($content)) {
        header("Location: list_templates.php?type=$type");
        exit;
    }

    try {
        // Extraire les placeholders {{...}} du contenu
        preg_match_all('/\{\{(.*?)\}\}/', $content, $matches);
        $placeholders = implode(',', array_unique($matches[1]));

        $stmt = $conn->prepare("
            UPDATE `$table`
            SET title = :title, content = :content, placeholders = :placeholders, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $template_id,
            ':title' => $title,
            ':content' => $content,
            ':placeholders' => $placeholders
        ]);

        header("Location: list_templates.php?type=$type&saved=1");
        exit;
    } catch (PDOException $e) {
        error_log('save_template.php: ' . $e->getMessage());
        echo "Une erreur interne est survenue.";
    }
} else {
    $type = detectContractType();
    header("Location: list_templates.php?type=$type");
    exit;
}
