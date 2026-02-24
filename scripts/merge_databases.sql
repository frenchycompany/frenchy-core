-- =============================================================================
-- SCRIPT DE FUSION DES BASES DE DONNÉES
-- frenchyconciergerie (VPS) = IONOS + sms_db_import
-- Date : 2026-02-24
-- =============================================================================

SET FOREIGN_KEY_CHECKS=0;

-- =============================================================================
-- ÉTAPE 1 : Compléter les tables existantes avec les colonnes manquantes
-- =============================================================================

-- liste_logements : ajouter description, actif, ville_id (présents dans sms_db)
ALTER TABLE frenchyconciergerie.liste_logements
  ADD COLUMN IF NOT EXISTS `description` text DEFAULT NULL AFTER `nom_du_logement`,
  ADD COLUMN IF NOT EXISTS `actif` tinyint(1) NOT NULL DEFAULT 1 AFTER `valeur_fonciere`,
  ADD COLUMN IF NOT EXISTS `ville_id` int(11) DEFAULT NULL AFTER `actif`;

-- reservation : ajouter les flags custom (présents dans sms_db)
ALTER TABLE frenchyconciergerie.reservation
  ADD COLUMN IF NOT EXISTS `custom1_sent` tinyint(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `custom2_sent` tinyint(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `custom3_sent` tinyint(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `custom4_sent` tinyint(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `custom5_sent` tinyint(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `mid_sent` tinyint(4) DEFAULT 0;

-- sms_templates : ajouter description, rendre campaign nullable
ALTER TABLE frenchyconciergerie.sms_templates
  ADD COLUMN IF NOT EXISTS `description` text DEFAULT NULL AFTER `template`,
  MODIFY COLUMN `campaign` varchar(50) DEFAULT 'default';

-- =============================================================================
-- ÉTAPE 2 : Créer les tables UNIQUES à sms_db dans frenchyconciergerie
-- =============================================================================

-- Villes
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`villes` LIKE sms_db_import.`villes`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`ville_recommandations` LIKE sms_db_import.`ville_recommandations`;

-- Équipements logements
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`logement_equipements` LIKE sms_db_import.`logement_equipements`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`logement_recommandations` LIKE sms_db_import.`logement_recommandations`;

-- Clients (table étendue)
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`clients` LIKE sms_db_import.`clients`;

-- SMS avancé
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`sms_automations` LIKE sms_db_import.`sms_automations`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`sms_campaigns` LIKE sms_db_import.`sms_campaigns`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`sms_campaign_recipients` LIKE sms_db_import.`sms_campaign_recipients`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`sms_logement_templates` LIKE sms_db_import.`sms_logement_templates`;

-- Agents
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`agent_users` LIKE sms_db_import.`agent_users`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`agent_action_rates` LIKE sms_db_import.`agent_action_rates`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`agent_actions` LIKE sms_db_import.`agent_actions`;

-- Analyse de marché
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`market_competitors` LIKE sms_db_import.`market_competitors`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`market_competitor_mapping` LIKE sms_db_import.`market_competitor_mapping`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`market_prices` LIKE sms_db_import.`market_prices`;

-- Superhote
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`superhote_credentials` LIKE sms_db_import.`superhote_credentials`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`superhote_groups` LIKE sms_db_import.`superhote_groups`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`superhote_config` LIKE sms_db_import.`superhote_config`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`superhote_pricing_rules` LIKE sms_db_import.`superhote_pricing_rules`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`superhote_rule_logements` LIKE sms_db_import.`superhote_rule_logements`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`superhote_seasons` LIKE sms_db_import.`superhote_seasons`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`superhote_holidays` LIKE sms_db_import.`superhote_holidays`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`superhote_occupancy_rules` LIKE sms_db_import.`superhote_occupancy_rules`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`superhote_price_updates` LIKE sms_db_import.`superhote_price_updates`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`superhote_price_history` LIKE sms_db_import.`superhote_price_history`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`superhote_automation_logs` LIKE sms_db_import.`superhote_automation_logs`;
CREATE TABLE IF NOT EXISTS frenchyconciergerie.`superhote_settings` LIKE sms_db_import.`superhote_settings`;

-- =============================================================================
-- ÉTAPE 3 : Migrer les données depuis sms_db_import
-- =============================================================================

-- Villes
INSERT IGNORE INTO frenchyconciergerie.villes SELECT * FROM sms_db_import.villes;
INSERT IGNORE INTO frenchyconciergerie.ville_recommandations SELECT * FROM sms_db_import.ville_recommandations;

-- Équipements logements
INSERT IGNORE INTO frenchyconciergerie.logement_equipements SELECT * FROM sms_db_import.logement_equipements;
INSERT IGNORE INTO frenchyconciergerie.logement_recommandations SELECT * FROM sms_db_import.logement_recommandations;

-- Clients
INSERT IGNORE INTO frenchyconciergerie.clients SELECT * FROM sms_db_import.clients;

-- Réservations (merger les données sms_db, plus complètes)
INSERT INTO frenchyconciergerie.reservation
SELECT * FROM sms_db_import.reservation
ON DUPLICATE KEY UPDATE
  statut = VALUES(statut),
  custom1_sent = VALUES(custom1_sent),
  custom2_sent = VALUES(custom2_sent),
  custom3_sent = VALUES(custom3_sent),
  custom4_sent = VALUES(custom4_sent),
  custom5_sent = VALUES(custom5_sent),
  mid_sent = VALUES(mid_sent),
  j1_sent = VALUES(j1_sent),
  dep_sent = VALUES(dep_sent),
  start_sent = VALUES(start_sent);

-- Clients simples
INSERT INTO frenchyconciergerie.client SELECT * FROM sms_db_import.client
ON DUPLICATE KEY UPDATE nom = VALUES(nom), email = VALUES(email);

-- SMS entrants/sortants
INSERT IGNORE INTO frenchyconciergerie.sms_in
  SELECT id, NULL as created_at, sender, message, received_at, modem, auto_replied, ai_handled,
         logement_id, fallback, conversation_id, is_read, archived, starred, tags, notes
  FROM sms_db_import.sms_in;

INSERT IGNORE INTO frenchyconciergerie.sms_out SELECT * FROM sms_db_import.sms_out;
INSERT IGNORE INTO frenchyconciergerie.sms_outbox SELECT * FROM sms_db_import.sms_outbox;
INSERT IGNORE INTO frenchyconciergerie.sms_messages
  SELECT id, NULL as created_at, numero, message, date_reception, modem, date_enregistrement
  FROM sms_db_import.sms_messages;

-- Templates SMS
INSERT INTO frenchyconciergerie.sms_templates (campaign, name, template, description, created_at, updated_at)
SELECT campaign, name, template, description, created_at, updated_at
FROM sms_db_import.sms_templates
ON DUPLICATE KEY UPDATE template = VALUES(template), description = VALUES(description);

-- Conversations
INSERT INTO frenchyconciergerie.conversations SELECT * FROM sms_db_import.conversations
ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = VALUES(updated_at);

INSERT IGNORE INTO frenchyconciergerie.conversation_messages SELECT * FROM sms_db_import.conversation_messages;
INSERT IGNORE INTO frenchyconciergerie.sms_conversations SELECT * FROM sms_db_import.sms_conversations;

-- iCal
INSERT INTO frenchyconciergerie.travel_platforms SELECT * FROM sms_db_import.travel_platforms
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO frenchyconciergerie.travel_account_connections SELECT * FROM sms_db_import.travel_account_connections
ON DUPLICATE KEY UPDATE connection_status = VALUES(connection_status), last_sync_at = VALUES(last_sync_at);

INSERT IGNORE INTO frenchyconciergerie.travel_listings SELECT * FROM sms_db_import.travel_listings;

INSERT INTO frenchyconciergerie.ical_reservations SELECT * FROM sms_db_import.ical_reservations
ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = VALUES(updated_at);

INSERT IGNORE INTO frenchyconciergerie.ical_sync_log SELECT * FROM sms_db_import.ical_sync_log;
INSERT IGNORE INTO frenchyconciergerie.listing_mappings SELECT * FROM sms_db_import.listing_mappings;

-- Scénarios IA
INSERT INTO frenchyconciergerie.scenario SELECT * FROM sms_db_import.scenario
ON DUPLICATE KEY UPDATE regles = VALUES(regles), message_modele = VALUES(message_modele);

INSERT IGNORE INTO frenchyconciergerie.ia_scenario SELECT * FROM sms_db_import.ia_scenario;
INSERT IGNORE INTO frenchyconciergerie.client_scenario SELECT * FROM sms_db_import.client_scenario;
INSERT IGNORE INTO frenchyconciergerie.satisfaction_conversations SELECT * FROM sms_db_import.satisfaction_conversations;

-- Config et prompts IA
INSERT INTO frenchyconciergerie.ai_prompts SELECT * FROM sms_db_import.ai_prompts
ON DUPLICATE KEY UPDATE content = VALUES(content);

INSERT INTO frenchyconciergerie.configuration SELECT * FROM sms_db_import.configuration
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- Campagnes
INSERT INTO frenchyconciergerie.campagne_immo SELECT * FROM sms_db_import.campagne_immo
ON DUPLICATE KEY UPDATE statut_contact = VALUES(statut_contact), updated_at = VALUES(updated_at);

-- SMS avancé (tables uniques sms_db)
INSERT IGNORE INTO frenchyconciergerie.sms_automations SELECT * FROM sms_db_import.sms_automations;
INSERT IGNORE INTO frenchyconciergerie.sms_campaigns SELECT * FROM sms_db_import.sms_campaigns;
INSERT IGNORE INTO frenchyconciergerie.sms_campaign_recipients SELECT * FROM sms_db_import.sms_campaign_recipients;
INSERT IGNORE INTO frenchyconciergerie.sms_logement_templates SELECT * FROM sms_db_import.sms_logement_templates;

-- Agents
INSERT IGNORE INTO frenchyconciergerie.agent_users SELECT * FROM sms_db_import.agent_users;
INSERT IGNORE INTO frenchyconciergerie.agent_action_rates SELECT * FROM sms_db_import.agent_action_rates;
INSERT IGNORE INTO frenchyconciergerie.agent_actions SELECT * FROM sms_db_import.agent_actions;

-- Analyse de marché
INSERT IGNORE INTO frenchyconciergerie.market_competitors SELECT * FROM sms_db_import.market_competitors;
INSERT IGNORE INTO frenchyconciergerie.market_competitor_mapping SELECT * FROM sms_db_import.market_competitor_mapping;
INSERT IGNORE INTO frenchyconciergerie.market_prices SELECT * FROM sms_db_import.market_prices;

-- Superhote
INSERT IGNORE INTO frenchyconciergerie.superhote_credentials SELECT * FROM sms_db_import.superhote_credentials;
INSERT IGNORE INTO frenchyconciergerie.superhote_groups SELECT * FROM sms_db_import.superhote_groups;
INSERT IGNORE INTO frenchyconciergerie.superhote_config SELECT * FROM sms_db_import.superhote_config;
INSERT IGNORE INTO frenchyconciergerie.superhote_pricing_rules SELECT * FROM sms_db_import.superhote_pricing_rules;
INSERT IGNORE INTO frenchyconciergerie.superhote_rule_logements SELECT * FROM sms_db_import.superhote_rule_logements;
INSERT IGNORE INTO frenchyconciergerie.superhote_seasons SELECT * FROM sms_db_import.superhote_seasons;
INSERT IGNORE INTO frenchyconciergerie.superhote_holidays SELECT * FROM sms_db_import.superhote_holidays;
INSERT IGNORE INTO frenchyconciergerie.superhote_occupancy_rules SELECT * FROM sms_db_import.superhote_occupancy_rules;
INSERT IGNORE INTO frenchyconciergerie.superhote_price_updates SELECT * FROM sms_db_import.superhote_price_updates;
INSERT IGNORE INTO frenchyconciergerie.superhote_price_history SELECT * FROM sms_db_import.superhote_price_history;
INSERT IGNORE INTO frenchyconciergerie.superhote_automation_logs SELECT * FROM sms_db_import.superhote_automation_logs;
INSERT INTO frenchyconciergerie.superhote_settings SELECT * FROM sms_db_import.superhote_settings
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- Modem
INSERT INTO frenchyconciergerie.modem SELECT * FROM sms_db_import.modem
ON DUPLICATE KEY UPDATE etat = VALUES(etat);

-- =============================================================================
-- ÉTAPE 4 : Supprimer les tables legacy Gammu
-- =============================================================================

DROP TABLE IF EXISTS frenchyconciergerie.gammu;
DROP TABLE IF EXISTS frenchyconciergerie.inbox;
DROP TABLE IF EXISTS frenchyconciergerie.outbox;
DROP TABLE IF EXISTS frenchyconciergerie.outbox_multipart;
DROP TABLE IF EXISTS frenchyconciergerie.sentitems;
DROP TABLE IF EXISTS frenchyconciergerie.phones;

-- =============================================================================
-- ÉTAPE 5 : Créer les vues depuis sms_db
-- =============================================================================

DROP VIEW IF EXISTS frenchyconciergerie.v_all_reservations;
CREATE VIEW frenchyconciergerie.v_all_reservations AS
SELECT `r`.`id`, `r`.`ical_uid`, `r`.`summary`, `r`.`description`, `r`.`start_date`,
  `r`.`end_date`, `r`.`guest_name`, `r`.`guest_email`, `r`.`guest_phone`, `r`.`status`,
  `r`.`is_blocked`, `r`.`platform_reservation_id`, `r`.`total_price`, `r`.`currency`,
  `r`.`num_guests`, `r`.`num_nights`,
  TO_DAYS(`r`.`end_date`) - TO_DAYS(`r`.`start_date`) AS `calculated_nights`,
  `r`.`imported_at`, `r`.`updated_at`,
  `l`.`id` AS `listing_id`, `l`.`title` AS `listing_title`, `l`.`city` AS `listing_city`,
  `l`.`platform_listing_id`, `c`.`id` AS `connection_id`, `c`.`account_name`,
  `p`.`name` AS `platform_name`, `p`.`code` AS `platform_code`, `p`.`logo_url` AS `platform_logo`,
  CASE WHEN `r`.`end_date` < CURDATE() THEN 'past'
       WHEN `r`.`start_date` > CURDATE() THEN 'upcoming'
       ELSE 'current' END AS `reservation_period`
FROM ical_reservations `r`
JOIN travel_account_connections `c` ON `r`.`connection_id` = `c`.`id`
JOIN travel_platforms `p` ON `c`.`platform_id` = `p`.`id`
LEFT JOIN travel_listings `l` ON `r`.`listing_id` = `l`.`id`
ORDER BY `r`.`start_date` DESC;

DROP VIEW IF EXISTS frenchyconciergerie.v_pending_price_updates;
CREATE VIEW frenchyconciergerie.v_pending_price_updates AS
SELECT `spu`.`id`, `spu`.`logement_id`, `l`.`nom_du_logement`, `spu`.`superhote_property_id`,
  `spu`.`date_start`, `spu`.`date_end`,
  TO_DAYS(`spu`.`date_end`) - TO_DAYS(`spu`.`date_start`) + 1 AS `days_count`,
  `spu`.`price`, `spu`.`currency`, `spu`.`status`, `spu`.`priority`, `spu`.`retry_count`,
  `spu`.`error_message`, `spu`.`scheduled_at`, `spu`.`created_at`, `spu`.`created_by`
FROM superhote_price_updates `spu`
LEFT JOIN liste_logements `l` ON `spu`.`logement_id` = `l`.`id`
WHERE `spu`.`status` IN ('pending','processing')
ORDER BY `spu`.`priority` DESC, `spu`.`created_at` ASC;

DROP VIEW IF EXISTS frenchyconciergerie.v_logement_superhote_config;
CREATE VIEW frenchyconciergerie.v_logement_superhote_config AS
SELECT `l`.`id` AS `logement_id`, `l`.`nom_du_logement`, `l`.`adresse`, `l`.`nombre_de_personnes`,
  `sc`.`id` AS `config_id`, `sc`.`superhote_property_id`, `sc`.`superhote_property_name`,
  `sc`.`is_active` AS `superhote_active`, `sc`.`default_price`, `sc`.`weekend_price`,
  `sc`.`min_price`, `sc`.`max_price`, `sc`.`auto_sync`, `sc`.`last_sync_at`,
  (SELECT COUNT(0) FROM superhote_price_updates `spu`
   WHERE `spu`.`logement_id` = `l`.`id` AND `spu`.`status` = 'pending') AS `pending_updates`,
  (SELECT MAX(`sph`.`created_at`) FROM superhote_price_history `sph`
   WHERE `sph`.`logement_id` = `l`.`id` AND `sph`.`success` = 1) AS `last_successful_update`
FROM liste_logements `l`
LEFT JOIN superhote_config `sc` ON `l`.`id` = `sc`.`logement_id`;

SET FOREIGN_KEY_CHECKS=1;

SELECT 'Fusion terminée avec succès !' AS statut;
