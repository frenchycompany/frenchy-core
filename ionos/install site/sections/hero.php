<!-- ═══════════════ HERO ═══════════════ -->
<section class="vf-hero">
    <img
        src="<?= htmlspecialchars($hero_img) ?>"
        alt="<?= htmlspecialchars($site['name']) ?>"
        class="vf-hero-img"
    >
    <div class="vf-hero-overlay"></div>

    <div class="vf-hero-content">
        <div class="vf-container">
            <p class="vf-hero-kicker"><?= htmlspecialchars(vf_text($txt, 'hero', 'kicker', 'Confort · Charme · Détente')) ?></p>
            <h1 class="vf-hero-title">
                <?= vf_text($txt, 'hero', 'title', 'Votre séjour<br>commence ici') ?>
            </h1>
            <p class="vf-hero-desc">
                <?= htmlspecialchars(vf_text($txt, 'hero', 'desc', 'Un lieu pensé pour se retrouver, se détendre et profiter. Idéal pour des vacances en famille, entre amis ou en amoureux.')) ?>
            </p>
            <div class="vf-hero-actions">
                <a class="vf-btn vf-btn-primary" href="#reserver"><?= htmlspecialchars(vf_text($txt, 'hero', 'cta1', 'Voir les disponibilités')) ?></a>
                <a class="vf-btn vf-btn-outline" href="#visite"><?= htmlspecialchars(vf_text($txt, 'hero', 'cta2', 'Explorer en 360°')) ?></a>
            </div>
        </div>
    </div>
</section>

<!-- Organic curve : hero → band -->
<div class="vf-curve vf-curve--green" aria-hidden="true">
    <svg viewBox="0 0 1440 64" preserveAspectRatio="none">
        <path d="M0,40 C240,64 480,16 720,32 C960,48 1200,8 1440,28 L1440,64 L0,64 Z"/>
    </svg>
</div>
