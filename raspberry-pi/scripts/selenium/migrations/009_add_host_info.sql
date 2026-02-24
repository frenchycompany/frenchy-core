-- Migration 009: Ajouter colonnes pour info proprietaire et co-hotes
-- Permet d'identifier les multi-proprietaires

ALTER TABLE `market_competitors`
ADD COLUMN `host_name` VARCHAR(255) DEFAULT NULL COMMENT 'Nom du proprietaire' AFTER `cohotes`,
ADD COLUMN `host_profile_id` VARCHAR(50) DEFAULT NULL COMMENT 'ID profil Airbnb du proprietaire' AFTER `host_name`,
ADD COLUMN `cohost_names` TEXT DEFAULT NULL COMMENT 'Noms des co-hotes (JSON array)' AFTER `host_profile_id`;

-- Index pour rechercher par proprietaire
CREATE INDEX idx_host_profile ON market_competitors(host_profile_id);
