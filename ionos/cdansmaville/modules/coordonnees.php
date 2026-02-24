<?php
require_once dirname(__DIR__) . '/db/connection.php';

// Vérifier si un client est sélectionné
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

if ($client_id) {
    // Récupérer les coordonnées du client
    $stmt = $conn->prepare("SELECT nom, adresse, contact_nom, contact_tel, contact_email FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($client): ?>
        <section id="coordonnees">
            <h2>Nos Coordonnées</h2>
            <p><strong>Nom :</strong> <?php echo htmlspecialchars($client['nom']); ?></p>
            <p><strong>Adresse :</strong> <?php echo htmlspecialchars($client['adresse']); ?></p>
            <p><strong>Contact :</strong> <?php echo htmlspecialchars($client['contact_nom']); ?></p>
            <p><strong>Téléphone :</strong> <?php echo htmlspecialchars($client['contact_tel']); ?></p>
            <p><strong>Email :</strong> <a href="mailto:<?php echo htmlspecialchars($client['contact_email']); ?>">
                <?php echo htmlspecialchars($client['contact_email']); ?></a></p>
        </section>
    <?php endif;
}
?>
