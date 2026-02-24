-- Migration: Création des tables pour l'automatisation Superhote
-- Date: 2026-01-26
-- Description: Tables pour la configuration et le suivi des mises à jour de prix Superhote

-- --------------------------------------------------------
-- Table: superhote_config
-- Configuration des logements pour Superhote
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `superhote_config` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `logement_id` INT(11) NOT NULL COMMENT 'Référence vers liste_logements',
    `superhote_property_id` VARCHAR(100) DEFAULT NULL COMMENT 'ID du logement sur Superhote (dans URL)',
    `superhote_property_name` VARCHAR(255) DEFAULT NULL COMMENT 'Nom du logement sur Superhote',
    `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Configuration active',
    `default_price` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix par défaut',
    `weekend_price` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix weekend (samedi-dimanche)',
    `min_price` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix minimum autorisé',
    `max_price` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix maximum autorisé',
    `auto_sync` TINYINT(1) DEFAULT 0 COMMENT 'Synchronisation automatique activée',
    `sync_interval_hours` INT(11) DEFAULT 24 COMMENT 'Intervalle de sync en heures',
    `last_sync_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Dernière synchronisation',
    `notes` TEXT DEFAULT NULL COMMENT 'Notes / commentaires',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_logement` (`logement_id`),
    KEY `idx_superhote_id` (`superhote_property_id`),
    KEY `idx_active` (`is_active`),
    CONSTRAINT `fk_superhote_config_logement` FOREIGN KEY (`logement_id`)
        REFERENCES `liste_logements` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Configuration des logements pour automatisation Superhote';

-- --------------------------------------------------------
-- Table: superhote_price_updates
-- File d'attente des mises à jour de prix
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `superhote_price_updates` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `logement_id` INT(11) NOT NULL COMMENT 'Référence vers liste_logements',
    `superhote_property_id` VARCHAR(100) NOT NULL COMMENT 'ID Superhote du logement',
    `date_start` DATE NOT NULL COMMENT 'Date de début de la période',
    `date_end` DATE NOT NULL COMMENT 'Date de fin de la période',
    `price` DECIMAL(10,2) NOT NULL COMMENT 'Prix à appliquer',
    `currency` VARCHAR(3) DEFAULT 'EUR' COMMENT 'Devise',
    `status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    `priority` INT(11) DEFAULT 0 COMMENT 'Priorité (plus élevé = plus prioritaire)',
    `retry_count` INT(11) DEFAULT 0 COMMENT 'Nombre de tentatives',
    `max_retries` INT(11) DEFAULT 3 COMMENT 'Nombre max de tentatives',
    `error_message` TEXT DEFAULT NULL COMMENT 'Message d''erreur si échec',
    `scheduled_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Date/heure programmée pour exécution',
    `processed_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Date/heure de traitement',
    `created_by` VARCHAR(100) DEFAULT 'system' COMMENT 'Créé par (user/system/api)',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_logement` (`logement_id`),
    KEY `idx_dates` (`date_start`, `date_end`),
    KEY `idx_scheduled` (`scheduled_at`),
    KEY `idx_priority_status` (`priority` DESC, `status`, `created_at`),
    CONSTRAINT `fk_price_updates_logement` FOREIGN KEY (`logement_id`)
        REFERENCES `liste_logements` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='File d''attente des mises à jour de prix Superhote';

-- --------------------------------------------------------
-- Table: superhote_price_history
-- Historique des mises à jour de prix
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `superhote_price_history` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `logement_id` INT(11) NOT NULL,
    `superhote_property_id` VARCHAR(100) NOT NULL,
    `date_start` DATE NOT NULL,
    `date_end` DATE NOT NULL,
    `old_price` DECIMAL(10,2) DEFAULT NULL COMMENT 'Ancien prix (si connu)',
    `new_price` DECIMAL(10,2) NOT NULL COMMENT 'Nouveau prix appliqué',
    `currency` VARCHAR(3) DEFAULT 'EUR',
    `success` TINYINT(1) NOT NULL COMMENT '1 = succès, 0 = échec',
    `source` VARCHAR(50) DEFAULT 'selenium' COMMENT 'Source de la mise à jour',
    `screenshot_path` VARCHAR(500) DEFAULT NULL COMMENT 'Chemin vers capture d''écran',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_logement` (`logement_id`),
    KEY `idx_dates` (`date_start`, `date_end`),
    KEY `idx_created` (`created_at`),
    CONSTRAINT `fk_price_history_logement` FOREIGN KEY (`logement_id`)
        REFERENCES `liste_logements` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historique des mises à jour de prix Superhote';

