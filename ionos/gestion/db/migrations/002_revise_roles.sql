-- ============================================================
-- Migration 002: Révision des rôles utilisateurs
-- Anciens rôles : super_admin, admin, staff, proprietaire_full, proprietaire_opti
-- Nouveaux rôles : super_admin, gestionnaire, femme_de_menage, proprietaire, voyageur
-- ============================================================

-- Étape 1 : Convertir les données existantes vers les nouveaux noms de rôles
-- (doit être fait AVANT de modifier l'ENUM)
UPDATE `users` SET `role` = 'staff' WHERE `role` = 'staff';

-- Étape 2 : Modifier l'ENUM pour accepter à la fois les anciens et nouveaux rôles (transition)
ALTER TABLE `users` MODIFY COLUMN `role`
    ENUM('super_admin', 'admin', 'staff', 'proprietaire_full', 'proprietaire_opti',
         'gestionnaire', 'femme_de_menage', 'proprietaire', 'voyageur')
    NOT NULL DEFAULT 'femme_de_menage';

-- Étape 3 : Migrer les données vers les nouveaux rôles
UPDATE `users` SET `role` = 'gestionnaire' WHERE `role` = 'admin';
UPDATE `users` SET `role` = 'femme_de_menage' WHERE `role` = 'staff';
UPDATE `users` SET `role` = 'proprietaire' WHERE `role` IN ('proprietaire_full', 'proprietaire_opti');

-- Étape 4 : Supprimer les anciens rôles de l'ENUM
ALTER TABLE `users` MODIFY COLUMN `role`
    ENUM('super_admin', 'gestionnaire', 'femme_de_menage', 'proprietaire', 'voyageur')
    NOT NULL DEFAULT 'femme_de_menage';
