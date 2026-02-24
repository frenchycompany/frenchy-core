<?php
/**
 * Mode d'emploi — Sauna
 * Accessible via QR code
 */
require_once __DIR__ . '/includes/guide-layout.php';

$g = 'guide_sauna';
guide_head(vf_text($txt, $g, 'title', 'Le Sauna'), $site, $font_url, $css_vars);
?>

<div class="guide-hero">
    <div class="guide-icon">
        <svg viewBox="0 0 24 24"><path d="M17 4a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" fill="currentColor" stroke="none"/><path d="M17 7c-2 0-3.5 1.5-3.5 3.5V14h7v-3.5C20.5 8.5 19 7 17 7Z"/><path d="M2 18c0 0 2-2 5-2s5 2 5 2"/><path d="M2 22c0 0 2-2 5-2s5 2 5 2"/><path d="M7 10v2"/><path d="M5 8.5c0 0 .5-2 2-2s2 2 2 2"/></svg>
    </div>
    <h1 class="guide-title"><?= vf_text($txt, $g, 'title', 'Le Sauna') ?></h1>
    <p class="guide-subtitle"><?= vf_text($txt, $g, 'subtitle', 'Un moment de détente absolue') ?></p>
</div>

<?= vf_guide_photos($db_photos, 'guide_sauna') ?>

<div class="guide-card">
    <h2 class="guide-card-title">Informations</h2>
    <div class="guide-info-grid">
        <span class="guide-info-label">Type</span>
        <span class="guide-info-value"><?= vf_text($txt, $g, 'type', 'Sauna traditionnel') ?></span>
        <span class="guide-info-label">Température</span>
        <span class="guide-info-value"><?= vf_text($txt, $g, 'temperature', '80°C — 90°C') ?></span>
        <span class="guide-info-label">Capacité</span>
        <span class="guide-info-value"><?= vf_text($txt, $g, 'capacite', '4 personnes') ?></span>
    </div>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">1</span> Mise en route</h2>
    <ol><?= vf_lines_to_li(vf_text($txt, $g, 'mise_en_route', "Appuyez sur le bouton <strong>ON</strong> du panneau de commande\nRéglez la température souhaitée (80°C recommandé)\nPatientez environ <strong>20 à 30 minutes</strong> le temps que le sauna atteigne la température")) ?></ol>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">2</span> Votre séance</h2>
    <ol><?= vf_lines_to_li(vf_text($txt, $g, 'seance', "Prenez une douche tiède avant d'entrer\nPlacez votre serviette sur la banquette (ne pas s'asseoir directement sur le bois)\nDurée recommandée : <strong>10 à 15 minutes</strong> par séance\nSortez et prenez une douche fraîche entre chaque séance\nHydratez-vous régulièrement")) ?></ol>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">3</span> Après la séance</h2>
    <ol><?= vf_lines_to_li(vf_text($txt, $g, 'apres', "Appuyez sur le bouton <strong>OFF</strong> du panneau de commande\nLaissez la porte entrouverte pour aérer\nEssuyez les banquettes avec votre serviette")) ?></ol>
</div>

<div class="guide-alert guide-alert--warning">
    <span class="guide-alert-icon">&#x26A0;&#xFE0F;</span>
    <div class="guide-alert-text">
        <strong>Précautions</strong>
        <?= vf_text($txt, $g, 'precautions', 'Déconseillé aux femmes enceintes, aux enfants de moins de 12 ans et aux personnes souffrant de problèmes cardiaques. Ne consommez pas d\'alcool avant ou pendant la séance.') ?>
    </div>
</div>

<div class="guide-alert guide-alert--danger">
    <span class="guide-alert-icon">&#x1F6D1;</span>
    <div class="guide-alert-text">
        <?= vf_text($txt, $g, 'danger', 'Ne versez jamais d\'huiles essentielles directement sur les pierres. Cela peut endommager le poêle et créer des vapeurs irritantes.') ?>
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
