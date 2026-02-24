# 🔍 Analyse de la Structure de la Base de Données

## Différences Critiques entre Structure Réelle et Mes Suppositions

Après analyse du fichier `dbs13515816.sql`, voici les **corrections importantes** à apporter à mes services et au script d'optimisation.

---

## ❌ ERREURS IDENTIFIÉES

### 1. **Table `planning` - Noms de colonnes INCORRECTS**

#### Ce que j'ai utilisé (FAUX) :
```sql
conducteur_id, femme_menage_1_id, femme_menage_2_id, laverie_id
date_intervention, heure_intervention
```

#### Structure RÉELLE :
```sql
-- Table planning (ligne 445-474)
conducteur INT              -- Pas conducteur_id
femme_de_menage_1 INT      -- Pas femme_menage_1_id
femme_de_menage_2 INT      -- Pas femme_menage_2_id
laverie INT                -- Pas laverie_id
date DATE                  -- Pas date_intervention
-- PAS de colonne heure_intervention !
```

#### Colonnes supplémentaires réelles :
```sql
nombre_de_personnes
nombre_de_jours_reservation
note_sur_10
poid_menage
notes
logement_id
ca_generé ENUM('non','oui')
charges_comptabilisées ENUM('non','oui')
montant_ca
montant_charges
lit_bebe TINYINT
nombre_lits_specifique
early_check_in TINYINT
late_check_out TINYINT
bonus_total DECIMAL(10,2)
bonus_reason VARCHAR(255)
note TEXT
source_reservation_id
source_type ENUM('AUTO_CHECKOUT','AUTO_ARRIVAL')
commentaire_menage TEXT
bonus TINYINT(1)
```

---

### 2. **Table `comptabilite` - Structure DIFFÉRENTE**

#### Ce que j'ai utilisé (FAUX) :
```sql
categorie, planning_id
```

#### Structure RÉELLE :
```sql
-- Table comptabilite (ligne 60-69)
type ENUM('CA','Charge')
source_type ENUM('intervention','todo')  -- Pas de "planning"
source_id INT                            -- Pas planning_id !
intervenant_id INT
montant FLOAT
date_comptabilisation DATE               -- Pas "date" !
description TEXT
```

#### Index existants (déjà optimisés !) :
```sql
KEY intervenant_id (intervenant_id)
KEY idx_source (source_type, source_id)
KEY idx_type_date (type, date_comptabilisation)
```

---

### 3. **Table `liste_logements` - Noms de colonnes**

#### Ce que j'ai utilisé (FAUX) :
```sql
nom, adresse
```

#### Structure RÉELLE :
```sql
-- Table liste_logements (ligne 334-345)
nom_du_logement VARCHAR(255)   -- Pas "nom" !
adresse VARCHAR(255)
m2 FLOAT
nombre_de_personnes INT
poid_menage DECIMAL(5,2)
prix_vente_menage FLOAT
code VARCHAR(255)
valeur_locative FLOAT
valeur_fonciere FLOAT
```

---

### 4. **Tables d'inventaire - Noms DIFFÉRENTS**

#### Ce que j'ai utilisé (FAUX) :
```sql
inventaire_objets.session_id
sessions_inventaire
```

#### Structure RÉELLE - DEUX SYSTÈMES :

**Système 1 : sessions_inventaire + inventaire_objets**
```sql
-- Table sessions_inventaire (ligne 541-546)
id VARCHAR(64)              -- STRING, pas INT !
logement_id INT
date_creation DATETIME
statut ENUM('en_cours','terminee')  -- Pas 'validee' !

-- Table inventaire_objets (ligne 285-300)
id INT
session_id VARCHAR(50)     -- STRING !
logement_id INT            -- Colonne supplémentaire
nom_objet VARCHAR(255)
quantite INT
marque VARCHAR(255)
etat VARCHAR(50)
date_acquisition DATE
valeur DECIMAL(10,2)      -- Pas valeur_achat !
remarques TEXT
photo_path VARCHAR(255)   -- Pas "photo" !
qr_code_path VARCHAR(255)
proprietaire ENUM('frenchy','proprietaire','autre')
horodatage TIMESTAMP
```

**Système 2 : inventaire_sessions (différent !)**
```sql
-- Table inventaire_sessions (ligne 308-313)
id INT
logements_id INT           -- Pas logement_id !
date_debut DATETIME
statut ENUM('en_cours','valide','archive')
```

---

### 5. **Table `intervenant` - Colonnes supplémentaires**

#### Structure RÉELLE complète :
```sql
-- Table intervenant (ligne 226-237)
id INT
nom VARCHAR(255)
numero VARCHAR(50)
role1 VARCHAR(255)         -- Multiples rôles !
role2 VARCHAR(255)
role3 VARCHAR(255)
nom_utilisateur VARCHAR(50)
mot_de_passe VARCHAR(255)
role ENUM('admin','user')  -- Rôle système
pages_accessibles TEXT     -- Ancien système ?
```

---

### 6. **Table `notifications` - Structure DIFFÉRENTE**

#### Ce que j'ai utilisé (FAUX) :
```sql
user_id, is_read
```

#### Structure RÉELLE :
```sql
-- Table notifications (ligne 366-372)
id INT
nom_utilisateur VARCHAR(255)  -- STRING, pas user_id !
message TEXT
type VARCHAR(100)
date_notification DATETIME
-- PAS de colonne is_read !
```

---

### 7. **Table `login_attempts` - Noms DIFFÉRENTS**

