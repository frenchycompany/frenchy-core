<?php
/**
 * Mode d'emploi — Cuisine
 * Accessible via QR code
 */
require_once __DIR__ . '/includes/guide-layout.php';

$g = 'guide_cuisine';
guide_head(vf_text($txt, $g, 'title', 'La Cuisine'), $site, $font_url, $css_vars);
?>

<div class="guide-hero">
    <div class="guide-icon">
        <svg viewBox="0 0 24 24"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3zm0 0v7"/></svg>
    </div>
    <h1 class="guide-title"><?= vf_text($txt, $g, 'title', 'La Cuisine') ?></h1>
    <p class="guide-subtitle"><?= vf_text($txt, $g, 'subtitle', 'Tout pour préparer vos repas en toute autonomie') ?></p>
</div>

<?= vf_guide_photos($db_photos, 'guide_cuisine') ?>

<div class="guide-card">
    <h2 class="guide-card-title">Équipements disponibles</h2>
    <ul><?= vf_lines_to_li(vf_text($txt, $g, 'equipements', "Four encastrable\nPlaques à induction\nMicro-ondes\nLave-vaisselle\nRéfrigérateur / congélateur\nCafetière Nespresso\nBouilloire\nGrille-pain\nRobot mixeur")) ?></ul>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">1</span> Utiliser le four</h2>
    <ol><?= vf_lines_to_li(vf_text($txt, $g, 'four', "Tournez le sélecteur de mode sur le pictogramme souhaité (chaleur tournante recommandée)\nRéglez la température avec le second sélecteur\nLe voyant s'éteint lorsque la température est atteinte\nAprès utilisation, éteignez le four en remettant les deux sélecteurs sur 0")) ?></ol>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">2</span> Plaques à induction</h2>
    <ol><?= vf_lines_to_li(vf_text($txt, $g, 'induction', "Appuyez sur le bouton <strong>ON/OFF</strong> du panneau tactile\nSélectionnez la zone de cuisson en appuyant sur le <strong>+</strong> ou <strong>-</strong> correspondant\nRéglez la puissance de 1 à 9\nUn signal sonore retentit à l'extinction")) ?></ol>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">3</span> Machine Nespresso</h2>
    <ol><?= vf_lines_to_li(vf_text($txt, $g, 'nespresso', "Remplissez le réservoir d'eau à l'arrière de la machine\nAllumez la machine — elle chauffe en 25 secondes\nInsérez une capsule et placez votre tasse\nAppuyez sur le bouton petit ou grand café\nLes capsules usagées vont dans le bac intégré")) ?></ol>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">4</span> Lave-vaisselle</h2>
    <ol><?= vf_lines_to_li(vf_text($txt, $g, 'lave_vaisselle', "Chargez la vaisselle (assiettes en bas, verres en haut)\nAjoutez une pastille dans le compartiment de la porte\nSélectionnez le programme <strong>Eco</strong> (bouton 2) pour un usage quotidien\nAppuyez sur <strong>Start</strong>")) ?></ol>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">5</span> Tri des déchets</h2>
    <ul><?= vf_lines_to_li(vf_text($txt, $g, 'tri', "Poubelle grise : déchets ménagers\nPoubelle jaune : emballages, plastiques, cartons\nBac vert : verre\nCompost (jardin) : épluchures, marc de café")) ?></ul>
</div>

<div class="guide-alert guide-alert--info">
    <span class="guide-alert-icon">&#x2139;&#xFE0F;</span>
    <div class="guide-alert-text">
        <strong>Fin de séjour</strong>
        <?= vf_text($txt, $g, 'consignes', 'Merci de laisser la cuisine propre et rangée en fin de séjour. Le lave-vaisselle doit être vidé. Les poubelles pleines peuvent être déposées dans le local poubelles à l\'extérieur (côté garage).') ?>
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
