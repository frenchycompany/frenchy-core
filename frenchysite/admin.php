<?php
/**
 * Page d'administration
 * Configuration des couleurs, textes, photos et paramètres du site.
 * Guides dynamiques gérés depuis la BDD (vf_guides + vf_guide_blocks).
 */

require_once __DIR__ . '/db/connection.php';
require_once __DIR__ . '/db/helpers.php';
require_once __DIR__ . '/admin/auth.php';

$property = vf_load_property();

// ── Auth ──
$admin_user = getenv('ADMIN_USER') ?: 'admin';
$admin_pass = getenv('ADMIN_PASS') ?: 'admin2025';

// Login
if (isset($_POST['login'])) {
    $rate_error = vf_rate_limit_check();
    if ($rate_error) {
        $login_error = $rate_error;
    } elseif ($_POST['username'] === $admin_user && vf_check_password($_POST['password'], $admin_pass)) {
        vf_rate_limit_success();
        session_regenerate_id(true);
        $_SESSION['vf_admin'] = true;
        header('Location: admin.php');
        exit;
    } else {
        vf_rate_limit_fail();
        $login_error = 'Identifiants incorrects.';
    }
}

// Logout (POST only with CSRF token)
if (isset($_POST['logout']) && !empty($_SESSION['vf_admin'])) {
    if (vf_csrf_verify()) {
        session_destroy();
        header('Location: admin.php');
        exit;
    }
}

// Check auth
if (empty($_SESSION['vf_admin'])) {
    show_login($login_error ?? null, $property);
    exit;
}

