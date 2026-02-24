-- Migration: Tables pour l'automatisation Superhote avec Yield Management
-- Version 2 - Avec regles de tarification dynamique

-- --------------------------------------------------------
-- Table: superhote_config
-- Configuration des logements pour Superhote
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `superhote_config` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `logement_id` INT(11) NOT NULL,
    `superhote_property_id` VARCHAR(100) DEFAULT NULL,
    `superhote_property_name` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `base_price` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix de base standard',
    `min_price` DECIMAL(10,2) DEFAULT NULL,
    `max_price` DECIMAL(10,2) DEFAULT NULL,
    `auto_sync` TINYINT(1) DEFAULT 0,
    `sync_interval_hours` INT(11) DEFAULT 24,
    `last_sync_at` TIMESTAMP NULL DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_logement` (`logement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: superhote_pricing_rules
-- Regles de tarification dynamique (yield management)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `superhote_pricing_rules` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `logement_id` INT(11) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `rule_type` ENUM('last_minute', 'advance', 'standard', 'weekend', 'seasonal', 'holiday') NOT NULL,
    `days_from` INT(11) DEFAULT NULL COMMENT 'Jours avant arrivee (debut)',
    `days_to` INT(11) DEFAULT NULL COMMENT 'Jours avant arrivee (fin)',
    `price` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix fixe',
    `discount_percent` DECIMAL(5,2) DEFAULT NULL COMMENT 'Reduction en % (negatif = reduction)',
    `priority` INT(11) DEFAULT 0 COMMENT 'Plus eleve = prioritaire',
    `is_active` TINYINT(1) DEFAULT 1,
    `weekdays` VARCHAR(20) DEFAULT NULL COMMENT 'Jours: 0=Dim,1=Lun...6=Sam (ex: 5,6 pour weekend)',
    `month_from` INT(11) DEFAULT NULL COMMENT 'Mois debut (1-12)',
    `month_to` INT(11) DEFAULT NULL COMMENT 'Mois fin (1-12)',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_logement` (`logement_id`),
    KEY `idx_type` (`rule_type`),
    KEY `idx_active` (`is_active`),
    KEY `idx_priority` (`priority` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: superhote_price_updates
-- File d'attente des mises a jour
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `superhote_price_updates` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `logement_id` INT(11) NOT NULL,
    `superhote_property_id` VARCHAR(100) NOT NULL,
    `date_start` DATE NOT NULL,
    `date_end` DATE NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `rule_id` INT(11) DEFAULT NULL COMMENT 'Regle qui a genere ce prix',
    `rule_name` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    `priority` INT(11) DEFAULT 0,
    `retry_count` INT(11) DEFAULT 0,
    `error_message` TEXT DEFAULT NULL,
    `created_by` VARCHAR(100) DEFAULT 'system',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_logement` (`logement_id`),
    KEY `idx_dates` (`date_start`, `date_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: superhote_price_history
-- Historique des mises a jour
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `superhote_price_history` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `logement_id` INT(11) NOT NULL,
    `superhote_property_id` VARCHAR(100) NOT NULL,
    `date_target` DATE NOT NULL,
    `old_price` DECIMAL(10,2) DEFAULT NULL,
    `new_price` DECIMAL(10,2) NOT NULL,
    `rule_applied` VARCHAR(100) DEFAULT NULL,
    `success` TINYINT(1) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_logement` (`logement_id`),
    KEY `idx_date` (`date_target`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: superhote_automation_logs
-- Logs des executions
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `superhote_automation_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `action` VARCHAR(100) NOT NULL,
    `logement_id` INT(11) DEFAULT NULL,
    `status` ENUM('started', 'success', 'failed', 'warning') NOT NULL,
    `message` TEXT DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
