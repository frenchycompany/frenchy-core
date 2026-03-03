<?php
require_once '../includes/error_handler.php';  // Gestion centralisée des erreurs
require_once '../includes/phone.php';  // Fonction de normalisation téléphone centralisée

// Alias pour compatibilité
function formatPhoneNumber($phone) {
    return normalize_phone($phone);
}

$feedback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fichier'])) {
    $fichier = $_FILES['fichier']['tmp_name'];

    if (!file_exists($fichier)) {
        $feedback = "<div class='alert alert-danger'>Fichier introuvable.</div>";
    } else {
        $handle = fopen($fichier, 'r');
        // Lire l'en-tête du CSV
        $header = fgetcsv($handle, 1000, ',');
        if (!$header) {
            $feedback = "<div class='alert alert-danger'>Impossible de lire l'en-tête du fichier CSV.</div>";
        } else {
            $importees = 0;
            $ignorees = 0;

            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $row = array_map(function($value) {
                    return trim((string)$value);
                }, $row);
                
                while(count($row) > count($header) && end($row) === "") {
                    array_pop($row);
                }

                if (count($row) !== count($header)) {
                    error_log("Ligne ignorée (nombre de colonnes différent): " . print_r($row, true));
                    $ignorees++;
                    continue;
                }

                $data = array_combine($header, $row);

                $telephone = formatPhoneNumber($data['Téléphone'] ?? '');
                $reference = $data['Référence'] ?? '';

                // Vérifier si le numéro existe déjà - avec requête préparée
                $stmt_check = $conn->prepare("SELECT id FROM campagne_immo WHERE telephone = ?");
                $stmt_check->bind_param('s', $telephone);
                $stmt_check->execute();
                $check_result = $stmt_check->get_result();
                if ($check_result && $check_result->num_rows > 0) {
                    $stmt_check->close();
                    $ignorees++;
                    continue;
                }
                $stmt_check->close();

                // Préparer les autres champs (sans échappement)
                $nom = $data['Nom diffuseur'] ?? '';
                $prenom = $data['Identité'] ?? '';
                $email = $data['Email'] ?? '';
                $commentaire = $data['Commentaire'] ?? '';
                $titre = $data['Titre'] ?? '';
                $code_postal = $data['Code postal'] ?? '';
                $ville = $data['Ville'] ?? '';

                // Insertion avec requête préparée
                $stmt_insert = $conn->prepare("
                    INSERT INTO campagne_immo (
                        nom, prenom, email, telephone, reference, commentaire, titre, code_postal, ville
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )
                ");
                $stmt_insert->bind_param('sssssssss', $nom, $prenom, $email, $telephone, $reference, $commentaire, $titre, $code_postal, $ville);

                if ($stmt_insert->execute()) {
                    $importees++;
                } else {
                    error_log("Erreur lors de l'insertion pour la référence $reference: " . $stmt_insert->error);
                    $ignorees++;
                }
                $stmt_insert->close();
            }
            fclose($handle);

            $feedback = "<div class='alert alert-info'><strong>$importees prospect(s)</strong> importé(s) avec succès.<br>$ignorees ligne(s) ignorée(s).</div>";
        }
    }
}
?>


<div class="container mt-4">
    <h3>Importer des prospects (Campagne Immo)</h3>
    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="fichier">Fichier CSV :</label>
            <input type="file" name="fichier" id="fichier" class="form-control-file" required>
        </div>
        <button type="submit" class="btn btn-primary">Importer</button>
    </form>
    <div class="mt-3">
        <?= $feedback ?>
    </div>
</div>

