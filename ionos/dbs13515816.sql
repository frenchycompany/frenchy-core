-- phpMyAdmin SQL Dump
-- version 4.9.11
-- https://www.phpmyadmin.net/
--
-- Hôte : db5016690401.hosting-data.io
-- Généré le : mar. 24 fév. 2026 à 13:21
-- Version du serveur : 8.0.36
-- Version de PHP : 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `dbs13515816`
--
CREATE DATABASE IF NOT EXISTS `dbs13515816` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `dbs13515816`;

-- --------------------------------------------------------

--
-- Structure de la table `annexes`
--

CREATE TABLE `annexes` (
  `id` int NOT NULL,
  `contract_id` int NOT NULL,
  `annex_type` enum('Photo dossier','Inventaire') COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `articles`
--

CREATE TABLE `articles` (
  `id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci NOT NULL,
  `thumbnail` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `chz24_photos`
--

CREATE TABLE `chz24_photos` (
  `id` int NOT NULL,
  `photo_group` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `srcset_json` text COLLATE utf8mb4_unicode_ci,
  `alt_text` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `is_wide` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `chz24_settings`
--

CREATE TABLE `chz24_settings` (
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `setting_group` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `label` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `sort_order` int DEFAULT '0',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `chz24_texts`
--

CREATE TABLE `chz24_texts` (
  `id` int NOT NULL,
  `section_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_value` text COLLATE utf8mb4_unicode_ci,
  `label` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `sort_order` int DEFAULT '0',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `comptabilite`
--

CREATE TABLE `comptabilite` (
  `id` int NOT NULL,
  `type` enum('CA','Charge') NOT NULL COMMENT 'CA pour chiffre d''affaire, Charge pour dépenses',
  `source_type` enum('intervention','todo') NOT NULL COMMENT 'Origine de l''entrée : intervention ou todo',
  `source_id` int NOT NULL COMMENT 'Identifiant de la source',
  `intervenant_id` int DEFAULT NULL COMMENT 'Identifiant de l''intervenant lié (facultatif)',
  `montant` float NOT NULL COMMENT 'Montant de la transaction',
  `date_comptabilisation` date NOT NULL COMMENT 'Date de la comptabilisation',
  `description` text COMMENT 'Description supplémentaire pour clarté'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `configuration`
--

CREATE TABLE `configuration` (
  `id` int NOT NULL,
  `nom_site` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `email_contact` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `mode_maintenance` tinyint(1) NOT NULL DEFAULT '0',
  `footer_text` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `contracts`
--

CREATE TABLE `contracts` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `logement_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `content` text COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `contract_entries`
--

CREATE TABLE `contract_entries` (
  `id` int NOT NULL,
  `logement_id` int NOT NULL,
  `user_id` int NOT NULL,
  `field_data` json NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `contract_fields`
--

CREATE TABLE `contract_fields` (
  `id` int NOT NULL,
  `field_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci NOT NULL,
  `input_type` enum('text','number','textarea','date','select') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'text',
  `options` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `contract_templates`
--

CREATE TABLE `contract_templates` (
  `id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `content` text COLLATE utf8mb4_general_ci NOT NULL,
  `placeholders` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `cv01_photos`
--

CREATE TABLE `cv01_photos` (
  `id` int NOT NULL,
  `photo_group` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `srcset_json` text COLLATE utf8mb4_unicode_ci,
  `alt_text` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `is_wide` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `cv01_settings`
--

CREATE TABLE `cv01_settings` (
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `setting_group` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `label` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `sort_order` int DEFAULT '0',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `cv01_texts`
--

CREATE TABLE `cv01_texts` (
  `id` int NOT NULL,
  `section_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_value` text COLLATE utf8mb4_unicode_ci,
  `label` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `sort_order` int DEFAULT '0',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `description_logements`
--

CREATE TABLE `description_logements` (
  `id` int NOT NULL,
  `logement_id` int NOT NULL,
  `superficie_m2` float NOT NULL COMMENT 'Superficie en mètres carrés',
  `nombre_pieces` int NOT NULL COMMENT 'Nombre de pièces dans le logement',
  `nombre_marches` int DEFAULT '0' COMMENT 'Nombre de marches ou d''escaliers',
  `nombre_tables` int DEFAULT '0' COMMENT 'Nombre de tables',
  `nombre_plans_travail` int DEFAULT '0' COMMENT 'Nombre de plans de travail dans la cuisine',
  `nombre_lavabos` int DEFAULT '0' COMMENT 'Nombre de lavabos',
  `nombre_tables_basses` int DEFAULT '0' COMMENT 'Nombre de tables basses',
  `nombre_tables_chevets` int DEFAULT '0' COMMENT 'Nombre de tables de chevet',
  `nombre_frigos` int DEFAULT '0' COMMENT 'Nombre total de frigos (toutes tailles confondues)',
  `nombre_couchages` int DEFAULT '0' COMMENT 'Nombre total de couchages (lits, canapés-lits, etc.)',
  `nombre_tiroirs` int DEFAULT '0' COMMENT 'Nombre de tiroirs nécessitant un nettoyage ou un aspirateur',
  `nombre_wcs` int DEFAULT '0' COMMENT 'Nombre de WC',
  `nombre_douches` int DEFAULT '0' COMMENT 'Nombre de cabines de douche',
  `nombre_miroirs` int DEFAULT '0' COMMENT 'Nombre de miroirs',
  `nombre_vitres` int DEFAULT '0' COMMENT 'Nombre de vitres',
  `nombre_objets_deco` int DEFAULT '0' COMMENT 'Nombre total d''objets décoratifs (cadres, bibelots, etc.)',
  `nombre_vaisselle` int DEFAULT '0' COMMENT 'Nombre total de couverts et assiettes',
  `nombre_televisions` int DEFAULT '0' COMMENT 'Nombre total de télévisions',
  `nombre_electromenagers` int DEFAULT '0' COMMENT 'Nombre total d''électroménagers (micro-ondes, cafetières, etc.)',
  `multiplicateur` float DEFAULT '1' COMMENT 'Coefficient ajusté selon la difficulté ou la prise en main',
  `commentaire` text COMMENT 'Commentaires ou remarques supplémentaires',
  `temps_passe_moyen` float DEFAULT '0' COMMENT 'Critère ajouté automatiquement'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `factures`
--

CREATE TABLE `factures` (
  `id` int NOT NULL,
  `intervenant_id` int NOT NULL,
  `numero_facture` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `periode` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `montant_total` decimal(10,2) NOT NULL,
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_admins`
--

CREATE TABLE `FC_admins` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('super_admin','admin','editeur') COLLATE utf8mb4_unicode_ci DEFAULT 'admin',
  `derniere_connexion` datetime DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_admin_logs`
--

CREATE TABLE `FC_admin_logs` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_admin_sessions`
--

CREATE TABLE `FC_admin_sessions` (
  `id` int NOT NULL,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `last_activity` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_articles`
--

CREATE TABLE `FC_articles` (
  `id` int NOT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenu` text COLLATE utf8mb4_unicode_ci,
  `extrait` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auteur` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_publication` date DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `categorie_id` int DEFAULT NULL,
  `auteur_id` int DEFAULT NULL,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `nb_vues` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_avis`
--

CREATE TABLE `FC_avis` (
  `id` int NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Propriétaire',
  `date_avis` date DEFAULT NULL,
  `note` int DEFAULT '5',
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `source` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_avis_codes`
--

CREATE TABLE `FC_avis_codes` (
  `id` int NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_proprietaire` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse_bien` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `used` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_calculateur_params`
--

CREATE TABLE `FC_calculateur_params` (
  `id` int NOT NULL,
  `type_bien` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `localisation` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prix_base_nuit` decimal(10,2) DEFAULT NULL,
  `taux_occupation_moyen` decimal(5,2) DEFAULT NULL,
  `coefficient_saisonnier` decimal(5,2) DEFAULT '1.00',
  `actif` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_categories`
--

CREATE TABLE `FC_categories` (
  `id` int NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `ordre` int DEFAULT '0',
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_checklists_menage`
--

CREATE TABLE `FC_checklists_menage` (
  `id` int NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_logement` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_menage` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `items` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordre` int DEFAULT '0',
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_contacts`
--

CREATE TABLE `FC_contacts` (
  `id` int NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sujet` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `lu` tinyint(1) DEFAULT '0',
  `traite` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `archive` tinyint(1) DEFAULT '0',
  `statut` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'nouveau'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_conversions`
--

CREATE TABLE `FC_conversions` (
  `id` int NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `donnees` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_csrf_tokens`
--

CREATE TABLE `FC_csrf_tokens` (
  `id` int NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_distinctions`
--

CREATE TABLE `FC_distinctions` (
  `id` int NOT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 0xF09F8F86,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordre` int DEFAULT '0',
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_legal`
--

CREATE TABLE `FC_legal` (
  `id` int NOT NULL,
  `section` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contenu` text COLLATE utf8mb4_unicode_ci,
  `ordre` int DEFAULT '0',
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_logements`
--

CREATE TABLE `FC_logements` (
  `id` int NOT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `localisation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_bien` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordre` int DEFAULT '0',
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_medias`
--

CREATE TABLE `FC_medias` (
  `id` int NOT NULL,
  `nom_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_fichier` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chemin` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_mime` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taille` int DEFAULT NULL,
  `largeur` int DEFAULT NULL,
  `hauteur` int DEFAULT NULL,
  `alt_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_menages`
--

CREATE TABLE `FC_menages` (
  `id` int NOT NULL,
  `logement_id` int NOT NULL,
  `prestataire_id` int DEFAULT NULL,
  `reservation_id` int DEFAULT NULL,
  `date_intervention` date NOT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `duree_estimee` int DEFAULT NULL,
  `duree_reelle` int DEFAULT NULL,
  `type_menage` enum('depart','arrivee','complet','entretien','grand_menage') COLLATE utf8mb4_unicode_ci DEFAULT 'complet',
  `statut` enum('planifie','confirme','en_cours','termine','annule','reporte') COLLATE utf8mb4_unicode_ci DEFAULT 'planifie',
  `priorite` enum('normale','urgente','flexible') COLLATE utf8mb4_unicode_ci DEFAULT 'normale',
  `checklist` text COLLATE utf8mb4_unicode_ci,
  `checklist_valide` text COLLATE utf8mb4_unicode_ci,
  `photos_avant` text COLLATE utf8mb4_unicode_ci,
  `photos_apres` text COLLATE utf8mb4_unicode_ci,
  `problemes_signales` text COLLATE utf8mb4_unicode_ci,
  `fournitures_utilisees` text COLLATE utf8mb4_unicode_ci,
  `linge_change` tinyint(1) DEFAULT '1',
  `produits_reappro` text COLLATE utf8mb4_unicode_ci,
  `montant` decimal(10,2) DEFAULT NULL,
  `paye` tinyint(1) DEFAULT '0',
  `date_paiement` date DEFAULT NULL,
  `mode_paiement` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `note_qualite` int DEFAULT NULL,
  `commentaire_qualite` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_newsletter`
--

CREATE TABLE `FC_newsletter` (
  `id` int NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token_unsubscribe` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'site',
  `confirme` tinyint(1) DEFAULT '0',
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_newsletter_campaigns`
--

CREATE TABLE `FC_newsletter_campaigns` (
  `id` int NOT NULL,
  `sujet` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenu` longtext COLLATE utf8mb4_unicode_ci,
  `nb_envoyes` int DEFAULT '0',
  `nb_ouverts` int DEFAULT '0',
  `nb_clics` int DEFAULT '0',
  `date_envoi` datetime DEFAULT NULL,
  `statut` enum('brouillon','planifie','envoye') COLLATE utf8mb4_unicode_ci DEFAULT 'brouillon',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_paiements_prestataires`
--

CREATE TABLE `FC_paiements_prestataires` (
  `id` int NOT NULL,
  `prestataire_id` int NOT NULL,
  `periode_debut` date DEFAULT NULL,
  `periode_fin` date DEFAULT NULL,
  `nb_interventions` int DEFAULT '0',
  `montant_total` decimal(10,2) NOT NULL,
  `montant_paye` decimal(10,2) DEFAULT '0.00',
  `statut` enum('en_attente','partiel','paye') COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  `date_paiement` date DEFAULT NULL,
  `mode_paiement` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_paiement` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facture` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_prestataires`
--

CREATE TABLE `FC_prestataires` (
  `id` int NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `zone_intervention` text COLLATE utf8mb4_unicode_ci,
  `tarif_horaire` decimal(10,2) DEFAULT NULL,
  `tarif_forfait_studio` decimal(10,2) DEFAULT NULL,
  `tarif_forfait_t2` decimal(10,2) DEFAULT NULL,
  `tarif_forfait_t3` decimal(10,2) DEFAULT NULL,
  `tarif_forfait_t4_plus` decimal(10,2) DEFAULT NULL,
  `note_moyenne` decimal(3,2) DEFAULT '0.00',
  `nb_interventions` int DEFAULT '0',
  `disponibilites` text COLLATE utf8mb4_unicode_ci,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documents` text COLLATE utf8mb4_unicode_ci,
  `siret` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('actif','inactif','en_pause') COLLATE utf8mb4_unicode_ci DEFAULT 'actif',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_proprietaires`
--

CREATE TABLE `FC_proprietaires` (
  `id` int NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_inscription` date DEFAULT NULL,
  `derniere_connexion` datetime DEFAULT NULL,
  `token_reset` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token_reset_expire` datetime DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_rate_limit`
--

CREATE TABLE `FC_rate_limit` (
  `id` int NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` int DEFAULT '1',
  `first_attempt` datetime NOT NULL,
  `last_attempt` datetime NOT NULL,
  `blocked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_reservations`
--

CREATE TABLE `FC_reservations` (
  `id` int NOT NULL,
  `logement_id` int NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `nom_voyageur` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nb_voyageurs` int DEFAULT '1',
  `plateforme` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Airbnb',
  `montant` decimal(10,2) DEFAULT NULL,
  `statut` enum('confirmee','en_attente','annulee') COLLATE utf8mb4_unicode_ci DEFAULT 'confirmee',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_revenus`
--

CREATE TABLE `FC_revenus` (
  `id` int NOT NULL,
  `logement_id` int NOT NULL,
  `mois` date NOT NULL,
  `nb_reservations` int DEFAULT '0',
  `nb_nuits` int DEFAULT '0',
  `revenu_brut` decimal(10,2) DEFAULT '0.00',
  `commission` decimal(10,2) DEFAULT '0.00',
  `revenu_net` decimal(10,2) DEFAULT '0.00',
  `taux_occupation` decimal(5,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_rgpd_config`
--

CREATE TABLE `FC_rgpd_config` (
  `id` int NOT NULL,
  `config_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_sections`
--

CREATE TABLE `FC_sections` (
  `id` int NOT NULL,
  `section_key` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `section_label` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `ordre` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_services`
--

CREATE TABLE `FC_services` (
  `id` int NOT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 0xF09F8FA0,
  `carte_info` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `liste_items` text COLLATE utf8mb4_unicode_ci,
  `ordre` int DEFAULT '0',
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_settings`
--

CREATE TABLE `FC_settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_simulateur_config`
--

CREATE TABLE `FC_simulateur_config` (
  `id` int NOT NULL,
  `config_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_value` text COLLATE utf8mb4_unicode_ci,
  `config_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `config_type` enum('number','percent','text','json') COLLATE utf8mb4_unicode_ci DEFAULT 'number',
  `ordre` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_simulateur_villes`
--

CREATE TABLE `FC_simulateur_villes` (
  `id` int NOT NULL,
  `ville` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `majoration_percent` decimal(5,2) DEFAULT '0.00',
  `actif` tinyint(1) DEFAULT '1',
  `ordre` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_simulations`
--

CREATE TABLE `FC_simulations` (
  `id` int NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_bien` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `localisation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surface` int DEFAULT NULL,
  `nb_chambres` int DEFAULT NULL,
  `equipements` text COLLATE utf8mb4_unicode_ci,
  `estimation_basse` decimal(10,2) DEFAULT NULL,
  `estimation_haute` decimal(10,2) DEFAULT NULL,
  `estimation_moyenne` decimal(10,2) DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `centre_ville` tinyint(1) DEFAULT '0',
  `fibre` tinyint(1) DEFAULT '0',
  `equipements_speciaux` tinyint(1) DEFAULT '0',
  `machine_cafe` tinyint(1) DEFAULT '0',
  `machine_laver` tinyint(1) DEFAULT '0',
  `autre_equipement` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarif_nuit_estime` decimal(10,2) DEFAULT NULL,
  `revenu_mensuel_estime` decimal(10,2) DEFAULT NULL,
  `contacted` tinyint(1) DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `capacite` int DEFAULT NULL,
  `ville` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'a_contacter',
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_stock_fournitures`
--

CREATE TABLE `FC_stock_fournitures` (
  `id` int NOT NULL,
  `logement_id` int DEFAULT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categorie` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantite` int DEFAULT '0',
  `quantite_min` int DEFAULT '1',
  `unite` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'unité',
  `prix_unitaire` decimal(10,2) DEFAULT NULL,
  `fournisseur` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `derniere_verification` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_stock_mouvements`
--

CREATE TABLE `FC_stock_mouvements` (
  `id` int NOT NULL,
  `fourniture_id` int NOT NULL,
  `logement_id` int DEFAULT NULL,
  `menage_id` int DEFAULT NULL,
  `type_mouvement` enum('entree','sortie','inventaire','perte') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantite` int NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_tarifs`
--

CREATE TABLE `FC_tarifs` (
  `id` int NOT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pourcentage` decimal(5,2) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `details` text COLLATE utf8mb4_unicode_ci,
  `ordre` int DEFAULT '0',
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `montant` decimal(10,2) DEFAULT '0.00',
  `type_tarif` enum('pourcentage','euro') COLLATE utf8mb4_unicode_ci DEFAULT 'pourcentage'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_tarifs_menage`
--

CREATE TABLE `FC_tarifs_menage` (
  `id` int NOT NULL,
  `logement_id` int NOT NULL,
  `type_menage` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `duree_estimee` int DEFAULT NULL,
  `tarif` decimal(10,2) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `checklist_defaut` text COLLATE utf8mb4_unicode_ci,
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_users`
--

CREATE TABLE `FC_users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','editor','viewer') DEFAULT 'viewer',
  `nom` varchar(100) DEFAULT NULL,
  `prenom` varchar(100) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_visites`
--

CREATE TABLE `FC_visites` (
  `id` int NOT NULL,
  `page` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `referer` text COLLATE utf8mb4_unicode_ci,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `generated_contracts`
--

CREATE TABLE `generated_contracts` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `logement_id` int NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `gestion_machines`
--

CREATE TABLE `gestion_machines` (
  `id` int NOT NULL,
  `periode_debut` date NOT NULL,
  `periode_fin` date NOT NULL,
  `nombre_de_locations` int NOT NULL,
  `nombre_de_machines` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `intervenant`
--

CREATE TABLE `intervenant` (
  `id` int NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `numero` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role1` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role2` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role3` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nom_utilisateur` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'temp_user',
  `mot_de_passe` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'temp_pass',
  `role` enum('admin','user') COLLATE utf8mb4_general_ci DEFAULT 'user',
  `pages_accessibles` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `intervenants_pages`
--

CREATE TABLE `intervenants_pages` (
  `intervenant_id` int NOT NULL,
  `page_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `intervention_tokens`
--

CREATE TABLE `intervention_tokens` (
  `id` int NOT NULL,
  `intervention_id` int NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `inventaire_logement`
--

CREATE TABLE `inventaire_logement` (
  `id` int NOT NULL,
  `logement_id` int DEFAULT NULL,
  `date_inventaire` datetime DEFAULT CURRENT_TIMESTAMP,
  `commentaire` text COLLATE utf8mb4_general_ci,
  `utilisateur` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `inventaire_objets`
--

CREATE TABLE `inventaire_objets` (
  `id` int NOT NULL,
  `session_id` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `logement_id` int DEFAULT NULL,
  `nom_objet` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `quantite` int DEFAULT '1',
  `marque` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `etat` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_acquisition` date DEFAULT NULL,
  `valeur` decimal(10,2) DEFAULT NULL,
  `remarques` text COLLATE utf8mb4_general_ci,
  `photo_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `qr_code_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `proprietaire` enum('frenchy','proprietaire','autre') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `horodatage` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `inventaire_sessions`
--

CREATE TABLE `inventaire_sessions` (
  `id` int NOT NULL,
  `logements_id` int NOT NULL,
  `date_debut` datetime DEFAULT CURRENT_TIMESTAMP,
  `statut` enum('en_cours','valide','archive') COLLATE utf8mb4_general_ci DEFAULT 'en_cours'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `leads`
--

CREATE TABLE `leads` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `liste_logements`
--

CREATE TABLE `liste_logements` (
  `id` int NOT NULL,
  `nom_du_logement` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `adresse` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `m2` float DEFAULT NULL,
  `nombre_de_personnes` int DEFAULT NULL,
  `poid_menage` decimal(5,2) DEFAULT NULL,
  `prix_vente_menage` float DEFAULT NULL,
  `code` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `valeur_locative` float NOT NULL DEFAULT '0',
  `valeur_fonciere` float NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `nom_utilisateur` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `attempt_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ml01_photos`
--

CREATE TABLE `ml01_photos` (
  `id` int NOT NULL,
  `photo_group` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `srcset_json` text COLLATE utf8mb4_unicode_ci,
  `alt_text` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `is_wide` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ml01_settings`
--

CREATE TABLE `ml01_settings` (
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `setting_group` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `label` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `sort_order` int DEFAULT '0',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ml01_texts`
--

CREATE TABLE `ml01_texts` (
  `id` int NOT NULL,
  `section_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_value` text COLLATE utf8mb4_unicode_ci,
  `label` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `sort_order` int DEFAULT '0',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `nom_utilisateur` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `date_notification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `objet_logements`
--

CREATE TABLE `objet_logements` (
  `id` int NOT NULL,
  `logement_id` int NOT NULL,
  `nom_objet` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `marque` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `etat` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_acquisition` date DEFAULT NULL,
  `valeur_achat` decimal(10,2) DEFAULT NULL,
  `quantite` int DEFAULT '1',
  `notes` text COLLATE utf8mb4_general_ci,
  `qr_code_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `photo_objet` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `facture_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `proprietaire` enum('Propriétaire','Frenchy Conciergerie','Autre') COLLATE utf8mb4_general_ci DEFAULT 'Propriétaire'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `pages`
--

CREATE TABLE `pages` (
  `id` int NOT NULL,
  `page_chemin` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `chemin` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `afficher_menu` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `partners`
--

CREATE TABLE `partners` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci NOT NULL,
  `logo_url` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `website_url` varchar(255) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `expiration` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `planning`
--

CREATE TABLE `planning` (
  `id` int NOT NULL,
  `date` date DEFAULT NULL,
  `nombre_de_personnes` int DEFAULT NULL,
  `nombre_de_jours_reservation` int DEFAULT NULL,
  `statut` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `note_sur_10` float DEFAULT NULL,
  `conducteur` int DEFAULT NULL,
  `femme_de_menage_1` int DEFAULT NULL,
  `femme_de_menage_2` int DEFAULT NULL,
  `laverie` int DEFAULT NULL,
  `poid_menage` float DEFAULT NULL,
  `notes` float DEFAULT NULL,
  `logement_id` int DEFAULT NULL,
  `ca_generé` enum('non','oui') COLLATE utf8mb4_general_ci DEFAULT 'non',
  `charges_comptabilisées` enum('non','oui') COLLATE utf8mb4_general_ci DEFAULT 'non',
  `montant_ca` float DEFAULT NULL,
  `montant_charges` float DEFAULT NULL,
  `lit_bebe` tinyint(1) NOT NULL DEFAULT '0',
  `nombre_lits_specifique` int DEFAULT NULL,
  `early_check_in` tinyint(1) NOT NULL DEFAULT '0',
  `late_check_out` tinyint(1) NOT NULL DEFAULT '0',
  `bonus_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `bonus_reason` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_general_ci,
  `source_reservation_id` int DEFAULT NULL,
  `source_type` enum('AUTO_CHECKOUT','AUTO_ARRIVAL') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `commentaire_menage` text COLLATE utf8mb4_general_ci,
  `bonus` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `poids_criteres`
--

CREATE TABLE `poids_criteres` (
  `critere` varchar(255) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Nom de la colonne dans description_logements',
  `valeur` float NOT NULL DEFAULT '0' COMMENT 'Valeur en points pour ce critère',
  `temps_par_unite` float DEFAULT NULL COMMENT 'Temps estimé en minutes par unité'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `reservation`
--

CREATE TABLE `reservation` (
  `id` int NOT NULL,
  `reference` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `client_id` int DEFAULT NULL,
  `logement_id` int DEFAULT NULL,
  `date_reservation` date DEFAULT NULL,
  `date_arrivee` date DEFAULT NULL,
  `heure_arrivee` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_depart` date DEFAULT NULL,
  `heure_depart` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `statut` enum('confirmée','annulée') COLLATE utf8mb4_general_ci DEFAULT 'confirmée',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `auto_replied` tinyint DEFAULT '0',
  `prenom` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `nom` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `plateforme` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nb_adultes` int DEFAULT '0',
  `nb_enfants` int DEFAULT '0',
  `nb_bebes` int DEFAULT '0',
  `ville` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `code_postal` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `j1_sent` tinyint DEFAULT '0',
  `dep_sent` tinyint DEFAULT '0',
  `start_sent` tinyint DEFAULT '0',
  `scenario_state` tinyint DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `role`
--

CREATE TABLE `role` (
  `id` int NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `valeur` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sessions_inventaire`
--

CREATE TABLE `sessions_inventaire` (
  `id` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `logement_id` int NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `statut` enum('en_cours','terminee') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'en_cours'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sites`
--

CREATE TABLE `sites` (
  `id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `logo` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `description` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `todo_list`
--

CREATE TABLE `todo_list` (
  `id` int NOT NULL,
  `logement_id` int NOT NULL,
  `description` text COLLATE utf8mb4_general_ci NOT NULL,
  `statut` enum('en attente','en cours','terminée') COLLATE utf8mb4_general_ci DEFAULT 'en attente',
  `date_limite` date DEFAULT NULL,
  `responsable` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `prix_vente` decimal(10,2) DEFAULT '0.00',
  `prix_achat` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `total_owed_to_intervenants`
--

CREATE TABLE `total_owed_to_intervenants` (
  `id` int NOT NULL,
  `intervenant` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `total_owed` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `vf_photos`
--

CREATE TABLE `vf_photos` (
  `id` int NOT NULL,
  `photo_group` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alt_text` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `is_wide` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `vf_settings`
--

CREATE TABLE `vf_settings` (
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `setting_group` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `label` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `sort_order` int DEFAULT '0',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `vf_texts`
--

CREATE TABLE `vf_texts` (
  `id` int NOT NULL,
  `section_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_value` text COLLATE utf8mb4_unicode_ci,
  `label` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `sort_order` int DEFAULT '0',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `annexes`
--
ALTER TABLE `annexes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contract_id` (`contract_id`);

--
-- Index pour la table `articles`
--
ALTER TABLE `articles`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `chz24_photos`
--
ALTER TABLE `chz24_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_group` (`photo_group`);

--
-- Index pour la table `chz24_settings`
--
ALTER TABLE `chz24_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Index pour la table `chz24_texts`
--
ALTER TABLE `chz24_texts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_section_field` (`section_key`,`field_key`);

--
-- Index pour la table `comptabilite`
--
ALTER TABLE `comptabilite`
  ADD PRIMARY KEY (`id`),
  ADD KEY `intervenant_id` (`intervenant_id`),
  ADD KEY `idx_source` (`source_type`,`source_id`),
  ADD KEY `idx_type_date` (`type`,`date_comptabilisation`);

--
-- Index pour la table `configuration`
--
ALTER TABLE `configuration`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `logement_id` (`logement_id`);

--
-- Index pour la table `contract_entries`
--
ALTER TABLE `contract_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `logement_id` (`logement_id`);

--
-- Index pour la table `contract_fields`
--
ALTER TABLE `contract_fields`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `contract_templates`
--
ALTER TABLE `contract_templates`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `cv01_photos`
--
ALTER TABLE `cv01_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_group` (`photo_group`);

--
-- Index pour la table `cv01_settings`
--
ALTER TABLE `cv01_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Index pour la table `cv01_texts`
--
ALTER TABLE `cv01_texts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_section_field` (`section_key`,`field_key`);

--
-- Index pour la table `description_logements`
--
ALTER TABLE `description_logements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `logement_id` (`logement_id`);

--
-- Index pour la table `factures`
--
ALTER TABLE `factures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_facture` (`numero_facture`);

--
-- Index pour la table `FC_admins`
--
ALTER TABLE `FC_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `FC_admin_logs`
--
ALTER TABLE `FC_admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

--
-- Index pour la table `FC_admin_sessions`
--
ALTER TABLE `FC_admin_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`);

--
-- Index pour la table `FC_articles`
--
ALTER TABLE `FC_articles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_articles_slug` (`slug`),
  ADD KEY `idx_articles_date` (`date_publication`,`actif`);

--
-- Index pour la table `FC_avis`
--
ALTER TABLE `FC_avis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_avis_actif` (`actif`);

--
-- Index pour la table `FC_avis_codes`
--
ALTER TABLE `FC_avis_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Index pour la table `FC_calculateur_params`
--
ALTER TABLE `FC_calculateur_params`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_type_loc` (`type_bien`,`localisation`);

--
-- Index pour la table `FC_categories`
--
ALTER TABLE `FC_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Index pour la table `FC_checklists_menage`
--
ALTER TABLE `FC_checklists_menage`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `FC_contacts`
--
ALTER TABLE `FC_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contacts_lu` (`lu`,`traite`);

--
-- Index pour la table `FC_conversions`
--
ALTER TABLE `FC_conversions`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `FC_csrf_tokens`
--
ALTER TABLE `FC_csrf_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_csrf_expires` (`expires_at`);

--
-- Index pour la table `FC_distinctions`
--
ALTER TABLE `FC_distinctions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_distinctions_ordre` (`ordre`,`actif`);

--
-- Index pour la table `FC_legal`
--
ALTER TABLE `FC_legal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `section` (`section`);

--
-- Index pour la table `FC_logements`
--
ALTER TABLE `FC_logements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logements_ordre` (`ordre`,`actif`);

--
-- Index pour la table `FC_medias`
--
ALTER TABLE `FC_medias`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `FC_menages`
--
ALTER TABLE `FC_menages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `logement_id` (`logement_id`),
  ADD KEY `reservation_id` (`reservation_id`),
  ADD KEY `idx_menages_date` (`date_intervention`,`statut`),
  ADD KEY `idx_menages_prestataire` (`prestataire_id`,`date_intervention`);

--
-- Index pour la table `FC_newsletter`
--
ALTER TABLE `FC_newsletter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_newsletter_email` (`email`,`actif`);

--
-- Index pour la table `FC_newsletter_campaigns`
--
ALTER TABLE `FC_newsletter_campaigns`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `FC_paiements_prestataires`
--
ALTER TABLE `FC_paiements_prestataires`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prestataire_id` (`prestataire_id`);

--
-- Index pour la table `FC_prestataires`
--
ALTER TABLE `FC_prestataires`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `FC_proprietaires`
--
ALTER TABLE `FC_proprietaires`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `FC_rate_limit`
--
ALTER TABLE `FC_rate_limit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_action` (`ip_address`,`action`),
  ADD KEY `idx_rate_limit_expires` (`blocked_until`);

--
-- Index pour la table `FC_reservations`
--
ALTER TABLE `FC_reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reservations_dates` (`logement_id`,`date_debut`,`date_fin`);

--
-- Index pour la table `FC_revenus`
--
ALTER TABLE `FC_revenus`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_logement_mois` (`logement_id`,`mois`),
  ADD KEY `idx_revenus_logement` (`logement_id`,`mois`);

--
-- Index pour la table `FC_rgpd_config`
--
ALTER TABLE `FC_rgpd_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`);

--
-- Index pour la table `FC_sections`
--
ALTER TABLE `FC_sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `section_key` (`section_key`);

--
-- Index pour la table `FC_services`
--
ALTER TABLE `FC_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_services_ordre` (`ordre`,`actif`);

--
-- Index pour la table `FC_settings`
--
ALTER TABLE `FC_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Index pour la table `FC_simulateur_config`
--
ALTER TABLE `FC_simulateur_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`);

--
-- Index pour la table `FC_simulateur_villes`
--
ALTER TABLE `FC_simulateur_villes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ville` (`ville`);

--
-- Index pour la table `FC_simulations`
--
ALTER TABLE `FC_simulations`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `FC_stock_fournitures`
--
ALTER TABLE `FC_stock_fournitures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `logement_id` (`logement_id`);

--
-- Index pour la table `FC_stock_mouvements`
--
ALTER TABLE `FC_stock_mouvements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fourniture_id` (`fourniture_id`),
  ADD KEY `logement_id` (`logement_id`),
  ADD KEY `menage_id` (`menage_id`);

--
-- Index pour la table `FC_tarifs`
--
ALTER TABLE `FC_tarifs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tarifs_ordre` (`ordre`,`actif`);

--
-- Index pour la table `FC_tarifs_menage`
--
ALTER TABLE `FC_tarifs_menage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_logement_type` (`logement_id`,`type_menage`);

--
-- Index pour la table `FC_users`
--
ALTER TABLE `FC_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`);

--
-- Index pour la table `FC_visites`
--
ALTER TABLE `FC_visites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`created_at`),
  ADD KEY `idx_page` (`page`);

--
-- Index pour la table `generated_contracts`
--
ALTER TABLE `generated_contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `logement_id` (`logement_id`);

--
-- Index pour la table `gestion_machines`
--
ALTER TABLE `gestion_machines`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `intervenant`
--
ALTER TABLE `intervenant`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nom_utilisateur` (`nom_utilisateur`);

--
-- Index pour la table `intervenants_pages`
--
ALTER TABLE `intervenants_pages`
  ADD PRIMARY KEY (`intervenant_id`,`page_id`),
  ADD KEY `page_id` (`page_id`);

--
-- Index pour la table `intervention_tokens`
--
ALTER TABLE `intervention_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `intervention_id` (`intervention_id`);

--
-- Index pour la table `inventaire_logement`
--
ALTER TABLE `inventaire_logement`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `inventaire_objets`
--
ALTER TABLE `inventaire_objets`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `inventaire_sessions`
--
ALTER TABLE `inventaire_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `logements_id` (`logements_id`);

--
-- Index pour la table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `liste_logements`
--
ALTER TABLE `liste_logements`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `ml01_photos`
--
ALTER TABLE `ml01_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_group` (`photo_group`);

--
-- Index pour la table `ml01_settings`
--
ALTER TABLE `ml01_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Index pour la table `ml01_texts`
--
ALTER TABLE `ml01_texts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_section_field` (`section_key`,`field_key`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `objet_logements`
--
ALTER TABLE `objet_logements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `logement_id` (`logement_id`);

--
-- Index pour la table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chemin` (`chemin`);

--
-- Index pour la table `partners`
--
ALTER TABLE `partners`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `planning`
--
ALTER TABLE `planning`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_resa_source` (`source_reservation_id`,`source_type`),
  ADD KEY `fk_planning_logement` (`logement_id`);

--
-- Index pour la table `poids_criteres`
--
ALTER TABLE `poids_criteres`
  ADD PRIMARY KEY (`critere`);

--
-- Index pour la table `reservation`
--
ALTER TABLE `reservation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_logement` (`logement_id`),
  ADD KEY `idx_ref` (`reference`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_date_depart` (`date_depart`),
  ADD KEY `idx_date_arrivee` (`date_arrivee`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_plateforme` (`plateforme`);

--
-- Index pour la table `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `sessions_inventaire`
--
ALTER TABLE `sessions_inventaire`
  ADD PRIMARY KEY (`id`),
  ADD KEY `logement_id` (`logement_id`);

--
-- Index pour la table `sites`
--
ALTER TABLE `sites`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `todo_list`
--
ALTER TABLE `todo_list`
  ADD PRIMARY KEY (`id`),
  ADD KEY `logement_id` (`logement_id`);

--
-- Index pour la table `total_owed_to_intervenants`
--
ALTER TABLE `total_owed_to_intervenants`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `vf_photos`
--
ALTER TABLE `vf_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_group` (`photo_group`);

--
-- Index pour la table `vf_settings`
--
ALTER TABLE `vf_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Index pour la table `vf_texts`
--
ALTER TABLE `vf_texts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_section_field` (`section_key`,`field_key`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `annexes`
--
ALTER TABLE `annexes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `articles`
--
ALTER TABLE `articles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `chz24_photos`
--
ALTER TABLE `chz24_photos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `chz24_texts`
--
ALTER TABLE `chz24_texts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `comptabilite`
--
ALTER TABLE `comptabilite`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `configuration`
--
ALTER TABLE `configuration`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `contract_entries`
--
ALTER TABLE `contract_entries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `contract_fields`
--
ALTER TABLE `contract_fields`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `contract_templates`
--
ALTER TABLE `contract_templates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `cv01_photos`
--
ALTER TABLE `cv01_photos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `cv01_texts`
--
ALTER TABLE `cv01_texts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `description_logements`
--
ALTER TABLE `description_logements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `factures`
--
ALTER TABLE `factures`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_admins`
--
ALTER TABLE `FC_admins`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_admin_logs`
--
ALTER TABLE `FC_admin_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_admin_sessions`
--
ALTER TABLE `FC_admin_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_articles`
--
ALTER TABLE `FC_articles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_avis`
--
ALTER TABLE `FC_avis`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_avis_codes`
--
ALTER TABLE `FC_avis_codes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_calculateur_params`
--
ALTER TABLE `FC_calculateur_params`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_categories`
--
ALTER TABLE `FC_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_checklists_menage`
--
ALTER TABLE `FC_checklists_menage`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_contacts`
--
ALTER TABLE `FC_contacts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_conversions`
--
ALTER TABLE `FC_conversions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_csrf_tokens`
--
ALTER TABLE `FC_csrf_tokens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_distinctions`
--
ALTER TABLE `FC_distinctions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_legal`
--
ALTER TABLE `FC_legal`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_logements`
--
ALTER TABLE `FC_logements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_medias`
--
ALTER TABLE `FC_medias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_menages`
--
ALTER TABLE `FC_menages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_newsletter`
--
ALTER TABLE `FC_newsletter`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_newsletter_campaigns`
--
ALTER TABLE `FC_newsletter_campaigns`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_paiements_prestataires`
--
ALTER TABLE `FC_paiements_prestataires`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_prestataires`
--
ALTER TABLE `FC_prestataires`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_proprietaires`
--
ALTER TABLE `FC_proprietaires`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_rate_limit`
--
ALTER TABLE `FC_rate_limit`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_reservations`
--
ALTER TABLE `FC_reservations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_revenus`
--
ALTER TABLE `FC_revenus`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_rgpd_config`
--
ALTER TABLE `FC_rgpd_config`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_sections`
--
ALTER TABLE `FC_sections`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_services`
--
ALTER TABLE `FC_services`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_settings`
--
ALTER TABLE `FC_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_simulateur_config`
--
ALTER TABLE `FC_simulateur_config`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_simulateur_villes`
--
ALTER TABLE `FC_simulateur_villes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_simulations`
--
ALTER TABLE `FC_simulations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_stock_fournitures`
--
ALTER TABLE `FC_stock_fournitures`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_stock_mouvements`
--
ALTER TABLE `FC_stock_mouvements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_tarifs`
--
ALTER TABLE `FC_tarifs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_tarifs_menage`
--
ALTER TABLE `FC_tarifs_menage`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_users`
--
ALTER TABLE `FC_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_visites`
--
ALTER TABLE `FC_visites`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `generated_contracts`
--
ALTER TABLE `generated_contracts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `gestion_machines`
--
ALTER TABLE `gestion_machines`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `intervenant`
--
ALTER TABLE `intervenant`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `intervention_tokens`
--
ALTER TABLE `intervention_tokens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `inventaire_logement`
--
ALTER TABLE `inventaire_logement`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `inventaire_objets`
--
ALTER TABLE `inventaire_objets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `inventaire_sessions`
--
ALTER TABLE `inventaire_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `liste_logements`
--
ALTER TABLE `liste_logements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ml01_photos`
--
ALTER TABLE `ml01_photos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ml01_texts`
--
ALTER TABLE `ml01_texts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `objet_logements`
--
ALTER TABLE `objet_logements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `partners`
--
ALTER TABLE `partners`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `planning`
--
ALTER TABLE `planning`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `reservation`
--
ALTER TABLE `reservation`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `role`
--
ALTER TABLE `role`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `sites`
--
ALTER TABLE `sites`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `todo_list`
--
ALTER TABLE `todo_list`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `total_owed_to_intervenants`
--
ALTER TABLE `total_owed_to_intervenants`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `vf_photos`
--
ALTER TABLE `vf_photos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `vf_texts`
--
ALTER TABLE `vf_texts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `annexes`
--
ALTER TABLE `annexes`
  ADD CONSTRAINT `annexes_ibfk_1` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `comptabilite`
--
ALTER TABLE `comptabilite`
  ADD CONSTRAINT `comptabilite_ibfk_1` FOREIGN KEY (`intervenant_id`) REFERENCES `intervenant` (`id`);

--
-- Contraintes pour la table `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `contracts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `intervenant` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contracts_ibfk_2` FOREIGN KEY (`logement_id`) REFERENCES `liste_logements` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `contract_entries`
--
ALTER TABLE `contract_entries`
  ADD CONSTRAINT `contract_entries_ibfk_1` FOREIGN KEY (`logement_id`) REFERENCES `liste_logements` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `description_logements`
--
ALTER TABLE `description_logements`
  ADD CONSTRAINT `description_logements_ibfk_1` FOREIGN KEY (`logement_id`) REFERENCES `liste_logements` (`id`);

--
-- Contraintes pour la table `FC_menages`
--
ALTER TABLE `FC_menages`
  ADD CONSTRAINT `FC_menages_ibfk_1` FOREIGN KEY (`logement_id`) REFERENCES `FC_logements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FC_menages_ibfk_2` FOREIGN KEY (`prestataire_id`) REFERENCES `FC_prestataires` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `FC_menages_ibfk_3` FOREIGN KEY (`reservation_id`) REFERENCES `FC_reservations` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `FC_paiements_prestataires`
--
ALTER TABLE `FC_paiements_prestataires`
  ADD CONSTRAINT `FC_paiements_prestataires_ibfk_1` FOREIGN KEY (`prestataire_id`) REFERENCES `FC_prestataires` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `FC_reservations`
--
ALTER TABLE `FC_reservations`
  ADD CONSTRAINT `FC_reservations_ibfk_1` FOREIGN KEY (`logement_id`) REFERENCES `FC_logements` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `FC_revenus`
--
ALTER TABLE `FC_revenus`
  ADD CONSTRAINT `FC_revenus_ibfk_1` FOREIGN KEY (`logement_id`) REFERENCES `FC_logements` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `FC_stock_fournitures`
--
ALTER TABLE `FC_stock_fournitures`
  ADD CONSTRAINT `FC_stock_fournitures_ibfk_1` FOREIGN KEY (`logement_id`) REFERENCES `FC_logements` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `FC_stock_mouvements`
--
ALTER TABLE `FC_stock_mouvements`
  ADD CONSTRAINT `FC_stock_mouvements_ibfk_1` FOREIGN KEY (`fourniture_id`) REFERENCES `FC_stock_fournitures` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FC_stock_mouvements_ibfk_2` FOREIGN KEY (`logement_id`) REFERENCES `FC_logements` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `FC_stock_mouvements_ibfk_3` FOREIGN KEY (`menage_id`) REFERENCES `FC_menages` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `FC_tarifs_menage`
--
ALTER TABLE `FC_tarifs_menage`
  ADD CONSTRAINT `FC_tarifs_menage_ibfk_1` FOREIGN KEY (`logement_id`) REFERENCES `FC_logements` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `generated_contracts`
--
ALTER TABLE `generated_contracts`
  ADD CONSTRAINT `generated_contracts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `intervenant` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `generated_contracts_ibfk_2` FOREIGN KEY (`logement_id`) REFERENCES `liste_logements` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `intervenants_pages`
--
ALTER TABLE `intervenants_pages`
  ADD CONSTRAINT `intervenants_pages_ibfk_1` FOREIGN KEY (`intervenant_id`) REFERENCES `intervenant` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `intervenants_pages_ibfk_2` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `intervention_tokens`
--
ALTER TABLE `intervention_tokens`
  ADD CONSTRAINT `intervention_tokens_ibfk_1` FOREIGN KEY (`intervention_id`) REFERENCES `planning` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `inventaire_sessions`
--
ALTER TABLE `inventaire_sessions`
  ADD CONSTRAINT `inventaire_sessions_ibfk_1` FOREIGN KEY (`logements_id`) REFERENCES `liste_logements` (`id`);

--
-- Contraintes pour la table `objet_logements`
--
ALTER TABLE `objet_logements`
  ADD CONSTRAINT `objet_logements_ibfk_1` FOREIGN KEY (`logement_id`) REFERENCES `liste_logements` (`id`);

--
-- Contraintes pour la table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `intervenant` (`id`);

--
-- Contraintes pour la table `planning`
--
ALTER TABLE `planning`
  ADD CONSTRAINT `fk_planning_logement` FOREIGN KEY (`logement_id`) REFERENCES `liste_logements` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `sessions_inventaire`
--
ALTER TABLE `sessions_inventaire`
  ADD CONSTRAINT `sessions_inventaire_ibfk_1` FOREIGN KEY (`logement_id`) REFERENCES `liste_logements` (`id`);

--
-- Contraintes pour la table `todo_list`
--
ALTER TABLE `todo_list`
  ADD CONSTRAINT `todo_list_ibfk_1` FOREIGN KEY (`logement_id`) REFERENCES `liste_logements` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
