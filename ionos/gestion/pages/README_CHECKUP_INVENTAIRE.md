# Systeme Checkup / Inventaire / Taches

Documentation du systeme integre de gestion des logements pour les intervenants (femmes de menage, techniciens).

---

## Architecture generale

Le systeme repose sur **3 outils interconnectes** + des outils transversaux :

```
┌──────────────────────────────────────────────────────────────┐
│                     CHECKUP (hub central)                    │
│  checkup_logement.php · checkup_faire.php · checkup_rapport  │
├──────────┬──────────┬──────────┬──────────────────────────────┤
│INVENTAIRE│  TACHES  │EQUIPMENTS│    OUTILS TRANSVERSAUX       │
│inventaire│ todo.php │logement_ │ dashboard · historique ·     │
│_saisie   │ todo_list│equipments│ stats · pdf · qr · templates │
└──────────┴──────────┴──────────┴──────────────────────────────┘
```

Le **Checkup** est le point d'entree principal. Il agrege les donnees des 3 autres sources pour creer une checklist complete qu'un intervenant peut parcourir sur mobile.

---

## Toutes les fonctionnalites

### 1. Checkup logement (hub)

**Fichiers :**
- `checkup_logement.php` — Lancement avec preview AJAX + auto-select QR
- `checkup_faire.php` — Checklist interactive mobile-first
- `checkup_rapport.php` — Rapport de synthese imprimable

**Fonctionnalites :**
- Selection du logement avec preview AJAX (taches en attente, dernier inventaire, equipements)
- Auto-selection via parametre `?auto_logement=X` (pour QR codes)
- Generation automatique des items depuis **5 sources** :
  1. Equipements du logement (`logement_equipements`)
  2. Objets du dernier inventaire termine (`inventaire_objets`)
  3. Taches en attente (`todo_list` → liees via `todo_task_id`)
  4. Etat general (items standards : proprete, odeurs, securite, etc.)
  5. **Templates personnalises** (`checkup_templates` — piscine, jardin, etc.)
- Interface tactile mobile-first avec boutons OK / Probleme / Absent
- Sauvegarde AJAX en temps reel
- Capture photo via camera
- Barre de progression avec compteur
- Categories avec icones collapsibles
- Sync bidirectionnelle : marquer tache OK → `todo_list.statut = 'terminee'`
- Liens rapides vers Inventaire / Taches / Equipements
- **Signature tactile** canvas en fin de checkup
- **Notifications** automatiques si problemes (email + BDD)
- **Mode hors-ligne** (Service Worker + IndexedDB)
- Rapport avec score cards + details + impression + **export PDF**
- Validation des inputs via helpers securises

### 2. Historique des checkups

**Fichier :** `checkup_historique.php`

- Liste tous les checkups (tous logements ou filtre)
- Filtres : logement, statut, intervenant, dates
- Stats globales (total, termines, en cours, score moyen)
- Cards cliquables vers rapport ou reprise

### 3. Dashboard de suivi

**Fichier :** `checkup_dashboard.php`

- Vue globale de tous les logements actifs
- Pour chaque logement : dernier checkup + score, taches en attente, dernier inventaire
- Code couleur par score (vert ≥80%, orange ≥50%, rouge <50%)
- Liens rapides : checkup, rapport, taches
- Stats globales : nb logements, score moyen, alertes

### 4. Statistiques checkup

**Fichier :** `checkup_statistiques.php`

- Graphique evolution du score dans le temps (Chart.js)
- Multi-lignes par logement si pas de filtre
- Graphique barres : problemes et absents par checkup
- Classement par logement (score moyen, nb checkups)
- Top 10 problemes les plus frequents
- Top 10 elements absents les plus frequents

### 5. Export PDF

**Fichier :** `checkup_pdf.php`

- Page optimisee pour impression / print-to-PDF
- Layout A4 avec en-tete, score cards, details, signature
- Bouton "Telecharger en PDF" (print navigateur)
- Accessible depuis le rapport via bouton PDF

### 6. Signature intervenant

- Canvas tactile integre dans `checkup_faire.php`
- Support souris et tactile (touch events)
- Sauvegarde en base64 → fichier PNG dans `uploads/signatures/`
- Affichee dans le rapport et le PDF
- Colonne `signature_path` ajoutee a `checkup_sessions`

### 7. Templates de checkup

**Fichier :** `checkup_templates.php`

- Table `checkup_templates` pour items personnalises
- Items globaux (tous logements) ou specifiques a un logement
- Gestion AJAX (ajout, suppression, activation/desactivation)
- Categories predefinies : Piscine, Jardin, Garage, Cave, Sauna/Spa, etc.
- Automatiquement inclus lors de la creation d'un checkup

### 8. Notifications

**Fichier :** `includes/notifications.php`

- Table `notifications` pour stocker les alertes
- Email automatique quand checkup termine avec problemes
- Details des problemes dans l'email (categorie, item, commentaire)
- Lien direct vers le rapport dans l'email
- Variable `ADMIN_EMAIL` dans `.env` pour configurer le destinataire

### 9. QR Code checkup

**Fichier :** `checkup_qrcode.php`

- Generation de QR codes par logement (via phpqrcode)
- QR pointe vers `checkup_logement.php?auto_logement=X`
- Generation individuelle ou en masse
- Telecharger / Imprimer chaque QR
- Les QR existants restent utilisables meme sans la lib

### 10. Mode hors-ligne

**Fichier :** `sw-checkup.js` (Service Worker)

