# 🤖 Automatisation de l'envoi de SMS

## 📋 Vue d'ensemble

Le système d'automatisation SMS envoie automatiquement des messages personnalisés aux clients selon leur réservation :

- **Check-out** : SMS de départ le jour du départ
- **Check-in** : SMS d'accueil le jour de l'arrivée
- **Préparation** : SMS de préparation 4 jours avant l'arrivée
- **Automatisations personnalisées** : Créez vos propres règles d'envoi avec filtre par logement

---

## 🔧 Architecture

### **1. Script d'automatisation** (`scripts/auto_send_sms.php`)
- Vérifie les réservations du jour et dans X jours
- Génère les SMS dans la table `sms_outbox` (status='pending')
- Marque les flags sur les réservations (dep_sent, j1_sent, start_sent)

### **2. Script d'envoi** (`scripts/envoyer_sms.py`)
- Lit la table `sms_outbox` (status='pending')
- Envoie via le modem série GSM
- Marque les SMS comme envoyés (status='sent')

### **3. Interface web** (`web/pages/automation_config.php`)
- Configuration de l'automatisation
- Statistiques en temps réel
- Test manuel
- Visualisation des logs

---

## 📦 Installation

### **Étape 1 : Donner les permissions d'exécution**

```bash
chmod +x /home/raphael/sms_project/scripts/auto_send_sms.php
chmod +x /home/raphael/sms_project/scripts/envoyer_sms.py
```

### **Étape 2 : Créer le dossier de logs**

```bash
mkdir -p /home/raphael/sms_project/logs
chmod 755 /home/raphael/sms_project/logs
```

### **Étape 3 : Tester manuellement**

```bash
# Test du script d'automatisation
php /home/raphael/sms_project/scripts/auto_send_sms.php

# Vérifier les logs
tail -f /home/raphael/sms_project/logs/auto_send_sms.log
```

### **Étape 4 : Configurer le cron**

```bash
# Éditer la crontab
crontab -e

# Ajouter cette ligne (exécution toutes les 30 minutes)
*/30 * * * * php /home/raphael/sms_project/scripts/auto_send_sms.php >> /home/raphael/sms_project/logs/cron.log 2>&1
```

**Autres exemples de planification :**
```bash
# Tous les jours à 8h, 12h et 18h
0 8,12,18 * * * php /home/raphael/sms_project/scripts/auto_send_sms.php >> /home/raphael/sms_project/logs/cron.log 2>&1

# Tous les jours à 9h uniquement
0 9 * * * php /home/raphael/sms_project/scripts/auto_send_sms.php >> /home/raphael/sms_project/logs/cron.log 2>&1

# Toutes les heures
0 * * * * php /home/raphael/sms_project/scripts/auto_send_sms.php >> /home/raphael/sms_project/logs/cron.log 2>&1
```

### **Étape 5 : Démarrer le service d'envoi SMS**

```bash
# Créer un service systemd
sudo nano /etc/systemd/system/sms-sender.service
```

Contenu du fichier :
```ini
[Unit]
Description=SMS Sender Service
After=network.target mysql.service

[Service]
Type=simple
User=raphael
WorkingDirectory=/home/raphael/sms_project/scripts
ExecStart=/usr/bin/python3 /home/raphael/sms_project/scripts/envoyer_sms.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

```bash
# Activer et démarrer le service
sudo systemctl daemon-reload
sudo systemctl enable sms-sender
sudo systemctl start sms-sender

# Vérifier le statut
sudo systemctl status sms-sender
```

---

## ⚙️ Configuration via l'interface web

### **Accéder à la page de configuration**

URL : `http://ton-serveur/pages/automation_config.php`

### **Options disponibles**

1. **Types d'envoi activés**
   - ☑ Check-out du jour
   - ☑ Check-in du jour
   - ☑ Préparation (X jours avant)

2. **Nombre de jours pour la préparation**
   - Par défaut : 4 jours
   - Modifiable entre 1 et 30 jours

