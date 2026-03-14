<?php
/**
 * Configuration unifiée du système de contrats
 * Gère les deux types : conciergerie et location
 *
 * Usage : $config = getContractConfig($type);
 */

function getContractConfig(string $type): array
{
    $configs = [
        'conciergerie' => [
            'type'                  => 'conciergerie',
            'label'                 => 'Conciergerie',
            'color'                 => 'primary',
            'icon'                  => 'fa-file-contract',
            'table_templates'       => 'contract_templates',
            'table_fields'          => 'contract_fields',
            'table_generated'       => 'generated_contracts',
            'page_create'           => 'create_contract.php',
            'page_generate'         => 'generate_contract.php',
            'page_list'             => 'contrats_generes.php',
            'page_templates'        => 'list_templates.php',
            'page_create_template'  => 'create_template.php',
            'page_edit_template'    => 'edit_template.php',
            'page_save_template'    => 'save_template.php',
            'page_delete_template'  => 'delete_template.php',
            'page_duplicate_template' => 'duplicate_template.php',
            'page_get_fields'       => 'get_template_fields.php',
            'page_get_infos'        => 'get_logement_infos.php',
            'file_prefix'           => 'contract_',
            'has_voyageur'          => false,
            'has_dates_sejour'      => false,
            'has_prix_total'        => false,
            'has_logement_details'  => false,
        ],
        'location' => [
            'type'                  => 'location',
            'label'                 => 'Location',
            'color'                 => 'warning',
            'icon'                  => 'fa-house-user',
            'table_templates'       => 'location_contract_templates',
            'table_fields'          => 'location_contract_fields',
            'table_generated'       => 'generated_location_contracts',
            'page_create'           => 'create_contract.php?type=location',
            'page_generate'         => 'generate_contract.php',
            'page_list'             => 'contrats_generes.php?type=location',
            'page_templates'        => 'list_templates.php?type=location',
            'page_create_template'  => 'create_template.php?type=location',
            'page_edit_template'    => 'edit_template.php',
            'page_save_template'    => 'save_template.php',
            'page_delete_template'  => 'delete_template.php',
            'page_duplicate_template' => 'duplicate_template.php',
            'page_get_fields'       => 'get_location_template_fields.php',
            'page_get_infos'        => 'get_location_logement_infos.php',
            'file_prefix'           => 'location_contract_',
            'has_voyageur'          => true,
            'has_dates_sejour'      => true,
            'has_prix_total'        => true,
            'has_logement_details'  => true,
        ],
    ];

    return $configs[$type] ?? $configs['conciergerie'];
}

/**
 * Détermine le type de contrat depuis les paramètres GET/POST
 */
function detectContractType(): string
{
    $type = $_GET['type'] ?? $_POST['contract_type'] ?? 'conciergerie';
    return in_array($type, ['conciergerie', 'location']) ? $type : 'conciergerie';
}

/**
 * Génère les onglets de sélection du type de contrat
 */
function renderContractTypeTabs(string $currentType, string $basePage): string
{
    $types = [
        'conciergerie' => ['label' => 'Conciergerie', 'icon' => 'fa-file-contract', 'color' => 'primary'],
        'location'     => ['label' => 'Location',     'icon' => 'fa-house-user',    'color' => 'warning'],
    ];

    $html = '<ul class="nav nav-pills mb-4">';
    foreach ($types as $type => $info) {
        $active = ($type === $currentType) ? 'active' : '';
        $textClass = ($type === $currentType) ? '' : 'text-dark';
        $bgClass = ($type === $currentType) ? "bg-{$info['color']}" : 'bg-light';
        $html .= "<li class='nav-item me-2'>";
        $html .= "<a class='nav-link {$bgClass} {$active} {$textClass}' href='{$basePage}?type={$type}'>";
        $html .= "<i class='fas {$info['icon']} me-1'></i> {$info['label']}</a></li>";
    }
    $html .= '</ul>';
    return $html;
}
