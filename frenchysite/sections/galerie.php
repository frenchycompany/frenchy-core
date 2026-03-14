<!-- ═══════════════ GALERIE ═══════════════ -->
<section id="galerie" class="vf-section vf-section--beige">
    <div class="vf-container">
        <h2 class="vf-heading vf-heading--center"><?= htmlspecialchars(vf_text($txt, 'galerie', 'title', 'Galerie')) ?></h2>
        <p class="vf-subheading">
            <?= htmlspecialchars(vf_text($txt, 'galerie', 'subtitle', 'Découvrez le lieu en images.')) ?>
        </p>

        <div class="vf-gallery" id="vf-gallery">
            <?php foreach ($galerie_items as $i => $photo): ?>
            <figure class="vf-gallery-item<?= !empty($photo['wide']) ? ' vf-gallery-item--wide' : '' ?>" data-index="<?= $i ?>">
                <img
                    src="<?= htmlspecialchars($photo['url']) ?>"
                    alt="<?= htmlspecialchars($photo['alt']) ?>"
                    <?= !empty($photo['srcset']) ? $photo['srcset'] : '' ?>
                    loading="lazy"
                >
                <?php if (!empty($photo['alt'])): ?>
                <figcaption class="vf-gallery-caption"><?= htmlspecialchars($photo['alt']) ?></figcaption>
                <?php endif; ?>
            </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Lightbox -->
<div class="vf-lightbox" id="vf-lightbox" hidden aria-hidden="true">
    <div class="vf-lightbox-backdrop"></div>
    <button class="vf-lightbox-close" aria-label="Fermer">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <button class="vf-lightbox-prev" aria-label="Photo précédente">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
    <button class="vf-lightbox-next" aria-label="Photo suivante">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 6 15 12 9 18"/></svg>
    </button>
    <div class="vf-lightbox-content">
        <img src="" alt="" id="vf-lightbox-img">
        <p class="vf-lightbox-caption" id="vf-lightbox-caption"></p>
    </div>
    <div class="vf-lightbox-counter" id="vf-lightbox-counter"></div>
</div>

<!-- Organic curve : galerie → visite -->
<div class="vf-curve vf-curve--white" aria-hidden="true">
    <svg viewBox="0 0 1440 64" preserveAspectRatio="none">
        <path d="M0,40 C360,64 720,8 1080,36 C1260,50 1380,20 1440,28 L1440,64 L0,64 Z"/>
    </svg>
</div>
