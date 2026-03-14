<?php
/**
 * AJAX endpoint - Charge les champs dynamiques d'un modele de contrat de location
 */
include '../config.php';

// Mapping champ contrat -> source auto-fill depuis donnees logement
$autofillMap = [
    'nom_du_logement'      => 'nom_du_logement',
    'adresse_logement'     => 'adresse',
    'ville'                => 'ville',
    'code_postal'          => 'code_postal',
    'type_logement'        => 'type_logement',
    'capacite'             => 'capacite',
    'surface_m2'           => 'm2',
    'description_logement' => 'detail_description_logement',
    'equipements'          => 'detail_equipements',
    'regles_maison'        => 'detail_regles_maison',
    'heure_arrivee'        => 'detail_heure_arrivee',
    'heure_depart'         => 'detail_heure_depart',
    'depot_garantie'       => 'detail_depot_garantie',
    'conditions_annulation'=> 'detail_conditions_annulation',
    'date_contrat'         => 'date_contrat',
];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $template_id = (int)$_GET['id'];

    try {
        $stmt = $conn->prepare("SELECT placeholders FROM location_contract_templates WHERE id = :id");
        $stmt->execute([':id' => $template_id]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($template && !empty($template['placeholders'])) {
            $placeholders = array_map('trim', explode(',', $template['placeholders']));

            // Charger les champs definis
            $inClause = implode(',', array_fill(0, count($placeholders), '?'));
            $fieldsStmt = $conn->prepare("SELECT * FROM location_contract_fields WHERE field_name IN ($inClause) ORDER BY sort_order, field_name");
            $fieldsStmt->execute($placeholders);
            $definedFields = $fieldsStmt->fetchAll(PDO::FETCH_ASSOC);

            // Indexer par field_name
            $definedByName = [];
            foreach ($definedFields as $f) {
                $definedByName[$f['field_name']] = $f;
            }

            // Grouper les champs
            $groups = ['voyageur' => [], 'reservation' => [], 'logement' => [], 'autre' => []];
            $groupLabels = ['voyageur' => 'Voyageur', 'reservation' => 'Reservation', 'logement' => 'Logement', 'autre' => 'Autre'];
            $groupIcons = ['voyageur' => 'fa-user', 'reservation' => 'fa-calendar-alt', 'logement' => 'fa-home', 'autre' => 'fa-info-circle'];

            foreach ($placeholders as $ph) {
                if (isset($definedByName[$ph])) {
                    $group = $definedByName[$ph]['field_group'] ?? 'autre';
                    $groups[$group][] = $definedByName[$ph];
                } else {
                    // Champ non defini en BDD, on le genere comme texte
                    $groups['autre'][] = [
                        'field_name' => $ph,
                        'description' => ucfirst(str_replace('_', ' ', $ph)),
                        'input_type' => 'text',
                        'options' => '',
                        'field_group' => 'autre'
                    ];
                }
            }

            // Afficher les champs groupes
            foreach ($groups as $groupKey => $groupFields) {
                if (empty($groupFields)) continue;

                echo "<div class='mb-4'>";
                echo "<h6 class='text-uppercase text-muted border-bottom pb-2'><i class='fas {$groupIcons[$groupKey]} me-1'></i> {$groupLabels[$groupKey]}</h6>";

                foreach ($groupFields as $field) {
                    $fieldName = htmlspecialchars($field['field_name']);
                    $description = htmlspecialchars($field['description']);
                    $autofill = isset($autofillMap[$field['field_name']]) ? " data-autofill='" . htmlspecialchars($autofillMap[$field['field_name']]) . "'" : '';

                    echo "<div class='mb-3'>";
                    echo "<label for='{$fieldName}' class='form-label'>{$description} :</label>";

                    if ($field['input_type'] === 'textarea') {
                        echo "<textarea name='{$fieldName}' id='{$fieldName}' class='form-control' rows='3'{$autofill}></textarea>";
                    } elseif ($field['input_type'] === 'select' && !empty($field['options'])) {
                        $options = explode(',', $field['options']);
                        echo "<select name='{$fieldName}' id='{$fieldName}' class='form-select'{$autofill}>";
                        echo '<option value="">-- Choisissez --</option>';
                        foreach ($options as $option) {
                            $opt = htmlspecialchars(trim($option));
                            echo "<option value='{$opt}'>{$opt}</option>";
                        }
                        echo "</select>";
                    } else {
                        $type = $field['input_type'] ?: 'text';
                        $step = $type === 'number' ? " step='0.01'" : '';
                        echo "<input type='{$type}' name='{$fieldName}' id='{$fieldName}' class='form-control'{$step}{$autofill}>";
                    }
                    echo "</div>";
                }
                echo "</div>";
            }
        } else {
            echo "Aucun champ dynamique disponible.";
        }
    } catch (PDOException $e) {
        error_log('get_location_template_fields.php: ' . $e->getMessage());
        echo '<div class="alert alert-danger">Erreur lors du chargement des champs.</div>';
    }
} else {
    echo "ID du modele non valide.";
}
