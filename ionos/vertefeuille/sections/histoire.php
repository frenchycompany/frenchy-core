<!-- ═══════════════ HISTOIRE ═══════════════ -->
<section id="histoire" class="vf-section vf-section--beige">
    <div class="vf-container vf-section-narrow">
        <h2 class="vf-heading vf-heading--center"><?= htmlspecialchars(vf_text($txt, 'histoire', 'title', 'Une histoire séculaire')) ?></h2>

        <div class="vf-prose">
            <p><?= htmlspecialchars(vf_text($txt, 'histoire', 'para1', 'Le Château de Vertefeuille s\'inscrit dans la tradition des grandes demeures françaises. Son architecture, ses volumes et ses détails racontent une époque — et invitent aujourd\'hui à vivre une expérience authentique, loin du bruit.')) ?></p>
            <p><?= htmlspecialchars(vf_text($txt, 'histoire', 'para2', 'Chaque espace a été pensé pour préserver l\'âme du lieu tout en apportant le confort attendu : literie de qualité, équipements modernes, et une attention particulière portée à l\'accueil.')) ?></p>
        </div>

        <blockquote class="vf-quote">
            <p><?= htmlspecialchars(vf_text($txt, 'histoire', 'quote', '« Le luxe discret d\'un lieu vrai : quand l\'élégance se ressent, sans se montrer. »')) ?></p>
        </blockquote>
    </div>
</section>

<!-- Organic curve : histoire → expérience -->
<div class="vf-curve vf-curve--white" aria-hidden="true">
    <svg viewBox="0 0 1440 64" preserveAspectRatio="none">
        <path d="M0,40 C360,64 720,8 1080,36 C1260,50 1380,20 1440,28 L1440,64 L0,64 Z"/>
    </svg>
</div>
