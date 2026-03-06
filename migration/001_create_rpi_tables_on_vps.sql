-- ============================================================================
-- Migration 001 : Créer les tables RPi sur le VPS
-- Objectif : Consolider la base SMS/réservations du Raspberry Pi sur le VPS
--
-- IMPORTANT : Exécuter ce script sur la base de données VPS (IONOS)
-- Les tables sont créées avec IF NOT EXISTS pour être idempotent
--
-- Tables exclues :
--   - gammu, inbox, outbox, outbox_multipart, sentitems, phones (legacy Gammu)
--   - logement (doublon de liste_logements)
--   - poids_critere (doublon de poids_criteres)
--
-- Date : 2026-03-06
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- 1. TABLES DE CONFIGURATION
-- ============================================================================

-- Prompts IA (satisfaction bot, suggestions SMS)
CREATE TABLE IF NOT EXISTS `ai_prompts` (
  `key` varchar(50) NOT NULL,
  `content` text NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Configuration clé/valeur
CREATE TABLE IF NOT EXISTS `config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `type` enum('string','int','float','bool','json') DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modems GSM enregistrés
CREATE TABLE IF NOT EXISTS `modem` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `port` varchar(20) NOT NULL,
  `etat` enum('actif','inactif') DEFAULT 'actif',
  PRIMARY KEY (`id`),
  UNIQUE KEY `port` (`port`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table users (authentification interface RPi)
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` varchar(50) DEFAULT 'user',
  `nom` varchar(255) DEFAULT NULL,
  `prenom` varchar(255) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. TABLES SMS
-- ============================================================================

-- SMS reçus
CREATE TABLE IF NOT EXISTS `sms_in` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `sender` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `modem` varchar(20) NOT NULL,
  `auto_replied` tinyint(4) DEFAULT 0,
  `ai_handled` tinyint(4) DEFAULT 0,
  `logement_id` int(11) DEFAULT NULL,
  `fallback` tinyint(4) NOT NULL DEFAULT 0,
  `conversation_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0 COMMENT 'Message lu (0=non, 1=oui)',
  `archived` tinyint(1) DEFAULT 0 COMMENT 'Message archivé',
  `starred` tinyint(1) DEFAULT 0 COMMENT 'Message marqué important',
  `tags` varchar(255) DEFAULT NULL COMMENT 'Tags séparés par virgule',
  `notes` text DEFAULT NULL COMMENT 'Notes internes',
  PRIMARY KEY (`id`),
  KEY `idx_sender` (`sender`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_received_at` (`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- SMS envoyés (historique)
CREATE TABLE IF NOT EXISTS `sms_out` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receiver` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `modem` varchar(20) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- File d'attente SMS (le daemon RPi lit cette table)
CREATE TABLE IF NOT EXISTS `sms_outbox` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receiver` varchar(20) NOT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `gammu_outbox_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `message` text NOT NULL,
  `modem` varchar(64) NOT NULL DEFAULT '/dev/ttyUSB0',
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `conversation_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sms_outbox_reservation_id` (`reservation_id`),
  KEY `idx_sms_outbox_receiver` (`receiver`),
  KEY `idx_sms_outbox_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Messages SMS (legacy, ancien format)
CREATE TABLE IF NOT EXISTS `sms_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `date_reception` datetime DEFAULT NULL,
  `modem` varchar(20) DEFAULT NULL,
  `date_enregistrement` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Templates SMS
CREATE TABLE IF NOT EXISTS `sms_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `campaign` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `template` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_campaign` (`campaign`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Métadonnées conversations SMS
CREATE TABLE IF NOT EXISTS `sms_conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone_number` varchar(20) NOT NULL COMMENT 'Numéro de téléphone',
  `contact_name` varchar(100) DEFAULT NULL COMMENT 'Nom du contact',
  `last_message_at` datetime DEFAULT NULL COMMENT 'Date du dernier message',
  `unread_count` int(11) DEFAULT 0 COMMENT 'Nombre de messages non lus',
  `archived` tinyint(1) DEFAULT 0 COMMENT 'Conversation archivée',
  `muted` tinyint(1) DEFAULT 0 COMMENT 'Notifications désactivées',
  `reservation_id` int(11) DEFAULT NULL COMMENT 'ID réservation liée',
  `notes` text DEFAULT NULL COMMENT 'Notes sur le contact',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone_number` (`phone_number`),
  KEY `idx_last_message` (`last_message_at`),
  KEY `idx_reservation` (`reservation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Métadonnées des conversations SMS';

-- Conversations satisfaction bot
CREATE TABLE IF NOT EXISTS `satisfaction_conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender` varchar(20) NOT NULL,
  `logement_id` int(11) DEFAULT NULL,
  `role` enum('user','assistant') NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- 3. TABLES CONVERSATIONS
-- ============================================================================

-- Conversations (regroupement de messages par numéro)
CREATE TABLE IF NOT EXISTS `conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone_e164` varchar(32) NOT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `status` enum('open','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone_e164`),
  KEY `idx_res` (`reservation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Messages dans les conversations
CREATE TABLE IF NOT EXISTS `conversation_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `direction` enum('in','out') NOT NULL,
  `message` text NOT NULL,
  `modem` varchar(64) DEFAULT NULL,
  `sms_in_id` int(11) DEFAULT NULL,
  `sms_out_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_conv` (`conversation_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- 4. TABLES CONTACTS & CLIENTS
-- ============================================================================

-- Clients (voyageurs)
CREATE TABLE IF NOT EXISTS `client` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prenom` varchar(50) NOT NULL,
  `nom` varchar(50) DEFAULT NULL,
  `telephone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `date_inscription` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `telephone` (`telephone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Contacts téléphoniques
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_telephone` varchar(20) NOT NULL,
  `nom` varchar(255) DEFAULT NULL,
  `prenom` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `logement_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_telephone` (`numero_telephone`),
  KEY `idx_logement` (`logement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. TABLES CAMPAGNES
-- ============================================================================

-- Campagnes SMS
CREATE TABLE IF NOT EXISTS `campagne_sms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `type` enum('satisfaction','immo','marketing','rappel') DEFAULT 'satisfaction',
  `message_template` text NOT NULL,
  `cible` text DEFAULT NULL COMMENT 'Criteres de ciblage en JSON',
  `statut` enum('brouillon','active','terminee','archivee') DEFAULT 'brouillon',
  `date_envoi` datetime DEFAULT NULL,
  `nb_envoyes` int(11) DEFAULT 0,
  `nb_reponses` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campagnes immobilières (prospection)
CREATE TABLE IF NOT EXISTS `campagne_immo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date_envoi` datetime DEFAULT current_timestamp(),
  `nom` varchar(100) DEFAULT NULL,
  `prenom` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `titre` varchar(100) DEFAULT NULL,
  `code_postal` varchar(10) DEFAULT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `statut_contact` enum('en_attente','en_cours','rdv_pris','sans_reponse','refus') DEFAULT 'en_attente',
  `conversation_ia` text DEFAULT NULL,
  `date_rdv` date DEFAULT NULL,
  `heure_rdv` time DEFAULT NULL,
  `agenda_event_id` varchar(255) DEFAULT NULL,
  `sms_init_envoye` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `telephone` (`telephone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- 6. TABLES SCÉNARIOS / AUTOMATISATION
-- ============================================================================

-- Scénarios SMS (templates d'automatisation)
CREATE TABLE IF NOT EXISTS `scenario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `regles` text DEFAULT NULL,
  `message_modele` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Scénarios IA par logement
CREATE TABLE IF NOT EXISTS `ia_scenario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `logement_id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `regles` text NOT NULL,
  `message_modele` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Liaison client-scénario par réservation
CREATE TABLE IF NOT EXISTS `client_scenario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` int(11) NOT NULL,
  `scenario_id` int(11) NOT NULL,
  `executed` tinyint(4) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `reservation_id` (`reservation_id`),
  KEY `scenario_id` (`scenario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- 7. TABLES TRAVEL / iCAL
-- ============================================================================

-- Plateformes de réservation (Airbnb, Booking, etc.)
CREATE TABLE IF NOT EXISTS `travel_platforms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Connexions aux comptes de voyage
CREATE TABLE IF NOT EXISTS `travel_account_connections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `platform_id` int(11) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `api_key` varchar(500) DEFAULT NULL,
  `api_secret` varchar(500) DEFAULT NULL,
  `access_token` text DEFAULT NULL,
  `ical_url` varchar(1000) DEFAULT NULL,
  `refresh_token` text DEFAULT NULL,
  `account_email` varchar(255) DEFAULT NULL,
  `account_id` varchar(100) DEFAULT NULL,
  `is_connected` tinyint(1) DEFAULT 0,
  `last_sync_at` timestamp NULL DEFAULT NULL,
  `ical_last_sync` timestamp NULL DEFAULT NULL,
  `ical_sync_status` enum('never','success','error') DEFAULT 'never',
  `ical_error_message` text DEFAULT NULL,
  `connection_status` enum('pending','connected','error','disconnected') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_platform` (`platform_id`),
  KEY `idx_status` (`connection_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Annonces sur les plateformes
CREATE TABLE IF NOT EXISTS `travel_listings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `connection_id` int(11) NOT NULL,
  `platform_listing_id` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `price_per_night` decimal(10,2) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'EUR',
  `bedrooms` int(11) DEFAULT NULL,
  `bathrooms` int(11) DEFAULT NULL,
  `max_guests` int(11) DEFAULT NULL,
  `property_type` varchar(50) DEFAULT NULL,
  `listing_url` varchar(500) DEFAULT NULL,
  `ical_url` varchar(1000) DEFAULT NULL,
  `ical_last_sync` timestamp NULL DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_synced_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `metadata` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_listing` (`connection_id`,`platform_listing_id`),
  KEY `idx_connection` (`connection_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Réservations importées via iCal
CREATE TABLE IF NOT EXISTS `ical_reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `connection_id` int(11) NOT NULL,
  `listing_id` int(11) DEFAULT NULL,
  `ical_uid` varchar(255) NOT NULL,
  `summary` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `guest_name` varchar(255) DEFAULT NULL,
  `guest_email` varchar(255) DEFAULT NULL,
  `guest_phone` varchar(50) DEFAULT NULL,
  `status` enum('confirmed','pending','cancelled','blocked') DEFAULT 'confirmed',
  `is_blocked` tinyint(1) DEFAULT 0,
  `platform_reservation_id` varchar(100) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'EUR',
  `num_guests` int(11) DEFAULT NULL,
  `num_nights` int(11) DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `imported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ical_event` (`connection_id`,`ical_uid`),
  KEY `idx_dates` (`start_date`,`end_date`),
  KEY `idx_listing` (`listing_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal de synchronisation iCal
CREATE TABLE IF NOT EXISTS `ical_sync_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `connection_id` int(11) NOT NULL,
  `ical_url` varchar(1000) NOT NULL,
  `sync_status` enum('success','error','partial') NOT NULL,
  `events_found` int(11) DEFAULT 0,
  `events_imported` int(11) DEFAULT 0,
  `events_updated` int(11) DEFAULT 0,
  `events_skipped` int(11) DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `raw_ical_data` longtext DEFAULT NULL,
  `sync_duration_ms` int(11) DEFAULT NULL,
  `synced_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_connection` (`connection_id`),
  KEY `idx_sync_date` (`synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mapping entre annonces (multi-plateforme)
CREATE TABLE IF NOT EXISTS `listing_mappings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_listing_id` int(11) DEFAULT NULL,
  `airbnb_listing_id` int(11) DEFAULT NULL,
  `booking_listing_id` int(11) DEFAULT NULL,
  `direct_listing_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_master` (`master_listing_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. TABLES UTILITAIRES
-- ============================================================================

-- Journal de synchronisation inter-bases
CREATE TABLE IF NOT EXISTS `sync_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `direction` enum('raspberry_to_ionos','ionos_to_raspberry','bidirectional') NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `nb_records` int(11) DEFAULT 0,
  `status` enum('success','partial','failed') DEFAULT 'success',
  `error_message` text DEFAULT NULL,
  `duration_ms` int(11) DEFAULT NULL,
  `started_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_table` (`table_name`),
  KEY `idx_status` (`status`),
  KEY `idx_started` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- FIN DE LA MIGRATION 001
-- ============================================================================
--
-- Prochaines étapes :
-- 1. Exporter les données du RPi : mysqldump -u root -p frenchyconciergerie \
--      ai_prompts campagne_immo campagne_sms client client_scenario config \
--      contacts conversation_messages conversations ia_scenario ical_reservations \
--      ical_sync_log listing_mappings modem satisfaction_conversations scenario \
--      sms_conversations sms_in sms_messages sms_out sms_outbox sms_templates \
--      sync_log travel_account_connections travel_listings travel_platforms users \
--      --no-create-info > rpi_data_export.sql
--
-- 2. Importer sur le VPS : mysql -u frenchy_app -p frenchyconciergerie < rpi_data_export.sql
--
-- 3. Vérifier la table 'reservation' : elle existe déjà sur le VPS,
--    comparer les schémas et migrer les données manquantes si nécessaire
-- ============================================================================
