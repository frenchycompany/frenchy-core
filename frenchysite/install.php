<?php
/**
 * Installateur web — Déploiement sur hébergement mutualisé
 *
 * Ouvrez cette page dans votre navigateur pour configurer le site.
 * Elle crée le .env et config/property.php, puis installe les tables.
 *
 * IMPORTANT : Supprimez ce fichier après l'installation !
 */

// Bloquer l'accès si déjà installé (.env + property.php existent)
$env_exists      = file_exists(__DIR__ . '/.env');
$property_exists = file_exists(__DIR__ . '/config/property.php');
$already_installed = $env_exists && $property_exists;

$step = $_POST['step'] ?? ($_GET['step'] ?? '1');
$error   = '';
$success = false;

// ── Traitement du formulaire ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'install') {

    // Récupérer les champs
    $prop_name   = trim($_POST['prop_name'] ?? '');
    $prop_mono   = trim($_POST['prop_mono'] ?? '');
    $prop_tag    = trim($_POST['prop_tag'] ?? '');
    $prop_loc    = trim($_POST['prop_loc'] ?? '');
    $prop_phone  = trim($_POST['prop_phone'] ?? '');
    $prop_email  = trim($_POST['prop_email'] ?? '');
    $prop_addr   = trim($_POST['prop_addr'] ?? '');

    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_name = trim($_POST['db_name'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass'] ?? '';

    $adm_user = trim($_POST['adm_user'] ?? 'admin');
    $adm_pass = $_POST['adm_pass'] ?? '';

    // Validation
    if (!$prop_name) {
        $error = 'Le nom du logement est obligatoire.';
    } elseif (!$db_name || !$db_user) {
        $error = 'Les informations de base de données sont obligatoires.';
    } elseif (!$adm_pass) {
        $error = 'Le mot de passe admin est obligatoire.';
    } else {
        // Tester la connexion BDD
        try {
            $test_conn = new PDO(
                "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
                $db_user, $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            $error = 'Connexion BDD impossible : ' . $e->getMessage();
        }
    }

    if (!$error) {
        // Générer le préfixe BDD
        $letters = strtolower(preg_replace('/[^a-zA-Z]/', '', $prop_mono ?: $prop_name));
        $letters = substr($letters, 0, 3);
        if (strlen($letters) < 2) $letters = 'vf';
        $db_prefix = $letters . sprintf('%02d', rand(0, 99)) . '_';

        // Hasher le mot de passe admin
        $adm_pass_hash = password_hash($adm_pass, PASSWORD_BCRYPT);

        // Générer phone_raw
        $phone_raw = preg_replace('/[^+0-9]/', '', $prop_phone);

        // 1. Créer le .env
        $env_content = "DB_HOST={$db_host}\nDB_NAME={$db_name}\nDB_USER={$db_user}\nDB_PASS={$db_pass}\n\nADMIN_USER={$adm_user}\nADMIN_PASS={$adm_pass_hash}\n";

        if (file_put_contents(__DIR__ . '/.env', $env_content) === false) {
            $error = 'Impossible de créer le fichier .env. Vérifiez les permissions du dossier.';
        }
    }

    if (!$error) {
        // 2. Créer config/property.php
        $prop_name_escaped = str_replace("'", "\\'", $prop_name);
        $prop_tag_escaped  = str_replace("'", "\\'", $prop_tag ?: 'Bienvenue');
        $prop_loc_escaped  = str_replace("'", "\\'", $prop_loc);
        $prop_addr_escaped = str_replace("'", "\\'", $prop_addr ?: $prop_loc);
        $prop_phone_escaped = str_replace("'", "\\'", $prop_phone);
        $prop_email_escaped = str_replace("'", "\\'", $prop_email);

        $property_content = <<<'PHPTPL'
<?php
return [
    'name'      => '%NAME%',
    'monogram'  => '%MONO%',
    'tagline'   => '%TAG%',
    'location'  => '%LOC%',
    'phone'     => '%PHONE%',
    'phone_raw' => '%PHONE_RAW%',
    'email'     => '%EMAIL%',
    'address'   => '%ADDR%',
    'db_prefix' => '%PREFIX%',
    'airbnb_id'     => '',
    'matterport_id' => '',
    'colors' => [
        'green'    => '#1D5345',
        'green_dk' => '#153d33',
        'beige'    => '#CFCDB0',
        'grey'     => '#B2ACA9',
        'brown'    => '#6C5C4F',
        'offwhite' => '#E8E4D0',
        'dark'     => '#2B2924',
    ],
    'font_display' => 'Playfair Display',
    'font_body'    => 'Inter',
    'sections' => [
        'hero'        => ['label' => 'Hero (accueil)'],
        'band'        => ['label' => 'Bandeau chiffres clés'],
        'histoire'    => ['label' => 'Histoire',      'nav' => 'Histoire'],
        'experience'  => ['label' => "L'expérience",  'nav' => "L'expérience"],
        'galerie'     => ['label' => 'Galerie',       'nav' => 'Galerie'],
        'visite'      => ['label' => 'Visite 360°',   'nav' => 'Visite 360°'],
        'reservation' => ['label' => 'Réservation',   'nav' => 'Réserver', 'id' => 'reserver'],
        'contact'     => ['label' => 'Contact',       'nav' => 'Contact'],
    ],
    'guides' => [
        'wifi' => [
            'label'       => 'WiFi',
            'admin_label' => 'WiFi',
            'icon'        => '<path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1" fill="currentColor" stroke="none"/>',
        ],
    ],
    'photo_fallbacks' => [
        'hero' => 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?w=2000&q=80',
        'galerie' => [],
        'experience' => [],
    ],
];
PHPTPL;

        $property_content = str_replace(
            ['%NAME%', '%MONO%', '%TAG%', '%LOC%', '%PHONE%', '%PHONE_RAW%', '%EMAIL%', '%ADDR%', '%PREFIX%'],
            [$prop_name_escaped, $prop_mono, $prop_tag_escaped, $prop_loc_escaped, $prop_phone_escaped, $phone_raw, $prop_email_escaped, $prop_addr_escaped, $db_prefix],
            $property_content
        );

        if (!is_dir(__DIR__ . '/config')) {
            mkdir(__DIR__ . '/config', 0755, true);
        }

        if (file_put_contents(__DIR__ . '/config/property.php', $property_content) === false) {
            $error = 'Impossible de créer config/property.php. Vérifiez les permissions.';
        }
    }

    if (!$error) {
        // 3. Créer les dossiers photos
        $photo_dirs = ['assets/photos/hero', 'assets/photos/galerie', 'assets/photos/experience', 'assets/img'];
        foreach ($photo_dirs as $dir) {
            $full = __DIR__ . '/' . $dir;
            if (!is_dir($full)) {
                mkdir($full, 0755, true);
            }
        }

        // 4. Installer les tables BDD
        try {
            $conn = new PDO(
                "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
                $db_user, $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );

            $schema_file = __DIR__ . '/db/schema.sql';
            if (file_exists($schema_file)) {
                $sql = file_get_contents($schema_file);
                $sql = str_replace('vf_settings', $db_prefix . 'settings', $sql);
                $sql = str_replace('vf_texts', $db_prefix . 'texts', $sql);
                $sql = str_replace('vf_photos', $db_prefix . 'photos', $sql);
                $conn->exec($sql);

                // Seed with config values
                require_once __DIR__ . '/db/helpers.php';
                vf_seed_from_config($conn);
            }

            $success = true;
        } catch (PDOException $e) {
            $error = 'Erreur lors de la création des tables : ' . $e->getMessage();
        }
    }
}

// ── Vérifications système ──
$checks = [];
$checks['php'] = version_compare(PHP_VERSION, '7.4', '>=');
$checks['pdo'] = extension_loaded('pdo_mysql');
$checks['gd'] = extension_loaded('gd');
$checks['writable_root'] = is_writable(__DIR__);
$checks['writable_config'] = is_dir(__DIR__ . '/config') ? is_writable(__DIR__ . '/config') : is_writable(__DIR__);
$checks['schema'] = file_exists(__DIR__ . '/db/schema.sql');
$all_ok = $checks['php'] && $checks['pdo'] && $checks['writable_root'] && $checks['writable_config'] && $checks['schema'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation du site</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #f5f3ee;
            color: #2B2924;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .installer {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 40px rgba(0,0,0,.08);
            max-width: 580px;
            width: 100%;
            padding: 2.5rem;
        }
        h1 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.6rem;
            color: #1D5345;
            margin-bottom: .25rem;
        }
        .subtitle {
            color: #6C5C4F;
            font-size: .85rem;
            margin-bottom: 2rem;
        }
        fieldset {
            border: 1px solid #e5e2d9;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
        }
        legend {
            font-weight: 600;
            font-size: .9rem;
            color: #1D5345;
            padding: 0 .5rem;
        }
        .field {
            margin-top: .85rem;
        }
        .field label {
            display: block;
            font-size: .8rem;
            font-weight: 500;
            margin-bottom: .3rem;
            color: #2B2924;
        }
        .field input {
            width: 100%;
            padding: .6rem .75rem;
            border: 1px solid #d4d0c8;
            border-radius: 6px;
            font-family: inherit;
            font-size: .85rem;
            transition: border-color .2s;
        }
        .field input:focus {
            outline: none;
            border-color: #1D5345;
            box-shadow: 0 0 0 3px rgba(29,83,69,.1);
        }
        .field .hint {
            font-size: .72rem;
            color: #B2ACA9;
            margin-top: .2rem;
        }
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem;
        }
        .btn {
            display: inline-block;
            padding: .75rem 2rem;
            background: #1D5345;
            color: #E8E4D0;
            border: none;
            border-radius: 6px;
            font-family: inherit;
            font-size: .9rem;
            font-weight: 500;
            cursor: pointer;
            transition: background .2s;
            width: 100%;
            margin-top: 1.5rem;
        }
        .btn:hover { background: #153d33; }
        .btn:disabled { background: #B2ACA9; cursor: not-allowed; }
        .error {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #991b1b;
            padding: .75rem 1rem;
            border-radius: 8px;
            font-size: .82rem;
            margin-bottom: 1.25rem;
        }
        .success-box {
            text-align: center;
            padding: 1rem 0;
        }
        .success-box .check {
            width: 64px;
            height: 64px;
            background: #1D5345;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        .success-box h2 {
            font-family: 'Playfair Display', Georgia, serif;
            color: #1D5345;
            font-size: 1.3rem;
            margin-bottom: .75rem;
        }
        .success-box p { color: #6C5C4F; font-size: .85rem; line-height: 1.6; }
        .success-box a {
            display: inline-block;
            margin-top: 1.25rem;
            padding: .65rem 2rem;
            background: #1D5345;
            color: #E8E4D0;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
        }
        .success-box a:hover { background: #153d33; }
        .warning {
            background: #fffbeb;
            border: 1px solid #fbbf24;
            color: #92400e;
            padding: .75rem 1rem;
            border-radius: 8px;
            font-size: .82rem;
            margin-top: 1rem;
        }
        .checks { margin-bottom: 1.5rem; }
        .checks li {
            list-style: none;
            padding: .3rem 0;
            font-size: .82rem;
        }
        .checks li::before {
            display: inline-block;
            width: 1.25rem;
            margin-right: .35rem;
            font-weight: bold;
        }
        .checks li.ok::before { content: "\2713"; color: #1D5345; }
        .checks li.fail::before { content: "\2717"; color: #dc2626; }
        .checks li.warn::before { content: "~"; color: #d97706; }
        @media (max-width: 500px) {
            .installer { padding: 1.5rem 1.15rem; }
            .row { grid-template-columns: 1fr; }
            h1 { font-size: 1.3rem; }
        }
    </style>
</head>
<body>
    <div class="installer">

    <?php if ($success): ?>
        <!-- ── SUCCÈS ── -->
        <div class="success-box">
            <div class="check">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#E8E4D0" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h2>Installation terminee !</h2>
            <p>
                Le site est pret. Les tables ont ete creees dans la base de donnees.<br>
                Connectez-vous a l'administration pour personnaliser textes, photos et couleurs.
            </p>
            <a href="admin.php">Ouvrir l'administration</a>
            <div class="warning">
                <strong>Securite :</strong> Supprimez le fichier <code>install.php</code> de votre serveur maintenant.
            </div>
        </div>

    <?php elseif ($already_installed): ?>
        <!-- ── DÉJÀ INSTALLÉ ── -->
        <h1>Deja installe</h1>
        <p class="subtitle">Le site est deja configure.</p>
        <div class="warning">
            Les fichiers <code>.env</code> et <code>config/property.php</code> existent deja.<br>
            Si vous voulez reinstaller, supprimez ces deux fichiers puis rechargez cette page.
        </div>
        <a href="admin.php" class="btn" style="text-align:center;text-decoration:none;display:block;margin-top:1.25rem">Ouvrir l'administration</a>

    <?php else: ?>
        <!-- ── FORMULAIRE ── -->
        <h1>Installation</h1>
        <p class="subtitle">Configurez votre site en quelques clics.</p>

        <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Vérifications système -->
        <ul class="checks">
            <li class="<?= $checks['php'] ? 'ok' : 'fail' ?>">PHP <?= PHP_VERSION ?> (minimum 7.4)</li>
            <li class="<?= $checks['pdo'] ? 'ok' : 'fail' ?>">Extension PDO MySQL</li>
            <li class="<?= $checks['gd'] ? 'ok' : 'warn' ?>">Extension GD (compression images)<?= !$checks['gd'] ? ' — optionnel' : '' ?></li>
            <li class="<?= $checks['writable_root'] ? 'ok' : 'fail' ?>">Dossier racine inscriptible</li>
            <li class="<?= $checks['schema'] ? 'ok' : 'fail' ?>">Fichier schema.sql present</li>
        </ul>

        <?php if (!$all_ok): ?>
        <div class="error">Des prerequis ne sont pas remplis. Corrigez les elements en rouge avant de continuer.</div>
        <?php endif; ?>

        <form method="post" action="install.php">
            <input type="hidden" name="step" value="install">

            <fieldset>
                <legend>Votre logement</legend>
                <div class="row">
                    <div class="field">
                        <label for="prop_name">Nom *</label>
                        <input type="text" id="prop_name" name="prop_name" required placeholder="Maison des Lilas" value="<?= htmlspecialchars($_POST['prop_name'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label for="prop_mono">Initiales</label>
                        <input type="text" id="prop_mono" name="prop_mono" placeholder="ML" maxlength="3" value="<?= htmlspecialchars($_POST['prop_mono'] ?? '') ?>">
                        <div class="hint">2-3 lettres, affichees dans le logo</div>
                    </div>
                </div>
                <div class="field">
                    <label for="prop_tag">Slogan</label>
                    <input type="text" id="prop_tag" name="prop_tag" placeholder="Charme & Serenite" value="<?= htmlspecialchars($_POST['prop_tag'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="prop_loc">Ville et departement</label>
                    <input type="text" id="prop_loc" name="prop_loc" placeholder="Lyon · Rhone" value="<?= htmlspecialchars($_POST['prop_loc'] ?? '') ?>">
                </div>
                <div class="row">
                    <div class="field">
                        <label for="prop_phone">Telephone</label>
                        <input type="tel" id="prop_phone" name="prop_phone" placeholder="+33 6 00 00 00 00" value="<?= htmlspecialchars($_POST['prop_phone'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label for="prop_email">Email</label>
                        <input type="email" id="prop_email" name="prop_email" placeholder="contact@example.fr" value="<?= htmlspecialchars($_POST['prop_email'] ?? '') ?>">
                    </div>
                </div>
                <div class="field">
                    <label for="prop_addr">Adresse complete</label>
                    <input type="text" id="prop_addr" name="prop_addr" placeholder="12 rue des Lilas, 69001 Lyon" value="<?= htmlspecialchars($_POST['prop_addr'] ?? '') ?>">
                </div>
            </fieldset>

            <fieldset>
                <legend>Base de donnees MySQL</legend>
                <div class="hint" style="margin-bottom:.5rem">Ces informations se trouvent dans votre panneau d'hebergement (Ionos, OVH, etc.)</div>
                <div class="row">
                    <div class="field">
                        <label for="db_host">Serveur *</label>
                        <input type="text" id="db_host" name="db_host" required value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>">
                    </div>
                    <div class="field">
                        <label for="db_name">Nom de la base *</label>
                        <input type="text" id="db_name" name="db_name" required placeholder="dbs12345678" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="field">
                        <label for="db_user">Utilisateur *</label>
                        <input type="text" id="db_user" name="db_user" required placeholder="dbu12345678" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label for="db_pass">Mot de passe</label>
                        <input type="password" id="db_pass" name="db_pass" placeholder="••••••••">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Compte administrateur</legend>
                <div class="row">
                    <div class="field">
                        <label for="adm_user">Identifiant</label>
                        <input type="text" id="adm_user" name="adm_user" value="<?= htmlspecialchars($_POST['adm_user'] ?? 'admin') ?>">
                    </div>
                    <div class="field">
                        <label for="adm_pass">Mot de passe *</label>
                        <input type="password" id="adm_pass" name="adm_pass" required placeholder="Choisissez un mot de passe">
                    </div>
                </div>
            </fieldset>

            <button type="submit" class="btn" <?= !$all_ok ? 'disabled' : '' ?>>Installer le site</button>
        </form>

    <?php endif; ?>

    </div>
</body>
</html>
