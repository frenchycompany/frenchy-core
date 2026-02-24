-- ============================================
-- Script d'optimisation de la base de données (CORRIGÉ)
-- FC-gestion - FrenchyConciergerie
-- Version corrigée selon dbs13515816.sql
-- ============================================

-- ============================================
-- INDEXES POUR AMÉLIORER LES PERFORMANCES
-- Note : Certains indexes existent déjà, on les skip
-- ============================================

-- Table planning : indexes sur colonnes fréquemment utilisées
-- Index existant : fk_planning_logement (logement_id)
-- Index existant : uniq_resa_source (source_reservation_id, source_type)

CREATE INDEX IF NOT EXISTS idx_planning_date ON planning(date);
CREATE INDEX IF NOT EXISTS idx_planning_statut ON planning(statut);
CREATE INDEX IF NOT EXISTS idx_planning_conducteur ON planning(conducteur);
CREATE INDEX IF NOT EXISTS idx_planning_fm1 ON planning(femme_de_menage_1);
CREATE INDEX IF NOT EXISTS idx_planning_fm2 ON planning(femme_de_menage_2);
CREATE INDEX IF NOT EXISTS idx_planning_laverie ON planning(laverie);
CREATE INDEX IF NOT EXISTS idx_planning_date_statut ON planning(date, statut);

-- Table comptabilite : indexes déjà optimisés !
-- Index existant : intervenant_id (intervenant_id)
-- Index existant : idx_source (source_type, source_id)
-- Index existant : idx_type_date (type, date_comptabilisation)
-- Rien à ajouter ici !

-- Table intervenant : indexes pour recherches
CREATE INDEX IF NOT EXISTS idx_intervenant_nom ON intervenant(nom);
CREATE INDEX IF NOT EXISTS idx_intervenant_nom_utilisateur ON intervenant(nom_utilisateur);

-- Table liste_logements : indexes pour recherches
CREATE INDEX IF NOT EXISTS idx_logements_nom ON liste_logements(nom_du_logement);

-- Table inventaire_objets : indexes pour sessions
CREATE INDEX IF NOT EXISTS idx_inventaire_session ON inventaire_objets(session_id);
CREATE INDEX IF NOT EXISTS idx_inventaire_logement ON inventaire_objets(logement_id);

-- Table sessions_inventaire : indexes pour logements
CREATE INDEX IF NOT EXISTS idx_sessions_logement ON sessions_inventaire(logement_id);
CREATE INDEX IF NOT EXISTS idx_sessions_statut ON sessions_inventaire(statut);

-- Table login_attempts : indexes pour sécurité
CREATE INDEX IF NOT EXISTS idx_login_ip ON login_attempts(ip_address);
CREATE INDEX IF NOT EXISTS idx_login_time ON login_attempts(attempt_time);

-- Table notifications : indexes pour utilisateurs
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(nom_utilisateur);
CREATE INDEX IF NOT EXISTS idx_notifications_date ON notifications(date_notification);

-- Table todo_list : indexes
-- Index existant : logement_id
CREATE INDEX IF NOT EXISTS idx_todo_statut ON todo_list(statut);
CREATE INDEX IF NOT EXISTS idx_todo_date_limite ON todo_list(date_limite);

-- Table reservation : déjà bien indexée !
-- Tous les indexes nécessaires existent déjà

-- ============================================
-- VUES SQL POUR LES STATISTIQUES
-- ============================================

-- Vue : Planning avec détails complets (CORRIGÉE)
CREATE OR REPLACE VIEW v_planning_details AS
SELECT
    p.id,
    p.date,
    p.nombre_de_personnes,
    p.nombre_de_jours_reservation,
    p.statut,
    p.note_sur_10,
    p.poid_menage,
    p.notes,
    p.lit_bebe,
    p.nombre_lits_specifique,
    p.early_check_in,
    p.late_check_out,
    p.bonus,
    p.bonus_total,
    p.bonus_reason,
    p.note,
    p.commentaire_menage,
    p.ca_generé,
    p.charges_comptabilisées,
    p.montant_ca,
    p.montant_charges,
    l.nom_du_logement AS logement_nom,
    l.adresse AS logement_adresse,
    l.poid_menage AS logement_poids,
    c.nom AS conducteur_nom,
    fm1.nom AS femme_menage_1_nom,
    fm2.nom AS femme_menage_2_nom,
    lav.nom AS laverie_nom
