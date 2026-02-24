<!-- ═══════════════ CONTACT ═══════════════ -->
<section id="contact" class="vf-section vf-section--beige">
    <div class="vf-container vf-section-narrow">
        <h2 class="vf-heading vf-heading--center"><?= htmlspecialchars(vf_text($txt, 'contact', 'title', 'Contact')) ?></h2>

        <div class="vf-contact-card">
            <div class="vf-contact-grid">
                <div class="vf-contact-item">
                    <span class="vf-contact-label">Téléphone</span>
                    <a href="tel:<?= htmlspecialchars($site['phone_raw']) ?>" class="vf-contact-value"><?= htmlspecialchars($site['phone']) ?></a>
                </div>
                <div class="vf-contact-item">
                    <span class="vf-contact-label">Email</span>
                    <a href="mailto:<?= htmlspecialchars($site['email']) ?>" class="vf-contact-value"><?= htmlspecialchars($site['email']) ?></a>
                </div>
                <div class="vf-contact-item">
                    <span class="vf-contact-label">Localisation</span>
                    <span class="vf-contact-value"><?= htmlspecialchars($site['address']) ?></span>
                </div>
            </div>

            <!-- Formulaire de contact -->
            <form class="vf-contact-form" id="vf-contact-form" method="post" action="contact-send.php">
                <div class="vf-contact-form-row">
                    <div class="vf-contact-field">
                        <label for="cf-name">Nom</label>
                        <input type="text" id="cf-name" name="name" required maxlength="100" class="vf-input">
                    </div>
                    <div class="vf-contact-field">
                        <label for="cf-email">Email</label>
                        <input type="email" id="cf-email" name="email" required maxlength="200" class="vf-input">
                    </div>
                </div>
                <div class="vf-contact-field">
                    <label for="cf-subject">Sujet</label>
                    <input type="text" id="cf-subject" name="subject" maxlength="200" class="vf-input" placeholder="Demande de renseignements, événement, etc.">
                </div>
                <div class="vf-contact-field">
                    <label for="cf-message">Message</label>
                    <textarea id="cf-message" name="message" required rows="5" maxlength="5000" class="vf-input vf-textarea"></textarea>
                </div>
                <!-- Honeypot anti-spam -->
                <div style="position:absolute;left:-9999px" aria-hidden="true">
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </div>
                <div class="vf-contact-form-actions">
                    <button type="submit" class="vf-btn vf-btn-primary" id="vf-contact-submit">Envoyer le message</button>
                </div>
                <p class="vf-contact-form-status" id="vf-contact-status" hidden></p>
            </form>

            <div class="vf-contact-actions">
                <a class="vf-btn vf-btn-outline" href="#top">Retour en haut</a>
            </div>
        </div>
    </div>
</section>
