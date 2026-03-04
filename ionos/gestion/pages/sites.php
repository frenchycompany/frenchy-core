<?php
/**
 * Gestion des sites FrenchySite — VPS (ionos/gestion)
 * Création automatique : tables BDD + copie moteur + config .env + property.php
 */
include '../config.php';
include '../pages/menu.php';

if (!($conn instanceof PDO)) {
    die('Erreur: PDO non disponible.');
}

// Racine ionos (là où sont déployés les sites : ionos/vertefeuille, ionos/alexia, etc.)
$ionosRoot = realpath(__DIR__ . '/../../');
// Moteur FrenchySite source
$frenchysiteSource = realpath(__DIR__ . '/../../../frenchysite');

// ── Créer/mettre à jour la table de suivi ──
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS frenchysite_instances (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            logement_id   INT NOT NULL,
            db_prefix     VARCHAR(10) NOT NULL UNIQUE,
            site_slug     VARCHAR(100) NOT NULL UNIQUE,
            site_name     VARCHAR(255) NOT NULL,
            site_url      VARCHAR(500) DEFAULT '',
            deploy_path   VARCHAR(500) DEFAULT '',
            admin_user    VARCHAR(100) DEFAULT 'admin',
            admin_pass_hash VARCHAR(255) DEFAULT '',
            actif         TINYINT(1) NOT NULL DEFAULT 1,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_logement (logement_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Migration : ajouter site_slug et deploy_path si absents
    try {
        $conn->exec("ALTER TABLE frenchysite_instances ADD COLUMN site_slug VARCHAR(100) NOT NULL DEFAULT '' AFTER db_prefix");
    } catch (PDOException $e) { /* colonne existe déjà */ }
    try {
        $conn->exec("ALTER TABLE frenchysite_instances ADD COLUMN deploy_path VARCHAR(500) DEFAULT '' AFTER site_url");
    } catch (PDOException $e) { /* colonne existe déjà */ }
} catch (PDOException $e) {
    // Table existe deja
}

$feedback = '';

// ── Helper : générer un préfixe BDD unique ──
function generateDbPrefix($name, $conn) {
    $letters = strtolower(preg_replace('/[^a-zA-Z]/', '', $name));
    $letters = substr($letters, 0, 3);
    if (strlen($letters) < 2) $letters = 'fs';

    for ($i = 0; $i < 20; $i++) {
        $prefix = $letters . sprintf('%02d', rand(0, 99)) . '_';
        $stmt = $conn->prepare("SELECT COUNT(*) FROM frenchysite_instances WHERE db_prefix = :prefix");
        $stmt->execute([':prefix' => $prefix]);
        if ($stmt->fetchColumn() == 0) {
            return $prefix;
        }
    }
    return $letters . substr(time(), -4) . '_';
}

// ── Helper : charger les équipements d'un logement ──
function loadLogementEquipements($conn, $logementId) {
    try {
        $stmt = $conn->prepare("SELECT * FROM logement_equipements WHERE logement_id = :id");
        $stmt->execute([':id' => $logementId]);
        return $stmt->fetch() ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

// ── Helper : créer les tables BDD avec préfixe ──
function createSiteTables($conn, $dbPrefix, $siteName, $logementData, $equipements) {
    $schemaFile = __DIR__ . '/../../../frenchysite/db/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Fichier schema.sql introuvable dans frenchysite/db/");
    }

    $sql = file_get_contents($schemaFile);

    $sql = str_replace('vf_settings', $dbPrefix . 'settings', $sql);
    $sql = str_replace('vf_texts', $dbPrefix . 'texts', $sql);
    $sql = str_replace('vf_photos', $dbPrefix . 'photos', $sql);
    $sql = str_replace('vf_guides', $dbPrefix . 'guides', $sql);
    $sql = str_replace('vf_guide_blocks', $dbPrefix . 'guide_blocks', $sql);

    $conn->exec($sql);

    // Mettre à jour les settings avec les données du logement
    $stmtSetting = $conn->prepare("UPDATE {$dbPrefix}settings SET setting_value = ? WHERE setting_key = ?");
    $stmtSetting->execute([$siteName, 'site_name']);

    if (!empty($logementData['adresse'])) {
        $stmtSetting->execute([$logementData['adresse'], 'address']);
        $stmtSetting->execute([$logementData['adresse'], 'site_location']);
    }
    if (!empty($logementData['description'])) {
        $tagline = mb_substr(strip_tags($logementData['description']), 0, 100);
        $stmtSetting->execute([$tagline, 'site_tagline']);
    }

    // Mettre à jour les textes avec les équipements
    $stmtText = $conn->prepare("UPDATE {$dbPrefix}texts SET field_value = ? WHERE section_key = ? AND field_key = ?");

    if (!empty($equipements)) {
        if (!empty($equipements['nombre_couchages'])) {
            $stmtText->execute([(string)$equipements['nombre_couchages'], 'band', 'stat1_number']);
        }
        if (!empty($equipements['nombre_chambres'])) {
            $stmtText->execute([(string)$equipements['nombre_chambres'], 'band', 'stat2_number']);
        }
        if (!empty($equipements['superficie_m2'])) {
            $stmtText->execute([(string)$equipements['superficie_m2'], 'band', 'stat3_number']);
        }

        if (!empty($equipements['nom_wifi'])) {
            $stmtText->execute([$equipements['nom_wifi'], 'guide_wifi', 'network_name']);
        }
        if (!empty($equipements['code_wifi'])) {
            $stmtText->execute([$equipements['code_wifi'], 'guide_wifi', 'password']);
        }

        $cuisineItems = [];
        if (!empty($equipements['four']))              $cuisineItems[] = 'Four encastrable';
        if (!empty($equipements['plaque_cuisson'])) {
            $type = !empty($equipements['plaque_cuisson_type']) ? ' (' . $equipements['plaque_cuisson_type'] . ')' : '';
            $cuisineItems[] = 'Plaques de cuisson' . $type;
        }
        if (!empty($equipements['micro_ondes']))       $cuisineItems[] = 'Micro-ondes';
        if (!empty($equipements['lave_vaisselle']))    $cuisineItems[] = 'Lave-vaisselle';
        if (!empty($equipements['refrigerateur']))     $cuisineItems[] = 'Réfrigérateur';
        if (!empty($equipements['congelateur']))       $cuisineItems[] = 'Congélateur';
        if (!empty($equipements['bouilloire']))        $cuisineItems[] = 'Bouilloire';
        if (!empty($equipements['grille_pain']))       $cuisineItems[] = 'Grille-pain';
        if (!empty($equipements['ustensiles_cuisine'])) $cuisineItems[] = 'Ustensiles de cuisine';
        if (!empty($equipements['machine_cafe_type']) && $equipements['machine_cafe_type'] !== 'aucune') {
            $cafeType = $equipements['machine_cafe_type'];
            if ($cafeType === 'autre' && !empty($equipements['machine_cafe_autre'])) {
                $cafeType = $equipements['machine_cafe_autre'];
            }
            $cuisineItems[] = 'Machine à café ' . ucfirst($cafeType);
        }
        if (!empty($cuisineItems)) {
            $stmtText->execute([implode("\n", $cuisineItems), 'guide_cuisine', 'equipements']);
        }

        if (!empty($equipements['tv'])) {
            $streaming = [];
            if (!empty($equipements['netflix']))      $streaming[] = 'Netflix';
            if (!empty($equipements['amazon_prime'])) $streaming[] = 'Amazon Prime Video';
            if (!empty($equipements['disney_plus']))  $streaming[] = 'Disney+';
            if (!empty($streaming)) {
                $contenu = '<strong>' . implode(' / ', $streaming) . '</strong> — Utilisez la télécommande pour naviguer dans les applications. Les comptes sont déjà connectés.';
                $stmtText->execute([$contenu, 'guide_cinema', 'contenu']);
            }
        }

        $horaires = [];
        if (!empty($equipements['heure_checkin'])) {
            $horaires[] = 'Arrivée à partir de ' . $equipements['heure_checkin'];
        }
        if (!empty($equipements['heure_checkout'])) {
            $horaires[] = 'Départ avant ' . $equipements['heure_checkout'];
        }
        if (!empty($horaires)) {
            $stmtText->execute([implode(' · ', $horaires), 'hero', 'kicker']);
        }
    }

    // Seed les guides par défaut
    $guidesData = [
        ['wifi',    'WiFi',          'Accédez à Internet pendant votre séjour',       '<path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1" fill="currentColor" stroke="none"/>', 1],
        ['piscine', 'Piscine',       'Profitez de notre piscine privée',              '<path d="M2 12h20"/><path d="M2 16c1.5 1 3 1.5 4.5 1s3-1.5 4.5-1 3 .5 4.5 1 3 0 4.5-1"/><path d="M2 20c1.5 1 3 1.5 4.5 1s3-1.5 4.5-1 3 .5 4.5 1 3 0 4.5-1"/>', 2],
        ['sauna',   'Sauna',         'Un moment de détente absolue',                  '<path d="M7 10v2"/><path d="M5 8.5c0 0 .5-2 2-2s2 2 2 2"/><path d="M2 18c0 0 2-2 5-2s5 2 5 2"/><path d="M2 22c0 0 2-2 5-2s5 2 5 2"/>', 3],
        ['sport',   'Salle de Sport','Restez actif pendant votre séjour',             '<path d="M2 12h4m12 0h4"/><path d="M6 8v8"/><path d="M18 8v8"/><path d="M4 10v4"/><path d="M20 10v4"/>', 4],
        ['cinema',  'Salle Cinéma',  'Votre cinéma privé',                            '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 8h20"/><polygon points="10,11 10,17 15,14" fill="currentColor" stroke="none"/>', 5],
        ['cuisine', 'Cuisine',       'Tout pour préparer vos repas en toute autonomie','<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3zm0 0v7"/>', 6],
    ];

    $stmtGuide = $conn->prepare("INSERT IGNORE INTO {$dbPrefix}guides (slug, title, subtitle, icon_svg, is_active, sort_order) VALUES (?, ?, ?, ?, 1, ?)");
    foreach ($guidesData as $g) {
        $stmtGuide->execute($g);
    }
}

// ── Helper : copier un dossier récursivement ──
function copyDir($src, $dst) {
    if (!is_dir($src)) {
        throw new Exception("Dossier source introuvable : $src");
    }
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    $dir = opendir($src);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') continue;
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        if (is_dir($srcPath)) {
            copyDir($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
    closedir($dir);
}

// ── Helper : déployer le moteur FrenchySite dans un dossier ──
function deploySite($frenchysiteSource, $deployPath, $dbPrefix, $siteName, $logementData, $adminPass) {
    // 1. Copier le moteur
    copyDir($frenchysiteSource, $deployPath);

    // 2. Générer config/property.php
    $monogram = strtoupper(mb_substr(preg_replace('/[^a-zA-Z]/', '', $siteName), 0, 2));
    if (strlen($monogram) < 2) $monogram = 'FS';

    $location = !empty($logementData['adresse']) ? $logementData['adresse'] : 'France';
    $phone    = !empty($logementData['telephone']) ? $logementData['telephone'] : '+33 6 00 00 00 00';
    $phoneRaw = preg_replace('/[^+0-9]/', '', $phone);

    $propertyConfig = "<?php\nreturn [\n"
        . "    'name'      => " . var_export($siteName, true) . ",\n"
        . "    'monogram'  => " . var_export($monogram, true) . ",\n"
        . "    'tagline'   => 'Bienvenue chez vous',\n"
        . "    'location'  => " . var_export($location, true) . ",\n"
        . "    'phone'     => " . var_export($phone, true) . ",\n"
        . "    'phone_raw' => " . var_export($phoneRaw, true) . ",\n"
        . "    'email'     => 'contact@frenchyconciergerie.fr',\n"
        . "    'address'   => " . var_export($location, true) . ",\n"
        . "    'db_prefix' => " . var_export($dbPrefix, true) . ",\n"
        . "    'airbnb_id'     => '',\n"
        . "    'matterport_id' => '',\n"
        . "    'colors' => [\n"
        . "        'green'    => '#1D5345',\n"
        . "        'green_dk' => '#153d33',\n"
        . "        'beige'    => '#CFCDB0',\n"
        . "        'grey'     => '#B2ACA9',\n"
        . "        'brown'    => '#6C5C4F',\n"
        . "        'offwhite' => '#E8E4D0',\n"
        . "        'dark'     => '#2B2924',\n"
        . "    ],\n"
        . "    'font_display' => 'Playfair Display',\n"
        . "    'font_body'    => 'Inter',\n"
        . "    'sections' => [\n"
        . "        'hero'        => ['label' => 'Hero (accueil)'],\n"
        . "        'band'        => ['label' => 'Bandeau chiffres clés'],\n"
        . "        'histoire'    => ['label' => 'Histoire',      'nav' => 'Histoire'],\n"
        . "        'experience'  => ['label' => 'L\\'expérience', 'nav' => 'L\\'expérience'],\n"
        . "        'galerie'     => ['label' => 'Galerie',       'nav' => 'Galerie'],\n"
        . "        'visite'      => ['label' => 'Visite 360°',   'nav' => 'Visite 360°'],\n"
        . "        'reservation' => ['label' => 'Réservation',   'nav' => 'Réserver', 'id' => 'reserver'],\n"
        . "        'contact'     => ['label' => 'Contact',       'nav' => 'Contact'],\n"
        . "    ],\n"
        . "    'guides' => [\n"
        . "        'wifi'    => ['label' => 'WiFi',    'admin_label' => 'WiFi',            'icon' => '<path d=\"M5 12.55a11 11 0 0 1 14.08 0\"/><path d=\"M1.42 9a16 16 0 0 1 21.16 0\"/><path d=\"M8.53 16.11a6 6 0 0 1 6.95 0\"/><circle cx=\"12\" cy=\"20\" r=\"1\" fill=\"currentColor\" stroke=\"none\"/>'],\n"
        . "        'piscine' => ['label' => 'Piscine', 'admin_label' => 'Piscine',         'icon' => '<path d=\"M2 12h20\"/><path d=\"M2 16c1.5 1 3 1.5 4.5 1s3-1.5 4.5-1 3 .5 4.5 1 3 0 4.5-1\"/><path d=\"M2 20c1.5 1 3 1.5 4.5 1s3-1.5 4.5-1 3 .5 4.5 1 3 0 4.5-1\"/>'],\n"
        . "        'sauna'   => ['label' => 'Sauna',   'admin_label' => 'Sauna',           'icon' => '<path d=\"M7 10v2\"/><path d=\"M5 8.5c0 0 .5-2 2-2s2 2 2 2\"/><path d=\"M2 18c0 0 2-2 5-2s5 2 5 2\"/><path d=\"M2 22c0 0 2-2 5-2s5 2 5 2\"/>'],\n"
        . "        'sport'   => ['label' => 'Sport',   'admin_label' => 'Salle de Sport',  'icon' => '<path d=\"M2 12h4m12 0h4\"/><path d=\"M6 8v8\"/><path d=\"M18 8v8\"/><path d=\"M4 10v4\"/><path d=\"M20 10v4\"/>'],\n"
        . "        'cinema'  => ['label' => 'Cinéma',  'admin_label' => 'Cinéma',          'icon' => '<rect x=\"2\" y=\"4\" width=\"20\" height=\"16\" rx=\"2\"/><path d=\"M2 8h20\"/><polygon points=\"10,11 10,17 15,14\" fill=\"currentColor\" stroke=\"none\"/>'],\n"
        . "        'cuisine' => ['label' => 'Cuisine', 'admin_label' => 'Cuisine',         'icon' => '<path d=\"M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2\"/><path d=\"M7 2v20\"/><path d=\"M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3zm0 0v7\"/>'],\n"
        . "    ],\n"
        . "    'photo_fallbacks' => [\n"
        . "        'hero' => 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?w=2000&q=80',\n"
        . "        'galerie' => [\n"
        . "            ['url' => 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?w=1200&q=80', 'alt' => 'Vue extérieure',     'wide' => true],\n"
        . "            ['url' => 'https://images.unsplash.com/photo-1582268611958-ebfd161ef9cf?w=800&q=80',  'alt' => 'Grand salon',        'wide' => false],\n"
        . "            ['url' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80',  'alt' => 'Chambre principale', 'wide' => false],\n"
        . "            ['url' => 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800&q=80',     'alt' => 'Jardin',             'wide' => false],\n"
        . "        ],\n"
        . "        'experience' => [\n"
        . "            'confort' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&q=80',\n"
        . "            'charme'  => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800&q=80',\n"
        . "            'accueil' => 'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?w=800&q=80',\n"
        . "        ],\n"
        . "    ],\n"
        . "];\n";

    file_put_contents($deployPath . '/config/property.php', $propertyConfig);

    // 3. Générer .env avec les mêmes credentials BDD que gestion
    $dbHost = env('DB_HOST', 'localhost');
    $dbName = env('DB_NAME', '');
    $dbUser = env('DB_USER', '');
    $dbPass = env('DB_PASSWORD', '');

    $envContent = "# Site vitrine : {$siteName}\n"
        . "# Généré automatiquement le " . date('Y-m-d H:i:s') . "\n\n"
        . "DB_HOST={$dbHost}\n"
        . "DB_NAME={$dbName}\n"
        . "DB_USER={$dbUser}\n"
        . "DB_PASS={$dbPass}\n\n"
        . "ADMIN_USER=admin\n"
        . "ADMIN_PASS={$adminPass}\n\n"
        . "APP_DEBUG=false\n";

    file_put_contents($deployPath . '/.env', $envContent);

    // 4. Créer les dossiers photos s'ils n'existent pas
    $photoDirs = ['assets/photos/hero', 'assets/photos/galerie', 'assets/photos/experience'];
    foreach ($photoDirs as $dir) {
        $fullDir = $deployPath . '/' . $dir;
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }
    }

    // 5. Supprimer le .env.example s'il a été copié
    $envExample = $deployPath . '/.env.example';
    if (file_exists($envExample)) {
        unlink($envExample);
    }
}

// ── Helper : supprimer un dossier récursivement ──
function removeDir($dir) {
    if (!is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getRealPath());
        } else {
            unlink($item->getRealPath());
        }
    }
    rmdir($dir);
}

// ── Helper : supprimer les tables BDD d'un site ──
function dropSiteTables($conn, $dbPrefix) {
    $tables = ['guide_blocks', 'guides', 'photos', 'texts', 'settings'];
    foreach ($tables as $t) {
        $conn->exec("DROP TABLE IF EXISTS `{$dbPrefix}{$t}`");
    }
}

// ── Compter les tables existantes pour un préfixe ──
function countSiteTables($conn, $dbPrefix) {
    $count = 0;
    $tables = ['settings', 'texts', 'photos', 'guides', 'guide_blocks'];
    foreach ($tables as $t) {
        try {
            $conn->query("SELECT 1 FROM `{$dbPrefix}{$t}` LIMIT 1");
            $count++;
        } catch (PDOException $e) {}
    }
    return $count;
}

// ══════════════════════════════════════════════
// ACTIONS POST
// ══════════════════════════════════════════════

// ── CRÉER un nouveau site ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_site'])) {
    validateCsrfToken();

    $logement_id = (int)($_POST['logement_id'] ?? 0);
    $site_slug   = trim($_POST['site_slug'] ?? '');
    $admin_pass  = trim($_POST['admin_pass'] ?? 'admin2025');

    // Nettoyer le slug : minuscules, alphanum + tirets uniquement
    $site_slug = strtolower(preg_replace('/[^a-z0-9\-]/', '', $site_slug));

    if ($logement_id <= 0) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Veuillez sélectionner un logement</div>";
    } elseif (empty($site_slug) || strlen($site_slug) < 3) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Le nom du sous-domaine doit faire au moins 3 caractères (lettres, chiffres, tirets)</div>";
    } elseif (is_dir($ionosRoot . '/' . $site_slug)) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Le dossier <code>{$site_slug}/</code> existe déjà sur le serveur</div>";
    } else {
        // Vérifier unicité logement
        $stmt = $conn->prepare("SELECT COUNT(*) FROM frenchysite_instances WHERE logement_id = :lid");
        $stmt->execute([':lid' => $logement_id]);
        if ($stmt->fetchColumn() > 0) {
            $feedback = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Ce logement a déjà un site associé</div>";
        } else {
            // Vérifier unicité slug
            $stmt = $conn->prepare("SELECT COUNT(*) FROM frenchysite_instances WHERE site_slug = :slug");
            $stmt->execute([':slug' => $site_slug]);
            if ($stmt->fetchColumn() > 0) {
                $feedback = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Ce sous-domaine est déjà utilisé</div>";
            } else {
                $stmt = $conn->prepare("SELECT * FROM liste_logements WHERE id = :id");
                $stmt->execute([':id' => $logement_id]);
                $logement = $stmt->fetch();

                if (!$logement) {
                    $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Logement introuvable</div>";
                } else {
                    try {
                        $siteName   = $logement['nom_du_logement'];
                        $dbPrefix   = generateDbPrefix($siteName, $conn);
                        $siteUrl    = 'https://' . $site_slug . '.frenchyconciergerie.fr';
                        $deployPath = $ionosRoot . '/' . $site_slug;

                        // 1. Créer les tables BDD
                        $equipements = loadLogementEquipements($conn, $logement_id);
                        createSiteTables($conn, $dbPrefix, $siteName, $logement, $equipements);

                        // 2. Déployer le moteur FrenchySite
                        deploySite($frenchysiteSource, $deployPath, $dbPrefix, $siteName, $logement, $admin_pass);

                        // 3. Enregistrer l'instance
                        $passHash = password_hash($admin_pass, PASSWORD_BCRYPT);
                        $stmt = $conn->prepare("
                            INSERT INTO frenchysite_instances (logement_id, db_prefix, site_slug, site_name, site_url, deploy_path, admin_user, admin_pass_hash)
                            VALUES (:logement_id, :db_prefix, :site_slug, :site_name, :site_url, :deploy_path, :admin_user, :admin_pass_hash)
                        ");
                        $stmt->execute([
                            ':logement_id'    => $logement_id,
                            ':db_prefix'      => $dbPrefix,
                            ':site_slug'      => $site_slug,
                            ':site_name'      => $siteName,
                            ':site_url'       => $siteUrl,
                            ':deploy_path'    => $deployPath,
                            ':admin_user'     => 'admin',
                            ':admin_pass_hash' => $passHash,
                        ]);

                        $feedback = "<div class='alert alert-success'>
                            <i class='fas fa-check-circle'></i> Site créé avec succès !<br>
                            <strong>" . htmlspecialchars($siteName) . "</strong><br>
                            <small>
                                Préfixe BDD : <code>{$dbPrefix}</code><br>
                                Dossier : <code>{$site_slug}/</code><br>
                                URL : <a href='{$siteUrl}' target='_blank'>{$siteUrl}</a><br>
                                Admin : <a href='{$siteUrl}/admin.php' target='_blank'>{$siteUrl}/admin.php</a> (admin / {$admin_pass})
                            </small>
                        </div>";
                    } catch (Exception $e) {
                        // Nettoyer en cas d'erreur
                        if (isset($deployPath) && is_dir($deployPath)) {
                            removeDir($deployPath);
                        }
                        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                }
            }
        }
    }
}

// ── MODIFIER un site ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_site'])) {
    validateCsrfToken();

    $id         = (int)$_POST['site_id'];
    $admin_pass = trim($_POST['admin_pass'] ?? '');

    try {
        if ($admin_pass) {
            $passHash = password_hash($admin_pass, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE frenchysite_instances SET admin_pass_hash = :pass WHERE id = :id");
            $stmt->execute([':pass' => $passHash, ':id' => $id]);
        }
        $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Site mis à jour</div>";
    } catch (PDOException $e) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// ── ACTIVER / DÉSACTIVER ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_site'])) {
    validateCsrfToken();

    $id = (int)$_POST['site_id'];
    try {
        $conn->prepare("UPDATE frenchysite_instances SET actif = NOT actif WHERE id = :id")->execute([':id' => $id]);
        $stmt = $conn->prepare("SELECT actif, site_name FROM frenchysite_instances WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        $status = $result['actif'] ? 'activé' : 'désactivé';
        $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Site \"" . htmlspecialchars($result['site_name']) . "\" {$status}</div>";
    } catch (PDOException $e) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// ── SUPPRIMER un site ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_site'])) {
    validateCsrfToken();

    $id = (int)$_POST['site_id'];
    try {
        $stmt = $conn->prepare("SELECT db_prefix, site_name, site_slug, deploy_path FROM frenchysite_instances WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $site = $stmt->fetch();

        if ($site) {
            // Supprimer les tables BDD
            dropSiteTables($conn, $site['db_prefix']);

            // Supprimer le dossier déployé
            if (!empty($site['deploy_path']) && is_dir($site['deploy_path'])) {
                removeDir($site['deploy_path']);
            }

            $conn->prepare("DELETE FROM frenchysite_instances WHERE id = :id")->execute([':id' => $id]);
            $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Site \"" . htmlspecialchars($site['site_name']) . "\" supprimé (tables <code>{$site['db_prefix']}*</code> + dossier <code>{$site['site_slug']}/</code>)</div>";
        }
    } catch (PDOException $e) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// ── Charger les données ──
$sites = [];
try {
    $sites = $conn->query("
        SELECT s.*, l.nom_du_logement, l.adresse
        FROM frenchysite_instances s
        LEFT JOIN liste_logements l ON s.logement_id = l.id
        ORDER BY s.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {}

$logements_dispo = [];
try {
    $logements_dispo = $conn->query("
        SELECT l.id, l.nom_du_logement, l.adresse
        FROM liste_logements l
        LEFT JOIN frenchysite_instances s ON l.id = s.logement_id
        WHERE s.id IS NULL AND (l.actif = 1 OR l.actif IS NULL)
        ORDER BY l.nom_du_logement ASC
    ")->fetchAll();
} catch (PDOException $e) {}

$sitesHealth = [];
foreach ($sites as $site) {
    $sitesHealth[$site['id']] = countSiteTables($conn, $site['db_prefix']);
}
?>

<div class="container-fluid mt-4">

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="display-5">
            <i class="fas fa-globe text-primary"></i> Sites vitrine
        </h1>
        <p class="lead text-muted">Créez et gérez les sites web de vos logements — déploiement automatique</p>
    </div>
</div>

<?= $feedback ?>

<div class="row">
    <!-- Formulaire de création -->
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-plus"></i> Nouveau site</h5>
            </div>
            <div class="card-body">
                <?php if (count($logements_dispo) === 0): ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> Tous les logements actifs ont déjà un site, ou aucun logement n'est enregistré.
                    </div>
                <?php else: ?>
                <form method="POST">
                    <?php echoCsrfField(); ?>

                    <div class="mb-3">
                        <label for="logement_id" class="form-label"><i class="fas fa-home"></i> Logement *</label>
                        <select class="form-select" id="logement_id" name="logement_id" required>
                            <option value="">-- Sélectionnez un logement --</option>
                            <?php foreach ($logements_dispo as $lg): ?>
                                <option value="<?= $lg['id'] ?>" data-nom="<?= htmlspecialchars($lg['nom_du_logement']) ?>">
                                    <?= htmlspecialchars($lg['nom_du_logement']) ?>
                                    <?php if ($lg['adresse']): ?>
                                        — <?= htmlspecialchars(substr($lg['adresse'], 0, 30)) ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="site_slug" class="form-label"><i class="fas fa-link"></i> Nom du sous-domaine *</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="site_slug" name="site_slug"
                                   placeholder="alexia" pattern="[a-z0-9\-]{3,}" required
                                   title="Lettres minuscules, chiffres et tirets (min 3 caractères)">
                            <span class="input-group-text">.frenchyconciergerie.fr</span>
                        </div>
                        <div class="form-text">
                            Le site sera accessible sur <strong id="preview_url">___</strong>.frenchyconciergerie.fr
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="admin_pass" class="form-label"><i class="fas fa-lock"></i> Mot de passe admin</label>
                        <input type="text" class="form-control" id="admin_pass" name="admin_pass"
                               value="admin2025" placeholder="Mot de passe admin du site">
                        <div class="form-text">
                            Identifiants : <code>admin</code> / ce mot de passe
                        </div>
                    </div>

                    <button type="submit" name="create_site" class="btn btn-success w-100">
                        <i class="fas fa-rocket"></i> Créer et déployer le site
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info -->
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-info-circle"></i> Comment ça marche</h6>
            </div>
            <div class="card-body">
                <small>
                    <strong>1.</strong> Sélectionnez un logement et choisissez un nom de sous-domaine<br><br>
                    <strong>2.</strong> Cliquez sur "Créer" — le système fait tout automatiquement :<br>
                    <ul class="mb-2">
                        <li>Crée les tables BDD avec un préfixe unique</li>
                        <li>Copie le moteur FrenchySite dans un nouveau dossier</li>
                        <li>Configure le <code>.env</code> et <code>property.php</code></li>
                        <li>Pré-remplit les données du logement et équipements</li>
                    </ul>
                    <strong>3.</strong> Créez le sous-domaine chez IONOS pointant vers le dossier<br><br>
                    <strong>4.</strong> Personnalisez via <code>/admin.php</code> du site
                </small>
            </div>
        </div>
    </div>

    <!-- Liste des sites -->
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-list"></i> Sites créés (<?= count($sites) ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (count($sites) === 0): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Aucun site créé pour le moment.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Logement</th>
                                    <th>Sous-domaine</th>
                                    <th>Préfixe</th>
                                    <th>Santé</th>
                                    <th>Dossier</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sites as $site): ?>
                                    <?php
                                    $health = $sitesHealth[$site['id']] ?? 0;
                                    $slug = $site['site_slug'] ?? '';
                                    $folderExists = !empty($site['deploy_path']) && is_dir($site['deploy_path']);
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($site['nom_du_logement'] ?? 'Logement supprimé') ?></strong>
                                        </td>
                                        <td>
                                            <?php if (!empty($site['site_url'])): ?>
                                                <a href="<?= htmlspecialchars($site['site_url']) ?>" target="_blank" title="Ouvrir le site">
                                                    <?= htmlspecialchars($slug ?: $site['site_url']) ?>
                                                    <i class="fas fa-external-link-alt ms-1"></i>
                                                </a>
                                                <br>
                                                <a href="<?= htmlspecialchars(rtrim($site['site_url'], '/')) ?>/admin.php" target="_blank" class="text-warning" title="Admin">
                                                    <small><i class="fas fa-cog"></i> admin</small>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted"><?= htmlspecialchars($slug ?: '—') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?= htmlspecialchars($site['db_prefix']) ?></code></td>
                                        <td>
                                            <?php if ($health === 5): ?>
                                                <span class="badge bg-success"><i class="fas fa-check"></i> 5/5</span>
                                            <?php elseif ($health > 0): ?>
                                                <span class="badge bg-warning text-dark"><?= $health ?>/5</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">0/5</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($folderExists): ?>
                                                <span class="badge bg-success"><i class="fas fa-folder"></i> OK</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><i class="fas fa-folder-open"></i> Absent</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display:inline">
                                                <?php echoCsrfField(); ?>
                                                <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                                                <?php if ($site['actif']): ?>
                                                    <button type="submit" name="toggle_site" class="btn btn-sm btn-success" title="Désactiver">
                                                        <i class="fas fa-check-circle"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" name="toggle_site" class="btn btn-sm btn-secondary" title="Activer">
                                                        <i class="fas fa-pause-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                        <td class="text-nowrap">
                                            <button type="button" class="btn btn-sm btn-warning"
                                                    onclick="editSite(<?= htmlspecialchars(json_encode($site)) ?>)"
                                                    title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce site, ses tables BDD et le dossier <?= htmlspecialchars($slug) ?>/ ?')">
                                                <?php echoCsrfField(); ?>
                                                <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                                                <button type="submit" name="delete_site" class="btn btn-sm btn-danger" title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</div><!-- /container-fluid -->

<!-- Modal de modification -->
<div class="modal fade" id="editSiteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Modifier le site</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echoCsrfField(); ?>
                <input type="hidden" name="site_id" id="edit_site_id">

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-home"></i> Logement</label>
                        <input type="text" class="form-control" id="edit_site_name" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-link"></i> URL</label>
                        <input type="text" class="form-control" id="edit_site_url" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-database"></i> Préfixe BDD</label>
                        <input type="text" class="form-control" id="edit_db_prefix" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="edit_admin_pass" class="form-label"><i class="fas fa-lock"></i> Nouveau mot de passe admin</label>
                        <input type="password" class="form-control" id="edit_admin_pass" name="admin_pass" placeholder="Laisser vide pour ne pas changer">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" name="update_site" class="btn btn-warning">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Prévisualisation du slug
const slugInput = document.getElementById('site_slug');
const previewUrl = document.getElementById('preview_url');
if (slugInput && previewUrl) {
    slugInput.addEventListener('input', function() {
        const val = this.value.toLowerCase().replace(/[^a-z0-9\-]/g, '');
        this.value = val;
        previewUrl.textContent = val || '___';
    });
}

// Auto-suggestion du slug depuis le nom du logement
const logementSelect = document.getElementById('logement_id');
if (logementSelect && slugInput) {
    logementSelect.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const nom = opt.dataset.nom || '';
        if (nom && !slugInput.value) {
            // Générer un slug depuis le nom
            let slug = nom.toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')  // supprimer accents
                .replace(/[^a-z0-9]+/g, '-')  // remplacer non-alphanum par tiret
                .replace(/^-+|-+$/g, '');      // trim tirets
            slugInput.value = slug;
            if (previewUrl) previewUrl.textContent = slug;
        }
    });
}

function editSite(site) {
    document.getElementById('edit_site_id').value = site.id;
    document.getElementById('edit_site_name').value = site.site_name || '';
    document.getElementById('edit_site_url').value = site.site_url || '';
    document.getElementById('edit_db_prefix').value = site.db_prefix || '';
    document.getElementById('edit_admin_pass').value = '';
    new bootstrap.Modal(document.getElementById('editSiteModal')).show();
}
</script>
