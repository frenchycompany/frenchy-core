<!-- ═══════════════ FOOTER ═══════════════ -->
<?php $footer_nav = []; foreach ($active_sections as $k => $cfg) { if (!empty($cfg['nav'])) $footer_nav[$k] = $cfg; } ?>
<footer class="vf-footer">
    <div class="vf-container vf-footer-inner">

        <div class="vf-footer-top">
            <div class="vf-footer-brand">
                <?php if (!empty($site['logo']) && file_exists($site['logo'])): ?>
                    <img
                        src="<?= htmlspecialchars($site['logo']) ?>"
                        alt="Logo <?= htmlspecialchars($site['name']) ?>"
                        class="vf-footer-logo-img"
                    >
                <?php endif; ?>
                <span class="vf-footer-logo"><?= htmlspecialchars($site['name']) ?></span>
                <p class="vf-footer-tagline"><?= htmlspecialchars($site['location']) ?></p>
            </div>

            <nav class="vf-footer-nav" aria-label="Navigation secondaire">
                <?php foreach ($footer_nav as $key => $cfg): ?>
                    <a href="#<?= htmlspecialchars($cfg['id'] ?? $key) ?>"><?= htmlspecialchars($cfg['nav']) ?></a>
                <?php endforeach; ?>
            </nav>

            <div class="vf-footer-contact">
                <a href="tel:<?= htmlspecialchars($site['phone_raw']) ?>"><?= htmlspecialchars($site['phone']) ?></a>
                <a href="mailto:<?= htmlspecialchars($site['email']) ?>"><?= htmlspecialchars($site['email']) ?></a>
            </div>
        </div>

        <div class="vf-footer-bottom">
            <p class="vf-footer-copy">&copy; <?= $site['year'] ?> <?= htmlspecialchars($site['name']) ?> — Tous droits réservés — <a href="https://frenchyconciergerie.com" target="_blank" rel="noopener">Frenchy Conciergerie</a></p>
            <p class="vf-footer-legal"><a href="mentions-legales.php">Mentions légales</a></p>
        </div>

    </div>
</footer>

<!-- Bandeau cookies (RGPD) -->
<div class="vf-cookie-banner" id="vf-cookie-banner" hidden>
    <div class="vf-container vf-cookie-inner">
        <p>Ce site utilise uniquement des cookies techniques nécessaires à son fonctionnement. Aucun cookie publicitaire n'est utilisé. <a href="mentions-legales.php#cookies">En savoir plus</a></p>
        <button type="button" class="vf-btn vf-btn-primary vf-cookie-accept" id="vf-cookie-accept">Compris</button>
    </div>
</div>
<script>
(function(){
    if(!localStorage.getItem('vf_cookie_ok')){
        document.getElementById('vf-cookie-banner').hidden=false;
    }
    var btn=document.getElementById('vf-cookie-accept');
    if(btn)btn.addEventListener('click',function(){
        localStorage.setItem('vf_cookie_ok','1');
        document.getElementById('vf-cookie-banner').hidden=true;
    });
})();
</script>
