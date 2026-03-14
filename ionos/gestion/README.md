# FC-gestion - FrenchyConciergerie 🏠

Système de gestion complet pour la conciergerie de locations courte durée FrenchyConciergerie.

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange.svg)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-purple.svg)](https://getbootstrap.com/)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg)]()

## 📋 Table des matières

- [À propos](#à-propos)
- [Fonctionnalités](#fonctionnalités)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Architecture](#architecture)
- [Utilisation](#utilisation)
- [Sécurité](#sécurité)
- [Optimisations](#optimisations)
- [Contribution](#contribution)
- [Support](#support)

## 🎯 À propos

**FC-gestion** est une application web complète développée pour gérer l'ensemble des opérations de FrenchyConciergerie, une entreprise spécialisée dans la gestion de locations courte durée (type Airbnb).

Le système permet de :
- Planifier et gérer les interventions de nettoyage
- Gérer le personnel et les affectations
- Suivre la comptabilité et les rémunérations
- Gérer les inventaires des logements
- Générer des statistiques et rapports

## ✨ Fonctionnalités

### 🗓️ Planning & Interventions
- Planification quotidienne des interventions
- Affectation automatique ou manuelle du personnel
- Suivi de statut (À Faire, En Cours, Fait)
- Gestion des options (Early check-in, Late checkout, Baby bed, Bonus)
- Vue calendrier et liste
- Filtrage par date, logement, intervenant

### 👥 Gestion du Personnel
- Base de données des intervenants
- Rôles multiples : Conducteur, Femme de ménage, Laverie
- Système de permissions par page
- Historique des interventions
- Calcul automatique des rémunérations

### 🏘️ Gestion des Logements
- Catalogue complet des propriétés
- Informations détaillées (adresse, capacité, superficie)
- Codes d'accès
- Tarifs de nettoyage
- Valeurs immobilières

### 💰 Comptabilité
- Double saisie CA (Chiffre d'Affaires) et Charges
- Génération automatique depuis le planning
- Filtrage mensuel/annuel
- Export CSV
- Calcul automatique des marges
- Facturation par intervenant

### 📦 Inventaire
- Sessions d'inventaire par logement
- Scan de QR codes
- Interface mobile optimisée
- Suivi des objets (nom, quantité, état, valeur)
- Photos et remarques
- Comparaison entre inventaires
- Export PDF

### 📊 Statistiques & Rapports
- Taux de remplissage des logements
- Rentabilité par m²
- Activité par jour de semaine
- CA vs Charges
- Nombre d'interventions
- Performances par intervenant

### 🔐 Sécurité
- Authentification par session
- Protection CSRF
- Limitation des tentatives de connexion
- Hashage sécurisé des mots de passe
- Validation des uploads
- SQL Injection prevention (PDO)
- Configuration par environnement (.env)

## 📦 Prérequis

### Serveur
- **PHP** >= 7.4
  - Extensions requises : PDO, PDO_MySQL, GD, mbstring
- **MySQL** >= 5.7 ou **MariaDB** >= 10.2
- **Apache** ou **Nginx**
- **HTTPS** (recommandé pour la production)

### Développement (optionnel)
- **Composer** (pour futures dépendances)
- **Git** (pour le versioning)

## 🚀 Installation

### 1. Cloner ou télécharger le projet

```bash
git clone https://github.com/frenchycompany/FC-gestion.git
cd FC-gestion
```

### 2. Configurer la base de données

```bash
# Créer la base de données
mysql -u root -p -e "CREATE DATABASE dbs13515816 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Importer la structure (si fournie)
mysql -u root -p dbs13515816 < dbs13515816.sql

# Appliquer les optimisations
mysql -u root -p dbs13515816 < database/optimizations.sql
```

### 3. Configurer l'environnement

```bash
# Copier le fichier d'exemple
cp .env.example .env

# Éditer avec vos paramètres
nano .env
```

Configurer au minimum :
```env
APP_ENV=production
APP_DEBUG=false

DB_HOST=votre_host
DB_NAME=votre_base
DB_USER=votre_utilisateur
DB_PASSWORD=votre_mot_de_passe
```

### 4. Configurer les permissions

```bash
# Donner les permissions d'écriture
chmod 755 logs uploads cache generated_contracts
chmod 644 .env
```

### 5. Configuration Apache (exemple)

```apache
<VirtualHost *:80>
    ServerName fc-gestion.example.com
    DocumentRoot /var/www/fc-gestion

    <Directory /var/www/fc-gestion>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/fc-gestion-error.log
    CustomLog ${APACHE_LOG_DIR}/fc-gestion-access.log combined
</VirtualHost>
```

### 6. Première connexion

Accédez à `https://votre-domaine.com/login.php`

Les identifiants par défaut dépendent de votre base de données.

## ⚙️ Configuration

### Variables d'environnement (.env)

Le fichier `.env` contient toutes les configurations sensibles :

```env
# Environnement
APP_ENV=production          # development, staging, production
APP_DEBUG=false            # true en développement uniquement
APP_NAME="FC-gestion"

# Base de données
DB_HOST=localhost
DB_NAME=nom_base
DB_USER=utilisateur
DB_PASSWORD=mot_de_passe
DB_CHARSET=utf8mb4

# Sécurité
SESSION_LIFETIME=3600      # 1 heure
MAX_LOGIN_ATTEMPTS=5       # Tentatives avant blocage
LOGIN_LOCKOUT_TIME=900     # 15 minutes de blocage

# Uploads
MAX_UPLOAD_SIZE=5242880    # 5MB
ALLOWED_EXTENSIONS=jpg,jpeg,png,pdf,doc,docx

# Cache
CACHE_ENABLED=true
CACHE_DRIVER=file
CACHE_LIFETIME=3600        # 1 heure

# Logs
LOG_LEVEL=info            # debug, info, warning, error
LOG_PATH=logs
```

### Configuration PHP (php.ini)

Paramètres recommandés :

```ini
upload_max_filesize = 500M
post_max_size = 500M
max_execution_time = 300
memory_limit = 512M
date.timezone = Europe/Paris
```

## 🏗️ Architecture

### Structure des dossiers

```
FC-gestion/
├── assets/                 # Assets statiques
│   ├── css/               # Feuilles de style
│   │   └── modern.css     # CSS moderne avec variables
│   ├── js/                # JavaScript
│   │   ├── toast.js       # Système de notifications
│   │   └── utils.js       # Fonctions utilitaires
│   └── images/            # Images
├── cache/                 # Cache applicatif
├── config/                # Configuration
│   ├── Config.php         # Gestionnaire de configuration
│   └── Security.php       # Fonctions de sécurité
├── css/                   # Anciens styles (à migrer)
├── database/              # Scripts SQL
│   ├── migrations/        # Migrations de base
│   ├── seeds/             # Données de test
│   └── optimizations.sql  # Indexes et vues
├── db/                    # Connexion DB
│   └── connection.php     # Connexion PDO
├── generated_contracts/   # Contrats générés
├── images/                # Images uploadées
├── logs/                  # Logs applicatifs
├── pages/                 # Pages anciennes (à migrer)
├── src/                   # Nouveau code (architecture MVC)
│   ├── Controllers/       # Contrôleurs
│   │   └── BaseController.php
│   ├── Models/            # Modèles
│   │   └── BaseModel.php
│   ├── Services/          # Services métier
│   │   ├── PlanningService.php
│   │   ├── ComptabiliteService.php
│   │   └── InventaireService.php
│   ├── Middleware/        # Middleware
│   │   └── AuthMiddleware.php
│   ├── Views/             # Vues (templates)
│   ├── Database.php       # Classe Database
│   └── Cache.php          # Système de cache
├── tests/                 # Tests (à venir)
├── uploads/               # Fichiers uploadés
│   └── qrcodes/           # QR codes générés
├── .env                   # Configuration (à ne PAS commit)
├── .env.example           # Exemple de configuration
├── .gitignore             # Fichiers ignorés par Git
├── config.php             # Point d'entrée configuration
├── index.php              # Page d'accueil
├── login.php              # Page de connexion
└── README.md              # Ce fichier
```

### Pattern MVC

L'application suit une architecture MVC moderne :

#### Controllers
Gèrent les requêtes HTTP et orchestrent la logique.

```php
class MonController extends BaseController
{
    public function index()
    {
        $data = ['titre' => 'Ma page'];
        $this->view('mon-template', $data);
    }
}
```

#### Models
Gèrent l'accès aux données.

```php
class MonModel extends BaseModel
{
    protected $table = 'ma_table';
    protected $fillable = ['champ1', 'champ2'];
}
```

#### Services
Contiennent la logique métier complexe.

```php
class MonService
{
    public function traiterDonnees($id)
    {
        // Logique métier...
    }
}
```

### Base de données

#### Classes utilitaires

**Database** : Singleton pour connexion et requêtes
```php
$db = Database::getInstance();
$results = $db->query("SELECT * FROM table WHERE id = ?", [$id]);
```

**Cache** : Système de cache fichier
```php
$cache = Cache::getInstance();
$data = $cache->remember('ma-cle', function() {
    return fetchData();
}, 3600);
```

**Config** : Accès aux variables d'environnement
```php
$host = Config::get('DB_HOST');
$isDebug = Config::isDebug();
```

**Security** : Fonctions de sécurité
```php
$result = Security::validateUpload($_FILES['fichier']);
$hash = Security::hashPassword($password);
```

## 🎨 Interface Utilisateur

### CSS Moderne

Le nouveau système utilise des variables CSS pour une personnalisation facile :

```css
:root {
    --primary-color: #17a2b8;
    --spacing-md: 1rem;
    --border-radius: 0.375rem;
}
```

### Composants JavaScript

#### Toast Notifications

```javascript
// Succès
toast.success('Opération réussie !');

// Erreur
toast.error('Une erreur est survenue');

// Warning
toast.warning('Attention !');

// Info
toast.info('Information');

// Loading
const loading = toast.loading('Chargement...');
// ... opération ...
toast.hideLoading(loading);
```

#### Utilities

```javascript
// Loading overlay
utils.showLoading('Traitement en cours...');
utils.hideLoading();

// Confirmation
const confirmed = await utils.confirm('Êtes-vous sûr ?');
if (confirmed) {
    // Action...
}

// AJAX
const data = await utils.ajax('/api/endpoint');

// Formatage
const price = utils.formatEuro(123.45); // "123,45 €"
const date = utils.formatDate(new Date()); // "12/11/2025"
```

## 🔒 Sécurité

### Bonnes pratiques implémentées

✅ **Variables d'environnement** : Credentials hors du code
✅ **PDO avec prepared statements** : Protection SQL injection
✅ **CSRF tokens** : Protection contre CSRF
✅ **Password hashing** : `password_hash()` PHP
✅ **Rate limiting** : Limitation tentatives de connexion
✅ **Upload validation** : Type MIME et extension
✅ **Session sécurisée** : HTTPOnly, Secure (HTTPS)
✅ **Error handling** : Messages génériques en production
✅ **Logs sécurisés** : Enregistrement des erreurs

### Recommandations

1. **HTTPS obligatoire** en production
2. **Backup réguliers** de la base de données
3. **Mises à jour** PHP et MySQL
4. **Monitoring** des logs
5. **Firewall** configuré correctement

## ⚡ Optimisations

### Base de données

- **Indexes** sur colonnes fréquentes (date, IDs, statuts)
- **Vues SQL** pour requêtes complexes
- **Query optimization** avec EXPLAIN
- **Connection pooling** via PDO persistant

### Application

- **Cache** pour données fréquentes
- **Lazy loading** des images
- **Minification** CSS/JS (à venir)
- **CDN** pour assets statiques (recommandé)
- **Compression gzip** Apache/Nginx

### Frontend

- **CSS variables** pour performance
- **Debouncing** sur recherches
- **Pagination** des listes longues
- **Responsive design** mobile-first

## 📱 Responsive Design

L'application est optimisée pour tous les écrans :

- **Desktop** : 1200px+
- **Tablet** : 768px - 1199px
- **Mobile** : < 768px

## 🧪 Tests

### Lancer les tests (à venir)

```bash
# Tests unitaires
vendor/bin/phpunit tests/

# Tests d'intégration
vendor/bin/phpunit tests/Integration/
```

## 📊 Monitoring

### Logs

Les logs sont enregistrés dans `/logs/` :

- `error.log` : Erreurs PHP
- `app.log` : Logs applicatifs

### Cache

Statistiques du cache :

```php
$stats = Cache::getInstance()->stats();
// ['total' => 42, 'valid' => 38, 'expired' => 4, 'size' => 1024000]
```

Nettoyer le cache :

```bash
# Via PHP
cache_flush();

# Ou manuellement
rm -rf cache/*
```

## 🤝 Contribution

Ce projet est propriétaire et géré par FrenchyConciergerie.

Pour contribuer :
1. Créer une branche feature
2. Commiter les changements
3. Soumettre une pull request
4. Attendre la review

## 📄 License

Propriétaire - © 2024 FrenchyConciergerie. Tous droits réservés.

## 👨‍💻 Support

Pour toute question ou assistance :

- **Email** : support@frenchyconciergerie.fr
- **Documentation** : Ce fichier README

## 📝 Changelog

### Version 2.0.0 (2024-11-12)

**🎉 Modernisation complète**

- ✅ Architecture MVC avec classes réutilisables
- ✅ Système de configuration .env sécurisé
- ✅ Classes Database, Cache, Config, Security
- ✅ Services métier (Planning, Comptabilité, Inventaire)
- ✅ BaseController et BaseModel
- ✅ Middleware d'authentification
- ✅ CSS moderne avec variables
- ✅ Composants JavaScript (Toast, Utils)
- ✅ Optimisations DB (indexes, vues SQL)
- ✅ Documentation complète

### Version 1.0.0 (2024)

- 🎯 Version initiale
- Planning et interventions
- Gestion logements et intervenants
- Comptabilité
- Inventaire
- Statistiques

---

**Développé avec ❤️ pour FrenchyConciergerie**