-- --------------------------------------------------------
-- Table: superhote_pricing_rules
-- Règles de tarification automatiques par logement
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `superhote_pricing_rules` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `logement_id` INT(11) NOT NULL,
    `name` VARCHAR(100) NOT NULL COMMENT 'Nom de la règle',
    `rule_type` ENUM('base', 'weekend', 'seasonal', 'holiday', 'last_minute', 'occupancy', 'custom') NOT NULL,
    `price` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix fixe',
    `price_modifier` DECIMAL(5,2) DEFAULT NULL COMMENT 'Modificateur en % (+10 = +10%)',
    `priority` INT(11) DEFAULT 0 COMMENT 'Priorité (plus élevé = s''applique en premier)',
    `is_active` TINYINT(1) DEFAULT 1,

    -- Conditions de la règle (format JSON)
    `conditions` JSON DEFAULT NULL COMMENT 'Conditions en JSON',

    -- Période d'application
    `valid_from` DATE DEFAULT NULL COMMENT 'Valide à partir de',
    `valid_until` DATE DEFAULT NULL COMMENT 'Valide jusqu''à',

    -- Jours de la semaine (bitmask: 1=Lun, 2=Mar, 4=Mer, 8=Jeu, 16=Ven, 32=Sam, 64=Dim)
    `weekdays` INT(11) DEFAULT 127 COMMENT 'Jours applicables (bitmask)',

    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_logement` (`logement_id`),
    KEY `idx_type` (`rule_type`),
    KEY `idx_active` (`is_active`),
    KEY `idx_priority` (`priority` DESC),
    CONSTRAINT `fk_pricing_rules_logement` FOREIGN KEY (`logement_id`)
        REFERENCES `liste_logements` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Règles de tarification automatiques par logement';

-- --------------------------------------------------------
-- Table: superhote_credentials
-- Credentials Superhote (cryptés)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `superhote_credentials` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL COMMENT 'Nom du compte',
    `email` VARCHAR(255) NOT NULL COMMENT 'Email de connexion',
    `password_encrypted` TEXT NOT NULL COMMENT 'Mot de passe crypté',
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login_at` TIMESTAMP NULL DEFAULT NULL,
    `last_login_status` ENUM('success', 'failed') DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_email` (`email`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Credentials de connexion Superhote';

-- --------------------------------------------------------
-- Table: superhote_automation_logs
-- Logs des exécutions d'automatisation
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `superhote_automation_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `action` VARCHAR(100) NOT NULL COMMENT 'Type d''action',
    `logement_id` INT(11) DEFAULT NULL,
    `status` ENUM('started', 'success', 'failed', 'warning') NOT NULL,
    `message` TEXT DEFAULT NULL,
    `details` JSON DEFAULT NULL COMMENT 'Détails supplémentaires en JSON',
    `duration_ms` INT(11) DEFAULT NULL COMMENT 'Durée en millisecondes',
    `screenshot_path` VARCHAR(500) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_action` (`action`),
    KEY `idx_logement` (`logement_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created` (`created_at`),
    CONSTRAINT `fk_automation_logs_logement` FOREIGN KEY (`logement_id`)
        REFERENCES `liste_logements` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Logs des exécutions d''automatisation Superhote';

-- --------------------------------------------------------
-- Vue: v_pending_price_updates
-- Vue des mises à jour de prix en attente avec détails
-- --------------------------------------------------------

CREATE OR REPLACE VIEW `v_pending_price_updates` AS
SELECT
    spu.id,
    spu.logement_id,
    l.nom_du_logement,
    spu.superhote_property_id,
    spu.date_start,
    spu.date_end,
    DATEDIFF(spu.date_end, spu.date_start) + 1 AS days_count,
    spu.price,
    spu.currency,
    spu.status,
    spu.priority,
    spu.retry_count,
    spu.error_message,
    spu.scheduled_at,
    spu.created_at,
    spu.created_by
FROM superhote_price_updates spu
LEFT JOIN liste_logements l ON spu.logement_id = l.id
WHERE spu.status IN ('pending', 'processing')
ORDER BY spu.priority DESC, spu.created_at ASC;

-- --------------------------------------------------------
-- Vue: v_logement_superhote_config
-- Vue combinée des logements avec leur config Superhote
-- --------------------------------------------------------

CREATE OR REPLACE VIEW `v_logement_superhote_config` AS
SELECT
    l.id AS logement_id,
    l.nom_du_logement,
    l.adresse,
    l.nombre_de_personnes,
    sc.id AS config_id,
    sc.superhote_property_id,
    sc.superhote_property_name,
    sc.is_active AS superhote_active,
    sc.default_price,
    sc.weekend_price,
    sc.min_price,
    sc.max_price,
    sc.auto_sync,
    sc.last_sync_at,
    (SELECT COUNT(*) FROM superhote_price_updates spu
     WHERE spu.logement_id = l.id AND spu.status = 'pending') AS pending_updates,
    (SELECT MAX(created_at) FROM superhote_price_history sph
     WHERE sph.logement_id = l.id AND sph.success = 1) AS last_successful_update
FROM liste_logements l
LEFT JOIN superhote_config sc ON l.id = sc.logement_id;

-- --------------------------------------------------------
-- Indexes supplémentaires pour les performances
-- --------------------------------------------------------

-- Index pour recherche rapide des mises à jour par statut et date
CREATE INDEX IF NOT EXISTS `idx_updates_status_scheduled`
ON `superhote_price_updates` (`status`, `scheduled_at`);

-- Index pour l'historique par période
CREATE INDEX IF NOT EXISTS `idx_history_logement_dates`
ON `superhote_price_history` (`logement_id`, `date_start`, `date_end`);
