#!/bin/bash
#
# Script d'installation des crons pour l'automatisation SMS
#
# Ce script configure les taches cron suivantes :
# - Synchronisation des reservations (toutes les heures)
# - SMS check-out a 9h00
# - SMS check-in a 20h00
# - SMS preparation (J-4) a 10h00
# - SMS mi-parcours a 12h00
# - SMS automatisations personnalisees a 11h00
#
# Usage: sudo bash setup_crons.sh
#

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Couleurs pour les messages
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "==========================================="
echo "Installation des crons SMS automatiques"
echo "==========================================="
echo ""
echo "Repertoire du projet: $PROJECT_DIR"
echo ""

# Verifier que les scripts existent
if [ ! -f "$SCRIPT_DIR/auto_send_sms.php" ]; then
    echo -e "${RED}Erreur: auto_send_sms.php non trouve${NC}"
    exit 1
fi

if [ ! -f "$SCRIPT_DIR/sync_reservations.php" ]; then
    echo -e "${RED}Erreur: sync_reservations.php non trouve${NC}"
    exit 1
fi

# Detecter l'utilisateur (ne pas utiliser root pour les crons)
if [ "$EUID" -eq 0 ]; then
    # Si execute en root, demander l'utilisateur cible
    read -p "Utilisateur pour les crons (ex: raphael, www-data): " CRON_USER
    if [ -z "$CRON_USER" ]; then
        echo -e "${RED}Utilisateur requis${NC}"
        exit 1
    fi
else
    CRON_USER=$(whoami)
fi

echo "Utilisateur cron: $CRON_USER"
echo ""

# Definir les crons
CRON_ENTRIES="# === SMS Project - Automatisation ===

# Synchronisation des reservations depuis les calendriers ICS (toutes les heures)
0 * * * * cd $SCRIPT_DIR && /usr/bin/php sync_reservations.php >> $PROJECT_DIR/logs/cron_sync.log 2>&1

# SMS Check-out du jour (9h00 le matin)
0 9 * * * cd $SCRIPT_DIR && /usr/bin/php auto_send_sms.php --type=checkout >> $PROJECT_DIR/logs/cron_checkout.log 2>&1

# SMS Check-in du jour (20h00 le soir)
0 20 * * * cd $SCRIPT_DIR && /usr/bin/php auto_send_sms.php --type=checkin >> $PROJECT_DIR/logs/cron_checkin.log 2>&1

# SMS Preparation J-4 avant arrivee (10h00)
0 10 * * * cd $SCRIPT_DIR && /usr/bin/php auto_send_sms.php --type=preparation >> $PROJECT_DIR/logs/cron_preparation.log 2>&1

# SMS Mi-parcours pour sejours longs (12h00)
0 12 * * * cd $SCRIPT_DIR && /usr/bin/php auto_send_sms.php --type=midstay >> $PROJECT_DIR/logs/cron_midstay.log 2>&1

# SMS Automatisations personnalisees (11h00)
0 11 * * * cd $SCRIPT_DIR && /usr/bin/php auto_send_sms.php --type=custom >> $PROJECT_DIR/logs/cron_custom.log 2>&1

# === Fin SMS Project ==="

echo "Crons a installer:"
echo "-------------------------------------------"
echo "$CRON_ENTRIES"
echo "-------------------------------------------"
echo ""

read -p "Installer ces crons pour $CRON_USER ? (o/n) " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Oo]$ ]]; then
    # Sauvegarder le crontab existant
    BACKUP_FILE="/tmp/crontab_backup_$(date +%Y%m%d_%H%M%S).txt"
    if [ "$EUID" -eq 0 ]; then
        crontab -u $CRON_USER -l > "$BACKUP_FILE" 2>/dev/null || true
    else
        crontab -l > "$BACKUP_FILE" 2>/dev/null || true
    fi
    echo -e "${YELLOW}Sauvegarde du crontab existant dans: $BACKUP_FILE${NC}"

    # Supprimer les anciennes entrees SMS Project si elles existent
    if [ "$EUID" -eq 0 ]; then
        EXISTING=$(crontab -u $CRON_USER -l 2>/dev/null | grep -v "SMS Project" | grep -v "auto_send_sms.php" | grep -v "sync_reservations.php")
    else
        EXISTING=$(crontab -l 2>/dev/null | grep -v "SMS Project" | grep -v "auto_send_sms.php" | grep -v "sync_reservations.php")
    fi

    # Ajouter les nouvelles entrees
    NEW_CRONTAB="$EXISTING

$CRON_ENTRIES"

    # Installer le nouveau crontab
    if [ "$EUID" -eq 0 ]; then
        echo "$NEW_CRONTAB" | crontab -u $CRON_USER -
    else
        echo "$NEW_CRONTAB" | crontab -
    fi

    echo -e "${GREEN}Crons installes avec succes !${NC}"
    echo ""
    echo "Verification du crontab actuel:"
    if [ "$EUID" -eq 0 ]; then
        crontab -u $CRON_USER -l | grep -A 20 "SMS Project"
    else
        crontab -l | grep -A 20 "SMS Project"
    fi
else
    echo -e "${YELLOW}Installation annulee${NC}"
    exit 0
fi

echo ""
echo "==========================================="
echo "Prochaines etapes:"
echo "==========================================="
echo ""
echo "1. Verifier que le daemon d'envoi SMS tourne:"
echo "   systemctl status sms-sender"
echo ""
echo "2. Si le service n'existe pas, l'installer:"
echo "   sudo cp $PROJECT_DIR/config/sms-sender.service /etc/systemd/system/"
echo "   sudo systemctl daemon-reload"
echo "   sudo systemctl enable sms-sender"
echo "   sudo systemctl start sms-sender"
echo ""
echo "3. Verifier les logs:"
echo "   tail -f $PROJECT_DIR/logs/cron_*.log"
echo ""
