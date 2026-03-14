<?php
include '../config.php'; // Connexion à la base de données
header('Content-Type: application/json; charset=utf-8');

// Valider l'ID du logement passé en paramètre
$logement_id = filter_input(INPUT_GET, 'logement_id', FILTER_VALIDATE_INT);

if (!$logement_id) {
    echo json_encode(['error' => 'ID du logement invalide']);
    exit;
}

try {
    // Detecter les colonnes disponibles dans FC_proprietaires
    $extraCols = '';
    try {
        $cols = array_column($conn->query("SHOW COLUMNS FROM FC_proprietaires")->fetchAll(), 'Field');
        $optional = [
            'societe' => 'proprietaire_societe',
            'siret' => 'proprietaire_siret',
            'rib_iban' => 'proprietaire_rib_iban',
            'rib_bic' => 'proprietaire_rib_bic',
            'rib_banque' => 'proprietaire_rib_banque',
            'commission' => 'proprietaire_commission',
        ];
        foreach ($optional as $col => $alias) {
            if (in_array($col, $cols)) {
                $extraCols .= ", p.{$col} AS {$alias}";
            }
        }
    } catch (PDOException $e2) {
        // Table FC_proprietaires n'existe pas encore
    }

    // Récupérer les données du logement avec les infos propriétaire
    $sql = "
        SELECT l.*,
               p.nom AS proprietaire_nom,
               p.prenom AS proprietaire_prenom,
               p.email AS proprietaire_email,
               p.telephone AS proprietaire_telephone,
               p.adresse AS proprietaire_adresse
               {$extraCols}
        FROM liste_logements l
        LEFT JOIN FC_proprietaires p ON l.proprietaire_id = p.id
        WHERE l.id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$logement_id]);
    $logement_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Vérifier si des données ont été récupérées
    if (!$logement_data) {
        echo json_encode(['error' => 'Aucun logement trouvé avec cet ID.']);
        exit;
    }

    // Retourner les données sous forme JSON
    echo json_encode($logement_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('get_logement_infos.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Erreur lors du chargement des donnees du logement.']);
    exit;
} catch (Exception $e) {
    error_log('get_logement_infos.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Erreur lors du chargement des donnees du logement.']);
    exit;
}