// ── Install schema if tables don't exist ──
try {
    $tables = $conn->query("SHOW TABLES LIKE '" . vf_table('settings') . "'")->fetchAll();
    if (empty($tables)) {
        $sql = file_get_contents(__DIR__ . '/db/schema.sql');
        // Remplacer le préfixe par défaut vf_ par le préfixe configuré
        $sql = str_replace('vf_guide_blocks', vf_table('guide_blocks'), $sql);
        $sql = str_replace('vf_guides', vf_table('guides'), $sql);
        $sql = str_replace('vf_settings', vf_table('settings'), $sql);
        $sql = str_replace('vf_texts', vf_table('texts'), $sql);
        $sql = str_replace('vf_photos', vf_table('photos'), $sql);
        $conn->exec($sql);
        // Seed with property config values
        vf_seed_from_config($conn);
        header('Location: admin.php?installed=1');
        exit;
    }

    // Migration: add srcset_json column if missing
    try {
        $cols = $conn->query("SHOW COLUMNS FROM " . vf_table('photos') . " LIKE 'srcset_json'")->fetchAll();
        if (empty($cols)) {
            $conn->exec("ALTER TABLE " . vf_table('photos') . " ADD COLUMN srcset_json TEXT DEFAULT NULL AFTER file_path");
        }
    } catch (PDOException $e) { }

    // Migration: create guides tables if missing
    try {
        $gt = $conn->query("SHOW TABLES LIKE '" . vf_table('guides') . "'")->fetchAll();
        if (empty($gt)) {
            $conn->exec("CREATE TABLE IF NOT EXISTS " . vf_table('guides') . " (
                id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(50) NOT NULL UNIQUE,
                title VARCHAR(200) NOT NULL, subtitle VARCHAR(300) DEFAULT '',
                icon_svg TEXT DEFAULT NULL, is_active TINYINT(1) DEFAULT 1,
                sort_order INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $conn->exec("CREATE TABLE IF NOT EXISTS " . vf_table('guide_blocks') . " (
                id INT AUTO_INCREMENT PRIMARY KEY, guide_slug VARCHAR(50) NOT NULL,
                block_type VARCHAR(20) NOT NULL DEFAULT 'text', block_title VARCHAR(200) DEFAULT '',
                block_content TEXT, sort_order INT DEFAULT 0,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_guide (guide_slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    } catch (PDOException $e) { }

    // Migration: seed guides from config if guides table is empty
    try {
        $guide_count = $conn->query("SELECT COUNT(*) FROM " . vf_table('guides'))->fetchColumn();
        if ($guide_count == 0 && !empty($property['guides'])) {
            $all_settings = vf_load_settings($conn);
            $active_json = $all_settings['active_guides'] ?? null;
            $active_keys = ($active_json) ? (json_decode($active_json, true) ?: []) : array_keys($property['guides']);

            $sort = 0;
            $seed = $conn->prepare("INSERT INTO " . vf_table('guides') . " (slug, title, subtitle, icon_svg, is_active, sort_order) VALUES (?,?,?,?,?,?)");
            foreach ($property['guides'] as $slug => $cfg) {
                $seed->execute([
                    $slug,
                    $cfg['admin_label'] ?? $cfg['label'],
                    $cfg['admin_label'] ?? $cfg['label'],
                    $cfg['icon'] ?? '',
                    in_array($slug, $active_keys) ? 1 : 0,
                    $sort++
                ]);
            }
        }
    } catch (PDOException $e) { }

} catch (PDOException $e) { }

// ── AJAX handlers (in separate file) ──
require_once __DIR__ . '/admin/handlers.php';

// ── Load data for the page ──
$settings_raw = $conn->query("SELECT setting_key, setting_value, setting_group, label, field_type, sort_order FROM " . vf_table('settings') . " ORDER BY setting_group, sort_order")->fetchAll();
$texts_raw    = $conn->query("SELECT id, section_key, field_key, field_value, label, field_type, sort_order FROM " . vf_table('texts') . " ORDER BY section_key, sort_order")->fetchAll();
$photos_raw   = $conn->query("SELECT id, photo_group, photo_key, file_path, srcset_json, alt_text, is_wide, sort_order FROM " . vf_table('photos') . " ORDER BY photo_group, sort_order")->fetchAll();
$site         = vf_build_site_config(vf_load_settings($conn));

// Group settings
$settings_grouped = [];
foreach ($settings_raw as $s) {
    $settings_grouped[$s['setting_group']][] = $s;
}

// Group texts by section
$texts_grouped = [];
foreach ($texts_raw as $t) {
    $texts_grouped[$t['section_key']][] = $t;
}

// Group photos
$photos_grouped = [];
foreach ($photos_raw as $p) {
    $photos_grouped[$p['photo_group']][] = $p;
}

$group_labels = [
    'identity'     => 'Identité',
    'integrations' => 'Intégrations',
    'colors'       => 'Couleurs',
    'typography'   => 'Typographie',
];

// Section labels from config
$section_labels = [];
foreach ($property['sections'] ?? [] as $key => $cfg) {
    $section_labels[$key] = $cfg['label'];
}

// Active sections (from DB)
$all_settings = vf_load_settings($conn);
$active_section_keys = array_keys(vf_get_active_sections($all_settings));

// Dynamic guides from DB
$db_guides = vf_load_guides($conn);
$guide_blocks = [];
foreach ($db_guides as $slug => $g) {
    $guide_blocks[$slug] = vf_load_guide_blocks($conn, $slug);
}

// Guide text labels (for old texts tab — only show active guides that still have vf_texts entries)
$guide_labels = [];
foreach ($db_guides as $slug => $g) {
    $gk = 'guide_' . $slug;
    if (!empty($texts_grouped[$gk])) {
        $guide_labels[$gk] = $g['title'];
    }
}

$csrf_token = vf_csrf_token();
$site_name = htmlspecialchars($site['name']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Administration — <?= $site_name ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?= time() ?>">
</head>
<body>

    <header class="adm-header">
        <div class="adm-container adm-header-inner">
            <h1 class="adm-title">Configuration du site</h1>
            <div class="adm-header-actions">
                <a href="index.php" class="adm-btn adm-btn-ghost" target="_blank">Voir le site</a>
                <form method="post" style="display:inline">
                    <input type="hidden" name="_csrf" value="<?= $csrf_token ?>">
                    <button type="submit" name="logout" value="1" class="adm-btn adm-btn-ghost">Déconnexion</button>
                </form>
            </div>
        </div>
    </header>

    <!-- Tab navigation -->
    <nav class="adm-tabs" id="adm-tabs">
        <div class="adm-container">
            <button class="adm-tab is-active" data-tab="modules">Modules</button>
            <button class="adm-tab" data-tab="settings">Paramètres</button>
            <button class="adm-tab" data-tab="colors">Couleurs & Typo</button>
            <button class="adm-tab" data-tab="texts">Textes</button>
            <button class="adm-tab" data-tab="guides">Guides</button>
            <button class="adm-tab" data-tab="photos">Photos</button>
        </div>
    </nav>

    <div class="adm-container adm-main">

        <!-- Toast notification -->
        <div class="adm-toast" id="adm-toast"></div>

        <!-- ═══════════════ TAB: MODULES ═══════════════ -->
        <section class="adm-panel is-active" id="panel-modules">
            <form id="form-modules" class="adm-form">

                <fieldset class="adm-fieldset">
                    <legend>Sections du site</legend>
                    <p class="adm-module-intro">Activez ou désactivez les sections affichées sur la page d'accueil.</p>
                    <div class="adm-module-grid">
                        <?php foreach ($property['sections'] ?? [] as $key => $cfg): ?>
                        <label class="adm-module-toggle">
                            <input type="checkbox" name="sections[]" value="<?= htmlspecialchars($key) ?>"<?= in_array($key, $active_section_keys) ? ' checked' : '' ?>>
                            <span class="adm-module-switch"></span>
                            <span class="adm-module-label"><?= htmlspecialchars($cfg['label']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <fieldset class="adm-fieldset">
                    <legend>Guides (modes d'emploi QR codes)</legend>
                    <p class="adm-module-intro">Activez ou désactivez les pages de guides accessibles via QR codes.</p>
                    <div class="adm-module-grid">
                        <?php foreach ($db_guides as $slug => $g): ?>
                        <label class="adm-module-toggle">
                            <input type="checkbox" name="guides[]" value="<?= htmlspecialchars($slug) ?>"<?= $g['is_active'] ? ' checked' : '' ?>>
                            <span class="adm-module-switch"></span>
                            <span class="adm-module-label"><?= htmlspecialchars($g['title']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (empty($db_guides)): ?>
                    <p class="adm-module-intro" style="margin-top:.5rem;font-style:italic">Aucun guide créé. Allez dans l'onglet « Guides » pour en créer.</p>
                    <?php endif; ?>
                </fieldset>

                <div class="adm-form-actions">
                    <button type="submit" class="adm-btn adm-btn-primary">Enregistrer les modules</button>
                </div>
            </form>

            <?php
            $active_guide_slugs = [];
            foreach ($db_guides as $slug => $g) {
                if ($g['is_active']) $active_guide_slugs[] = $slug;
            }
            ?>
            <?php if (!empty($active_guide_slugs)): ?>
            <fieldset class="adm-fieldset" style="margin-top:2rem">
                <legend>QR Codes des guides</legend>
                <p class="adm-module-intro">Cliquez sur un QR code pour le télécharger. Imprimez-le et placez-le dans la pièce correspondante.</p>
                <div class="adm-qr-grid">
                    <?php
                    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                        . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
                    foreach ($db_guides as $slug => $g):
                        if (!$g['is_active']) continue;
                        $guide_url = $base_url . 'guide.php?slug=' . $slug;
                        $qr_api = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&format=png&data=' . urlencode($guide_url);
                    ?>
                    <div class="adm-qr-item">
                        <a href="<?= htmlspecialchars($qr_api) ?>" download="qr-<?= htmlspecialchars($slug) ?>.png" title="Télécharger">
                            <img src="<?= htmlspecialchars($qr_api) ?>" alt="QR <?= htmlspecialchars($g['title']) ?>" width="150" height="150" loading="lazy">
                        </a>
                        <span class="adm-qr-label"><?= htmlspecialchars($g['title']) ?></span>
                        <code class="adm-qr-url"><?= htmlspecialchars($guide_url) ?></code>
                    </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <?php endif; ?>

        </section>

        <!-- ═══════════════ TAB: PARAMÈTRES ═══════════════ -->
        <section class="adm-panel" id="panel-settings">
            <form id="form-settings" class="adm-form">

                <?php foreach (['identity', 'integrations'] as $group): ?>
                <?php if (!empty($settings_grouped[$group])): ?>
                <fieldset class="adm-fieldset">
                    <legend><?= htmlspecialchars($group_labels[$group] ?? $group) ?></legend>

                    <?php foreach ($settings_grouped[$group] as $s): ?>
                    <div class="adm-field">
                        <label for="s-<?= htmlspecialchars($s['setting_key']) ?>"><?= htmlspecialchars($s['label'] ?? $s['setting_key']) ?></label>
                        <input
                            type="text"
                            id="s-<?= htmlspecialchars($s['setting_key']) ?>"
                            name="<?= htmlspecialchars($s['setting_key']) ?>"
                            value="<?= htmlspecialchars($s['setting_value']) ?>"
                            class="adm-input"
                        >
                    </div>
                    <?php endforeach; ?>

                </fieldset>
                <?php endif; ?>
                <?php endforeach; ?>

                <div class="adm-form-actions">
                    <button type="submit" class="adm-btn adm-btn-primary">Enregistrer les paramètres</button>
                </div>
            </form>
        </section>

        <!-- ═══════════════ TAB: COULEURS & TYPO ═══════════════ -->
        <section class="adm-panel" id="panel-colors">
            <form id="form-colors" class="adm-form">

                <?php if (!empty($settings_grouped['colors'])): ?>
                <fieldset class="adm-fieldset">
                    <legend>Palette de couleurs</legend>
                    <div class="adm-color-grid">
                        <?php foreach ($settings_grouped['colors'] as $s): ?>
                        <div class="adm-color-item">
                            <input
                                type="color"
                                id="s-<?= htmlspecialchars($s['setting_key']) ?>"
                                name="<?= htmlspecialchars($s['setting_key']) ?>"
                                value="<?= htmlspecialchars($s['setting_value']) ?>"
                                class="adm-color-picker"
                            >
                            <div class="adm-color-info">
                                <label for="s-<?= htmlspecialchars($s['setting_key']) ?>"><?= htmlspecialchars($s['label']) ?></label>
                                <input
                                    type="text"
                                    class="adm-color-hex"
                                    value="<?= htmlspecialchars($s['setting_value']) ?>"
                                    data-for="s-<?= htmlspecialchars($s['setting_key']) ?>"
                                    maxlength="7"
                                    pattern="#[0-9a-fA-F]{6}"
                                >
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <?php endif; ?>

                <!-- Preview -->
                <fieldset class="adm-fieldset">
                    <legend>Aperçu</legend>
                    <div class="adm-preview" id="color-preview">
                        <div class="adm-preview-bar" id="prev-bar">
                            <span id="prev-bar-text">Bandeau vert forêt avec texte blanc cassé</span>
                        </div>
                        <div class="adm-preview-card" id="prev-card">
                            <h3 id="prev-heading">Titre en Playfair Display</h3>
                            <p id="prev-body">Corps de texte en Inter, lisible et élégant.</p>
                            <button id="prev-btn">Bouton principal</button>
                            <button id="prev-btn-outline">Bouton outline</button>
                        </div>
                    </div>
                </fieldset>

                <?php if (!empty($settings_grouped['typography'])): ?>
                <fieldset class="adm-fieldset">
                    <legend>Typographie</legend>
                    <?php foreach ($settings_grouped['typography'] as $s): ?>
                    <div class="adm-field">
                        <label for="s-<?= htmlspecialchars($s['setting_key']) ?>"><?= htmlspecialchars($s['label']) ?></label>
                        <input
                            type="text"
                            id="s-<?= htmlspecialchars($s['setting_key']) ?>"
                            name="<?= htmlspecialchars($s['setting_key']) ?>"
                            value="<?= htmlspecialchars($s['setting_value']) ?>"
                            class="adm-input"
                        >
                    </div>
                    <?php endforeach; ?>
                </fieldset>
                <?php endif; ?>

                <div class="adm-form-actions">
                    <button type="submit" class="adm-btn adm-btn-primary">Enregistrer couleurs & typo</button>
                </div>
            </form>
        </section>

        <!-- ═══════════════ TAB: TEXTES ═══════════════ -->
        <section class="adm-panel" id="panel-texts">
            <form id="form-texts" class="adm-form">

                <?php foreach ($section_labels as $section_key => $section_label): ?>
                <?php if (!empty($texts_grouped[$section_key])): ?>
                <fieldset class="adm-fieldset">
                    <legend><?= htmlspecialchars($section_label) ?></legend>

                    <?php foreach ($texts_grouped[$section_key] as $t): ?>
                    <div class="adm-field">
                        <label for="t-<?= htmlspecialchars($t['section_key'] . '-' . $t['field_key']) ?>">
                            <?= htmlspecialchars($t['label'] ?? $t['field_key']) ?>
                        </label>

                        <?php if ($t['field_type'] === 'textarea'): ?>
                        <textarea
                            id="t-<?= htmlspecialchars($t['section_key'] . '-' . $t['field_key']) ?>"
                            data-section="<?= htmlspecialchars($t['section_key']) ?>"
                            data-field="<?= htmlspecialchars($t['field_key']) ?>"
                            class="adm-textarea"
                            rows="3"
                        ><?= htmlspecialchars($t['field_value']) ?></textarea>
                        <?php else: ?>
                        <input
                            type="text"
                            id="t-<?= htmlspecialchars($t['section_key'] . '-' . $t['field_key']) ?>"
                            data-section="<?= htmlspecialchars($t['section_key']) ?>"
                            data-field="<?= htmlspecialchars($t['field_key']) ?>"
                            value="<?= htmlspecialchars($t['field_value']) ?>"
                            class="adm-input"
                        >
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                </fieldset>
                <?php endif; ?>
                <?php endforeach; ?>

                <div class="adm-form-actions">
                    <button type="submit" class="adm-btn adm-btn-primary">Enregistrer les textes</button>
                </div>
            </form>
        </section>

        <!-- ═══════════════ TAB: GUIDES (dynamique) ═══════════════ -->
        <section class="adm-panel" id="panel-guides">

            <p class="adm-guide-intro">Créez et gérez les guides / modes d'emploi de vos équipements. Chaque guide est accessible via un QR code unique.</p>

            <!-- Create new guide -->
            <div class="adm-create-guide-form" id="create-guide-form" hidden>
                <h3 style="font-size:.95rem;font-weight:600;color:var(--adm-green);margin-bottom:.5rem">Nouveau guide</h3>
                <div class="adm-guide-meta-row">
                    <div class="adm-field">
                        <label>Slug (URL) <small style="color:var(--adm-grey)">— lettres, chiffres, tirets</small></label>
                        <input type="text" class="adm-input" id="new-guide-slug" placeholder="ex: piscine, sauna, barbecue">
                    </div>
                    <div class="adm-field">
                        <label>Titre</label>
                        <input type="text" class="adm-input" id="new-guide-title" placeholder="ex: La Piscine">
                    </div>
                </div>
                <div class="adm-field">
                    <label>Sous-titre</label>
                    <input type="text" class="adm-input" id="new-guide-subtitle" placeholder="ex: Profitez de notre piscine privée">
                </div>
                <div class="adm-field">
                    <label>Icône SVG <small style="color:var(--adm-grey)">— contenu &lt;path&gt; pour un viewBox 24x24</small></label>
                    <textarea class="adm-textarea" id="new-guide-icon" rows="2" placeholder='ex: <path d="M2 12h20"/>'></textarea>
                </div>
                <div class="adm-form-actions" style="justify-content:flex-start;gap:.5rem">
                    <button type="button" class="adm-btn adm-btn-primary" id="btn-save-new-guide">Créer le guide</button>
                    <button type="button" class="adm-btn adm-btn-outline" id="btn-cancel-new-guide">Annuler</button>
                </div>
            </div>

            <div style="margin-bottom:1rem">
                <button type="button" class="adm-btn adm-btn-primary" id="btn-create-guide">+ Nouveau guide</button>
            </div>

            <!-- Existing guides list -->
            <div id="guides-list">
                <?php foreach ($db_guides as $slug => $g): ?>
                <fieldset class="adm-fieldset adm-guide-item" data-id="<?= $g['id'] ?>" data-slug="<?= htmlspecialchars($slug) ?>" style="margin-bottom:1.5rem">
                    <legend>
                        <span class="adm-guide-legend-inner">
                            <?php if (!empty($g['icon_svg'])): ?>
                            <svg viewBox="0 0 24 24" width="18" height="18"><?= $g['icon_svg'] ?></svg>
                            <?php endif; ?>
                            <?= htmlspecialchars($g['title']) ?>
                            <?php if (!$g['is_active']): ?>
                                <span class="adm-badge" style="background:var(--adm-grey)">Inactif</span>
                            <?php endif; ?>
                        </span>
                    </legend>

                    <!-- Guide metadata -->
                    <div class="adm-guide-meta">
                        <div class="adm-guide-meta-row">
                            <div class="adm-field">
                                <label>Slug (URL)</label>
                                <input type="text" class="adm-input" value="<?= htmlspecialchars($slug) ?>" readonly style="background:#eee;cursor:not-allowed">
                            </div>
                            <div class="adm-field">
                                <label>Titre</label>
                                <input type="text" class="adm-input guide-title" value="<?= htmlspecialchars($g['title']) ?>">
                            </div>
                        </div>
                        <div class="adm-field">
                            <label>Sous-titre</label>
                            <input type="text" class="adm-input guide-subtitle" value="<?= htmlspecialchars($g['subtitle']) ?>">
                        </div>
                        <div class="adm-field">
                            <label>Icône SVG <small style="color:var(--adm-grey)">— contenu &lt;path&gt; pour viewBox 24x24</small></label>
                            <textarea class="adm-textarea guide-icon" rows="2"><?= htmlspecialchars($g['icon_svg']) ?></textarea>
                        </div>
                        <div style="display:flex;gap:.75rem;align-items:center;margin-top:.5rem">
                            <label class="adm-module-toggle" style="margin:0">
                                <input type="checkbox" class="guide-active"<?= $g['is_active'] ? ' checked' : '' ?>>
                                <span class="adm-module-switch"></span>
                                <span class="adm-module-label">Actif</span>
                            </label>
                            <div style="flex:1"></div>
                            <button type="button" class="adm-btn adm-btn-primary adm-btn-sm btn-save-guide">Enregistrer</button>
                            <button type="button" class="adm-btn adm-btn-danger adm-btn-sm btn-delete-guide">Supprimer</button>
                        </div>
                    </div>

                    <!-- Blocks section -->
                    <div class="adm-guide-blocks">
                        <h4>Blocs de contenu</h4>

                        <div class="adm-blocks-list" data-slug="<?= htmlspecialchars($slug) ?>">
                            <?php foreach ($guide_blocks[$slug] ?? [] as $block): ?>
                            <div class="adm-block-item" data-block-id="<?= $block['id'] ?>" draggable="true">
                                <div class="adm-block-header">
                                    <span class="adm-block-drag" title="Glisser pour réordonner">&#x2630;</span>
                                    <span class="adm-block-type-badge"><?= htmlspecialchars($block['block_type']) ?></span>
                                    <span class="adm-block-title-preview"><?= htmlspecialchars(mb_substr($block['block_title'], 0, 60)) ?></span>
                                    <button type="button" class="adm-btn adm-btn-sm adm-btn-outline btn-edit-block">Modifier</button>
                                    <button type="button" class="adm-btn adm-btn-sm adm-btn-danger btn-delete-block">&#x2715;</button>
                                </div>
                                <div class="adm-block-edit" hidden>
                                    <div class="adm-field">
                                        <label>Type</label>
                                        <select class="adm-input block-type">
                                            <option value="text"<?= $block['block_type'] === 'text' ? ' selected' : '' ?>>Texte</option>
                                            <option value="highlight"<?= $block['block_type'] === 'highlight' ? ' selected' : '' ?>>Highlight (mise en avant)</option>
                                            <option value="steps"<?= $block['block_type'] === 'steps' ? ' selected' : '' ?>>Étapes (numérotées)</option>
                                            <option value="list"<?= $block['block_type'] === 'list' ? ' selected' : '' ?>>Liste à puces</option>
                                            <option value="alert"<?= $block['block_type'] === 'alert' ? ' selected' : '' ?>>Alerte (warning/info/danger)</option>
                                        </select>
                                    </div>
                                    <div class="adm-field">
                                        <label>Titre du bloc <small class="block-title-hint" style="color:var(--adm-grey)"></small></label>
                                        <input type="text" class="adm-input block-title" value="<?= htmlspecialchars($block['block_title']) ?>">
                                    </div>
                                    <div class="adm-field">
                                        <label>Contenu <small style="color:var(--adm-grey)">— pour listes/étapes : 1 élément par ligne</small></label>
                                        <textarea class="adm-textarea block-content" rows="4"><?= htmlspecialchars($block['block_content']) ?></textarea>
                                    </div>
                                    <div class="adm-form-actions" style="justify-content:flex-start">
                                        <button type="button" class="adm-btn adm-btn-primary adm-btn-sm btn-save-block">Enregistrer le bloc</button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="adm-btn adm-btn-outline adm-btn-sm btn-add-block" style="margin-top:.75rem">+ Ajouter un bloc</button>
                    </div>
                </fieldset>
                <?php endforeach; ?>
            </div>

            <?php if (empty($db_guides)): ?>
            <p class="adm-guide-intro" style="text-align:center;margin-top:2rem;font-style:italic">Aucun guide pour le moment. Cliquez sur « + Nouveau guide » pour commencer.</p>
            <?php endif; ?>

        </section>

        <!-- ═══════════════ TAB: PHOTOS ═══════════════ -->
        <section class="adm-panel" id="panel-photos">

            <!-- ── IMAGE PRINCIPALE (Hero) ── -->
            <fieldset class="adm-fieldset">
                <legend>Image principale</legend>
                <p class="adm-photo-help">La grande photo affichée en haut de la page d'accueil. Cliquez pour la remplacer.</p>
                <?php $hero_photo = $photos_grouped['hero'][0] ?? null; ?>
                <div class="adm-photo-single" id="photos-hero">
                    <?php if ($hero_photo): ?>
                    <div class="adm-photo-card adm-photo-card--large" data-id="<?= $hero_photo['id'] ?>">
                        <img src="<?= htmlspecialchars($hero_photo['file_path']) ?>" alt="Image principale">
                        <button type="button" class="adm-photo-delete" data-id="<?= $hero_photo['id'] ?>" title="Supprimer">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <?php else: ?>
                    <p class="adm-photo-empty">Aucune image. Ajoutez-en une ci-dessous.</p>
                    <?php endif; ?>
                </div>
                <form class="adm-upload-form" data-group="hero" data-mode="single">
                    <input type="hidden" name="photo_key" value="hero">
                    <input type="hidden" name="alt_text" value="Image principale">
                    <div class="adm-upload-action">
                        <label class="adm-btn adm-btn-outline adm-upload-btn">
                            <?= $hero_photo ? 'Remplacer l\'image' : 'Choisir une image' ?>
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" hidden>
                        </label>
                        <button type="submit" class="adm-btn adm-btn-primary" disabled>Envoyer</button>
                    </div>
                    <div class="adm-upload-preview" hidden>
                        <img src="" alt="Aperçu">
                        <span class="adm-upload-filename"></span>
                    </div>
                </form>
            </fieldset>

            <!-- ── LOGO ── -->
            <fieldset class="adm-fieldset">
                <legend>Logo</legend>
                <p class="adm-photo-help">Le logo affiché dans le bandeau en haut du site.</p>
                <?php $logo_photo = $photos_grouped['logo'][0] ?? null; ?>
                <div class="adm-photo-single" id="photos-logo">
                    <?php if ($logo_photo): ?>
                    <div class="adm-photo-card" data-id="<?= $logo_photo['id'] ?>">
                        <img src="<?= htmlspecialchars($logo_photo['file_path']) ?>" alt="Logo" style="max-height:80px;width:auto">
                        <button type="button" class="adm-photo-delete" data-id="<?= $logo_photo['id'] ?>" title="Supprimer">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <?php else: ?>
                    <p class="adm-photo-empty">Aucun logo.</p>
                    <?php endif; ?>
                </div>
                <form class="adm-upload-form" data-group="logo" data-mode="single">
                    <input type="hidden" name="photo_key" value="logo">
                    <input type="hidden" name="alt_text" value="Logo">
                    <div class="adm-upload-action">
                        <label class="adm-btn adm-btn-outline adm-upload-btn">
                            <?= $logo_photo ? 'Remplacer le logo' : 'Choisir un logo' ?>
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/svg+xml" hidden>
                        </label>
                        <button type="submit" class="adm-btn adm-btn-primary" disabled>Envoyer</button>
                    </div>
                    <div class="adm-upload-preview" hidden>
                        <img src="" alt="Aperçu">
                        <span class="adm-upload-filename"></span>
                    </div>
                </form>
            </fieldset>

            <!-- ── EXPÉRIENCE (3 cartes) ── -->
            <fieldset class="adm-fieldset">
                <legend>Photos Expérience</legend>
                <p class="adm-photo-help">Les 3 images affichées dans la section « L'expérience » de la page d'accueil.</p>
                <?php
                $exp_slots = [
                    'confort' => 'Confort & volumes',
                    'charme'  => 'Charme historique',
                    'accueil' => 'Accueil & service',
                ];
                ?>
                <div class="adm-exp-grid">
                    <?php foreach ($exp_slots as $exp_key => $exp_label): ?>
                    <?php
                        $exp_photo = null;
                        if (!empty($photos_grouped['experience'])) {
                            foreach ($photos_grouped['experience'] as $ep) {
                                if ($ep['photo_key'] === $exp_key) { $exp_photo = $ep; break; }
                            }
                        }
                    ?>
                    <div class="adm-exp-slot">
                        <h4 class="adm-exp-slot-title"><?= htmlspecialchars($exp_label) ?></h4>
                        <div class="adm-photo-single" id="photos-experience-<?= $exp_key ?>">
                            <?php if ($exp_photo): ?>
                            <div class="adm-photo-card" data-id="<?= $exp_photo['id'] ?>">
                                <img src="<?= htmlspecialchars($exp_photo['file_path']) ?>" alt="<?= htmlspecialchars($exp_label) ?>">
                                <button type="button" class="adm-photo-delete" data-id="<?= $exp_photo['id'] ?>" title="Supprimer">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                            <?php else: ?>
                            <p class="adm-photo-empty">Pas d'image</p>
                            <?php endif; ?>
                        </div>
                        <form class="adm-upload-form" data-group="experience" data-mode="single" data-target="photos-experience-<?= $exp_key ?>">
                            <input type="hidden" name="photo_key" value="<?= $exp_key ?>">
                            <input type="hidden" name="alt_text" value="<?= htmlspecialchars($exp_label) ?>">
                            <div class="adm-upload-action">
                                <label class="adm-btn adm-btn-outline adm-btn-sm adm-upload-btn">
                                    <?= $exp_photo ? 'Remplacer' : 'Ajouter' ?>
                                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" hidden>
                                </label>
                                <button type="submit" class="adm-btn adm-btn-primary adm-btn-sm" disabled>Envoyer</button>
                            </div>
                            <div class="adm-upload-preview" hidden>
                                <img src="" alt="Aperçu">
                                <span class="adm-upload-filename"></span>
                            </div>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <!-- ── GALERIE ── -->
            <fieldset class="adm-fieldset">
                <legend>Galerie photos</legend>
                <p class="adm-photo-help">Les photos de la galerie sur la page d'accueil. Ajoutez autant de photos que vous voulez.</p>

                <div class="adm-photo-grid" id="photos-galerie">
                    <?php if (!empty($photos_grouped['galerie'])): ?>
                    <?php foreach ($photos_grouped['galerie'] as $p): ?>
                    <div class="adm-photo-card" data-id="<?= $p['id'] ?>">
                        <img src="<?= htmlspecialchars($p['file_path']) ?>" alt="<?= htmlspecialchars($p['alt_text']) ?>">
                        <div class="adm-photo-info">
                            <span class="adm-photo-name"><?= htmlspecialchars($p['alt_text'] ?: basename($p['file_path'])) ?></span>
                            <?php if ($p['is_wide']): ?><span class="adm-badge">Grande</span><?php endif; ?>
                        </div>
                        <button type="button" class="adm-photo-delete" data-id="<?= $p['id'] ?>" title="Supprimer">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <p class="adm-photo-empty">Aucune photo dans la galerie.</p>
                    <?php endif; ?>
                </div>

                <form class="adm-upload-form" data-group="galerie" data-mode="multi">
                    <input type="hidden" name="photo_key" value="">
                    <div class="adm-upload-simple">
                        <div class="adm-field" style="flex:1">
                            <label>Description (optionnel)</label>
                            <input type="text" name="alt_text" class="adm-input adm-input-sm" placeholder="Ex : Vue du jardin, Chambre bleue...">
                        </div>
                        <div class="adm-field">
                            <label class="adm-checkbox">
                                <input type="checkbox" name="is_wide" value="1">
                                Grande image
                            </label>
                        </div>
                    </div>
                    <div class="adm-upload-action">
                        <label class="adm-btn adm-btn-outline adm-upload-btn">
                            Choisir une image
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" hidden>
                        </label>
                        <button type="submit" class="adm-btn adm-btn-primary" disabled>Ajouter</button>
                    </div>
                    <div class="adm-upload-preview" hidden>
                        <img src="" alt="Aperçu">
                        <span class="adm-upload-filename"></span>
                    </div>
                </form>
            </fieldset>

            <!-- ── GUIDE PHOTOS (from DB guides) ── -->
            <?php foreach ($db_guides as $slug => $g):
                if (!$g['is_active']) continue;
                $gk = 'guide_' . $slug;
            ?>
            <fieldset class="adm-fieldset">
                <legend>Photos — <?= htmlspecialchars($g['title']) ?></legend>

                <div class="adm-photo-grid" id="photos-<?= htmlspecialchars($gk) ?>">
                    <?php if (!empty($photos_grouped[$gk])): ?>
                    <?php foreach ($photos_grouped[$gk] as $p): ?>
                    <div class="adm-photo-card" data-id="<?= $p['id'] ?>">
                        <img src="<?= htmlspecialchars($p['file_path']) ?>" alt="<?= htmlspecialchars($p['alt_text']) ?>">
                        <div class="adm-photo-info">
                            <span class="adm-photo-name"><?= htmlspecialchars($p['alt_text'] ?: basename($p['file_path'])) ?></span>
                        </div>
                        <button type="button" class="adm-photo-delete" data-id="<?= $p['id'] ?>" title="Supprimer">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <p class="adm-photo-empty">Aucune photo.</p>
                    <?php endif; ?>
                </div>

                <form class="adm-upload-form" data-group="<?= htmlspecialchars($gk) ?>" data-mode="multi">
                    <input type="hidden" name="photo_key" value="">
                    <div class="adm-upload-simple">
                        <div class="adm-field" style="flex:1">
                            <label>Description (optionnel)</label>
                            <input type="text" name="alt_text" class="adm-input adm-input-sm" placeholder="Description de l'image...">
                        </div>
                    </div>
                    <div class="adm-upload-action">
                        <label class="adm-btn adm-btn-outline adm-upload-btn">
                            Choisir une image
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" hidden>
                        </label>
                        <button type="submit" class="adm-btn adm-btn-primary" disabled>Ajouter</button>
                    </div>
                    <div class="adm-upload-preview" hidden>
                        <img src="" alt="Aperçu">
                        <span class="adm-upload-filename"></span>
                    </div>
                </form>
            </fieldset>
            <?php endforeach; ?>

        </section>

    </div>

    <script>window.VF_CSRF = '<?= $csrf_token ?>';</script>
    <script src="assets/js/admin.js?v=<?= time() ?>"></script>
</body>
</html>
<?php

// ── Login page (called above if not authenticated) ──
function show_login($error, $property) {
    $site_name = htmlspecialchars($property['name'] ?? 'Administration');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Connexion — <?= $site_name ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?= time() ?>">
</head>
<body class="adm-login-page">
    <div class="adm-login-card">
        <h1 class="adm-login-title"><?= $site_name ?></h1>
        <p class="adm-login-sub">Administration du site</p>

        <?php if ($error): ?>
        <p class="adm-login-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="post" class="adm-login-form">
            <div class="adm-field">
                <label for="username">Identifiant</label>
                <input type="text" id="username" name="username" class="adm-input" required autofocus>
            </div>
            <div class="adm-field">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" class="adm-input" required>
            </div>
            <button type="submit" name="login" value="1" class="adm-btn adm-btn-primary adm-btn-full">Se connecter</button>
        </form>
    </div>
</body>
</html>
<?php
}
