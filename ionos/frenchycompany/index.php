<!DOCTYPE html>
<html lang="fr">
<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-MJWPDDCWKW"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-MJWPDDCWKW');
    </script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Holding Company Sites</title>
    <meta name="description" content="Bienvenue chez la Frenchy Company, votre expert en services immobiliers comprenant la conciergerie, la communication, la rénovation, et plus encore.">
    <meta name="keywords" content="services immobiliers, conciergerie, rénovation, communication, agence immobilière, Frenchy Company, Compiègne">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    <style>
        /* Add your CSS styles here */
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
        header img {
            max-width: 120px;
            height: auto;
            margin-bottom: 15px;
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
        .header-contact {
            font-size: 14px;
            margin-top: 10px;
        }
        .header-contact span {
            display: block;
            margin-top: 5px;
        }
        .header-contact a {
            color: #f5a623;
            text-decoration: none;
            font-weight: bold;
        }
        .header-contact a:hover {
            text-decoration: underline;
        }
        .header-cta {
            margin-top: 20px;
        }
        .header-cta a {
            display: inline-block;
            padding: 12px 25px;
            font-size: 16px;
            color: #001d2e;
            background-color: #f5a623;
            text-decoration: none;
            border-radius: 50px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s, transform 0.3s;
        }
        .header-cta a:hover {
            background-color: #e5941d;
            transform: translateY(-3px);
        }
        .content {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #ffffff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
        }
        .section-title {
            font-size: 22px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
            color: #001d2e;
        }
        .frame-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .frame-wrapper {
            background-color: #ffffff;
            text-align: center;
            padding: 20px;
            border-radius: 8px;
            transition: transform 0.3s, box-shadow 0.3s;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .frame-wrapper:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
        }
        .frame-wrapper img {
            max-width: 100px;
            height: auto;
            margin-bottom: 10px;
        }
        .frame-title {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
        }
        .frame-description {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
            margin-bottom: 10px;
        }
        .frame-wrapper a.btn {
            display: inline-block;
            padding: 8px 15px;
            font-size: 14px;
            background-color: #005082;
            color: #ffffff;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .frame-rdv {
            border: 1px solid #ddd;
            overflow: hidden;
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            margin-top: 20px;
        }
        .frame-rdv iframe {
            width: 100%;
            height: 650px;
            border: none;
        }
        .frame-title-rdv {
            background-color: #001d2e;
            color: #ffffff;
            padding: 15px;
            font-size: 18px;
            font-weight: bold;
            text-align: center;
        }
        .frame-wrapper a.btn:hover {
            background-color: #003d5e;
        }
        .articles-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 40px;
        }
        .article-item {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            background-color: #ffffff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 15px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .article-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
        }
        .article-image img {
            width: 120px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
            flex-shrink: 0;
        }
        .article-content {
            flex-grow: 1;
        }
        .article-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #001d2e;
        }
        .article-description {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
            margin-bottom: 15px;
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
        .partners-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .partner-card {
            background-color: #ffffff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 15px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .partner-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
        }
        .partner-logo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            margin-bottom: 10px;
            border-radius: 50%;
            background-color: #001d2e; /* Couleur de fond */
            object-fit: cover; /* Ajuste l'image au conteneur sans déformer */
        }
        .partner-card h3 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #005082;
        }
        .partner-card p {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }
        .partner-btn {
            display: inline-block;
            padding: 8px 15px;
            font-size: 14px;
            font-weight: bold;
            color: #ffffff;
            background-color: #005082;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }
        .partner-btn:hover {
            background-color: #003d5e;
            transform: translateY(-3px);
        }
    </style>
</head>
<body>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TQVDCX3T"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

    <!-- Microdonnées SEO -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "Frenchy Company",
      "url": "https://frenchycompany.fr",
      "logo": "https://frenchycompany.fr/logo.png",
      "contactPoint": {
        "@type": "ContactPoint",
        "telephone": "+33-6-47-55-46-78",
        "contactType": "Customer Service",
        "email": "raphael@frenchycompany.fr"
      },
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "94 rue Jean Jaurès",
        "addressLocality": "Lacroix-Saint-Ouen",
        "postalCode": "60610",
        "addressCountry": "FR"
      }
    }
    </script>
    <!-- Fin Microdonnées SEO -->

    <?php include 'config.php'; ?>
    <header data-aos="fade-down">
        <img src="logo.png" alt="Logo de la Frenchy Company">
        <h1>Bienvenue chez la Frenchy Company</h1>
        <p>Services immobiliers sur mesure pour particuliers et professionnels.</p>
        <div class="header-contact">
            <span><strong>Téléphone :</strong> +33 6 47 55 46 78</span>
            <span><strong>Email :</strong> <a href="mailto:raphael@frenchycompany.fr">raphael@frenchycompany.fr</a></span>
        </div>
        <div class="header-cta">
            <a href="#rendez-vous">Planifiez un rendez-vous</a>
        </div>
    </header>
    <div class="content">
        <h2 class="section-title" data-aos="fade-up">Découvrez nos services en ligne</h2>
        <div class="frame-container">
            <?php
            $stmt = $conn->prepare("SELECT title, logo, url, description FROM sites");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<div class='frame-wrapper' data-aos='fade-up'>";
                echo "<img src='" . htmlspecialchars($row['logo']) . "' alt='Logo de " . htmlspecialchars($row['title']) . "'>";
                echo "<div class='frame-title'>" . htmlspecialchars($row['title']) . "</div>";
                echo "<p class='frame-description'>" . htmlspecialchars($row['description']) . "</p>";
                echo "<a href='" . htmlspecialchars($row['url']) . "' target='_blank' class='btn'>Visiter le site</a>";
                echo "</div>";
            }
            ?>
        </div>
        <div class="frame-rdv" id="rendez-vous" data-aos="fade-left">
            <div class="frame-title-rdv">Planifiez un rendez-vous avec nous</div>
            <iframe src="https://calendar.google.com/calendar/appointments/schedules/AcZssZ3IkmAxvmZjK5vaov3jEiAA80p89neKxTBuKai4nKJVYEYyiFPgNkynOnWgPm_k0ib-VL4WLKWm?gv=true"></iframe>
        </div>
    </div>

    <div class="content">
    <!-- Section Articles -->
    <h2 class="section-title" data-aos="fade-up">Nos articles et conseils</h2>
    <div class="articles-container">
    <?php
    // Récupérer les articles depuis la base de données
    $stmt = $conn->prepare("SELECT id, title, description, thumbnail FROM articles ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();

    // Afficher chaque article avec image à gauche
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<article class='article-item' data-aos='fade-up'>";
        echo "<div class='article-image'>";
        echo "<img src='" . htmlspecialchars($row['thumbnail']) . "' alt='" . htmlspecialchars($row['title']) . "'>";
        echo "</div>";
        echo "<div class='article-content'>";
        echo "<h3 class='article-title'>" . htmlspecialchars($row['title']) . "</h3>";
        echo "<p class='article-description'>" . htmlspecialchars($row['description']) . "</p>";
        echo "<a href='article.php?id=" . $row['id'] . "' class='btn'>Lire l'article</a>";
        echo "</div>";
        echo "</article>";
    }
    ?>
    </div>
    </div>
