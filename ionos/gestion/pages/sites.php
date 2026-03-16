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

// Dossier de déploiement des sites vitrine : ionos/gestion/sites/{slug}/
$sitesRoot = realpath(__DIR__ . '/..') . '/sites';
if (!is_dir($sitesRoot)) {
    mkdir($sitesRoot, 0755, true);
}
// Moteur FrenchySite source
$frenchysiteSource = realpath(__DIR__ . '/../../../frenchysite');

// Tables requises : voir db/install_tables.php

// Migration : ajouter site_slug et deploy_path si absents
try {
    $conn->exec("ALTER TABLE frenchysite_instances ADD COLUMN site_slug VARCHAR(100) NOT NULL DEFAULT '' AFTER db_prefix");
} catch (PDOException $e) { error_log('sites.php: ' . $e->getMessage()); }
try {
    $conn->exec("ALTER TABLE frenchysite_instances ADD COLUMN deploy_path VARCHAR(500) DEFAULT '' AFTER site_url");
} catch (PDOException $e) { error_log('sites.php: ' . $e->getMessage()); }

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
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

// ── Helper : charger les photos d'un logement ──
function loadLogementPhotos($conn, $logementId) {
    try {
        $stmt = $conn->prepare("SELECT * FROM logement_photos WHERE logement_id = :id ORDER BY ordre, id");
        $stmt->execute([':id' => $logementId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

// ── Helper : générer l'URL publique de recommandations pour un logement ──
function buildRecommandationsUrl($conn, $logementId) {
    try {
        $stmt = $conn->prepare("SELECT ville_id FROM liste_logements WHERE id = ?");
        $stmt->execute([$logementId]);
        $villeId = $stmt->fetchColumn();
        if (!$villeId) return '';

        // Vérifier qu'il y a des recommandations pour cette ville
        $stmt = $conn->prepare("SELECT COUNT(*) FROM ville_recommandations WHERE ville_id = ? AND actif = 1");
        $stmt->execute([$villeId]);
        if ($stmt->fetchColumn() == 0) return '';

        $token = md5($logementId . '-frenchybnb');
        return '/pages/recommandations_logement.php?token=' . $token;
    } catch (PDOException $e) {
        return '';
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
        $descClean = strip_tags($logementData['description']);
        $tagline = mb_substr($descClean, 0, 100);
        $stmtSetting->execute([$tagline, 'site_tagline']);
    }

    // Intégrations : Airbnb
    if (!empty($logementData['airbnb_url'])) {
        $stmtSetting->execute([$logementData['airbnb_url'], 'airbnb_url']);
        // Extraire l'ID Airbnb depuis l'URL (format: /rooms/123456)
        if (preg_match('#/rooms/(\d+)#', $logementData['airbnb_url'], $m)) {
            $stmtSetting->execute([$m[1], 'airbnb_id']);
        }
    }

    // ICS URL pour calendrier
    if (!empty($logementData['ics_url'])) {
        $stmtSetting->execute([$logementData['ics_url'], 'ics_url']);
    }

    // Recommandations URL (auto-générée si ville avec recommandations)
    $recoUrl = buildRecommandationsUrl($conn, $logementData['id']);
    if ($recoUrl) {
        $stmtSetting->execute([$recoUrl, 'recommandations_url']);
    }

    // Mettre à jour les textes avec les équipements
    $stmtText = $conn->prepare("UPDATE {$dbPrefix}texts SET field_value = ? WHERE section_key = ? AND field_key = ?");

    // Histoire : utiliser la description du logement
    if (!empty($logementData['description'])) {
        $descClean = strip_tags($logementData['description']);
        // Découper en 2 paragraphes si la description est assez longue
        $sentences = preg_split('/(?<=[.!?])\s+/', $descClean, -1, PREG_SPLIT_NO_EMPTY);
        if (count($sentences) > 2) {
            $mid = (int)ceil(count($sentences) / 2);
            $para1 = implode(' ', array_slice($sentences, 0, $mid));
            $para2 = implode(' ', array_slice($sentences, $mid));
            $stmtText->execute([$para1, 'histoire', 'para1']);
            $stmtText->execute([$para2, 'histoire', 'para2']);
        } else {
            $stmtText->execute([$descClean, 'histoire', 'para1']);
        }
    }

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
            if (!empty($equipements['molotov_tv']))   $streaming[] = 'Molotov TV';
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

        // Injecter les guides d'utilisation personnalisés
        if (!empty($equipements['guide_four'])) {
            $stmtText->execute([$equipements['guide_four'], 'guide_cuisine', 'four']);
        }
        if (!empty($equipements['guide_plaque_cuisson'])) {
            $stmtText->execute([$equipements['guide_plaque_cuisson'], 'guide_cuisine', 'induction']);
        }
        if (!empty($equipements['guide_micro_ondes'])) {
            $stmtText->execute([$equipements['guide_micro_ondes'], 'guide_cuisine', 'micro_ondes']);
        }
        if (!empty($equipements['guide_machine_cafe'])) {
            $stmtText->execute([$equipements['guide_machine_cafe'], 'guide_cuisine', 'nespresso']);
        }
        if (!empty($equipements['guide_lave_vaisselle'])) {
            $stmtText->execute([$equipements['guide_lave_vaisselle'], 'guide_cuisine', 'lave_vaisselle']);
        }
        if (!empty($equipements['guide_tv'])) {
            $stmtText->execute([$equipements['guide_tv'], 'guide_cinema', 'allumer']);
        }
        if (!empty($equipements['guide_canape_convertible'])) {
            $stmtText->execute([$equipements['guide_canape_convertible'], 'guide_canape', 'instructions']);
        }
        if (!empty($equipements['guide_chauffage'])) {
            $stmtText->execute([$equipements['guide_chauffage'], 'guide_chauffage', 'instructions']);
        }
        if (!empty($equipements['guide_climatisation'])) {
            $stmtText->execute([$equipements['guide_climatisation'], 'guide_climatisation', 'instructions']);
        }
        if (!empty($equipements['guide_machine_laver'])) {
            $stmtText->execute([$equipements['guide_machine_laver'], 'guide_menager', 'machine_laver']);
        }
        if (!empty($equipements['guide_seche_linge'])) {
            $stmtText->execute([$equipements['guide_seche_linge'], 'guide_menager', 'seche_linge']);
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
        ['chauffage','Chauffage',    'Comment régler le chauffage',                  '<path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/>', 7],
        ['climatisation','Climatisation','Comment utiliser la climatisation',        '<path d="M2 12h20M12 2v20M4.93 4.93l14.14 14.14M19.07 4.93L4.93 19.07"/>', 8],
        ['menager', 'Buanderie',    'Machine à laver et sèche-linge',               '<circle cx="12" cy="12" r="3"/><rect x="2" y="2" width="20" height="20" rx="2"/><path d="M6 2v2M18 2v2"/>', 9],
        ['canape',  'Canapé convertible','Comment déplier le canapé-lit',            '<path d="M2 4v16M22 4v16M2 12h20M6 12V8h12v4"/>', 10],
    ];

    $stmtGuide = $conn->prepare("INSERT IGNORE INTO {$dbPrefix}guides (slug, title, subtitle, icon_svg, is_active, sort_order) VALUES (?, ?, ?, ?, 1, ?)");
    foreach ($guidesData as $g) {
        $stmtGuide->execute($g);
    }
}

// ── Helper : copier les photos du logement vers le site déployé ──
function deployLogementPhotos($conn, $dbPrefix, $logementId, $deployPath) {
    $photos = loadLogementPhotos($conn, $logementId);
    if (empty($photos)) return 0;

    $uploadSource = __DIR__ . '/../uploads/photos/';
    $imported = 0;

    foreach ($photos as $i => $photo) {
        $srcFile = $uploadSource . $photo['filename'];
        if (!file_exists($srcFile)) continue;

        // Déterminer le groupe de destination
        if ($i === 0) {
            // Première photo = hero
            $group = 'hero';
            $key = 'hero';
            $destDir = $deployPath . '/assets/photos/hero/';
        } elseif ($i <= 6) {
            // Photos 2-7 = galerie
            $group = 'galerie';
            $key = 'photo_' . $i;
            $destDir = $deployPath . '/assets/photos/galerie/';
        } else {
            // Photos 8+ = aussi galerie
            $group = 'galerie';
            $key = 'photo_' . $i;
            $destDir = $deployPath . '/assets/photos/galerie/';
        }

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        // Copier le fichier
        $destFile = $destDir . $photo['filename'];
        if (!copy($srcFile, $destFile)) continue;

        $relPath = 'assets/photos/' . ($group === 'hero' ? 'hero/' : 'galerie/') . $photo['filename'];
        $isWide = ($i === 0 || $i === 1 || $i === 6) ? 1 : 0;
        $alt = $photo['caption'] ?: 'Photo du logement';

        // Insérer dans la table photos du site
        try {
            $conn->prepare("INSERT INTO {$dbPrefix}photos (photo_group, photo_key, file_path, alt_text, is_wide, sort_order) VALUES (?, ?, ?, ?, ?, ?)")
                 ->execute([$group, $key, $relPath, $alt, $isWide, $i]);
            $imported++;
        } catch (PDOException $e) {
            error_log('deployLogementPhotos: ' . $e->getMessage());
        }
    }

    // Photos 2-4 pour la section expérience (si on a assez de photos)
    $expKeys = ['confort', 'charme', 'accueil'];
    for ($j = 0; $j < 3; $j++) {
        $idx = $j + 1; // photos 2, 3, 4
        if (isset($photos[$idx])) {
            $srcFile = $uploadSource . $photos[$idx]['filename'];
            if (!file_exists($srcFile)) continue;

            $destDir = $deployPath . '/assets/photos/experience/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);

            $destFile = $destDir . $photos[$idx]['filename'];
            copy($srcFile, $destFile);

            $relPath = 'assets/photos/experience/' . $photos[$idx]['filename'];
            try {
                $conn->prepare("INSERT INTO {$dbPrefix}photos (photo_group, photo_key, file_path, alt_text, sort_order) VALUES (?, ?, ?, ?, ?)")
                     ->execute(['experience', $expKeys[$j], $relPath, $photos[$idx]['caption'] ?: ucfirst($expKeys[$j]), $j]);
            } catch (PDOException $e) {
                error_log('deployLogementPhotos exp: ' . $e->getMessage());
            }
        }
    }

    return $imported;
}

// ── Helper : resynchroniser les données d'un site existant ──
function resyncSiteData($conn, $dbPrefix, $logementId) {
    $logStmt = $conn->prepare("SELECT * FROM liste_logements WHERE id = ?");
    $logStmt->execute([$logementId]);
    $logement = $logStmt->fetch(PDO::FETCH_ASSOC);
    if (!$logement) return 'Logement introuvable';

    $equipements = loadLogementEquipements($conn, $logementId);

    $stmtSetting = $conn->prepare("UPDATE {$dbPrefix}settings SET setting_value = ? WHERE setting_key = ?");

    // Identité
    $stmtSetting->execute([$logement['nom_du_logement'], 'site_name']);
    if (!empty($logement['adresse'])) {
        $stmtSetting->execute([$logement['adresse'], 'address']);
        $stmtSetting->execute([$logement['adresse'], 'site_location']);
    }
    if (!empty($logement['description'])) {
        $stmtSetting->execute([mb_substr(strip_tags($logement['description']), 0, 100), 'site_tagline']);
    }

    // Airbnb
    if (!empty($logement['airbnb_url'])) {
        $stmtSetting->execute([$logement['airbnb_url'], 'airbnb_url']);
        if (preg_match('#/rooms/(\d+)#', $logement['airbnb_url'], $m)) {
            $stmtSetting->execute([$m[1], 'airbnb_id']);
        }
    }

    // ICS
    if (!empty($logement['ics_url'])) {
        $stmtSetting->execute([$logement['ics_url'], 'ics_url']);
    }

    // Recommandations
    $recoUrl = buildRecommandationsUrl($conn, $logementId);
    if ($recoUrl) {
        $stmtSetting->execute([$recoUrl, 'recommandations_url']);
    }

    // Bandeau chiffres clés
    $stmtText = $conn->prepare("UPDATE {$dbPrefix}texts SET field_value = ? WHERE section_key = ? AND field_key = ?");
    if (!empty($equipements['nombre_couchages'])) {
        $stmtText->execute([(string)$equipements['nombre_couchages'], 'band', 'stat1_number']);
    }
    if (!empty($equipements['nombre_chambres'])) {
        $stmtText->execute([(string)$equipements['nombre_chambres'], 'band', 'stat2_number']);
    }
    if (!empty($equipements['superficie_m2'])) {
        $stmtText->execute([(string)$equipements['superficie_m2'], 'band', 'stat3_number']);
    }

    // Description → histoire
    if (!empty($logement['description'])) {
        $descClean = strip_tags($logement['description']);
        $sentences = preg_split('/(?<=[.!?])\s+/', $descClean, -1, PREG_SPLIT_NO_EMPTY);
        if (count($sentences) > 2) {
            $mid = (int)ceil(count($sentences) / 2);
            $stmtText->execute([implode(' ', array_slice($sentences, 0, $mid)), 'histoire', 'para1']);
            $stmtText->execute([implode(' ', array_slice($sentences, $mid)), 'histoire', 'para2']);
        } else {
            $stmtText->execute([$descClean, 'histoire', 'para1']);
        }
    }

    // WiFi
    if (!empty($equipements['nom_wifi']))  $stmtText->execute([$equipements['nom_wifi'], 'guide_wifi', 'network_name']);
    if (!empty($equipements['code_wifi'])) $stmtText->execute([$equipements['code_wifi'], 'guide_wifi', 'password']);

    // Horaires
    $horaires = [];
    if (!empty($equipements['heure_checkin']))  $horaires[] = 'Arrivée à partir de ' . $equipements['heure_checkin'];
    if (!empty($equipements['heure_checkout'])) $horaires[] = 'Départ avant ' . $equipements['heure_checkout'];
    if (!empty($horaires)) {
        $stmtText->execute([implode(' · ', $horaires), 'hero', 'kicker']);
    }

    return true;
}

// ── Helper : recopier le moteur (PHP/CSS/JS) sans écraser config, .env, photos uploadées ──
function redeploySiteEngine($frenchysiteSource, $deployPath) {
    if (!is_dir($frenchysiteSource)) {
        throw new Exception("Moteur FrenchySite introuvable : $frenchysiteSource");
    }
    if (!is_dir($deployPath)) {
        throw new Exception("Dossier du site introuvable : $deployPath");
    }
    // Fichiers/dossiers à ne pas écraser (config spécifique au site)
    $exclude = ['.env', '.env.example', 'config', 'install.php'];

    $dir = opendir($frenchysiteSource);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..' || in_array($file, $exclude)) continue;
        $srcPath = $frenchysiteSource . '/' . $file;
        $dstPath = $deployPath . '/' . $file;
        if (is_dir($srcPath)) {
            // Pour assets/, on recopie tout sauf les photos uploadées
            if ($file === 'assets') {
                redeployAssetsDir($srcPath, $dstPath);
            } else {
                copyDir($srcPath, $dstPath);
            }
        } else {
            copy($srcPath, $dstPath);
        }
    }
    closedir($dir);
}

// ── Helper : recopier assets/ en préservant les photos uploadées ──
function redeployAssetsDir($src, $dst) {
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    $dir = opendir($src);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') continue;
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        if (is_dir($srcPath)) {
            // Ne pas écraser le dossier photos/ (contient les uploads du site)
            if ($file === 'photos') continue;
            copyDir($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
    closedir($dir);
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
function deploySite($frenchysiteSource, $deployPath, $dbPrefix, $siteName, $logementData, $adminPass, $conn = null) {
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
        . "    'airbnb_id'     => " . var_export(!empty($logementData['airbnb_url']) && preg_match('#/rooms/(\d+)#', $logementData['airbnb_url'], $_m) ? $_m[1] : '', true) . ",\n"
        . "    'airbnb_url'    => " . var_export($logementData['airbnb_url'] ?? '', true) . ",\n"
        . "    'ics_url'       => " . var_export($logementData['ics_url'] ?? '', true) . ",\n"
        . "    'matterport_id' => '',\n"
        . "    'superhote_planning_url' => '',\n"
        . "    'recommandations_url'    => " . var_export($conn ? buildRecommandationsUrl($conn, $logementData['id']) : '', true) . ",\n"
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
        . "        'visite'          => ['label' => 'Visite 360°',    'nav' => 'Visite 360°'],\n"
        . "        'recommandations' => ['label' => 'Recommandations','nav' => 'Recommandations'],\n"
        . "        'planning'        => ['label' => 'Disponibilités','nav' => 'Disponibilités'],\n"
        . "        'reservation'     => ['label' => 'Réservation',   'nav' => 'Réserver', 'id' => 'reserver'],\n"
        . "        'contact'         => ['label' => 'Contact',       'nav' => 'Contact'],\n"
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
        . "ADMIN_USER=admin@frenchy.local\n"
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

    // 6. Copier les photos du logement vers le site et les insérer en BDD
    if ($conn && !empty($logementData['id'])) {
        deployLogementPhotos($conn, $dbPrefix, $logementData['id'], $deployPath);
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
        } catch (PDOException $e) { error_log('sites.php: ' . $e->getMessage()); }
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
    } elseif (is_dir($sitesRoot . '/' . $site_slug)) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Le dossier <code>sites/{$site_slug}/</code> existe déjà sur le serveur</div>";
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
                        $siteUrl    = 'https://gestion.frenchyconciergerie.fr/sites/' . $site_slug;
                        $deployPath = $sitesRoot . '/' . $site_slug;

                        // Vérifier que le moteur source existe
                        if (!$frenchysiteSource || !is_dir($frenchysiteSource)) {
                            throw new Exception("Moteur FrenchySite introuvable. Chemin testé : " . (__DIR__ . '/../../../frenchysite') . " (realpath=" . var_export($frenchysiteSource, true) . ")");
                        }

                        // 1. Créer les tables BDD
                        $equipements = loadLogementEquipements($conn, $logement_id);
                        createSiteTables($conn, $dbPrefix, $siteName, $logement, $equipements);

                        // 2. Déployer le moteur FrenchySite
                        deploySite($frenchysiteSource, $deployPath, $dbPrefix, $siteName, $logement, $admin_pass, $conn);

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
                            ':admin_user'     => 'admin@frenchy.local',
                            ':admin_pass_hash' => $passHash,
                        ]);

                        // Vérifier que le déploiement a bien fonctionné
                        $deployOk = is_dir($deployPath) && file_exists($deployPath . '/index.php');

                        $feedback = "<div class='alert alert-success'>
                            <i class='fas fa-check-circle'></i> Site créé avec succès !<br>
                            <strong>" . htmlspecialchars($siteName) . "</strong><br>
                            <small>
                                Préfixe BDD : <code>{$dbPrefix}</code><br>
                                Dossier : <code>{$deployPath}</code> " . ($deployOk ? '✅' : '❌ fichiers absents !') . "<br>
                                Source : <code>{$frenchysiteSource}</code><br>
                                URL : <a href='{$siteUrl}' target='_blank'>{$siteUrl}</a><br>
                                Admin : <a href='{$siteUrl}/admin.php' target='_blank'>{$siteUrl}/admin.php</a> (admin@frenchy.local / {$admin_pass})
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

// ── RESYNCHRONISER les données d'un site ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resync_site'])) {
    validateCsrfToken();

    $id = (int)$_POST['site_id'];
    try {
        $stmt = $conn->prepare("SELECT * FROM frenchysite_instances WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $siteData = $stmt->fetch();

        if ($siteData) {
            $result = resyncSiteData($conn, $siteData['db_prefix'], $siteData['logement_id']);

            // Redéployer les fichiers du moteur (PHP, CSS, JS) sans écraser config/photos
            $engineMsg = '';
            if (!empty($siteData['deploy_path']) && is_dir($siteData['deploy_path'])) {
                redeploySiteEngine($frenchysiteSource, $siteData['deploy_path']);
                $engineMsg = ' — fichiers mis à jour';

                // Resync photos
                $conn->exec("DELETE FROM {$siteData['db_prefix']}photos");
                $nbPhotos = deployLogementPhotos($conn, $siteData['db_prefix'], $siteData['logement_id'], $siteData['deploy_path']);
                $photoMsg = $nbPhotos > 0 ? ", {$nbPhotos} photo(s) synchronisée(s)" : '';
            } else {
                $photoMsg = ' — dossier absent, photos non synchronisées';
            }

            if ($result === true) {
                $feedback = "<div class='alert alert-success'><i class='fas fa-sync'></i> Site \"" . htmlspecialchars($siteData['site_name']) . "\" resynchronisé{$engineMsg}{$photoMsg}</div>";
            } else {
                $feedback = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> " . htmlspecialchars($result) . "</div>";
            }
        }
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

// ── Générer un token bridge pour accéder à l'admin du site ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bridge_admin'])) {
    validateCsrfToken();

    $siteId = (int)$_POST['site_id'];
    $userId = $_SESSION['user_id'] ?? $_SESSION['id_intervenant'] ?? null;

    if ($userId) {
        try {
            // Créer la table si nécessaire
            $conn->exec("CREATE TABLE IF NOT EXISTS admin_bridge_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                token VARCHAR(128) NOT NULL UNIQUE,
                user_id INT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                used_at DATETIME DEFAULT NULL,
                INDEX idx_token (token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Générer un token sécurisé
            $token = bin2hex(random_bytes(32));
            $stmt = $conn->prepare("INSERT INTO admin_bridge_tokens (token, user_id) VALUES (:token, :user_id)");
            $stmt->execute([':token' => $token, ':user_id' => $userId]);

            // Récupérer l'URL du site
            $stmt = $conn->prepare("SELECT site_url FROM frenchysite_instances WHERE id = :id");
            $stmt->execute([':id' => $siteId]);
            $siteUrl = $stmt->fetchColumn();

            if ($siteUrl) {
                $adminUrl = rtrim($siteUrl, '/') . '/admin.php?bridge_token=' . urlencode($token);
                header('Location: ' . $adminUrl);
                exit;
            }
        } catch (PDOException $e) {
            $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur token : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
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
} catch (PDOException $e) { error_log('sites.php: ' . $e->getMessage()); }

$logements_dispo = [];
try {
    $logements_dispo = $conn->query("
        SELECT l.id, l.nom_du_logement, l.adresse
        FROM liste_logements l
        LEFT JOIN frenchysite_instances s ON l.id = s.logement_id
        WHERE s.id IS NULL AND (l.actif = 1 OR l.actif IS NULL)
        ORDER BY l.nom_du_logement ASC
    ")->fetchAll();
} catch (PDOException $e) { error_log('sites.php: ' . $e->getMessage()); }

$sitesHealth = [];
$sitesPhotos = [];
foreach ($sites as $site) {
    $sitesHealth[$site['id']] = countSiteTables($conn, $site['db_prefix']);
    // Compter les photos du logement source
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM logement_photos WHERE logement_id = ?");
        $stmt->execute([$site['logement_id']]);
        $sitesPhotos[$site['id']] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $sitesPhotos[$site['id']] = 0;
    }
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
                            <span class="input-group-text"></span>
                        </div>
                        <div class="form-text">
                            Le site sera accessible sur <strong>gestion.frenchyconciergerie.fr/sites/<span id="preview_url">___</span></strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="admin_pass" class="form-label"><i class="fas fa-lock"></i> Mot de passe admin</label>
                        <input type="text" class="form-control" id="admin_pass" name="admin_pass"
                               value="admin2025" placeholder="Mot de passe admin du site">
                        <div class="form-text">
                            Identifiants : <code>admin@frenchy.local</code> / ce mot de passe
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
                        <li>Copie les photos importées (hero, galerie, expérience)</li>
                        <li>Injecte Airbnb, calendrier, recommandations</li>
                    </ul>
                    <strong>3.</strong> Le site est immédiatement accessible via <code>/sites/{slug}/</code><br><br>
                    <strong>4.</strong> Personnalisez via <code>/admin.php</code> du site<br><br>
                    <strong>5.</strong> Utilisez <i class="fas fa-sync"></i> pour resynchroniser les données après modification du logement
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
                                    <th>Photos</th>
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
                                                <form method="POST" style="display:inline" target="_blank">
                                                    <?php echoCsrfField(); ?>
                                                    <input type="hidden" name="bridge_admin" value="1">
                                                    <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                                                    <button type="submit" class="btn btn-link btn-sm text-warning p-0" title="Ouvrir l'admin du site">
                                                        <small><i class="fas fa-cog"></i> admin</small>
                                                    </button>
                                                </form>
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
                                            <?php $nbPhotos = $sitesPhotos[$site['id']] ?? 0; ?>
                                            <?php if ($nbPhotos > 0): ?>
                                                <span class="badge bg-success"><i class="fas fa-images"></i> <?= $nbPhotos ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><i class="fas fa-image"></i> 0</span>
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
                                            <form method="POST" style="display:inline" title="Resynchroniser données + photos">
                                                <?php echoCsrfField(); ?>
                                                <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                                                <button type="submit" name="resync_site" class="btn btn-sm btn-info" title="Resynchroniser">
                                                    <i class="fas fa-sync"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-warning"
                                                    onclick="editSite(<?= htmlspecialchars(json_encode($site)) ?>)"
                                                    title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce site, ses tables BDD et le dossier sites/<?= htmlspecialchars($slug) ?>/ ?')">
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
