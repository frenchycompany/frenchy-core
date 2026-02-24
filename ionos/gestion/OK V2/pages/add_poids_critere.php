<?php
include '../config.php'; // Connexion à la base de données

$critere = filter_input(INPUT_POST, 'critere', FILTER_SANITIZE_STRING);
$valeur = filter_input(INPUT_POST, 'valeur', FILTER_VALIDATE_FLOAT);
$temps_par_unite = filter_input(INPUT_POST, 'temps_par_unite', FILTER_VALIDATE_FLOAT);

if (!$critere || $valeur === false || $temps_par_unite === false) {
    echo "Erreur : Les champs critère, valeur ou temps sont invalides.";
    exit;
}

// Vérifier si le critère existe déjà
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM poids_criteres WHERE critere = :critere");
    $stmt->execute(['critere' => $critere]);
    $exists = $stmt->fetchColumn();

    if ($exists > 0) {
        echo "Erreur : Ce critère existe déjà dans la table poids_criteres.";
        exit;
    }

    // Ajouter le critère dans la table poids_criteres
    $stmt = $conn->prepare("INSERT INTO poids_criteres (critere, valeur, temps_par_unite) VALUES (:critere, :valeur, :temps_par_unite)");
    $stmt->execute([
        'critere' => $critere,
        'valeur' => $valeur,
        'temps_par_unite' => $temps_par_unite
    ]);

    // Ajouter le critère comme colonne dans description_logements
    $columnName = preg_replace('/[^a-zA-Z0-9_]/', '_', $critere); // Sécuriser le nom de la colonne
    $sql = "ALTER TABLE description_logements ADD COLUMN `$columnName` FLOAT DEFAULT 0 COMMENT 'Critère ajouté automatiquement'";
    $conn->exec($sql);

    echo "Le critère '$critere' a été ajouté avec succès et intégré à la table description_logements.";
} catch (PDOException $e) {
    echo "Erreur lors de l'ajout du critère : " . $e->getMessage();
}
