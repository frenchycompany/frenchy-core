<?php
/**
 * Script de migration pour ajouter les champs iCal
 * Exécutez ce fichier une seule fois depuis votre navigateur: /sms/migrate_ical.php
 */

require_once '../includes/db.php';

echo "<h1>Migration iCal - Ajout des champs</h1>";
echo "<pre>";

try {
    // Vérifier si les colonnes existent déjà
    $stmt = $pdo->query("SHOW COLUMNS FROM travel_account_connections LIKE 'ical_url'");
    $icalUrlExists = $stmt->rowCount() > 0;

    if (!$icalUrlExists) {
        echo "✓ Ajout du champ ical_url...\n";
        $pdo->exec("ALTER TABLE `travel_account_connections`
            ADD COLUMN `ical_url` VARCHAR(1000) AFTER `access_token`");
        echo "  → Champ ical_url ajouté avec succès\n\n";
    } else {
        echo "✓ Le champ ical_url existe déjà\n\n";
    }

    // Ajouter ical_last_sync
    $stmt = $pdo->query("SHOW COLUMNS FROM travel_account_connections LIKE 'ical_last_sync'");
    $icalLastSyncExists = $stmt->rowCount() > 0;

    if (!$icalLastSyncExists) {
        echo "✓ Ajout du champ ical_last_sync...\n";
        $pdo->exec("ALTER TABLE `travel_account_connections`
            ADD COLUMN `ical_last_sync` TIMESTAMP NULL AFTER `ical_url`");
        echo "  → Champ ical_last_sync ajouté avec succès\n\n";
    } else {
        echo "✓ Le champ ical_last_sync existe déjà\n\n";
    }

    // Ajouter ical_sync_status
    $stmt = $pdo->query("SHOW COLUMNS FROM travel_account_connections LIKE 'ical_sync_status'");
    $icalSyncStatusExists = $stmt->rowCount() > 0;

    if (!$icalSyncStatusExists) {
        echo "✓ Ajout du champ ical_sync_status...\n";
        $pdo->exec("ALTER TABLE `travel_account_connections`
            ADD COLUMN `ical_sync_status` ENUM('never', 'success', 'error') DEFAULT 'never' AFTER `ical_last_sync`");
        echo "  → Champ ical_sync_status ajouté avec succès\n\n";
    } else {
        echo "✓ Le champ ical_sync_status existe déjà\n\n";
    }

    // Ajouter ical_error_message
    $stmt = $pdo->query("SHOW COLUMNS FROM travel_account_connections LIKE 'ical_error_message'");
    $icalErrorExists = $stmt->rowCount() > 0;

    if (!$icalErrorExists) {
        echo "✓ Ajout du champ ical_error_message...\n";
        $pdo->exec("ALTER TABLE `travel_account_connections`
            ADD COLUMN `ical_error_message` TEXT AFTER `ical_sync_status`");
        echo "  → Champ ical_error_message ajouté avec succès\n\n";
    } else {
        echo "✓ Le champ ical_error_message existe déjà\n\n";
    }

    // Créer la table ical_reservations si elle n'existe pas
    echo "✓ Création de la table ical_reservations...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS `ical_reservations` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `connection_id` INT NOT NULL,
      `listing_id` INT,
      `ical_uid` VARCHAR(255) NOT NULL,
      `summary` VARCHAR(255),
      `description` TEXT,
      `start_date` DATE NOT NULL,
      `end_date` DATE NOT NULL,
      `guest_name` VARCHAR(255),
      `guest_email` VARCHAR(255),
      `guest_phone` VARCHAR(50),
      `status` ENUM('confirmed', 'pending', 'cancelled', 'blocked') DEFAULT 'confirmed',
      `is_blocked` BOOLEAN DEFAULT FALSE,
      `platform_reservation_id` VARCHAR(100),
      `total_price` DECIMAL(10,2),
      `currency` VARCHAR(3) DEFAULT 'EUR',
      `num_guests` INT,
      `num_nights` INT,
      `metadata` JSON,
      `imported_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      FOREIGN KEY (`connection_id`) REFERENCES `travel_account_connections`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`listing_id`) REFERENCES `travel_listings`(`id`) ON DELETE SET NULL,
      UNIQUE KEY `unique_ical_event` (`connection_id`, `ical_uid`),
      KEY `idx_dates` (`start_date`, `end_date`),
      KEY `idx_listing` (`listing_id`),
      KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "  → Table ical_reservations créée avec succès\n\n";

    // Créer la table ical_sync_log si elle n'existe pas
    echo "✓ Création de la table ical_sync_log...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS `ical_sync_log` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `connection_id` INT NOT NULL,
      `ical_url` VARCHAR(1000) NOT NULL,
      `sync_status` ENUM('success', 'error', 'partial') NOT NULL,
      `events_found` INT DEFAULT 0,
      `events_imported` INT DEFAULT 0,
      `events_updated` INT DEFAULT 0,
      `events_skipped` INT DEFAULT 0,
      `error_message` TEXT,
      `raw_ical_data` LONGTEXT,
      `sync_duration_ms` INT,
      `synced_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`connection_id`) REFERENCES `travel_account_connections`(`id`) ON DELETE CASCADE,
      KEY `idx_connection` (`connection_id`),
      KEY `idx_sync_date` (`synced_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "  → Table ical_sync_log créée avec succès\n\n";

    // Créer la vue v_all_reservations
    echo "✓ Création de la vue v_all_reservations...\n";
    $pdo->exec("CREATE OR REPLACE VIEW `v_all_reservations` AS
    SELECT
        r.id,
        r.ical_uid,
        r.summary,
        r.description,
        r.start_date,
        r.end_date,
        r.guest_name,
        r.guest_email,
        r.guest_phone,
        r.status,
        r.is_blocked,
        r.platform_reservation_id,
        r.total_price,
        r.currency,
        r.num_guests,
        r.num_nights,
        DATEDIFF(r.end_date, r.start_date) as calculated_nights,
        r.imported_at,
        r.updated_at,
        l.id as listing_id,
        l.title as listing_title,
        l.city as listing_city,
        l.platform_listing_id,
        c.id as connection_id,
        c.account_name,
        p.name as platform_name,
        p.code as platform_code,
        p.logo_url as platform_logo,
        CASE
            WHEN r.end_date < CURDATE() THEN 'past'
            WHEN r.start_date > CURDATE() THEN 'upcoming'
            ELSE 'current'
        END as reservation_period
    FROM ical_reservations r
    JOIN travel_account_connections c ON r.connection_id = c.id
    JOIN travel_platforms p ON c.platform_id = p.id
    LEFT JOIN travel_listings l ON r.listing_id = l.id
    ORDER BY r.start_date DESC");
    echo "  → Vue v_all_reservations créée avec succès\n\n";

    echo "========================================\n";
    echo "✅ MIGRATION RÉUSSIE !\n";
    echo "========================================\n\n";
    echo "Vous pouvez maintenant :\n";
    echo "1. Retourner sur travel_accounts.php\n";
    echo "2. Modifier une connexion\n";
    echo "3. Coller votre URL iCal (ex: https://www.airbnb.fr/calendar/ical/...)\n";
    echo "4. Enregistrer\n";
    echo "5. Cliquer sur l'icône calendrier pour synchroniser\n\n";
    echo "⚠️  IMPORTANT: Vous pouvez supprimer ce fichier (migrate_ical.php) après avoir exécuté la migration.\n";

} catch (PDOException $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n\n";
    echo "Détails:\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
?>