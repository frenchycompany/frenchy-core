# CLAUDE.md - FrenchyConciergerie

## Projet

Plateforme de gestion pour conciergerie de locations courte duree (Airbnb, Booking). PHP/MySQL + Python. Deux serveurs : VPS IONOS (gestion) + Raspberry Pi (SMS).

## Points d'entree principaux

- `ionos/gestion/index.php` — Dashboard principal (planning du jour, interventions)
- `ionos/gestion/pages/*.php` — Chaque page = un module fonctionnel
- `ionos/gestion/proprietaire/index.php` — Portail proprietaires (sous-app separee)
- `raspberry-pi/web/index.php` — Interface web SMS
- `raspberry-pi/scripts/envoyer_sms.py` — Daemon Python envoi SMS
- `frenchysite/admin.php` — Generateur de sites marketing

## Conventions de code

- **PHP** : Pas de framework, pages monolithiques avec includes partages
- **Structure pages** : Chaque fichier dans `pages/` est autonome, inclut `db/connection.php` et `includes/` au besoin
- **Base de donnees** : PDO avec prepared statements partout. Pas d'ORM
- **Frontend** : Bootstrap 5.3, JS vanilla, pas de build step
- **Nommage tables** : snake_case francais (ex: `liste_logements`, `planning_menages`, `comptabilite`)

## Base de donnees

### DB IONOS (principale)
- Connexion : `ionos/gestion/db/connection.php`
- Env vars : `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
- Tables cles : `liste_logements`, `planning_menages`, `comptabilite`, `intervenant`, `inventaire_sessions`, `proprietaires`, `gestion_pages`

### DB Raspberry Pi (SMS)
- Connexion : `ionos/gestion/includes/rpi_db.php`
- Env vars : `RPI_DB_HOST`, `RPI_DB_NAME`, `RPI_DB_USER`, `RPI_DB_PASSWORD`
- Tables cles : `reservations`, `sms_messages`, `sms_conversations`, `sms_templates`

### Chargement env
- `ionos/gestion/includes/env_loader.php` — Charge `.env` depuis plusieurs chemins possibles
- Fonction `env($key, $default)` disponible apres include

## Fichiers critiques

- `ionos/gestion/includes/env_loader.php` — Chargement des variables d'environnement
- `ionos/gestion/includes/rpi_db.php` — Connexion DB Raspberry Pi
- `ionos/gestion/includes/security.php` — CSRF, rate limiting, session
- `ionos/gestion/includes/checkup_functions.php` — Fonctions partagees checkup
- `ionos/gestion/db/connection.php` — Connexion DB principale
- `.env.example` — Template de configuration

## Commandes utiles

```bash
# Voir le schema d'une table
mysql -u root -p frenchyconciergerie -e "DESCRIBE nom_table;"

# Tester la connexion DB
php -r "require 'ionos/gestion/db/connection.php'; echo \$conn ? 'OK' : 'FAIL';"

# Sync reservations manuellement
php raspberry-pi/scripts/sync_reservations.php

# Installer les dependances PHP (Raspberry Pi)
cd raspberry-pi && composer install
```

## Points de vigilance

- **Credentials hardcodes** : `ionos/db/connection.php` (DB password fallback). A migrer vers .env
- **Chemins hardcodes** : References a `/home/raphael/` dans certains scripts Python
- **Dual-DB** : Le systeme utilise deux bases de donnees separees (IONOS + RPI)
- **Pas de tests automatises** : Aucun test unitaire ou d'integration
- **Pas de CI/CD** : Aucun pipeline configure
