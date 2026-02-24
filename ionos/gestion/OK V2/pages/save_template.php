<?php
include '../config.php'; // Inclut la configuration de la base de données

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        echo "ID du modèle non valide.";
        exit;
    }

    $template_id = (int)$_POST['id'];
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';

    if (empty($title) || empty($content)) {
        echo "Titre ou contenu manquant.";
        exit;
    }

    try {
        // Extraire les balises dynamiques {{...}} du contenu
        preg_match_all('/{{(.*?)}}/', $content, $matches);
        $placeholders = implode(',', array_unique($matches[1])); // Liste séparée par des virgules

        // Mettre à jour le modèle dans la base de données
        $stmt = $conn->prepare("
            UPDATE contract_templates 
            SET title = :title, content = :content, placeholders = :placeholders, updated_at = NOW() 
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $template_id,
            ':title' => htmlspecialchars($title),
            ':content' => $content,
            ':placeholders' => $placeholders
        ]);

        // Rediriger vers la liste des modèles
        header("Location: list_templates.php");
        exit;
    } catch (PDOException $e) {
        echo "Erreur : " . $e->getMessage();
    }
} else {
    echo "Méthode non autorisée.";
}
