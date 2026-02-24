<?php
/**
 * Page de détail d'un article
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
$settings = getAllSettings($conn);

// Support des deux formats: slug et id
$slug = $_GET['slug'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (empty($slug) && $id === 0) {
    header('Location: blog.php');
    exit;
}

// Récupération de l'article
if (!empty($slug)) {
    $stmt = $conn->prepare("SELECT a.*, c.nom as categorie_nom, c.slug as categorie_slug
                            FROM FC_articles a
                            LEFT JOIN FC_categories c ON a.categorie_id = c.id
                            WHERE a.slug = ? AND a.actif = 1");
    $stmt->execute([$slug]);
} else {
    $stmt = $conn->prepare("SELECT a.*, c.nom as categorie_nom, c.slug as categorie_slug
                            FROM FC_articles a
                            LEFT JOIN FC_categories c ON a.categorie_id = c.id
                            WHERE a.id = ? AND a.actif = 1");
    $stmt->execute([$id]);
}
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Article non trouvé - <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></title>
        <style>
            body { font-family: 'Segoe UI', sans-serif; text-align: center; padding: 4rem; background: #f3f4f6; }
            h1 { color: #1E3A8A; }
            a { color: #3B82F6; }
        </style>
    </head>
    <body>
        <h1>Article non trouvé</h1>
        <p>L'article que vous cherchez n'existe pas ou a été supprimé.</p>
        <a href="blog.php">← Retour au blog</a>
    </body>
    </html>
    <?php
    exit;
}

// Incrémenter le compteur de vues (si la colonne existe)
try {
    $stmt = $conn->prepare("UPDATE FC_articles SET nb_vues = nb_vues + 1 WHERE id = ?");
    $stmt->execute([$article['id']]);
} catch (PDOException $e) {
    // Colonne nb_vues n'existe pas encore
}

// Articles connexes
$stmt = $conn->prepare("SELECT id, titre, slug, image, date_publication
                        FROM FC_articles
                        WHERE actif = 1 AND id != ?
                        ORDER BY RAND()
                        LIMIT 3");
$stmt->execute([$article['id']]);
$articles_connexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$security->trackVisit('/article/' . ($article['slug'] ?? $article['id']));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($article['meta_title'] ?? $article['titre']) ?> - <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></title>
    <meta name="description" content="<?= e($article['meta_description'] ?? substr(strip_tags($article['extrait'] ?? $article['contenu']), 0, 160)) ?>">

    <style>
        :root {
            --bleu-frenchy: #1E3A8A;
            --bleu-clair: #3B82F6;
            --gris-clair: #F3F4F6;
            --gris-fonce: #1F2937;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--gris-fonce);
            background: var(--gris-clair);
        }

        header {
            background: linear-gradient(135deg, var(--bleu-frenchy), var(--bleu-clair));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        header a { color: white; text-decoration: none; }

        .article-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .article-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .article-image {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
        }

        .article-body {
            padding: 2rem;
        }

        .article-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            color: #6B7280;
            font-size: 0.9rem;
        }

        .article-category {
            background: rgba(59, 130, 246, 0.1);
            color: var(--bleu-clair);
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
        }

        h1 {
            font-size: 2rem;
            color: var(--gris-fonce);
            margin-bottom: 1.5rem;
            line-height: 1.3;
        }

        .article-content {
            font-size: 1.1rem;
            line-height: 1.8;
        }

        .article-content h2 {
            color: var(--bleu-frenchy);
            margin: 2rem 0 1rem;
            font-size: 1.5rem;
        }

        .article-content p { margin-bottom: 1.5rem; }

        .article-content ul, .article-content ol {
            margin-bottom: 1.5rem;
            padding-left: 1.5rem;
        }

        .article-content blockquote {
            background: var(--gris-clair);
            border-left: 4px solid var(--bleu-clair);
            padding: 1rem;
            margin: 1.5rem 0;
            font-style: italic;
        }

        .share-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }

        .share-btn {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.85rem;
            color: white;
        }

        .share-btn.fb { background: #1877F2; }
        .share-btn.tw { background: #1DA1F2; }
        .share-btn.li { background: #0A66C2; }

        .back-link {
            display: inline-block;
            margin-top: 1.5rem;
            color: var(--bleu-clair);
            text-decoration: none;
        }

        .related-articles {
            margin-top: 3rem;
        }

        .related-articles h2 {
            color: var(--bleu-frenchy);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .related-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .related-card img {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }

        .related-card .content {
            padding: 1rem;
        }

        .related-card h3 {
            font-size: 0.95rem;
            margin-bottom: 0.3rem;
        }

        .related-card h3 a {
            color: var(--gris-fonce);
            text-decoration: none;
        }

        .related-card h3 a:hover { color: var(--bleu-clair); }

        .cta-box {
            background: linear-gradient(135deg, var(--bleu-frenchy), var(--bleu-clair));
            color: white;
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            margin-top: 2rem;
        }

        .cta-box h3 { margin-bottom: 0.5rem; }

        .cta-box a {
            display: inline-block;
            margin-top: 1rem;
            background: white;
            color: var(--bleu-frenchy);
            padding: 0.7rem 1.5rem;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
        }

        footer {
            background: var(--bleu-frenchy);
            color: white;
            text-align: center;
            padding: 2rem;
            margin-top: 3rem;
        }

        footer a { color: #93c5fd; }
    </style>
</head>
<body>
    <header>
        <a href="index.php"><?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></a>
    </header>

    <div class="article-container">
        <article class="article-card">
            <?php if (!empty($article['image'])): ?>
            <img src="<?= e($article['image']) ?>" alt="<?= e($article['titre']) ?>" class="article-image">
            <?php endif; ?>

            <div class="article-body">
                <div class="article-meta">
                    <?php if (!empty($article['categorie_nom'])): ?>
                    <span class="article-category"><?= e($article['categorie_nom']) ?></span>
                    <?php endif; ?>
                    <span><?= date('d/m/Y', strtotime($article['date_publication'] ?? $article['created_at'])) ?></span>
                    <span><?= number_format($article['nb_vues'] ?? 0) ?> lectures</span>
                </div>

                <h1><?= e($article['titre']) ?></h1>

                <div class="article-content">
                    <?= $article['contenu'] ?>
                </div>

                <div class="share-buttons">
                    <span style="color: #6B7280;">Partager :</span>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($_SERVER['REQUEST_URI'] ?? '') ?>" target="_blank" class="share-btn fb">Facebook</a>
                    <a href="https://twitter.com/intent/tweet?url=<?= urlencode($_SERVER['REQUEST_URI'] ?? '') ?>" target="_blank" class="share-btn tw">Twitter</a>
                    <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?= urlencode($_SERVER['REQUEST_URI'] ?? '') ?>" target="_blank" class="share-btn li">LinkedIn</a>
                </div>

                <a href="blog.php" class="back-link">← Retour au blog</a>
            </div>
        </article>

        <?php if (!empty($articles_connexes)): ?>
        <section class="related-articles">
            <h2>Articles similaires</h2>
            <div class="related-grid">
                <?php foreach ($articles_connexes as $related): ?>
                <div class="related-card">
                    <?php if (!empty($related['image'])): ?>
                    <img src="<?= e($related['image']) ?>" alt="<?= e($related['titre']) ?>">
                    <?php endif; ?>
                    <div class="content">
                        <h3><a href="article.php?slug=<?= e($related['slug']) ?>"><?= e($related['titre']) ?></a></h3>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <div class="cta-box">
            <h3>Besoin d'aide pour votre projet ?</h3>
            <p>Notre équipe vous accompagne pour maximiser vos revenus locatifs.</p>
            <a href="index.php#contact">Contactez-nous</a>
        </div>
    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></p>
        <p>Contact : <a href="mailto:<?= e($settings['email'] ?? 'contact@frenchycompany.fr') ?>"><?= e($settings['email'] ?? 'contact@frenchycompany.fr') ?></a></p>
    </footer>
</body>
</html>
