<?php
/**
 * Import auto photos Airbnb
 * Recupere les photos des annonces Airbnb via leurs URLs
 * et les associe aux logements FrenchyConciergerie
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_db.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../error.php?message=" . urlencode('Acces reserve aux administrateurs.'));
    exit;
}

$rpi = getRpiPdo();

// Auto-creation table photos
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS logement_photos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            logement_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            url_source TEXT DEFAULT NULL,
            caption VARCHAR(255) DEFAULT NULL,
            ordre INT DEFAULT 0,
            source ENUM('airbnb', 'booking', 'manual', 'autre') DEFAULT 'airbnb',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_logement (logement_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (PDOException $e) {}

// Repertoire uploads
$uploadDir = __DIR__ . '/../uploads/photos/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$feedback = '';
$scraped_images = [];

// Logements
$logements = [];
try {
    $logements = $conn->query("SELECT id, nom_du_logement, actif FROM liste_logements ORDER BY actif DESC, nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Liens Airbnb des logements (depuis market_competitors ou liste_logements)
$airbnbLinks = [];
try {
    $airbnbLinks = $rpi->query("
        SELECT mc.url, mc.nom, mc.airbnb_id,
               (SELECT ll.id FROM liste_logements ll WHERE ll.nom_du_logement LIKE CONCAT('%', SUBSTRING(mc.nom, 1, 20), '%') LIMIT 1) as matched_logement_id
        FROM market_competitors mc
        WHERE mc.url IS NOT NULL AND mc.url != ''
        ORDER BY mc.nom
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// === ACTIONS POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    // Scraper une URL Airbnb pour extraire les photos
    if (isset($_POST['scrape_url_btn'])) {
        $scrape_url = trim($_POST['scrape_url'] ?? '');
        $logement_id = (int)$_POST['logement_id'];

        if ($logement_id > 0 && !empty($scrape_url)) {
            $scraped_images = [];
            $scrape_error = '';

            // Fetch la page
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $scrape_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: fr-FR,fr;q=0.9,en;q=0.8',
                ],
            ]);
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($html && $httpCode === 200) {
                $found_urls = [];

                // 1) Chercher les URLs muscache.com (CDN Airbnb) dans tout le HTML
                if (preg_match_all('#https?://a\d+\.muscache\.com/im/pictures/[a-zA-Z0-9/_\-\.]+\.(?:jpg|jpeg|webp|png)#i', $html, $m)) {
                    $found_urls = array_merge($found_urls, $m[0]);
                }

                // 2) Chercher dans les attributs data et src
                if (preg_match_all('#https?://a\d+\.muscache\.com/im/(?:ml-)?pictures/[^\s"\'<>]+#i', $html, $m)) {
                    $found_urls = array_merge($found_urls, $m[0]);
                }

                // 3) Chercher les URLs d'images Airbnb encodees dans le JSON embarque
                if (preg_match_all('#https?:%2F%2Fa\d+\.muscache\.com%2Fim%2Fpictures%2F[^"\'&\s]+#i', $html, $m)) {
                    foreach ($m[0] as $encoded) {
                        $found_urls[] = urldecode($encoded);
                    }
                }

                // 4) og:image et meta images
                if (preg_match_all('#<meta[^>]+content=["\']?(https?://[^"\'>\s]+muscache[^"\'>\s]+)["\']?#i', $html, $m)) {
                    $found_urls = array_merge($found_urls, $m[1]);
                }

                // Deduplication et nettoyage
                $clean_urls = [];
                foreach ($found_urls as $u) {
                    // Nettoyer les parametres de taille pour avoir la meilleure qualite
                    $u = preg_replace('/\?im_w=\d+/', '?im_w=1200', $u);
                    // Retirer les doublons par nom de fichier
                    $basename = preg_replace('/\?.*$/', '', $u);
                    if (!isset($clean_urls[$basename])) {
                        $clean_urls[$basename] = $u;
                    }
                }

                // Filtrer les miniatures (trop petites) et icones
                $scraped_images = array_values(array_filter($clean_urls, function($url) {
                    // Exclure les images de profil, icones, logos
                    if (preg_match('/(User-|avatar|logo|icon|badge|flag|marker)/i', $url)) return false;
                    // Exclure les tres petites images (im_w < 200)
                    if (preg_match('/im_w=(\d+)/', $url, $wm) && (int)$wm[1] < 200) return false;
                    return true;
                }));

                if (empty($scraped_images)) {
                    $scrape_error = "Aucune photo trouvee dans cette page. Le site bloque peut-etre le scraping.";
                }
            } else {
                $scrape_error = "Impossible de charger la page" . ($curlError ? " : $curlError" : " (HTTP $httpCode)");
            }

            if ($scrape_error) {
                $feedback = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> $scrape_error</div>";
            }
        }
    }

    // Importer les photos selectionnees depuis le scraping
    if (isset($_POST['import_scraped'])) {
        $logement_id = (int)$_POST['logement_id'];
        $selected = $_POST['scraped_urls'] ?? [];

        if ($logement_id > 0 && !empty($selected)) {
            $imported = 0;
            $errors = 0;

            foreach ($selected as $i => $url) {
                if (!filter_var($url, FILTER_VALIDATE_URL)) { $errors++; continue; }

                $ext = 'jpg';
                if (preg_match('/\.(png|webp|jpeg|jpg)(\?|$)/i', $url, $m)) {
                    $ext = strtolower($m[1]);
                }
                $filename = 'logement_' . $logement_id . '_' . time() . '_' . $i . '.' . $ext;
                $filepath = $uploadDir . $filename;

                $ctx = stream_context_create([
                    'http' => ['timeout' => 15, 'user_agent' => 'Mozilla/5.0 (compatible; FrenchyBot/1.0)'],
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                ]);
                $imageData = @file_get_contents($url, false, $ctx);

                if ($imageData && strlen($imageData) > 1000) {
                    file_put_contents($filepath, $imageData);
                    try {
                        $conn->prepare("INSERT INTO logement_photos (logement_id, filename, url_source, ordre, source) VALUES (?, ?, ?, ?, 'airbnb')")
                             ->execute([$logement_id, $filename, $url, $i + 1]);
                        $imported++;
                    } catch (PDOException $e) { $errors++; }
                } else {
                    $errors++;
                }
            }

            $feedback = "<div class='alert alert-" . ($imported > 0 ? 'success' : 'warning') . "'>
                <i class='fas fa-" . ($imported > 0 ? 'check-circle' : 'exclamation-triangle') . "'></i>
                $imported photo(s) importee(s) depuis le scraping" . ($errors > 0 ? ", $errors erreur(s)" : '') . "
            </div>";
        }
    }

    // Import depuis URLs collees
    if (isset($_POST['import_urls'])) {
        $logement_id = (int)$_POST['logement_id'];
        $urls = array_filter(array_map('trim', explode("\n", $_POST['photo_urls'] ?? '')));

        if ($logement_id > 0 && !empty($urls)) {
            $imported = 0;
            $errors = 0;

            foreach ($urls as $i => $url) {
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    $errors++;
                    continue;
                }

                // Telecharger l'image
                $ext = 'jpg';
                if (preg_match('/\.(png|webp|jpeg|jpg)(\?|$)/i', $url, $m)) {
                    $ext = strtolower($m[1]);
                }
                $filename = 'logement_' . $logement_id . '_' . time() . '_' . $i . '.' . $ext;
                $filepath = $uploadDir . $filename;

                $ctx = stream_context_create([
                    'http' => [
                        'timeout' => 15,
                        'user_agent' => 'Mozilla/5.0 (compatible; FrenchyBot/1.0)',
                    ],
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                ]);

                $imageData = @file_get_contents($url, false, $ctx);
                if ($imageData && strlen($imageData) > 1000) {
                    file_put_contents($filepath, $imageData);

                    // Enregistrer en BDD
                    $ordre = $i + 1;
                    try {
                        $conn->prepare("
                            INSERT INTO logement_photos (logement_id, filename, url_source, ordre, source)
                            VALUES (?, ?, ?, ?, 'airbnb')
                        ")->execute([$logement_id, $filename, $url, $ordre]);
                        $imported++;
                    } catch (PDOException $e) {
                        $errors++;
                    }
                } else {
                    $errors++;
                }
            }

            $feedback = "<div class='alert alert-" . ($imported > 0 ? 'success' : 'warning') . "'>
                <i class='fas fa-" . ($imported > 0 ? 'check-circle' : 'exclamation-triangle') . "'></i>
                $imported photo(s) importee(s)" . ($errors > 0 ? ", $errors erreur(s)" : '') . "
            </div>";
        } else {
            $feedback = "<div class='alert alert-warning'>Selectionnez un logement et collez au moins une URL</div>";
        }
    }

    // Upload manuel
    if (isset($_POST['upload_manual'])) {
        $logement_id = (int)$_POST['logement_id'];

        if ($logement_id > 0 && !empty($_FILES['photos']['name'][0])) {
            $imported = 0;
            $errors = 0;

            foreach ($_FILES['photos']['tmp_name'] as $i => $tmpFile) {
                if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) {
                    $errors++;
                    continue;
                }

                $origName = $_FILES['photos']['name'][$i];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $errors++;
                    continue;
                }

                $filename = 'logement_' . $logement_id . '_' . time() . '_' . $i . '.' . $ext;
                $filepath = $uploadDir . $filename;

                if (move_uploaded_file($tmpFile, $filepath)) {
                    try {
                        $conn->prepare("
                            INSERT INTO logement_photos (logement_id, filename, caption, ordre, source)
                            VALUES (?, ?, ?, ?, 'manual')
                        ")->execute([$logement_id, $filename, pathinfo($origName, PATHINFO_FILENAME), $i + 1]);
                        $imported++;
                    } catch (PDOException $e) {
                        $errors++;
                    }
                } else {
                    $errors++;
                }
            }

            $feedback = "<div class='alert alert-success'>$imported photo(s) uploadee(s)" . ($errors > 0 ? ", $errors erreur(s)" : '') . "</div>";
        }
    }

    // Supprimer une photo
    if (isset($_POST['delete_photo'])) {
        $photo_id = (int)$_POST['photo_id'];
        try {
            $stmt = $conn->prepare("SELECT filename FROM logement_photos WHERE id = ?");
            $stmt->execute([$photo_id]);
            $photo = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($photo) {
                @unlink($uploadDir . $photo['filename']);
                $conn->prepare("DELETE FROM logement_photos WHERE id = ?")->execute([$photo_id]);
                $feedback = "<div class='alert alert-success'>Photo supprimee</div>";
            }
        } catch (PDOException $e) {
            $feedback = "<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    // Mettre a jour la legende
    if (isset($_POST['update_caption'])) {
        $photo_id = (int)$_POST['photo_id'];
        $caption = trim($_POST['caption'] ?? '');
        try {
            $conn->prepare("UPDATE logement_photos SET caption = ? WHERE id = ?")->execute([$caption, $photo_id]);
        } catch (PDOException $e) {}
    }
}

// Photos existantes par logement
$selected_logement = (int)($_GET['logement'] ?? 0);

// URL Airbnb du logement selectionne (pour pre-remplir le scraping)
$selected_airbnb_url = '';
if ($selected_logement > 0) {
    try {
        $stmt = $conn->prepare("SELECT airbnb_url FROM liste_logements WHERE id = ?");
        $stmt->execute([$selected_logement]);
        $selected_airbnb_url = $stmt->fetchColumn() ?: '';
    } catch (PDOException $e) {}
}
$photos = [];
if ($selected_logement > 0) {
    try {
        $stmt = $conn->prepare("SELECT * FROM logement_photos WHERE logement_id = ? ORDER BY ordre, id");
        $stmt->execute([$selected_logement]);
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

// Stats globales
$total_photos = 0;
$logements_avec_photos = 0;
try {
    $stats = $conn->query("SELECT COUNT(*) as total, COUNT(DISTINCT logement_id) as logements FROM logement_photos")->fetch(PDO::FETCH_ASSOC);
    $total_photos = $stats['total'];
    $logements_avec_photos = $stats['logements'];
} catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Photos — FrenchyConciergerie</title>
    <style>
        .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
        .photo-card { position: relative; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .photo-card img { width: 100%; height: 160px; object-fit: cover; }
        .photo-card .overlay {
            position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.6);
            color: #fff; padding: 6px 8px; font-size: 0.8em;
        }
        .photo-card .delete-btn {
            position: absolute; top: 6px; right: 6px; background: rgba(220,53,69,0.9);
            color: #fff; border: none; border-radius: 50%; width: 28px; height: 28px;
            cursor: pointer; font-size: 0.8em; display: flex; align-items: center; justify-content: center;
        }
    </style>
</head>
<body>
<div class="container-fluid mt-3">

    <?= $feedback ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2><i class="fas fa-images"></i> Import Photos</h2>
            <p class="text-muted mb-0">Importez les photos depuis Airbnb ou uploadez manuellement</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-2">
                    <div class="h4 mb-0 text-primary"><?= $total_photos ?></div>
                    <small class="text-muted">Photos totales</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-2">
                    <div class="h4 mb-0 text-success"><?= $logements_avec_photos ?></div>
                    <small class="text-muted">Logements avec photos</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-2">
                    <div class="h4 mb-0"><?= count($logements) ?></div>
                    <small class="text-muted">Logements total</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-2">
                    <div class="h4 mb-0 text-warning"><?= count($logements) - $logements_avec_photos ?></div>
                    <small class="text-muted">Sans photos</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Colonne import -->
        <div class="col-md-5">
            <!-- Selecteur logement -->
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-home"></i> Choisir un logement</h6></div>
                <div class="card-body">
                    <form method="GET">
                        <select name="logement" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Selectionner --</option>
                            <?php foreach ($logements as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= $selected_logement == $l['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($l['nom_du_logement']) ?> <?= empty($l['actif']) ? '(inactif)' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>

            <?php if ($selected_logement > 0): ?>
            <!-- Scraping automatique -->
            <div class="card mb-3">
                <div class="card-header bg-danger text-white"><h6 class="mb-0"><i class="fas fa-spider"></i> Scraping automatique</h6></div>
                <div class="card-body">
                    <form method="POST">
                        <?php echoCsrfField(); ?>
                        <input type="hidden" name="logement_id" value="<?= $selected_logement ?>">
                        <div class="mb-2">
                            <label class="form-label small">URL de l'annonce Airbnb</label>
                            <input type="url" name="scrape_url" class="form-control form-control-sm"
                                   placeholder="https://www.airbnb.fr/rooms/123456"
                                   value="<?= htmlspecialchars($_POST['scrape_url'] ?? $selected_airbnb_url) ?>">
                            <small class="text-muted">Collez le lien de l'annonce, le systeme extraira toutes les photos</small>
                        </div>
                        <button type="submit" name="scrape_url_btn" value="1" class="btn btn-danger btn-sm w-100">
                            <i class="fas fa-search"></i> Scanner les photos
                        </button>
                    </form>

                    <?php if (!empty($scraped_images)): ?>
                    <hr>
                    <p class="small text-success mb-2"><i class="fas fa-check-circle"></i> <strong><?= count($scraped_images) ?> photo(s)</strong> trouvee(s)</p>
                    <form method="POST">
                        <?php echoCsrfField(); ?>
                        <input type="hidden" name="logement_id" value="<?= $selected_logement ?>">
                        <div class="mb-2" style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($scraped_images as $idx => $img_url): ?>
                            <div class="form-check mb-1 d-flex align-items-center gap-2">
                                <input class="form-check-input scraped-check" type="checkbox" name="scraped_urls[]"
                                       value="<?= htmlspecialchars($img_url) ?>" id="scrape_<?= $idx ?>" checked>
                                <img src="<?= htmlspecialchars($img_url) ?>" style="width:60px;height:40px;object-fit:cover;border-radius:4px;"
                                     onerror="this.parentElement.style.display='none'">
                                <label class="form-check-label small text-truncate" for="scrape_<?= $idx ?>" style="max-width:200px;"
                                       title="<?= htmlspecialchars($img_url) ?>">
                                    Photo <?= $idx + 1 ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll('.scraped-check').forEach(c=>c.checked=!c.checked)">
                                <i class="fas fa-exchange-alt"></i> Inverser
                            </button>
                            <button type="submit" name="import_scraped" class="btn btn-success btn-sm flex-fill">
                                <i class="fas fa-download"></i> Importer la selection
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Import par URL -->
            <div class="card mb-3">
                <div class="card-header bg-primary text-white"><h6 class="mb-0"><i class="fas fa-link"></i> Import par URLs</h6></div>
                <div class="card-body">
                    <form method="POST">
                        <?php echoCsrfField(); ?>
                        <input type="hidden" name="logement_id" value="<?= $selected_logement ?>">
                        <div class="mb-2">
                            <label class="form-label small">Collez les URLs des photos (une par ligne)</label>
                            <textarea name="photo_urls" class="form-control form-control-sm" rows="6"
                                      placeholder="https://a0.muscache.com/im/pictures/xxx.jpg&#10;https://a0.muscache.com/im/pictures/yyy.jpg"></textarea>
                            <small class="text-muted">Astuce : clic droit sur chaque photo Airbnb > "Copier l'adresse de l'image"</small>
                        </div>
                        <button type="submit" name="import_urls" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-download"></i> Importer les photos
                        </button>
                    </form>
                </div>
            </div>

            <!-- Upload manuel -->
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-upload"></i> Upload manuel</h6></div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?php echoCsrfField(); ?>
                        <input type="hidden" name="logement_id" value="<?= $selected_logement ?>">
                        <div class="mb-2">
                            <input type="file" name="photos[]" class="form-control form-control-sm" multiple accept="image/jpeg,image/png,image/webp">
                            <small class="text-muted">JPG, PNG ou WebP, plusieurs fichiers possibles</small>
                        </div>
                        <button type="submit" name="upload_manual" class="btn btn-outline-primary btn-sm w-100">
                            <i class="fas fa-upload"></i> Uploader
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Colonne photos -->
        <div class="col-md-7">
            <?php if ($selected_logement > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-images"></i> Photos (<?= count($photos) ?>)</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($photos)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-image fa-3x mb-2"></i>
                            <p>Aucune photo pour ce logement</p>
                        </div>
                    <?php else: ?>
                        <div class="photo-grid">
                            <?php foreach ($photos as $photo): ?>
                            <div class="photo-card">
                                <img src="../uploads/photos/<?= htmlspecialchars($photo['filename']) ?>" alt="<?= htmlspecialchars($photo['caption'] ?? '') ?>" loading="lazy">
                                <form method="POST" class="d-inline">
                                    <?php echoCsrfField(); ?>
                                    <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                    <button type="submit" name="delete_photo" class="delete-btn" onclick="return confirm('Supprimer ?')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                                <div class="overlay">
                                    <?= htmlspecialchars($photo['caption'] ?? 'Photo ' . $photo['ordre']) ?>
                                    <span class="badge bg-<?= $photo['source'] === 'airbnb' ? 'danger' : 'secondary' ?> ms-1" style="font-size:0.7em"><?= $photo['source'] ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="fas fa-hand-pointer fa-3x mb-3"></i>
                    <h5>Selectionnez un logement</h5>
                    <p>Choisissez un logement pour voir et importer ses photos</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
