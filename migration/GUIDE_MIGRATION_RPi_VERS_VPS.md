# Guide de migration : Base RPi → VPS

**Objectif :** Toutes les tables SMS/réservations passent sur le VPS. Le RPi garde uniquement le modem GSM et le scraping Chromium, mais se connecte à la base du VPS.

**Date :** 2026-03-06

---

## Pré-requis

- Accès SSH (PuTTY) au VPS IONOS
- Accès SSH au Raspberry Pi
- Les deux serveurs doivent pouvoir communiquer (port MySQL 3306)

---

## Étape 1 — Créer les tables sur le VPS

Sur le **VPS** (PuTTY) :

```bash
# Aller dans le projet
cd /chemin/vers/frenchy-core

# Mettre à jour le code (récupérer les fichiers de migration)
git pull

# Exécuter le script de création des tables
mysql -u frenchy_app -p frenchyconciergerie < migration/001_create_rpi_tables_on_vps.sql

# Vérifier que les tables ont été créées
mysql -u frenchy_app -p frenchyconciergerie -e "SHOW TABLES LIKE 'sms_%';"
mysql -u frenchy_app -p frenchyconciergerie -e "SHOW TABLES LIKE 'travel_%';"
```

Résultat attendu : les tables `sms_in`, `sms_out`, `sms_outbox`, `sms_templates`, etc. apparaissent.

---

## Étape 2 — Exporter les données du RPi

Sur le **Raspberry Pi** (PuTTY) :

```bash
# Exporter UNIQUEMENT les données (pas la structure, elle est déjà créée)
mysqldump -u root -p frenchyconciergerie \
  ai_prompts campagne_immo campagne_sms client client_scenario config \
  contacts conversation_messages conversations ia_scenario ical_reservations \
  ical_sync_log listing_mappings modem satisfaction_conversations scenario \
  sms_conversations sms_in sms_messages sms_out sms_outbox sms_templates \
  sync_log travel_account_connections travel_listings travel_platforms users \
  --no-create-info --complete-insert \
  > /tmp/rpi_data_export.sql

# Vérifier la taille du fichier
ls -lh /tmp/rpi_data_export.sql

# Copier vers le VPS (remplacer IP_VPS par l'IP de ton VPS)
scp /tmp/rpi_data_export.sql user@IP_VPS:/tmp/rpi_data_export.sql
```

---

## Étape 3 — Importer les données sur le VPS

Sur le **VPS** :

```bash
# Importer les données
mysql -u frenchy_app -p frenchyconciergerie < /tmp/rpi_data_export.sql

# Vérifier quelques tables
mysql -u frenchy_app -p frenchyconciergerie -e "SELECT COUNT(*) AS nb_sms_in FROM sms_in;"
mysql -u frenchy_app -p frenchyconciergerie -e "SELECT COUNT(*) AS nb_sms_outbox FROM sms_outbox;"
mysql -u frenchy_app -p frenchyconciergerie -e "SELECT COUNT(*) AS nb_contacts FROM contacts;"
mysql -u frenchy_app -p frenchyconciergerie -e "SELECT COUNT(*) AS nb_conversations FROM satisfaction_conversations;"
```

Les nombres doivent correspondre à ceux du RPi.

---

## Étape 4 — Gérer la table `reservation`

La table `reservation` existe **déjà** sur les deux bases. Il faut vérifier et migrer les données manquantes.

Sur le **RPi** :

```bash
# Compter les réservations sur le RPi
mysql -u root -p frenchyconciergerie -e "SELECT COUNT(*) FROM reservation;"
```

Sur le **VPS** :

```bash
# Compter les réservations sur le VPS
mysql -u frenchy_app -p frenchyconciergerie -e "SELECT COUNT(*) FROM reservation;"

# Si le VPS a moins de réservations, comparer les schémas :
mysql -u frenchy_app -p frenchyconciergerie -e "DESCRIBE reservation;" > /tmp/vps_reservation.txt

# Puis sur le RPi :
# mysql -u root -p frenchyconciergerie -e "DESCRIBE reservation;" > /tmp/rpi_reservation.txt
# Et comparer les deux fichiers
```

Si les schémas sont identiques, exporter/importer les données manquantes :

```bash
# Sur le RPi : exporter les données
mysqldump -u root -p frenchyconciergerie reservation \
  --no-create-info --complete-insert --insert-ignore \
  > /tmp/rpi_reservation_data.sql

# Copier et importer sur le VPS
scp /tmp/rpi_reservation_data.sql user@IP_VPS:/tmp/
# Sur le VPS :
mysql -u frenchy_app -p frenchyconciergerie < /tmp/rpi_reservation_data.sql
```

---

## Étape 5 — Autoriser le RPi à se connecter au VPS

Sur le **VPS** :

