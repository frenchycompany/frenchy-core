<?php
include '../config.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        // Récupérer le modèle à dupliquer
        $stmt = $conn->prepare("SELECT title, content FROM contract_templates WHERE id = :id");
        $stmt->execute([':id' => $_GET['id']]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($template) {
            // Dupliquer le modèle avec un nouveau titre
            $new_title = $template['title'] . " (Copie)";
            $stmt = $conn->prepare("INSERT INTO contract_templates (title, content) VALUES (:title, :content)");
            $stmt->execute([
                ':title' => $new_title,
                ':content' => $template['content']
            ]);
            header("Location: list_templates.php");
            exit;
        } else {
            echo "Modèle introuvable.";
        }
    } catch (PDOException $e) {
        echo "Erreur : " . $e->getMessage();
    }
} else {
    echo "ID invalide.";
}
?>