FROM planning p
LEFT JOIN liste_logements l ON p.logement_id = l.id
LEFT JOIN intervenant c ON p.conducteur = c.id
LEFT JOIN intervenant fm1 ON p.femme_de_menage_1 = fm1.id
LEFT JOIN intervenant fm2 ON p.femme_de_menage_2 = fm2.id
LEFT JOIN intervenant lav ON p.laverie = lav.id;

-- Vue : Statistiques par logement (CORRIGÉE)
CREATE OR REPLACE VIEW v_stats_logements AS
SELECT
    l.id,
    l.nom_du_logement AS nom,
    l.adresse,
    l.m2,
    l.nombre_de_personnes,
    COUNT(p.id) AS nb_interventions,
    SUM(CASE WHEN p.statut = 'Fait' THEN 1 ELSE 0 END) AS nb_interventions_terminees,
    SUM(CASE WHEN p.statut = 'À Faire' OR p.statut = 'A Faire' THEN 1 ELSE 0 END) AS nb_interventions_a_faire,
    SUM(CASE WHEN p.bonus = 1 THEN 1 ELSE 0 END) AS nb_bonus,
    COALESCE(SUM(CASE WHEN c.type = 'CA' THEN c.montant ELSE 0 END), 0) AS ca_total,
    COALESCE(SUM(CASE WHEN c.type = 'Charge' THEN c.montant ELSE 0 END), 0) AS charges_total
FROM liste_logements l
LEFT JOIN planning p ON l.id = p.logement_id
LEFT JOIN comptabilite c ON l.id = c.source_id AND c.source_type = 'intervention'
GROUP BY l.id, l.nom_du_logement, l.adresse, l.m2, l.nombre_de_personnes;

-- Vue : Statistiques par intervenant (CORRIGÉE)
CREATE OR REPLACE VIEW v_stats_intervenants AS
SELECT
    i.id,
    i.nom,
    i.numero,
    i.role1,
    i.role2,
    i.role3,
    COUNT(DISTINCT CASE WHEN p.conducteur = i.id THEN p.id END) AS nb_conducteur,
    COUNT(DISTINCT CASE WHEN p.femme_de_menage_1 = i.id THEN p.id END) AS nb_fm1,
    COUNT(DISTINCT CASE WHEN p.femme_de_menage_2 = i.id THEN p.id END) AS nb_fm2,
    COUNT(DISTINCT CASE WHEN p.laverie = i.id THEN p.id END) AS nb_laverie,
    COALESCE(SUM(c.montant), 0) AS remuneration_totale
FROM intervenant i
LEFT JOIN planning p ON (
    p.conducteur = i.id OR
    p.femme_de_menage_1 = i.id OR
    p.femme_de_menage_2 = i.id OR
    p.laverie = i.id
)
LEFT JOIN comptabilite c ON i.id = c.intervenant_id
GROUP BY i.id, i.nom, i.numero, i.role1, i.role2, i.role3;

-- Vue : Bilan comptable mensuel (CORRIGÉE)
CREATE OR REPLACE VIEW v_bilan_mensuel AS
SELECT
    DATE_FORMAT(date_comptabilisation, '%Y-%m') AS mois,
    SUM(CASE WHEN type = 'CA' THEN montant ELSE 0 END) AS ca,
    SUM(CASE WHEN type = 'Charge' THEN montant ELSE 0 END) AS charges,
    SUM(CASE WHEN type = 'CA' THEN montant ELSE -montant END) AS resultat
FROM comptabilite
GROUP BY DATE_FORMAT(date_comptabilisation, '%Y-%m')
ORDER BY mois DESC;

-- Vue : Planning du jour (CORRIGÉE)
CREATE OR REPLACE VIEW v_planning_jour AS
SELECT * FROM v_planning_details
WHERE date = CURDATE()
ORDER BY logement_nom ASC;

-- Vue : Interventions non affectées (CORRIGÉE)
CREATE OR REPLACE VIEW v_interventions_non_affectees AS
SELECT
    p.*,
    l.nom_du_logement AS logement_nom
FROM planning p
LEFT JOIN liste_logements l ON p.logement_id = l.id
WHERE (
    p.conducteur IS NULL OR
    p.femme_de_menage_1 IS NULL
)
AND (p.statut = 'À Faire' OR p.statut = 'A Faire')
AND p.date >= CURDATE()
ORDER BY p.date ASC;

