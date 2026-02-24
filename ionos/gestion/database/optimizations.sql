-- ============================================
-- Script d'optimisation de la base de données
-- FC-gestion - FrenchyConciergerie
-- ============================================

-- ============================================
-- INDEXES POUR AMÉLIORER LES PERFORMANCES
-- ============================================

-- Table planning : index sur les colonnes fréquemment utilisées
CREATE INDEX IF NOT EXISTS idx_planning_date ON planning(date_intervention);
CREATE INDEX IF NOT EXISTS idx_planning_logement ON planning(logement_id);
CREATE INDEX IF NOT EXISTS idx_planning_statut ON planning(statut);
CREATE INDEX IF NOT EXISTS idx_planning_conducteur ON planning(conducteur_id);
CREATE INDEX IF NOT EXISTS idx_planning_fm1 ON planning(femme_menage_1_id);
CREATE INDEX IF NOT EXISTS idx_planning_fm2 ON planning(femme_menage_2_id);
CREATE INDEX IF NOT EXISTS idx_planning_laverie ON planning(laverie_id);
CREATE INDEX IF NOT EXISTS idx_planning_date_statut ON planning(date_intervention, statut);

-- Table comptabilite : index pour les requêtes financières
CREATE INDEX IF NOT EXISTS idx_comptabilite_date ON comptabilite(date);
CREATE INDEX IF NOT EXISTS idx_comptabilite_type ON comptabilite(type);
CREATE INDEX IF NOT EXISTS idx_comptabilite_intervenant ON comptabilite(intervenant_id);
CREATE INDEX IF NOT EXISTS idx_comptabilite_logement ON comptabilite(logement_id);
CREATE INDEX IF NOT EXISTS idx_comptabilite_date_type ON comptabilite(date, type);
CREATE INDEX IF NOT EXISTS idx_comptabilite_planning ON comptabilite(planning_id);

-- Table intervenant : index pour les recherches
CREATE INDEX IF NOT EXISTS idx_intervenant_nom ON intervenant(nom);
CREATE INDEX IF NOT EXISTS idx_intervenant_email ON intervenant(email);

-- Table liste_logements : index pour les recherches
CREATE INDEX IF NOT EXISTS idx_logements_nom ON liste_logements(nom);

-- Table inventaire_objets : index pour les sessions
CREATE INDEX IF NOT EXISTS idx_inventaire_session ON inventaire_objets(session_id);

-- Table sessions_inventaire : index pour les logements
CREATE INDEX IF NOT EXISTS idx_sessions_logement ON sessions_inventaire(logement_id);
CREATE INDEX IF NOT EXISTS idx_sessions_statut ON sessions_inventaire(statut);

-- Table login_attempts : index pour la sécurité
CREATE INDEX IF NOT EXISTS idx_login_ip ON login_attempts(ip_address);
CREATE INDEX IF NOT EXISTS idx_login_timestamp ON login_attempts(attempted_at);

-- Table notifications : index pour les utilisateurs
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(is_read);

-- ============================================
-- VUES SQL POUR LES STATISTIQUES
-- ============================================

-- Vue : Planning avec détails complets
CREATE OR REPLACE VIEW v_planning_details AS
SELECT
    p.id,
    p.date_intervention,
    p.heure_intervention,
    p.statut,
    p.early_checkin,
    p.late_checkout,
    p.baby_bed,
    p.bonus,
    p.remarques,
    l.nom AS logement_nom,
    l.adresse AS logement_adresse,
    l.poid_menage AS logement_poids,
    c.nom AS conducteur_nom,
    c.prenom AS conducteur_prenom,
    fm1.nom AS femme_menage_1_nom,
    fm1.prenom AS femme_menage_1_prenom,
    fm2.nom AS femme_menage_2_nom,
    fm2.prenom AS femme_menage_2_prenom,
    lav.nom AS laverie_nom,
    lav.prenom AS laverie_prenom
FROM planning p
LEFT JOIN liste_logements l ON p.logement_id = l.id
LEFT JOIN intervenant c ON p.conducteur_id = c.id
LEFT JOIN intervenant fm1 ON p.femme_menage_1_id = fm1.id
LEFT JOIN intervenant fm2 ON p.femme_menage_2_id = fm2.id
LEFT JOIN intervenant lav ON p.laverie_id = lav.id;

-- Vue : Statistiques par logement
CREATE OR REPLACE VIEW v_stats_logements AS
SELECT
    l.id,
    l.nom,
    l.adresse,
    COUNT(p.id) AS nb_interventions,
    SUM(CASE WHEN p.statut = 'Fait' THEN 1 ELSE 0 END) AS nb_interventions_terminees,
    SUM(CASE WHEN p.statut = 'A Faire' THEN 1 ELSE 0 END) AS nb_interventions_a_faire,
    SUM(CASE WHEN p.bonus = 1 THEN 1 ELSE 0 END) AS nb_bonus,
    COALESCE(SUM(comp.montant), 0) AS ca_total
