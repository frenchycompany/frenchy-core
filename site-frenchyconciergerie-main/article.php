<?php
include 'config.php';

// Récupérer l'ID de l'article depuis l'URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Requête pour récupérer les données de l'article
$stmt = $conn->prepare("SELECT title, content, created_at FROM articles WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

// Si l'article est trouvé
if ($article): ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($article['title']); ?></title>
        <meta name="description" content="<?php echo substr(strip_tags($article['content']), 0, 160); ?>">
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
        <style>
            body {
                font-family: 'Roboto', Arial, sans-serif;
                margin: 0;
                padding: 0;
                background-color: #f5f5f5;
            }
            header {
                background: linear-gradient(135deg, #001d2e, #005082);
                color: #ffffff;
                padding: 40px 20px;
                text-align: center;
                position: relative;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            }
            header h1 {
                font-size: 28px;
                margin: 10px 0;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            header p {
                font-size: 16px;
                margin: 5px 0;
            }
            .content {
                max-width: 800px;
                margin: 20px auto;
                padding: 20px;
                background-color: #ffffff;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                border-radius: 12px;
            }
            .article-title {
                font-size: 24px;
                font-weight: bold;
                margin-bottom: 20px;
                color: #001d2e;
                text-align: center;
            }
            .article-meta {
                font-size: 14px;
                color: #666;
                text-align: center;
                margin-bottom: 20px;
            }
            .article-content {
                font-size: 16px;
                color: #333;
                line-height: 1.8;
            }
            .back-link {
                display: inline-block;
                margin-top: 20px;
                font-size: 14px;
                background-color: #005082;
                color: #ffffff;
                text-decoration: none;
                padding: 10px 20px;
                border-radius: 5px;
                text-align: center;
                transition: background-color 0.3s;
            }
            .back-link:hover {
                background-color: #003d5e;
            }
            footer {
                text-align: center;
                background-color: #001d2e;
                color: #ffffff;
                padding: 20px;
                margin-top: 40px;
            }
            footer a {
                color: #f5a623;
                text-decoration: none;
            }
            footer a:hover {
                text-decoration: underline;
            }
            .cta-box {
    margin: 20px 0;
    padding: 20px;
    background-color: #f5a623;
    color: #ffffff;
    text-align: center;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.cta-btn {
    display: inline-block;
    margin-top: 10px;
    padding: 10px 20px;
    font-size: 16px;
    font-weight: bold;
    color: #ffffff;
    background-color: #005082;
    text-decoration: none;
    border-radius: 5px;
    transition: background-color 0.3s ease, transform 0.3s ease;
}

.cta-btn:hover {
    background-color: #003d5e;
    transform: translateY(-3px);
}

        </style>
    </head>
    <body>
        <header>
            <h1><?php echo htmlspecialchars($article['title']); ?></h1>
            <p class="article-meta">Publié le <?php echo date('d M Y', strtotime($article['created_at'])); ?></p>
        </header>
        <div class="content">
            <article class="article-content">
                <?php echo $article['content']; // Contenu complet en HTML ?>
            </article>
            <a href="index.php" class="back-link">← Retour à la liste des articles</a>
        </div>
        <footer>
            <p>&copy; 2024 Frenchy Company</p>
            <p>Contactez-nous à <a href="mailto:raphael@frenchycompany.fr">raphael@frenchycompany.fr</a></p>
        </footer>
    </body>
    </html>
<?php else: ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Article non trouvé</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
        <style>
            body {
                font-family: 'Roboto', Arial, sans-serif;
                margin: 0;
                padding: 0;
                text-align: center;
                color: #333;
                background-color: #f5f5f5;
            }
            .not-found {
                margin-top: 50px;
            }
            .back-link {
                display: inline-block;
                margin-top: 20px;
                font-size: 14px;
                background-color: #005082;
                color: #ffffff;
                text-decoration: none;
                padding: 10px 20px;
                border-radius: 5px;
                text-align: center;
                transition: background-color 0.3s;
            }
            .back-link:hover {
                background-color: #003d5e;
            }
        </style>
    </head>
    <body>
        <div class="not-found">
            <h1>Article non trouvé</h1>
            <p>L'article que vous cherchez n'existe pas ou a été supprimé.</p>
            <a href="index.php" class="back-link">← Retour à la liste des articles</a>
        </div>
    </body>
    </html>
<?php endif; ?>
