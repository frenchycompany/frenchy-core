# Plan de Migration — Consolidation des Outils de Gestion FrenchyConciergerie

**Version :** 1.0
**Date :** 2026-02-24
**Branche :** `claude/consolidate-management-tools-Yezzj`

---

## Table des matières

1. [Contexte et objectif](#1-contexte-et-objectif)
2. [État actuel de l'infrastructure](#2-état-actuel-de-linfrastructure)
3. [Inventaire fonctionnel](#3-inventaire-fonctionnel)
4. [Architecture cible (VPS unifié)](#4-architecture-cible-vps-unifié)
5. [Schéma de base de données unifié](#5-schéma-de-base-de-données-unifié)
6. [Plan de migration étape par étape](#6-plan-de-migration-étape-par-étape)
7. [Stratégie de rollback](#7-stratégie-de-rollback)
8. [Checklist de déploiement](#8-checklist-de-déploiement)

---

## 1. Contexte et objectif

### Problème actuel

L'infrastructure FrenchyConciergerie est fragmentée sur **deux serveurs distincts** avec des outils de gestion qui se chevauchent et des bases de données redondantes :

| Serveur | Rôle | Contraintes |
|---------|------|-------------|
| **IONOS** (hébergement mutualisé) | Site public, gestion ménages, comptabilité, inventaire | Pas d'accès root, PHP limité, pas de cron fiable |
| **Raspberry Pi 4** (local/domicile) | Sync réservations iCal, envoi SMS, bot satisfaction, tableau de bord | Dépend de la connexion internet locale, matériel fragile |

### Objectif

Consolider l'ensemble sur un **VPS unique** (ex. : Hetzner CX22 ou OVH VPS) pour :

- ✅ **Une seule base de données** — fin des redondances (`liste_logements` existe dans les deux BDs)
- ✅ **Un seul tableau de bord admin** — fusion de `ionos/gestion` et `raspberry-pi/web`
- ✅ **Fiabilité** — VPS disponible 24/7, pas de dépendance au réseau domestique
- ✅ **Maintenabilité** — un seul endroit pour les mises à jour, les logs, les sauvegardes
- ✅ **Sécurité** — HTTPS, firewall, secrets en variables d'environnement

---

## 2. État actuel de l'infrastructure

### 2.1 Serveur IONOS (mutualisé)

**Base de données :** `dbs13515816` — **84 tables**

Domaines fonctionnels :
- **FC_*** (40+ tables) : Site public FrenchyConciergerie — logements, réservations propriétaires, avis, newsletter, calculateur, simulateur, prestataires, revenus
- **Gestion ménages** : `planning`, `intervenant`, `intervenants_pages`, `role`, `intervention_tokens`
- **Comptabilité** : `comptabilite`, `factures`
- **Inventaire** : `inventaire_objets`, `inventaire_sessions`, `sessions_inventaire`, `inventaire_logement`
- **Logements** : `liste_logements`, `description_logements`, `poids_critere`, `poids_criteres`
- **Contrats** : `contracts`, `contract_templates`, `contract_entries`, `contract_fields`, `generated_contracts`
- **CMS** : `articles`, `sites`, `partners`, `pages`
- **Leads** : `leads`

**Applications web hébergées :**
- `ionos/index.php` — site public FrenchyConciergerie (101 Ko)
- `ionos/admin/index.php` — panneau admin principal (181 Ko)
- `ionos/admin/menage.php` — gestion ménages (69 Ko)
- `ionos/gestion/` — application de gestion complète (MVC partiel, ~50 pages PHP)
- `ionos/cdansmaville/` — CMS multi-tenant landing pages
- `ionos/vertefeuille/` + `ionos/install site/` — sites propriétés individuelles
- `ionos/proprietaire/` — espace propriétaire

### 2.2 Raspberry Pi (serveur local)

**Base de données :** `frenchyconciergerie` — **69 tables**

Domaines fonctionnels :
- **Réservations** : `reservation`, `ical_reservations`, `ical_sync_log`, `travel_account_connections`, `listing_mappings`
- **SMS** : `sms_in`, `sms_out`, `sms_messages`, `sms_conversations`, `inbox`, `outbox`, `sentitems` (tables Gammu legacy)
- **Templates** : `sms_templates` (implicite via configuration), `sms_automations`
- **Campagnes** : `campagne_sms`, `campagne_immo`
- **Conversations** : `conversations`, `conversation_messages`, `satisfaction_conversations`
- **Config** : `configuration`, `ai_prompts`, `config`, `users`
- **Contacts** : `client`, `contacts`
- **Comptabilité** (doublons) : `comptabilite`, `intervenant`, `liste_logements`, `logement`

**Scripts d'automatisation :**
- `scripts/sync_reservations.php` — sync iCal toutes les heures (cron)
- `scripts/auto_send_sms.php` — envoi SMS automatique toutes les 30 min (cron)
- `scripts/envoyer_sms.py` — service envoi modem GSM (daemon)
- `scripts/satisfaction_bot.py` — bot satisfaction client (daemon)
- `scripts/selenium/superhote_*.py` — automation Superhôte (prices, planning)

**Interface web :**
- `raspberry-pi/web/pages/` — ~70 pages PHP (dashboard, réservations, SMS, templates, clients, campagnes, outils)

### 2.3 Chevauchements identifiés

| Table/Fonctionnalité | IONOS (`dbs13515816`) | Raspberry Pi (`frenchyconciergerie`) |
|---|---|---|
| `liste_logements` / `logement` | ✅ Données gestion ménages | ✅ Données réservations/SMS |
| `comptabilite` | ✅ Complet (368 299 lignes) | ✅ Partiellement dupliqué |
| `intervenant` | ✅ Avec rôles et accès | ✅ Partiellement dupliqué |
| `reservation` | Via `FC_reservations` (propriétaires) | ✅ Réservations Airbnb/Booking |
| `contracts`/`contrats` | ✅ Templates et entrées | — |
| `articles`/`sites`/`partners` | ✅ CMS | — |
| `users` | Via `intervenant` + `FC_users` | ✅ Table `users` dédiée |

---

## 3. Inventaire fonctionnel

### Domaines métier et modules associés

```
FrenchyConciergerie
│
├── 🏘️  GESTION LOGEMENTS
│   ├── Liste des propriétés (adresse, capacité, m², codes accès)
│   ├── Descriptions détaillées (poids ménage, critères)
│   ├── Équipements et inventaire (objets, sessions, QR codes)
│   └── Sites propriétés individuelles (Vertefeuille, etc.)
│
├── 📅  PLANNING & INTERVENTIONS
│   ├── Planification journalière (nettoyages, check-in/out)
│   ├── Affectation intervenants (conducteur, ménage, laverie)
│   ├── Suivi statuts (À faire → En cours → Fait)
│   └── Gestion options (early check-in, late checkout, bonus)
│
├── 👥  RESSOURCES HUMAINES
│   ├── Intervenants (profils, rôles, contacts)
│   ├── Permissions par page
│   ├── Calcul rémunérations
│   └── Historique des interventions
│
├── 💰  COMPTABILITÉ
│   ├── Saisie CA et charges
│   ├── Facturation intervenants
│   ├── Export CSV/PDF
│   └── Statistiques et marges
│
├── 📆  RÉSERVATIONS
│   ├── Sync iCalendar (Airbnb, Booking, etc.)
│   ├── Gestion des réservations clients
│   ├── Portail propriétaires
│   └── Calendrier d'occupation
│
├── 📱  COMMUNICATION SMS
│   ├── Templates personnalisables par logement
│   ├── Envoi automatisé (check-in, check-out, préparation)
│   ├── Bot de satisfaction client
│   ├── Conversations bidirectionnelles
│   └── Campagnes marketing (immo, satisfaction)
│
├── 🤖  AUTOMATISATION
│   ├── Mise à jour prix Superhôte (Selenium)
│   ├── Synchronisation iCal horaire
│   └── Envoi SMS programmé
│
├── 📊  MARKETING & CRM
│   ├── Campagnes SMS immobilières
│   ├── Leads et suivi prospects
│   ├── Newsletter
│   └── Avis clients
│
└── 🌐  SITES WEB
    ├── Site public FrenchyConciergerie
    ├── CMS CdansmaVille (multi-tenant)
    └── Sites propriétés individuelles
```

---

## 4. Architecture cible (VPS unifié)

### 4.1 Spécifications recommandées

**VPS minimal :** Hetzner CX22 ou équivalent
- 2 vCPU, 4 Go RAM, 40 Go SSD NVMe
- OS : Debian 12 Bookworm
- ~6 €/mois

**Stack technique :**
- Nginx + PHP 8.2-FPM
- MariaDB 10.11
- Python 3.11 + venv
- Supervisor (gestion daemons Python)
- Let's Encrypt (HTTPS)
- UFW (firewall)

### 4.2 Structure des sous-domaines

```
frenchyconciergerie.fr         → Site public (ionos/index.php)
gestion.frenchyconciergerie.fr → App gestion unifiée (fusionné)
proprietaire.frenchyconciergerie.fr → Espace propriétaires
[ville].cdansmaville.fr        → CMS multi-tenant
[propriete].fr                 → Sites propriétés individuelles
```

### 4.3 Structure de répertoires VPS

```
/var/www/
├── frenchy-public/          # Site public (ionos/)
├── frenchy-gestion/         # APP GESTION UNIFIÉE ← nouveau
│   ├── api/                 # API REST interne
│   ├── assets/
│   ├── config/
│   ├── pages/               # Pages PHP fusionnées
│   │   ├── planning.php
│   │   ├── intervenants.php
│   │   ├── reservations.php
│   │   ├── sms/
│   │   ├── comptabilite.php
│   │   ├── inventaire/
│   │   └── ...
│   ├── scripts/             # Scripts automation (PHP + Python)
│   ├── src/                 # Classes MVC
│   └── web/                 # Anciens fichiers web Raspberry Pi
├── frenchy-proprietaire/    # Espace propriétaires
└── cdansmaville/            # CMS multi-tenant

/home/frenchy/
├── scripts/                 # Scripts Python automation
│   ├── envoyer_sms.py
│   ├── satisfaction_bot.py
│   ├── sync_reservations.php
│   └── selenium/            # Automation Superhôte
├── logs/                    # Logs centralisés (avec logrotate)
└── backups/                 # Sauvegardes DB quotidiennes
```

### 4.4 Architecture de la base de données unifiée

**Une seule base de données :** `frenchyconciergerie`

Voir section 5 pour le détail du schéma.

---

## 5. Schéma de base de données unifié

### 5.1 Stratégie de fusion

| Situation | Action |
|-----------|--------|
| Table identique dans les deux BDs | Merger les données, garder la plus complète |
| Table présente seulement côté IONOS | Migrer telle quelle |
| Table présente seulement côté Raspberry Pi | Migrer telle quelle |
| Tables similaires (ex. `logement` vs `liste_logements`) | Fusionner en une seule table avec toutes les colonnes |
| Tables legacy Gammu (`inbox`, `outbox`, `sentitems`, `phones`) | Supprimer après migration des données utiles |

### 5.2 Tables à fusionner

#### `logements` (fusion de `liste_logements` IONOS + `logement` Raspberry)

```sql
CREATE TABLE `logements` (
  `id`                    INT AUTO_INCREMENT PRIMARY KEY,
  -- Champs communs (IONOS)
  `nom_du_logement`       VARCHAR(255) NOT NULL,
  `adresse`               VARCHAR(255),
  `m2`                    FLOAT,
  `nombre_de_personnes`   INT,
  `poid_menage`           DECIMAL(5,2),
  `prix_vente_menage`     FLOAT,
  `code`                  VARCHAR(255),
  `valeur_locative`       FLOAT,
  `valeur_fonciere`       FLOAT,
  -- Champs SMS/Réservations (Raspberry Pi)
  `ics_url`               TEXT COMMENT 'URL iCalendar Airbnb/Booking',
  `ics_url_2`             TEXT,
  `telephone`             VARCHAR(20),
  `sms_template_id`       INT,
  -- Champs communs aux deux
  `actif`                 TINYINT(1) DEFAULT 1,
  `created_at`            DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `users` (fusion de `intervenant` + `users` Raspberry + `FC_users` IONOS)

```sql
CREATE TABLE `users` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `nom`                 VARCHAR(255) NOT NULL,
  `prenom`              VARCHAR(100),
  `email`               VARCHAR(255) UNIQUE,
  `telephone`           VARCHAR(50),
  `password_hash`       VARCHAR(255),
  -- Rôles système
  `role`                ENUM('super_admin','admin','gestionnaire','intervenant','proprietaire','viewer') DEFAULT 'viewer',
  -- Compatibilité intervenant (IONOS)
  `role1`               VARCHAR(255) COMMENT 'Conducteur/Femme de ménage/Laverie',
  `role2`               VARCHAR(255),
  `role3`               VARCHAR(255),
  `pages_accessibles`   TEXT,
  -- Sécurité
  `actif`               TINYINT(1) DEFAULT 1,
  `last_login`          DATETIME,
  `created_at`          DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_role` (`role`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5.3 Tables à supprimer (legacy Gammu)

```sql
-- Après migration des données SMS utiles vers sms_in/sms_out :
DROP TABLE IF EXISTS `gammu`;
DROP TABLE IF EXISTS `inbox`;       -- Remplacé par sms_in
DROP TABLE IF EXISTS `outbox`;      -- Remplacé par sms_out/sms_messages
DROP TABLE IF EXISTS `outbox_multipart`;
DROP TABLE IF EXISTS `sentitems`;   -- Remplacé par sms_out
DROP TABLE IF EXISTS `phones`;
```

### 5.4 Tables dupliquées à consolider

| Tables à fusionner | Table résultante | Notes |
|---|---|---|
| `liste_logements` (IONOS) + `logement` (RPi) | `logements` | Voir §5.2 |
| `intervenant` (IONOS) + `users` (RPi) + `FC_users` (IONOS) | `users` | Voir §5.2 |
| `comptabilite` (IONOS) + `comptabilite` (RPi) | `comptabilite` | Garder structure IONOS (plus complète) |
| `poids_critere` + `poids_criteres` (doublons dans IONOS) | `poids_criteres` | Supprimer le doublon |
| `inventaire_sessions` + `sessions_inventaire` (IONOS) | `sessions_inventaire` | Garder la plus complète |

---

## 6. Plan de migration étape par étape

### Phase 1 — Préparation (Semaine 1)

#### 1.1 Provisionner le VPS

```bash
# Sur le VPS Debian 12
apt-get update && apt-get upgrade -y
apt-get install -y nginx mariadb-server php8.2-fpm php8.2-mysql php8.2-curl \
  php8.2-gd php8.2-mbstring php8.2-xml php8.2-zip \
  python3.11 python3-pip python3-venv supervisor certbot \
  python3-certbot-nginx git composer ufw fail2ban

# Firewall
ufw allow ssh
ufw allow http
ufw allow https
ufw enable
```

#### 1.2 Sécuriser MariaDB

```bash
mysql_secure_installation
mysql -u root -p <<'EOF'
CREATE DATABASE frenchyconciergerie CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'frenchy_app'@'localhost' IDENTIFIED BY '<MOT_DE_PASSE_FORT>';
GRANT ALL PRIVILEGES ON frenchyconciergerie.* TO 'frenchy_app'@'localhost';
FLUSH PRIVILEGES;
EOF
```

#### 1.3 Configurer les variables d'environnement

```bash
# /etc/frenchy/app.env (permissions 640, owned by www-data)
APP_ENV=production
APP_DEBUG=false

DB_HOST=localhost
DB_NAME=frenchyconciergerie
DB_USER=frenchy_app
DB_PASSWORD=<MOT_DE_PASSE_FORT>

# SMS / OpenAI
OPENAI_API_KEY=sk-...  # Nouvelle clé (révoquer l'ancienne exposée dans le repo)
MODEM_PORT=/dev/ttyUSB0
ADMIN_PHONE=+33XXXXXXXXX

# Sessions
SESSION_SECRET=<CHAINE_ALEATOIRE_64_CHARS>
```

> ⚠️ **CRITIQUE** : La clé OpenAI `sk-proj-LRiJXib...` exposée dans `raspberry-pi/config/config.ini` doit être **révoquée immédiatement** sur https://platform.openai.com et remplacée par une nouvelle clé.

#### 1.4 Sauvegarder les bases de données existantes

```bash
# Depuis IONOS (via phpMyAdmin ou SSH si disponible)
mysqldump -u <user> -p dbs13515816 > backup_ionos_$(date +%Y%m%d).sql

# Depuis Raspberry Pi
mysqldump -u root -p frenchyconciergerie > backup_rpi_$(date +%Y%m%d).sql

# Copier vers le VPS
scp backup_*.sql user@vps:/home/frenchy/backups/
```

---

### Phase 2 — Migration des bases de données (Semaine 1-2)

#### 2.1 Importer la base IONOS comme base de départ

```bash
# Importer la BDD IONOS (plus complète, avec FC_* tables)
mysql -u frenchy_app -p frenchyconciergerie < backup_ionos_YYYYMMDD.sql
```

#### 2.2 Script de fusion des tables dupliquées

```sql
-- Étape 1 : Renommer liste_logements en logements
ALTER TABLE `liste_logements` RENAME TO `logements`;

-- Étape 2 : Ajouter les colonnes manquantes depuis la table logement (RPi)
ALTER TABLE `logements`
  ADD COLUMN `ics_url` TEXT AFTER `code`,
  ADD COLUMN `ics_url_2` TEXT AFTER `ics_url`,
  ADD COLUMN `actif` TINYINT(1) DEFAULT 1;

-- Étape 3 : Migrer les données de logement (RPi) vers logements
-- (exécuter depuis une connexion ayant accès aux deux BDs)
INSERT INTO frenchyconciergerie.logements
  (id, nom_du_logement, adresse, ics_url, ics_url_2, actif)
SELECT
  id,
  nom,
  adresse,
  ics_url,
  ics_url_2,
  1
FROM rpi_frenchyconciergerie.logement
ON DUPLICATE KEY UPDATE
  ics_url = VALUES(ics_url),
  ics_url_2 = VALUES(ics_url_2);
```

#### 2.3 Migrer les tables uniquement côté Raspberry Pi

```sql
-- Tables à importer depuis la BDD Raspberry Pi
-- (exécuter après import de la BDD Raspberry Pi dans un schéma temporaire)

-- Réservations et sync iCal
INSERT INTO frenchyconciergerie.reservation SELECT * FROM rpi_backup.reservation
  ON DUPLICATE KEY UPDATE statut = VALUES(statut), updated_at = VALUES(updated_at);

INSERT INTO frenchyconciergerie.ical_reservations SELECT * FROM rpi_backup.ical_reservations
  ON DUPLICATE KEY UPDATE uid = VALUES(uid);

INSERT INTO frenchyconciergerie.ical_sync_log SELECT * FROM rpi_backup.ical_sync_log;

-- SMS
INSERT INTO frenchyconciergerie.sms_in SELECT * FROM rpi_backup.sms_in
  ON DUPLICATE KEY IGNORE;
INSERT INTO frenchyconciergerie.sms_out SELECT * FROM rpi_backup.sms_out
  ON DUPLICATE KEY IGNORE;

-- Conversations
INSERT INTO frenchyconciergerie.conversations SELECT * FROM rpi_backup.conversations
  ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at);

INSERT INTO frenchyconciergerie.conversation_messages SELECT * FROM rpi_backup.conversation_messages;

-- Campagnes
INSERT INTO frenchyconciergerie.campagne_sms SELECT * FROM rpi_backup.campagne_sms;
INSERT INTO frenchyconciergerie.campagne_immo SELECT * FROM rpi_backup.campagne_immo
  ON DUPLICATE KEY UPDATE statut_contact = VALUES(statut_contact);

-- Clients
INSERT INTO frenchyconciergerie.client SELECT * FROM rpi_backup.client
  ON DUPLICATE KEY UPDATE nom = VALUES(nom), email = VALUES(email);

-- Configuration et prompts IA
INSERT INTO frenchyconciergerie.ai_prompts SELECT * FROM rpi_backup.ai_prompts
  ON DUPLICATE KEY UPDATE content = VALUES(content);

INSERT INTO frenchyconciergerie.configuration SELECT * FROM rpi_backup.configuration
  ON DUPLICATE KEY UPDATE valeur = VALUES(valeur);

-- Fusionner les users
INSERT INTO frenchyconciergerie.users (nom, prenom, email, password_hash, role)
SELECT username, '', email, password_hash, 'admin'
FROM rpi_backup.users
WHERE email NOT IN (SELECT email FROM frenchyconciergerie.users WHERE email IS NOT NULL);
```

#### 2.4 Nettoyer les tables legacy

```sql
-- Supprimer les tables Gammu obsolètes (après vérification)
DROP TABLE IF EXISTS `gammu`;
DROP TABLE IF EXISTS `inbox`;
DROP TABLE IF EXISTS `outbox`;
DROP TABLE IF EXISTS `outbox_multipart`;
DROP TABLE IF EXISTS `sentitems`;
DROP TABLE IF EXISTS `phones`;

-- Consolider les doublons inventaire
ALTER TABLE `inventaire_sessions` RENAME TO `inventaire_sessions_old`;
-- (après vérification que sessions_inventaire contient toutes les données)
```

---

### Phase 3 — Déploiement des applications (Semaine 2-3)

#### 3.1 Déployer le code source

```bash
cd /var/www
git clone https://github.com/frenchycompany/frenchy-core.git
chown -R www-data:www-data frenchy-core/
```

#### 3.2 Configurer l'application de gestion unifiée

La fusion de `ionos/gestion/` et `raspberry-pi/web/` se fait par :

1. **Garder `ionos/gestion/` comme base** (architecture MVC, sécurité plus avancée)
2. **Intégrer les pages manquantes de `raspberry-pi/web/pages/`** dans `ionos/gestion/pages/`

Pages à intégrer depuis `raspberry-pi/web/pages/` :

| Page Raspberry Pi | Page cible dans gestion | Notes |
|---|---|---|
| `reservations_listing.php` | `pages/reservations.php` | Fusion avec planning |
| `sms_ai_suggest.php` | `pages/sms/ai_suggest.php` | Nouveau module |
| `templates.php` | `pages/sms/templates.php` | Nouveau module |
| `automations.php` | `pages/sms/automations.php` | Nouveau module |
| `campaigns.php` | `pages/sms/campaigns.php` | Nouveau module |
| `clients.php` | `pages/clients.php` | Nouveau module |
| `travel_accounts.php` | `pages/sync/travel_accounts.php` | Nouveau module |
| `logement_equipements.php` | `pages/logements_equipements.php` | Fusion avec logements |
| `superhote_config.php` | `pages/outils/superhote.php` | Nouveau module |
| `analyse_marche.php` | `pages/outils/analyse_marche.php` | Nouveau module |
| `occupation.php` | `pages/statistiques_occupation.php` | Fusion avec stats |

#### 3.3 Configurer Nginx

```nginx
# /etc/nginx/sites-available/frenchy-gestion
server {
    listen 443 ssl http2;
    server_name gestion.frenchyconciergerie.fr;
    root /var/www/frenchy-core/ionos/gestion;

    ssl_certificate /etc/letsencrypt/live/gestion.frenchyconciergerie.fr/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/gestion.frenchyconciergerie.fr/privkey.pem;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Bloquer l'accès aux fichiers sensibles
    location ~ /\.(env|git|htaccess) {
        deny all;
    }
}

server {
    listen 80;
    server_name gestion.frenchyconciergerie.fr;
    return 301 https://$host$request_uri;
}
```

#### 3.4 Configurer les scripts Python

```bash
# Créer l'environnement virtuel
python3 -m venv /home/frenchy/venv
source /home/frenchy/venv/bin/activate
pip install pymysql pyserial python-gammu openai requests

# Copier les scripts
cp -r raspberry-pi/scripts/ /home/frenchy/scripts/

# Corriger les chemins absolus dans les scripts
sed -i 's|/home/raphael/sms_project|/home/frenchy|g' \
  /home/frenchy/scripts/envoyer_sms.py \
  /home/frenchy/scripts/satisfaction_bot.py
```

#### 3.5 Configurer Supervisor pour les daemons Python

```ini
# /etc/supervisor/conf.d/frenchy-sms.conf
[program:frenchy-sms-sender]
command=/home/frenchy/venv/bin/python3 /home/frenchy/scripts/envoyer_sms.py
directory=/home/frenchy
user=frenchy
autostart=true
autorestart=true
stderr_logfile=/home/frenchy/logs/sms-sender.err.log
stdout_logfile=/home/frenchy/logs/sms-sender.log
environment=PYTHONPATH="/home/frenchy"

[program:frenchy-satisfaction-bot]
command=/home/frenchy/venv/bin/python3 /home/frenchy/scripts/satisfaction_bot.py
directory=/home/frenchy
user=frenchy
autostart=true
autorestart=true
stderr_logfile=/home/frenchy/logs/satisfaction-bot.err.log
stdout_logfile=/home/frenchy/logs/satisfaction-bot.log
```

#### 3.6 Configurer les cron jobs

```cron
# /etc/cron.d/frenchy
# Synchronisation iCal toutes les heures
0 * * * * www-data php /var/www/frenchy-core/raspberry-pi/scripts/sync_reservations.php >> /home/frenchy/logs/cron_sync.log 2>&1

# Envoi SMS automatique toutes les 30 minutes
*/30 * * * * www-data php /var/www/frenchy-core/raspberry-pi/scripts/auto_send_sms.php >> /home/frenchy/logs/cron_sms.log 2>&1

# Backup DB quotidien à 3h
0 3 * * * frenchy /home/frenchy/scripts/backup_db.sh >> /home/frenchy/logs/backup.log 2>&1

# Rotation logs hebdomadaire
0 0 * * 0 frenchy find /home/frenchy/logs -name "*.log" -mtime +30 -delete
```

---

### Phase 4 — Tests et bascule (Semaine 3-4)

#### 4.1 Tests à effectuer

**Fonctionnels :**
- [ ] Connexion à l'interface de gestion unifiée
- [ ] Chargement du planning et affectation des ménages
- [ ] Sync iCal (exécution manuelle de `sync_reservations.php`)
- [ ] Envoi SMS test vers numéro de test
- [ ] Affichage des réservations dans le tableau de bord
- [ ] Création d'une entrée comptable
- [ ] Génération d'un contrat PDF
- [ ] Accès depuis mobile (responsive)

**Automatisation :**
- [ ] Vérification supervisor (`supervisorctl status`)
- [ ] Test du cron de sync (vérifier logs)
- [ ] Test du bot satisfaction (simuler une réponse SMS)

**Sécurité :**
- [ ] HTTPS fonctionnel sur tous les sous-domaines
- [ ] Redirections HTTP → HTTPS
- [ ] Fichiers `.env` inaccessibles depuis le web
- [ ] Pages sans authentification bloquées
- [ ] Tokens CSRF fonctionnels

#### 4.2 Checklist pré-bascule

- [ ] DNS configurés pour pointer vers le VPS
- [ ] Certificats Let's Encrypt générés
- [ ] Sauvegarde complète de l'état actuel IONOS + Raspberry Pi
- [ ] Équipe informée de la fenêtre de maintenance
- [ ] Procédure de rollback testée (voir §7)

#### 4.3 Bascule progressive

**Ordre recommandé :**

1. **Jour 1 :** Basculer uniquement le tableau de bord SMS (raspberry-pi/web → VPS). Laisser IONOS en place.
2. **Jour 3-5 :** Basculer le module gestion ménages (ionos/gestion → VPS).
3. **Semaine 2 :** Basculer le site public si souhaité.
4. **Semaine 3 :** Désactiver l'ancienne infrastructure après validation.

---

### Phase 5 — Arrêt de l'ancienne infrastructure (Semaine 4+)

#### 5.1 Vérifications avant arrêt

```bash
# Vérifier qu'aucun cron ne pointe encore vers le Raspberry Pi
crontab -l | grep -v VPS

# Vérifier les logs pour s'assurer que tout tourne sur le VPS
tail -f /home/frenchy/logs/cron_sync.log
tail -f /home/frenchy/logs/cron_sms.log

# Confirmer que les données se synchronisent correctement
mysql -u frenchy_app -p frenchyconciergerie \
  -e "SELECT COUNT(*), MAX(created_at) FROM reservation;"
```

#### 5.2 Archiver Raspberry Pi

- Faire une image complète de la carte SD (backup final)
- Conserver le modem GSM et le brancher sur le VPS (si le VPS est physiquement accessible) **OU** utiliser un service SMS API (Twilio, OVH SMS, etc.) comme alternative au modem GSM

> 💡 **Recommandation :** Si le modem GSM ne peut pas être déplacé vers le VPS, migrer vers une API SMS (Twilio, OVH SMS API) pour éliminer la dépendance matérielle. Coût ≈ 0,05-0,08 €/SMS.

---

## 7. Stratégie de rollback

### Si la migration échoue à n'importe quelle phase :

#### Rollback Phase 2 (base de données)

```bash
# Restaurer la BDD depuis la sauvegarde
mysql -u frenchy_app -p frenchyconciergerie < backup_ionos_YYYYMMDD.sql

# Ou supprimer et recréer la BDD
mysql -u root -p <<'EOF'
DROP DATABASE frenchyconciergerie;
CREATE DATABASE frenchyconciergerie CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON frenchyconciergerie.* TO 'frenchy_app'@'localhost';
EOF
mysql -u frenchy_app -p frenchyconciergerie < backup_ionos_YYYYMMDD.sql
```

#### Rollback Phase 3 (DNS)

```bash
# Remettre les DNS vers l'hébergement IONOS
# (via le panneau DNS de votre registrar)
# Le TTL doit être préalablement réduit à 300s avant la migration
```

#### Rollback Raspberry Pi

```bash
# Remettre les crons sur le Raspberry Pi
# Redémarrer les daemons Python
sudo systemctl start sms-sender
sudo systemctl start satisfaction-bot
```

### Points de sauvegarde

| Étape | Sauvegarde à créer |
|---|---|
| Avant Phase 1 | Image disque IONOS + dump BDD IONOS |
| Avant Phase 2 | Dump BDD Raspberry Pi |
| Avant Phase 3 | Sauvegarde code source IONOS |
| Avant Phase 4 | Snapshot VPS |

---

## 8. Checklist de déploiement

### Infrastructure

- [ ] VPS provisionné et sécurisé (SSH par clé, fail2ban actif)
- [ ] MariaDB installé et sécurisé
- [ ] Nginx configuré avec HTTPS
- [ ] PHP 8.2 FPM configuré
- [ ] Python 3.11 + venv configuré
- [ ] Supervisor configuré pour les daemons
- [ ] Cron jobs configurés
- [ ] Logrotate configuré
- [ ] Script backup quotidien en place

### Base de données

- [ ] Schéma unifié importé
- [ ] Tables dupliquées fusionnées
- [ ] Tables legacy Gammu supprimées
- [ ] Indexes de performance ajoutés
- [ ] Utilisateur DB créé avec permissions minimales

### Application

- [ ] Variables d'environnement configurées (`.env` hors du webroot)
- [ ] Ancienne clé OpenAI révoquée et remplacée
- [ ] Mot de passe par défaut `Admin123!` retiré de login.php
- [ ] Chemins absolus `/home/raphael/` corrigés dans scripts Python
- [ ] Validation des URLs iCal (whitelist domaines autorisés)
- [ ] Headers de sécurité ajoutés dans Nginx
- [ ] Pages de l'interface unifiée testées

### Fonctionnel

- [ ] Sync iCal fonctionnelle (test manuel)
- [ ] Envoi SMS fonctionnel (test avec numéro dédié)
- [ ] Bot satisfaction actif
- [ ] Planning ménages accessible
- [ ] Comptabilité fonctionnelle
- [ ] Inventaire QR code fonctionnel
- [ ] Campagnes SMS accessibles
- [ ] Export CSV/PDF fonctionnel

### Sécurité finale

- [ ] Aucune credential dans le code source
- [ ] HTTPS sur tous les sous-domaines
- [ ] Accès SSH restreint par IP (si possible)
- [ ] Monitoring uptime configuré (UptimeRobot gratuit)
- [ ] Alertes email en cas de panne

---

## Annexe A — Références fichiers clés

| Fichier | Taille | Rôle |
|---|---|---|
| `ionos/admin/index.php` | 181 Ko | Panneau admin principal IONOS |
| `ionos/gestion/pages/planning.php` | 37 Ko | Planification interventions |
| `ionos/gestion/pages/statistiques.php` | 14 Ko | Statistiques et rapports |
| `raspberry-pi/web/pages/superhote_config.php` | 105 Ko | Config automation Superhôte |
| `raspberry-pi/web/pages/logement_equipements.php` | 93 Ko | Équipements logements |
| `raspberry-pi/web/pages/clients.php` | 45 Ko | Gestion clients |
| `raspberry-pi/scripts/selenium/superhote_base.py` | 108 Ko | Automation Superhôte (Selenium) |
| `raspberry-pi/schema_dump.sql` | 64 Ko | Schéma BDD Raspberry Pi |
| `ionos/dbs13515816.sql` | 82 Ko | Schéma + données BDD IONOS |

## Annexe B — Problèmes de sécurité à corriger en priorité

1. **🔴 CRITIQUE** : Révoquer la clé API OpenAI exposée dans `raspberry-pi/config/config.ini` (ligne 15)
2. **🔴 CRITIQUE** : Supprimer les credentials par défaut dans `raspberry-pi/web/pages/login.php` (lignes 121-124)
3. **🔴 CRITIQUE** : Exclure `config/config.ini` du dépôt Git (ajouter au `.gitignore`)
4. **🟠 ÉLEVÉ** : Corriger les chemins absolus `/home/raphael/` dans `scripts/envoyer_sms.py` et `satisfaction_bot.py`
5. **🟠 ÉLEVÉ** : Implémenter la validation des URLs iCal (whitelist airbnb.com, booking.com, etc.)
6. **🟡 MOYEN** : Corriger les noms de colonnes dans `ionos/gestion/src/Services/` (voir `ANALYSE_STRUCTURE_DB.md`)

---

*Document préparé dans le cadre de la consolidation des outils de gestion FrenchyConciergerie*
*Branche : `claude/consolidate-management-tools-Yezzj`*
