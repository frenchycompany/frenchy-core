#!/bin/bash
#
# Script d'automatisation quotidienne des prix Superhote
# Usage: ./run_daily_prices.sh [--dry-run] [--no-sms]
#
# Ce script:
# 1. Genere les prix pour tous les logements actifs
# 2. Lance le worker pool pour appliquer les prix sur Superhote
# 3. Log les resultats
# 4. Envoie une notification SMS
#

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$(dirname "$SCRIPT_DIR")")"
LOG_DIR="$PROJECT_DIR/logs/cron"
DATE=$(date +%Y-%m-%d)
TIME=$(date +%H:%M:%S)
LOG_FILE="$LOG_DIR/superhote_$DATE.log"

# Configuration SMS - Numero de telephone pour les notifications
# Modifier ce numero pour recevoir les notifications
SMS_NOTIFICATION_NUMBER="+33647554678"

# Creer le repertoire de logs si necessaire
mkdir -p "$LOG_DIR"

# Fonction de log
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Fonction d'erreur
error() {
    log "ERREUR: $1"
    send_sms "ERREUR Superhote: $1"
    exit 1
}

# Fonction d'envoi SMS via la table sms_outbox
send_sms() {
    local message="$1"
    if [[ "$SEND_SMS" == "true" && -n "$SMS_NOTIFICATION_NUMBER" && "$SMS_NOTIFICATION_NUMBER" != "+33600000000" ]]; then
        mysql -u sms_user -ppassword123 sms_db -e \
            "INSERT INTO sms_outbox (receiver, message, status, created_at) VALUES ('$SMS_NOTIFICATION_NUMBER', '$message', 'pending', NOW())" 2>/dev/null
        log "  SMS de notification envoye"
    fi
}

# Verification des arguments
DRY_RUN=false
SEND_SMS=true
for arg in "$@"; do
    case $arg in
        --dry-run)
            DRY_RUN=true
            log "Mode dry-run active (pas d'execution reelle)"
            ;;
        --no-sms)
            SEND_SMS=false
            log "Notifications SMS desactivees"
            ;;
    esac
done

log "=========================================="
log "Debut de la mise a jour des prix Superhote"
log "=========================================="

# Etape 1: Generer les prix
log "Etape 1: Generation des prix..."
cd "$SCRIPT_DIR"

if $DRY_RUN; then
    log "  [DRY-RUN] php generate_prices.php --all"
else
    php generate_prices.php --all >> "$LOG_FILE" 2>&1 || error "Echec de la generation des prix"
fi

log "  Generation terminee"

# Etape 2: Lancer le worker pool
log "Etape 2: Application des prix via worker pool..."

# Activer l'environnement virtuel si present
if [[ -f "$PROJECT_DIR/venv/bin/activate" ]]; then
    source "$PROJECT_DIR/venv/bin/activate"
    log "  Environnement virtuel active"
fi

if $DRY_RUN; then
    log "  [DRY-RUN] python superhote_worker_pool.py --groups"
else
    # Lancer avec timeout de 45 minutes max
    timeout 2700 python "$SCRIPT_DIR/superhote_worker_pool.py" --groups >> "$LOG_FILE" 2>&1
    EXIT_CODE=$?

    if [[ $EXIT_CODE -eq 124 ]]; then
        log "  ATTENTION: Timeout atteint (45 min)"
    elif [[ $EXIT_CODE -ne 0 ]]; then
        log "  ATTENTION: Worker pool termine avec code $EXIT_CODE"
    fi
fi

log "  Worker pool termine"

# Etape 3: Resume
log "=========================================="
log "Resume de l'execution"
log "=========================================="

# Compter les updates restantes
PENDING=$(mysql -u sms_user -ppassword123 sms_db -N -e "SELECT COUNT(*) FROM superhote_price_updates WHERE status='pending'" 2>/dev/null || echo "?")
COMPLETED=$(mysql -u sms_user -ppassword123 sms_db -N -e "SELECT COUNT(*) FROM superhote_price_updates WHERE status='completed' AND DATE(created_at)='$DATE'" 2>/dev/null || echo "?")
FAILED=$(mysql -u sms_user -ppassword123 sms_db -N -e "SELECT COUNT(*) FROM superhote_price_updates WHERE status='failed' AND DATE(created_at)='$DATE'" 2>/dev/null || echo "?")

log "  Mises a jour en attente: $PENDING"
log "  Mises a jour terminees aujourd'hui: $COMPLETED"
log "  Mises a jour echouees aujourd'hui: $FAILED"

# Nettoyage des vieux logs (garder 30 jours)
find "$LOG_DIR" -name "superhote_*.log" -mtime +30 -delete 2>/dev/null || true

log "=========================================="
log "Fin de l'execution"
log "=========================================="

# Notification SMS de fin
if [[ "$FAILED" == "0" || "$FAILED" == "?" ]]; then
    send_sms "Superhote OK: $COMPLETED maj appliquees, $PENDING en attente"
else
    send_sms "Superhote: $COMPLETED OK, $FAILED echecs, $PENDING en attente"
fi

exit 0
