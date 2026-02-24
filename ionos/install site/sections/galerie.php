<!-- ═══════════════ GALERIE ═══════════════ -->
<section id="galerie" class="vf-section vf-section--beige">
    <div class="vf-container">
        <h2 class="vf-heading vf-heading--center"><?= htmlspecialchars(vf_text($txt, 'galerie', 'title', 'Galerie')) ?></h2>
        <p class="vf-subheading">
            <?= htmlspecialchars(vf_text($txt, 'galerie', 'subtitle', 'Découvrez le lieu en images.')) ?>
        </p>

        <div class="vf-gallery">
            <?php foreach ($galerie_items as $photo): ?>
            <figure class="vf-gallery-item<?= !empty($photo['wide']) ? ' vf-gallery-item--wide' : '' ?>">
                <img
                    src="<?= htmlspecialchars($photo['url']) ?>"
                    alt="<?= htmlspecialchars($photo['alt']) ?>"
                    <?= !empty($photo['srcset']) ? $photo['srcset'] : '' ?>
                    loading="lazy"
                >
            </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Organic curve : galerie → visite -->
<div class="vf-curve vf-curve--white" aria-hidden="true">
    <svg viewBox="0 0 1440 64" preserveAspectRatio="none">
        <path d="M0,40 C360,64 720,8 1080,36 C1260,50 1380,20 1440,28 L1440,64 L0,64 Z"/>
    </svg>
</div>
