# Superhote - Automatisation Yield Management

## Vue d'ensemble

Système d'automatisation des prix sur Superhote.com via Selenium. Permet de mettre à jour automatiquement les tarifs de plusieurs logements en fonction d'une logique de yield management (prix dynamiques selon l'anticipation).

**Performance**: ~2.3 min/appartement, 7 appartements en ~16 minutes avec 2 workers.

## Architecture

```
scripts/selenium/
├── superhote_base.py          # Classe de base Selenium (login, navigation, modales)
├── superhote_worker_pool.py   # Pool de workers parallèles (PRINCIPAL)
├── generate_prices.php        # Génération des prix (CLI)
├── run_daily_prices.sh        # Script cron d'automatisation
├── config_superhote.ini       # Configuration (credentials)
└── migrations/
    ├── 004_add_groups.sql
    └── 005_add_group_pricing.sql

web/pages/
└── superhote_config.php       # Interface de configuration
```

## Fonctionnalités

### 1. Yield Management automatique
- **Prix plancher** (J0) : prix minimum pour réservations de dernière minute
- **Prix standard** (J14+) : prix normal pour réservations anticipées
- **Paliers intermédiaires** : J1-3 (25%), J4-6 (50%), J7-13 (75%) - configurables
- **Majoration weekend** : +X% vendredi/samedi
- **Réduction dimanche** : -Y€

### 2. Système de groupes
- Regrouper plusieurs logements avec la même tarification
- **Logement de référence** : logement fictif SANS réservations pour ouvrir les modales
- **Héritage des prix** : les logements d'un groupe héritent automatiquement des prix du groupe (pas de duplication)
- Modifier les prix du groupe = modifier pour tous les logements du groupe

### 3. Worker Pool parallèle
- Plusieurs workers Selenium en parallèle
- **Mode groupe** : utilise le logement de référence, coche les vrais logements
- **Mode standard** : traite les logements orphelins individuellement
- **Optimisation** : fusion des dates consécutives avec le même prix (réduit ~50% des opérations)

### 4. Notifications SMS
- Notification de fin d'exécution via table `sms_outbox`
- Résumé : nombre de mises à jour OK/échecs/en attente
- Numéro configuré : +33647554678

## Tables MySQL

```sql
-- Configuration par logement
superhote_config (
    logement_id, superhote_property_id, superhote_property_name,
    prix_plancher, prix_standard, weekend_pourcent, dimanche_reduction,
    groupe, is_active
)

-- Groupes de logements (source unique des prix pour logements groupés)
superhote_groups (
    id, nom, description, logement_reference_id,
    prix_plancher, prix_standard, weekend_pourcent, dimanche_reduction
)

-- File d'attente des mises à jour
superhote_price_updates (
    logement_id, superhote_property_id, nom_du_logement,
    date_start, date_end, price, rule_name,
    status  -- 'pending', 'processing', 'completed', 'failed'
)

-- Paramètres globaux
superhote_settings (
    key_name, value
    -- palier_j1_3_pourcent (20), palier_j4_13_pourcent (40),
    -- palier_j14_30_pourcent (60), palier_j31_60_pourcent (80),
    -- jours_generation (90)
)
```

## Configuration

### config_superhote.ini
```ini
[SUPERHOTE]
email = ton_email@example.com
password = ton_mot_de_passe
headless = true
timeout = 30
```

### Numéro SMS (run_daily_prices.sh ligne 25)
```bash
SMS_NOTIFICATION_NUMBER="+33647554678"
```

## Utilisation

### Script cron complet (recommandé)
```bash
cd /home/raphael/sms_project/scripts/selenium
./run_daily_prices.sh           # Génération + workers + SMS
./run_daily_prices.sh --no-sms  # Sans notification SMS
./run_daily_prices.sh --dry-run # Simulation
```

### Exécution manuelle par étapes
```bash
# 1. Générer les prix dans la BDD
php generate_prices.php --all

# 2. Lancer le worker pool
python superhote_worker_pool.py --groups
```

### Cron (tous les jours à 6h)
```bash
crontab -e
0 6 * * * /home/raphael/sms_project/scripts/selenium/run_daily_prices.sh
```

## Flux de données

```
1. Interface PHP (superhote_config.php)
   └─> Configuration logements + groupes

2. generate_prices.php --all
   └─> Calcule prix selon yield management
   └─> INSERT INTO superhote_price_updates (status='pending')
   └─> Utilise prix du GROUPE si logement groupé

3. superhote_worker_pool.py --groups
   ├─> Récupère updates pending
   ├─> Regroupe par prix/date
   ├─> Fusionne dates consécutives (optimisation)
   ├─> Worker 1: GROUPE1 (logement ref) → coche vrais logements
   ├─> Worker 2: Logements orphelins (mode standard)
   └─> UPDATE status='completed'/'failed'

4. Notification SMS via sms_outbox
```

## Sélecteurs Selenium importants

```python
# Calendrier principal
"td[data-td-date='YYYY-MM-DD']"      # Cellule de date
"div.checkin-price"                   # Zone cliquable pour ouvrir modal prix

# Modal de prix
"#price-number"                       # Input prix
"#edit-price-end-date"                # Input date de fin (IMPORTANT!)
".vdp-datepicker"                     # Datepicker
".day__month_btn"                     # Bouton mois (DOUBLE UNDERSCORE!)
"span.cell.day"                       # Jours dans le picker

# Modal multi-logements (mode groupe)
"#edit-rentals-price"                 # Modal de sélection
"#edit-rentals-price .btn-save"       # Bouton enregistrer
"input[type='checkbox']"              # Checkboxes logements
```

## Points d'attention critiques

1. **Logement de référence** : DOIT être un logement SANS réservations (fictif type "GROUPE1")
2. **Réservations** : Les cellules avec réservations sont détectées et skippées automatiquement
3. **Date picker** : Utiliser `.day__month_btn` (DOUBLE underscore, pas simple)
4. **Timeout** : Script a un timeout de 45 minutes max
5. **Héritage prix** : Si logement dans groupe avec prix définis → utilise prix du groupe

## Logs

```
logs/
├── superhote_worker_pool.log        # Logs détaillés workers
├── cron/
│   └── superhote_YYYY-MM-DD.log     # Logs quotidiens cron
└── screenshots/
    ├── modal_debug_*.png            # Debug modales
    └── debug_picker_fin_*.png       # Debug date picker
```

## Troubleshooting

### "Mois du picker non detecte"
- Normal en mode standard, fallback fonctionne
- Le picker de date de fin s'ouvre parfois mal

### "Jour X non trouve dans le picker"
- Le picker n'était pas ouvert, fallback utilisé
- Vérifier les screenshots de debug

### Worker bloqué
- Vérifier si réservation sur la date
- Le logement de référence a peut-être une réservation

### Pas de mise à jour
- Vérifier que `is_active = 1` sur le logement
- Vérifier que prix_plancher ET prix_standard sont définis (groupe OU logement)

## Évolutions possibles

1. ☐ Dashboard web historique des exécutions
2. ☐ Mode "retry failed only"
3. ☐ Détection automatique nouvelles réservations
4. ☐ Support multi-comptes Superhote
5. ☐ Alertes si trop d'échecs
