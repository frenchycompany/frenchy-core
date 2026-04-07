<?php
include '../config.php'; // Inclut la configuration de la base de données

// Auto-migration : ajouter les champs adresse structuree si absents
try {
    $existing = $conn->query("SELECT field_name FROM contract_fields WHERE field_name = 'client_adresse'")->fetch();
    if (!$existing) {
        $ins = $conn->prepare("INSERT IGNORE INTO contract_fields (field_name, description, input_type) VALUES (?,?,?)");
        $newFields = [
            ['client_adresse', 'Adresse du proprietaire (ligne 1)', 'text'],
            ['client_adresse_ligne2', 'Adresse du proprietaire (ligne 2)', 'text'],
            ['client_code_postal', 'Code postal du proprietaire', 'text'],
            ['client_ville', 'Ville du proprietaire', 'text'],
            ['client_adresse_complete', 'Adresse complete du proprietaire', 'text'],
            ['adresse_ligne2', 'Adresse du logement (ligne 2)', 'text'],
            ['adresse_complete', 'Adresse complete du logement', 'text'],
        ];
        foreach ($newFields as $f) { $ins->execute($f); }
    }
} catch (PDOException $e) { error_log('contract_fields migration: ' . $e->getMessage()); }

// Mapping champ contrat -> source auto-fill depuis donnees logement/proprietaire
$autofillMap = [
    'client_name'          => 'proprietaire_fullname',
    'nom_du_logement'      => 'nom_du_logement',
    'percentage_fee'       => 'proprietaire_commission',
    'prix_vente_menage'    => 'prix_vente_menage',
    'location'             => 'ville',
    'owner_address'        => 'proprietaire_adresse_complete',
    'property_description' => 'description_logement',
    'contract_date'        => 'date_contrat',
    'client_email'         => 'proprietaire_email',
    'client_phone'         => 'proprietaire_telephone',
    'client_societe'       => 'proprietaire_societe',
    'client_siret'         => 'proprietaire_siret',
    'client_adresse'       => 'proprietaire_adresse',
    'client_adresse_ligne2'=> 'proprietaire_adresse_ligne2',
    'client_code_postal'   => 'proprietaire_code_postal',
    'client_ville'         => 'proprietaire_ville',
    'client_adresse_complete' => 'proprietaire_adresse_complete',
    'iban'                 => 'proprietaire_rib_iban',
    'bic'                  => 'proprietaire_rib_bic',
    'banque'               => 'proprietaire_rib_banque',
    'adresse'              => 'adresse',
    'adresse_logement'     => 'adresse',
    'adresse_ligne2'       => 'adresse_ligne2',
    'adresse_complete'     => 'adresse_complete',
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
        error_log('get_template_fields.php: ' . $e->getMessage());
        echo '<div class="alert alert-danger">Erreur lors du chargement des champs.</div>';
    }
} else {
    echo "ID du modèle non valide.";
}
