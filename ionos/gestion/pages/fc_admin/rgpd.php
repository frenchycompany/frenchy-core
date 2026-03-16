<?php
/** RGPD Configuration — Page FC Admin */
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
        'responsable_traitement' => '',
        'email_dpo' => '',
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
?>
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
        <div class="card-header bg-info text-white"><h6 class="mb-0"><i class="fas fa-file-alt"></i> Textes legaux</h6></div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Politique de confidentialite</label>
                <textarea name="rgpd[politique_confidentialite]" class="form-control" rows="8" style="font-family:monospace"><?= e($rgpdConfig['politique_confidentialite'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Mentions legales</label>
                <textarea name="rgpd[mentions_legales]" class="form-control" rows="8" style="font-family:monospace"><?= e($rgpdConfig['mentions_legales'] ?? '') ?></textarea>
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
