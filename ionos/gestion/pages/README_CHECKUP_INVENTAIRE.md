# Systeme Checkup / Inventaire / Taches

Documentation du systeme integre de gestion des logements pour les intervenants (femmes de menage, techniciens).

---

## Architecture generale

Le systeme repose sur **3 outils interconnectes** :

```
┌─────────────────────────────────────────────────┐
│                  CHECKUP (hub)                  │
│         checkup_logement.php (lancement)        │
│         checkup_faire.php (execution)           │
│         checkup_rapport.php (rapport)           │
├────────────────┬────────────────┬───────────────┤
│   INVENTAIRE   │     TACHES     │  EQUIPEMENTS  │
│  inventaire.php│   todo.php     │  logement_    │
│  inventaire_   │   todo_list_   │  equipements  │
│  saisie.php    │   complete.php │  .php         │
└────────────────┴────────────────┴───────────────┘
```

Le **Checkup** est le point d'entree principal. Il agrege les donnees des 3 autres sources pour creer une checklist complete qu'un intervenant peut parcourir sur mobile.

---

## Ce qui a ete fait

### 1. Checkup logement (CREE)

**Fichiers :**
- `checkup_logement.php` — Page de lancement avec preview AJAX
- `checkup_faire.php` — Checklist interactive mobile-first
- `checkup_rapport.php` — Rapport de synthese imprimable

**Tables SQL creees :**
- `checkup_sessions` — Sessions de checkup (logement, intervenant, compteurs, statut)
- `checkup_items` — Items individuels (categorie, statut ok/probleme/absent, photo, commentaire, lien todo_task_id)

**Fonctionnalites :**
- Selection du logement avec preview AJAX (taches en attente, dernier inventaire, equipements)
- Generation automatique des items depuis 4 sources :
  - Equipements du logement (`logement_equipements`)
  - Objets du dernier inventaire termine (`inventaire_objets`)
  - Taches en attente (`todo_list` → liees via `todo_task_id`)
  - Etat general (items standards : proprete, odeurs, securite, etc.)
- Interface tactile mobile-first avec boutons OK / Probleme / Absent
- Sauvegarde AJAX en temps reel (pas de rechargement de page)
- Capture photo via camera du telephone
- Barre de progression avec compteur
- Categories avec icones (equipements, inventaire, taches, etat general)
- Synchronisation bidirectionnelle : marquer une tache OK dans le checkup → met a jour `todo_list.statut` a `terminee`
- Liens rapides vers Inventaire / Taches / Equipements en haut de page
- Rapport avec score cards (OK, Problemes, Absents, Score %, Taches X/Y)
- CSS d'impression pour le rapport

### 2. Inventaire (REECRIT)

**Fichiers modifies :**
- `inventaire.php` — Page d'accueil modernisee avec stats (sessions en cours, terminees, total objets)
- `inventaire_lancer.php` — Lancement de session avec alerte si session en cours existe deja
- `inventaire_saisie.php` — Saisie complete reecrite en AJAX
- `liste_sessions.php` — Liste toutes les sessions (en cours + terminees)

**Fichier supprime :**
- `faire_inventaire.php` — Etait un doublon de `inventaire_saisie.php` utilisant la mauvaise table (`objet_inventaire` au lieu de `inventaire_objets`)

**Ameliorations :**
- Interface AJAX (ajout, suppression, modification sans rechargement)
- Classement par piece (`piece` column ajoutee a `inventaire_objets`)
- Capture photo avec camera
- Badges d'etat (neuf, bon, use, abime)
- Champs optionnels depliables (marque, date acquisition, valeur, remarques)
- Compteur d'objets en temps reel
- Mode lecture seule quand session terminee
- Correction du bug de statut (`valide` → `terminee`)

### 3. Corrections de bugs

- **`statistiques_menage.php`** : Corrige le JOIN `pl.logement = ll.nom_du_logement` → `pl.logement_id = ll.id` (PDOException column not found)
- **Doublon inventaire** : Supprime `faire_inventaire.php` qui utilisait la mauvaise table
- **Statut inventaire** : Corrige la verification `valide` → `terminee` dans `checkup_logement.php`

### 4. Menu

- Ajout de l'entree "Checkup" dans le menu (categorie Logements) avec icone `fa-clipboard-check`

---

## Ce qui fonctionne

