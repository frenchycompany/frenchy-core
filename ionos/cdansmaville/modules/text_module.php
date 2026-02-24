<?php
require_once __DIR__ . '/../db/connection.php';

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

if ($client_id) {
    $stmt = $conn->prepare("SELECT * FROM client_texts WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $texts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<section class="text-content">
    <?php if (!empty($texts)): ?>
        <?php foreach ($texts as $text): ?>
            <article>
                <h2><?php echo htmlspecialchars($text['section']); ?></h2>
                <div><?php echo $text['content']; ?></div>
            </article>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Aucun contenu pour l’instant.</p>
    <?php endif; ?>
</section>
