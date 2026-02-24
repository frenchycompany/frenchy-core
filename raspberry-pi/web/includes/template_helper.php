<?php
/**
 * Template Helper Functions
 *
 * Fonctions utilitaires pour gérer les templates SMS
 * avec fallback automatique (logement spécifique -> défaut)
 */

/**
 * Récupère le template approprié pour un type de message et un logement
 *
 * @param PDO $pdo Instance PDO
 * @param string $type_message Type de message (checkout, accueil, preparation, relance)
 * @param int|null $logement_id ID du logement (optionnel)
 * @return string|null Le template trouvé ou null
 */
function get_sms_template(PDO $pdo, string $type_message, ?int $logement_id = null): ?string {
    // Si un logement est spécifié, chercher d'abord un template spécifique actif
    if ($logement_id !== null && $logement_id > 0) {
        try {
            $stmt = $pdo->prepare("
                SELECT message
                FROM sms_logement_templates
                WHERE logement_id = :logement_id
                  AND type_message = :type_message
                  AND actif = 1
                LIMIT 1
            ");
            $stmt->execute([
                ':logement_id' => $logement_id,
                ':type_message' => $type_message
            ]);

            $template = $stmt->fetchColumn();
            if ($template !== false && !empty($template)) {
                return $template;
            }
        } catch (PDOException $e) {
            // Continuer vers le template par défaut en cas d'erreur
            error_log("Erreur lors de la récupération du template logement: " . $e->getMessage());
        }
    }

    // Fallback: chercher le template par défaut
    try {
        $stmt = $pdo->prepare("
            SELECT template
            FROM sms_templates
            WHERE name = :name
            LIMIT 1
        ");
        $stmt->execute([':name' => $type_message]);

        $template = $stmt->fetchColumn();
        return $template !== false ? $template : null;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du template par défaut: " . $e->getMessage());
        return null;
    }
}

/**
 * Personnalise un template avec les variables
 *
 * @param string $template Template avec variables {prenom}, {nom}, etc.
 * @param array $variables Tableau associatif des valeurs
 * @return string Template avec variables remplacées
 */
function personalize_template(string $template, array $variables): string {
    $replacements = [];

    foreach ($variables as $key => $value) {
        $replacements['{' . $key . '}'] = $value ?? '';
    }

    return str_replace(array_keys($replacements), array_values($replacements), $template);
}

/**
 * Récupère et personnalise un template en une seule fonction
 *
 * @param PDO $pdo Instance PDO
 * @param string $type_message Type de message
 * @param array $variables Variables pour personnalisation
 * @param int|null $logement_id ID du logement (optionnel)
 * @return string|null Message personnalisé ou null
 */
function get_personalized_sms(PDO $pdo, string $type_message, array $variables, ?int $logement_id = null): ?string {
    $template = get_sms_template($pdo, $type_message, $logement_id);

    if ($template === null) {
        return null;
    }

    return personalize_template($template, $variables);
}

/**
 * Vérifie si un logement a un template spécifique pour un type de message
 *
 * @param PDO $pdo Instance PDO
 * @param int $logement_id ID du logement
 * @param string $type_message Type de message
 * @return bool True si un template spécifique existe et est actif
 */
function has_custom_template(PDO $pdo, int $logement_id, string $type_message): bool {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM sms_logement_templates
            WHERE logement_id = :logement_id
              AND type_message = :type_message
              AND actif = 1
        ");
        $stmt->execute([
            ':logement_id' => $logement_id,
            ':type_message' => $type_message
        ]);

        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification du template personnalisé: " . $e->getMessage());
        return false;
    }
}

/**
 * Liste tous les types de messages disponibles
 *
 * @return array Liste des types de messages
 */
function get_message_types(): array {
    return [
        'checkout' => [
            'name' => 'Check-out',
            'description' => 'Envoyé le jour du départ'
        ],
        'accueil' => [
            'name' => 'Accueil',
            'description' => 'Envoyé le jour de l\'arrivée'
        ],
        'preparation' => [
            'name' => 'Préparation',
            'description' => 'Envoyé 4 jours avant l\'arrivée'
        ],
        'relance' => [
            'name' => 'Relance',
            'description' => 'Utilisé dans les campagnes de relance'
        ]
    ];
}

/**
 * Récupère tous les templates (par défaut et par logement) pour un logement donné
 *
 * @param PDO $pdo Instance PDO
 * @param int $logement_id ID du logement
 * @return array Tableau avec les templates par type
 */
function get_all_templates_for_logement(PDO $pdo, int $logement_id): array {
    $message_types = array_keys(get_message_types());
    $templates = [];

    foreach ($message_types as $type) {
        $templates[$type] = [
            'template' => get_sms_template($pdo, $type, $logement_id),
            'is_custom' => has_custom_template($pdo, $logement_id, $type)
        ];
    }

    return $templates;
}
?>
