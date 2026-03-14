<!-- ═══════════════ RECOMMANDATIONS ═══════════════ -->
<section id="recommandations" class="vf-section vf-section--offwhite">
    <div class="vf-container vf-section-narrow">
        <h2 class="vf-heading vf-heading--center"><?= htmlspecialchars(vf_text($txt, 'recommandations', 'title', 'Nos recommandations')) ?></h2>
        <p class="vf-subheading">
            <?= htmlspecialchars(vf_text($txt, 'recommandations', 'subtitle', 'Découvrez nos adresses préférées autour du logement.')) ?>
        </p>

        <div class="vf-reco-grid">
            <div class="vf-reco-card">
                <div class="vf-reco-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3zm0 0v7"/>
                    </svg>
                </div>
                <h3>Restaurants</h3>
                <p>Nos tables favorites pour tous les goûts et toutes les occasions.</p>
            </div>
            <div class="vf-reco-card">
                <div class="vf-reco-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 4.24 4.24"/><path d="m14.83 9.17 4.24-4.24"/><path d="m14.83 14.83 4.24 4.24"/><path d="m9.17 14.83-4.24 4.24"/><circle cx="12" cy="12" r="4"/>
                    </svg>
                </div>
                <h3>Activités</h3>
                <p>Les meilleures expériences et sorties autour du logement.</p>
            </div>
            <div class="vf-reco-card">
                <div class="vf-reco-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.42 4.58a5.4 5.4 0 0 0-7.65 0l-.77.78-.77-.78a5.4 5.4 0 0 0-7.65 0C1.46 6.7 1.33 10.28 4 13l8 8 8-8c2.67-2.72 2.54-6.3.42-8.42z"/>
                    </svg>
                </div>
                <h3>Partenaires</h3>
                <p>Nos partenaires locaux pour un séjour encore plus agréable.</p>
            </div>
        </div>

        <?php if (!empty($site['recommandations_url'])): ?>
        <div class="vf-section-cta">
            <a class="vf-btn vf-btn-primary" href="<?= htmlspecialchars($site['recommandations_url']) ?>" target="_blank" rel="noopener">
                <?= htmlspecialchars(vf_text($txt, 'recommandations', 'cta', 'Voir toutes les recommandations')) ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
.vf-reco-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    margin: 2rem 0;
}
.vf-reco-card {
    background: #fff;
    border-radius: 16px;
    padding: 2rem 1.5rem;
    text-align: center;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    transition: transform 0.2s, box-shadow 0.2s;
}
.vf-reco-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}
.vf-reco-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: var(--vf-green, #1D5345);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
}
.vf-reco-card h3 {
    font-family: var(--font-display);
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    color: var(--vf-dark, #2B2924);
}
.vf-reco-card p {
    font-size: 0.95rem;
    color: var(--vf-brown, #6C5C4F);
    line-height: 1.5;
}
</style>

<!-- Organic curve : recommandations → planning -->
<div class="vf-curve vf-curve--green-from-offwhite" aria-hidden="true">
    <svg viewBox="0 0 1440 64" preserveAspectRatio="none">
        <path d="M0,0 L1440,0 L1440,24 C1200,56 960,0 720,32 C480,64 240,8 0,36 Z"/>
    </svg>
</div>
