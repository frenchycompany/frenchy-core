#!/bin/bash
# Script pour mettre a jour l'heure de planification du timer systemd
# Usage: ./update_schedule.sh HH:MM ou ./update_schedule.sh "HH:MM,HH:MM,HH:MM"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TIMER_FILE="$SCRIPT_DIR/superhote-scheduled.timer"
SYSTEMD_TIMER="/etc/systemd/system/superhote-scheduled.timer"

# Verifier l'argument
if [ -z "$1" ]; then
    echo "Usage: $0 HH:MM ou $0 \"HH:MM,HH:MM,HH:MM\""
    echo "Exemple: $0 07:00"
    echo "Exemple: $0 \"07:00,12:00,19:00\""
    exit 1
fi

TIMES="$1"

# Valider et construire les lignes OnCalendar
ONCALENDAR_LINES=""
VALID_TIMES=""
IFS=',' read -ra TIME_ARRAY <<< "$TIMES"
for TIME in "${TIME_ARRAY[@]}"; do
    TIME=$(echo "$TIME" | tr -d ' ')
    if [[ "$TIME" =~ ^([01]?[0-9]|2[0-3]):[0-5][0-9]$ ]]; then
        ONCALENDAR_LINES="${ONCALENDAR_LINES}OnCalendar=*-*-* ${TIME}:00
"
        if [ -n "$VALID_TIMES" ]; then
            VALID_TIMES="${VALID_TIMES}, ${TIME}"
        else
            VALID_TIMES="${TIME}"
        fi
    else
        echo "Attention: Format d'heure invalide ignore: $TIME"
    fi
done

if [ -z "$ONCALENDAR_LINES" ]; then
    echo "Erreur: Aucune heure valide fournie. Utilisez HH:MM (ex: 07:00)"
    exit 1
fi

echo "Configuration des mises a jour quotidiennes: $VALID_TIMES"

# Mettre a jour le fichier timer local
cat > "$TIMER_FILE" << EOF
[Unit]
Description=Daily Superhote Price Update Timer
Requires=superhote-scheduled.service

[Timer]
# Executions quotidiennes
${ONCALENDAR_LINES}
# Rattraper les executions manquees (si le serveur etait eteint)
Persistent=true

# Delai aleatoire de 5 minutes pour eviter les pics de charge
RandomizedDelaySec=300

[Install]
WantedBy=timers.target
EOF

echo "Timer local mis a jour: $TIMER_FILE"

# Si on a les droits root, mettre a jour le systemd
if [ "$EUID" -eq 0 ]; then
    # Copier les fichiers vers systemd
    cp "$SCRIPT_DIR/superhote-scheduled.service" /etc/systemd/system/
    cp "$TIMER_FILE" /etc/systemd/system/

    # Recharger systemd
    systemctl daemon-reload

    # Activer et demarrer le timer
    systemctl enable superhote-scheduled.timer
    systemctl restart superhote-scheduled.timer

    echo "Timer systemd mis a jour et redemarre."
    echo "Statut:"
    systemctl status superhote-scheduled.timer --no-pager
else
    echo ""
    echo "Pour appliquer les changements, executez en tant que root:"
    echo "  sudo cp $SCRIPT_DIR/superhote-scheduled.service /etc/systemd/system/"
    echo "  sudo cp $TIMER_FILE /etc/systemd/system/"
    echo "  sudo systemctl daemon-reload"
    echo "  sudo systemctl enable superhote-scheduled.timer"
    echo "  sudo systemctl restart superhote-scheduled.timer"
fi

echo ""
echo "Heures de mise a jour configurees: $VALID_TIMES"
