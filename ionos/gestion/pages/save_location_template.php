<?php
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    $template_id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : 0;
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';

    if (!$template_id || empty($title) || empty($content)) {
        header("Location: list_location_templates.php");
        exit;
    }

    try {
        preg_match_all('/\{\{(.*?)\}\}/', $content, $matches);
        $placeholders = implode(',', array_unique($matches[1]));

        $stmt = $conn->prepare("
            UPDATE location_contract_templates
            SET title = :title, content = :content, placeholders = :placeholders, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $template_id,
            ':title' => $title,
            ':content' => $content,
            ':placeholders' => $placeholders
        ]);

        header("Location: list_location_templates.php?saved=1");
        exit;
    } catch (PDOException $e) {
        echo "Erreur : " . htmlspecialchars($e->getMessage());
    }
} else {
    header("Location: list_location_templates.php");
    exit;
}
