# 🔄 Synchronisation automatique des réservations

## 📋 Vue d'ensemble

Le système de synchronisation télécharge automatiquement les réservations depuis les flux iCalendar (ICS) configurés pour chaque logement et met à jour la base de données.

**Fonctionnalités :**
- Import depuis flux ICS (Airbnb, Booking.com, etc.)
- Détection automatique des nouvelles réservations
- Mise à jour des réservations existantes
- Extraction du téléphone et de la ville depuis la description
- Journalisation détaillée de chaque synchronisation

---

## 🔧 Architecture

Le système comprend deux interfaces :

1. **Interface web** : `web/pages/update_reservations.php`
   - Synchronisation manuelle via le navigateur
   - Affichage des résultats en temps réel
   - Accessible depuis le menu "Mise à jour réservations"

2. **Script CLI** : `scripts/sync_reservations.php`
   - Exécutable en ligne de commande
   - Conçu pour l'automatisation via cron
   - Journalisation dans `logs/sync_reservations.log`

---

## 📦 Installation des dépendances

Le système utilise la bibliothèque `sabre/vobject` pour parser les fichiers ICS.

### **1. Installer Composer** (si nécessaire)

```bash
cd /home/raphael/sms_project
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
```

### **2. Installer les dépendances**

```bash
cd /home/raphael/sms_project
composer install --no-dev --optimize-autoloader
```

Cela créera le dossier `vendor/` avec toutes les dépendances nécessaires.

---

## ⚙️ Configuration du cron

### **Méthode 1 : Crontab utilisateur**

Ouvrir l'éditeur de crontab :

```bash
crontab -e
```

Ajouter la ligne suivante pour synchroniser toutes les heures :

```cron
# Synchronisation des réservations toutes les heures
0 * * * * /usr/bin/php /home/raphael/sms_project/scripts/sync_reservations.php >> /home/raphael/sms_project/logs/cron_sync.log 2>&1
```

**Autres exemples :**

```cron
# Toutes les 6 heures
0 */6 * * * /usr/bin/php /home/raphael/sms_project/scripts/sync_reservations.php

# Tous les jours à 3h du matin
0 3 * * * /usr/bin/php /home/raphael/sms_project/scripts/sync_reservations.php

# Toutes les 30 minutes
*/30 * * * * /usr/bin/php /home/raphael/sms_project/scripts/sync_reservations.php
```

### **Méthode 2 : Service systemd (recommandé)**

Créer un timer systemd pour plus de contrôle et de fiabilité.

**1. Créer le service** `/etc/systemd/system/sync-reservations.service` :

```ini
[Unit]
Description=Synchronisation des réservations depuis ICS
After=network.target mariadb.service

[Service]
Type=oneshot
User=raphael
WorkingDirectory=/home/raphael/sms_project
ExecStart=/usr/bin/php /home/raphael/sms_project/scripts/sync_reservations.php
StandardOutput=journal
StandardError=journal
```

**2. Créer le timer** `/etc/systemd/system/sync-reservations.timer` :

```ini
[Unit]
Description=Timer pour synchronisation réservations
Requires=sync-reservations.service

[Timer]
# Exécuter toutes les heures
OnCalendar=hourly
# Démarrer 5 minutes après le boot
OnBootSec=5min
# Précision de 5 minutes
AccuracySec=5min
Persistent=true

[Install]
WantedBy=timers.target
```

**3. Activer et démarrer le timer** :

```bash
sudo systemctl daemon-reload
sudo systemctl enable sync-reservations.timer
sudo systemctl start sync-reservations.timer
```

**4. Vérifier le statut** :

```bash
# Statut du timer
sudo systemctl status sync-reservations.timer

# Liste des prochaines exécutions
systemctl list-timers

# Logs du service
sudo journalctl -u sync-reservations.service -f
```

---

## 🧪 Test manuel

Tester le script avant de configurer le cron :

```bash
cd /home/raphael/sms_project
php scripts/sync_reservations.php
```

**Sortie attendue :**

```
[2025-01-19 15:30:00] === Début de la synchronisation des réservations ===
[2025-01-19 15:30:00] Logements trouvés : 3
[2025-01-19 15:30:00] → Logement #1 «Appartement A»
[2025-01-19 15:30:01]   Événements trouvés : 12
[2025-01-19 15:30:01]     ✓ Inséré ref#12345 (ID=789)
[2025-01-19 15:30:01]     ↺ Mise à jour ref#12346 (ID=790)
[2025-01-19 15:30:02] → Logement #2 «Studio B»
[2025-01-19 15:30:02]   Événements trouvés : 8
[2025-01-19 15:30:03] === Résumé ===
[2025-01-19 15:30:03] ✓ Nouvelles insertions : 3
[2025-01-19 15:30:03] ↺ Mises à jour : 17
[2025-01-19 15:30:03] === Synchronisation terminée ===
```

---

## 📝 Configuration des URL ICS

### **1. Obtenir l'URL ICS depuis Airbnb/Booking**

**Airbnb :**
1. Accéder à votre calendrier de logement
2. Menu "Disponibilité" → "Synchronisation de calendriers"
3. Copier l'URL "Exporter le calendrier"

**Booking.com :**
1. Extranet → Calendrier
2. "Synchronisation de calendrier" → "Exporter"
3. Copier l'URL fournie

### **2. Configurer dans la base de données**

