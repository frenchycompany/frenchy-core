#!/bin/bash
# Script d'installation du systeme de mise a jour planifiee Superhote
# Remplace l'ancien daemon permanent par une execution quotidienne planifiee

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$(dirname "$SCRIPT_DIR")")"

echo "=========================================="
echo "Installation Superhote - Mode Planifie"
echo "=========================================="
echo ""
echo "Ce script va:"
echo "  1. Arreter l'ancien daemon (si actif)"
echo "  2. Installer le nouveau timer systemd"
echo "  3. Configurer la mise a jour quotidienne"
echo ""

# Verifier qu'on est root
if [ "$EUID" -ne 0 ]; then
    echo "Erreur: Ce script doit etre execute en tant que root"
    echo "Usage: sudo $0"
    exit 1
fi

# Configuration par defaut
DEFAULT_TIME="07:00"
read -p "Heure de mise a jour quotidienne [$DEFAULT_TIME]: " SCHEDULED_TIME
SCHEDULED_TIME=${SCHEDULED_TIME:-$DEFAULT_TIME}

# Valider le format
if ! [[ "$SCHEDULED_TIME" =~ ^([01]?[0-9]|2[0-3]):[0-5][0-9]$ ]]; then
    echo "Erreur: Format d'heure invalide. Utilisez HH:MM"
    exit 1
fi

echo ""
echo "1. Arret de l'ancien daemon..."
# Arreter l'ancien daemon s'il existe
if systemctl is-active --quiet superhote-daemon 2>/dev/null; then
    systemctl stop superhote-daemon
    systemctl disable superhote-daemon
    echo "   Ancien daemon arrete et desactive."
else
    echo "   Ancien daemon non actif."
fi

echo ""
echo "2. Creation des repertoires..."
mkdir -p "$PROJECT_DIR/logs"
chmod 755 "$PROJECT_DIR/logs"

echo ""
echo "3. Configuration du timer a $SCHEDULED_TIME..."
# Mettre a jour le timer avec l'heure choisie
cat > "$SCRIPT_DIR/superhote-scheduled.timer" << EOF
[Unit]
Description=Daily Superhote Price Update Timer
Requires=superhote-scheduled.service

[Timer]
# Execution quotidienne a $SCHEDULED_TIME
OnCalendar=*-*-* $SCHEDULED_TIME:00

# Rattraper les executions manquees
Persistent=true

# Delai aleatoire de 5 minutes
RandomizedDelaySec=300

[Install]
WantedBy=timers.target
EOF

echo ""
echo "4. Installation des fichiers systemd..."
cp "$SCRIPT_DIR/superhote-scheduled.service" /etc/systemd/system/
cp "$SCRIPT_DIR/superhote-scheduled.timer" /etc/systemd/system/

echo ""
echo "5. Rechargement de systemd..."
systemctl daemon-reload

echo ""
echo "6. Activation du timer..."
systemctl enable superhote-scheduled.timer
systemctl start superhote-scheduled.timer

echo ""
echo "=========================================="
echo "Installation terminee!"
echo "=========================================="
echo ""
echo "Configuration:"
echo "  - Heure d'execution: $SCHEDULED_TIME"
echo "  - Service: superhote-scheduled.service"
echo "  - Timer: superhote-scheduled.timer"
echo ""
echo "Commandes utiles:"
echo "  - Voir le statut: systemctl status superhote-scheduled.timer"
echo "  - Voir la prochaine execution: systemctl list-timers | grep superhote"
echo "  - Lancer manuellement: systemctl start superhote-scheduled.service"
echo "  - Voir les logs: journalctl -u superhote-scheduled.service"
echo ""
echo "Pour modifier l'heure, executez:"
echo "  sudo $SCRIPT_DIR/update_schedule.sh HH:MM"
echo ""

# Afficher le statut
echo "Statut actuel du timer:"
systemctl list-timers | grep -E "(NEXT|superhote)" || true
