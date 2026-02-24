<?php
/**
 * Sitemap XML dynamique
 * Frenchy Conciergerie
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=utf-8');

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Page d'accueil -->
    <url>
        <loc><?= $baseUrl ?>/</loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>

    <!-- Pages principales -->
    <url>
        <loc><?= $baseUrl ?>/blog.php</loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>/simulateur.php</loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>/calculateur.php</loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>/avis.php</loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>

    <!-- Pages légales et informatives -->
    <url>
        <loc><?= $baseUrl ?>/contrats-retractation.php</loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>yearly</changefreq>
        <priority>0.5</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>/politique-avis.php</loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>yearly</changefreq>
        <priority>0.5</priority>
    </url>

    <!-- Logements -->
    <?php
    $stmt = $conn->query("SELECT slug, updated_at FROM FC_logements WHERE actif = 1 ORDER BY updated_at DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
    ?>
    <url>
        <loc><?= $baseUrl ?>/logement.php?slug=<?= htmlspecialchars($row['slug']) ?></loc>
        <lastmod><?= date('Y-m-d', strtotime($row['updated_at'])) ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
    <?php endwhile; ?>

    <!-- Articles de blog -->
    <?php
    $stmt = $conn->query("SELECT slug, updated_at FROM FC_articles WHERE actif = 1 ORDER BY date_publication DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
    ?>
    <url>
        <loc><?= $baseUrl ?>/article.php?slug=<?= htmlspecialchars($row['slug']) ?></loc>
        <lastmod><?= date('Y-m-d', strtotime($row['updated_at'])) ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    <?php endwhile; ?>

    <!-- Catégories -->
    <?php
    $stmt = $conn->query("SELECT slug FROM FC_categories WHERE actif = 1");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
    ?>
    <url>
        <loc><?= $baseUrl ?>/blog.php?categorie=<?= htmlspecialchars($row['slug']) ?></loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.5</priority>
    </url>
    <?php endwhile; ?>
</urlset>
