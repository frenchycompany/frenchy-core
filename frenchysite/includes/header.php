<!-- ═══════════════ HEADER ═══════════════ -->
<?php $nav_items = []; foreach ($active_sections as $k => $cfg) { if (!empty($cfg['nav'])) $nav_items[$k] = $cfg; } ?>
<header class="vf-header" id="top">
    <div class="vf-container vf-header-inner">

        <a href="#top" class="vf-logo">
            <?php if (!empty($site['logo']) && file_exists($site['logo'])): ?>
                <img
                    src="<?= htmlspecialchars($site['logo']) ?>"
                    alt="Logo <?= htmlspecialchars($site['name']) ?>"
                    class="vf-logo-img"
                >
            <?php else: ?>
                <svg class="vf-logo-icon" width="40" height="40" viewBox="0 0 40 40" aria-hidden="true">
                    <circle cx="20" cy="20" r="19" fill="none" stroke="#1D5345" stroke-width="1"/>
                    <text x="20" y="26" text-anchor="middle" fill="#1D5345" font-family="'Playfair Display', serif" font-size="16" font-weight="500"><?= htmlspecialchars($site['monogram'] ?? 'FC') ?></text>
                </svg>
            <?php endif; ?>
            <span class="vf-logo-text">
                <span class="vf-logo-name"><?= htmlspecialchars($site['name']) ?></span>
                <span class="vf-logo-sub"><?= htmlspecialchars($site['location']) ?></span>
            </span>
        </a>

        <nav class="vf-nav" id="vf-nav" aria-label="Navigation principale">
            <?php foreach ($nav_items as $key => $cfg): ?>
                <a href="#<?= htmlspecialchars($cfg['id'] ?? $key) ?>"><?= htmlspecialchars($cfg['nav']) ?></a>
            <?php endforeach; ?>
        </nav>

        <?php
        // Bouton "Réserver" visible si la section reservation est active
        $has_booking = isset($active_sections['reservation']);
        ?>
        <?php if ($has_booking): ?>
        <a href="#reserver" class="vf-btn vf-btn-primary vf-header-cta">Réserver</a>
        <?php endif; ?>

        <!-- Search toggle -->
        <button class="vf-search-toggle" id="vf-search-toggle" aria-label="Rechercher" title="Rechercher">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
        </button>

        <!-- Mobile menu toggle -->
        <button class="vf-menu-toggle" id="vf-menu-toggle" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="vf-nav">
            <svg class="vf-menu-icon vf-menu-icon--open" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
            <svg class="vf-menu-icon vf-menu-icon--close" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                <line x1="6" y1="6" x2="18" y2="18"/>
                <line x1="18" y1="6" x2="6" y2="18"/>
            </svg>
        </button>

    </div>
</header>
