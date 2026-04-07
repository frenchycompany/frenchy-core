<?php
// Activer l'affichage des erreurs pour le débogage


// Traitement du formulaire de réservation
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $prenom     = $conn->real_escape_string($_POST['prenom']);
    $nom        = $conn->real_escape_string($_POST['nom']);
    $telephone  = $conn->real_escape_string($_POST['telephone']);
    $email      = $conn->real_escape_string($_POST['email']);
    $logement_id= $conn->real_escape_string($_POST['logement_id']); // On récupère ici l'ID du logement
    $ville      = $conn->real_escape_string($_POST['ville']);
    $date_checkin  = $conn->real_escape_string($_POST['date_checkin']);
    $date_checkout = $conn->real_escape_string($_POST['date_checkout']);

    // Vérifier que le numéro de téléphone commence par '+'
    if (strpos($telephone, '+') !== 0) {
        echo "<div class='alert alert-warning'>⚠️ Le numéro de téléphone doit commencer par un '+'.</div>";
    } elseif (!empty($prenom) && !empty($nom) && !empty($telephone) && !empty($logement_id) 
              && !empty($ville) && !empty($date_checkin) && !empty($date_checkout)) {

        // Insérer la réservation en utilisant logement_id
        $sql = "INSERT INTO reservation (
                    prenom, nom, telephone, email, logement_id, date_arrivee, date_depart, ville, auto_replied
                ) VALUES (
                    '$prenom', '$nom', '$telephone', '$email', '$logement_id',
                    '$date_checkin', '$date_checkout', '$ville', 0
                )";
        if ($conn->query($sql) === TRUE) {
            echo "<div class='alert alert-success'>Réservation enregistrée avec succès.</div>";
        } else {
            echo "<div class='alert alert-danger'>Erreur : " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='alert alert-warning'>Tous les champs marqués d'une * sont obligatoires.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Enregistrer une réservation</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h2>Enregistrer une réservation</h2>
    <form method="POST" action="">
        <div class="form-group">
            <label>Prénom *</label>
            <input type="text" name="prenom" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Nom *</label>
            <input type="text" name="nom" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Téléphone * (doit commencer par un '+')</label>
            <input type="text" name="telephone" id="telephone"
                   pattern="^\+.*$" title="Le numéro doit commencer par un +." 
                   class="form-control" required>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control">
        </div>

        <!-- Nouveau menu déroulant pour choisir le logement (via son ID) -->
        <div class="form-group">
            <label>Logement réservé *</label>
            <select name="logement_id" class="form-control" required>
                <option value="">-- Sélectionner un logement --</option>
                <?php
                // Récupérer la liste des logements depuis la table liste_logements
                $sql_logements = "SELECT id, nom_du_logement FROM liste_logements ORDER BY nom_du_logement ASC";
                $result_logements = $conn->query($sql_logements);
                if ($result_logements && $result_logements->num_rows > 0) {
                    while ($row_l = $result_logements->fetch_assoc()) {
                        // On stocke dans "value" l'ID du logement
                        echo '<option value="'.htmlspecialchars($row_l['id']).'">'
                             .htmlspecialchars($row_l['nom_du_logement']).'</option>';
                    }
                } else {
                    echo '<option value="">Aucun logement disponible</option>';
                }
                ?>
            </select>
        </div>

        <div class="form-group">
            <label>Ville *</label>
            <input type="text" name="ville" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Date Check-in *</label>
            <input type="date" name="date_checkin" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Date Check-out *</label>
            <input type="date" name="date_checkout" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
    </form>
</div>

</body>
</html>
