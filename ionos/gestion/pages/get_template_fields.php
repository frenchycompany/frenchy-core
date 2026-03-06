<?php
include '../config.php'; // Inclut la configuration de la base de données

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $template_id = (int)$_GET['id'];

    try {
        // Récupérer les balises dynamiques du modèle
        $stmt = $conn->prepare("SELECT placeholders FROM contract_templates WHERE id = :id");
        $stmt->execute([':id' => $template_id]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($template && !empty($template['placeholders'])) {
            $placeholders = array_map('trim', explode(',', $template['placeholders']));

            // Charger les champs avec des paramètres préparés
            $inClause = implode(',', array_fill(0, count($placeholders), '?'));
            $fieldsStmt = $conn->prepare("SELECT * FROM contract_fields WHERE field_name IN ($inClause)");
            $fieldsStmt->execute($placeholders);
            $fields = $fieldsStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($fields as $field) {
                $fieldName = htmlspecialchars($field['field_name']);
                $description = htmlspecialchars($field['description']);

                echo "<div class='mb-3'>";
                echo "<label for='{$fieldName}' class='form-label'>{$description} :</label>";
                if ($field['input_type'] === 'textarea') {
                    echo "<textarea name='{$fieldName}' id='{$fieldName}' class='form-control' rows='4' required></textarea>";
                } elseif ($field['input_type'] === 'select' && !empty($field['options'])) {
                    $options = explode(',', $field['options']);
                    echo "<select name='{$fieldName}' id='{$fieldName}' class='form-select' required>";
                    echo '<option value="">-- Choisissez une option --</option>';
                    foreach ($options as $option) {
                        $opt = htmlspecialchars(trim($option));
                        echo "<option value='{$opt}'>{$opt}</option>";
                    }
                    echo "</select>";
                } else {
                    echo "<input type='{$field['input_type']}' name='{$fieldName}' id='{$fieldName}' class='form-control' required>";
                }
                echo "</div>";
            }
        } else {
            echo "Aucun champ dynamique disponible.";
        }
    } catch (PDOException $e) {
        echo "Erreur : " . $e->getMessage();
    }
} else {
    echo "ID du modèle non valide.";
}
