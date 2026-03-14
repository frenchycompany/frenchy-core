# FrenchyConciergerie

Plateforme de gestion pour conciergerie de locations courte duree (Airbnb, Booking.com).

[![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange.svg)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-purple.svg)](https://getbootstrap.com/)
[![Python](https://img.shields.io/badge/Python-3.11-green.svg)](https://python.org)

## Architecture

Deux serveurs, roles distincts :

| Serveur | Contenu | Role |
|---------|---------|------|
| **VPS IONOS** | `ionos/gestion/`, `ionos/vertefeuille/`, `frenchysite/` | Dashboard de gestion, sites vitrine, portail proprietaires, API |
| **Raspberry Pi** | `raspberry-pi/` | Crons, scripts Python, Selenium (Superhote), modem GSM, gestion SMS |

**Stack :** PHP 7.4+ (PDO/MySQL) / Python 3.11 / Bootstrap 5.3 / JS vanilla / MySQL (dual-DB)
**Integrations :** OpenAI (generation SMS), iCalendar (Airbnb/Booking), Superhote (tarification Selenium)

## Structure du projet

```
frenchy-core/
├── ionos/                              # VPS IONOS
│   ├── index.php                       # Site public conciergerie
│   ├── assets/                         # CSS/JS du site public
│   ├── includes/                       # Header, footer, security, functions
│   ├── db/                             # Connexion BDD site public
│   ├── docs/                           # Documents legaux (PDF)
│   ├── gestion/                        # Dashboard de gestion principal
│   │   ├── index.php                   # Accueil / planning du jour
│   │   ├── pages/                      # ~120 modules fonctionnels
│   │   ├── proprietaire/               # Portail proprietaires (sous-app)
│   │   ├── api/                        # Endpoints API (cron, bookmarklet, SMS)
│   │   ├── includes/                   # env_loader, security, rpi_db, checkup
│   │   ├── db/                         # Connexion BDD + migrations
│   │   ├── src/                        # Controllers, Models, Services, Middleware
│   │   ├── config/                     # Configuration applicative
│   │   ├── sql/                        # Scripts SQL
│   │   └── assets/, css/, images/      # Ressources frontend
│   └── vertefeuille/                   # Site vitrine deploye (propriete)
├── raspberry-pi/                       # Raspberry Pi
│   ├── scripts/                        # Daemons et automatisation
│   │   ├── envoyer_sms.py              # Daemon envoi SMS (modem GSM)
│   │   ├── satisfaction_bot.py         # Bot satisfaction client
│   │   ├── sync_reservations.php       # Sync iCal (cron horaire)
│   │   ├── auto_send_sms.php           # Envoi SMS automatique
│   │   ├── setup_crons.sh             # Installation des crontabs
│   │   └── selenium/                   # Automation Superhote (tarifs)
│   ├── web/                            # Interface web SMS
│   │   ├── pages/                      # Pages de gestion SMS/reservations
│   │   ├── api/                        # API (cron sync, auto SMS, bookmarklet)
│   │   ├── includes/                   # Helpers, header, footer
│   │   └── css/, js/                   # Ressources frontend
│   ├── config/                         # Configuration (config.ini)
│   └── vendor/                         # Dependances Composer (sabre/vobject)
├── frenchysite/                        # Generateur de sites marketing
│   ├── admin.php                       # Interface d'administration
│   ├── index.php                       # Template site vitrine
│   ├── sections/                       # Sections du template (hero, galerie...)
│   ├── config/                         # Configuration par propriete
│   ├── db/                             # Connexion BDD
│   └── assets/                         # CSS, JS, images, photos
├── scripts/                            # Scripts utilitaires
│   ├── merge_databases.sql             # Migration/fusion BDD
│   ├── cleanup_and_optimize.sql        # Optimisation BDD
│   └── config.php                      # Configuration partagee
├── .env.example                        # Template variables d'environnement
├── .gitignore
└── CLAUDE.md                           # Instructions pour Claude Code
```

## Modules principaux (ionos/gestion/pages/)

| Module | Description |
|--------|-------------|
| **Planning** | Planning quotidien des menages, check-in/check-out, affectation personnel |
| **Logements** | Catalogue proprietes, adresses, capacite, codes d'acces, equipements, photos |
| **Reservations** | Import iCal (Airbnb/Booking), sync horaire, calendrier multi-logements |
| **Comptabilite** | CA/charges, facturation intervenants, export CSV, marges |
| **Intervenants** | Gestion du personnel, roles, affectations |
| **Checkup** | Inspection par equipement/piece, video, QR code, rapport PDF |
| **Inventaire** | Sessions d'inventaire, scan QR, comparaison, validation |
| **SMS** | Templates, conversations, campagnes, generation IA (OpenAI) |
| **Portail proprietaires** | Calendrier, taches, checkups, inventaire, stats occupation |
| **Superhote** | Sync tarifs via Selenium, analyse concurrence, simulations |
| **Contrats** | Generation de contrats de gestion et location |
| **Admin** | Gestion pages, utilisateurs, roles, configuration |

## Installation

### 1. Cloner et configurer

```bash
git clone https://github.com/frenchycompany/frenchy-core.git
cd frenchy-core
cp .env.example .env
# Editer .env avec vos parametres
```

### 2. Variables d'environnement (.env)

| Variable | Description |
|----------|-------------|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | BDD principale (IONOS) |
| `RPI_DB_HOST`, `RPI_DB_NAME`, `RPI_DB_USER`, `RPI_DB_PASSWORD` | BDD Raspberry Pi (SMS) |
| `OPENAI_API_KEY` | Generation de messages IA |
| `APP_DEBUG` | Mode debug (false en prod) |
| `SESSION_TIMEOUT` | Duree de session (secondes) |

### 3. Base de donnees

```bash
mysql -u root -p -e "CREATE DATABASE frenchyconciergerie CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p frenchyconciergerie < scripts/merge_databases.sql
mysql -u root -p frenchyconciergerie < scripts/cleanup_and_optimize.sql
```

### 4. Raspberry Pi

```bash
cd raspberry-pi && composer install
cd scripts && pip install -r requirements.txt
bash setup_crons.sh
```

### 5. Serveur web

Pointer le domaine vers `ionos/gestion/`.

```ini
# php.ini recommande
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
memory_limit = 256M
date.timezone = Europe/Paris
```

## Securite

- PDO prepared statements sur toutes les requetes
- Tokens CSRF sur les formulaires
- password_hash() / password_verify()
- Rate limiting sur les connexions
- Sessions securisees (HTTPOnly, SameSite=Strict)
- Variables d'environnement pour les secrets (.env)
- Validation des uploads (MIME, extension, taille)
- .htaccess de protection sur les repertoires sensibles

## Documentation

| Document | Description |
|----------|-------------|
| [ionos/gestion/README.md](ionos/gestion/README.md) | Dashboard de gestion (detail) |
| [raspberry-pi/README.md](raspberry-pi/README.md) | Systeme SMS et scripts |
| [raspberry-pi/scripts/selenium/README.md](raspberry-pi/scripts/selenium/README.md) | Automation Superhote |
| [CLAUDE.md](CLAUDE.md) | Instructions pour Claude Code |

---

Proprietary - FrenchyConciergerie. Tous droits reserves.
