<!-- ═══════════════ BANDEAU CHIFFRES CLÉS ═══════════════ -->
<section class="vf-band" aria-label="Chiffres clés">
    <div class="vf-container vf-band-grid">
        <div class="vf-band-item">
            <span class="vf-band-number"><?= vf_text($txt, 'band', 'stat1_number', 'XV<sup>e</sup>') ?></span>
            <span class="vf-band-label"><?= htmlspecialchars(vf_text($txt, 'band', 'stat1_label', 'siècle d\'origine')) ?></span>
        </div>
        <div class="vf-band-item">
            <span class="vf-band-number"><?= htmlspecialchars(vf_text($txt, 'band', 'stat2_number', '12')) ?></span>
            <span class="vf-band-label"><?= htmlspecialchars(vf_text($txt, 'band', 'stat2_label', 'couchages')) ?></span>
        </div>
        <div class="vf-band-item">
            <span class="vf-band-number"><?= htmlspecialchars(vf_text($txt, 'band', 'stat3_number', '1,5 ha')) ?></span>
            <span class="vf-band-label"><?= htmlspecialchars(vf_text($txt, 'band', 'stat3_label', 'de parc arboré')) ?></span>
        </div>
        <div class="vf-band-item">
            <span class="vf-band-number"><?= htmlspecialchars(vf_text($txt, 'band', 'stat4_number', '80 km')) ?></span>
            <span class="vf-band-label"><?= htmlspecialchars(vf_text($txt, 'band', 'stat4_label', 'de Paris')) ?></span>
        </div>
    </div>
</section>

<!-- Organic curve : band → histoire -->
<div class="vf-curve vf-curve--beige-from-green" aria-hidden="true">
    <svg viewBox="0 0 1440 64" preserveAspectRatio="none">
        <path d="M0,0 L1440,0 L1440,24 C1200,56 960,0 720,32 C480,64 240,8 0,36 Z"/>
    </svg>
</div>
