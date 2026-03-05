# Revue de Projet — FrenchyConciergerie

**Date :** 5 mars 2026
**Scope :** Audit complet (securite, qualite, architecture, infrastructure)

---

## Vue d'ensemble

| Metrique | Valeur |
|----------|--------|
| Fichiers PHP | 799 |
| Fichiers Python | 14 |
| Fichiers JS | 19 |
| Pages dans `gestion/pages/` | 148 |
| Connexions PDO distinctes | 31 |
| Formulaires HTML | ~46 |
| Formulaires avec CSRF | ~15 |

---

## 1. SECURITE — PROBLEMES CRITIQUES

### 1.1 Credentials hardcodes dans le code source

**Severite : CRITIQUE** — Ces secrets sont dans le depot Git et doivent etre revoques immediatement.

| Fichier | Ligne | Type | Valeur exposee |
|---------|-------|------|----------------|
| `ionos/db/connection.php` | 34 | Mot de passe DB | `**Baycpq25**` |
| `ionos/admin/index.php` | 17-18 | Admin user/pass | `admin` / `frenchyconciergerie2026` |
| `ionos/cdansmaville/evenements/generate_seo.php` | 7 | Cle OpenAI | `sk-proj-fKEGQ...` |
| `ionos/cdansmaville/evenements/process_image.php` | 9 | Cle Google Vision | `AIzaSyBBJ9...` |
| `ionos/cdansmaville/evenements/upload.php` | 13 | Cle Google Vision | `AIzaSyAfK...` |
| `ionos/frenchycompany/db/connection.php` | 6 | Mot de passe DB | `**Baycpq25**` |
| `ionos/gestion/OK V2/db/connection.php` | 6 | Mot de passe DB | `**Baycpq25**` |
| `ionos/cdansmaville/evenements/db/connection.php` | 6 | Mot de passe DB | `**Baycpq25**` |
| `frenchysite/admin.php` | 16 | Admin password fallback | `admin2025` |
| `raspberry-pi/config_superhote.ini` | 11 | Mot de passe Superhote | `**Baycpq25**` |
| `raspberry-pi/setup_admin.php` | 37 | Mot de passe admin | `Admin123!` |

**Actions requises :**
1. Revoquer IMMEDIATEMENT la cle OpenAI (elle est compromise)
2. Revoquer les cles Google Vision
3. Changer le mot de passe DB `**Baycpq25**` (reutilise partout!)
4. Migrer tous les credentials vers `.env` / variables d'environnement
5. Ajouter les fichiers `.env` et `*.ini` (avec secrets) au `.gitignore`

### 1.2 Protection CSRF insuffisante

Sur ~46 formulaires, seulement ~15 fichiers utilisent un token CSRF. Les pages suivantes sont vulnerables (liste non exhaustive) :
- `pages/users.php` (4 formulaires)
- `pages/agents_dashboard.php` (3 formulaires)
- `pages/campaigns_edit.php` (3 formulaires)
- `pages/sms_templates.php` (2 formulaires)
- `pages/login.php` (pas de CSRF sur le formulaire de login)
- Et bien d'autres...

**Action :** Ajouter un token CSRF a TOUS les formulaires POST. Le mecanisme existe deja dans `index.php` (`$_SESSION['csrf_token']`).

### 1.3 Debug active en production

Les fichiers suivants activent `display_errors` en dur, ce qui expose des details techniques :

- `ionos/gestion/index.php:4-5`
- `ionos/submit.php:3,5`
- `raspberry-pi/web/pages/send_departure_sms.php:2-3`
- `raspberry-pi/web/pages/import_ics.php:5-6`
- `raspberry-pi/web/pages/update_reservations.php:3-4`
- `raspberry-pi/web/pages/scenario.php:3-4`
- `raspberry-pi/web/pages/send_sms.php:3`
- Et d'autres...

**Action :** Remplacer par une gestion conditionnelle basee sur `APP_DEBUG` dans `.env`.

### 1.4 Fichiers d'installation accessibles

Ces fichiers permettent une configuration non-authentifiee de la BDD :

- `ionos/db/install.php`
- `ionos/install site/install.php`
- `frenchysite/install.php`

**Action :** Supprimer ces fichiers ou les proteger par authentification.

### 1.5 Cookie de session non securise

`ionos/gestion/index.php:12` : `'secure' => false` — les cookies de session sont envoyes en clair sur HTTP.

**Action :** Passer a `'secure' => true` si le site utilise HTTPS (ce qui devrait etre le cas).

---

## 2. ARCHITECTURE ET QUALITE DU CODE

### 2.1 Duplication massive des connexions DB

**31 instances de `new PDO`** dispersees dans le projet. Chaque sous-repertoire a sa propre copie de `connection.php` avec des implementations differentes :

- `ionos/db/connection.php` — credentials hardcodes
- `ionos/gestion/db/connection.php` — utilise env_loader (bien)
- `ionos/frenchycompany/db/connection.php` — credentials hardcodes
- `ionos/gestion/OK V2/db/connection.php` — credentials hardcodes
- `ionos/cdansmaville/evenements/db/connection.php` — credentials hardcodes
- `ionos/cdansmaville/db/connection.php` — constantes
- `ionos/cdansmaville/admin/db/connection.php` — constantes
- `frenchysite/db/connection.php` — env loader
- `ionos/vertefeuille/db/connection.php` — env loader
- `raspberry-pi/web/includes/db.php` — 3 connexions PDO dans un seul fichier

**Action :** Centraliser sur un seul fichier de connexion (`ionos/gestion/db/connection.php` est le modele a suivre).

