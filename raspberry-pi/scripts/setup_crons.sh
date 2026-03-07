#!/bin/bash
#
# Script d'installation des crons pour l'automatisation SMS
#
# Les crons appellent le VPS via curl pour exécuter les scripts PHP.
# Les scripts PHP vivent sur le VPS (source unique de vérité).
# Le RPi ne fait que déclencher et envoyer les SMS via le modem.
#
# Prérequis:
# - CRON_SECRET configuré dans le .env du VPS
# - Le même CRON_SECRET dans /home/raphael/sms_project/.cron_secret sur le RPi
#
# Usage: sudo bash setup_crons.sh
#

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
LOG_DIR="$PROJECT_DIR/logs"

# Couleurs pour les messages
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "==========================================="
echo "Installation des crons SMS (mode VPS API)"
echo "==========================================="
echo ""
echo "Repertoire du projet: $PROJECT_DIR"
echo ""

# Vérifier/demander l'URL du VPS
VPS_URL="${VPS_URL:-https://gestion.frenchyconciergerie.fr}"
read -p "URL du VPS [$VPS_URL]: " input_url
VPS_URL="${input_url:-$VPS_URL}"

# Vérifier/créer le fichier de secret
SECRET_FILE="$PROJECT_DIR/.cron_secret"
if [ ! -f "$SECRET_FILE" ]; then
    echo -e "${YELLOW}Fichier .cron_secret non trouvé.${NC}"
    read -p "Entrez le CRON_SECRET (même valeur que dans le .env du VPS): " CRON_SECRET
    if [ -z "$CRON_SECRET" ]; then
        echo -e "${RED}CRON_SECRET requis${NC}"
        exit 1
    fi
    echo "$CRON_SECRET" > "$SECRET_FILE"
    chmod 600 "$SECRET_FILE"
    echo -e "${GREEN}Secret enregistré dans $SECRET_FILE${NC}"
fi

# Créer le répertoire de logs si nécessaire
mkdir -p "$LOG_DIR"

# Détecter l'utilisateur
if [ "$EUID" -eq 0 ]; then
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

# Définir les crons (curl vers le VPS)
CRON_ENTRIES="# === SMS Project - Automatisation (via VPS API) ===

# Synchronisation des reservations ICS (toutes les heures)
0 * * * * curl -sf -H \"Authorization: Bearer \$(cat $SECRET_FILE)\" \"$VPS_URL/api/cron_sync_reservations.php\" >> $LOG_DIR/cron_sync.log 2>&1

# SMS Check-out du jour (9h00 le matin)
0 9 * * * curl -sf -H \"Authorization: Bearer \$(cat $SECRET_FILE)\" \"$VPS_URL/api/cron_auto_sms.php?type=checkout\" >> $LOG_DIR/cron_checkout.log 2>&1

# SMS Check-in du jour (20h00 le soir)
0 20 * * * curl -sf -H \"Authorization: Bearer \$(cat $SECRET_FILE)\" \"$VPS_URL/api/cron_auto_sms.php?type=checkin\" >> $LOG_DIR/cron_checkin.log 2>&1

# SMS Preparation J-4 avant arrivee (10h00)
0 10 * * * curl -sf -H \"Authorization: Bearer \$(cat $SECRET_FILE)\" \"$VPS_URL/api/cron_auto_sms.php?type=preparation\" >> $LOG_DIR/cron_preparation.log 2>&1

# SMS Mi-parcours pour sejours longs (12h00)
0 12 * * * curl -sf -H \"Authorization: Bearer \$(cat $SECRET_FILE)\" \"$VPS_URL/api/cron_auto_sms.php?type=midstay\" >> $LOG_DIR/cron_midstay.log 2>&1

# SMS Automatisations personnalisees (11h00)
0 11 * * * curl -sf -H \"Authorization: Bearer \$(cat $SECRET_FILE)\" \"$VPS_URL/api/cron_auto_sms.php?type=custom\" >> $LOG_DIR/cron_custom.log 2>&1

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

    # Supprimer les anciennes entrées SMS Project
    if [ "$EUID" -eq 0 ]; then
        EXISTING=$(crontab -u $CRON_USER -l 2>/dev/null | grep -v "SMS Project" | grep -v "auto_send_sms" | grep -v "sync_reservations" | grep -v "cron_auto_sms" | grep -v "cron_sync")
    else
        EXISTING=$(crontab -l 2>/dev/null | grep -v "SMS Project" | grep -v "auto_send_sms" | grep -v "sync_reservations" | grep -v "cron_auto_sms" | grep -v "cron_sync")
    fi

    # Ajouter les nouvelles entrées
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
echo "1. Sur le VPS, configurer CRON_SECRET dans le .env:"
echo "   echo 'CRON_SECRET=<votre_secret>' >> /path/to/.env"
echo ""
echo "2. Sur le VPS, installer les dépendances composer:"
echo "   cd ionos/gestion && composer install"
echo ""
echo "3. Verifier que le daemon d'envoi SMS tourne:"
echo "   systemctl status sms-sender"
echo ""
echo "4. Tester un appel manuellement:"
echo "   curl -s -H \"Authorization: Bearer \$(cat $SECRET_FILE)\" \"$VPS_URL/api/cron_auto_sms.php?type=checkout\" | python3 -m json.tool"
echo ""
echo "5. Verifier les logs:"
echo "   tail -f $LOG_DIR/cron_*.log"
echo ""
