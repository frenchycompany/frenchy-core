-- Migration: Simplification du Yield Management
-- Version 3 - Schema simplifie avec formule de prix

-- --------------------------------------------------------
-- 1. Ajouter les nouvelles colonnes a superhote_config
-- --------------------------------------------------------

ALTER TABLE `superhote_config`
    ADD COLUMN IF NOT EXISTS `prix_plancher` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix minimum (jour 0)',
    ADD COLUMN IF NOT EXISTS `prix_standard` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix normal (J+14)',
    ADD COLUMN IF NOT EXISTS `weekend_pourcent` DECIMAL(5,2) DEFAULT 10 COMMENT 'Majoration weekend en %',
    ADD COLUMN IF NOT EXISTS `dimanche_reduction` DECIMAL(10,2) DEFAULT 5 COMMENT 'Reduction dimanche en euros';

-- Pour MySQL < 8.0 qui ne supporte pas IF NOT EXISTS sur ALTER:
-- ALTER TABLE `superhote_config` ADD COLUMN `prix_plancher` DECIMAL(10,2) DEFAULT NULL;
-- ALTER TABLE `superhote_config` ADD COLUMN `prix_standard` DECIMAL(10,2) DEFAULT NULL;
-- ALTER TABLE `superhote_config` ADD COLUMN `weekend_pourcent` DECIMAL(5,2) DEFAULT 10;
-- ALTER TABLE `superhote_config` ADD COLUMN `dimanche_reduction` DECIMAL(10,2) DEFAULT 5;

-- Valeurs par defaut pour les colonnes existantes
UPDATE `superhote_config` SET `weekend_pourcent` = 10 WHERE `weekend_pourcent` IS NULL;
UPDATE `superhote_config` SET `dimanche_reduction` = 5 WHERE `dimanche_reduction` IS NULL;

-- --------------------------------------------------------
-- 2. Creer la table superhote_settings (parametres globaux)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `superhote_settings` (
    `key_name` VARCHAR(50) NOT NULL,
    `value` VARCHAR(255) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserer les parametres par defaut
INSERT IGNORE INTO `superhote_settings` (`key_name`, `value`, `description`) VALUES
    ('palier_j1_3_pourcent', '20', 'Pourcentage entre plancher et standard pour J1-3'),
    ('palier_j4_13_pourcent', '40', 'Pourcentage entre plancher et standard pour J4-13'),
    ('palier_j14_30_pourcent', '60', 'Pourcentage entre plancher et standard pour J14-30'),
    ('palier_j31_60_pourcent', '80', 'Pourcentage entre plancher et standard pour J31-60'),
    ('jours_generation', '90', 'Nombre de jours a generer');

-- --------------------------------------------------------
-- 3. Ajouter colonne nom_du_logement a superhote_price_updates
-- --------------------------------------------------------

ALTER TABLE `superhote_price_updates`
    ADD COLUMN IF NOT EXISTS `nom_du_logement` VARCHAR(255) DEFAULT NULL;

-- Pour MySQL < 8.0:
-- ALTER TABLE `superhote_price_updates` ADD COLUMN `nom_du_logement` VARCHAR(255) DEFAULT NULL;

-- --------------------------------------------------------
-- 4. (Optionnel) Supprimer l'ancienne table de regles complexes
-- Decommentez si vous voulez nettoyer
-- --------------------------------------------------------

-- DROP TABLE IF EXISTS `superhote_pricing_rules`;

-- --------------------------------------------------------
-- Resume du nouveau schema:
-- --------------------------------------------------------
--
-- superhote_config: Configuration par logement
--   - logement_id, superhote_property_id, superhote_property_name
--   - prix_plancher: Prix minimum (J0)
--   - prix_standard: Prix normal (J14+)
--   - weekend_pourcent: Majoration vendredi/samedi en %
--   - dimanche_reduction: Reduction dimanche en euros
--   - is_active
--
-- superhote_settings: Parametres globaux
--   - palier_j1_3_pourcent: % entre plancher et standard pour J1-3
--   - palier_j4_13_pourcent: % entre plancher et standard pour J4-13
--   - palier_j14_30_pourcent: % entre plancher et standard pour J14-30
--   - palier_j31_60_pourcent: % entre plancher et standard pour J31-60
--   - jours_generation: Nombre de jours a generer
--
-- superhote_price_updates: File d'attente (inchange)
--   - + nom_du_logement pour affichage
--
-- Formule de calcul:
--   prix = plancher + (standard - plancher) * pourcentage_palier
--   Si vendredi/samedi: prix = prix * (1 + weekend_pourcent/100)
--   Si dimanche: prix = prix - dimanche_reduction
--
