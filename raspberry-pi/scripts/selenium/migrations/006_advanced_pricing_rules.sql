-- Migration 006: Regles de tarification avancees
-- - Saisons (haute, moyenne, basse)
-- - Jours feries francais
-- - Taux d'occupation

-- --------------------------------------------------------
-- 1. Table des saisons
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `superhote_seasons` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `nom` VARCHAR(100) NOT NULL COMMENT 'Nom de la saison (ex: Haute saison ete)',
    `date_debut` DATE NOT NULL COMMENT 'Date de debut (annee ignoree, ex: 2000-07-01)',
    `date_fin` DATE NOT NULL COMMENT 'Date de fin (annee ignoree, ex: 2000-08-31)',
    `type_saison` ENUM('haute', 'moyenne', 'basse') NOT NULL DEFAULT 'moyenne',
    `majoration_pourcent` DECIMAL(5,2) DEFAULT 0 COMMENT 'Majoration en % (ex: 20 pour +20%)',
    `reduction_pourcent` DECIMAL(5,2) DEFAULT 0 COMMENT 'Reduction en % (ex: 15 pour -15%)',
    `priorite` INT(11) DEFAULT 10 COMMENT 'Priorite (plus haut = prioritaire)',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_seasons_dates` (`date_debut`, `date_fin`),
    KEY `idx_seasons_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saisons par defaut (vacances scolaires zone C approximatives)
INSERT INTO `superhote_seasons` (`nom`, `date_debut`, `date_fin`, `type_saison`, `majoration_pourcent`, `priorite`) VALUES
    ('Haute saison ete', '2000-07-01', '2000-08-31', 'haute', 25, 100),
    ('Vacances Noel', '2000-12-20', '2000-12-31', 'haute', 30, 90),
    ('Nouvel An', '2000-01-01', '2000-01-05', 'haute', 30, 90),
    ('Vacances fevrier', '2000-02-10', '2000-03-10', 'moyenne', 15, 50),
    ('Vacances Paques', '2000-04-05', '2000-04-25', 'moyenne', 15, 50),
    ('Vacances Toussaint', '2000-10-20', '2000-11-05', 'moyenne', 10, 50),
    ('Basse saison hiver', '2000-01-06', '2000-02-09', 'basse', 0, 10),
    ('Basse saison novembre', '2000-11-06', '2000-12-19', 'basse', 0, 10);

-- Mettre reduction pour basse saison
UPDATE `superhote_seasons` SET `reduction_pourcent` = 10 WHERE `type_saison` = 'basse';

-- --------------------------------------------------------
-- 2. Table des jours feries
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `superhote_holidays` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `nom` VARCHAR(100) NOT NULL COMMENT 'Nom du jour ferie',
    `date_ferie` DATE NOT NULL COMMENT 'Date (annee ignoree pour recurrents, ex: 2000-07-14)',
    `is_recurring` TINYINT(1) DEFAULT 1 COMMENT '1=chaque annee, 0=date fixe',
    `majoration_pourcent` DECIMAL(5,2) DEFAULT 15 COMMENT 'Majoration en %',
    `jours_autour` INT(11) DEFAULT 0 COMMENT 'Jours avant/apres a majorer aussi',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_holidays_date` (`date_ferie`),
    KEY `idx_holidays_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jours feries francais
INSERT INTO `superhote_holidays` (`nom`, `date_ferie`, `is_recurring`, `majoration_pourcent`, `jours_autour`) VALUES
    ('Jour de l''An', '2000-01-01', 1, 20, 1),
    ('Lundi de Paques', '2000-04-01', 0, 15, 0),
    ('Fete du Travail', '2000-05-01', 1, 15, 0),
    ('Victoire 1945', '2000-05-08', 1, 15, 0),
    ('Ascension', '2000-05-09', 0, 15, 1),
    ('Lundi de Pentecote', '2000-05-20', 0, 15, 0),
    ('Fete Nationale', '2000-07-14', 1, 20, 1),
    ('Assomption', '2000-08-15', 1, 20, 1),
    ('Toussaint', '2000-11-01', 1, 15, 1),
    ('Armistice', '2000-11-11', 1, 15, 0),
    ('Noel', '2000-12-25', 1, 25, 2),
    ('Saint-Sylvestre', '2000-12-31', 1, 25, 0);

