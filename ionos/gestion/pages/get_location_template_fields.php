<?php
/**
 * AJAX endpoint - Charge les champs dynamiques d'un modele de contrat de location
 */
include '../config.php';

// Auto-migration : ajouter le groupe 'proprietaire' et les champs proprietaire
try {
    // Modifier ENUM pour ajouter 'proprietaire' si absent
    $row = $conn->query("SHOW COLUMNS FROM location_contract_fields LIKE 'field_group'")->fetch(PDO::FETCH_ASSOC);
    if ($row && strpos($row['Type'], 'proprietaire') === false) {
        $conn->exec("ALTER TABLE location_contract_fields MODIFY COLUMN field_group ENUM('voyageur','reservation','logement','proprietaire','autre') DEFAULT 'autre'");
    }
    // Inserer les champs proprietaire s'ils n'existent pas
    $existing = $conn->query("SELECT field_name FROM location_contract_fields WHERE field_group = 'proprietaire'")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($existing)) {
        $ins = $conn->prepare("INSERT IGNORE INTO location_contract_fields (field_name, description, input_type, field_group, sort_order) VALUES (?,?,?,?,?)");
        $propFields = [
            ['proprietaire_nom_complet', 'Nom complet du proprietaire', 'text', 'proprietaire', 500],
            ['proprietaire_email', 'Email du proprietaire', 'text', 'proprietaire', 510],
            ['proprietaire_telephone', 'Telephone du proprietaire', 'text', 'proprietaire', 520],
            ['proprietaire_adresse', 'Adresse du proprietaire (ligne 1)', 'text', 'proprietaire', 530],
            ['proprietaire_adresse_ligne2', 'Adresse du proprietaire (ligne 2)', 'text', 'proprietaire', 540],
            ['proprietaire_code_postal', 'Code postal du proprietaire', 'text', 'proprietaire', 550],
            ['proprietaire_ville', 'Ville du proprietaire', 'text', 'proprietaire', 560],
            ['proprietaire_adresse_complete', 'Adresse complete du proprietaire', 'text', 'proprietaire', 570],
            ['proprietaire_societe', 'Societe du proprietaire', 'text', 'proprietaire', 580],
            ['proprietaire_siret', 'SIRET du proprietaire', 'text', 'proprietaire', 590],
        ];
        foreach ($propFields as $f) { $ins->execute($f); }
    }
} catch (PDOException $e) { error_log('location_contract_fields migration: ' . $e->getMessage()); }

// Mapping champ contrat -> source auto-fill depuis donnees logement/proprietaire
$autofillMap = [
    // Logement
    'nom_du_logement'      => 'nom_du_logement',
    'adresse_logement'     => 'adresse',
    'adresse_complete'     => 'adresse_complete',
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
    // Proprietaire
    'proprietaire_nom_complet'  => 'proprietaire_fullname',
    'proprietaire_nom'          => 'proprietaire_nom',
    'proprietaire_prenom'       => 'proprietaire_prenom',
    'proprietaire_email'        => 'proprietaire_email',
    'proprietaire_telephone'    => 'proprietaire_telephone',
    'proprietaire_adresse'      => 'proprietaire_adresse',
    'proprietaire_adresse_ligne2' => 'proprietaire_adresse_ligne2',
    'proprietaire_code_postal'  => 'proprietaire_code_postal',
    'proprietaire_ville'        => 'proprietaire_ville',
    'proprietaire_adresse_complete' => 'proprietaire_adresse_complete',
    'proprietaire_societe'      => 'proprietaire_societe',
    'proprietaire_siret'        => 'proprietaire_siret',
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
            $groups = ['voyageur' => [], 'reservation' => [], 'logement' => [], 'proprietaire' => [], 'autre' => []];
            $groupLabels = ['voyageur' => 'Voyageur', 'reservation' => 'Reservation', 'logement' => 'Logement', 'proprietaire' => 'Proprietaire', 'autre' => 'Autre'];
            $groupIcons = ['voyageur' => 'fa-user', 'reservation' => 'fa-calendar-alt', 'logement' => 'fa-home', 'proprietaire' => 'fa-user-tie', 'autre' => 'fa-info-circle'];

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
