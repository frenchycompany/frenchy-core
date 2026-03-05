<?php
/**
 * Guide Layout helpers
 * Shared header/footer for QR-code instruction pages.
 * Guides dynamiques chargés depuis la BDD (vf_guides + vf_guide_blocks).
 */

require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../db/helpers.php';

$property  = vf_load_property();
$settings  = vf_load_settings($conn);
$GLOBALS['_guide_conn'] = $conn;
$txt       = vf_load_texts($conn);
$db_photos = vf_load_photos($conn);
$site      = vf_build_site_config($settings);
$css_vars  = vf_build_css_vars($settings);
$font_url  = vf_build_font_url($settings);

if (!empty($db_photos['logo'][0]['file_path'])) {
    $site['logo'] = $db_photos['logo'][0]['file_path'];
}

/**
 * Convert newline-separated text to <li> items.
 */
function vf_lines_to_li($text) {
    $lines = array_filter(array_map('trim', explode("\n", $text)));
    $out = '';
    foreach ($lines as $line) {
        $out .= '<li>' . $line . '</li>';
    }
    return $out;
}

/**
 * Render photos for a guide section as a gallery strip.
 */
function vf_guide_photos($db_photos, $group) {
    if (empty($db_photos[$group])) return '';
    $out = '<div class="guide-photos">';
    foreach ($db_photos[$group] as $p) {
        if (!empty($p['file_path']) && file_exists($p['file_path'])) {
            $alt = htmlspecialchars($p['alt_text'] ?? '');
            $out .= '<figure class="guide-photo">';
            $out .= '<img src="' . htmlspecialchars($p['file_path']) . '" alt="' . $alt . '" loading="lazy">';
            if (!empty($p['alt_text'])) {
                $out .= '<figcaption>' . $alt . '</figcaption>';
            }
            $out .= '</figure>';
        }
    }
    $out .= '</div>';
    return $out;
}

/**
 * Rend un bloc de guide dynamique.
 * Types : text, highlight, steps, list, alert
 *
 * @param array $block   Row from vf_guide_blocks
 * @param int   &$step_n Compteur auto-incrémenté pour les blocs "steps"
 */
function vf_render_block($block, &$step_n) {
    $type    = $block['block_type'] ?? 'text';
    $title   = $block['block_title'] ?? '';
    $content = $block['block_content'] ?? '';

    switch ($type) {

        case 'highlight':
            echo '<div class="guide-highlight">';
            if ($title) {
                echo '<p class="guide-highlight-label">' . htmlspecialchars($title) . '</p>';
            }
            echo '<p class="guide-highlight-value">' . htmlspecialchars($content) . '</p>';
            echo '</div>';
            break;

        case 'steps':
            $step_n++;
            echo '<div class="guide-card">';
            echo '<h2 class="guide-card-title"><span class="card-num">' . $step_n . '</span> ' . htmlspecialchars($title) . '</h2>';
            if ($content) {
                echo '<ol>' . vf_lines_to_li($content) . '</ol>';
            }
            echo '</div>';
            break;

        case 'list':
            echo '<div class="guide-card">';
            if ($title) {
                echo '<h2 class="guide-card-title">' . htmlspecialchars($title) . '</h2>';
            }
            if ($content) {
                echo '<ul>' . vf_lines_to_li($content) . '</ul>';
            }
            echo '</div>';
            break;

        case 'alert':
            $severity = in_array($title, ['warning', 'info', 'danger']) ? $title : 'info';
            $icons = [
                'warning' => '&#x26A0;&#xFE0F;',
                'info'    => '&#x2139;&#xFE0F;',
                'danger'  => '&#x1F6D1;',
            ];
            echo '<div class="guide-alert guide-alert--' . $severity . '">';
            echo '<span class="guide-alert-icon">' . $icons[$severity] . '</span>';
            echo '<div class="guide-alert-text">' . $content . '</div>';
            echo '</div>';
            break;

        case 'text':
        default:
            echo '<div class="guide-card">';
            if ($title) {
                echo '<h2 class="guide-card-title">' . htmlspecialchars($title) . '</h2>';
            }
            if ($content) {
                echo '<p>' . nl2br(htmlspecialchars($content)) . '</p>';
            }
            echo '</div>';
            break;
    }
}

function guide_head($page_title, $site, $font_url, $css_vars) {
    $GLOBALS['_guide_current'] = isset($_GET['slug']) ? $_GET['slug'] : 'guide';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — <?= htmlspecialchars($site['name']) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1D5345">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="<?= htmlspecialchars($font_url) ?>" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/guide.css">
    <style><?= $css_vars ?></style>
</head>
<body>

<header class="guide-topbar">
    <?php if (!empty($site['logo']) && file_exists($site['logo'])): ?>
        <img src="<?= htmlspecialchars($site['logo']) ?>" alt="Logo" class="guide-topbar-logo">
    <?php else: ?>
        <svg class="guide-topbar-monogram" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <circle cx="20" cy="20" r="19" fill="none" stroke="rgba(255,255,255,.3)" stroke-width="1.5"/>
            <text x="20" y="26" text-anchor="middle" fill="currentColor" font-family="Georgia,serif" font-size="16" font-weight="700"><?= htmlspecialchars($site['monogram'] ?? 'ML') ?></text>
        </svg>
    <?php endif; ?>
    <div>
        <div class="guide-topbar-name"><?= htmlspecialchars($site['name']) ?></div>
        <div class="guide-topbar-sub"><?= htmlspecialchars($site['location']) ?></div>
    </div>
</header>

<main class="guide-main">
<?php
}

function guide_foot($site) {
    $current = $GLOBALS['_guide_current'] ?? '';
    $conn    = $GLOBALS['_guide_conn'] ?? null;
    $guides  = vf_load_active_guides($conn);

    $accueil_icon = '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/><path d="M8 7h6"/><path d="M8 11h8"/>';
?>
</main>

<nav class="guide-nav" aria-label="Navigation guides">
    <a href="guide.php" class="guide-nav-item<?= $current === 'guide' ? ' is-active' : '' ?>" title="Accueil">
        <svg viewBox="0 0 24 24"><?= $accueil_icon ?></svg>
        <span>Accueil</span>
    </a>
    <?php foreach ($guides as $slug => $g): ?>
        <a href="guide.php?slug=<?= htmlspecialchars($slug) ?>" class="guide-nav-item<?= $current === $slug ? ' is-active' : '' ?>" title="<?= htmlspecialchars($g['title']) ?>">
            <svg viewBox="0 0 24 24"><?= $g['icon_svg'] ?></svg>
            <span><?= htmlspecialchars($g['title']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<footer class="guide-footer">
    <a href="/">&larr; Retour au site</a>
    <p class="guide-footer-copy">&copy; <?= $site['year'] ?> <?= htmlspecialchars($site['name']) ?></p>
</footer>

<script>
if('serviceWorker' in navigator){navigator.serviceWorker.register('/sw.js').catch(function(){});}
</script>
</body>
</html>
<?php
}