3. **Planification cron**
   - Expression cron personnalisable
   - Exemples fournis dans l'interface

4. **Test manuel**
   - Bouton "Exécuter maintenant" pour tester
   - Affiche les résultats en temps réel

5. **Statistiques**
   - SMS en attente
   - SMS envoyés aujourd'hui
   - Réservations à traiter

6. **Logs en temps réel**
   - Affiche les 50 dernières lignes
   - Rafraîchir la page pour actualiser

---

## 📊 Fonctionnement détaillé

### **Flux de données**

```
┌─────────────────┐
│  Réservations   │
│   (sms_db)      │
└────────┬────────┘
         │
         ↓
┌─────────────────────────┐
│ auto_send_sms.php       │
│ (Cron toutes les 30min) │
├─────────────────────────┤
│ • Vérifie date_arrivee  │
│ • Vérifie date_depart   │
│ • Génère les SMS        │
└────────┬────────────────┘
         │
         ↓
┌─────────────────┐
│   sms_outbox    │
│ (status=pending)│
└────────┬────────┘
         │
         ↓
┌─────────────────────────┐
│ envoyer_sms.py          │
│ (Service permanent)     │
├─────────────────────────┤
│ • Lit sms_outbox        │
│ • Envoie via modem GSM  │
│ • Marque status=sent    │
└─────────────────────────┘
```

### **Logique d'envoi**

```php
// Pour chaque type de SMS
if (check-out aujourd'hui && dep_sent == 0) {
    → Créer SMS dans sms_outbox
    → Marquer dep_sent = 1
}

if (check-in aujourd'hui && j1_sent == 0) {
    → Créer SMS dans sms_outbox
    → Marquer j1_sent = 1
}

if (arrivée dans 4 jours && start_sent == 0) {
    → Créer SMS dans sms_outbox
    → Marquer start_sent = 1
}
```

---

## 🔍 Vérification et dépannage

### **Vérifier que le cron fonctionne**

```bash
# Voir les tâches cron actives
crontab -l

# Vérifier les logs du cron
tail -f /home/raphael/sms_project/logs/cron.log

# Vérifier les logs d'automatisation
tail -f /home/raphael/sms_project/logs/auto_send_sms.log
```

### **Vérifier le service d'envoi**

```bash
# Statut du service
sudo systemctl status sms-sender

# Logs du service
journalctl -u sms-sender -f

# Logs Python
tail -f /home/raphael/sms_project/logs/envoyer_sms.log
```

### **Vérifier la base de données**

```bash
mysql -u sms_user -ppassword123 sms_db
```

```sql
-- SMS en attente
SELECT * FROM sms_outbox WHERE status='pending';

-- SMS envoyés aujourd'hui
SELECT COUNT(*) FROM sms_outbox WHERE status='sent' AND DATE(sent_at) = CURDATE();

-- Réservations du jour sans SMS
SELECT * FROM reservation
WHERE date_depart = CURDATE() AND dep_sent = 0;

SELECT * FROM reservation
WHERE date_arrivee = CURDATE() AND j1_sent = 0;
```

### **Problèmes courants**

#### **1. Les SMS ne sont pas créés**

**Vérifier :**
- Le cron est bien configuré : `crontab -l`
- Les logs : `tail /home/raphael/sms_project/logs/auto_send_sms.log`
- La configuration dans `automation_config.php`
- Les templates SMS existent dans la base

**Solution :**
```bash
# Tester manuellement
php /home/raphael/sms_project/scripts/auto_send_sms.php
```

#### **2. Les SMS restent en status='pending'**

**Vérifier :**
- Le service `sms-sender` est démarré : `sudo systemctl status sms-sender`
- Le modem est connecté : `ls -la /dev/ttyUSB0`
- Les logs Python : `tail /home/raphael/sms_project/logs/envoyer_sms.log`

