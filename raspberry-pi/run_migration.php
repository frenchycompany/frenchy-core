#!/usr/bin/env php
<?php
/**
 * Script pour exécuter la migration SQL
 */

require_once __DIR__ . '/web/includes/db.php';

if (!($pdo instanceof PDO)) {
    die("❌ Erreur: PDO non disponible\n");
}

echo "=== Exécution de la migration logement_id ===\n\n";

try {
    // Vérifier si la colonne existe déjà
    $stmt = $pdo->query("SHOW COLUMNS FROM sms_automations LIKE 'logement_id'");
    $exists = $stmt->fetch();

    if ($exists) {
        echo "✅ La colonne logement_id existe déjà dans sms_automations\n";
    } else {
        echo "Ajout de la colonne logement_id...\n";
        $pdo->exec("
            ALTER TABLE `sms_automations`
            ADD COLUMN `logement_id` int(11) DEFAULT NULL COMMENT 'Si défini, automatisation limitée à ce logement uniquement',
            ADD KEY `idx_logement` (`logement_id`)
        ");
        echo "✅ Colonne logement_id ajoutée avec succès\n";
    }

    // Vérifier si les colonnes custom_sent existent
    $stmt = $pdo->query("SHOW COLUMNS FROM reservation LIKE 'custom1_sent'");
    $customExists = $stmt->fetch();

    if ($customExists) {
        echo "✅ Les colonnes custom_sent existent déjà dans reservation\n";
    } else {
        echo "Ajout des colonnes custom_sent...\n";
        $pdo->exec("
            ALTER TABLE `reservation`
            ADD COLUMN `custom1_sent` tinyint(1) DEFAULT 0,
            ADD COLUMN `custom2_sent` tinyint(1) DEFAULT 0,
            ADD COLUMN `custom3_sent` tinyint(1) DEFAULT 0,
            ADD COLUMN `custom4_sent` tinyint(1) DEFAULT 0,
            ADD COLUMN `custom5_sent` tinyint(1) DEFAULT 0
        ");
        echo "✅ Colonnes custom_sent ajoutées avec succès\n";
    }

    echo "\n✅ Migration terminée avec succès\n";

} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
