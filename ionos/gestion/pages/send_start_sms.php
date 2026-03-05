<?php
require_once '../includes/error_handler.php';  // Gestion centralisée des erreurs

// Vérifier qu'un ID de réservation est bien fourni
if (!isset($_GET['id'])) {
    die("ID de reservation manquant.");
}

$res_id = (int) $_GET['id'];

// Récupérer les infos de la réservation - avec requête préparée
$stmt = $conn->prepare("SELECT r.id, r.prenom, r.nom, r.telephone, r.ville, r.start_sent,
               l.nom_du_logement AS logement_nom
        FROM reservation r
        LEFT JOIN liste_logements l ON r.logement_id = l.id
        WHERE r.id = ?");
$stmt->bind_param('i', $res_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $stmt->close();

    // Vérifier si le SMS a déjà été envoyé
    if ($row['start_sent'] == 1) {
        echo "<p>Le SMS de depart a deja ete envoye pour cette reservation.</p>";
        echo '<a href="reservation_list.php">Retour a la liste des reservations</a>';
        exit;
    }

    // Récupérer les variables (pas besoin d'échappement avec requêtes préparées)
    $telephone    = $row['telephone'];
    $prenom       = $row['prenom'];
    $logement_nom = $row['logement_nom'];
    $ville        = $row['ville'];

    // Construire le message sans accent
    $message  = "Bonjour $prenom, ";
    $message .= "Merci d'avoir reserve dans mon logement. ";
    $message .= "Avez vous besoin de quelque chose pour preparer votre arrivée ? ";
    $message .= "Raphael de Frenchy Conciergerie";

    // Insérer le SMS dans la table sms_outbox - avec requête préparée
    $stmt_insert = $conn->prepare("INSERT INTO sms_outbox (receiver, message, modem, status)
                   VALUES (?, ?, '/dev/ttyUSB0', 'pending')");
    $stmt_insert->bind_param('ss', $telephone, $message);

    if ($stmt_insert->execute()) {
        $stmt_insert->close();

        // Mettre a jour la reservation pour indiquer que le SMS de depart est envoye - avec requête préparée
        $stmt_update = $conn->prepare("UPDATE reservation SET start_sent = 1 WHERE id = ?");
        $stmt_update->bind_param('i', $res_id);
        $stmt_update->execute();
        $stmt_update->close();

        echo "<p>SMS de depart insere dans la file d'envoi pour la reservation #$res_id.</p>";
        echo '<a href="reservation_list.php">Retour a la liste des reservations</a>';
    } else {
        echo "<p>Erreur lors de l'insertion du SMS : " . $conn->error . "</p>";
    }
} else {
    echo "<p>Reservation introuvable.</p>";
}
