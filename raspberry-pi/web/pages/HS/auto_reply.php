<?php
// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclusion de la connexion à la base de données
include '../includes/db.php';

/**
 * Fonction pour générer la réponse automatique.
 * Remplace {prenom} et {logement} dans le template par les valeurs fournies.
 */
function generer_reponse($template, $prenom, $logement) {
    $reponse = str_replace("{prenom}", $prenom, $template);
    $reponse = str_replace("{logement}", $logement, $reponse);
    return $reponse;
}

// Sélectionner les réservations non traitées
$sql = "SELECT r.id, r.prenom, r.logement, r.telephone, l.ref_scenario 
        FROM reservation r 
        LEFT JOIN logement l ON r.logement = l.nom
        WHERE r.auto_replied = 0";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reservation_id = $row['id'];
        $prenom         = $row['prenom'];
        $logement       = $row['logement'];
        $telephone      = $row['telephone'];
        $template       = $row['ref_scenario'];
        
        // Utiliser un message par défaut si aucun scénario n'est défini pour ce logement
        if(empty($template)) {
            $template = "Bonjour {prenom}, bienvenue dans {logement} !";
        }
        
        // Générer la réponse en remplaçant les variables
        $reponse = generer_reponse($template, $prenom, $logement);
        
        // Insérer la réponse dans la table sms_outbox pour envoi (modem par défaut : '/dev/ttyUSB0')
        $insert_sql = "INSERT INTO sms_outbox (receiver, message, modem, status) 
                       VALUES ('$telephone', '$reponse', '/dev/ttyUSB0', 'pending')";
        
        if ($conn->query($insert_sql) === TRUE) {
            // Mettre à jour la réservation pour marquer qu'une réponse automatique a été envoyée
            $update_sql = "UPDATE reservation SET auto_replied = 1 WHERE id = $reservation_id";
            $conn->query($update_sql);
            echo "Réservation $reservation_id traitée avec succès.<br>";
        } else {
            echo "Erreur lors du traitement de la réservation $reservation_id : " . $conn->error . "<br>";
        }
    }
} else {
    echo "Aucune réservation non traitée.<br>";
}
?>
