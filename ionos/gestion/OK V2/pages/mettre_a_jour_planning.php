<?php
// mettre_a_jour_planning.php
include '../config.php'; // Inclut la configuration de la base de données

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids'])) {
    foreach ($_POST['ids'] as $id) {
        $nombre_de_personnes = (int) $_POST["nombre_de_personnes_$id"];
        $statut = $_POST["statut_$id"];
        $note_sur_10 = isset($_POST["note_sur_10_$id"]) ? (float) $_POST["note_sur_10_$id"] : null;

        // Récupérer les IDs des intervenants (conducteur, femme_de_ménage, laverie)
        $conducteur = !empty($_POST["conducteur_$id"]) ? (int) $_POST["conducteur_$id"] : null;
        $femme_de_menage_1 = !empty($_POST["femme_de_menage_1_$id"]) ? (int) $_POST["femme_de_menage_1_$id"] : null;
        $femme_de_menage_2 = !empty($_POST["femme_de_menage_2_$id"]) ? (int) $_POST["femme_de_menage_2_$id"] : null;
        $laverie = !empty($_POST["laverie_$id"]) ? (int) $_POST["laverie_$id"] : null;

        // Vérifier que le statut est valide
        $statuts_valides = ['À Faire', 'Fait', 'À Vérifier', 'Vérifié'];
        if (!in_array($statut, $statuts_valides, true)) {
            continue; // Ignore cette ligne si le statut n'est pas valide
        }

        // Mettre à jour l'intervention dans la base de données
        $stmt = $conn->prepare("
            UPDATE planning 
            SET 
                nombre_de_personnes = ?, 
                statut = ?, 
                note_sur_10 = ?, 
                conducteur = ?, 
                femme_de_menage_1 = ?, 
                femme_de_menage_2 = ?, 
                laverie = ? 
            WHERE id = ?
        ");
        $stmt->execute([
            $nombre_de_personnes, 
            $statut, 
            $note_sur_10, 
            $conducteur, 
            $femme_de_menage_1, 
            $femme_de_menage_2, 
            $laverie, 
            $id
        ]);
    }

    // Redirection après la mise à jour
    $date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING) ?? date('Y-m-d');
    header("Location: editer_planning.php?date=" . urlencode($date));
    exit;
}
?>
