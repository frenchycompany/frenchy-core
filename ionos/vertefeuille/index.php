<?php
/**
 * Site vitrine — Page principale
 * Heritage premium · Minimaliste · Élégant
 *
 * Les données (textes, couleurs, photos) sont chargées depuis la BDD.
 * Fallback sur config/property.php si la BDD est indisponible.
 */

// ── DB + Helpers ──
require_once __DIR__ . '/db/connection.php';
require_once __DIR__ . '/db/helpers.php';

// ── Config propriété ──
$property  = vf_load_property();

// ── Charger depuis la BDD ──
$settings  = vf_load_settings($conn);
$txt       = vf_load_texts($conn);
$db_photos = vf_load_photos($conn);

// ── Config site (compatible templates) ──
$site = vf_build_site_config($settings);

// ── Logo depuis la BDD ──
if (!empty($db_photos['logo'][0]['file_path'])) {
    $site['logo'] = $db_photos['logo'][0]['file_path'];
}

// ── Photos avec fallbacks depuis config ──
$fallbacks = $property['photo_fallbacks'] ?? [];

$hero_img = vf_photo_url($db_photos, 'hero', 'facade-chateau',
    $fallbacks['hero'] ?? 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?w=2000&q=80');

// Galerie : BDD photos, sinon fallbacks
if (!empty($db_photos['galerie'])) {
    $galerie_items = [];
    foreach ($db_photos['galerie'] as $p) {
        $galerie_items[] = [
            'url'    => $p['file_path'],
            'alt'    => $p['alt_text'],
            'wide'   => (bool)$p['is_wide'],
            'srcset' => vf_srcset($p),
        ];
    }
} else {
    $galerie_items = $fallbacks['galerie'] ?? [];
}

// Expérience photos
$exp_fallbacks = $fallbacks['experience'] ?? [];
$exp_photos = [
    'confort' => vf_photo_url($db_photos, 'experience', 'confort', $exp_fallbacks['confort'] ?? ''),
    'charme'  => vf_photo_url($db_photos, 'experience', 'charme',  $exp_fallbacks['charme']  ?? ''),
    'accueil' => vf_photo_url($db_photos, 'experience', 'accueil', $exp_fallbacks['accueil'] ?? ''),
];

// ── CSS & Fonts dynamiques ──
$css_vars = vf_build_css_vars($settings);
$font_url = vf_build_font_url($settings);

// ── Sections actives (depuis la BDD, sinon toutes du config) ──
$active_sections = vf_get_active_sections($settings);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site['name']) ?> — <?= htmlspecialchars($site['tagline']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($site['name']) ?> à <?= htmlspecialchars($site['address']) ?> — Séjour d'exception. Héritage, élégance et confort.">
    <meta name="theme-color" content="<?= htmlspecialchars(vf_get($settings, 'color_green', '#1D5345')) ?>">

    <!-- Open Graph (Facebook, WhatsApp, iMessage...) -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars($site['name']) ?> — <?= htmlspecialchars($site['tagline']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($site['name']) ?> à <?= htmlspecialchars($site['address']) ?> — Séjour d'exception.">
    <meta property="og:image" content="<?= htmlspecialchars($hero_img) ?>">
    <meta property="og:locale" content="fr_FR">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($site['name']) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($site['tagline']) ?> — <?= htmlspecialchars($site['address']) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($hero_img) ?>">

    <!-- Canonical -->
    <link rel="canonical" href="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/') ?>">

    <!-- Favicon -->
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">

    <!-- Fonts (dynamique) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="<?= htmlspecialchars($font_url) ?>" rel="stylesheet">

    <!-- CSS (defaults dans style.css) -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- CSS vars dynamiques (couleurs + typo depuis la BDD — override les defaults) -->
    <style><?= $css_vars ?></style>

    <!-- Airbnb SDK -->
    <?php if (!empty($site['airbnb_id'])): ?>
    <script async src="https://www.airbnb.fr/embeddable/airbnb_jssdk"></script>
    <?php endif; ?>
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <main>
        <?php foreach ($active_sections as $section_key => $section_cfg): ?>
            <?php
                $section_file = __DIR__ . "/sections/{$section_key}.php";
                if (file_exists($section_file)) {
                    include $section_file;
                }
            ?>
        <?php endforeach; ?>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
</body>
</html>
