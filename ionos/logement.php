<?php
/**
 * Page de détail d'un logement
 * Frenchy Conciergerie - SEO optimisé
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

$security = new Security($conn);
$settings = getAllSettings($conn);

$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header('Location: index.php#galerie');
    exit;
}

// Récupération du logement
$stmt = $conn->prepare("SELECT * FROM FC_logements WHERE slug = ? AND actif = 1");
$stmt->execute([$slug]);
$logement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$logement) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Logement non trouvé</title>
        <style>body{font-family:sans-serif;text-align:center;padding:4rem;}a{color:#3B82F6;}</style>
    </head>
    <body>
        <h1>Logement non trouvé</h1>
        <p><a href="index.php#galerie">Voir tous nos logements</a></p>
    </body>
    </html>
    <?php
    exit;
}

// Récupération des avis liés
$stmt = $conn->prepare("SELECT * FROM FC_avis WHERE logement_id = ? AND actif = 1 ORDER BY date_avis DESC");
$stmt->execute([$logement['id']]);
$avis = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Autres logements
$stmt = $conn->prepare("SELECT * FROM FC_logements WHERE actif = 1 AND id != ? ORDER BY RAND() LIMIT 3");
$stmt->execute([$logement['id']]);
$autres_logements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$security->trackVisit('/logement/' . $slug);

// Schema.org
$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'Accommodation',
    'name' => $logement['titre'],
    'description' => $logement['description_longue'] ?? $logement['description'],
    'image' => $logement['image'] ? $baseUrl . '/' . $logement['image'] : '',
    'address' => [
        '@type' => 'PostalAddress',
        'addressLocality' => $logement['localisation'],
        'addressRegion' => 'Oise',
        'addressCountry' => 'FR'
    ],
    'numberOfRooms' => $logement['nb_chambres'],
    'occupancy' => [
        '@type' => 'QuantitativeValue',
        'value' => $logement['nb_voyageurs']
    ]
];

if ($logement['note_moyenne'] > 0) {
    $schema['aggregateRating'] = [
        '@type' => 'AggregateRating',
        'ratingValue' => $logement['note_moyenne'],
        'reviewCount' => $logement['nb_avis']
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($logement['titre']) ?> - Location saisonnière <?= e($logement['localisation']) ?> | <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></title>
    <meta name="description" content="<?= e($logement['description']) ?> - <?= e($logement['type_bien']) ?> à <?= e($logement['localisation']) ?>. Réservez votre séjour avec Frenchy Conciergerie.">

    <meta property="og:title" content="<?= e($logement['titre']) ?>">
    <meta property="og:description" content="<?= e($logement['description']) ?>">
    <meta property="og:image" content="<?= e($logement['image']) ?>">
    <meta property="og:type" content="place">

    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>

    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --bleu-frenchy: #1E3A8A;
            --bleu-clair: #3B82F6;
            --gris-clair: #F3F4F6;
            --gris-fonce: #1F2937;
        }

        .logement-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .logement-header {
            margin-bottom: 2rem;
        }

        .breadcrumb {
            font-size: 0.9rem;
            color: #6B7280;
            margin-bottom: 1rem;
        }

        .breadcrumb a { color: var(--bleu-clair); text-decoration: none; }

        .logement-title {
            font-size: 2rem;
            color: var(--gris-fonce);
            margin-bottom: 0.5rem;
        }

        .logement-meta {
            display: flex;
            gap: 1.5rem;
            color: #6B7280;
            flex-wrap: wrap;
        }

        .logement-gallery {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-radius: 12px;
            overflow: hidden;
        }

        .logement-gallery img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .logement-gallery img:hover {
            transform: scale(1.02);
        }

        .main-image { grid-row: span 2; min-height: 400px; }
        .side-images { display: flex; flex-direction: column; gap: 0.5rem; }
        .side-images img { height: 197px; }

        @media (max-width: 768px) {
            .logement-gallery { grid-template-columns: 1fr; }
            .main-image { min-height: 250px; }
        }

        .logement-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 968px) {
            .logement-content { grid-template-columns: 1fr; }
        }

        .logement-info h2 {
            color: var(--bleu-frenchy);
            margin: 2rem 0 1rem;
            font-size: 1.4rem;
        }

        .logement-description {
            line-height: 1.8;
            color: var(--gris-fonce);
        }

        .equipements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 0.8rem;
            margin-top: 1rem;
        }

        .equipement-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem;
            background: var(--gris-clair);
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .sidebar-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: sticky;
            top: 100px;
        }

        .price-display {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .price-display .price {
            font-size: 2rem;
            font-weight: bold;
            color: var(--bleu-frenchy);
        }

        .price-display .per-night {
            color: #6B7280;
        }

        .features-list {
            margin-bottom: 1.5rem;
        }

        .feature-item {
            display: flex;
            justify-content: space-between;
            padding: 0.8rem 0;
            border-bottom: 1px solid var(--gris-clair);
        }

        .cta-button {
            display: block;
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--bleu-frenchy), var(--bleu-clair));
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .cta-button:hover {
            transform: translateY(-2px);
        }

        .avis-section { margin-top: 3rem; }

        .avis-card {
            background: var(--gris-clair);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .avis-card .stars { color: #F59E0B; margin-bottom: 0.5rem; }
        .avis-card .author { font-weight: 600; color: var(--bleu-frenchy); }
        .avis-card .date { font-size: 0.85rem; color: #6B7280; }

        .autres-logements {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px solid var(--gris-clair);
        }

        .autres-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .autre-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .autre-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .autre-card .content {
            padding: 1rem;
        }

        .autre-card h3 a {
            color: var(--gris-fonce);
            text-decoration: none;
        }

        .autre-card h3 a:hover { color: var(--bleu-clair); }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="logement-container">
        <div class="logement-header">
            <nav class="breadcrumb">
                <a href="index.php">Accueil</a> / <a href="index.php#galerie">Logements</a> / <?= e($logement['titre']) ?>
            </nav>
            <h1 class="logement-title"><?= e($logement['titre']) ?></h1>
            <div class="logement-meta">
                <span>📍 <?= e($logement['localisation']) ?></span>
                <span>🏠 <?= e($logement['type_bien']) ?></span>
                <?php if ($logement['surface']): ?>
                <span>📐 <?= $logement['surface'] ?> m²</span>
                <?php endif; ?>
                <span>🛏️ <?= $logement['nb_chambres'] ?> chambre(s)</span>
                <span>👥 <?= $logement['nb_voyageurs'] ?> voyageur(s)</span>
            </div>
        </div>

        <!-- Galerie -->
        <div class="logement-gallery gallery-clickable" data-lightbox="logement">
            <img src="<?= e($logement['image']) ?>" alt="<?= e($logement['titre']) ?>" class="main-image">
            <?php
            $gallery = json_decode($logement['images_gallery'] ?? '[]', true);
            if (!empty($gallery)):
                foreach (array_slice($gallery, 0, 2) as $img):
            ?>
            <img src="<?= e($img) ?>" alt="<?= e($logement['titre']) ?>">
            <?php
                endforeach;
            endif;
            ?>
        </div>

        <div class="logement-content">
            <div class="logement-info">
                <h2>Description</h2>
                <div class="logement-description">
                    <?= nl2br(e($logement['description_longue'] ?? $logement['description'])) ?>
                </div>

                <?php
                $equipements = json_decode($logement['equipements'] ?? '[]', true);
                if (!empty($equipements)):
                ?>
                <h2>Équipements</h2>
                <div class="equipements-grid">
                    <?php foreach ($equipements as $equip): ?>
                    <div class="equipement-item">
                        <span>✓</span> <?= e($equip) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($avis)): ?>
                <div class="avis-section">
                    <h2>Avis des voyageurs</h2>
                    <?php foreach ($avis as $avi): ?>
                    <div class="avis-card">
                        <div class="stars"><?= renderStars($avi['note']) ?></div>
                        <p><?= e($avi['commentaire']) ?></p>
                        <p class="author"><?= e($avi['nom']) ?></p>
                        <p class="date"><?= formatDateFr($avi['date_avis']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <aside>
                <div class="sidebar-card">
                    <?php if ($logement['prix_nuit']): ?>
                    <div class="price-display">
                        <span class="price"><?= number_format($logement['prix_nuit'], 0, ',', ' ') ?> €</span>
                        <span class="per-night">/ nuit</span>
                    </div>
                    <?php endif; ?>

                    <div class="features-list">
                        <div class="feature-item">
                            <span>Type</span>
                            <strong><?= e($logement['type_bien']) ?></strong>
                        </div>
                        <div class="feature-item">
                            <span>Chambres</span>
                            <strong><?= $logement['nb_chambres'] ?></strong>
                        </div>
                        <div class="feature-item">
                            <span>Voyageurs max</span>
                            <strong><?= $logement['nb_voyageurs'] ?></strong>
                        </div>
                        <?php if ($logement['surface']): ?>
                        <div class="feature-item">
                            <span>Surface</span>
                            <strong><?= $logement['surface'] ?> m²</strong>
                        </div>
                        <?php endif; ?>
                        <?php if ($logement['note_moyenne'] > 0): ?>
                        <div class="feature-item">
                            <span>Note</span>
                            <strong><?= number_format($logement['note_moyenne'], 1) ?>/5 (<?= $logement['nb_avis'] ?> avis)</strong>
                        </div>
                        <?php endif; ?>
                    </div>

                    <a href="index.php#contact" class="cta-button">Nous contacter</a>
                </div>
            </aside>
        </div>

        <?php if (!empty($autres_logements)): ?>
        <section class="autres-logements">
            <h2 style="color: var(--bleu-frenchy); margin-bottom: 1.5rem;">Autres logements</h2>
            <div class="autres-grid">
                <?php foreach ($autres_logements as $autre): ?>
                <div class="autre-card">
                    <a href="logement.php?slug=<?= e($autre['slug']) ?>">
                        <img src="<?= e($autre['image']) ?>" alt="<?= e($autre['titre']) ?>">
                    </a>
                    <div class="content">
                        <h3><a href="logement.php?slug=<?= e($autre['slug']) ?>"><?= e($autre['titre']) ?></a></h3>
                        <p style="color: #6B7280; font-size: 0.9rem;"><?= e($autre['localisation']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
