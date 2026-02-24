/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.5.29-MariaDB, for debian-linux-gnueabihf (armv8l)
--
-- Host: localhost    Database: frenchyconciergerie
-- ------------------------------------------------------
-- Server version	10.5.29-MariaDB-0+deb11u1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `ai_prompts`
--

DROP TABLE IF EXISTS `ai_prompts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_prompts` (
  `key` varchar(50) NOT NULL,
  `content` text NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `annexes`
--

DROP TABLE IF EXISTS `annexes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `annexes` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `annex_type` enum('Photo dossier','Inventaire') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `articles`
--

DROP TABLE IF EXISTS `articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `articles` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `thumbnail` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `campagne_immo`
--

DROP TABLE IF EXISTS `campagne_immo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `campagne_immo` (
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
) ENGINE=InnoDB AUTO_INCREMENT=674 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `campagne_sms`
--

DROP TABLE IF EXISTS `campagne_sms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `campagne_sms` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client`
--

DROP TABLE IF EXISTS `client`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `client` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prenom` varchar(50) NOT NULL,
  `nom` varchar(50) DEFAULT NULL,
  `telephone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `date_inscription` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `telephone` (`telephone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_scenario`
--

DROP TABLE IF EXISTS `client_scenario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_scenario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` int(11) NOT NULL,
  `scenario_id` int(11) NOT NULL,
  `executed` tinyint(4) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `reservation_id` (`reservation_id`),
  KEY `scenario_id` (`scenario_id`),
  CONSTRAINT `client_scenario_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservation` (`id`),
  CONSTRAINT `client_scenario_ibfk_2` FOREIGN KEY (`scenario_id`) REFERENCES `scenario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comptabilite`
--

DROP TABLE IF EXISTS `comptabilite`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comptabilite` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `intervenant_id` int(11) DEFAULT NULL,
  `type` enum('Recette','Charge') DEFAULT 'Charge',
  `categorie` varchar(100) DEFAULT NULL,
  `montant` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `source_type` varchar(50) DEFAULT NULL COMMENT 'planning, reservation, etc.',
  `source_id` int(11) DEFAULT NULL COMMENT 'ID de la source',
  `date_comptabilisation` date NOT NULL,
  `moyen_paiement` enum('especes','virement','cheque','cb','autre') DEFAULT NULL,
  `statut` enum('en_attente','valide','paye') DEFAULT 'en_attente',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_intervenant` (`intervenant_id`),
  KEY `idx_type` (`type`),
  KEY `idx_date` (`date_comptabilisation`),
  KEY `idx_statut` (`statut`),
  KEY `idx_comptabilite_date_intervenant` (`date_comptabilisation`,`intervenant_id`),
  CONSTRAINT `fk_comptabilite_intervenant` FOREIGN KEY (`intervenant_id`) REFERENCES `intervenant` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=368299 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `config`
--

DROP TABLE IF EXISTS `config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `type` enum('string','int','float','bool','json') DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`),
  UNIQUE KEY `idx_key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `configuration`
--

DROP TABLE IF EXISTS `configuration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuration` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key_name` varchar(50) NOT NULL,
  `value` text NOT NULL,
  `nom_site` varchar(255) DEFAULT 'Frenchyconciergerie' COMMENT 'Nom du site',
  `email_contact` varchar(255) NOT NULL,
  `mode_maintenance` tinyint(1) NOT NULL DEFAULT 0,
  `footer_text` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_name` (`key_name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contacts` (
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
  KEY `idx_numero` (`numero_telephone`),
  KEY `idx_logement` (`logement_id`)
) ENGINE=InnoDB AUTO_INCREMENT=293 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contract_entries`
--

DROP TABLE IF EXISTS `contract_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_entries` (
  `id` int(11) NOT NULL,
  `logement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `field_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`field_data`)),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contract_fields`
--

DROP TABLE IF EXISTS `contract_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_fields` (
  `id` int(11) NOT NULL,
  `field_name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `input_type` enum('text','number','textarea','date','select') NOT NULL DEFAULT 'text',
  `options` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contract_templates`
--

DROP TABLE IF EXISTS `contract_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_templates` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `placeholders` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `template_content` longtext DEFAULT NULL,
  `template_html` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contracts`
--

DROP TABLE IF EXISTS `contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `logement_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conversation_messages`
--

DROP TABLE IF EXISTS `conversation_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_messages` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conversations`
--

DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversations` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `description_logements`
--

DROP TABLE IF EXISTS `description_logements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `description_logements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `logement_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `equipements` text DEFAULT NULL,
  `acces` text DEFAULT NULL,
  `reglement` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `superficie_m2` float NOT NULL,
  `nombre_pieces` int(11) NOT NULL,
  `nombre_marches` int(11) DEFAULT 0,
  `nombre_tables` int(11) DEFAULT 0,
  `nombre_plans_travail` int(11) DEFAULT 0,
  `nombre_lavabos` int(11) DEFAULT 0,
  `nombre_tables_basses` int(11) DEFAULT 0,
  `nombre_tables_chevets` int(11) DEFAULT 0,
  `nombre_frigos` int(11) DEFAULT 0,
  `nombre_couchages` int(11) DEFAULT 0,
  `nombre_tiroirs` int(11) DEFAULT 0,
  `nombre_wcs` int(11) DEFAULT 0,
  `nombre_douches` int(11) DEFAULT 0,
  `nombre_miroirs` int(11) DEFAULT 0,
  `nombre_vitres` int(11) DEFAULT 0,
  `nombre_objets_deco` int(11) DEFAULT 0,
  `nombre_vaisselle` int(11) DEFAULT 0,
  `nombre_televisions` int(11) DEFAULT 0,
  `nombre_electromenagers` int(11) DEFAULT 0,
  `multiplicateur` float DEFAULT 1,
  `commentaire` text DEFAULT NULL,
  `temps_passe_moyen` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_logement` (`logement_id`),
  CONSTRAINT `description_logements_ibfk_1` FOREIGN KEY (`logement_id`) REFERENCES `liste_logements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `factures`
--

DROP TABLE IF EXISTS `factures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `factures` (
  `id` int(11) NOT NULL,
  `intervenant_id` int(11) NOT NULL,
  `numero_facture` varchar(50) NOT NULL,
  `periode` varchar(20) NOT NULL,
  `montant_total` decimal(10,2) NOT NULL,
  `date_creation` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gammu`
--

DROP TABLE IF EXISTS `gammu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gammu` (
  `Version` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`Version`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `generated_contracts`
--

DROP TABLE IF EXISTS `generated_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `generated_contracts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `logement_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gestion_machines`
--

DROP TABLE IF EXISTS `gestion_machines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gestion_machines` (
  `id` int(11) NOT NULL,
  `periode_debut` date NOT NULL,
  `periode_fin` date NOT NULL,
  `nombre_de_locations` int(11) NOT NULL,
  `nombre_de_machines` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ia_scenario`
--

DROP TABLE IF EXISTS `ia_scenario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_scenario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `logement_id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `regles` text NOT NULL,
  `message_modele` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ical_reservations`
--

DROP TABLE IF EXISTS `ical_reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ical_reservations` (
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
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `imported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ical_event` (`connection_id`,`ical_uid`),
  KEY `idx_dates` (`start_date`,`end_date`),
  KEY `idx_listing` (`listing_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `ical_reservations_ibfk_1` FOREIGN KEY (`connection_id`) REFERENCES `travel_account_connections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ical_reservations_ibfk_2` FOREIGN KEY (`listing_id`) REFERENCES `travel_listings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ical_sync_log`
--

DROP TABLE IF EXISTS `ical_sync_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ical_sync_log` (
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
  KEY `idx_sync_date` (`synced_at`),
  CONSTRAINT `ical_sync_log_ibfk_1` FOREIGN KEY (`connection_id`) REFERENCES `travel_account_connections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inbox`
--

DROP TABLE IF EXISTS `inbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inbox` (
  `UpdatedInDB` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ReceivingDateTime` timestamp NOT NULL DEFAULT current_timestamp(),
  `Text` text NOT NULL,
  `SenderNumber` varchar(20) NOT NULL DEFAULT '',
  `Coding` enum('Default_No_Compression','Unicode_No_Compression','8bit','Default_Compression','Unicode_Compression') NOT NULL DEFAULT 'Default_No_Compression',
  `UDH` text NOT NULL,
  `SMSCNumber` varchar(20) NOT NULL DEFAULT '',
  `Class` int(11) NOT NULL DEFAULT -1,
  `TextDecoded` text NOT NULL,
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `RecipientID` text NOT NULL,
  `Processed` enum('false','true') NOT NULL DEFAULT 'false',
  `Status` int(11) NOT NULL DEFAULT -1,
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `intervenant`
--

DROP TABLE IF EXISTS `intervenant`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `intervenant` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `prenom` varchar(255) DEFAULT NULL,
  `nom_utilisateur` varchar(100) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `role` enum('admin','conducteur','femme_menage','laverie','responsable') DEFAULT 'femme_menage',
  `taux_horaire` decimal(10,2) DEFAULT 0.00,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `numero` varchar(20) DEFAULT NULL COMMENT 'Numéro de téléphone',
  `role1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role3` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pages_accessibles` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom_utilisateur` (`nom_utilisateur`),
  UNIQUE KEY `idx_username` (`nom_utilisateur`),
  KEY `idx_role` (`role`),
  KEY `idx_actif` (`actif`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `intervenants_pages`
--

DROP TABLE IF EXISTS `intervenants_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `intervenants_pages` (
  `intervenant_id` int(11) NOT NULL,
  `page_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `intervention_tokens`
--

DROP TABLE IF EXISTS `intervention_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `intervention_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `intervention_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  UNIQUE KEY `idx_token` (`token`),
  KEY `idx_intervention` (`intervention_id`),
  KEY `idx_used` (`used`),
  CONSTRAINT `fk_token_intervention` FOREIGN KEY (`intervention_id`) REFERENCES `planning` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=679 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventaire_logement`
--

DROP TABLE IF EXISTS `inventaire_logement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventaire_logement` (
  `id` int(11) NOT NULL,
  `logement_id` int(11) DEFAULT NULL,
  `date_inventaire` datetime DEFAULT current_timestamp(),
  `commentaire` text DEFAULT NULL,
  `utilisateur` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventaire_objets`
--

DROP TABLE IF EXISTS `inventaire_objets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventaire_objets` (
  `id` int(11) NOT NULL,
  `session_id` varchar(50) NOT NULL,
  `logement_id` int(11) DEFAULT NULL,
  `nom_objet` varchar(255) NOT NULL,
  `quantite` int(11) DEFAULT 1,
  `marque` varchar(255) DEFAULT NULL,
  `etat` varchar(50) DEFAULT NULL,
  `date_acquisition` date DEFAULT NULL,
  `valeur` decimal(10,2) DEFAULT NULL,
  `remarques` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `qr_code_path` varchar(255) DEFAULT NULL,
  `proprietaire` enum('frenchy','proprietaire','autre') DEFAULT NULL,
  `horodatage` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventaire_sessions`
--

DROP TABLE IF EXISTS `inventaire_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventaire_sessions` (
  `id` int(11) NOT NULL,
  `logements_id` int(11) NOT NULL,
  `date_debut` datetime DEFAULT current_timestamp(),
  `statut` enum('en_cours','valide','archive') DEFAULT 'en_cours'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `leads`
--

DROP TABLE IF EXISTS `leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `leads` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `liste_logements`
--

DROP TABLE IF EXISTS `liste_logements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `liste_logements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom_du_logement` varchar(255) DEFAULT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `m2` float DEFAULT NULL,
  `nombre_de_personnes` int(11) DEFAULT NULL,
  `poid_menage` decimal(5,2) DEFAULT NULL,
  `prix_vente_menage` float DEFAULT NULL,
  `code` varchar(255) DEFAULT NULL,
  `ics_url` varchar(255) DEFAULT NULL,
  `valeur_locative` float NOT NULL DEFAULT 0,
  `valeur_fonciere` float NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `listing_mappings`
--

DROP TABLE IF EXISTS `listing_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `listing_mappings` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `logement`
--

DROP TABLE IF EXISTS `logement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `logement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `ref_scenario` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `telephone` (`telephone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) DEFAULT 0,
  `user_agent` varchar(255) DEFAULT NULL,
  `attempted_at` datetime DEFAULT current_timestamp(),
  `nom_utilisateur` varchar(255) DEFAULT NULL COMMENT 'Nom utilisateur',
  `attempt_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_username` (`username`),
  KEY `idx_attempted` (`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `modem`
--

DROP TABLE IF EXISTS `modem`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `modem` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `port` varchar(20) NOT NULL,
  `etat` enum('actif','inactif') DEFAULT 'actif',
  PRIMARY KEY (`id`),
  UNIQUE KEY `port` (`port`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `intervenant_id` int(11) DEFAULT NULL,
  `type` enum('info','warning','error','success') DEFAULT 'info',
  `titre` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `lien` varchar(255) DEFAULT NULL,
  `lu` tinyint(1) DEFAULT 0,
  `date_notification` datetime DEFAULT current_timestamp(),
  `nom_utilisateur` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_intervenant` (`intervenant_id`),
  KEY `idx_lu` (`lu`),
  KEY `idx_date` (`date_notification`),
  CONSTRAINT `fk_notification_intervenant` FOREIGN KEY (`intervenant_id`) REFERENCES `intervenant` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `objet_logements`
--

DROP TABLE IF EXISTS `objet_logements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `objet_logements` (
  `id` int(11) NOT NULL,
  `logement_id` int(11) NOT NULL,
  `nom_objet` varchar(255) NOT NULL,
  `marque` varchar(255) DEFAULT NULL,
  `etat` varchar(100) DEFAULT NULL,
  `date_acquisition` date DEFAULT NULL,
  `valeur_achat` decimal(10,2) DEFAULT NULL,
  `quantite` int(11) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `qr_code_path` varchar(255) DEFAULT NULL,
  `photo_objet` varchar(255) DEFAULT NULL,
  `facture_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `proprietaire` enum('Propriétaire','Frenchy Conciergerie','Autre') DEFAULT 'Propriétaire'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `outbox`
--

DROP TABLE IF EXISTS `outbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `outbox` (
  `UpdatedInDB` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `InsertIntoDB` timestamp NOT NULL DEFAULT current_timestamp(),
  `SendingDateTime` timestamp NOT NULL DEFAULT current_timestamp(),
  `SendBefore` time NOT NULL DEFAULT '23:59:59',
  `SendAfter` time NOT NULL DEFAULT '00:00:00',
  `Text` text DEFAULT NULL,
  `DestinationNumber` varchar(20) NOT NULL DEFAULT '',
  `Coding` enum('Default_No_Compression','Unicode_No_Compression','8bit','Default_Compression','Unicode_Compression') NOT NULL DEFAULT 'Default_No_Compression',
  `UDH` text DEFAULT NULL,
  `Class` int(11) DEFAULT -1,
  `TextDecoded` text NOT NULL,
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `MultiPart` enum('false','true') DEFAULT 'false',
  `RelativeValidity` int(11) DEFAULT -1,
  `SenderID` varchar(255) DEFAULT NULL,
  `SendingTimeOut` timestamp NULL DEFAULT current_timestamp(),
  `DeliveryReport` enum('default','yes','no') DEFAULT 'default',
  `CreatorID` text NOT NULL,
  `Retries` int(3) DEFAULT 0,
  `Priority` int(11) DEFAULT 0,
  `Status` enum('SendingOK','SendingOKNoReport','SendingError','DeliveryOK','DeliveryFailed','DeliveryPending','DeliveryUnknown','Error','Reserved') NOT NULL DEFAULT 'Reserved',
  `StatusCode` int(11) NOT NULL DEFAULT -1,
  PRIMARY KEY (`ID`),
  KEY `outbox_date` (`SendingDateTime`,`SendingTimeOut`),
  KEY `outbox_sender` (`SenderID`(250))
) ENGINE=MyISAM AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `outbox_multipart`
--

DROP TABLE IF EXISTS `outbox_multipart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `outbox_multipart` (
  `Text` text DEFAULT NULL,
  `Coding` enum('Default_No_Compression','Unicode_No_Compression','8bit','Default_Compression','Unicode_Compression') NOT NULL DEFAULT 'Default_No_Compression',
  `UDH` text DEFAULT NULL,
  `Class` int(11) DEFAULT -1,
  `TextDecoded` text DEFAULT NULL,
  `ID` int(10) unsigned NOT NULL DEFAULT 0,
  `SequencePosition` int(11) NOT NULL DEFAULT 1,
  `Status` enum('SendingOK','SendingOKNoReport','SendingError','DeliveryOK','DeliveryFailed','DeliveryPending','DeliveryUnknown','Error','Reserved') NOT NULL DEFAULT 'Reserved',
  `StatusCode` int(11) NOT NULL DEFAULT -1,
  PRIMARY KEY (`ID`,`SequencePosition`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages` (
  `id` int(11) NOT NULL,
  `page_chemin` varchar(255) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `chemin` varchar(255) NOT NULL,
  `afficher_menu` tinyint(1) DEFAULT 1,
  `page_content` longtext DEFAULT NULL,
  `page_html` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `partners`
--

DROP TABLE IF EXISTS `partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `partners` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `logo_url` varchar(255) NOT NULL,
  `website_url` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expiration` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `phones`
--

DROP TABLE IF EXISTS `phones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `phones` (
  `ID` text NOT NULL,
  `UpdatedInDB` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `InsertIntoDB` timestamp NOT NULL DEFAULT current_timestamp(),
  `TimeOut` timestamp NOT NULL DEFAULT current_timestamp(),
  `Send` enum('yes','no') NOT NULL DEFAULT 'no',
  `Receive` enum('yes','no') NOT NULL DEFAULT 'no',
  `IMEI` varchar(35) NOT NULL,
  `IMSI` varchar(35) NOT NULL,
  `NetCode` varchar(10) DEFAULT 'ERROR',
  `NetName` varchar(35) DEFAULT 'ERROR',
  `Client` text NOT NULL,
  `Battery` int(11) NOT NULL DEFAULT -1,
  `Signal` int(11) NOT NULL DEFAULT -1,
  `Sent` int(11) NOT NULL DEFAULT 0,
  `Received` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`IMEI`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `planning`
--

DROP TABLE IF EXISTS `planning`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `planning` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `logement_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `type_intervention` enum('menage','maintenance','inventaire','cle') DEFAULT 'menage',
  `statut` enum('A Faire','En Cours','Valide','Annule') DEFAULT 'A Faire',
  `conducteur` int(11) DEFAULT NULL COMMENT 'ID intervenant',
  `femme_de_menage_1` int(11) DEFAULT NULL,
  `femme_de_menage_2` int(11) DEFAULT NULL,
  `laverie` int(11) DEFAULT NULL,
  `nombre_de_personnes` int(11) DEFAULT NULL,
  `lit_bebe` tinyint(1) DEFAULT 0,
  `nombre_lits_specifique` int(11) DEFAULT NULL,
  `early_check_in` tinyint(1) DEFAULT 0,
  `late_check_out` tinyint(1) DEFAULT 0,
  `note` text DEFAULT NULL,
  `poids_menage` decimal(10,2) DEFAULT 0.00 COMMENT 'Calcul automatique',
  `duree_estimee` int(11) DEFAULT NULL COMMENT 'En minutes',
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `valide_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `nombre_de_jours_reservation` int(11) DEFAULT 0 COMMENT 'Nombre de jours de la réservation',
  `note_sur_10` float DEFAULT NULL,
  `poid_menage` float DEFAULT NULL,
  `notes` float DEFAULT NULL,
  `montant_ca` float DEFAULT NULL,
  `montant_charges` float DEFAULT NULL,
  `bonus_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `source_reservation_id` int(11) DEFAULT NULL,
  `commentaire_menage` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bonus_total` decimal(10,2) DEFAULT NULL,
  `source_type` varchar(255) DEFAULT NULL,
  `bonus` decimal(10,2) DEFAULT NULL,
  `ca_generé` decimal(10,2) DEFAULT NULL COMMENT 'CA généré',
  `ca_genere` decimal(10,2) DEFAULT NULL COMMENT 'CA généré (sans accent)',
  `charges_comptabilisées` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_logement` (`logement_id`),
  KEY `idx_date` (`date`),
  KEY `idx_statut` (`statut`),
  KEY `idx_reservation` (`reservation_id`),
  KEY `idx_planning_date_statut` (`date`,`statut`),
  CONSTRAINT `fk_planning_logement` FOREIGN KEY (`logement_id`) REFERENCES `liste_logements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_planning_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservation` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1611 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `poids_critere`
--

DROP TABLE IF EXISTS `poids_critere`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `poids_critere` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `poids` decimal(5,2) DEFAULT 1.00,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `poids_criteres`
--

DROP TABLE IF EXISTS `poids_criteres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `poids_criteres` (
  `critere` varchar(255) NOT NULL COMMENT 'Nom de la colonne dans description_logements',
  `valeur` float NOT NULL DEFAULT 0 COMMENT 'Valeur en points pour ce critère',
  `temps_par_unite` float DEFAULT NULL COMMENT 'Temps estimé en minutes par unité'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reservation`
--

DROP TABLE IF EXISTS `reservation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reservation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(100) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `logement_id` int(11) DEFAULT NULL,
  `date_reservation` date DEFAULT NULL,
  `date_arrivee` date DEFAULT NULL,
  `heure_arrivee` varchar(10) DEFAULT NULL,
  `date_depart` date DEFAULT NULL,
  `heure_depart` varchar(10) DEFAULT NULL,
  `statut` enum('confirmée','annulée') DEFAULT 'confirmée',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `auto_replied` tinyint(4) DEFAULT 0,
  `prenom` varchar(50) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `plateforme` varchar(255) DEFAULT NULL,
  `telephone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `nb_adultes` int(11) DEFAULT 0,
  `nb_enfants` int(11) DEFAULT 0,
  `nb_bebes` int(11) DEFAULT 0,
  `ville` varchar(50) NOT NULL,
  `code_postal` varchar(10) DEFAULT NULL,
  `j1_sent` tinyint(4) DEFAULT 0,
  `dep_sent` tinyint(4) DEFAULT 0,
  `start_sent` tinyint(4) DEFAULT 0,
  `scenario_state` tinyint(4) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `fk_reservation_liste_logements` (`logement_id`),
  KEY `idx_tel` (`telephone`),
  CONSTRAINT `fk_reservation_liste_logements` FOREIGN KEY (`logement_id`) REFERENCES `liste_logements` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `reservation_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2948 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role`
--

DROP TABLE IF EXISTS `role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role` (
  `id` int(11) NOT NULL,
  `role` varchar(255) DEFAULT NULL,
  `valeur` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `satisfaction_conversations`
--

DROP TABLE IF EXISTS `satisfaction_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `satisfaction_conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender` varchar(20) NOT NULL,
  `logement_id` int(11) DEFAULT NULL,
  `role` enum('user','assistant') NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=832 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `scenario`
--

DROP TABLE IF EXISTS `scenario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `scenario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `regles` text DEFAULT NULL,
  `message_modele` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sentitems`
--

DROP TABLE IF EXISTS `sentitems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sentitems` (
  `UpdatedInDB` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `InsertIntoDB` timestamp NOT NULL DEFAULT current_timestamp(),
  `SendingDateTime` timestamp NOT NULL DEFAULT current_timestamp(),
  `DeliveryDateTime` timestamp NULL DEFAULT NULL,
  `Text` text NOT NULL,
  `DestinationNumber` varchar(20) NOT NULL DEFAULT '',
  `Coding` enum('Default_No_Compression','Unicode_No_Compression','8bit','Default_Compression','Unicode_Compression') NOT NULL DEFAULT 'Default_No_Compression',
  `UDH` text NOT NULL,
  `SMSCNumber` varchar(20) NOT NULL DEFAULT '',
  `Class` int(11) NOT NULL DEFAULT -1,
  `TextDecoded` text NOT NULL,
  `ID` int(10) unsigned NOT NULL DEFAULT 0,
  `SenderID` varchar(255) NOT NULL,
  `SequencePosition` int(11) NOT NULL DEFAULT 1,
  `Status` enum('SendingOK','SendingOKNoReport','SendingError','DeliveryOK','DeliveryFailed','DeliveryPending','DeliveryUnknown','Error') NOT NULL DEFAULT 'SendingOK',
  `StatusError` int(11) NOT NULL DEFAULT -1,
  `TPMR` int(11) NOT NULL DEFAULT -1,
  `RelativeValidity` int(11) NOT NULL DEFAULT -1,
  `CreatorID` text NOT NULL,
  `StatusCode` int(11) NOT NULL DEFAULT -1,
  PRIMARY KEY (`ID`,`SequencePosition`),
  KEY `sentitems_date` (`DeliveryDateTime`),
  KEY `sentitems_tpmr` (`TPMR`),
  KEY `sentitems_dest` (`DestinationNumber`),
  KEY `sentitems_sender` (`SenderID`(250))
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions_inventaire`
--

DROP TABLE IF EXISTS `sessions_inventaire`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions_inventaire` (
  `id` varchar(64) NOT NULL,
  `logement_id` int(11) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `statut` enum('en_cours','terminee') DEFAULT 'en_cours'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sites`
--

DROP TABLE IF EXISTS `sites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sites` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `logo` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sms_conversations`
--

DROP TABLE IF EXISTS `sms_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sms_conversations` (
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
  KEY `idx_phone` (`phone_number`),
  KEY `idx_last_message` (`last_message_at`),
  KEY `idx_reservation` (`reservation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Métadonnées des conversations SMS';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sms_in`
--

DROP TABLE IF EXISTS `sms_in`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sms_in` (
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
) ENGINE=InnoDB AUTO_INCREMENT=599 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sms_messages`
--

DROP TABLE IF EXISTS `sms_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sms_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `date_reception` datetime DEFAULT NULL,
  `modem` varchar(20) DEFAULT NULL,
  `date_enregistrement` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sms_out`
--

DROP TABLE IF EXISTS `sms_out`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sms_out` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receiver` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `modem` varchar(20) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sms_outbox`
--

DROP TABLE IF EXISTS `sms_outbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sms_outbox` (
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
  KEY `idx_sms_outbox_status` (`status`),
  KEY `idx_sms_outbox_reservation` (`reservation_id`),
  KEY `idx_sms_outbox_gammu_outbox` (`gammu_outbox_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1806 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sms_templates`
--

DROP TABLE IF EXISTS `sms_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sms_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `campaign` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `template` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_campaign` (`campaign`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sync_log`
--

DROP TABLE IF EXISTS `sync_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sync_log` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `todo_list`
--

DROP TABLE IF EXISTS `todo_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `todo_list` (
  `id` int(11) NOT NULL,
  `logement_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `statut` enum('en attente','en cours','terminée') DEFAULT 'en attente',
  `date_limite` date DEFAULT NULL,
  `responsable` varchar(255) DEFAULT NULL,
  `prix_vente` decimal(10,2) DEFAULT 0.00,
  `prix_achat` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `total_owed_to_intervenants`
--

DROP TABLE IF EXISTS `total_owed_to_intervenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `total_owed_to_intervenants` (
  `id` int(11) NOT NULL,
  `intervenant` varchar(255) DEFAULT NULL,
  `total_owed` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `travel_account_connections`
--

DROP TABLE IF EXISTS `travel_account_connections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_account_connections` (
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
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_platform` (`platform_id`),
  KEY `idx_status` (`connection_status`),
  CONSTRAINT `travel_account_connections_ibfk_1` FOREIGN KEY (`platform_id`) REFERENCES `travel_platforms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `travel_listings`
--

DROP TABLE IF EXISTS `travel_listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_listings` (
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
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_listing` (`connection_id`,`platform_listing_id`),
  KEY `idx_connection` (`connection_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `travel_listings_ibfk_1` FOREIGN KEY (`connection_id`) REFERENCES `travel_account_connections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `travel_platforms`
--

DROP TABLE IF EXISTS `travel_platforms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_platforms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
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
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `v_planning_aujourdhui`
--

DROP TABLE IF EXISTS `v_planning_aujourdhui`;
/*!50001 DROP VIEW IF EXISTS `v_planning_aujourdhui`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_planning_aujourdhui` AS SELECT
 1 AS `id`,
  1 AS `logement_id`,
  1 AS `date`,
  1 AS `reservation_id`,
  1 AS `type_intervention`,
  1 AS `statut`,
  1 AS `conducteur`,
  1 AS `femme_de_menage_1`,
  1 AS `femme_de_menage_2`,
  1 AS `laverie`,
  1 AS `nombre_de_personnes`,
  1 AS `lit_bebe`,
  1 AS `nombre_lits_specifique`,
  1 AS `early_check_in`,
  1 AS `late_check_out`,
  1 AS `note`,
  1 AS `poids_menage`,
  1 AS `duree_estimee`,
  1 AS `heure_debut`,
  1 AS `heure_fin`,
  1 AS `valide_at`,
  1 AS `created_at`,
  1 AS `updated_at`,
  1 AS `nom_du_logement`,
  1 AS `adresse`,
  1 AS `code`,
  1 AS `nom_conducteur`,
  1 AS `nom_fdm1`,
  1 AS `nom_fdm2`,
  1 AS `nom_laverie` */;
SET character_set_client = @saved_cs_client;

--
-- Final view structure for view `v_planning_aujourdhui`
--

/*!50001 DROP VIEW IF EXISTS `v_planning_aujourdhui`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_planning_aujourdhui` AS select `p`.`id` AS `id`,`p`.`logement_id` AS `logement_id`,`p`.`date` AS `date`,`p`.`reservation_id` AS `reservation_id`,`p`.`type_intervention` AS `type_intervention`,`p`.`statut` AS `statut`,`p`.`conducteur` AS `conducteur`,`p`.`femme_de_menage_1` AS `femme_de_menage_1`,`p`.`femme_de_menage_2` AS `femme_de_menage_2`,`p`.`laverie` AS `laverie`,`p`.`nombre_de_personnes` AS `nombre_de_personnes`,`p`.`lit_bebe` AS `lit_bebe`,`p`.`nombre_lits_specifique` AS `nombre_lits_specifique`,`p`.`early_check_in` AS `early_check_in`,`p`.`late_check_out` AS `late_check_out`,`p`.`note` AS `note`,`p`.`poids_menage` AS `poids_menage`,`p`.`duree_estimee` AS `duree_estimee`,`p`.`heure_debut` AS `heure_debut`,`p`.`heure_fin` AS `heure_fin`,`p`.`valide_at` AS `valide_at`,`p`.`created_at` AS `created_at`,`p`.`updated_at` AS `updated_at`,`l`.`nom_du_logement` AS `nom_du_logement`,`l`.`adresse` AS `adresse`,`l`.`code` AS `code`,`i1`.`nom` AS `nom_conducteur`,`i2`.`nom` AS `nom_fdm1`,`i3`.`nom` AS `nom_fdm2`,`i4`.`nom` AS `nom_laverie` from (((((`planning` `p` join `liste_logements` `l` on(`p`.`logement_id` = `l`.`id`)) left join `intervenant` `i1` on(`p`.`conducteur` = `i1`.`id`)) left join `intervenant` `i2` on(`p`.`femme_de_menage_1` = `i2`.`id`)) left join `intervenant` `i3` on(`p`.`femme_de_menage_2` = `i3`.`id`)) left join `intervenant` `i4` on(`p`.`laverie` = `i4`.`id`)) where `p`.`date` = curdate() */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-24 13:27:19
