<?php
require_once '../db/connection.php';

// Vérifier si un client est sélectionné
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

if ($client_id) {
    // Récupérer le code de la visite virtuelle
    $stmt = $conn->prepare("SELECT matterport FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $code_visite = $stmt->fetchColumn();

    if (!empty($code_visite)): ?>
        <section id="visite-virtuelle">
            <h2>Visite Virtuelle</h2>
            <iframe width="100%" height="500" src="https://my.matterport.com/show/?m=<?php echo htmlspecialchars($code_visite); ?>" 
                    frameborder="0" allowfullscreen allow="autoplay; fullscreen; web-share; xr-spatial-tracking;"></iframe>
        </section>
    <?php endif;
}
?>
