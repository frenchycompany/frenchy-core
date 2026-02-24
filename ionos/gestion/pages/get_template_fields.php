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
            $placeholders = explode(',', $template['placeholders']);
            $placeholdersList = implode("','", $placeholders);

            // Charger uniquement les champs correspondant aux placeholders
            $fieldsStmt = $conn->query("SELECT * FROM contract_fields WHERE field_name IN ('$placeholdersList')");
            $fields = $fieldsStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($fields as $field) {
                echo "<label for='{$field['field_name']}'>{$field['description']}:</label><br>";
                if ($field['input_type'] === 'textarea') {
                    echo "<textarea name='{$field['field_name']}' id='{$field['field_name']}' rows='4' cols='50' required></textarea><br><br>";
                } elseif ($field['input_type'] === 'select' && !empty($field['options'])) {
                    $options = explode(',', $field['options']);
                    echo "<select name='{$field['field_name']}' id='{$field['field_name']}' required>";
                    echo '<option value="">-- Choisissez une option --</option>';
                    foreach ($options as $option) {
                        echo "<option value='{$option}'>{$option}</option>";
                    }
                    echo "</select><br><br>";
                } else {
                    echo "<input type='{$field['input_type']}' name='{$field['field_name']}' id='{$field['field_name']}' required><br><br>";
                }
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
