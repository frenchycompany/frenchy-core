<?php
require 'db/connection.php';
include 'menu.php';

// Récupérer toutes les actualités
try {
    $stmtArticles = $conn->query("SELECT title, slug, meta_description FROM seo_articles ORDER BY created_at DESC");
    $articles = $stmtArticles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualités - Cdansmaville</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="hero">
        <div class="container">
            <h1>Actualités locales</h1>
            <p>Découvrez les dernières nouvelles de votre ville.</p>
        </div>
    </header>

    <main>
        <div class="container">
            <section class="section-articles">
                <h2>Dernières actualités</h2>
                <ul>
                    <?php foreach ($articles as $article): ?>
                        <li>
                            <a href="article.php?slug=<?php echo htmlspecialchars($article['slug']); ?>">
                                <strong><?php echo htmlspecialchars($article['title']); ?></strong>
                            </a>
                            <p><?php echo htmlspecialchars($article['meta_description']); ?></p>
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
