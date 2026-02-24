# Superhote - Mode Planifie

## Vue d'ensemble

Le systeme de mise a jour des prix Superhote fonctionne maintenant en **mode planifie** au lieu d'un daemon permanent. Cela apporte:

- **Stabilite**: Plus de crashes dus aux sessions Selenium longues
- **Simplicite**: Une execution par jour, facile a monitorer
- **Fiabilite**: Processus frais a chaque execution, pas de fuites memoire
- **Flexibilite**: Heure configurable via l'interface web

## Fonctionnement

1. **Chaque jour a l'heure configuree** (defaut: 7h):
   - Le timer systemd lance `run_scheduled_update.py`
   - Les taches bloquees sont liberees
   - Les prix sont generes pour les 30 prochains jours
   - Les workers appliquent les prix sur Superhote
   - Le script se termine et libere les ressources

2. **Execution manuelle possible**:
   - Via l'interface web (bouton "Lancer maintenant")
   - Via la ligne de commande

## Installation

```bash
# Installer le timer systemd
sudo ./install_scheduled_update.sh

# Repondre a la question sur l'heure (ex: 07:00)
```

## Configuration

### Via l'interface web

1. Aller sur **Superhote Config** > **Workers**
2. Configurer:
   - **Heure d'execution**: Quand lancer la mise a jour
   - **Workers**: Nombre de workers (2 recommande)
   - **Activer**: Active/desactive la planification
3. Cliquer sur "Sauvegarder la planification"

### Via la ligne de commande

```bash
# Modifier l'heure de planification
sudo ./update_schedule.sh 07:00
```

## Commandes utiles

```bash
# Voir le statut du timer
systemctl status superhote-scheduled.timer

# Voir la prochaine execution
systemctl list-timers | grep superhote

# Lancer manuellement (sans attendre le timer)
sudo systemctl start superhote-scheduled.service

# Ou directement via Python
python3 run_scheduled_update.py

# Voir les logs
journalctl -u superhote-scheduled.service -f

# Voir les logs du jour
tail -f /home/user/smsproject/logs/scheduled_update_$(date +%Y-%m-%d).log
```

## Structure des fichiers

```
scripts/selenium/
├── run_scheduled_update.py      # Script principal
├── superhote-scheduled.service  # Unite systemd (service)
├── superhote-scheduled.timer    # Unite systemd (timer)
├── update_schedule.sh           # Script pour changer l'heure
├── install_scheduled_update.sh  # Script d'installation
└── README_SCHEDULED.md          # Cette documentation

logs/
├── scheduled_update_YYYY-MM-DD.log  # Logs journaliers
├── scheduled_update_status.json     # Statut derniere execution
└── manual_run.log                   # Logs execution manuelle
```

## Fichier de statut

Le fichier `logs/scheduled_update_status.json` contient:

```json
{
  "status": "completed",
  "started_at": "2026-01-29T07:00:00",
  "ended_at": "2026-01-29T07:15:23",
  "duration_seconds": 923,
  "steps": {
    "cleanup": {"released": 0},
    "generate": {"logements": 5, "updates": 155},
    "workers": {"status": "completed", "duration": 850},
    "final_stats": {"completed": 150, "failed": 5}
  }
}
```

## Migration depuis l'ancien daemon

L'ancien daemon permanent (`superhote_daemon_v2.py`) est toujours disponible mais n'est plus recommande. Le script d'installation desactive automatiquement l'ancien daemon.

Pour revenir a l'ancien mode (non recommande):
```bash
sudo systemctl stop superhote-scheduled.timer
sudo systemctl disable superhote-scheduled.timer
cd /home/user/smsproject/scripts/selenium
./daemon_ctl.sh start
```

## Depannage

### Le timer ne se lance pas

```bash
# Verifier le statut
systemctl status superhote-scheduled.timer

# Recharger systemd
sudo systemctl daemon-reload
sudo systemctl restart superhote-scheduled.timer
```

### Les prix ne s'appliquent pas

1. Verifier les logs: `tail -100 logs/scheduled_update_*.log`
2. Verifier la queue dans l'interface web
3. Lancer manuellement pour voir les erreurs: `python3 run_scheduled_update.py`

### Taches bloquees

Via l'interface web: cliquer sur "Liberer taches bloquees" dans l'onglet Workers.

Ou via la ligne de commande:
```bash
python3 run_scheduled_update.py --status
```
