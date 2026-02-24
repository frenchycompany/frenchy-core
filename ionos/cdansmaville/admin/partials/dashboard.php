<?php
// partials/dashboard.php
require_once '../db/connection.php';
try {
    // Récupérer toutes les statistiques en une seule requête
    $stmt = $conn->query("
        SELECT 
            (SELECT COUNT(*) FROM clients) AS totalClients, 
            (SELECT COUNT(*) FROM modules) AS totalModules, 
            (SELECT COUNT(*) FROM news) AS totalNews,
            (SELECT nom FROM clients ORDER BY id DESC LIMIT 1) AS lastClient,
            (SELECT nom FROM modules ORDER BY id DESC LIMIT 1) AS lastModule,
            (SELECT titre FROM news ORDER BY id DESC LIMIT 1) AS lastNews,
            (SELECT COUNT(*) FROM clients WHERE id NOT IN (SELECT DISTINCT client_id FROM client_content)) AS clientsWithoutContent
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>
<div>
    <h2>Statistiques Générales</h2>
    <div>
        <p>Total Clients : <?php echo $stats['totalClients']; ?></p>
        <p>Total Modules : <?php echo $stats['totalModules']; ?></p>
        <p>Total News : <?php echo $stats['totalNews']; ?></p>
    </div>
    <h3>Dernières Mises à Jour</h3>
    <div>
        <p>Dernier client ajouté : <?php echo $stats['lastClient'] ?? 'Aucun'; ?></p>
        <p>Dernier module ajouté : <?php echo $stats['lastModule'] ?? 'Aucun'; ?></p>
        <p>Dernière news publiée : <?php echo $stats['lastNews'] ?? 'Aucune'; ?></p>
    </div>
    <div>
        <?php if ($stats['clientsWithoutContent'] > 0): ?>
            <p><?php echo $stats['clientsWithoutContent']; ?> clients n'ont pas encore de contenu défini !</p>
        <?php else: ?>
            <p>Tous les clients ont du contenu défini.</p>
        <?php endif; ?>
    </div>
</div>
