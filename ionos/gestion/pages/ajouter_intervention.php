<?php
// ajouter_intervention.php
ini_set('display_startup_errors', 1);

include '../config.php'; // Inclut la configuration de la base de données

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et validation des données du formulaire
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
    $nom_du_logement = filter_input(INPUT_POST, 'nom_du_logement', FILTER_SANITIZE_STRING);
    $nombre_de_personnes = filter_input(INPUT_POST, 'nombre_de_personnes', FILTER_VALIDATE_INT);
    $statut = filter_input(INPUT_POST, 'statut', FILTER_SANITIZE_STRING);
    $note_sur_10 = filter_input(INPUT_POST, 'note_sur_10', FILTER_VALIDATE_FLOAT);
    $conducteur = !empty($_POST['conducteur']) ? filter_input(INPUT_POST, 'conducteur', FILTER_SANITIZE_STRING) : null;
    $femme_de_menage_1 = !empty($_POST['femme_de_menage_1']) ? filter_input(INPUT_POST, 'femme_de_menage_1', FILTER_SANITIZE_STRING) : null;
    $femme_de_menage_2 = !empty($_POST['femme_de_menage_2']) ? filter_input(INPUT_POST, 'femme_de_menage_2', FILTER_SANITIZE_STRING) : null;
    $laverie = !empty($_POST['laverie']) ? filter_input(INPUT_POST, 'laverie', FILTER_SANITIZE_STRING) : null;
    $poid_menage = filter_input(INPUT_POST, 'poid_menage', FILTER_VALIDATE_FLOAT);

    // Vérification des champs obligatoires
    if (!$date || !$nom_du_logement || !$nombre_de_personnes || !$statut || !$poid_menage) {
        echo "Tous les champs obligatoires doivent être remplis correctement.";
        exit;
    }

    try {
        // Préparation et exécution de la requête d'insertion
        $stmt = $conn->prepare("
            INSERT INTO planning (date, nom_du_logement, nombre_de_personnes, statut, note_sur_10, conducteur, femme_de_menage_1, femme_de_menage_2, laverie, poid_menage)
            VALUES (:date, :nom_du_logement, :nombre_de_personnes, :statut, :note_sur_10, :conducteur, :femme_de_menage_1, :femme_de_menage_2, :laverie, :poid_menage)
        ");
        $stmt->execute([
            ':date' => $date,
            ':nom_du_logement' => $nom_du_logement,
            ':nombre_de_personnes' => $nombre_de_personnes,
            ':statut' => $statut,
            ':note_sur_10' => $note_sur_10,
            ':conducteur' => $conducteur,
            ':femme_de_menage_1' => $femme_de_menage_1,
            ':femme_de_menage_2' => $femme_de_menage_2,
            ':laverie' => $laverie,
            ':poid_menage' => $poid_menage
        ]);

        // Redirection après l'insertion
        header("Location: planning.php");
        exit;
    } catch (PDOException $e) {
        // Gestion des erreurs PDO de manière sécurisée
        error_log("Erreur lors de l'insertion des données : " . $e->getMessage());
        echo "Une erreur est survenue lors de l'insertion des données. Veuillez réessayer plus tard.";
    }
}
