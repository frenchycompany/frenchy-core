<?php
require 'db/connection.php';

// Récupérer le slug de l'article depuis l'URL
$slug = htmlspecialchars($_GET['slug']);

try {
    $stmt = $conn->prepare("SELECT * FROM seo_articles WHERE slug = :slug");
    $stmt->bindParam(':slug', $slug);
    $stmt->execute();
    $article = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$article) {
        echo "Article non trouvé.";
        exit();
    }
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($article['meta_title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($article['meta_description']); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($article['keywords']); ?>">
</head>
<body>
    <h1><?php echo htmlspecialchars($article['title']); ?></h1>
    <img src="<?php echo htmlspecialchars($article['image']); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>">
    <p><?php echo nl2br(htmlspecialchars($article['content'])); ?></p>
</body>
</html>
