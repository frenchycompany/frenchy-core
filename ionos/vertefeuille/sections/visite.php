<!-- ═══════════════ VISITE VIRTUELLE ═══════════════ -->
<section id="visite" class="vf-section vf-section--white">
    <div class="vf-container">
        <h2 class="vf-heading vf-heading--center"><?= htmlspecialchars(vf_text($txt, 'visite', 'title', 'Visite virtuelle 360°')) ?></h2>
        <p class="vf-subheading">
            <?= htmlspecialchars(vf_text($txt, 'visite', 'subtitle', 'Parcourez le château comme si vous y étiez.')) ?>
        </p>

        <div class="vf-embed-wrap">
            <iframe
                src="https://my.matterport.com/show/?m=<?= htmlspecialchars($site['matterport']) ?>"
                class="vf-embed-iframe"
                frameborder="0"
                allowfullscreen
                allow="xr-spatial-tracking"
                loading="lazy"
                title="Visite virtuelle 360° — <?= htmlspecialchars($site['name']) ?>"
            ></iframe>
        </div>
    </div>
</section>

<!-- Organic curve : visite → réservation (green) -->
<div class="vf-curve vf-curve--green" aria-hidden="true">
    <svg viewBox="0 0 1440 64" preserveAspectRatio="none">
        <path d="M0,40 C240,64 480,16 720,32 C960,48 1200,8 1440,28 L1440,64 L0,64 Z"/>
    </svg>
</div>
