<?php
/**
 * Blog - Liste des articles
 * Frenchy Conciergerie
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

// Vérifier la connexion à la base de données
if (!$conn) {
    die('Erreur de connexion à la base de données. Veuillez réessayer plus tard.');
}

$security = new Security($conn);
$cache = new Cache($conn);
$settings = getAllSettings($conn);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 9;
$offset = ($page - 1) * $per_page;

// Filtre par catégorie
$categorie_slug = $_GET['categorie'] ?? null;

// Récupération des catégories
$categories = $cache->remember('blog_categories', function() use ($conn) {
    try {
        $stmt = $conn->query("SELECT c.*, COUNT(a.id) as nb_articles
                              FROM FC_categories c
                              LEFT JOIN FC_articles a ON c.id = a.categorie_id AND a.actif = 1
                              WHERE c.actif = 1
                              GROUP BY c.id
                              ORDER BY c.ordre");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        error_log("Blog categories error: " . $e->getMessage());
        return [];
    }
}, 3600);

// Récupération des articles
$where = "WHERE a.actif = 1 AND a.date_publication <= CURRENT_DATE";
$params = [];

if ($categorie_slug) {
    $where .= " AND c.slug = ?";
    $params[] = $categorie_slug;
}

// Compte total
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM FC_articles a LEFT JOIN FC_categories c ON a.categorie_id = c.id $where");
    $stmt->execute($params);
    $total_articles = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Blog count error: " . $e->getMessage());
    $total_articles = 0;
}
$total_pages = ceil($total_articles / $per_page);

// Articles
$params[] = $per_page;
$params[] = $offset;
try {
    $stmt = $conn->prepare("SELECT a.*, c.nom as categorie_nom, c.slug as categorie_slug
                            FROM FC_articles a
                            LEFT JOIN FC_categories c ON a.categorie_id = c.id
                            $where
                            ORDER BY a.date_publication DESC
                            LIMIT ? OFFSET ?");
    $stmt->execute($params);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Blog articles error: " . $e->getMessage());
    $articles = [];
}

// Articles populaires
$articles_populaires = $cache->remember('blog_populaires', function() use ($conn) {
    try {
        $stmt = $conn->query("SELECT id, titre, slug, image, nb_vues
                              FROM FC_articles
                              WHERE actif = 1
                              ORDER BY nb_vues DESC
                              LIMIT 5");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        error_log("Blog popular articles error: " . $e->getMessage());
        return [];
    }
}, 1800);

try {
    $security->trackVisit('/blog');
} catch (Exception $e) {
    // Ignorer les erreurs de tracking
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog - Conseils Location Saisonnière - <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></title>
    <meta name="description" content="Découvrez nos conseils et actualités sur la location saisonnière, la gestion Airbnb et l'investissement locatif dans l'Oise.">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --bleu-frenchy: #1E3A8A;
            --bleu-clair: #3B82F6;
            --rouge-frenchy: #EF4444;
            --gris-clair: #F3F4F6;
            --gris-fonce: #1F2937;
        }

        .blog-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .blog-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .blog-header h1 {
            color: var(--bleu-frenchy);
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .blog-header p {
            color: #6B7280;
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Categories */
        .categories-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
            margin-bottom: 2rem;
        }

        .category-link {
            padding: 0.5rem 1rem;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 25px;
            color: var(--gris-fonce);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .category-link:hover,
        .category-link.active {
            background: var(--bleu-frenchy);
            border-color: var(--bleu-frenchy);
            color: white;
        }

        .category-link .count {
            background: var(--gris-clair);
            padding: 0.1rem 0.4rem;
            border-radius: 10px;
            font-size: 0.8rem;
            margin-left: 0.3rem;
        }

        .category-link.active .count {
            background: rgba(255,255,255,0.2);
        }

        /* Grid */
        .blog-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 2rem;
        }

        @media (max-width: 968px) {
            .blog-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Articles */
        .articles-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .article-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .article-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .article-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: var(--gris-clair);
        }

        .article-content {
            padding: 1.5rem;
        }

        .article-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 0.8rem;
            font-size: 0.85rem;
            color: #6B7280;
        }

        .article-category {
            background: rgba(59, 130, 246, 0.1);
            color: var(--bleu-clair);
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
        }

        .article-content h2 {
            font-size: 1.2rem;
            color: var(--gris-fonce);
            margin-bottom: 0.8rem;
            line-height: 1.4;
        }

        .article-content h2 a {
            color: inherit;
            text-decoration: none;
        }

        .article-content h2 a:hover {
            color: var(--bleu-clair);
        }

        .article-excerpt {
            color: #6B7280;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .read-more {
            color: var(--bleu-clair);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .read-more:hover {
            text-decoration: underline;
        }

        /* Sidebar */
        .blog-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .sidebar-widget {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .sidebar-widget h3 {
            color: var(--bleu-frenchy);
            font-size: 1.1rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--gris-clair);
        }

        .popular-article {
            display: flex;
            gap: 1rem;
            padding: 0.8rem 0;
            border-bottom: 1px solid var(--gris-clair);
        }

        .popular-article:last-child {
            border-bottom: none;
        }

        .popular-article img {
            width: 70px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
        }

        .popular-article h4 {
            font-size: 0.9rem;
            color: var(--gris-fonce);
            margin-bottom: 0.3rem;
        }

        .popular-article h4 a {
            color: inherit;
            text-decoration: none;
        }

        .popular-article h4 a:hover {
            color: var(--bleu-clair);
        }

        .popular-article .views {
            font-size: 0.8rem;
            color: #6B7280;
        }

        /* Newsletter widget */
        .newsletter-widget {
            background: linear-gradient(135deg, var(--bleu-frenchy), var(--bleu-clair));
            color: white;
        }

        .newsletter-widget h3 {
            color: white;
            border-color: rgba(255,255,255,0.2);
        }

        .newsletter-widget p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 1rem;
        }

        .newsletter-widget input {
            width: 100%;
            padding: 0.8rem;
            border: none;
            border-radius: 6px;
            margin-bottom: 0.8rem;
        }

        .newsletter-widget button {
            width: 100%;
            padding: 0.8rem;
            background: white;
            color: var(--bleu-frenchy);
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .newsletter-widget button:hover {
            transform: scale(1.02);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination span {
            padding: 0.6rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .pagination a {
            background: white;
            color: var(--gris-fonce);
            border: 1px solid #e5e7eb;
        }

        .pagination a:hover {
            background: var(--bleu-clair);
            color: white;
            border-color: var(--bleu-clair);
        }

        .pagination span.current {
            background: var(--bleu-frenchy);
            color: white;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6B7280;
        }

        .empty-state .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="blog-container">
        <div class="blog-header">
            <h1>Le Blog</h1>
            <p>Conseils, actualités et astuces pour réussir votre projet de location saisonnière</p>
        </div>

        <!-- Catégories -->
        <div class="categories-bar">
            <a href="blog.php" class="category-link <?= !$categorie_slug ? 'active' : '' ?>">
                Tous <span class="count"><?= $total_articles ?></span>
            </a>
            <?php foreach ($categories as $cat): ?>
            <a href="blog.php?categorie=<?= e($cat['slug']) ?>" class="category-link <?= $categorie_slug === $cat['slug'] ? 'active' : '' ?>">
                <?= e($cat['nom']) ?> <span class="count"><?= $cat['nb_articles'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="blog-grid">
            <!-- Articles -->
            <div>
                <?php if (empty($articles)): ?>
                <div class="empty-state">
                    <div class="icon">📝</div>
                    <h2>Aucun article pour le moment</h2>
                    <p>Revenez bientôt pour découvrir nos conseils !</p>
                </div>
                <?php else: ?>
                <div class="articles-list">
                    <?php foreach ($articles as $article): ?>
                    <article class="article-card">
                        <a href="article.php?slug=<?= e($article['slug']) ?>">
                            <img src="<?= e($article['image'] ?: 'assets/img/blog-placeholder.jpg') ?>"
                                 alt="<?= e($article['titre']) ?>"
                                 class="article-image"
                                 loading="lazy"
                                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22200%22%3E%3Crect fill=%22%23e5e7eb%22 width=%22400%22 height=%22200%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 fill=%22%239ca3af%22%3EImage%3C/text%3E%3C/svg%3E'">
                        </a>
                        <div class="article-content">
                            <div class="article-meta">
                                <?php if ($article['categorie_nom']): ?>
                                <span class="article-category"><?= e($article['categorie_nom']) ?></span>
                                <?php endif; ?>
                                <span><?= formatDateFr($article['date_publication']) ?></span>
                            </div>
                            <h2><a href="article.php?slug=<?= e($article['slug']) ?>"><?= e($article['titre']) ?></a></h2>
                            <p class="article-excerpt"><?= e(substr($article['extrait'] ?? strip_tags($article['contenu']), 0, 150)) ?>...</p>
                            <a href="article.php?slug=<?= e($article['slug']) ?>" class="read-more">Lire la suite →</a>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $categorie_slug ? '&categorie=' . e($categorie_slug) : '' ?>">← Précédent</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                        <?php else: ?>
                        <a href="?page=<?= $i ?><?= $categorie_slug ? '&categorie=' . e($categorie_slug) : '' ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $categorie_slug ? '&categorie=' . e($categorie_slug) : '' ?>">Suivant →</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <aside class="blog-sidebar">
                <!-- Articles populaires -->
                <div class="sidebar-widget">
                    <h3>Articles populaires</h3>
                    <?php if (!empty($articles_populaires)): ?>
                    <?php foreach ($articles_populaires as $pop): ?>
                    <div class="popular-article">
                        <img src="<?= e($pop['image'] ?: 'assets/img/blog-placeholder.jpg') ?>"
                             alt="<?= e($pop['titre']) ?>"
                             loading="lazy">
                        <div>
                            <h4><a href="article.php?slug=<?= e($pop['slug']) ?>"><?= e($pop['titre']) ?></a></h4>
                            <span class="views"><?= number_format($pop['nb_vues']) ?> vues</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <p style="color: #6B7280; font-size: 0.9rem;">Aucun article populaire pour le moment.</p>
                    <?php endif; ?>
                </div>

                <!-- Newsletter -->
                <div class="sidebar-widget newsletter-widget">
                    <h3>Newsletter</h3>
                    <p>Recevez nos meilleurs conseils directement dans votre boîte mail !</p>
                    <form action="newsletter.php" method="POST">
                        <input type="email" name="email" placeholder="Votre email" required>
                        <button type="submit">S'inscrire</button>
                    </form>
                </div>

                <!-- CTA -->
                <div class="sidebar-widget">
                    <h3>Besoin d'aide ?</h3>
                    <p style="color: #6B7280; margin-bottom: 1rem;">Vous avez un projet de location saisonnière ? Contactez-nous !</p>
                    <a href="index.php#contact" style="display: block; text-align: center; background: var(--bleu-frenchy); color: white; padding: 0.8rem; border-radius: 6px; text-decoration: none; font-weight: 600;">Nous contacter</a>
                </div>
            </aside>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
