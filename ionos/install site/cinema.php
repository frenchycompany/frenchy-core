<?php
/**
 * Mode d'emploi — Cinéma
 * Accessible via QR code
 */
require_once __DIR__ . '/includes/guide-layout.php';

$g = 'guide_cinema';
guide_head(vf_text($txt, $g, 'title', 'Salle Cinéma'), $site, $font_url, $css_vars);
?>

<div class="guide-hero">
    <div class="guide-icon">
        <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 8h20"/><path d="M8 4v4"/><path d="M16 4v4"/><polygon points="10,11 10,17 15,14" fill="currentColor" stroke="none"/></svg>
    </div>
    <h1 class="guide-title"><?= vf_text($txt, $g, 'title', 'Salle Cinéma') ?></h1>
    <p class="guide-subtitle"><?= vf_text($txt, $g, 'subtitle', 'Votre cinéma privé') ?></p>
</div>

<?= vf_guide_photos($db_photos, 'guide_cinema') ?>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">1</span> Allumer le système</h2>
    <ol><?= vf_lines_to_li(vf_text($txt, $g, 'allumer', "Prenez la télécommande <strong>principale</strong> (marquée \"HOME CINEMA\")\nAppuyez sur le bouton <strong>ON</strong> — le vidéoprojecteur et le système audio s'allument automatiquement\nPatientez environ <strong>30 secondes</strong> le temps du démarrage\nL'écran descend automatiquement")) ?></ol>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">2</span> Choisir votre contenu</h2>
    <p>Plusieurs options sont disponibles :</p>
    <ul><?= vf_lines_to_li(vf_text($txt, $g, 'contenu', "<strong>Netflix / Disney+ / Prime Video</strong> — Utilisez la télécommande pour naviguer dans les applications. Les comptes sont déjà connectés.\n<strong>YouTube</strong> — Diffusez depuis votre téléphone via Chromecast.\n<strong>HDMI</strong> — Branchez votre appareil sur le câble HDMI disponible à côté du canapé.")) ?></ul>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">3</span> Régler le son</h2>
    <div class="guide-info-grid">
        <span class="guide-info-label">Volume</span>
        <span class="guide-info-value"><?= vf_text($txt, $g, 'son_label', 'Touches <strong>VOL +/-</strong> de la télécommande principale') ?></span>
        <span class="guide-info-label">Niveau conseillé</span>
        <span class="guide-info-value"><?= vf_text($txt, $g, 'son_niveau', 'Entre 25 et 40') ?></span>
    </div>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">4</span> Éteindre le système</h2>
    <ol><?= vf_lines_to_li(vf_text($txt, $g, 'eteindre', "Appuyez sur le bouton <strong>OFF</strong> de la télécommande principale\nLe vidéoprojecteur s'éteint et l'écran remonte automatiquement\nVeuillez ne pas débrancher les appareils")) ?></ol>
</div>

<div class="guide-alert guide-alert--warning">
    <span class="guide-alert-icon">&#x26A0;&#xFE0F;</span>
    <div class="guide-alert-text">
        <strong>Important</strong>
        <?= vf_text($txt, $g, 'avertissement', 'Ne touchez jamais l\'objectif du vidéoprojecteur. Éteignez le système après utilisation pour préserver la durée de vie de la lampe.') ?>
    </div>
</div>

<div class="guide-alert guide-alert--info">
    <span class="guide-alert-icon">&#x2139;&#xFE0F;</span>
    <div class="guide-alert-text">
        <strong>Besoin d'aide ?</strong>
        Contactez-nous au <?= htmlspecialchars($site['phone']) ?> si le système ne répond pas.
    </div>
</div>

<?php guide_foot($site); ?>
