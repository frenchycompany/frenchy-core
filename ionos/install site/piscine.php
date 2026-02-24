<?php
/**
 * Mode d'emploi — Piscine
 * Accessible via QR code
 */
require_once __DIR__ . '/includes/guide-layout.php';

$g = 'guide_piscine';
guide_head(vf_text($txt, $g, 'title', 'La Piscine'), $site, $font_url, $css_vars);
?>

<div class="guide-hero">
    <div class="guide-icon">
        <svg viewBox="0 0 24 24"><path d="M2 12h20"/><path d="M2 16c1.5 1 3 1.5 4.5 1s3-1.5 4.5-1 3 .5 4.5 1 3 0 4.5-1"/><path d="M2 20c1.5 1 3 1.5 4.5 1s3-1.5 4.5-1 3 .5 4.5 1 3 0 4.5-1"/><path d="M8 12V4m8 8V4"/><circle cx="8" cy="3" r="1" fill="currentColor" stroke="none"/><circle cx="16" cy="3" r="1" fill="currentColor" stroke="none"/></svg>
    </div>
    <h1 class="guide-title"><?= vf_text($txt, $g, 'title', 'La Piscine') ?></h1>
    <p class="guide-subtitle"><?= vf_text($txt, $g, 'subtitle', 'Profitez de notre piscine privée') ?></p>
</div>

<?= vf_guide_photos($db_photos, 'guide_piscine') ?>

<div class="guide-card">
    <h2 class="guide-card-title">Horaires d'accès</h2>
    <div class="guide-info-grid">
        <span class="guide-info-label">Disponibilité</span>
        <span class="guide-info-value">Toute la journée</span>
        <span class="guide-info-label">Température</span>
        <span class="guide-info-value"><?= vf_text($txt, $g, 'temperature', '28°C — chauffée') ?></span>
    </div>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">1</span> Avant la baignade</h2>
    <ul><?= vf_lines_to_li(vf_text($txt, $g, 'avant', "Prenez une douche avant d'entrer dans la piscine\nLes serviettes de piscine sont à votre disposition sur les transats\nAppliquez votre crème solaire au moins 15 minutes avant la baignade")) ?></ul>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">2</span> Pendant la baignade</h2>
    <ul><?= vf_lines_to_li(vf_text($txt, $g, 'pendant', "Les enfants doivent être accompagnés d'un adulte en permanence\nIl est interdit de plonger\nPas de nourriture ni de boissons en verre aux abords de la piscine")) ?></ul>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">3</span> Après la baignade</h2>
    <ul><?= vf_lines_to_li(vf_text($txt, $g, 'apres', "Veuillez replier les transats et ranger les serviettes\nRefermez la couverture de piscine si vous êtes les derniers à l'utiliser")) ?></ul>
</div>

<div class="guide-alert guide-alert--warning">
    <span class="guide-alert-icon">&#x26A0;&#xFE0F;</span>
    <div class="guide-alert-text">
        <strong>Sécurité</strong>
        <?= vf_text($txt, $g, 'securite', 'La piscine n\'est pas surveillée. La baignade est sous votre entière responsabilité. En cas d\'urgence, appelez le 15 (SAMU) ou le 112.') ?>
    </div>
</div>

<div class="guide-alert guide-alert--info">
    <span class="guide-alert-icon">&#x2139;&#xFE0F;</span>
    <div class="guide-alert-text">
        <strong>Besoin d'aide ?</strong>
        Contactez-nous au <?= htmlspecialchars($site['phone']) ?> pour toute question.
    </div>
</div>

<?php guide_foot($site); ?>
