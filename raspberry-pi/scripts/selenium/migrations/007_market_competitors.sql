-- Migration 007: Analyse concurrentielle du marche
-- Table pour stocker les prix des concurrents Airbnb

-- --------------------------------------------------------
-- 1. Table des concurrents
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `market_competitors` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `airbnb_id` VARCHAR(50) DEFAULT NULL COMMENT 'ID Airbnb de l annonce',
    `nom` VARCHAR(255) NOT NULL COMMENT 'Nom de l annonce',
    `url` VARCHAR(500) DEFAULT NULL COMMENT 'URL complete',
    `ville` VARCHAR(100) DEFAULT NULL,
    `quartier` VARCHAR(100) DEFAULT NULL,
    `type_logement` ENUM('appartement', 'maison', 'studio', 'chambre', 'autre') DEFAULT 'appartement',
    `capacite` INT(11) DEFAULT NULL COMMENT 'Nombre de voyageurs',
    `chambres` INT(11) DEFAULT NULL,
    `lits` INT(11) DEFAULT NULL,
    `salles_bain` DECIMAL(3,1) DEFAULT NULL,
    `note_moyenne` DECIMAL(3,2) DEFAULT NULL COMMENT 'Note sur 5',
    `nb_avis` INT(11) DEFAULT NULL,
    `superhost` TINYINT(1) DEFAULT 0,
    `latitude` DECIMAL(10,8) DEFAULT NULL,
    `longitude` DECIMAL(11,8) DEFAULT NULL,
    `equipements` TEXT DEFAULT NULL COMMENT 'Liste des equipements (JSON)',
    `photo_url` VARCHAR(500) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_airbnb_id` (`airbnb_id`),
    KEY `idx_ville` (`ville`),
    KEY `idx_capacite` (`capacite`),
    KEY `idx_type` (`type_logement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Table des prix releves
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `market_prices` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `competitor_id` INT(11) NOT NULL,
    `date_sejour` DATE NOT NULL COMMENT 'Date du sejour',
    `prix_nuit` DECIMAL(10,2) NOT NULL COMMENT 'Prix par nuit',
    `prix_total` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix total avec frais',
    `frais_menage` DECIMAL(10,2) DEFAULT NULL,
    `frais_service` DECIMAL(10,2) DEFAULT NULL,
    `duree_min` INT(11) DEFAULT 1 COMMENT 'Sejour minimum',
    `disponible` TINYINT(1) DEFAULT 1,
    `source` ENUM('bookmarklet', 'manuel', 'api') DEFAULT 'bookmarklet',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_competitor_date` (`competitor_id`, `date_sejour`),
    KEY `idx_date` (`date_sejour`),
    CONSTRAINT `fk_market_prices_competitor` FOREIGN KEY (`competitor_id`)
        REFERENCES `market_competitors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Table de liaison avec nos logements (pour comparaison)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `market_competitor_mapping` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `logement_id` INT(11) NOT NULL COMMENT 'Notre logement',
    `competitor_id` INT(11) NOT NULL COMMENT 'Concurrent a comparer',
    `poids` INT(11) DEFAULT 100 COMMENT 'Poids dans la comparaison (100=normal)',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_mapping` (`logement_id`, `competitor_id`),
    CONSTRAINT `fk_mapping_competitor` FOREIGN KEY (`competitor_id`)
        REFERENCES `market_competitors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Vue pour l'analyse comparative
-- --------------------------------------------------------

CREATE OR REPLACE VIEW `v_market_comparison` AS
SELECT
    l.id AS logement_id,
    l.nom_du_logement,
    mc.id AS competitor_id,
    mc.nom AS competitor_nom,
    mc.capacite AS competitor_capacite,
    mc.note_moyenne AS competitor_note,
    mp.date_sejour,
    mp.prix_nuit AS competitor_prix,
    spu.price AS notre_prix,
    ROUND(((mp.prix_nuit - spu.price) / spu.price) * 100, 1) AS ecart_pourcent
FROM liste_logements l
INNER JOIN market_competitor_mapping mcm ON l.id = mcm.logement_id
INNER JOIN market_competitors mc ON mcm.competitor_id = mc.id
LEFT JOIN market_prices mp ON mc.id = mp.competitor_id
LEFT JOIN superhote_price_updates spu ON l.id = spu.logement_id
    AND mp.date_sejour = spu.date_start
    AND spu.status = 'pending'
WHERE mc.is_active = 1;

-- --------------------------------------------------------
-- Resume:
-- --------------------------------------------------------
--
-- market_competitors: Informations sur les annonces concurrentes
-- market_prices: Historique des prix releves
-- market_competitor_mapping: Liaison entre nos logements et les concurrents
-- v_market_comparison: Vue pour comparer nos prix avec la concurrence
--
