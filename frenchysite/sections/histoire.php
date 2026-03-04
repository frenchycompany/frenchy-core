<!-- ═══════════════ HISTOIRE ═══════════════ -->
<section id="histoire" class="vf-section vf-section--beige">
    <div class="vf-container vf-section-narrow">
        <h2 class="vf-heading vf-heading--center"><?= htmlspecialchars(vf_text($txt, 'histoire', 'title', 'Notre histoire')) ?></h2>

        <div class="vf-prose">
            <p><?= htmlspecialchars(vf_text($txt, 'histoire', 'para1', 'Ce lieu a été pensé pour offrir une expérience unique à chaque voyageur. Son ambiance, ses espaces et ses détails créent un cadre idéal pour se ressourcer.')) ?></p>
            <p><?= htmlspecialchars(vf_text($txt, 'histoire', 'para2', 'Chaque espace a été aménagé avec soin pour allier confort et authenticité : literie de qualité, équipements modernes et décoration soignée.')) ?></p>
        </div>

        <blockquote class="vf-quote">
            <p><?= htmlspecialchars(vf_text($txt, 'histoire', 'quote', '« Un lieu où l\'on se sent chez soi, avec le charme en plus. »')) ?></p>
        </blockquote>
    </div>
</section>

<!-- Organic curve : histoire → expérience -->
<div class="vf-curve vf-curve--white" aria-hidden="true">
    <svg viewBox="0 0 1440 64" preserveAspectRatio="none">
        <path d="M0,40 C360,64 720,8 1080,36 C1260,50 1380,20 1440,28 L1440,64 L0,64 Z"/>
    </svg>
</div>
