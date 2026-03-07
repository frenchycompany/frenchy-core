<?php
include '../config.php'; // Inclut la configuration de la base de données

// Mapping champ contrat -> source auto-fill depuis donnees logement/proprietaire
$autofillMap = [
    'client_name'          => 'proprietaire_fullname',
    'nom_du_logement'      => 'nom_du_logement',
    'percentage_fee'       => 'proprietaire_commission',
    'prix_vente_menage'    => 'prix_vente_menage',
    'location'             => 'ville',
    'owner_address'        => 'proprietaire_adresse',
    'property_description' => 'description_logement',
    'contract_date'        => 'date_contrat',
    'client_email'         => 'proprietaire_email',
    'client_phone'         => 'proprietaire_telephone',
    'client_societe'       => 'proprietaire_societe',
    'client_siret'         => 'proprietaire_siret',
    'iban'                 => 'proprietaire_rib_iban',
    'bic'                  => 'proprietaire_rib_bic',
    'banque'               => 'proprietaire_rib_banque',
    'adresse'              => 'adresse',
    'adresse_logement'     => 'adresse',
    'ville'                => 'ville',
    'code_postal'          => 'code_postal',
    'capacite'             => 'capacite',
    'type_logement'        => 'type_logement',
];

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
                $autofill = isset($autofillMap[$field['field_name']]) ? " data-autofill='" . htmlspecialchars($autofillMap[$field['field_name']]) . "'" : '';

                echo "<div class='mb-3'>";
                echo "<label for='{$fieldName}' class='form-label'>{$description} :</label>";
                if ($field['input_type'] === 'textarea') {
                    echo "<textarea name='{$fieldName}' id='{$fieldName}' class='form-control' rows='4' required{$autofill}></textarea>";
                } elseif ($field['input_type'] === 'select' && !empty($field['options'])) {
                    $options = explode(',', $field['options']);
                    echo "<select name='{$fieldName}' id='{$fieldName}' class='form-select' required{$autofill}>";
                    echo '<option value="">-- Choisissez une option --</option>';
                    foreach ($options as $option) {
                        $opt = htmlspecialchars(trim($option));
                        echo "<option value='{$opt}'>{$opt}</option>";
                    }
                    echo "</select>";
                } else {
                    echo "<input type='{$field['input_type']}' name='{$fieldName}' id='{$fieldName}' class='form-control' required{$autofill}>";
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
