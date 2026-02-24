<?php
// Affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connexion à la base + en-tête
require_once '../includes/db.php';
require_once '../includes/header.php';

// Vérifier que l'ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>ID de réservation manquant.</div></div>";
    exit;
}

$id = intval($_GET['id']);

// Récupération des infos réservation + logement
$sql = "SELECT r.*, l.nom_du_logement 
        FROM reservation r
        LEFT JOIN liste_logements l ON r.logement_id = l.id
        WHERE r.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<div class='container mt-4'><div class='alert alert-warning'>Aucune réservation trouvée pour cet ID.</div></div>";
    require_once '../includes/footer.php';
    exit;
}

$data = $result->fetch_assoc();
?>

<div class="container mt-4">
    <h2>Détail de la réservation n°<?= $data['id'] ?></h2>
    <table class="table table-bordered table-striped mt-3">
        <tbody>
            <tr><th>Prénom</th><td><?= htmlspecialchars($data['prenom']) ?></td></tr>
            <tr><th>Nom</th><td><?= htmlspecialchars($data['nom']) ?></td></tr>
            <tr><th>Téléphone</th><td><?= htmlspecialchars($data['telephone']) ?></td></tr>
            <tr><th>Email</th><td><?= htmlspecialchars($data['email']) ?></td></tr>
            <tr><th>Logement</th><td><?= htmlspecialchars($data['nom_du_logement'] ?? 'Inconnu') ?></td></tr>
            <tr><th>Date d'arrivée</th><td><?= htmlspecialchars($data['date_arrivee']) ?></td></tr>
            <tr><th>Date de départ</th><td><?= htmlspecialchars($data['date_depart']) ?></td></tr>
            <tr><th>Ville</th><td><?= htmlspecialchars($data['ville']) ?></td></tr>
            <tr><th>Date de création</th><td><?= htmlspecialchars($data['created_at']) ?></td></tr>
            <tr><th>SMS de départ</th>
                <td>
                    <?php
                        echo ($data['start_sent'] == 1)
                            ? "<span class='text-success'>✔️ Envoyé</span>"
                            : "<span class='text-danger'>Non envoyé</span>";
                    ?>
                </td>
            </tr>
        </tbody>
    </table>

    <a href="reservation_list.php" class="btn btn-secondary">← Retour à la liste</a>
</div>

<?php require_once '../includes/footer.php'; ?>