<div class="content">
    <!-- Section Articles -->
    <h2 class="section-title" data-aos="fade-up">Nos partenaires de confiance</h2>
    <div class="articles-container">
    <!-- Partners-->
<?php
// Inclure la configuration de connexion à la base de données
include 'config.php';

try {
    // Requête pour récupérer tous les partenaires
    $stmt = $conn->prepare("SELECT name, description, logo_url, website_url FROM partners");
    $stmt->execute();

    // Vérifier s'il y a des partenaires
    if ($stmt->rowCount() > 0) {
        echo '<p>Découvrez nos partenaires locaux et nationaux qui complètent nos services et vous offrent des solutions adaptées.</p>';
        echo '<div class="partners-grid">';

        // Afficher chaque partenaire
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo '<div class="partner-card">';
            echo '<img src="' . htmlspecialchars($row['logo_url']) . '" alt="Logo ' . htmlspecialchars($row['name']) . '" class="partner-logo">';
            echo '<h3>' . htmlspecialchars($row['name']) . '</h3>';
            echo '<p>' . htmlspecialchars($row['description']) . '</p>';
            echo '<a href="' . htmlspecialchars($row['website_url']) . '" target="_blank" class="partner-btn">Visiter</a>';
            echo '</div>';
        }

        echo '</div>';
    } else {
        echo '<p>Aucun partenaire disponible pour le moment.</p>';
    }
} catch (PDOException $e) {
    echo '<p>Erreur lors du chargement des partenaires : ' . $e->getMessage() . '</p>';
}
?>
</div></div>

<div class="content">
        <h2 class="section-title" data-aos="fade-up">Questions fréquentes</h2>
        <div class="faq-container">
            <div class="faq-item" data-aos="fade-right">
                <h3>Quels services proposez-vous ?</h3>
                <p>Nous offrons des services de conciergerie, rénovation, communication, et gestion immobilière sur mesure.</p>
            </div>
            <div class="faq-item" data-aos="fade-left">
                <h3>Comment puis-je planifier un rendez-vous ?</h3>
                <p>Vous pouvez utiliser notre formulaire de réservation ou nous contacter directement par e-mail ou téléphone.</p>
            </div>
            <div class="faq-item" data-aos="fade-right">
                <h3>Où êtes-vous situés ?</h3>
                <p>Nous sommes basés à Compiègne, avec une présence locale pour mieux répondre à vos besoins.</p>
            </div>
        </div>
    </div>


    <footer>
        <p>Contactez-nous à <a href="mailto:raphael@frenchycompany.fr">raphael@frenchycompany.fr</a></p>
        <p>&copy; 2024 Frenchy Company</p>
    </footer>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 1000, // Durée des animations en ms
            once: true, // Animation jouée une seule fois
        });
    </script>
</body>
</html>
