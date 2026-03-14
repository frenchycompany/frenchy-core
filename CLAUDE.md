# CLAUDE.md - FrenchyConciergerie

## Projet

Plateforme de gestion pour conciergerie de locations courte duree (Airbnb, Booking). PHP/MySQL + Python. Base de donnees unifiee sur VPS IONOS. Raspberry Pi conserve uniquement le modem GSM (daemon SMS) et le scraping Chromium.

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

### DB Unifiee (VPS IONOS)
- Connexion : `ionos/gestion/db/connection.php`
- Env vars : `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
- Tables gestion : `liste_logements`, `planning`, `comptabilite`, `intervenant`, `inventaire_sessions`
- Tables SMS : `sms_in`, `sms_out`, `sms_outbox`, `sms_templates`, `sms_conversations`, `sms_messages`
- Tables reservations : `reservation`, `ical_reservations`, `ical_sync_log`, `travel_account_connections`
- Tables clients : `client`, `contacts`, `conversations`, `conversation_messages`

### getRpiPdo() — Alias historique
- Fichier : `ionos/gestion/includes/rpi_db.php`
- Retourne `$conn` (meme connexion VPS). Les variables `RPI_DB_*` ne sont plus necessaires.
- Conserve pour compatibilite avec les 63 pages qui l'appellent.

### Raspberry Pi (daemon SMS)
- Le RPi n'a plus de base locale. Il se connecte au VPS via `config/config.ini`.
- Scripts : `envoyer_sms.py` (lit `sms_outbox` du VPS), `satisfaction_bot.py`, scraping Chromium

### Chargement env
- `ionos/gestion/includes/env_loader.php` — Charge `.env` depuis plusieurs chemins possibles
- Fonction `env($key, $default)` disponible apres include

## Fichiers critiques

- `ionos/gestion/includes/env_loader.php` — Chargement des variables d'environnement
- `ionos/gestion/includes/rpi_db.php` — Alias getRpiPdo() → $conn (VPS local)
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
- **Migration DB** : Base unifiee sur VPS. Voir `migration/GUIDE_MIGRATION_RPi_VERS_VPS.md` pour les etapes restantes
- **Pas de tests automatises** : Aucun test unitaire ou d'integration
- **Pas de CI/CD** : Aucun pipeline configure
