<?php
/** RGPD Configuration — Page FC Admin */

// Charger les settings du site pour pré-remplir les champs légaux
$siteSettings = [];
try {
    $stmtS = $conn->query("SELECT setting_key, setting_value FROM FC_settings");
    while ($row = $stmtS->fetch(PDO::FETCH_ASSOC)) {
        $siteSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {}

// Helper pour accéder aux settings
$s = function($key, $default = '') use ($siteSettings) {
    return $siteSettings[$key] ?? $default;
};

// Générer le HTML par défaut des mentions légales depuis les settings actuels
$defaultMentions = '<h3>Identité de l\'Entreprise</h3>
<p><strong>Raison sociale :</strong> SAS ' . htmlspecialchars($s('site_nom', 'FRENCHY CONCIERGERIE')) . '</p>
<p><strong>Forme juridique :</strong> ' . htmlspecialchars($s('forme_juridique', 'Société par Actions Simplifiée (SAS)')) . '</p>
<p><strong>Capital social :</strong> ' . htmlspecialchars($s('capital', '100 euros')) . '</p>
<p><strong>Siège social :</strong> ' . htmlspecialchars($s('adresse')) . '</p>
<p><strong>SIRET :</strong> ' . htmlspecialchars($s('siret')) . '</p>
<p><strong>RCS :</strong> ' . htmlspecialchars($s('rcs')) . '</p>
<p><strong>N° TVA intracommunautaire :</strong> ' . htmlspecialchars($s('tva_intra')) . '</p>
<p><strong>Présidente :</strong> ' . htmlspecialchars($s('presidente')) . '</p>
<p><strong>Email :</strong> ' . htmlspecialchars($s('email_legal')) . '</p>
<p><strong>Téléphone :</strong> ' . htmlspecialchars($s('telephone_legal')) . '</p>

<h3>Cartes Professionnelles</h3>
<p><strong>Carte de Transaction Immobilière n° ' . htmlspecialchars($s('carte_transaction')) . '</strong></p>
<p>Délivrée par la CCI de l\'Oise</p>
<p>Activité : Transactions sur immeubles et fonds de commerce</p>
<p><strong>Carte de Gestion Immobilière n° ' . htmlspecialchars($s('carte_gestion')) . '</strong></p>
<p>Délivrée par la CCI de l\'Oise</p>
<p>Activité : Gestion immobilière - Prestations touristiques</p>

<h3>Médiation de la Consommation</h3>
<p>Conformément aux articles L.611-1 et suivants et R.612-1 et suivants du Code de la consommation, nous vous informons que tout consommateur a le droit de recourir gratuitement à un médiateur de la consommation en vue de la résolution amiable d\'un litige l\'opposant à un professionnel.</p>
<p><strong>Médiateur désigné :</strong> GIE IMMOMEDIATEURS</p>
<p><strong>Adresse :</strong> 55 Avenue Marceau, 75116 Paris</p>
<p><strong>Site internet :</strong> <a href="https://www.immomediateurs.fr" target="_blank">www.immomediateurs.fr</a></p>
<p><strong>Email :</strong> <a href="mailto:contact@immomediateurs.fr">contact@immomediateurs.fr</a></p>
<p>Avant de saisir le médiateur, vous devez d\'abord contacter notre service client par email à <a href="mailto:' . htmlspecialchars($s('email_legal', $s('email'))) . '">' . htmlspecialchars($s('email_legal', $s('email'))) . '</a> pour tenter de résoudre votre litige à l\'amiable. En cas d\'échec ou d\'absence de réponse dans un délai de 2 mois, vous pourrez saisir le médiateur.</p>';

$emailLegal = htmlspecialchars($s('email_legal', $s('email')));
$defaultPolitique = '<p><strong>Dernière mise à jour :</strong> ' . date('d/m/Y') . '</p>

<p>La société <strong>' . htmlspecialchars($s('site_nom', 'FRENCHY CONCIERGERIE')) . '</strong> (ci-après « nous », « notre » ou « la Société ») s\'engage à protéger la vie privée des utilisateurs de son site web et de ses services. Cette politique de confidentialité explique comment nous collectons, utilisons et protégeons vos données personnelles, conformément au Règlement Général sur la Protection des Données (RGPD - Règlement UE 2016/679).</p>

<h3>1. Responsable du traitement</h3>
<p>Le responsable du traitement des données personnelles est :</p>
<ul>
    <li><strong>Société :</strong> SAS ' . htmlspecialchars($s('site_nom', 'FRENCHY CONCIERGERIE')) . '</li>
    <li><strong>Siège social :</strong> ' . htmlspecialchars($s('adresse')) . '</li>
    <li><strong>SIRET :</strong> ' . htmlspecialchars($s('siret')) . '</li>
    <li><strong>Email DPO/Contact :</strong> <a href="mailto:' . $emailLegal . '">' . $emailLegal . '</a></li>
</ul>

<h3>2. Données collectées</h3>
<p>Nous collectons les catégories de données suivantes :</p>
<ul>
    <li><strong>Données d\'identification :</strong> nom, prénom, adresse email, numéro de téléphone</li>
    <li><strong>Données relatives à votre bien :</strong> localisation, surface, équipements (via le simulateur de revenus)</li>
    <li><strong>Données de navigation :</strong> adresse IP, cookies, pages visitées, durée de visite (avec votre consentement)</li>
    <li><strong>Données de correspondance :</strong> messages envoyés via le formulaire de contact</li>
</ul>

<h3>3. Finalités du traitement</h3>
<p>Vos données sont traitées pour les finalités suivantes :</p>
<ul>
    <li>Répondre à vos demandes de contact et de renseignement</li>
    <li>Vous fournir un avis de valeur locative personnalisé (simulateur)</li>
    <li>Gérer la relation commerciale et les contrats de conciergerie</li>
    <li>Vous envoyer des communications marketing (avec votre consentement)</li>
    <li>Améliorer nos services et notre site web</li>
    <li>Respecter nos obligations légales</li>
</ul>

<h3>4. Base légale du traitement</h3>
<p>Le traitement de vos données repose sur :</p>
<ul>
    <li><strong>Votre consentement :</strong> pour l\'envoi de newsletters et la collecte de cookies non essentiels</li>
    <li><strong>L\'exécution d\'un contrat :</strong> pour la gestion des services de conciergerie</li>
    <li><strong>L\'intérêt légitime :</strong> pour améliorer nos services et répondre à vos demandes</li>
    <li><strong>Les obligations légales :</strong> pour conserver certaines données fiscales et comptables</li>
</ul>

<h3>5. Durée de conservation</h3>
<p>Nous conservons vos données personnelles pendant :</p>
<ul>
    <li><strong>Données de contact/simulation :</strong> 3 ans après le dernier contact</li>
    <li><strong>Données clients actifs :</strong> pendant la durée de la relation contractuelle + 5 ans</li>
    <li><strong>Données comptables :</strong> 10 ans conformément aux obligations fiscales</li>
    <li><strong>Cookies :</strong> 13 mois maximum</li>
</ul>

<h3>6. Destinataires des données</h3>
<p>Vos données peuvent être transmises à :</p>
<ul>
    <li>Notre équipe interne pour le traitement de vos demandes</li>
    <li>Notre hébergeur web (dans l\'UE)</li>
    <li>Nos prestataires de services (ménage, maintenance) - uniquement les données nécessaires</li>
    <li>Les autorités compétentes en cas d\'obligation légale</li>
</ul>
<p>Nous ne vendons jamais vos données à des tiers.</p>

<h3>7. Vos droits</h3>
<p>Conformément au RGPD, vous disposez des droits suivants :</p>
<ul>
    <li><strong>Droit d\'accès :</strong> obtenir une copie de vos données personnelles</li>
    <li><strong>Droit de rectification :</strong> corriger vos données inexactes ou incomplètes</li>
    <li><strong>Droit à l\'effacement :</strong> demander la suppression de vos données</li>
    <li><strong>Droit à la limitation :</strong> limiter le traitement de vos données</li>
    <li><strong>Droit à la portabilité :</strong> recevoir vos données dans un format structuré</li>
    <li><strong>Droit d\'opposition :</strong> vous opposer au traitement de vos données</li>
    <li><strong>Droit de retirer votre consentement :</strong> à tout moment, sans affecter la légalité du traitement antérieur</li>
</ul>
<p>Pour exercer vos droits, contactez-nous à : <a href="mailto:' . $emailLegal . '">' . $emailLegal . '</a></p>
<p>Vous pouvez également introduire une réclamation auprès de la <strong>CNIL</strong> : <a href="https://www.cnil.fr" target="_blank">www.cnil.fr</a></p>

<h3>8. Cookies</h3>
<p>Notre site utilise des cookies pour :</p>
<ul>
    <li><strong>Cookies essentiels :</strong> fonctionnement du site (sessions, sécurité)</li>
    <li><strong>Cookies analytiques :</strong> mesure d\'audience et amélioration du site (avec consentement)</li>
    <li><strong>Cookies marketing :</strong> personnalisation des publicités (avec consentement)</li>
</ul>

<h3>9. Sécurité</h3>
<p>Nous mettons en œuvre des mesures techniques et organisationnelles appropriées pour protéger vos données :</p>
<ul>
    <li>Chiffrement SSL/TLS des communications</li>
    <li>Accès restreint aux données personnelles</li>
    <li>Sauvegardes régulières</li>
    <li>Mise à jour des systèmes de sécurité</li>
</ul>

<h3>10. Modifications</h3>
<p>Nous nous réservons le droit de modifier cette politique de confidentialité à tout moment. Toute modification sera publiée sur cette page avec une nouvelle date de mise à jour.</p>

<p><strong>Des questions sur vos données ?</strong> Contactez notre responsable de la protection des données : <a href="mailto:' . $emailLegal . '">' . $emailLegal . '</a></p>';

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS FC_rgpd_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        config_key VARCHAR(100) UNIQUE NOT NULL,
        config_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $rgpdDefaults = [
        'bandeau_actif' => '1',
        'bandeau_texte' => 'Ce site utilise des cookies pour ameliorer votre experience.',
        'bandeau_bouton_accepter' => 'Accepter',
        'bandeau_bouton_refuser' => 'Refuser',
        'bandeau_lien_politique' => '/politique-confidentialite',
        'politique_confidentialite' => '',
        'mentions_legales' => '',
        'duree_conservation_contacts' => '36',
        'duree_conservation_simulations' => '24',
        'duree_conservation_visites' => '13',
        'responsable_traitement' => $s('presidente'),
        'email_dpo' => $s('email_legal'),
    ];
    foreach ($rgpdDefaults as $key => $val) {
        $conn->prepare("INSERT IGNORE INTO FC_rgpd_config (config_key, config_value) VALUES (?, ?)")->execute([$key, $val]);
    }
    $rgpdConfig = [];
    $stmt = $conn->query("SELECT config_key, config_value FROM FC_rgpd_config");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rgpdConfig[$row['config_key']] = $row['config_value'];
    }
} catch (PDOException $e) { $rgpdConfig = []; }

// Si les textareas sont vides en BDD, montrer le texte par défaut du site
$displayMentions = !empty($rgpdConfig['mentions_legales']) ? $rgpdConfig['mentions_legales'] : $defaultMentions;
$displayPolitique = !empty($rgpdConfig['politique_confidentialite']) ? $rgpdConfig['politique_confidentialite'] : $defaultPolitique;
?>

<!-- Infos légales actuelles du site (lecture seule, rappel) -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-secondary text-white">
        <h6 class="mb-0"><i class="fas fa-building"></i> Informations legales actuelles du site
            <small class="text-light">(modifiables dans Parametres)</small>
        </h6>
    </div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-4"><strong>Raison sociale :</strong> <?= e($siteSettings['site_nom'] ?? 'Non renseigne') ?></div>
            <div class="col-md-4"><strong>Forme juridique :</strong> <?= e($siteSettings['forme_juridique'] ?? 'Non renseigne') ?></div>
            <div class="col-md-4"><strong>Capital :</strong> <?= e($siteSettings['capital'] ?? 'Non renseigne') ?></div>
            <div class="col-md-4"><strong>SIRET :</strong> <?= e($siteSettings['siret'] ?? 'Non renseigne') ?></div>
            <div class="col-md-4"><strong>RCS :</strong> <?= e($siteSettings['rcs'] ?? 'Non renseigne') ?></div>
            <div class="col-md-4"><strong>TVA intra :</strong> <?= e($siteSettings['tva_intra'] ?? 'Non renseigne') ?></div>
            <div class="col-md-4"><strong>Presidente :</strong> <?= e($siteSettings['presidente'] ?? 'Non renseigne') ?></div>
            <div class="col-md-4"><strong>Email legal :</strong> <?= e($siteSettings['email_legal'] ?? 'Non renseigne') ?></div>
            <div class="col-md-4"><strong>Tel legal :</strong> <?= e($siteSettings['telephone_legal'] ?? 'Non renseigne') ?></div>
            <div class="col-md-6"><strong>Carte Transaction :</strong> <?= e($siteSettings['carte_transaction'] ?? 'Non renseigne') ?></div>
            <div class="col-md-6"><strong>Carte Gestion :</strong> <?= e($siteSettings['carte_gestion'] ?? 'Non renseigne') ?></div>
        </div>
        <div class="mt-2">
            <a href="?fc_page=settings" class="btn btn-outline-secondary btn-sm"><i class="fas fa-cog"></i> Modifier dans Parametres</a>
        </div>
    </div>
</div>

<form method="POST">
    <?= fcCsrfField() ?>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-warning text-dark"><h6 class="mb-0"><i class="fas fa-cookie-bite"></i> Bandeau Cookies</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Bandeau actif</label>
                    <select name="rgpd[bandeau_actif]" class="form-select">
                        <option value="1" <?= ($rgpdConfig['bandeau_actif'] ?? '1') === '1' ? 'selected' : '' ?>>Oui</option>
                        <option value="0" <?= ($rgpdConfig['bandeau_actif'] ?? '1') === '0' ? 'selected' : '' ?>>Non</option>
                    </select>
                </div>
                <div class="col-md-9">
                    <label class="form-label">Texte du bandeau</label>
                    <input type="text" name="rgpd[bandeau_texte]" class="form-control" value="<?= e($rgpdConfig['bandeau_texte'] ?? '') ?>">
                </div>
                <div class="col-md-3"><label class="form-label">Bouton Accepter</label><input type="text" name="rgpd[bandeau_bouton_accepter]" class="form-control" value="<?= e($rgpdConfig['bandeau_bouton_accepter'] ?? 'Accepter') ?>"></div>
                <div class="col-md-3"><label class="form-label">Bouton Refuser</label><input type="text" name="rgpd[bandeau_bouton_refuser]" class="form-control" value="<?= e($rgpdConfig['bandeau_bouton_refuser'] ?? 'Refuser') ?>"></div>
                <div class="col-md-6"><label class="form-label">Lien politique</label><input type="text" name="rgpd[bandeau_lien_politique]" class="form-control" value="<?= e($rgpdConfig['bandeau_lien_politique'] ?? '') ?>"></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-info text-white"><h6 class="mb-0"><i class="fas fa-file-alt"></i> Mentions legales</h6></div>
        <div class="card-body">
            <div class="alert alert-info mb-3">
                <i class="fas fa-info-circle"></i>
                Le texte ci-dessous est pre-rempli avec le contenu actuel du site. Modifiez-le et enregistrez pour appliquer vos changements.
                Le format HTML est accepte.
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Mentions legales</label>
                <textarea name="rgpd[mentions_legales]" class="form-control" rows="15" style="font-family:monospace; font-size: 0.85rem"><?= e($displayMentions) ?></textarea>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-info text-white"><h6 class="mb-0"><i class="fas fa-shield-alt"></i> Politique de confidentialite</h6></div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label fw-bold">Politique de confidentialite</label>
                <textarea name="rgpd[politique_confidentialite]" class="form-control" rows="20" style="font-family:monospace; font-size: 0.85rem"><?= e($displayPolitique) ?></textarea>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-danger text-white"><h6 class="mb-0"><i class="fas fa-database"></i> Conservation des donnees</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Contacts (mois)</label><input type="number" name="rgpd[duree_conservation_contacts]" class="form-control" value="<?= e($rgpdConfig['duree_conservation_contacts'] ?? '36') ?>"></div>
                <div class="col-md-3"><label class="form-label">Simulations (mois)</label><input type="number" name="rgpd[duree_conservation_simulations]" class="form-control" value="<?= e($rgpdConfig['duree_conservation_simulations'] ?? '24') ?>"></div>
                <div class="col-md-3"><label class="form-label">Visites (mois)</label><input type="number" name="rgpd[duree_conservation_visites]" class="form-control" value="<?= e($rgpdConfig['duree_conservation_visites'] ?? '13') ?>"></div>
                <div class="col-md-3"><label class="form-label">Email DPO</label><input type="email" name="rgpd[email_dpo]" class="form-control" value="<?= e($rgpdConfig['email_dpo'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Responsable du traitement</label><input type="text" name="rgpd[responsable_traitement]" class="form-control" value="<?= e($rgpdConfig['responsable_traitement'] ?? '') ?>"></div>
            </div>
        </div>
    </div>

    <button type="submit" name="save_rgpd" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer la configuration RGPD</button>
</form>