```sql
UPDATE liste_logements
SET ics_url = 'https://www.airbnb.fr/calendar/ical/12345678.ics?s=abcdef123456'
WHERE id = 1;
```

Ou via l'interface web (si disponible) dans la page de gestion des logements.

---

## 📊 Format des données ICS

Le script s'attend à un format spécifique dans le SUMMARY des événements :

```
Format: Prénom - Plateforme - Référence
Exemple: Jean - Airbnb - 987654321
```

**Données extraites :**
- `SUMMARY` : Prénom, plateforme, référence
- `DTSTART` : Date d'arrivée
- `DTEND` : Date de départ
- `DESCRIPTION` : Téléphone et ville (extraits par regex)

**Exemple de description :**

```
Phone: +33612345678
City: Paris
Email: jean@example.com
```

---

## 🔍 Surveillance et logs

### **Fichiers de logs**

- **Script principal** : `logs/sync_reservations.log`
- **Cron output** : `logs/cron_sync.log` (si configuré)
- **Systemd journal** : `journalctl -u sync-reservations.service`

### **Vérifier les logs**

```bash
# Dernières lignes du log
tail -f /home/raphael/sms_project/logs/sync_reservations.log

# Rechercher les erreurs
grep "⚠️\|❌" /home/raphael/sms_project/logs/sync_reservations.log

# Compter les synchronisations réussies
grep "=== Synchronisation terminée ===" /home/raphael/sms_project/logs/sync_reservations.log | wc -l
```

### **Rotation des logs**

Créer `/etc/logrotate.d/sms-project` :

```
/home/raphael/sms_project/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    missingok
    create 0644 raphael raphael
}
```

---

## 🚨 Dépannage

### **Erreur : "vendor/autoload.php not found"**

```bash
cd /home/raphael/sms_project
composer install --no-dev
```

### **Erreur : "PDO non disponible"**

Vérifier que MySQL/MariaDB est démarré :

```bash
sudo systemctl status mariadb
sudo systemctl start mariadb
```

Vérifier les credentials dans `.env` :

```bash
cat /home/raphael/sms_project/.env
```

### **Erreur : "URL ICS inaccessible"**

- Vérifier que l'URL ICS est valide
- Tester l'URL dans un navigateur
- Vérifier que le serveur a accès à Internet
- Vérifier les paramètres du pare-feu

```bash
# Tester l'URL ICS
curl -I "https://www.airbnb.fr/calendar/ical/12345678.ics?s=..."
```

### **Erreur : "ICS invalide"**

- Vérifier le format du fichier ICS téléchargé
- Certaines plateformes peuvent bloquer les requêtes automatiques
- Essayer de télécharger manuellement pour vérifier le contenu

### **Aucune nouvelle réservation**

- Vérifier le format du SUMMARY (doit être "Prénom - Plateforme - Référence")
- Les événements "Blocked dates" sont automatiquement ignorés
- Vérifier les logs pour voir les événements ignorés

---

## 📈 Monitoring et alertes

### **Script de vérification**

Créer `scripts/check_sync_status.sh` :

```bash
#!/bin/bash
LOG_FILE="/home/raphael/sms_project/logs/sync_reservations.log"
LAST_SYNC=$(grep "=== Synchronisation terminée ===" "$LOG_FILE" | tail -1 | cut -d']' -f1 | cut -d'[' -f2)
LAST_SYNC_TIMESTAMP=$(date -d "$LAST_SYNC" +%s 2>/dev/null || echo 0)
NOW=$(date +%s)
DIFF=$((NOW - LAST_SYNC_TIMESTAMP))

# Alerte si dernière sync > 2 heures
if [ $DIFF -gt 7200 ]; then
    echo "⚠️  ALERTE: Dernière synchronisation il y a $((DIFF / 3600)) heures"
    exit 1
else
    echo "✓ Dernière synchronisation: $LAST_SYNC"
    exit 0
fi
```

### **Ajouter au cron pour monitoring**

```cron
# Vérifier le statut toutes les 3 heures
0 */3 * * * /home/raphael/sms_project/scripts/check_sync_status.sh || mail -s "Alerte sync réservations" admin@example.com
```

---

## 🔗 Intégration avec l'automatisation SMS

La synchronisation des réservations fonctionne en tandem avec le système d'automatisation SMS :

1. **Synchronisation** : `sync_reservations.php` met à jour les réservations
2. **Automatisation** : `auto_send_sms.php` détecte les nouvelles réservations et envoie les SMS

**Exemple de workflow complet :**

```
04:00 - Synchronisation des réservations (cron)
05:00 - Envoi automatique des SMS (cron)
10:00 - Synchronisation des réservations
11:00 - Envoi automatique des SMS
16:00 - Synchronisation des réservations
17:00 - Envoi automatique des SMS
```

---

## 📚 Commandes utiles

```bash
# Tester la synchronisation
php scripts/sync_reservations.php

# Vérifier le cron actif
crontab -l

# Éditer le cron
crontab -e

# Voir les logs en temps réel
tail -f logs/sync_reservations.log

# Compter les réservations synchronisées aujourd'hui
grep "$(date +%Y-%m-%d)" logs/sync_reservations.log | grep -c "Inséré\|Mise à jour"

# Vérifier la dernière synchronisation
tail -20 logs/sync_reservations.log
```

---

**Dernière mise à jour :** 2025-01-19
