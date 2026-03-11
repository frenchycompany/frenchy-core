<!-- ═══════════════ PLANNING / DISPONIBILITÉS ═══════════════ -->
<section id="planning" class="vf-section vf-section--green">
    <div class="vf-container vf-section-narrow">
        <h2 class="vf-heading vf-heading--center vf-heading--light"><?= htmlspecialchars(vf_text($txt, 'planning', 'title', 'Disponibilités')) ?></h2>
        <p class="vf-subheading vf-subheading--light">
            <?= htmlspecialchars(vf_text($txt, 'planning', 'subtitle', 'Consultez le calendrier de disponibilité du logement.')) ?>
        </p>

        <?php if (!empty($site['superhote_planning_url'])): ?>
        <div class="vf-planning-embed">
            <iframe
                src="<?= htmlspecialchars($site['superhote_planning_url']) ?>"
                frameborder="0"
                scrolling="auto"
                style="width:100%; min-height:500px; border:none; border-radius:12px; background:#fff;"
                loading="lazy"
                title="Calendrier de disponibilité"
                sandbox="allow-scripts allow-same-origin allow-popups allow-forms"
            ></iframe>
        </div>
        <?php else: ?>
        <div class="vf-planning-placeholder">
            <p style="text-align:center; opacity:0.7; font-style:italic;">
                Le calendrier de disponibilité sera bientôt disponible.
            </p>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
.vf-planning-embed {
    margin: 2rem auto;
    max-width: 800px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}
.vf-planning-embed iframe {
    display: block;
}
.vf-planning-placeholder {
    margin: 2rem auto;
    max-width: 600px;
    padding: 2rem;
    background: rgba(255,255,255,0.1);
    border-radius: 12px;
}
</style>

<!-- Organic curve : planning → reservation -->
<div class="vf-curve vf-curve--beige-from-green" aria-hidden="true">
    <svg viewBox="0 0 1440 64" preserveAspectRatio="none">
        <path d="M0,0 L1440,0 L1440,24 C1200,56 960,0 720,32 C480,64 240,8 0,36 Z"/>
    </svg>
</div>
