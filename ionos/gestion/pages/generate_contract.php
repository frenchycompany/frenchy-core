<?php
include '../config.php'; // Inclut la configuration de la base de données

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ID du modèle de contrat sélectionné
        if (empty($_POST['template_id']) || !is_numeric($_POST['template_id'])) {
            die("Erreur : Vous devez sélectionner un modèle de contrat.");
        }
        $template_id = (int) $_POST['template_id'];

        // Récupérer le modèle de contrat depuis la table `contract_templates`
        $stmt = $conn->prepare("SELECT content FROM contract_templates WHERE id = :id");
        $stmt->execute([':id' => $template_id]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($template) {
            $contract_content = $template['content'];

            // Remplacement des balises par les données du formulaire
            foreach ($_POST as $field_name => $field_value) {
                $contract_content = str_replace("{{{$field_name}}}", htmlspecialchars($field_value), $contract_content);
            }

            // Vérifier si le champ `logement_id` est présent et valide
            if (empty($_POST['logement_id']) || !is_numeric($_POST['logement_id'])) {
                die("Erreur : Le champ 'logement_id' est requis et doit être valide.");
            }
            $logement_id = (int) $_POST['logement_id'];

            // Créer un nom de fichier unique pour le contrat généré
            $timestamp = time();
            $file_name = "../generated_contracts/contract_{$timestamp}.html"; // Générer un fichier HTML

            // Sauvegarder le contenu généré dans un fichier HTML
            if (!is_dir("../generated_contracts")) {
                mkdir("../generated_contracts", 0755, true); // Créer le dossier si inexistant
            }
            file_put_contents($file_name, $contract_content);

            // Sauvegarder les informations du contrat dans la table `generated_contracts`
            $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;
            $stmt = $conn->prepare("
                INSERT INTO generated_contracts (user_id, logement_id, file_path)
                VALUES (:user_id, :logement_id, :file_path)
            ");
            $stmt->execute([
                ':user_id' => $user_id,
                ':logement_id' => $logement_id,
                ':file_path' => $file_name
            ]);

            // Retourner un message de succès avec un lien pour télécharger le contrat
            echo "<div style='text-align: center; margin-top: 50px;'>";
            echo "<h2>Le contrat a été généré avec succès !</h2>";
            echo "<a href='{$file_name}' download class='btn btn-primary'>Télécharger le contrat</a>";
            echo "</div>";
        } else {
            echo "Erreur : Modèle de contrat introuvable.";
        }
    } catch (PDOException $e) {
        echo "Erreur : " . $e->getMessage();
    }
} else {
    echo "Méthode non autorisée.";
}