- Cache des pages essentielles et ressources statiques
- Strategie Network First, fallback cache
- Stockage des requetes POST offline dans IndexedDB
- Synchronisation automatique au retour en ligne
- Banniere visuelle "Mode hors-ligne" quand deconnecte

### 11. Comparaison inventaire

**Fichier :** `inventaire_comparer.php`

- Compare deux sessions d'inventaire pour un meme logement
- Detection : objets ajoutes, supprimes, modifies, identiques
- Comparaison par nom + piece (case-insensitive)
- Detection des changements de quantite, etat, marque
- Stats resume + sections depliables

### 12. Inventaire (reecrit)

**Fichiers :**
- `inventaire.php` — Accueil avec stats
- `inventaire_lancer.php` — Lancement de session
- `inventaire_saisie.php` — Saisie AJAX
- `liste_sessions.php` — Liste sessions

**Ameliorations :**
- Interface AJAX complete
- Classement par piece
- Capture photo, badges d'etat
- Mode lecture seule quand termine

### 13. Gestion des photos

**Fichier :** `includes/upload_helper.php`

- Creation automatique des dossiers uploads avec droits 0755
- `.htaccess` pour bloquer l'execution de scripts
- Validation : taille max 10Mo, extensions autorisees, MIME type
- Noms de fichiers securises (timestamp + random)
- Sous-dossiers : checkup, inventaire, signatures, qrcodes

### 14. Validation des donnees

**Fichier :** `includes/validation.php`

- `sanitizeString()` — Strip tags + trim
- `sanitizeInt()` — Entier positif valide
- `sanitizeFloat()` — Float valide
- `sanitizeDate()` — Date Y-m-d valide
- `sanitizeEnum()` — Valeur parmi liste autorisee
- `requireAuth()` — Verification connexion + role
- `verifyCsrfToken()` — Protection CSRF

### 15. Multi-langue (i18n)

**Fichiers :**
- `includes/i18n.php` — Systeme de traduction
- `includes/lang/fr.php` — Traductions francaises
- `includes/lang/en.php` — Traductions anglaises

- Detection automatique : session > parametre `?lang=` > defaut FR
- Fonction `__('cle')` avec support de parametres `{:nom}`
- Selecteur de langue FR/EN dans la navbar
- ~100 cles traduites couvrant checkup, inventaire, rapport, dashboard, stats

---

## Base de donnees

### Tables creees

```sql
-- Sessions de checkup
checkup_sessions (
    id, logement_id, intervenant_id,
    statut ENUM('en_cours','termine'),
    nb_ok, nb_problemes, nb_absents, nb_taches_faites,
    commentaire_general, signature_path,
    created_at, updated_at
)

-- Items de checkup
checkup_items (
    id, session_id, categorie, nom_item,
    statut ENUM('ok','probleme','absent','non_verifie'),
    commentaire, photo_path, todo_task_id,
    created_at
)

-- Templates personnalises
checkup_templates (
    id, logement_id (NULL=global), categorie, nom_item,
    actif, ordre, created_at
)

-- Notifications
notifications (
    id, type, titre, message, lien,
    logement_id, intervenant_id, lu, created_at
)
```

### Colonnes ajoutees

```sql
ALTER TABLE inventaire_objets ADD COLUMN piece VARCHAR(50);
ALTER TABLE checkup_sessions ADD COLUMN signature_path VARCHAR(500);
```

---

## Arborescence des fichiers

```
ionos/gestion/
├── sw-checkup.js                    # Service Worker hors-ligne
├── includes/
│   ├── upload_helper.php            # Gestion securisee des uploads
│   ├── validation.php               # Sanitization et validation
│   ├── notifications.php            # Systeme de notifications
│   ├── i18n.php                     # Internationalisation
│   └── lang/
│       ├── fr.php                   # Traductions FR
│       └── en.php                   # Traductions EN
└── pages/
    ├── checkup_logement.php         # Lancement checkup (hub)
    ├── checkup_faire.php            # Execution checkup (mobile)
    ├── checkup_rapport.php          # Rapport checkup
    ├── checkup_pdf.php              # Export PDF
    ├── checkup_historique.php       # Historique checkups
    ├── checkup_dashboard.php        # Dashboard suivi
    ├── checkup_statistiques.php     # Stats + graphiques
    ├── checkup_templates.php        # Templates personnalises
    ├── checkup_qrcode.php           # QR codes par logement
    ├── inventaire.php               # Accueil inventaire
    ├── inventaire_lancer.php        # Lancer session
    ├── inventaire_creer_session.php # Creation session
    ├── inventaire_saisie.php        # Saisie objets AJAX
    ├── inventaire_valider.php       # Validation session
    ├── inventaire_comparer.php      # Comparaison sessions
    ├── liste_sessions.php           # Liste sessions
    ├── liste_objets.php             # Liste objets
    ├── objet.php                    # Detail objet
    ├── impression_etiquettes.php    # QR codes inventaire
    ├── todo.php                     # Taches par logement
    ├── todo_list_complete.php       # Vue globale taches
    ├── logement_equipements.php     # Gestion equipements
    └── menu.php                     # Menu (modifie)
```

---

## Configuration

### Variables d'environnement (.env)

```
ADMIN_EMAIL=admin@frenchyconciergerie.fr   # Pour les notifications email
```

### Dependances externes (CDN)

- Bootstrap 5.3 (CSS + JS)
- FontAwesome 6.4
- Chart.js 4.4 (statistiques uniquement)
- jQuery 3.6 (pages todo uniquement)

### Dependance PHP optionnelle

- `phpqrcode` (lib/) — Pour generer les QR codes. Si absent, les QR existants restent utilisables.