### 2.2 Dossiers obsoletes / legacy

| Dossier | Contenu |
|---------|---------|
| `ionos/gestion/OK V2/` | Ancienne version du code avec credentials hardcodes |
| `ionos/install site/` | Scripts d'installation (doublon de `frenchysite/`) |
| `raspberry-pi/web/pages/_archive/` | 17+ fichiers archives |
| `raspberry-pi/web/pages/HS/` | 4 fichiers hors-service |

**Action :** Supprimer ces dossiers ou les deplacer hors du depot.

### 2.3 Fichiers inutiles dans le depot

- `ionos/index.phpko` — fichier backup
- `raspberry-pi/config/config.inisav` — backup de config
- `raspberry-pi/scripts/__pycache__/` — cache Python
- Plusieurs fichiers `debug_*.php`, `hash.php`, `check_table_structure.php` — outils de debug

**Action :** Ajouter au `.gitignore` et supprimer du depot.

### 2.4 Pages monolithiques

Chaque fichier dans `pages/` (148 fichiers) est autonome et melange :
- Logique metier (requetes SQL)
- Traitement des formulaires
- Rendu HTML/CSS/JS

Certaines pages font 1000+ lignes. Il n'y a pas de separation Model/View/Controller.

**Action a terme :** Extraire la logique metier dans des fonctions reutilisables. Le fichier `ionos/gestion/src/Database.php` montre un debut de refactoring mais n'est utilise nulle part.

### 2.5 Mot de passe reutilise

Le mot de passe `**Baycpq25**` est utilise pour :
- La base de donnees MySQL (IONOS)
- Le compte Superhote
- Les anciens fichiers de connexion

C'est un risque majeur si un seul service est compromis.

---

## 3. SCRIPTS PYTHON (Raspberry Pi)

### 3.1 Chemins absolus hardcodes

References a `/home/raphael/sms_project/` dans :
- `raspberry-pi/check_reservation_list.sh:7`
- `raspberry-pi/raspberry-info.txt` (crontabs)

**Action :** Utiliser des chemins relatifs ou des variables d'environnement.

### 3.2 Qualite des scripts Python

**Points positifs :**
- `envoyer_sms.py` : Resolution dynamique des chemins, gestion des signaux, logging
- `superhote_base.py` : Retry decorator avec backoff, gestion memoire Raspberry Pi
- `superhote_daemon.py` : Implementation daemon propre avec signaux

**Points a ameliorer :**
- `satisfaction_bot.py` : Blocs `except` trop larges (bare `except`), pas de validation des numeros
- `config_modem.py` : Boucle infinie sans arret gracieux
- Pas de pinning des dependances (`>=1.0.0` au lieu de `==1.0.0`)

### 3.3 Dependances non pinees

```
# raspberry-pi/scripts/requirements.txt
pymysql>=1.0.0      # Trop large
pyserial>=3.5
python-gammu>=3.2
openai>=1.0.0        # Inclus mais utilise en mode fallback uniquement
```

**Action :** Piner les versions exactes pour la reproductibilite.

---

## 4. FRONTEND

### 4.1 Points positifs
- Bootstrap 5.3 utilise correctement
- `htmlspecialchars()` utilise dans la plupart des outputs
- Design responsive avec media queries
- Pas de dependances JS lourdes (vanilla JS)

### 4.2 Points a ameliorer
- CSS inline dans les fichiers PHP (ex: `index.php` 80+ lignes de `<style>`)
- Pas de minification des assets
- Pas de cache-busting (versioning des CSS/JS)
- Adresse IP hardcodee `109.219.194.30` dans `agent_dashboard.php`

---

## 5. BASE DE DONNEES

### 5.1 Points positifs
- PDO avec prepared statements dans les fichiers principaux
- `ATTR_EMULATE_PREPARES => false` dans la connexion principale (securise)
- Index definis dans le schema d'installation

### 5.2 Points a ameliorer
- Pas de migrations versionees (schema.sql monolithique)
- Dual-DB (IONOS + Raspberry Pi) complexifie la maintenance
- Tables avec le prefixe `FC_` dans `frenchysite/` vs snake_case dans `gestion/`
- `CREATE TABLE IF NOT EXISTS` dans le code applicatif (ex: `admin/index.php:48`)

---

## 6. PRIORITES D'ACTION

### Immediat (securite critique)
1. **Revoquer** la cle OpenAI `sk-proj-fKEGQ...`
2. **Revoquer** les 2 cles Google Vision
3. **Changer** le mot de passe DB `**Baycpq25**` sur tous les services
4. **Supprimer** les credentials hardcodes de tous les fichiers PHP
5. **Proteger/supprimer** les fichiers `install.php`

### Court terme (1-2 semaines)
6. Migrer TOUS les credentials vers `.env`
7. Ajouter CSRF sur tous les formulaires
8. Desactiver `display_errors` en production
9. Passer les cookies de session en `secure: true`
10. Supprimer `OK V2/`, `install site/`, `_archive/`, fichiers debug

### Moyen terme (1-2 mois)
11. Centraliser les connexions DB (un seul `connection.php`)
12. Piner les dependances Python
13. Remplacer les chemins `/home/raphael/` par des variables
14. Extraire le CSS inline dans des fichiers separes
15. Mettre en place un `.gitignore` complet

### Long terme
16. Ajouter des tests automatises
17. Mettre en place un CI/CD
18. Refactoriser les pages monolithiques (separation MVC)
19. Consolider les 2 bases de donnees (cf. MIGRATION_PLAN.md)
20. Mettre en place des migrations versionees
