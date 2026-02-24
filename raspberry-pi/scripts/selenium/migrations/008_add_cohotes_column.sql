-- Migration 008: Ajouter colonne cohotes
-- Nombre de co-hotes pour un logement

ALTER TABLE `market_competitors`
ADD COLUMN `cohotes` INT(11) DEFAULT 0 COMMENT 'Nombre de co-hotes'
AFTER `superhost`;
