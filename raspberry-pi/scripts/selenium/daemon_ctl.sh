#!/bin/bash
#
# Superhote Daemon Control Script
# Usage: ./daemon_ctl.sh {start|stop|restart|status|logs}
#

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
DAEMON_SCRIPT="$SCRIPT_DIR/superhote_daemon_v2.py"
PID_FILE="$SCRIPT_DIR/daemon.pid"
LOG_FILE="$SCRIPT_DIR/../../logs/superhote_daemon_v2.log"

# Configuration par defaut (modifiable)
NUM_WORKERS=${NUM_WORKERS:-2}
POLL_INTERVAL=${POLL_INTERVAL:-30}
USE_GROUPS=${USE_GROUPS:-false}

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

start() {
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if ps -p $PID > /dev/null 2>&1; then
            echo -e "${YELLOW}Daemon deja en cours (PID: $PID)${NC}"
            return 1
        else
            rm -f "$PID_FILE"
        fi
    fi

    echo -e "${GREEN}Demarrage du daemon...${NC}"
    echo "  Workers: $NUM_WORKERS"
    echo "  Interval: ${POLL_INTERVAL}s"
    echo "  Mode: $([ "$USE_GROUPS" = true ] && echo 'groupe' || echo 'standard')"

    # Construire la commande
    CMD="python3 $DAEMON_SCRIPT -w $NUM_WORKERS -i $POLL_INTERVAL"
    if [ "$USE_GROUPS" = true ]; then
        CMD="$CMD --groups"
    fi

    # Lancer en arriere-plan
    nohup $CMD >> "$LOG_FILE" 2>&1 &
    PID=$!
    echo $PID > "$PID_FILE"

    sleep 2
    if ps -p $PID > /dev/null 2>&1; then
        echo -e "${GREEN}Daemon demarre (PID: $PID)${NC}"
    else
        echo -e "${RED}Echec du demarrage${NC}"
        rm -f "$PID_FILE"
        return 1
    fi
}

stop() {
    if [ ! -f "$PID_FILE" ]; then
        echo -e "${YELLOW}Daemon non demarre${NC}"
        return 0
    fi

    PID=$(cat "$PID_FILE")
    if ps -p $PID > /dev/null 2>&1; then
        echo -e "${YELLOW}Arret du daemon (PID: $PID)...${NC}"
        kill $PID

        # Attendre l'arret
        for i in {1..30}; do
            if ! ps -p $PID > /dev/null 2>&1; then
                break
            fi
            sleep 1
        done

        if ps -p $PID > /dev/null 2>&1; then
            echo -e "${RED}Forcer l'arret...${NC}"
            kill -9 $PID
        fi

        echo -e "${GREEN}Daemon arrete${NC}"
    else
        echo -e "${YELLOW}Daemon deja arrete${NC}"
    fi

    rm -f "$PID_FILE"
}

status() {
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if ps -p $PID > /dev/null 2>&1; then
            echo -e "${GREEN}Daemon en cours (PID: $PID)${NC}"

            # Afficher les workers
            echo ""
            echo "Workers actifs:"
            ps -ef | grep "superhote_daemon_v2" | grep -v grep | head -5

            # Afficher le statut de la queue
            echo ""
            echo "Queue status:"
            python3 "$DAEMON_SCRIPT" --status 2>/dev/null || echo "  (erreur lecture queue)"

            return 0
        else
            echo -e "${RED}Daemon mort (PID file existe mais process absent)${NC}"
            rm -f "$PID_FILE"
            return 1
        fi
    else
        echo -e "${YELLOW}Daemon non demarre${NC}"
        return 1
    fi
}

logs() {
    if [ -f "$LOG_FILE" ]; then
        tail -f "$LOG_FILE"
    else
        echo -e "${RED}Fichier de log non trouve: $LOG_FILE${NC}"
    fi
}

case "$1" in
    start)
        start
        ;;
    stop)
        stop
        ;;
    restart)
        stop
        sleep 2
        start
        ;;
    status)
        status
        ;;
    logs)
        logs
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status|logs}"
        echo ""
        echo "Variables d'environnement:"
        echo "  NUM_WORKERS=$NUM_WORKERS       Nombre de workers"
        echo "  POLL_INTERVAL=$POLL_INTERVAL   Intervalle de polling (secondes)"
        echo "  USE_GROUPS=$USE_GROUPS         Mode groupe (true/false)"
        echo ""
        echo "Exemples:"
        echo "  $0 start                       # Demarrer avec config par defaut"
        echo "  NUM_WORKERS=4 $0 start         # Demarrer avec 4 workers"
        echo "  USE_GROUPS=true $0 start       # Demarrer en mode groupe"
        echo "  $0 logs                        # Suivre les logs en direct"
        exit 1
        ;;
esac
