<?php
include '../config.php';

try {
    // Supprimer les anciennes entrées pour éviter les doublons
    $conn->exec("DELETE FROM comptabilite");

    // Insérer les CA
    $stmtCA = $conn->prepare("
        INSERT INTO comptabilite (type, source_type, source_id, montant, date_comptabilisation, description)
        SELECT
            'CA' AS type,
            'intervention' AS source_type,
            p.id AS source_id,
            l.prix_vente_menage AS montant,
            p.date AS date_comptabilisation,
            CONCAT('CA généré pour l\'intervention ID ', p.id) AS description
        FROM planning p
        LEFT JOIN liste_logements l ON p.logement_id = l.id
        WHERE p.statut IN ('À Faire', 'Fait')
    ");
    $stmtCA->execute();

    // Insérer les Charges (Conducteur)
    $stmtConducteur = $conn->prepare("
        INSERT INTO comptabilite (type, source_type, source_id, intervenant_id, montant, date_comptabilisation, description)
        SELECT
            'Charge' AS type,
            'intervention' AS source_type,
            p.id AS source_id,
            p.conducteur AS intervenant_id,
            r_conducteur.valeur AS montant,
            p.date AS date_comptabilisation,
            CONCAT('Charge conducteur pour l\'intervention ID ', p.id) AS description
        FROM planning p
        LEFT JOIN role r_conducteur ON r_conducteur.role = 'conducteur'
        WHERE p.statut IN ('Fait', 'Vérifier') 
        AND p.conducteur IS NOT NULL 
        AND EXISTS (SELECT 1 FROM intervenant i WHERE i.id = p.conducteur)
    ");
    $stmtConducteur->execute();

    // Insérer les Charges (Femme de ménage 1)
    $stmtFM1 = $conn->prepare("
        INSERT INTO comptabilite (type, source_type, source_id, intervenant_id, montant, date_comptabilisation, description)
        SELECT
            'Charge' AS type,
            'intervention' AS source_type,
            p.id AS source_id,
            p.femme_de_menage_1 AS intervenant_id,
            (r_femme_de_menage.valeur * l.poid_menage) AS montant,
            p.date AS date_comptabilisation,
            CONCAT('Charge femme de ménage 1 pour l\'intervention ID ', p.id) AS description
        FROM planning p
        LEFT JOIN role r_femme_de_menage ON r_femme_de_menage.role = 'Femme de menage'
        LEFT JOIN liste_logements l ON l.id = p.logement_id
        WHERE p.statut IN ('Fait', 'Vérifier') 
        AND p.femme_de_menage_1 IS NOT NULL 
        AND EXISTS (SELECT 1 FROM intervenant i WHERE i.id = p.femme_de_menage_1)
    ");
    $stmtFM1->execute();

    // Insérer les Charges (Femme de ménage 2)
    $stmtFM2 = $conn->prepare("
        INSERT INTO comptabilite (type, source_type, source_id, intervenant_id, montant, date_comptabilisation, description)
        SELECT
            'Charge' AS type,
            'intervention' AS source_type,
            p.id AS source_id,
            p.femme_de_menage_2 AS intervenant_id,
            (r_femme_de_menage.valeur * l.poid_menage) AS montant,
            p.date AS date_comptabilisation,
            CONCAT('Charge femme de ménage 2 pour l\'intervention ID ', p.id) AS description
        FROM planning p
        LEFT JOIN role r_femme_de_menage ON r_femme_de_menage.role = 'Femme de menage'
        LEFT JOIN liste_logements l ON l.id = p.logement_id
        WHERE p.statut IN ('Fait', 'Vérifier') 
        AND p.femme_de_menage_2 IS NOT NULL 
        AND EXISTS (SELECT 1 FROM intervenant i WHERE i.id = p.femme_de_menage_2)
    ");
    $stmtFM2->execute();

    // Insérer les Charges fixes (Laverie - Coût fixe uniquement)
    $stmtLaverieFixe = $conn->prepare("
        INSERT INTO comptabilite (type, source_type, source_id, montant, date_comptabilisation, description)
        SELECT
            'Charge' AS type,
            'intervention' AS source_type,
            p.id AS source_id,
            3 AS montant,  -- Coût fixe laverie
            p.date AS date_comptabilisation,
            CONCAT('Charge fixe laverie pour l\'intervention ID ', p.id) AS description
        FROM planning p
        WHERE p.statut IN ('Fait', 'Vérifier')
    ");
    $stmtLaverieFixe->execute();

    // Insérer les Charges variables (Laverie - Avec intervenant)
    $stmtLaverieVar = $conn->prepare("
        INSERT INTO comptabilite (type, source_type, source_id, intervenant_id, montant, date_comptabilisation, description)
        SELECT
            'Charge' AS type,
            'intervention' AS source_type,
            p.id AS source_id,
            p.laverie AS intervenant_id,
            r_laverie.valeur AS montant,
            p.date AS date_comptabilisation,
            CONCAT('Charge variable laverie pour l\'intervention ID ', p.id) AS description
        FROM planning p
        LEFT JOIN role r_laverie ON r_laverie.role = 'laverie'
        WHERE p.statut IN ('Fait', 'Vérifier') 
        AND p.laverie IS NOT NULL 
        AND EXISTS (SELECT 1 FROM intervenant i WHERE i.id = p.laverie)
    ");
    $stmtLaverieVar->execute();

    // Redirection après mise à jour
    header("Location: comptabilite.php");
    exit;

} catch (PDOException $e) {
    echo "Erreur lors de la mise à jour comptable : " . $e->getMessage();
}
