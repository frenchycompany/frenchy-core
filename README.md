# FrenchyConciergerie - Plateforme de Gestion

Plateforme complete de gestion pour la conciergerie de locations courte duree FrenchyConciergerie. Consolide l'ensemble des outils de gestion (planning, reservations, SMS, comptabilite, inventaire, portail proprietaires) en un seul systeme.

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange.svg)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-purple.svg)](https://getbootstrap.com/)
[![Python](https://img.shields.io/badge/Python-3.11-green.svg)](https://python.org)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg)]()

## Table des matieres

- [Architecture](#architecture)
- [Modules fonctionnels](#modules-fonctionnels)
- [Prerequis](#prerequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Structure du projet](#structure-du-projet)
- [Securite](#securite)
- [Documentation complementaire](#documentation-complementaire)

## Architecture

Le systeme repose sur deux serveurs et trois composants principaux :

| Composant | Emplacement | Role |
|-----------|-------------|------|
| **ionos/gestion** | VPS IONOS | Dashboard de gestion principal (planning, logements, comptabilite, intervenants, inventaire, portail proprietaires) |
| **raspberry-pi** | Raspberry Pi 4 local | Synchronisation iCal, envoi SMS via modem GSM, bot satisfaction, daemon Python |
| **frenchysite** | VPS IONOS | Generateur de sites marketing par propriete |

**Stack technique :**
- **Backend :** PHP 7.4+ (PDO/MySQL), Python 3.11 (daemons, SMS, Selenium)
- **Frontend :** Bootstrap 5.3, JavaScript vanilla, CSS3 avec variables
- **Base de donnees :** MySQL/MariaDB (dual-DB : IONOS + Raspberry Pi)
- **Integrations :** OpenAI API (generation SMS), iCalendar (Airbnb/Booking), Superhote (tarification)

## Modules fonctionnels

### Planning & Interventions
Planification quotidienne des menages, check-in/check-out. Affectation du personnel (conducteur, femme de menage, laverie). Suivi de statut, timeline avec revenus, filtrage multi-criteres.

### Gestion des logements
Catalogue des proprietes avec adresse, capacite, superficie, codes d'acces, tarifs. Equipements par piece, galeries photos, guides. Taux d'occupation et valorisation immobiliere.

### Portail proprietaires
Espace self-service pour les proprietaires : calendrier des reservations, creation de taches, historique des checkups, inventaire, taux d'occupation.

### Reservations & Synchronisation
Import automatique via iCalendar (Airbnb, Booking.com). Sync horaire par cron. Synchronisation des tarifs Superhote via Selenium. Detection des creneaux libres.

### SMS & Communication
Conversations SMS bidirectionnelles via modem GSM (Raspberry Pi). Templates personnalisables par logement. Envoi automatise (check-in, check-out, satisfaction). Generation IA des messages (OpenAI). Campagnes marketing.

### Checkup & Inventaire
Checkup par equipement et par piece avec video obligatoire. Scan QR code pour inventaire. Rapport PDF. Interface mobile optimisee. Attribution des checkups aux intervenants.

### Comptabilite
Double saisie CA/charges. Generation automatique depuis le planning. Facturation par intervenant. Export CSV. Calcul des marges mensuel/annuel.

### Administration
Panneau admin dynamique avec configuration en base. Gestion des utilisateurs et permissions par page. Deploiement de sites. Configuration iCal.

### FrenchySite
Generateur de sites marketing pour chaque propriete. Templates (Vertefeuille). Guides locataires (WiFi, electromenager). Support FR/EN.

## Prerequis

### Serveur principal (VPS)
- **PHP** >= 7.4 avec extensions : PDO, PDO_MySQL, GD, mbstring, cURL, XML
- **MySQL** >= 5.7 ou **MariaDB** >= 10.2
- **Apache** ou **Nginx**
- **Composer** (pour sabre/vobject)

### Serveur SMS (Raspberry Pi)
- **Python** >= 3.6 avec : pymysql, pyserial, python-gammu, openai, requests, selenium
- **Modem GSM** compatible (sur /dev/ttyUSB0)
- **PHP** >= 7.4 + Composer

## Installation

### 1. Cloner le projet

```bash
git clone https://github.com/frenchycompany/frenchy-core.git
cd frenchy-core
```

### 2. Configurer l'environnement

```bash
cp .env.example .env
# Editer .env avec vos parametres de connexion
```

Variables essentielles :
```env
DB_HOST=localhost
DB_NAME=frenchyconciergerie
DB_USER=frenchy_app
DB_PASSWORD=votre_mot_de_passe

OPENAI_API_KEY=sk-votre_cle

RPI_DB_HOST=adresse_raspberry
RPI_DB_NAME=sms_db
RPI_DB_USER=remote
RPI_DB_PASSWORD=votre_mot_de_passe
```

### 3. Configurer la base de donnees

```bash
# Creer la base
mysql -u root -p -e "CREATE DATABASE frenchyconciergerie CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Appliquer le schema (si fourni)
mysql -u root -p frenchyconciergerie < scripts/merge_databases.sql
mysql -u root -p frenchyconciergerie < scripts/cleanup_and_optimize.sql
```

### 4. Installer les dependances PHP (Raspberry Pi)

```bash
cd raspberry-pi
composer install
```

### 5. Installer les dependances Python (Raspberry Pi)

```bash
cd raspberry-pi/scripts
pip install -r requirements.txt
```

### 6. Permissions

```bash
chmod 755 ionos/gestion/uploads ionos/gestion/logs ionos/gestion/cache
chmod 644 .env
```

### 7. Configurer le serveur web

Pointer le domaine `gestion.frenchyconciergerie.fr` vers `ionos/gestion/`.

## Configuration

### Variables d'environnement (.env)

Voir `.env.example` pour la liste complete. Les parametres cles :

| Variable | Description |
|----------|-------------|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Base de donnees principale |
| `RPI_DB_*` | Base de donnees Raspberry Pi (SMS) |
| `OPENAI_API_KEY` | Cle API pour generation de messages |
| `APP_DEBUG` | Mode debug (false en production) |
| `SESSION_TIMEOUT` | Duree de session en secondes |

### PHP (php.ini)

```ini
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
memory_limit = 256M
date.timezone = Europe/Paris
```

## Structure du projet

```
frenchy-core/
├── ionos/                          # Serveur VPS principal
│   ├── gestion/                    # Dashboard de gestion
│   │   ├── index.php               # Page d'accueil / planning
│   │   ├── pages/                  # Pages fonctionnelles (~30 modules)
│   │   │   ├── planning.php        # Planning des interventions
│   │   │   ├── logements.php       # Gestion des logements
│   │   │   ├── reservations.php    # Reservations & iCal
│   │   │   ├── comptabilite.php    # Comptabilite
│   │   │   ├── intervenants.php    # Gestion du personnel
│   │   │   ├── inventaire.php      # Inventaire & QR codes
│   │   │   ├── checkup.php         # Checkup par equipement
│   │   │   ├── calendrier.php      # Calendrier multi-logements
│   │   │   ├── superhote.php       # Sync tarifs Superhote
│   │   │   └── ...
│   │   ├── proprietaire/           # Portail proprietaires
│   │   ├── includes/               # Includes partages (DB, env, securite)
│   │   ├── api/                    # Endpoints API REST
│   │   ├── css/                    # Styles
│   │   └── db/                     # Connexion base de donnees
│   ├── admin/                      # Panel admin
│   └── index.php                   # Site public
├── raspberry-pi/                   # Serveur local Raspberry Pi
│   ├── web/                        # Interface web SMS
│   │   └── pages/                  # Pages de gestion SMS
│   ├── scripts/                    # Daemons et automatisation
│   │   ├── envoyer_sms.py          # Daemon envoi SMS (modem GSM)
│   │   ├── satisfaction_bot.py     # Bot satisfaction client
│   │   ├── sync_reservations.php   # Sync iCal (cron)
│   │   └── selenium/               # Automation Superhote
│   ├── config/                     # Configuration
│   └── composer.json               # Dependances PHP
├── frenchysite/                    # Generateur de sites marketing
│   ├── admin.php                   # Interface admin
│   └── install.php                 # Wizard d'installation
├── scripts/                        # Scripts utilitaires
│   ├── merge_databases.sql         # Script de migration DB
│   └── cleanup_and_optimize.sql    # Optimisation DB
├── .env.example                    # Template de configuration
├── .gitignore                      # Fichiers exclus du versionning
└── MIGRATION_PLAN.md               # Plan de migration VPS unifie
```

## Securite

### Mesures implementees
- **PDO prepared statements** sur toutes les requetes SQL
- **Tokens CSRF** sur les formulaires
- **password_hash() / password_verify()** pour les mots de passe
- **Rate limiting** sur les tentatives de connexion
- **Session securisee** (HTTPOnly, SameSite=Strict)
- **Variables d'environnement** pour les secrets (.env)
- **Validation des uploads** (type MIME, extension, taille)
- **.htaccess** de protection sur les repertoires sensibles

### Recommandations pour la production
- Activer HTTPS obligatoire
- Configurer les headers de securite (CSP, X-Frame-Options, HSTS)
- Mettre `APP_DEBUG=false`
- Sauvegardes automatiques de la base de donnees
- Monitoring des logs d'erreur
- Firewall configure (UFW/iptables)

## Documentation complementaire

| Document | Description |
|----------|-------------|
| [ionos/gestion/README.md](ionos/gestion/README.md) | Documentation detaillee du dashboard de gestion |
| [raspberry-pi/README.md](raspberry-pi/README.md) | Documentation du systeme SMS |
| [MIGRATION_PLAN.md](MIGRATION_PLAN.md) | Plan de migration vers VPS unifie |
| [ionos/gestion/ANALYSE_STRUCTURE_DB.md](ionos/gestion/ANALYSE_STRUCTURE_DB.md) | Analyse du schema de base de donnees |
| [raspberry-pi/SECURITY_IMPROVEMENTS.md](raspberry-pi/SECURITY_IMPROVEMENTS.md) | Ameliorations securite |

---

Proprietary - FrenchyConciergerie. Tous droits reserves.
