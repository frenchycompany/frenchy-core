<?php
/**
 * Guide des équipements — Dispatcher dynamique
 *
 * Sans ?slug : page index (liste des guides actifs)
 * Avec ?slug=xxx : rendu dynamique du guide depuis la BDD
 */
require_once __DIR__ . '/includes/guide-layout.php';

$slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9_-]/', '', strtolower($_GET['slug'])) : '';

if ($slug === '') {
    // ═══ MODE INDEX ═══
    $guides = vf_load_active_guides($conn);

    guide_head('Guide des équipements', $site, $font_url, $css_vars);
?>

<div class="guide-hero">
    <div class="guide-icon">
        <svg viewBox="0 0 24 24"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/><path d="M8 7h6"/><path d="M8 11h8"/></svg>
    </div>
    <h1 class="guide-title">Guide des équipements</h1>
    <p class="guide-subtitle">Tout ce qu'il faut savoir pour profiter pleinement de votre séjour</p>
</div>

<?php if (empty($guides)): ?>
<div class="guide-card">
    <p>Aucun guide disponible pour le moment.</p>
</div>
<?php else: ?>
<?php foreach ($guides as $g_slug => $g): ?>
<a href="guide.php?slug=<?= htmlspecialchars($g_slug) ?>" class="guide-link-card">
    <div class="guide-link-icon">
        <svg viewBox="0 0 24 24"><?= $g['icon_svg'] ?></svg>
    </div>
    <div class="guide-link-text">
        <h2><?= htmlspecialchars($g['title']) ?></h2>
        <?php if (!empty($g['subtitle'])): ?>
        <p><?= htmlspecialchars($g['subtitle']) ?></p>
        <?php endif; ?>
    </div>
    <span class="guide-link-arrow">&rarr;</span>
</a>
<?php endforeach; ?>
<?php endif; ?>

<hr class="guide-sep">

<div class="guide-alert guide-alert--info">
    <span class="guide-alert-icon">&#x2139;&#xFE0F;</span>
    <div class="guide-alert-text">
        <strong>Une question ?</strong>
        N'hésitez pas à nous contacter au <?= htmlspecialchars($site['phone']) ?> ou par email à <?= htmlspecialchars($site['email']) ?>.
    </div>
</div>

<?php
    guide_foot($site);

} else {
    // ═══ MODE GUIDE ═══
    $guide = vf_load_guide($conn, $slug);

    if (!$guide || !$guide['is_active']) {
        header('Location: guide.php');
        exit;
    }

    $blocks = vf_load_guide_blocks($conn, $slug);

    guide_head($guide['title'], $site, $font_url, $css_vars);
?>

<div class="guide-hero">
    <?php if (!empty($guide['icon_svg'])): ?>
    <div class="guide-icon">
        <svg viewBox="0 0 24 24"><?= $guide['icon_svg'] ?></svg>
    </div>
    <?php endif; ?>
    <h1 class="guide-title"><?= htmlspecialchars($guide['title']) ?></h1>
    <?php if (!empty($guide['subtitle'])): ?>
    <p class="guide-subtitle"><?= htmlspecialchars($guide['subtitle']) ?></p>
    <?php endif; ?>
</div>

<?= vf_guide_photos($db_photos, 'guide_' . $slug) ?>

<?php
    $step_n = 0;
    foreach ($blocks as $block) {
        vf_render_block($block, $step_n);
    }

    if (empty($blocks)) {
        echo '<div class="guide-card"><p>Ce guide est en cours de préparation.</p></div>';
    }
?>

<div class="guide-alert guide-alert--info">
    <span class="guide-alert-icon">&#x2139;&#xFE0F;</span>
    <div class="guide-alert-text">
        <strong>Besoin d'aide ?</strong>
        Contactez-nous au <?= htmlspecialchars($site['phone']) ?> pour toute question.
    </div>
</div>

<?php
    guide_foot($site);
}
?>