FROM liste_logements l
LEFT JOIN planning p ON l.id = p.logement_id
LEFT JOIN comptabilite comp ON l.id = comp.logement_id AND comp.type = 'CA'
GROUP BY l.id, l.nom, l.adresse;

-- Vue : Statistiques par intervenant
CREATE OR REPLACE VIEW v_stats_intervenants AS
SELECT
    i.id,
    i.nom,
    i.prenom,
    i.email,
    COUNT(DISTINCT CASE WHEN p.conducteur_id = i.id THEN p.id END) AS nb_conducteur,
    COUNT(DISTINCT CASE WHEN p.femme_menage_1_id = i.id THEN p.id END) AS nb_fm1,
    COUNT(DISTINCT CASE WHEN p.femme_menage_2_id = i.id THEN p.id END) AS nb_fm2,
    COUNT(DISTINCT CASE WHEN p.laverie_id = i.id THEN p.id END) AS nb_laverie,
    COALESCE(SUM(comp.montant), 0) AS remuneration_totale
FROM intervenant i
LEFT JOIN planning p ON (
    p.conducteur_id = i.id OR
    p.femme_menage_1_id = i.id OR
    p.femme_menage_2_id = i.id OR
    p.laverie_id = i.id
)
LEFT JOIN comptabilite comp ON i.id = comp.intervenant_id
GROUP BY i.id, i.nom, i.prenom, i.email;

-- Vue : Bilan comptable mensuel
CREATE OR REPLACE VIEW v_bilan_mensuel AS
SELECT
    DATE_FORMAT(date, '%Y-%m') AS mois,
    SUM(CASE WHEN type = 'CA' THEN montant ELSE 0 END) AS ca,
    SUM(CASE WHEN type = 'Charge' THEN montant ELSE 0 END) AS charges,
    SUM(CASE WHEN type = 'CA' THEN montant ELSE -montant END) AS resultat
FROM comptabilite
GROUP BY DATE_FORMAT(date, '%Y-%m')
ORDER BY mois DESC;

-- Vue : Planning du jour
CREATE OR REPLACE VIEW v_planning_jour AS
SELECT * FROM v_planning_details
WHERE date_intervention = CURDATE()
ORDER BY heure_intervention ASC;

-- Vue : Interventions non affectées
CREATE OR REPLACE VIEW v_interventions_non_affectees AS
SELECT
    p.*,
    l.nom AS logement_nom
FROM planning p
LEFT JOIN liste_logements l ON p.logement_id = l.id
WHERE (
    p.conducteur_id IS NULL OR
    p.femme_menage_1_id IS NULL
)
AND p.statut = 'A Faire'
AND p.date_intervention >= CURDATE()
ORDER BY p.date_intervention ASC;

-- Vue : Inventaire actuel par logement
CREATE OR REPLACE VIEW v_inventaire_actuel AS
SELECT
    l.id AS logement_id,
    l.nom AS logement_nom,
    s.id AS session_id,
    s.date_debut,
    s.date_fin,
    COUNT(o.id) AS nb_objets,
    COALESCE(SUM(o.valeur * o.quantite), 0) AS valeur_totale
FROM liste_logements l
LEFT JOIN sessions_inventaire s ON l.id = s.logement_id
LEFT JOIN inventaire_objets o ON s.id = o.session_id
WHERE s.statut = 'validee'
AND s.id = (
    SELECT MAX(s2.id)
    FROM sessions_inventaire s2
    WHERE s2.logement_id = l.id
    AND s2.statut = 'validee'
)
GROUP BY l.id, l.nom, s.id, s.date_debut, s.date_fin;

-- ============================================
-- PROCÉDURES STOCKÉES UTILES (optionnel)
-- ============================================

DELIMITER //

-- Procédure : Générer les statistiques du mois
CREATE PROCEDURE IF NOT EXISTS sp_stats_mois(IN p_annee INT, IN p_mois INT)
BEGIN
    SELECT
        COUNT(*) AS nb_interventions,
        SUM(CASE WHEN statut = 'Fait' THEN 1 ELSE 0 END) AS nb_terminees,
        COUNT(DISTINCT logement_id) AS nb_logements,
        COUNT(DISTINCT conducteur_id) AS nb_conducteurs
    FROM planning
    WHERE YEAR(date_intervention) = p_annee
    AND MONTH(date_intervention) = p_mois;
END //

DELIMITER ;

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

-- ============================================
-- NOTES D'UTILISATION
-- ============================================

/*
Pour utiliser ce script :

1. Exécuter ce fichier sur votre base de données MySQL
2. Les indexes seront créés automatiquement
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

*/
