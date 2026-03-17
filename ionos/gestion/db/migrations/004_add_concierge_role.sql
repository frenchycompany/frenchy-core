-- Migration 004: Ajouter le role 'concierge' a la table users
-- Le concierge se situe entre femme_de_menage et gestionnaire dans la hierarchie
-- Il a acces au dashboard staff mais n'est pas admin

ALTER TABLE `users`
    MODIFY COLUMN `role` ENUM('super_admin', 'gestionnaire', 'concierge', 'femme_de_menage', 'proprietaire', 'voyageur')
    DEFAULT 'femme_de_menage';
