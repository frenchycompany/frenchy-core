-- Migration: Ajout des champs de tarification par defaut aux groupes
-- Version 5 - Tarification par groupe

-- --------------------------------------------------------
-- 1. Ajouter les colonnes de tarification a superhote_groups
-- --------------------------------------------------------

ALTER TABLE `superhote_groups`
    ADD COLUMN IF NOT EXISTS `prix_plancher` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix minimum par defaut (J0)',
    ADD COLUMN IF NOT EXISTS `prix_standard` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix normal par defaut (J14+)',
    ADD COLUMN IF NOT EXISTS `weekend_pourcent` DECIMAL(5,2) DEFAULT 10 COMMENT 'Majoration weekend par defaut en %',
    ADD COLUMN IF NOT EXISTS `dimanche_reduction` DECIMAL(10,2) DEFAULT 5 COMMENT 'Reduction dimanche par defaut en euros';

-- Pour MySQL < 8.0 qui ne supporte pas IF NOT EXISTS sur ALTER:
-- ALTER TABLE `superhote_groups` ADD COLUMN `prix_plancher` DECIMAL(10,2) DEFAULT NULL;
-- ALTER TABLE `superhote_groups` ADD COLUMN `prix_standard` DECIMAL(10,2) DEFAULT NULL;
-- ALTER TABLE `superhote_groups` ADD COLUMN `weekend_pourcent` DECIMAL(5,2) DEFAULT 10;
-- ALTER TABLE `superhote_groups` ADD COLUMN `dimanche_reduction` DECIMAL(10,2) DEFAULT 5;

-- --------------------------------------------------------
-- Resume:
-- --------------------------------------------------------
--
-- Cette migration permet de definir une tarification par defaut au niveau
-- du groupe. Quand un logement est assigne a un groupe, les champs de
-- tarification peuvent etre pre-remplis automatiquement avec les valeurs
-- du groupe, evitant ainsi les erreurs de saisie.
--
-- Champs ajoutes:
--   - prix_plancher: Prix minimum pour J0
--   - prix_standard: Prix normal pour J14+
--   - weekend_pourcent: Majoration vendredi/samedi en %
--   - dimanche_reduction: Reduction dimanche en euros
--
