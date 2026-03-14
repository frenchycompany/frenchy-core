-- Migration: Mise a jour des paliers de yield management
-- Anciens paliers : J0 / J1-3 / J4-6 / J7-13 / J14+
-- Nouveaux paliers : J0 / J1-3 / J4-13 / J14-30 / J31-60 / J60+

-- Supprimer les anciens paliers obsoletes
DELETE FROM `superhote_settings` WHERE `key_name` IN ('palier_j4_6_pourcent', 'palier_j7_13_pourcent');

-- Mettre a jour le palier J1-3 (nouveau defaut: 20%)
UPDATE `superhote_settings` SET `value` = '20', `description` = 'Pourcentage entre plancher et standard pour J1-3' WHERE `key_name` = 'palier_j1_3_pourcent';

-- Ajouter les nouveaux paliers
INSERT IGNORE INTO `superhote_settings` (`key_name`, `value`, `description`) VALUES
    ('palier_j4_13_pourcent', '40', 'Pourcentage entre plancher et standard pour J4-13'),
    ('palier_j14_30_pourcent', '60', 'Pourcentage entre plancher et standard pour J14-30'),
    ('palier_j31_60_pourcent', '80', 'Pourcentage entre plancher et standard pour J31-60');

-- Augmenter le nombre de jours a generer (necessaire pour couvrir J60+)
UPDATE `superhote_settings` SET `value` = '90' WHERE `key_name` = 'jours_generation' AND `value` = '30';
