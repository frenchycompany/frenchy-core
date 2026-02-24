# Revue Complète du Projet SMS Management

**Date de revue:** 2026-01-27
**Projet:** Système de gestion SMS pour locations de vacances

---

## 1. Vue d'ensemble du projet

### Description
Ce projet est un **système de gestion SMS pour locations de vacances** permettant :
- Synchronisation automatique des réservations depuis Airbnb/Booking via iCalendar
- Envoi automatisé de SMS aux invités (check-in, check-out, préparation)
- Gestion des conversations SMS bidirectionnelles via modem GSM
- Bot de satisfaction client avec transfert admin
- Interface web de gestion complète

### Stack technique
- **Backend:** PHP 7.4+, Python 3
- **Base de données:** MySQL/MariaDB
- **Frontend:** Bootstrap 4.5, Font Awesome
- **SMS:** Modem GSM via pyserial/gammu
- **iCal:** sabre/vobject

---

## 2. Points positifs

### Architecture
- Séparation claire entre scripts d'automatisation et interface web
- Utilisation de PDO avec requêtes préparées (protection SQL injection)
- Système de templates SMS avec fallback (logement spécifique -> défaut)
- Gestion transactionnelle pour les opérations critiques

### Sécurité (améliorations récentes)
- Protection CSRF implémentée (`csrf.php`)
- Authentification avec sessions et timeout de 30 minutes
- Hashage bcrypt pour les mots de passe
- Régénération de l'ID de session après login
- Variables d'environnement via `.env` (template fourni)

### Code
- Logs détaillés pour le débogage
- Gestion des erreurs avec try/catch
- Normalisation des numéros de téléphone (format E.164)
- Documentation des fonctions avec PHPDoc

---

## 3. Problèmes critiques identifiés

### 3.1 CLE API OPENAI EXPOSEE (CRITIQUE)

**Fichier:** `config/config.ini:15`

```ini
[OPENAI]
api_key = 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
```

**Impact:** Cette clé API est exposée dans le dépôt Git et peut être utilisée frauduleusement.

**Action requise:**
1. Révoquer immédiatement cette clé sur https://platform.openai.com
2. Ajouter `config/config.ini` au `.gitignore`
3. Créer un `config/config.ini.example` sans les vraies valeurs
4. Utiliser des variables d'environnement au lieu du fichier INI

### 3.2 Fichier config.ini non exclu du Git

**Fichier:** `.gitignore`

Le fichier `config/config.ini` contient des informations sensibles mais n'est pas exclu du dépôt Git.

**Action requise:** Ajouter au `.gitignore` :
```
config/config.ini
```

### 3.3 Mot de passe par défaut affiché sur la page de login

**Fichier:** `web/pages/login.php:121-124`

```php
<p><strong>Email:</strong> admin@sms.local<br>
<strong>Mot de passe:</strong> Admin123!</p>
```

**Impact:** Informations sensibles affichées publiquement.

**Action requise:** Supprimer ces informations de la page de login et les documenter ailleurs.

### 3.4 Chemins absolus codés en dur

**Fichiers affectés:**
- `scripts/envoyer_sms.py:9,16` - `/home/raphael/sms_project/...`
- `scripts/satisfaction_bot.py:23,30` - `/home/raphael/sms_project/...`
- `scripts/auto_send_sms.php:12` - utilise `__DIR__` (OK)

**Impact:** Les scripts Python ne fonctionneront pas sur d'autres installations.

**Action requise:** Utiliser des chemins relatifs ou des variables d'environnement.

---

## 4. Problèmes de sécurité modérés

### 4.1 SSRF potentiel via iCalendar

**Fichiers:**
- `scripts/sync_reservations.php:86`
- `web/pages/ical_parser.php:28`
- `web/pages/update_reservations.php:41`

```php
$content = @file_get_contents($lg['ics_url']);
```

**Impact:** Si un attaquant peut contrôler l'URL ICS, il pourrait accéder à des ressources internes.

**Recommandation:** Valider que l'URL pointe vers un domaine autorisé (airbnb.com, booking.com, etc.)

### 4.2 Exécution de commande shell

**Fichier:** `web/pages/automation_config.php:60`

```php
exec('php ' . __DIR__ . '/../../scripts/auto_send_sms.php 2>&1', $output, $return_var);
```

**Impact:** Faible car le chemin est fixe, mais c'est une mauvaise pratique.

**Recommandation:** Utiliser un système de file d'attente (queue) plutôt que d'exécuter des scripts directement.

### 4.3 Numéro admin exposé

**Fichier:** `config/config.ini:23`

```ini
numero_admin = +33647554678
```

