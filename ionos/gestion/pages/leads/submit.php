<?php
// Activer les erreurs pour débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure la configuration
include '../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);

    try {
        // Vérifier si l'email existe déjà
        $stmt = $conn->prepare("SELECT COUNT(*) FROM leads WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $exists = $stmt->fetchColumn();

        if (!$exists) {
            // Insérer dans la base de données
            $stmt = $conn->prepare("INSERT INTO leads (name, email) VALUES (:name, :email)");
            $stmt->execute(['name' => $name, 'email' => $email]);
        }

        // Redirection vers le fichier à télécharger
        header("Location: guide_bienvenue.pdf");
        exit;
    } catch (PDOException $e) {
        error_log('submit.php: ' . $e->getMessage());
        echo "Une erreur interne est survenue.";
    }
}
?>
