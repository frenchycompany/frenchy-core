<?php
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=UTF-8');

require 'db/connection.php'; // Connexion à la base de données

try {
    // Vérifier si les données nécessaires sont présentes
    if (
        !isset($_POST['id']) || 
        !isset($_POST['titre']) || 
        !isset($_POST['date_debut'])
    ) {
        echo json_encode(['success' => false, 'message' => 'Données incomplètes.']);
        exit;
    }

    // Récupérer les données du formulaire
    $id = $_POST['id'];
    $titre = $_POST['titre'];
    $description = $_POST['description'] ?? null;
    $date_debut = $_POST['date_debut'];
    $date_fin = $_POST['date_fin'] ?? null;
    $heure_debut = $_POST['heure_debut'] ?? null;
    $heure_fin = $_POST['heure_fin'] ?? null;
    $nom_lieu = $_POST['nom_lieu'] ?? null;
    $adresse_lieu = $_POST['adresse_lieu'] ?? null;
    $ville = $_POST['ville'] ?? null;
    $code_postal = $_POST['code_postal'] ?? null;
    $contact_nom = $_POST['contact_nom'] ?? null;
    $contact_telephone = $_POST['contact_telephone'] ?? null;
    $contact_email = $_POST['contact_email'] ?? null;
    $site_web = $_POST['site_web'] ?? null;
    $prix = $_POST['prix'] ?? null;
    $tags = $_POST['tags'] ?? null;
    $source_texte = $_POST['source_texte'] ?? null;

    // Préparer et exécuter la requête de mise à jour
    $stmt = $conn->prepare("
        UPDATE structured_events 
        SET titre = ?, description = ?, date_debut = ?, date_fin = ?, heure_debut = ?, heure_fin = ?, 
            nom_lieu = ?, adresse_lieu = ?, ville = ?, code_postal = ?, contact_nom = ?, 
            contact_telephone = ?, contact_email = ?, site_web = ?, prix = ?, tags = ?, source_texte = ? 
        WHERE id = ?
    ");

    $stmt->execute([
        $titre, $description, $date_debut, $date_fin, $heure_debut, $heure_fin, 
        $nom_lieu, $adresse_lieu, $ville, $code_postal, $contact_nom, 
        $contact_telephone, $contact_email, $site_web, $prix, $tags, $source_texte, $id
    ]);

    // Vérifier si la mise à jour a été effectuée
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Événement mis à jour avec succès.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Aucune modification détectée.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
