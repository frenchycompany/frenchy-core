<?php
/**
 * Mode d'emploi — WiFi
 * Accessible via QR code
 */
require_once __DIR__ . '/includes/guide-layout.php';

$g = 'guide_wifi';
guide_head(vf_text($txt, $g, 'title', 'Connexion WiFi'), $site, $font_url, $css_vars);
?>

<div class="guide-hero">
    <div class="guide-icon">
        <svg viewBox="0 0 24 24"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1" fill="currentColor" stroke="none"/></svg>
    </div>
    <h1 class="guide-title"><?= vf_text($txt, $g, 'title', 'Connexion WiFi') ?></h1>
    <p class="guide-subtitle"><?= vf_text($txt, $g, 'subtitle', 'Accédez à Internet pendant votre séjour') ?></p>
</div>

<?= vf_guide_photos($db_photos, 'guide_wifi') ?>

<div class="guide-highlight">
    <p class="guide-highlight-label">Nom du réseau</p>
    <p class="guide-highlight-value"><?= htmlspecialchars(vf_text($txt, $g, 'network_name', 'Chateau-Vertefeuille')) ?></p>
</div>

<div class="guide-highlight">
    <p class="guide-highlight-label">Mot de passe</p>
    <p class="guide-highlight-value guide-highlight-large"><?= htmlspecialchars(vf_text($txt, $g, 'password', 'Vertefeuille2025')) ?></p>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">1</span> Sur votre appareil</h2>
    <p><?= vf_text($txt, $g, 'step1', 'Ouvrez les réglages WiFi de votre téléphone, tablette ou ordinateur.') ?></p>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">2</span> Sélectionnez le réseau</h2>
    <p><?= vf_text($txt, $g, 'step2', 'Recherchez <strong>Chateau-Vertefeuille</strong> dans la liste des réseaux disponibles.') ?></p>
</div>

<div class="guide-card">
    <h2 class="guide-card-title"><span class="card-num">3</span> Entrez le mot de passe</h2>
    <p><?= vf_text($txt, $g, 'step3', 'Saisissez le mot de passe ci-dessus et validez. La connexion s\'établit en quelques secondes.') ?></p>
</div>

<div class="guide-alert guide-alert--info">
    <span class="guide-alert-icon">&#x2139;&#xFE0F;</span>
    <div class="guide-alert-text">
        <strong>Besoin d'aide ?</strong>
        Contactez-nous au <?= htmlspecialchars($site['phone']) ?> si vous rencontrez des difficultés de connexion.
    </div>
</div>

<?php guide_foot($site); ?>
