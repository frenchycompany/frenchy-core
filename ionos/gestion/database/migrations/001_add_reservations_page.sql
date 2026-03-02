-- Migration: ajouter la page Réservations au menu
-- À exécuter sur la base Ionos (dbs13515816)

INSERT INTO pages (nom, chemin, afficher_menu)
SELECT 'Réservations', 'pages/reservations.php', 1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM pages WHERE chemin = 'pages/reservations.php'
);