#### Structure RÉELLE :
```sql
-- Table login_attempts (ligne 353-358)
id INT
ip_address VARCHAR(45)
nom_utilisateur VARCHAR(255)  -- Pas username !
attempt_time TIMESTAMP        -- Pas attempted_at !
```

---

## ✅ INDEX DÉJÀ EXISTANTS

**Bonne nouvelle** : Certains indexes existent déjà !

### Table `comptabilite` :
```sql
KEY intervenant_id (intervenant_id)
KEY idx_source (source_type, source_id)
KEY idx_type_date (type, date_comptabilisation)
```

### Table `planning` :
```sql
UNIQUE KEY uniq_resa_source (source_reservation_id, source_type)
KEY fk_planning_logement (logement_id)
```

### Table `reservation` :
```sql
KEY idx_client (client_id)
KEY idx_logement (logement_id)
KEY idx_ref (reference)
KEY idx_statut (statut)
KEY idx_date_depart (date_depart)
KEY idx_date_arrivee (date_arrivee)
KEY idx_created_at (created_at)
KEY idx_plateforme (plateforme)
```

---

## 🔧 CORRECTIONS À APPORTER

### 1. **Corriger `database/optimizations.sql`**

Les indexes que j'ai créés sont **partiellement incorrects** car ils utilisent les mauvais noms de colonnes.

### 2. **Corriger les Services**

#### `src/Services/PlanningService.php`
- Remplacer tous les `conducteur_id` par `conducteur`
- Remplacer `femme_menage_1_id` par `femme_de_menage_1`
- Remplacer `femme_menage_2_id` par `femme_de_menage_2`
- Remplacer `laverie_id` par `laverie`
- Remplacer `date_intervention` par `date`
- Supprimer `heure_intervention` (n'existe pas)

#### `src/Services/ComptabiliteService.php`
- Remplacer `planning_id` par `source_id`
- Ajouter `source_type = 'intervention'`
- Remplacer `date` par `date_comptabilisation`
- Supprimer `categorie` (n'existe pas)

#### `src/Services/InventaireService.php`
- `session_id` est **VARCHAR**, pas INT !
- `sessions_inventaire.statut` = 'terminee', pas 'validee'
- `inventaire_objets` a `photo_path`, pas `photo`
- Ajouter colonne `proprietaire`
- Ajouter `qr_code_path`

---

## 📊 TABLES SUPPLÉMENTAIRES NON DOCUMENTÉES

Tables présentes dans la BDD mais non utilisées dans mes services :

1. **reservation** - Gestion des réservations clients
2. **description_logements** - Description détaillée des logements (poids, critères)
3. **poids_criteres** - Pondération pour calcul du temps de ménage
4. **todo_list** - Liste de tâches (déjà mentionnée)
5. **role** - Définition des rôles et tarifs
6. **gestion_machines** - Gestion des machines de nettoyage
7. **factures** - Stockage des factures générées
8. **contract_*** - Système de contrats (templates, entries, fields)
9. **generated_contracts** - Contrats générés
10. **intervention_tokens** - Tokens pour validation interventions
11. **password_resets** - Réinitialisation mots de passe
12. **leads** - Prospects/leads marketing
13. **articles, sites, partners** - Contenu CMS

---

## ⚠️ IMPACT SUR LE CODE ACTUEL

### Impact CRITIQUE :
- ❌ **PlanningService** ne fonctionnera PAS tel quel
- ❌ **ComptabiliteService** ne fonctionnera PAS tel quel
- ❌ **InventaireService** fonctionnera partiellement

### Impact sur optimizations.sql :
- ⚠️ Certains indexes utilisent les mauvais noms de colonnes
- ✅ Mais certains indexes existent déjà dans la BDD

---

## ✅ ACTIONS REQUISES

### Priorité 1 (URGENT) :
1. **Corriger `src/Services/PlanningService.php`**
   - Tous les noms de colonnes

2. **Corriger `src/Services/ComptabiliteService.php`**
   - Structure de la table comptabilite

3. **Corriger `src/Services/InventaireService.php`**
   - Types de données (VARCHAR pour session_id)

### Priorité 2 (IMPORTANT) :
4. **Mettre à jour `database/optimizations.sql`**
   - Corriger les noms de colonnes dans les indexes
   - Supprimer les indexes déjà existants

5. **Corriger les vues SQL**
   - Adapter aux vrais noms de colonnes

### Priorité 3 (AMÉLIORATION) :
6. **Ajouter services manquants**
   - ReservationService
   - DescriptionLogementService
   - TodoService (déjà mentionné)

---

## 🎯 RECOMMANDATIONS

1. **NE PAS** exécuter `database/optimizations.sql` tel quel
   - Risque d'erreurs SQL
   - Certains indexes existent déjà

2. **TESTER** les services avant utilisation
   - Faire des tests unitaires
   - Vérifier chaque requête

3. **CRÉER** un script de migration
   - Pour corriger progressivement le code existant
   - Sans casser l'existant

4. **DOCUMENTER** les vraies colonnes
   - Dans les commentaires des classes
   - Dans le README

---

## 📝 CONCLUSION

Mes services sont une **bonne base architecturale**, mais ils utilisent des **noms de colonnes incorrects**.

**Il faut les corriger avant toute utilisation en production.**

Le travail de modernisation (architecture MVC, sécurité, cache, UI) reste **totalement valable** et **nécessaire**.

Seuls les **noms de colonnes dans les requêtes SQL** doivent être corrigés.

---

**Préparé le :** 2025-11-12
**Analysé par :** Claude (Sonnet 4.5)