| Fonctionnalite | Statut | Notes |
|---|---|---|
| Lancer un checkup | OK | Selectionner logement + intervenant |
| Preview AJAX du logement | OK | Taches, inventaire, equipements |
| Generation auto des items | OK | 4 sources combinees |
| Checklist interactive mobile | OK | Boutons tactiles, AJAX |
| Capture photo | OK | Camera du telephone |
| Progression en temps reel | OK | Barre + compteur |
| Sync taches checkup → todo_list | OK | Marquer OK = tache terminee |
| Rapport de checkup | OK | Score cards + details + impression |
| Liens rapides (inventaire/taches/equipements) | OK | Boutons en haut du checkup |
| Inventaire - ajout/suppression AJAX | OK | Sans rechargement |
| Inventaire - classement par piece | OK | Colonne `piece` |
| Inventaire - capture photo | OK | Camera |
| Inventaire - mode lecture seule | OK | Session terminee |
| Liste sessions inventaire | OK | En cours + terminees |
| Stats inventaire (accueil) | OK | Compteurs dynamiques |

---

## Ce qu'il reste a faire

### Priorite haute

1. **Gestion des photos sur le serveur** — Les photos sont uploadees dans `../uploads/checkup/` et `../uploads/inventaire/` mais il faut verifier que ces dossiers existent et ont les bons droits en production.

2. **Historique des checkups** — Actuellement on peut lancer un checkup mais il n'y a pas de page listant tous les checkups passes pour un logement. Ajouter une page `checkup_historique.php`.

3. **Notifications** — Quand un checkup signale des problemes, envoyer une notification (email ou autre) au gestionnaire.

4. **Validation des donnees** — Renforcer la validation cote serveur (sanitization des inputs, verification des droits d'acces).

### Priorite moyenne

5. **Comparaison inventaire** — Permettre de comparer deux sessions d'inventaire pour detecter les objets manquants ou ajoutes.

6. **Templates de checkup** — Permettre de personnaliser les items d'etat general par logement (certains logements ont piscine, jardin, etc.).

7. **Dashboard de suivi** — Vue globale de tous les logements avec dernier checkup, score, taches en attente.

8. **Export PDF** — Generer un PDF du rapport de checkup (actuellement impression navigateur uniquement).

9. **Signature intervenant** — Ajouter une signature tactile en fin de checkup pour valider.

### Priorite basse

10. **Mode hors-ligne** — Service worker pour permettre le checkup sans connexion internet (sync au retour).

11. **QR code checkup** — Generer un QR code par logement pour lancer directement le checkup en scannant.

12. **Statistiques checkup** — Graphiques d'evolution des scores par logement dans le temps.

13. **Multi-langue** — L'interface est actuellement en francais uniquement.

---

## Base de donnees

### Tables creees

```sql
-- Sessions de checkup
checkup_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    logement_id INT NOT NULL,
    intervenant_id INT DEFAULT NULL,
    statut ENUM('en_cours','termine') DEFAULT 'en_cours',
    nb_ok INT DEFAULT 0,
    nb_problemes INT DEFAULT 0,
    nb_absents INT DEFAULT 0,
    nb_taches_faites INT DEFAULT 0,
    commentaire_general TEXT DEFAULT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)

-- Items de checkup
checkup_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    categorie VARCHAR(50) NOT NULL,     -- equipements, inventaire, taches, etat_general
    nom_item VARCHAR(255) NOT NULL,
    statut ENUM('ok','probleme','absent','non_verifie') DEFAULT 'non_verifie',
    commentaire TEXT,
    photo_path VARCHAR(500),
    todo_task_id INT DEFAULT NULL,      -- lien vers todo_list.id
    created_at TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES checkup_sessions(id) ON DELETE CASCADE
)
```

### Colonnes ajoutees

```sql
-- Classement par piece dans l'inventaire
ALTER TABLE inventaire_objets ADD COLUMN piece VARCHAR(50) DEFAULT NULL;
```

### Tables existantes utilisees

- `liste_logements` — Liste des logements
- `logement_equipements` — Equipements booleens par logement
- `inventaire_objets` — Objets inventories
- `sessions_inventaire` — Sessions d'inventaire (id VARCHAR, pas INT)
- `todo_list` — Taches a faire par logement
- `intervenant` — Liste des intervenants

---

## Arborescence des fichiers

```
ionos/gestion/pages/
├── checkup_logement.php      # Lancement checkup (hub)
├── checkup_faire.php         # Execution checkup (mobile)
├── checkup_rapport.php       # Rapport checkup (impression)
├── inventaire.php            # Accueil inventaire (stats)
├── inventaire_lancer.php     # Lancer nouvelle session
├── inventaire_creer_session.php  # Creation session (POST)
├── inventaire_saisie.php     # Saisie objets (AJAX)
├── inventaire_valider.php    # Validation session
├── liste_sessions.php        # Liste sessions
├── liste_objets.php          # Liste objets par logement
├── objet.php                 # Detail objet
├── impression_etiquettes.php # Impression QR codes
├── todo.php                  # Taches par logement
├── todo_list_complete.php    # Vue globale des taches
├── logement_equipements.php  # Gestion equipements
└── menu.php                  # Menu navigation (modifie)
```