**Solution :**
```bash
# Redémarrer le service
sudo systemctl restart sms-sender

# Vérifier le modem
python3 /home/raphael/sms_project/scripts/config_modem.py
```

#### **3. Erreur "PDO non disponible"**

**Vérifier :**
- MySQL est démarré : `sudo systemctl status mariadb`
- Les credentials dans `.env` sont corrects
- La connexion fonctionne : `php /home/raphael/sms_project/test_connection.php`

---

## 📈 Monitoring

### **Dashboard web**

Accéder à `http://ton-serveur/pages/automation_config.php` pour voir :
- Nombre de SMS en attente
- SMS envoyés aujourd'hui
- Réservations à traiter par type
- Logs en temps réel

### **Alertes**

Pour être notifié en cas de problème, ajouter un script de monitoring :

```bash
#!/bin/bash
# /home/raphael/sms_project/scripts/check_sms.sh

PENDING=$(mysql -u sms_user -ppassword123 sms_db -se "SELECT COUNT(*) FROM sms_outbox WHERE status='pending'")

if [ "$PENDING" -gt 10 ]; then
    echo "⚠️ ALERTE: $PENDING SMS en attente" | mail -s "Alerte SMS" admin@example.com
fi
```

```bash
# Ajouter au cron (toutes les heures)
0 * * * * /home/raphael/sms_project/scripts/check_sms.sh
```

---

## 🎯 Bonnes pratiques

1. **Tester avant de mettre en production**
   - Utiliser le bouton "Test manuel" dans l'interface
   - Vérifier les logs
   - Tester sur quelques réservations

2. **Surveiller les logs**
   - Vérifier régulièrement `/logs/auto_send_sms.log`
   - Configurer des alertes

3. **Backup de la configuration**
   ```bash
   cp /home/raphael/sms_project/scripts/auto_send_sms_config.php \
      /home/raphael/sms_project/scripts/auto_send_sms_config.php.backup
   ```

4. **Rotation des logs**
   ```bash
   # Ajouter à la crontab (tous les lundis à 3h)
   0 3 * * 1 find /home/raphael/sms_project/logs -name "*.log" -mtime +30 -delete
   ```

---

## 📞 Support

En cas de problème :
1. Vérifier les logs d'abord
2. Tester manuellement les scripts
3. Consulter la documentation
4. Créer une issue GitHub avec les logs

---

## 🏠 Automatisations spécifiques par logement

### **Créer une automatisation pour un logement spécifique**

Depuis la version mise à jour, vous pouvez créer des automatisations qui s'appliquent uniquement à un logement particulier.

**Cas d'usage :**
- Envoyer un code d'accès différent selon le logement
- SMS de bienvenue personnalisé par propriété
- Instructions spécifiques pour certains logements
- Promotions ciblées

**Comment faire :**

1. Accéder à `http://ton-serveur/pages/custom_automations.php`
2. Créer une nouvelle automatisation
3. Sélectionner un logement dans le menu déroulant "Logement (optionnel)"
4. Si aucun logement n'est sélectionné, l'automatisation s'applique à tous les logements

**Exemple :**

```
Nom: Code accès Appartement A
Description: SMS avec code d'accès spécifique pour l'appartement A
Déclencheur: Date d'arrivée
Jours: 0 (le jour même)
Template: code_acces
Logement: Appartement A
Flag: custom4_sent
```

**Fonctionnement technique :**

Le script `auto_send_sms.php` filtre automatiquement les réservations selon le `logement_id` défini dans l'automatisation :

```sql
-- Si logement_id est défini
SELECT r.* FROM reservation r
WHERE r.date_arrivee = '2025-01-20'
AND r.statut = 'confirmée'
AND r.logement_id = 1  -- Filtre par logement
```

**Migration automatique :**

La colonne `logement_id` est ajoutée automatiquement à la table `sms_automations` lors du premier accès à la page des automatisations personnalisées. Aucune intervention manuelle n'est nécessaire.

---

**Dernière mise à jour :** 2025-01-19