-- Vue : Inventaire actuel par logement (CORRIGÉE)
CREATE OR REPLACE VIEW v_inventaire_actuel AS
SELECT
    l.id AS logement_id,
    l.nom_du_logement AS logement_nom,
    s.id AS session_id,
    s.date_creation,
    COUNT(o.id) AS nb_objets,
    COALESCE(SUM(o.valeur * o.quantite), 0) AS valeur_totale
FROM liste_logements l
LEFT JOIN sessions_inventaire s ON l.id = s.logement_id
LEFT JOIN inventaire_objets o ON s.id = o.session_id
WHERE s.statut = 'terminee'
AND s.id = (
    SELECT s2.id
    FROM sessions_inventaire s2
    WHERE s2.logement_id = l.id
    AND s2.statut = 'terminee'
    ORDER BY s2.date_creation DESC
    LIMIT 1
)
GROUP BY l.id, l.nom_du_logement, s.id, s.date_creation;

-- Vue : Reservations à venir
CREATE OR REPLACE VIEW v_reservations_a_venir AS
SELECT
    r.id,
    r.reference,
    r.prenom,
    r.nom,
    r.telephone,
    r.email,
    r.date_arrivee,
    r.heure_arrivee,
    r.date_depart,
    r.heure_depart,
    r.nb_adultes,
    r.nb_enfants,
    r.nb_bebes,
    r.plateforme,
    l.nom_du_logement,
    l.adresse,
    DATEDIFF(r.date_depart, r.date_arrivee) AS nb_nuits
FROM reservation r
LEFT JOIN liste_logements l ON r.logement_id = l.id
WHERE r.statut = 'confirmée'
AND r.date_arrivee >= CURDATE()
ORDER BY r.date_arrivee ASC;

-- Vue : TODO en attente
CREATE OR REPLACE VIEW v_todo_en_attente AS
SELECT
    t.id,
    t.description,
    t.statut,
    t.date_limite,
    t.responsable,
    t.prix_vente,
    t.prix_achat,
    l.nom_du_logement,
    DATEDIFF(t.date_limite, CURDATE()) AS jours_restants,
    CASE
        WHEN t.date_limite < CURDATE() THEN 'En retard'
        WHEN DATEDIFF(t.date_limite, CURDATE()) <= 3 THEN 'Urgent'
        ELSE 'Normal'
    END AS priorite
FROM todo_list t
LEFT JOIN liste_logements l ON t.logement_id = l.id
WHERE t.statut IN ('en attente', 'en cours')
ORDER BY t.date_limite ASC;

-- ============================================
-- ANALYSE ET MAINTENANCE
-- ============================================

-- Analyser les tables pour optimiser les requêtes
ANALYZE TABLE planning;
ANALYZE TABLE comptabilite;
ANALYZE TABLE liste_logements;
ANALYZE TABLE intervenant;
ANALYZE TABLE inventaire_objets;
ANALYZE TABLE sessions_inventaire;
ANALYZE TABLE reservation;
ANALYZE TABLE todo_list;

-- ============================================
-- NOTES D'UTILISATION
-- ============================================

/*
Pour utiliser ce script :

1. Exécuter ce fichier sur votre base de données MySQL
2. Les indexes seront créés automatiquement (sans dupliquer les existants)
3. Les vues seront disponibles immédiatement

Exemples d'utilisation des vues :

-- Voir le planning du jour :
SELECT * FROM v_planning_jour;

-- Statistiques des logements :
SELECT * FROM v_stats_logements ORDER BY nb_interventions DESC;

-- Bilan mensuel :
SELECT * FROM v_bilan_mensuel WHERE mois >= '2024-01';

-- Interventions non affectées :
SELECT * FROM v_interventions_non_affectees;

-- Réservations à venir :
SELECT * FROM v_reservations_a_venir;

-- TODO urgents :
SELECT * FROM v_todo_en_attente WHERE priorite = 'Urgent';

DIFFÉRENCES AVEC LA VERSION PRÉCÉDENTE :
- Noms de colonnes corrigés selon la vraie structure DB
- Indexes existants non dupliqués
- Vues supplémentaires (reservations, todo)
- Toutes les vues testées et fonctionnelles

*/
