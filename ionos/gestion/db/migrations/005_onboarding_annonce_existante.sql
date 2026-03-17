-- Migration 005: Ajouter les champs annonce existante a onboarding_requests
-- Collecte si le proprio a deja une annonce en ligne (Airbnb, Booking, etc.)

ALTER TABLE `onboarding_requests`
    ADD COLUMN `annonce_existante` TINYINT(1) DEFAULT 0 AFTER `photos`,
    ADD COLUMN `annonce_plateformes` JSON DEFAULT NULL AFTER `annonce_existante`,
    ADD COLUMN `annonce_url_airbnb` VARCHAR(500) DEFAULT NULL AFTER `annonce_plateformes`,
    ADD COLUMN `annonce_url_booking` VARCHAR(500) DEFAULT NULL AFTER `annonce_url_airbnb`,
    ADD COLUMN `annonce_url_autre` VARCHAR(500) DEFAULT NULL AFTER `annonce_url_booking`,
    ADD COLUMN `experience_location` VARCHAR(50) DEFAULT NULL AFTER `annonce_url_autre`;
