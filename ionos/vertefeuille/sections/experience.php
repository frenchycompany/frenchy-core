<!-- ═══════════════ L'EXPÉRIENCE ═══════════════ -->
<section id="experience" class="vf-section vf-section--white">
    <div class="vf-container">
        <h2 class="vf-heading vf-heading--center"><?= htmlspecialchars(vf_text($txt, 'experience', 'title', 'L\'expérience Vertefeuille')) ?></h2>
        <p class="vf-subheading">
            <?= htmlspecialchars(vf_text($txt, 'experience', 'subtitle', 'Un séjour pensé pour les moments qui comptent : repos, célébrations, retrouvailles.')) ?>
        </p>

        <div class="vf-cards">

            <!-- Confort -->
            <article class="vf-card">
                <div class="vf-card-photo">
                    <img src="<?= htmlspecialchars($exp_photos['confort']) ?>" alt="Pièce de vie spacieuse" loading="lazy">
                </div>
                <div class="vf-card-body">
                    <div class="vf-card-icon" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                    </div>
                    <h3 class="vf-card-title"><?= htmlspecialchars(vf_text($txt, 'experience', 'card1_title', 'Confort & volumes')) ?></h3>
                    <p class="vf-card-text"><?= htmlspecialchars(vf_text($txt, 'experience', 'card1_text', 'De grandes pièces à vivre, une circulation fluide, et des espaces où chacun trouve sa place — sans compromis sur le confort.')) ?></p>
                </div>
            </article>

            <!-- Charme -->
            <article class="vf-card">
                <div class="vf-card-photo">
                    <img src="<?= htmlspecialchars($exp_photos['charme']) ?>" alt="Détails historiques" loading="lazy">
                </div>
                <div class="vf-card-body">
                    <div class="vf-card-icon" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <h3 class="vf-card-title"><?= htmlspecialchars(vf_text($txt, 'experience', 'card2_title', 'Charme historique')) ?></h3>
                    <p class="vf-card-text"><?= htmlspecialchars(vf_text($txt, 'experience', 'card2_text', 'Matières nobles, détails d\'époque, atmosphère unique : l\'ancien est là, dans ce qu\'il a de plus beau — sans rigidité.')) ?></p>
                </div>
            </article>

            <!-- Accueil -->
            <article class="vf-card">
                <div class="vf-card-photo">
                    <img src="<?= htmlspecialchars($exp_photos['accueil']) ?>" alt="Accueil chaleureux" loading="lazy">
                </div>
                <div class="vf-card-body">
                    <div class="vf-card-icon" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    <h3 class="vf-card-title"><?= htmlspecialchars(vf_text($txt, 'experience', 'card3_title', 'Accueil & service')) ?></h3>
                    <p class="vf-card-text"><?= htmlspecialchars(vf_text($txt, 'experience', 'card3_text', 'Une conciergerie attentive pour un séjour serein, avec des recommandations locales sur mesure.')) ?></p>
                </div>
            </article>

        </div>

        <div class="vf-section-cta">
            <a class="vf-btn vf-btn-outline" href="#contact">Poser une question</a>
        </div>
    </div>
</section>

<!-- Organic curve : expérience → galerie -->
<div class="vf-curve vf-curve--beige" aria-hidden="true">
    <svg viewBox="0 0 1440 64" preserveAspectRatio="none">
        <path d="M0,40 C240,64 480,16 720,32 C960,48 1200,8 1440,28 L1440,64 L0,64 Z"/>
    </svg>
</div>
