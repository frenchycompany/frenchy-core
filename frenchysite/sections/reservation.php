<!-- ═══════════════ RÉSERVATION ═══════════════ -->
<section id="reserver" class="vf-section vf-section--green">
    <div class="vf-container vf-section-narrow">
        <h2 class="vf-heading vf-heading--center vf-heading--light"><?= htmlspecialchars(vf_text($txt, 'reservation', 'title', 'Réserver votre séjour')) ?></h2>
        <p class="vf-subheading vf-subheading--light">
            <?= htmlspecialchars(vf_text($txt, 'reservation', 'subtitle', 'Consultez les disponibilités et réservez directement.')) ?>
        </p>

        <div class="vf-booking-widget">
            <div
                class="airbnb-embed-frame"
                data-id="<?= htmlspecialchars($site['airbnb_id']) ?>"
                data-view="home"
                data-hide-price="true"
                data-hide-reviews="true"
                style="width:450px;height:300px;margin:auto;"
            >
                <a href="https://www.airbnb.fr/rooms/<?= htmlspecialchars($site['airbnb_id']) ?>?guests=1&amp;adults=1&amp;s=66&amp;source=embed_widget" rel="nofollow">
                    Voir sur Airbnb
                </a>
            </div>
            <script async src="https://www.airbnb.fr/embeddable/airbnb_jssdk"></script>
        </div>

        <div class="vf-section-cta">
            <a class="vf-btn vf-btn-primary-inv" href="#contact"><?= htmlspecialchars(vf_text($txt, 'reservation', 'cta', 'Demande spéciale ou événement')) ?></a>
        </div>
    </div>
</section>

<!-- Organic curve : réservation → contact -->
<div class="vf-curve vf-curve--beige-from-green" aria-hidden="true">
    <svg viewBox="0 0 1440 64" preserveAspectRatio="none">
        <path d="M0,0 L1440,0 L1440,24 C1200,56 960,0 720,32 C480,64 240,8 0,36 Z"/>
    </svg>
</div>
