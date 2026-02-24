-- Migration: Ajout du systeme de groupes pour les mises a jour en lot
-- Version 4 - Groupes de logements

-- --------------------------------------------------------
-- 1. Ajouter la colonne groupe a superhote_config
-- --------------------------------------------------------

ALTER TABLE `superhote_config`
    ADD COLUMN IF NOT EXISTS `groupe` VARCHAR(100) DEFAULT NULL COMMENT 'Nom du groupe (ex: GROUPE1)';

-- Pour MySQL < 8.0 qui ne supporte pas IF NOT EXISTS sur ALTER:
-- ALTER TABLE `superhote_config` ADD COLUMN `groupe` VARCHAR(100) DEFAULT NULL;

-- Index pour optimiser les recherches par groupe
CREATE INDEX IF NOT EXISTS `idx_groupe` ON `superhote_config` (`groupe`);

-- Pour MySQL < 8.0:
-- CREATE INDEX `idx_groupe` ON `superhote_config` (`groupe`);

-- --------------------------------------------------------
-- 2. (Optionnel) Table superhote_groups pour gerer les groupes
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `superhote_groups` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `nom` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `logement_reference_id` INT(11) DEFAULT NULL COMMENT 'Logement fictif de reference pour ouvrir les modales',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Ajouter la colonne groupe a superhote_price_updates
-- --------------------------------------------------------

ALTER TABLE `superhote_price_updates`
    ADD COLUMN IF NOT EXISTS `groupe` VARCHAR(100) DEFAULT NULL COMMENT 'Groupe de logements concernes';

-- Pour MySQL < 8.0:
-- ALTER TABLE `superhote_price_updates` ADD COLUMN `groupe` VARCHAR(100) DEFAULT NULL;

-- Index pour les recherches par groupe
CREATE INDEX IF NOT EXISTS `idx_updates_groupe` ON `superhote_price_updates` (`groupe`);

-- --------------------------------------------------------
-- Resume du systeme de groupes:
-- --------------------------------------------------------
--
-- Fonctionnement:
--   1. Creer un logement fictif "GROUPE1" sur Superhote (sans reservations)
--   2. Associer ce logement a un groupe dans superhote_groups
--   3. Associer les vrais logements au meme groupe via superhote_config.groupe
--   4. Le worker:
--      a. Ouvre le calendrier du logement fictif (pas de reservations bloquantes)
--      b. Selectionne tous les logements du groupe via la checkbox multi-proprietes
--      c. Applique le prix en une seule operation
--
-- Avantages:
--   - Pas de blocage par les reservations
--   - Une seule operation pour plusieurs logements
--   - Plus rapide (~3min par groupe au lieu de 20min pour 5 logements)
--
