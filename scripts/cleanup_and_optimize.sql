-- =============================================================================
-- SCRIPT DE NETTOYAGE ET OPTIMISATION — BDD Unifiée frenchyconciergerie
-- Date : 2026-02-24
-- =============================================================================
-- Ce script :
-- 1. Supprime les tables Gammu legacy
-- 2. Ajoute les indexes de performance manquants
-- 3. Ajoute les colonnes manquantes pour la consolidation
-- =============================================================================

SET FOREIGN_KEY_CHECKS=0;

-- =============================================================================
-- ÉTAPE 1 : Suppression des tables Gammu legacy
-- (remplacées par sms_in, sms_out, sms_outbox)
-- =============================================================================

DROP TABLE IF EXISTS gammu;
DROP TABLE IF EXISTS inbox;
DROP TABLE IF EXISTS outbox;
DROP TABLE IF EXISTS outbox_multipart;
DROP TABLE IF EXISTS sentitems;
DROP TABLE IF EXISTS phones;

-- =============================================================================
-- ÉTAPE 2 : Colonnes manquantes pour la consolidation
-- =============================================================================

-- liste_logements : colonnes du Raspberry Pi
ALTER TABLE liste_logements
    ADD COLUMN IF NOT EXISTS `description` TEXT DEFAULT NULL AFTER `nom_du_logement`,
    ADD COLUMN IF NOT EXISTS `actif` TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS `ville_id` INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ics_url` TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ics_url_2` TEXT DEFAULT NULL;

-- reservation : flags SMS custom
ALTER TABLE reservation
    ADD COLUMN IF NOT EXISTS `custom1_sent` TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `custom2_sent` TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `custom3_sent` TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `custom4_sent` TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `custom5_sent` TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `mid_sent` TINYINT(4) DEFAULT 0;

-- =============================================================================
-- ÉTAPE 3 : Indexes de performance
-- =============================================================================

-- === PLANNING ===
CREATE INDEX IF NOT EXISTS idx_planning_date ON planning(date);
CREATE INDEX IF NOT EXISTS idx_planning_logement ON planning(logement_id);
CREATE INDEX IF NOT EXISTS idx_planning_statut ON planning(statut);
CREATE INDEX IF NOT EXISTS idx_planning_date_logement ON planning(date, logement_id);

-- === COMPTABILITE ===
CREATE INDEX IF NOT EXISTS idx_comptabilite_date ON comptabilite(date_comptabilisation);
CREATE INDEX IF NOT EXISTS idx_comptabilite_type ON comptabilite(type);
CREATE INDEX IF NOT EXISTS idx_comptabilite_intervenant ON comptabilite(intervenant_id);

-- === RESERVATION ===
CREATE INDEX IF NOT EXISTS idx_reservation_logement ON reservation(logement_id);
CREATE INDEX IF NOT EXISTS idx_reservation_dates ON reservation(date_arrivee, date_depart);
CREATE INDEX IF NOT EXISTS idx_reservation_statut ON reservation(statut);
CREATE INDEX IF NOT EXISTS idx_reservation_telephone ON reservation(telephone);
CREATE INDEX IF NOT EXISTS idx_reservation_plateforme ON reservation(plateforme);

-- === LISTE_LOGEMENTS ===
CREATE INDEX IF NOT EXISTS idx_logements_actif ON liste_logements(actif);
CREATE INDEX IF NOT EXISTS idx_logements_nom ON liste_logements(nom_du_logement);

-- === SMS ===
CREATE INDEX IF NOT EXISTS idx_sms_in_sender ON sms_in(sender);
CREATE INDEX IF NOT EXISTS idx_sms_in_date ON sms_in(received_at);
CREATE INDEX IF NOT EXISTS idx_sms_out_receiver ON sms_out(receiver);
CREATE INDEX IF NOT EXISTS idx_sms_out_date ON sms_out(sent_at);
CREATE INDEX IF NOT EXISTS idx_sms_outbox_status ON sms_outbox(status);

-- === CLIENTS ===
CREATE INDEX IF NOT EXISTS idx_clients_telephone ON clients(telephone);
CREATE INDEX IF NOT EXISTS idx_clients_email ON clients(email);

-- === ICAL ===
CREATE INDEX IF NOT EXISTS idx_ical_reservations_listing ON ical_reservations(listing_id);
CREATE INDEX IF NOT EXISTS idx_ical_reservations_dates ON ical_reservations(start_date, end_date);
CREATE INDEX IF NOT EXISTS idx_ical_reservations_connection ON ical_reservations(connection_id);

