<?php
require 'db/connection.php';
include 'menu.php';

// Récupérer les données pour les sections
try {
    // Commerçants
    $stmtMerchants = $conn->query("SELECT business_name, slug FROM merchants LIMIT 3");
    $merchants = $stmtMerchants->fetchAll(PDO::FETCH_ASSOC);

    // Promotions
    $stmtPromotions = $conn->query("SELECT title, slug FROM promotions WHERE end_date >= CURDATE() LIMIT 3");
    $promotions = $stmtPromotions->fetchAll(PDO::FETCH_ASSOC);

    // Actualités
    $stmtArticles = $conn->query("SELECT title, slug FROM seo_articles ORDER BY created_at DESC LIMIT 3");
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
    <title>Cdansmaville - Votre réseau local</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Banner Section -->
    <header class="banner">
        <img src="images/banner-logo.png" alt="C dans ma Ville" class="banner-logo">
    </header>

    <!-- Introduction Section -->
    <section class="intro-section">
        <div class="container intro-container">
            <div class="intro-text">
                <h1>Bienvenue sur "C' dans ma Ville" !</h1>
                <h2>Le site incontournable de Compiègne et ses environs !</h2>
                <p>
                    Plongez au cœur de votre ville avec <strong>C' dans ma Ville</strong> :
                </p>
                <ul>
                    <li><strong>Événements locaux</strong> : Découvrez chaque jour ce qui anime Compiègne et ses alentours.</li>
                    <li><strong>Bons plans et commerces</strong> : Profitez des meilleures offres de vos commerçants préférés.</li>
                    <li><strong>Vie associative</strong> : Restez informé sur les initiatives et actions locales.</li>
                    <li><strong>Actualités</strong> : Ne manquez rien des informations essentielles de <strong>VOTRE VILLE</strong>.</li>
                </ul>
                <p>
                    👉 <strong>Abonnez-vous à notre newsletter</strong> pour recevoir toutes les infos directement dans votre boîte mail.<br>
                    👉 <strong>Suivez-nous sur Facebook</strong> pour ne rien rater des nouveautés et événements proches de chez vous.
                </p>
            </div>
            <div class="intro-image">
                <img src="images/header-compiegne.jpg" alt="Vue de la mairie de Compiègne">
            </div>
        </div>
    </section>

    <!-- Sections Content -->
    <main class="main-sections">
        <div class="section">
            <img src="images/commerces.png" alt="Bons Plans" class="section-icon">
            <h2>Commerçants</h2>
            <ul>
                <?php foreach ($merchants as $merchant): ?>
                    <li>
                        <a href="merchant.php?slug=<?php echo htmlspecialchars($merchant['slug']); ?>">
                            <?php echo htmlspecialchars($merchant['business_name']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a href="directory.php" class="btn-link">Voir tous les commerçants</a>
        </div>

        <div class="section">
            <img src="images/promotions.png" alt="Promotions" class="section-icon">
            <h2>Promotions</h2>
            <ul>
                <?php foreach ($promotions as $promotion): ?>
                    <li>
                        <a href="promotion.php?slug=<?php echo htmlspecialchars($promotion['slug']); ?>">
                            <?php echo htmlspecialchars($promotion['title']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a href="promotions.php" class="btn-link">Voir toutes les promotions</a>
        </div>

        <div class="section">
            <img src="images/associations.png" alt="Actualités" class="section-icon">
            <h2>Actualités</h2>
            <ul>
                <?php foreach ($articles as $article): ?>
                    <li>
                        <a href="article.php?slug=<?php echo htmlspecialchars($article['slug']); ?>">
                            <?php echo htmlspecialchars($article['title']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a href="news.php" class="btn-link">Voir toutes les actualités</a>
        </div>
    </main>

    <footer>
        <p>&copy; 2024 Cdansmaville - Tous droits réservés.</p>
    </footer>
</body>
</html>
