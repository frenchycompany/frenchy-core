<?php
/**
 * Guide des équipements — Page d'index
 * Regroupe tous les modes d'emploi actifs.
 * Les guides sont définis dans config/property.php
 */
require_once __DIR__ . '/includes/guide-layout.php';

$guides = vf_get_active_guides(vf_load_settings($conn));

guide_head('Guide des équipements', $site, $font_url, $css_vars);
?>

<div class="guide-hero">
    <div class="guide-icon">
        <svg viewBox="0 0 24 24"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/><path d="M8 7h6"/><path d="M8 11h8"/></svg>
    </div>
    <h1 class="guide-title">Guide des équipements</h1>
    <p class="guide-subtitle">Tout ce qu'il faut savoir pour profiter pleinement de votre séjour au <?= htmlspecialchars($site['name']) ?></p>
</div>

<?php foreach ($guides as $slug => $guide_cfg): ?>
<a href="<?= $slug ?>.php" class="guide-link-card">
    <div class="guide-link-icon">
        <svg viewBox="0 0 24 24"><?= $guide_cfg['icon'] ?></svg>
    </div>
    <div class="guide-link-text">
        <h2><?= vf_text($txt, 'guide_' . $slug, 'title', $guide_cfg['label']) ?></h2>
        <p><?= vf_text($txt, 'guide_' . $slug, 'subtitle', '') ?></p>
    </div>
    <span class="guide-link-arrow">&rarr;</span>
</a>
<?php endforeach; ?>

<hr class="guide-sep">

<div class="guide-alert guide-alert--info">
    <span class="guide-alert-icon">&#x2139;&#xFE0F;</span>
    <div class="guide-alert-text">
        <strong>Une question ?</strong>
        N'hésitez pas à nous contacter au <?= htmlspecialchars($site['phone']) ?> ou par email à <?= htmlspecialchars($site['email']) ?>.
    </div>
</div>

<?php guide_foot($site); ?>
