-- Migration: Création de la table avis_voyageurs
-- Stocke les avis Booking.com collés manuellement, liés aux réservations via le numéro de référence

CREATE TABLE IF NOT EXISTS `avis_voyageurs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `numero_reservation` VARCHAR(50) NOT NULL COMMENT 'Numéro de réservation Booking.com',
  `reservation_id` INT(11) DEFAULT NULL COMMENT 'FK vers reservation.id si trouvée',
  `logement_id` INT(11) DEFAULT NULL COMMENT 'FK vers liste_logements.id (via reservation)',
  `nom_voyageur` VARCHAR(100) DEFAULT NULL,
  `pays_voyageur` VARCHAR(10) DEFAULT NULL,
  `date_avis` DATE DEFAULT NULL,
  `note_globale` DECIMAL(3,1) DEFAULT NULL,
  `note_personnel` DECIMAL(3,1) DEFAULT NULL,
  `note_proprete` DECIMAL(3,1) DEFAULT NULL,
  `note_situation` DECIMAL(3,1) DEFAULT NULL,
  `note_equipements` DECIMAL(3,1) DEFAULT NULL,
  `note_confort` DECIMAL(3,1) DEFAULT NULL,
  `note_rapport_qualite_prix` DECIMAL(3,1) DEFAULT NULL,
  `note_lit` DECIMAL(3,1) DEFAULT NULL COMMENT 'Catégorie supplémentaire optionnelle',
  `commentaire_positif` TEXT DEFAULT NULL,
  `commentaire_negatif` TEXT DEFAULT NULL,
  `commentaire_general` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_numero_reservation` (`numero_reservation`),
  KEY `idx_reservation_id` (`reservation_id`),
  KEY `idx_logement_id` (`logement_id`),
  KEY `idx_date_avis` (`date_avis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
