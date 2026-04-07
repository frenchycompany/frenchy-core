<?php


// Récupérer l'ID de la réservation dans l'URL
if (!isset($_GET['id'])) {
    die("ID de réservation manquant.");
}

$res_id = (int)$_GET['id'];

// Récupérer les infos de la réservation
$sql = "SELECT r.id, r.prenom, r.nom, r.telephone, r.ville,
               r.date_arrivee, r.date_depart,
               r.dep_sent,
               l.nom_du_logement AS logement_nom
        FROM reservation r
        LEFT JOIN liste_logements l ON r.logement_id = l.id
        WHERE r.id = $res_id";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();

    // Vérifier si le SMS de départ est déjà envoyé
    if ($row['dep_sent'] == 1) {
        echo "<p>Le SMS de départ a déjà été envoyé pour cette réservation.</p>";
        exit;
    }

    $telephone    = $row['telephone'];
    $prenom       = $row['prenom'];
    $logement_nom = $row['logement_nom'];
    $ville        = $row['ville'];

    // Construire le message de départ
    // Adaptable selon tes besoins
    $message = "Bonjour $prenom,\n";
    $message .= "Merci d'avoir séjourné à $logement_nom (ville: $ville). ";
    $message .= "Nous espérons que tout s'est bien passé. ";
    $message .= "N'hésitez pas à nous laisser un avis ou à nous recontacter si besoin.\n";
    $message .= "À bientôt !";

    // Insérer dans sms_outbox
    $insert_sql = "INSERT INTO sms_outbox (receiver, message, modem, status)
                   VALUES ('$telephone', '$message', '/dev/ttyUSB0', 'pending')";

    if ($conn->query($insert_sql) === TRUE) {
        // Mettre à jour la réservation pour dep_sent = 1
        $update_sql = "UPDATE reservation SET dep_sent = 1 WHERE id = $res_id";
        $conn->query($update_sql);

        echo "<p>SMS de départ inséré dans la file d'envoi pour la réservation #$res_id.</p>";
        echo '<a href="reservation_list.php">Retour à la liste des réservations</a>';
    } else {
        echo "<p>Erreur lors de l'insertion du SMS : " . $conn->error . "</p>";
    }
} else {
    echo "<p>Réservation introuvable.</p>";
}