-- === SUPERHOTE ===
CREATE INDEX IF NOT EXISTS idx_superhote_config_logement ON superhote_config(logement_id);
CREATE INDEX IF NOT EXISTS idx_superhote_updates_status ON superhote_price_updates(status);
CREATE INDEX IF NOT EXISTS idx_superhote_updates_logement ON superhote_price_updates(logement_id);
CREATE INDEX IF NOT EXISTS idx_superhote_history_logement ON superhote_price_history(logement_id);

-- === MARKET ===
CREATE INDEX IF NOT EXISTS idx_market_prices_competitor ON market_prices(competitor_id);
CREATE INDEX IF NOT EXISTS idx_market_prices_date ON market_prices(date_collected);
CREATE INDEX IF NOT EXISTS idx_market_competitors_logement ON market_competitors(logement_id);

-- === CONVERSATIONS ===
CREATE INDEX IF NOT EXISTS idx_conversations_phone ON conversations(phone);
CREATE INDEX IF NOT EXISTS idx_conversations_updated ON conversations(updated_at);
CREATE INDEX IF NOT EXISTS idx_conversation_messages_conv ON conversation_messages(conversation_id);

-- === INVENTAIRE ===
CREATE INDEX IF NOT EXISTS idx_inventaire_objets_session ON inventaire_objets(session_id);
CREATE INDEX IF NOT EXISTS idx_inventaire_objets_logement ON inventaire_objets(logement_id);
CREATE INDEX IF NOT EXISTS idx_sessions_inventaire_logement ON sessions_inventaire(logement_id);

-- === INTERVENANT ===
CREATE INDEX IF NOT EXISTS idx_intervenant_nom_utilisateur ON intervenant(nom_utilisateur);

-- =============================================================================
-- ÉTAPE 4 : Nettoyage des doublons de tables inventaire
-- =============================================================================

-- Vérifier si les deux tables existent et consolider
-- (sessions_inventaire est la version utilisée par le code)
-- inventaire_sessions peut être supprimée si elle est vide ou dupliquée

-- =============================================================================
-- ÉTAPE 5 : Tables de configuration pour l'app unifiée
-- =============================================================================

-- Table pour stocker les pages du menu avec catégories
ALTER TABLE pages
    ADD COLUMN IF NOT EXISTS `categorie` VARCHAR(50) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `icone` VARCHAR(50) DEFAULT NULL;

-- Insérer les nouvelles pages (modules ex-Raspberry Pi)
INSERT IGNORE INTO pages (nom, chemin, afficher_menu, categorie, icone) VALUES
    ('Réservations', 'pages/reservations.php', 1, 'reservations', 'fa-calendar-check'),
    ('Sync iCal', 'pages/sync_ical.php', 1, 'reservations', 'fa-sync-alt'),
    ('Occupation', 'pages/occupation.php', 1, 'reservations', 'fa-chart-pie'),
    ('SMS reçus', 'pages/sms_recus.php', 1, 'sms', 'fa-inbox'),
    ('Envoyer SMS', 'pages/sms_envoyer.php', 1, 'sms', 'fa-paper-plane'),
    ('Templates SMS', 'pages/sms_templates.php', 1, 'sms', 'fa-file-alt'),
    ('Automatisations SMS', 'pages/sms_automations.php', 1, 'sms', 'fa-robot'),
    ('Campagnes SMS', 'pages/sms_campagnes.php', 1, 'sms', 'fa-bullhorn'),
    ('Superhôte', 'pages/superhote.php', 1, 'outils', 'fa-euro-sign'),
    ('Analyse de marché', 'pages/analyse_marche.php', 1, 'outils', 'fa-chart-line'),
    ('Concurrence', 'pages/analyse_concurrence.php', 1, 'outils', 'fa-chart-bar'),
    ('Clients', 'pages/clients.php', 1, 'outils', 'fa-address-book'),
    ('Villes', 'pages/villes.php', 1, 'outils', 'fa-city');

SET FOREIGN_KEY_CHECKS=1;

-- =============================================================================
-- ANALYSE DES TABLES
-- =============================================================================
ANALYZE TABLE planning;
ANALYZE TABLE comptabilite;
ANALYZE TABLE liste_logements;
ANALYZE TABLE reservation;
ANALYZE TABLE sms_in;
ANALYZE TABLE sms_out;

SELECT 'Nettoyage et optimisation terminés avec succès !' AS statut;
