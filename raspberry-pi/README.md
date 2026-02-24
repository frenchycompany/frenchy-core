# SMS Management System

Systeme de gestion SMS pour locations de vacances avec synchronisation automatique des reservations depuis Airbnb/Booking et envoi automatise de SMS aux invites.

## Fonctionnalites

- Synchronisation des reservations depuis iCalendar (Airbnb, Booking.com, etc.)
- Envoi automatise de SMS (check-in, check-out, preparation)
- Gestion des conversations SMS bidirectionnelles
- Bot de satisfaction avec transfert vers admin
- Interface web de gestion complete
- Templates SMS personnalisables par logement

## Prerequis

- PHP 7.4+
- MySQL/MariaDB 5.7+
- Python 3.6+
- Modem GSM compatible (sur /dev/ttyUSB0)
- Composer
- Apache/Nginx

## Installation

### 1. Cloner le projet

```bash
git clone <repository-url>
cd smsproject
```

### 2. Configuration PHP

```bash
composer install
cp .env.example .env
# Editer .env avec vos parametres
```

### 3. Configuration Python

```bash
cd scripts
pip install -r requirements.txt
cd ..
cp config/config.ini.example config/config.ini
# Editer config/config.ini avec vos parametres
```

### 4. Base de donnees

```bash
mysql -u root -p < sauvegarde_sms_db.sql
mysql -u root -p < create_users_table.sql
php setup_admin.php
```

### 5. Cron jobs

Ajouter au crontab (`crontab -e`) :

```cron
# Synchronisation des reservations (toutes les heures)
0 * * * * php /chemin/vers/scripts/sync_reservations.php >> /var/log/sms_sync.log 2>&1

# Automatisation SMS (toutes les 30 minutes)
*/30 * * * * php /chemin/vers/scripts/auto_send_sms.php >> /var/log/sms_auto.log 2>&1
```

### 6. Service Python

Lancer le daemon d'envoi SMS :

```bash
cd scripts
python3 satisfaction_bot.py &
# ou
nohup python3 satisfaction_bot.py > /var/log/sms_bot.log 2>&1 &
```

## Identifiants par defaut

Apres l'installation, connectez-vous avec :

- **Email:** admin@sms.local
- **Mot de passe:** Admin123!

**IMPORTANT:** Changez ce mot de passe immediatement apres la premiere connexion.

## Structure du projet

```
smsproject/
├── config/                 # Fichiers de configuration
│   ├── config.ini         # Configuration principale (ne pas commiter)
│   └── config.ini.example # Template de configuration
├── scripts/               # Scripts d'automatisation
│   ├── auto_send_sms.php  # Generateur SMS automatiques
│   ├── sync_reservations.php  # Sync iCalendar
│   ├── envoyer_sms.py     # Envoi via modem
│   └── satisfaction_bot.py # Bot de satisfaction
├── web/                   # Application web
│   ├── pages/            # Pages PHP
│   ├── includes/         # Bibliotheques partagees
│   ├── css/              # Styles
│   └── js/               # Scripts frontend
├── .env.example          # Template variables d'environnement
└── README.md             # Ce fichier
```

## Documentation

- [Automatisation SMS](AUTOMATISATION_SMS.md)
- [Synchronisation Reservations](SYNCHRONISATION_RESERVATIONS.md)
- [Ameliorations](AMELIORATIONS.md)
- [Securite](SECURITY_IMPROVEMENTS.md)
- [Revue du projet](REVUE_PROJET.md)

## Securite

- Ne jamais commiter `config/config.ini` ou `.env`
- Changer les identifiants par defaut
- Utiliser HTTPS en production
- Restreindre l'acces au serveur web

## Licence

Projet prive - Tous droits reserves