-- --------------------------------------------------------
-- 3. Table des regles d'occupation
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `superhote_occupancy_rules` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `nom` VARCHAR(100) NOT NULL,
    `seuil_occupation_min` DECIMAL(5,2) NOT NULL COMMENT 'Seuil minimum (ex: 0 = 0%)',
    `seuil_occupation_max` DECIMAL(5,2) NOT NULL COMMENT 'Seuil maximum (ex: 50 = 50%)',
    `jours_anticipation` INT(11) DEFAULT 14 COMMENT 'Calcul sur les X prochains jours',
    `ajustement_pourcent` DECIMAL(5,2) NOT NULL COMMENT 'Ajustement en % (negatif = reduction)',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_occupancy_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Regles d'occupation par defaut
INSERT INTO `superhote_occupancy_rules` (`nom`, `seuil_occupation_min`, `seuil_occupation_max`, `jours_anticipation`, `ajustement_pourcent`) VALUES
    ('Occupation critique (<30%)', 0, 30, 14, -15),
    ('Occupation faible (30-50%)', 30, 50, 14, -10),
    ('Occupation moyenne (50-70%)', 50, 70, 14, 0),
    ('Occupation haute (70-90%)', 70, 90, 14, 5),
    ('Occupation tres haute (>90%)', 90, 100, 14, 10);

-- --------------------------------------------------------
-- 4. Parametres globaux supplementaires
-- --------------------------------------------------------

INSERT IGNORE INTO `superhote_settings` (`key_name`, `value`, `description`) VALUES
    ('saisons_enabled', '1', 'Activer les regles saisonnieres'),
    ('holidays_enabled', '1', 'Activer les majorations jours feries'),
    ('occupancy_enabled', '1', 'Activer ajustement selon occupation'),
    ('occupancy_calculation_days', '14', 'Jours pour calculer le taux occupation');

-- --------------------------------------------------------
-- 5. Vue pour faciliter le calcul d'occupation par logement
-- --------------------------------------------------------

CREATE OR REPLACE VIEW `v_logement_occupation` AS
SELECT
    l.id AS logement_id,
    l.nom AS logement_nom,
    COUNT(DISTINCT r.id) AS nb_reservations,
    SUM(DATEDIFF(
        LEAST(r.date_depart, DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
        GREATEST(r.date_arrivee, CURDATE())
    )) AS jours_occupes,
    14 AS jours_total,
    ROUND(
        (SUM(DATEDIFF(
            LEAST(r.date_depart, DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
            GREATEST(r.date_arrivee, CURDATE())
        )) / 14) * 100, 2
    ) AS taux_occupation
FROM logement l
LEFT JOIN reservation r ON r.logement_id = l.id
    AND r.date_depart > CURDATE()
    AND r.date_arrivee < DATE_ADD(CURDATE(), INTERVAL 14 DAY)
    AND r.statut != 'annulee'
GROUP BY l.id, l.nom;

-- --------------------------------------------------------
-- Resume des nouvelles regles:
-- --------------------------------------------------------
--
-- 1. SAISONS (superhote_seasons)
--    - Haute saison: majoration (ex: +25% en ete)
--    - Basse saison: reduction (ex: -10% en novembre)
--    - Priorite pour gerer chevauchements
--
-- 2. JOURS FERIES (superhote_holidays)
--    - Majoration automatique (ex: +20% le 14 juillet)
--    - Option jours_autour pour pont (ex: +15% veille et lendemain)
--
-- 3. OCCUPATION (superhote_occupancy_rules)
--    - Si occupation < 30%: reduction -15%
--    - Si occupation > 90%: majoration +10%
--
-- FORMULE FINALE:
-- prix = prix_base
--      * (1 + saison_pourcent/100)
--      * (1 + ferie_pourcent/100)
--      * (1 + occupation_pourcent/100)
--