```bash
# Créer un utilisateur MySQL pour le RPi (accès distant)
mysql -u root -p <<'EOF'
CREATE USER IF NOT EXISTS 'rpi_sms'@'%' IDENTIFIED BY 'MOT_DE_PASSE_FORT_ICI';
GRANT SELECT, INSERT, UPDATE ON frenchyconciergerie.sms_outbox TO 'rpi_sms'@'%';
GRANT SELECT, INSERT, UPDATE ON frenchyconciergerie.sms_in TO 'rpi_sms'@'%';
GRANT SELECT, INSERT, UPDATE ON frenchyconciergerie.sms_out TO 'rpi_sms'@'%';
GRANT SELECT, INSERT, UPDATE ON frenchyconciergerie.sms_messages TO 'rpi_sms'@'%';
GRANT SELECT, INSERT, UPDATE ON frenchyconciergerie.satisfaction_conversations TO 'rpi_sms'@'%';
GRANT SELECT, INSERT, UPDATE ON frenchyconciergerie.conversation_messages TO 'rpi_sms'@'%';
GRANT SELECT, INSERT, UPDATE ON frenchyconciergerie.conversations TO 'rpi_sms'@'%';
GRANT SELECT ON frenchyconciergerie.reservation TO 'rpi_sms'@'%';
GRANT SELECT ON frenchyconciergerie.liste_logements TO 'rpi_sms'@'%';
GRANT SELECT ON frenchyconciergerie.sms_templates TO 'rpi_sms'@'%';
GRANT SELECT ON frenchyconciergerie.ai_prompts TO 'rpi_sms'@'%';
GRANT SELECT ON frenchyconciergerie.configuration TO 'rpi_sms'@'%';
FLUSH PRIVILEGES;
EOF

# Vérifier que MySQL écoute sur toutes les interfaces (pas seulement 127.0.0.1)
grep -i bind-address /etc/mysql/mariadb.conf.d/50-server.cnf
# Si bind-address = 127.0.0.1, changer en :
# bind-address = 0.0.0.0
# Puis redémarrer : sudo systemctl restart mariadb

# Ouvrir le port 3306 dans le firewall (si UFW actif)
sudo ufw allow from IP_DU_RASPBERRY_PI to any port 3306
```

**IMPORTANT** : Ne pas ouvrir le port 3306 à tout le monde ! Restreindre à l'IP du RPi uniquement.

---

## Étape 6 — Configurer le RPi pour se connecter au VPS

Sur le **Raspberry Pi** :

```bash
cd /chemin/vers/frenchy-core/raspberry-pi

# Éditer le config.ini
nano config/config.ini

# Modifier la section [DATABASE] :
# [DATABASE]
# host = IP_DU_VPS
# port = 3306
# user = rpi_sms
# password = MOT_DE_PASSE_FORT_ICI
# database = frenchyconciergerie

# Tester la connexion
python3 -c "
import pymysql
db = pymysql.connect(host='IP_DU_VPS', port=3306, user='rpi_sms', password='MOT_DE_PASSE_FORT_ICI', database='frenchyconciergerie')
cursor = db.cursor()
cursor.execute('SELECT COUNT(*) FROM sms_outbox')
print('OK -', cursor.fetchone()[0], 'SMS dans sms_outbox')
db.close()
"

# Redémarrer le daemon SMS
sudo systemctl restart envoyer_sms  # ou: supervisorctl restart envoyer_sms
```

---

## Étape 7 — Tester la chaîne complète

1. **Depuis l'interface VPS** : Envoyer un SMS test via la page communication
2. **Vérifier sur le VPS** que le SMS est dans `sms_outbox` avec `status='pending'`
3. **Vérifier sur le RPi** que le daemon le récupère (logs)
4. **Vérifier** que le SMS est marqué `status='sent'` après envoi

```bash
# Sur le VPS : vérifier les SMS en attente
mysql -u frenchy_app -p frenchyconciergerie \
  -e "SELECT id, receiver, status, created_at FROM sms_outbox ORDER BY id DESC LIMIT 5;"

# Sur le RPi : surveiller les logs du daemon
tail -f /chemin/vers/frenchy-core/raspberry-pi/logs/envoyer_sms.log
```

---

## Étape 8 — Nettoyage (après validation)

Une fois que tout fonctionne pendant quelques jours :

```bash
# Sur le RPi : arrêter l'ancienne base locale (optionnel, garde en backup)
# sudo systemctl stop mariadb

# Sur le VPS : supprimer les variables RPI_DB_* du .env (plus nécessaires)
nano /chemin/vers/frenchy-core/.env
# Supprimer les lignes RPI_DB_HOST, RPI_DB_PORT, RPI_DB_NAME, RPI_DB_USER, RPI_DB_PASSWORD

# Nettoyer le fichier d'export temporaire
rm /tmp/rpi_data_export.sql /tmp/rpi_reservation_data.sql
```

---

## En cas de problème (rollback)

Si quelque chose ne marche pas, revenir à l'ancienne config :

1. Sur le RPi : remettre `host = 127.0.0.1` dans `config/config.ini`
2. Sur le VPS : `git checkout -- ionos/gestion/includes/rpi_db.php` pour restaurer l'ancien `getRpiPdo()`
3. Remettre les variables `RPI_DB_*` dans le `.env` du VPS

---

## Résumé de l'architecture après migration

```
┌─────────────────────────────────┐
│   VPS IONOS                     │
│   Base: frenchyconciergerie     │
│   (TOUTES les tables)           │
│                                 │
│   ionos/gestion/ → getRpiPdo()  │
│   retourne $conn (local)        │
└──────────┬──────────────────────┘
           │ MySQL port 3306
           │ (user: rpi_sms)
           │
┌──────────▼──────────────────────┐
│   Raspberry Pi                  │
│   - envoyer_sms.py (daemon)     │
│     Lit sms_outbox du VPS       │
│     Met à jour status='sent'    │
│   - superhote (Chromium)        │
│   - Modem GSM (/dev/ttyUSB0)   │
│   - PAS de base locale          │
└─────────────────────────────────┘
```