**Impact:** Numéro de téléphone personnel exposé dans le dépôt.

---

## 5. Problèmes de qualité du code

### 5.1 Duplication de code

- Les fonctions `logMessage()` sont dupliquées dans plusieurs scripts
- La connexion à la base de données est établie de plusieurs façons différentes

**Recommandation:** Créer une bibliothèque partagée pour les fonctions utilitaires.

### 5.2 Tables SMS redondantes

La base de données contient plusieurs tables pour les SMS :
- `sms_in`, `inbox`
- `sms_out`, `sms_outbox`, `outbox`
- `sms_messages`, `sentitems`

**Recommandation:** Consolider les tables et utiliser des vues si nécessaire.

### 5.3 Absence de tests automatisés

Aucun test unitaire ou d'intégration n'est présent.

**Recommandation:** Ajouter PHPUnit pour PHP et pytest pour Python.

### 5.4 Headers volumineux

- `header.php` : 6826 lignes
- `header_new.php` : 8579 lignes

**Recommandation:** Diviser en composants plus petits.

---

## 6. Problèmes de maintenance

### 6.1 Dépendances vendor non présentes

Le dossier `vendor/` est dans `.gitignore` mais aucun fichier `composer.lock` n'indique les versions exactes des dépendances (le fichier existe mais il faut s'assurer qu'il est à jour).

**Action requise:** Exécuter `composer install` après le clonage.

### 6.2 Scripts Python avec dépendances non documentées

Les scripts Python utilisent :
- pymysql
- pyserial
- gammu (python-gammu)
- openai

**Recommandation:** Créer un fichier `requirements.txt` :
```
pymysql>=1.0.0
pyserial>=3.5
python-gammu>=3.2
openai>=1.0.0
```

---

## 7. Recommandations d'amélioration

### Priorité haute (sécurité)

1. **Révoquer la clé API OpenAI** exposée et en générer une nouvelle
2. **Ajouter config/config.ini au .gitignore**
3. **Supprimer le mot de passe par défaut** de la page de login
4. **Corriger les chemins absolus** dans les scripts Python
5. **Implémenter une validation d'URL** pour les imports iCalendar

### Priorité moyenne (qualité)

1. Créer un fichier `requirements.txt` pour Python
2. Factoriser les fonctions de logging et connexion DB
3. Ajouter des tests automatisés
4. Documenter l'API des endpoints AJAX

### Priorité basse (optimisation)

1. Consolider les tables SMS redondantes
2. Implémenter un système de cache pour les templates
3. Diviser les fichiers header en composants
4. Ajouter un système de monitoring des modems

---

## 8. Structure de la base de données

### Tables principales (32 tables)

| Catégorie | Tables |
|-----------|--------|
| Réservations | `reservation`, `liste_logements`, `client` |
| SMS | `sms_in`, `sms_out`, `sms_outbox`, `inbox`, `outbox`, `sentitems` |
| Templates | `sms_templates`, `sms_logement_templates` |
| Automatisation | `sms_automations`, `scenario`, `client_scenario` |
| iCalendar | `ical_reservations`, `ical_sync_log`, `travel_account_connections` |
| Conversations | `conversations`, `conversation_messages`, `satisfaction_conversations` |
| Configuration | `configuration`, `ai_prompts`, `users` |

---

## 9. Workflow principal

```
┌─────────────────────────────────────────────────────────────┐
│                    FLUX DE DONNEES                         │
└─────────────────────────────────────────────────────────────┘

Airbnb/Booking (iCal)
        │
        ▼
sync_reservations.php (cron horaire)
        │
        ▼
┌───────────────────┐
│   reservation     │ ← Table centrale
└───────────────────┘
        │
        ▼
auto_send_sms.php (cron 30 min)
        │
        ▼
┌───────────────────┐
│   sms_outbox      │ (status='pending')
└───────────────────┘
        │
        ▼
envoyer_sms.py / satisfaction_bot.py (service continu)
        │
        ▼
    Modem GSM
        │
        ▼
   Invité (SMS)
```

---

## 10. Conclusion

Le projet est **fonctionnel et bien structuré** pour sa finalité. Les améliorations de sécurité récentes (CSRF, authentification, PDO) sont bienvenues.

**Actions immédiates requises :**
1. Révoquer la clé API OpenAI exposée
2. Sécuriser le fichier de configuration
3. Corriger les chemins absolus

Le code est maintenable et suit les bonnes pratiques de base, mais gagnerait à être refactorisé pour réduire la duplication et améliorer la testabilité.

---

*Revue effectuée par Claude - Session du 2026-01-27*
