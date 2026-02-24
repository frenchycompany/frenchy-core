# Superhote Workers - Architecture V2

## Vue d'ensemble

Le systeme de workers V2 utilise des **sessions Selenium persistantes** pour eviter
de se reconnecter a Superhote a chaque tache. Les workers restent actifs et
piochent dans une queue de taches (BDD).

## Avantages vs ancienne architecture

| Ancienne (worker_pool) | Nouvelle (daemon_v2) |
|------------------------|---------------------|
| Nouvelle session a chaque run | Sessions persistantes |
| Login/logout a chaque fois | Login unique, session reutilisee |
| ProcessPoolExecutor | Threads persistants |
| Termine apres traitement | Tourne en continu |
| Pas de controle en temps reel | Interface web de controle |

## Fichiers

```
scripts/selenium/
├── superhote_daemon_v2.py     # Daemon principal (NOUVEAU)
├── daemon_ctl.sh              # Script de controle (NOUVEAU)
├── superhote-daemon.service   # Service systemd (NOUVEAU)
├── superhote_base.py          # Classe Selenium de base
├── superhote_worker_pool.py   # Ancienne architecture (conservee)
└── README_WORKERS.md          # Cette documentation
```

## Utilisation

### Demarrage manuel

```bash
# Demarrer avec 2 workers, poll toutes les 30s
python3 superhote_daemon_v2.py -w 2 -i 30

# Demarrer en mode groupe (1 worker par groupe configure)
python3 superhote_daemon_v2.py -w 4 --groups

# Voir le statut de la queue
python3 superhote_daemon_v2.py --status
```

### Via le script de controle

```bash
# Demarrer
./daemon_ctl.sh start

# Arreter
./daemon_ctl.sh stop

# Redemarrer
./daemon_ctl.sh restart

# Voir le statut
./daemon_ctl.sh status

# Suivre les logs
./daemon_ctl.sh logs

# Avec parametres personnalises
NUM_WORKERS=4 POLL_INTERVAL=60 ./daemon_ctl.sh start
USE_GROUPS=true ./daemon_ctl.sh start
```

### Via systemd (production)

```bash
# Installer le service
sudo cp superhote-daemon.service /etc/systemd/system/
sudo systemctl daemon-reload

# Demarrer et activer au boot
sudo systemctl enable superhote-daemon
sudo systemctl start superhote-daemon

# Voir les logs
sudo journalctl -u superhote-daemon -f

# Arreter
sudo systemctl stop superhote-daemon
```

### Via l'interface web

1. Aller sur la page **Tarifs** (superhote_config.php)
2. Cliquer sur l'onglet **Workers**
3. Configurer le nombre de workers et l'intervalle
4. Cliquer sur **Demarrer**

## Parametres

| Parametre | Defaut | Description |
|-----------|--------|-------------|
| `-w, --workers` | 2 | Nombre de workers |
| `-i, --interval` | 30 | Intervalle de polling (secondes) |
| `-g, --groups` | false | Mode groupe (1 worker par groupe) |
| `--session-timeout` | 1800 | Timeout session Selenium (30 min) |
| `--max-tasks` | 50 | Taches max par session avant refresh |

## Architecture

```
                    ┌─────────────────────┐
                    │  SuperhoteDaemon    │
                    │  (Thread principal) │
                    └─────────┬───────────┘
                              │
           ┌──────────────────┼──────────────────┐
           │                  │                  │
           ▼                  ▼                  ▼
    ┌──────────────┐   ┌──────────────┐   ┌──────────────┐
    │   Worker 1   │   │   Worker 2   │   │   Worker N   │
    │  (Thread)    │   │  (Thread)    │   │  (Thread)    │
    │              │   │              │   │              │
    │ ┌──────────┐ │   │ ┌──────────┐ │   │ ┌──────────┐ │
    │ │ Selenium │ │   │ │ Selenium │ │   │ │ Selenium │ │
    │ │ Session  │ │   │ │ Session  │ │   │ │ Session  │ │
    │ └──────────┘ │   │ └──────────┘ │   │ └──────────┘ │
    └──────┬───────┘   └──────┬───────┘   └──────┬───────┘
           │                  │                  │
           └──────────────────┼──────────────────┘
                              │
                              ▼
                    ┌─────────────────────┐
                    │ TaskQueueManager    │
                    │ (MySQL Queue)       │
                    └─────────────────────┘
                              │
                              ▼
                    ┌─────────────────────┐
                    │superhote_price_updates│
                    │ status='pending'     │
                    └─────────────────────┘
```

## Cycle de vie d'un Worker

1. **Demarrage** : Cree une instance Selenium
2. **Login** : Se connecte a Superhote (une seule fois)
3. **Boucle** :
   - Pioche des taches dans la queue (BDD)
   - Traite les taches
   - Marque comme completed/failed
   - Attend l'intervalle de poll
4. **Healthcheck** : Verifie si refresh session necessaire
5. **Refresh** : Ferme et reouvre la session si timeout

## Etats des Workers

| Etat | Description |
|------|-------------|
| `idle` | En attente de taches |
| `starting` | Demarrage Selenium |
| `connected` | Connecte a Superhote |
| `processing` | Traitement d'une tache |
| `reconnecting` | Refresh de la session |
| `error` | Erreur (tentera de se reconnecter) |
| `stopped` | Arrete proprement |

## Queue de taches

Les taches sont stockees dans la table `superhote_price_updates` :

| status | Description |
|--------|-------------|
| `pending` | En attente de traitement |
| `processing` | En cours (reserve par un worker) |
| `completed` | Traitement reussi |
| `failed` | Echec du traitement |

### Mecanismes de securite

- **Locking** : `FOR UPDATE SKIP LOCKED` pour eviter les conflits
- **Timeout** : Les taches `processing` depuis >30 min sont liberees
- **Retry** : Les workers retentent en cas d'erreur

## Logs

Les logs sont ecrits dans :
- `/logs/superhote_daemon_v2.log`

Format :
```
2026-01-28 10:30:45 - INFO - [Worker-1] Session Selenium active
2026-01-28 10:30:46 - INFO - [Worker-1] 15 taches reservees
2026-01-28 10:30:50 - INFO - [Worker-1] Maj prix 28/01 - 29/01 = 70EUR pour 3 logements
```

## Troubleshooting

### Le daemon ne demarre pas
- Verifier les logs : `./daemon_ctl.sh logs`
- Verifier les permissions du display X11/Xvfb
- Verifier la config BDD : `config/config.ini`

### Les workers meurent
- Le daemon les relance automatiquement (healthcheck toutes les 5 min)
- Verifier la memoire disponible
- Augmenter `--session-timeout` si les sessions expirent trop vite

### Taches bloquees en 'processing'
- Via l'interface web : bouton "Liberer taches bloquees"
- Via CLI : `python3 superhote_daemon_v2.py --status` pour voir la queue

### Erreur "Element not clickable"
- Le site Superhote a peut-etre change
- Verifier les screenshots dans `/logs/screenshots/`
- Mettre a jour `superhote_base.py`
