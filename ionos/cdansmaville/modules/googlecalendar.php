<?php
require_once dirname(__DIR__) . '/db/connection.php';

// Vérifier si un client est sélectionné
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

if ($client_id) {
    // Récupérer le lien du calendrier Google
    $stmt = $conn->prepare("SELECT agenda FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $agenda = $stmt->fetchColumn();

    if (!empty($agenda)): ?>
        <section id="rendez-vous" data-aos="fade-left">
            <h2>Planifiez un rendez-vous avec nous</h2>
            <iframe src="<?php echo htmlspecialchars($agenda); ?>" width="100%" height="600px" frameborder="0"></iframe>
        </section>
    <?php endif;
}
?>
