<?php
/**
 * Mode d'emploi — Salle de Sport
 * Accessible via QR code
 */
require_once __DIR__ . '/includes/guide-layout.php';

$g = 'guide_sport';
guide_head(vf_text($txt, $g, 'title', 'Salle de Sport'), $site, $font_url, $css_vars);
?>

<div class="guide-hero">
    <div class="guide-icon">
        <svg viewBox="0 0 24 24"><path d="M6.5 6.5L17.5 17.5"/><path d="M2 12h4m12 0h4"/><path d="M6 8v8"/><path d="M18 8v8"/><path d="M4 10v4"/><path d="M20 10v4"/></svg>
    </div>
    <h1 class="guide-title"><?= vf_text($txt, $g, 'title', 'Salle de Sport') ?></h1>
    <p class="guide-subtitle"><?= vf_text($txt, $g, 'subtitle', 'Restez actif pendant votre séjour') ?></p>
</div>

<?= vf_guide_photos($db_photos, 'guide_sport') ?>

<div class="guide-card">
    <h2 class="guide-card-title">Équipements disponibles</h2>
    <ul><?= vf_lines_to_li(vf_text($txt, $g, 'equipements', "Tapis de course\nVélo elliptique\nBanc de musculation\nHaltères (paire de 2 kg à 20 kg)\nTapis de yoga et élastiques de résistance\nBallon de gym")) ?></ul>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">1</span> Tapis de course</h2>
    <ol><?= vf_lines_to_li(vf_text($txt, $g, 'tapis', "Branchez le tapis à la prise (interrupteur à l'arrière)\nMontez sur les rails latéraux <strong>avant</strong> de démarrer\nAppuyez sur <strong>START</strong> — la bande démarre lentement\nUtilisez les touches <strong>+</strong> et <strong>-</strong> pour régler vitesse et inclinaison\nAppuyez sur <strong>STOP</strong> pour terminer, attendez l'arrêt complet")) ?></ol>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">2</span> Vélo elliptique</h2>
    <ol><?= vf_lines_to_li(vf_text($txt, $g, 'velo', "Montez sur les pédales en vous tenant aux poignées fixes\nCommencez à pédaler — l'écran s'allume automatiquement\nRéglez la résistance avec les touches <strong>+</strong> / <strong>-</strong>")) ?></ol>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">3</span> Après votre séance</h2>
    <ul><?= vf_lines_to_li(vf_text($txt, $g, 'apres', "Essuyez les équipements avec les lingettes mises à disposition\nRangez les haltères sur leur support\nÉteignez le tapis de course (interrupteur arrière)")) ?></ul>
</div>

<div class="guide-alert guide-alert--info">
    <span class="guide-alert-icon">&#x2139;&#xFE0F;</span>
    <div class="guide-alert-text">
        <strong>Astuce</strong>
        Des serviettes et des bouteilles d'eau sont à votre disposition dans la salle.
    </div>
</div>

<div class="guide-alert guide-alert--warning">
    <span class="guide-alert-icon">&#x26A0;&#xFE0F;</span>
    <div class="guide-alert-text">
        <strong>En cas de dysfonctionnement</strong>
        N'essayez pas de réparer les équipements vous-même. Contactez-nous au <?= htmlspecialchars($site['phone']) ?>.
    </div>
</div>

<?php guide_foot($site); ?>
