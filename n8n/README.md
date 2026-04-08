# Workflows n8n — FrenchyConciergerie

## Setup

### 1. Variables d'environnement n8n

Dans n8n, aller dans **Settings > Variables** et configurer :

| Variable | Valeur | Description |
|----------|--------|-------------|
| `FRENCHY_API_URL` | `https://votre-domaine.com` | URL de base du VPS (sans slash final) |
| `FRENCHY_API_KEY` | `votre-cle-api` | Cle API (voir table `api_keys` ou `.env` `API_KEY`) |

### 2. Generer une cle API

```sql
INSERT INTO api_keys (nom, api_key) VALUES ('n8n', MD5(RAND()));
SELECT api_key FROM api_keys WHERE nom = 'n8n';
```

Ou dans `.env` :
```
API_KEY=votre-cle-secrete
```

### 3. Importer le workflow

Dans n8n : **Workflows > Import from File** > selectionner `workflow_prospection_airbnb.json`

## Workflows disponibles

### Prospection Airbnb (`workflow_prospection_airbnb.json`)

Pipeline complet toutes les 6h :

```
Scraping Airbnb (recherche par ville)
  -> Extraction des annonces (IDs)
  -> Detail de chaque annonce (nom, host, capacite, prix, note)
  -> Sauvegarde dans market_competitors (dedup par airbnb_id)
  -> Detection des multi-proprietaires (2+ annonces)
  -> Filtrage : seulement les hosts PAS encore dans le CRM
  -> Creation automatique de leads prospection
  -> Check des leads a relancer (score >= 50)
  -> Envoi SMS de relance (si telephone disponible)
```

**A adapter :**
- URL de recherche Airbnb (ville, filtres)
- Message SMS de relance
- Frequence du trigger (defaut: 6h)
- Seuil de score pour relance (defaut: 50)

## Endpoints API disponibles

### Leads (`/api/n8n_leads.php`)

| Methode | URL | Description |
|---------|-----|-------------|
| `GET` | `/api/n8n_leads.php` | Liste leads (filtres: `statut`, `source`, `score_min`, `ville`, `since`) |
| `GET` | `/api/n8n_leads.php?id=X` | Detail lead + interactions |
| `POST` | `/api/n8n_leads.php` | Creer lead (dedup auto par email/tel) |
| `PUT` | `/api/n8n_leads.php?id=X` | Modifier un lead |
| `DELETE` | `/api/n8n_leads.php?id=X` | Supprimer un lead |
| `POST` | `/api/n8n_leads.php?action=interaction` | Ajouter interaction |
| `GET` | `/api/n8n_leads.php?action=relances` | Leads a relancer |
| `POST` | `/api/n8n_leads.php?action=bulk` | Import en masse |

### Competitors (`/api/n8n_competitors.php`)

| Methode | URL | Description |
|---------|-----|-------------|
| `GET` | `/api/n8n_competitors.php` | Liste concurrents (filtres: `ville`, `superhost`, `min_avis`) |
| `GET` | `/api/n8n_competitors.php?id=X` | Detail + prix |
| `POST` | `/api/n8n_competitors.php` | Creer/update concurrent (dedup par airbnb_id) |
| `POST` | `/api/n8n_competitors.php?action=price` | Ajouter un prix |
| `POST` | `/api/n8n_competitors.php?action=bulk` | Import en masse |
| `GET` | `/api/n8n_competitors.php?action=multi_hosts` | Proprietaires multi-annonces |
| `POST` | `/api/n8n_competitors.php?action=to_lead` | Convertir host -> lead |

### Webhook (`/api/n8n_webhook.php`)

| Evenement | Description |
|-----------|-------------|
| `ping` | Test de connectivite |
| `lead.status_change` | Changer statut d'un lead |
| `lead.score_refresh` | Recalculer le score |
| `lead.assign_rdv` | Planifier un RDV |
| `competitor.new` | Notification nouveau concurrent |
| `sms.send` | Envoyer un SMS via la file |

### Authentification

Toutes les requetes necessitent le header :
```
X-API-Key: votre-cle-api
```

### Exemple curl

```bash
# Creer un lead
curl -X POST https://votre-domaine.com/api/n8n_leads.php \
  -H "X-API-Key: VOTRE_CLE" \
  -H "Content-Type: application/json" \
  -d '{"nom": "Dupont", "email": "dupont@email.com", "ville": "Montpellier", "source": "n8n_airbnb", "nb_annonces": 3}'

# Detecter multi-proprietaires
curl "https://votre-domaine.com/api/n8n_competitors.php?action=multi_hosts&min=2" \
  -H "X-API-Key: VOTRE_CLE"

# Webhook ping
curl -X POST https://votre-domaine.com/api/n8n_webhook.php \
  -H "X-API-Key: VOTRE_CLE" \
  -H "Content-Type: application/json" \
  -d '{"event": "ping"}'
```
