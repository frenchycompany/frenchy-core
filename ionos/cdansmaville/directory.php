<?php
require 'db/connection.php';
include 'menu.php';

// Récupérer les commerçants depuis la base de données
try {
    $stmt = $conn->query("SELECT business_name, address, city, phone, image, slug FROM merchants LIMIT 10");
    $merchants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annuaire des commerçants - Cdansmaville</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Header -->
    <header class="hero">
        <div class="container hero-container">
            <h1>Annuaire des commerçants</h1>
            <p>Découvrez les commerçants et artisans proches de chez vous.</p>
        </div>
    </header>

    <!-- Liste des commerçants -->
    <main class="merchant-list">
        <div class="container">
            <?php foreach ($merchants as $merchant): ?>
            <div class="merchant-card">
                <div class="merchant-image" style="background-image: url('images/<?php echo htmlspecialchars($merchant['image']); ?>');"></div>
                <div class="merchant-info">
                    <h3><?php echo htmlspecialchars($merchant['business_name']); ?></h3>
                    <p><?php echo htmlspecialchars($merchant['address']); ?>, <?php echo htmlspecialchars($merchant['city']); ?></p>
                    <p><a href="tel:<?php echo htmlspecialchars($merchant['phone']); ?>"><?php echo htmlspecialchars($merchant['phone']); ?></a></p>
                    <a href="merchant.php?slug=<?php echo htmlspecialchars($merchant['slug']); ?>" class="btn-link">+ d'informations</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p>&copy; 2024 Cdansmaville - Tous droits réservés.</p>
        </div>
    </footer>
</body>
</html>
