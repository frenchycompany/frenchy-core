<?php
require 'db/connection.php';
include 'menu.php';

// Récupérer toutes les promotions
try {
    $stmtPromotions = $conn->query("SELECT title, slug, description, end_date FROM promotions WHERE end_date >= CURDATE() ORDER BY start_date");
    $promotions = $stmtPromotions->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotions - Cdansmaville</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="hero">
        <div class="container">
            <h1>Promotions</h1>
            <p>Ne manquez pas nos offres exclusives.</p>
        </div>
    </header>

    <main>
        <div class="container">
            <section class="section-promotions">
                <h2>Offres en cours</h2>
                <ul>
                    <?php foreach ($promotions as $promotion): ?>
                        <li>
                            <a href="promotion.php?slug=<?php echo htmlspecialchars($promotion['slug']); ?>">
                                <strong><?php echo htmlspecialchars($promotion['title']); ?></strong>
                            </a>
                            <p><?php echo htmlspecialchars($promotion['description']); ?></p>
                            <p><em>Valide jusqu'au : <?php echo htmlspecialchars($promotion['end_date']); ?></em></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Cdansmaville - Tous droits réservés.</p>
        </div>
    </footer>
</body>
</html>
