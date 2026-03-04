<?php
/**
 * Admin AJAX Handlers
 * save_settings, save_texts, upload_photo, delete_photo,
 * create_guide, update_guide, delete_guide,
 * save_guide_block, delete_guide_block, reorder_blocks,
 * save_modules
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ajax'])) return;

header('Content-Type: application/json');

// Admin session check
if (empty($_SESSION['vf_admin'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié.']);
    exit;
}

// CSRF check on all AJAX requests
if (!vf_csrf_verify()) {
    echo json_encode(['success' => false, 'error' => 'Token CSRF invalide. Rechargez la page.']);
    exit;
}

$action = $_POST['action'] ?? '';

// ═══════════════════════════════════════════
// Save modules (sections toggles + guide is_active)
// ═══════════════════════════════════════════
if ($action === 'save_modules') {
    try {
        $sections = $_POST['sections'] ?? '[]';
        $guides_json = $_POST['guides'] ?? '[]';

        // Validate sections against config keys
        $property = vf_load_property();
        $valid_sections = array_keys($property['sections'] ?? []);

        $sections_arr = json_decode($sections, true);
        if (!is_array($sections_arr)) $sections_arr = [];
        $sections_arr = array_values(array_intersect($sections_arr, $valid_sections));
        $sections_json = json_encode($sections_arr);

        // Upsert active_sections
        $stmt = $conn->prepare("INSERT INTO " . vf_table('settings') . " (setting_key, setting_value, setting_group, label, sort_order)
            VALUES (?, ?, 'modules', ?, 0)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute(['active_sections', $sections_json, 'Sections actives']);

        // Update guide is_active flags directly in vf_guides
        $guide_slugs = json_decode($guides_json, true);
        if (!is_array($guide_slugs)) $guide_slugs = [];

        $all_db_guides = vf_load_guides($conn);
        $toggle = $conn->prepare("UPDATE " . vf_table('guides') . " SET is_active = ? WHERE slug = ?");
        foreach ($all_db_guides as $slug => $g) {
            $toggle->execute([in_array($slug, $guide_slugs) ? 1 : 0, $slug]);
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('[VF Admin] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erreur base de données.']);
    }
    exit;
}

// ═══════════════════════════════════════════
// Save settings
// ═══════════════════════════════════════════
if ($action === 'save_settings') {
    try {
        $stmt = $conn->prepare("UPDATE " . vf_table('settings') . " SET setting_value = ? WHERE setting_key = ?");
        $settings = json_decode($_POST['data'], true);
        foreach ($settings as $key => $value) {
            $stmt->execute([trim($value), $key]);
        }
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('[VF Admin] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erreur base de données.']);
    }
    exit;
}

// ═══════════════════════════════════════════
// Save texts
// ═══════════════════════════════════════════
if ($action === 'save_texts') {
    try {
        $stmt = $conn->prepare("UPDATE " . vf_table('texts') . " SET field_value = ? WHERE section_key = ? AND field_key = ?");
        $texts = json_decode($_POST['data'], true);
        foreach ($texts as $item) {
            $stmt->execute([trim($item['value']), $item['section'], $item['field']]);
        }
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('[VF Admin] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erreur base de données.']);
    }
    exit;
}

// ═══════════════════════════════════════════
// Upload photo
// ═══════════════════════════════════════════
if ($action === 'upload_photo') {
    $group   = $_POST['photo_group'] ?? '';
    $pkey    = $_POST['photo_key'] ?? '';
    $alt     = $_POST['alt_text'] ?? '';
    $is_wide = (int)($_POST['is_wide'] ?? 0);

    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
    $file = $_FILES['photo'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Erreur upload']);
        exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'error' => 'Format non autorisé (jpg, png, webp, svg)']);
        exit;
    }

    // Vérifier le type MIME réel du fichier
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed_mimes)) {
        echo json_encode(['success' => false, 'error' => 'Le contenu du fichier ne correspond pas à une image valide.']);
        exit;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'Fichier trop volumineux (max 5 Mo)']);
        exit;
    }

    // Build dir_map dynamically (guides from DB)
    $dir_map = [
        'hero'       => 'assets/photos/hero/',
        'galerie'    => 'assets/photos/galerie/',
        'experience' => 'assets/photos/experience/',
        'logo'       => 'assets/img/',
    ];
    $db_guides = vf_load_guides($conn);
    foreach ($db_guides as $g_slug => $g) {
        $dir_map['guide_' . $g_slug] = "assets/photos/guides/{$g_slug}/";
    }

    $dest_dir = $dir_map[$group] ?? 'assets/photos/';
    $dest_path = __DIR__ . '/../' . $dest_dir;

    if (!is_dir($dest_path)) {
        if (!mkdir($dest_path, 0755, true)) {
            echo json_encode(['success' => false, 'error' => 'Impossible de créer le dossier de destination.']);
            exit;
        }
    }

    $safe_name = $pkey ? preg_replace('/[^a-z0-9\-_]/', '', strtolower($pkey)) : pathinfo($file['name'], PATHINFO_FILENAME);
    $safe_name = $safe_name ?: 'photo';
    $filename  = $safe_name . '_' . substr(uniqid(), -6) . '.' . $ext;
    $full_path = $dest_path . $filename;
    $rel_path  = $dest_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $full_path)) {
        echo json_encode(['success' => false, 'error' => 'Impossible de déplacer le fichier']);
        exit;
    }

    // Compress + generate responsive variants (sm=640, md=1024, lg=1600)
    $variants = vf_process_image($full_path, $rel_path);
    $srcset_json = count($variants) > 1 ? json_encode($variants) : null;

    try {
        $existing = null;
        if ($pkey !== '') {
            $check = $conn->prepare("SELECT id FROM " . vf_table('photos') . " WHERE photo_group = ? AND photo_key = ?");
            $check->execute([$group, $pkey]);
            $existing = $check->fetch();
        }

        if ($existing) {
            $stmt = $conn->prepare("UPDATE " . vf_table('photos') . " SET file_path = ?, srcset_json = ?, alt_text = ?, is_wide = ? WHERE id = ?");
            $stmt->execute([$rel_path, $srcset_json, $alt, $is_wide, $existing['id']]);
            $photo_id = $existing['id'];
        } else {
            $max_order = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM " . vf_table('photos') . " WHERE photo_group = ?");
            $max_order->execute([$group]);
            $next_order = $max_order->fetchColumn();

            $stmt = $conn->prepare("INSERT INTO " . vf_table('photos') . " (photo_group, photo_key, file_path, srcset_json, alt_text, is_wide, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$group, $pkey, $rel_path, $srcset_json, $alt, $is_wide, $next_order]);
            $photo_id = $conn->lastInsertId();
        }

        echo json_encode(['success' => true, 'id' => $photo_id, 'path' => $rel_path]);
    } catch (PDOException $e) {
        error_log('[VF Admin] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erreur base de données.']);
    }
    exit;
}

// ═══════════════════════════════════════════
// Delete photo
// ═══════════════════════════════════════════
if ($action === 'delete_photo') {
    $id = (int)($_POST['photo_id'] ?? 0);
    try {
        $stmt = $conn->prepare("SELECT file_path, srcset_json FROM " . vf_table('photos') . " WHERE id = ?");
        $stmt->execute([$id]);
        $photo = $stmt->fetch();

        if ($photo) {
            $file_full = __DIR__ . '/../' . $photo['file_path'];
            if (file_exists($file_full)) {
                unlink($file_full);
            }
            if (!empty($photo['srcset_json'])) {
                $variants = json_decode($photo['srcset_json'], true) ?: [];
                foreach ($variants as $key => $path) {
                    if ($key === 'original') continue;
                    $vf = __DIR__ . '/../' . $path;
                    if (file_exists($vf)) unlink($vf);
                }
            }
            $conn->prepare("DELETE FROM " . vf_table('photos') . " WHERE id = ?")->execute([$id]);
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('[VF Admin] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erreur base de données.']);
    }
    exit;
}

// ═══════════════════════════════════════════
// Create guide
// ═══════════════════════════════════════════
if ($action === 'create_guide') {
    $slug     = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($_POST['slug'] ?? '')));
    $title    = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $icon_svg = trim($_POST['icon_svg'] ?? '');

    if (!$slug || !$title) {
        echo json_encode(['success' => false, 'error' => 'Le slug et le titre sont obligatoires.']);
        exit;
    }
    if (strlen($slug) > 50) {
        echo json_encode(['success' => false, 'error' => 'Le slug ne peut pas dépasser 50 caractères.']);
        exit;
    }

    try {
        // Check unique slug
        $check = $conn->prepare("SELECT id FROM " . vf_table('guides') . " WHERE slug = ?");
        $check->execute([$slug]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Ce slug existe déjà.']);
            exit;
        }

        $max_order = $conn->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM " . vf_table('guides'))->fetchColumn();

        $stmt = $conn->prepare("INSERT INTO " . vf_table('guides') . " (slug, title, subtitle, icon_svg, is_active, sort_order) VALUES (?, ?, ?, ?, 1, ?)");
        $stmt->execute([$slug, $title, $subtitle, $icon_svg, $max_order]);

        echo json_encode(['success' => true, 'id' => $conn->lastInsertId(), 'slug' => $slug]);
    } catch (PDOException $e) {
        error_log('[VF Admin] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erreur base de données.']);
    }
    exit;
}

// ═══════════════════════════════════════════
// Update guide
// ═══════════════════════════════════════════
if ($action === 'update_guide') {
    $id        = (int)($_POST['guide_id'] ?? 0);
    $title     = trim($_POST['title'] ?? '');
    $subtitle  = trim($_POST['subtitle'] ?? '');
    $icon_svg  = trim($_POST['icon_svg'] ?? '');
    $is_active = (int)($_POST['is_active'] ?? 0);

    if (!$id || !$title) {
        echo json_encode(['success' => false, 'error' => 'ID et titre obligatoires.']);
        exit;
    }

    try {
        $stmt = $conn->prepare("UPDATE " . vf_table('guides') . " SET title = ?, subtitle = ?, icon_svg = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$title, $subtitle, $icon_svg, $is_active, $id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('[VF Admin] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erreur base de données.']);
    }
    exit;
}

// ═══════════════════════════════════════════
// Delete guide (+ blocks + photos)
// ═══════════════════════════════════════════
if ($action === 'delete_guide') {
    $id = (int)($_POST['guide_id'] ?? 0);

    try {
        // Get slug first
        $stmt = $conn->prepare("SELECT slug FROM " . vf_table('guides') . " WHERE id = ?");
        $stmt->execute([$id]);
        $guide = $stmt->fetch();

        if (!$guide) {
            echo json_encode(['success' => false, 'error' => 'Guide introuvable.']);
            exit;
        }

        $slug = $guide['slug'];

        // Delete associated photos (files + DB)
        $photo_group = 'guide_' . $slug;
        $photos = $conn->prepare("SELECT file_path, srcset_json FROM " . vf_table('photos') . " WHERE photo_group = ?");
        $photos->execute([$photo_group]);
        foreach ($photos->fetchAll() as $p) {
            $file_full = __DIR__ . '/../' . $p['file_path'];
            if (file_exists($file_full)) unlink($file_full);
            if (!empty($p['srcset_json'])) {
                $variants = json_decode($p['srcset_json'], true) ?: [];
                foreach ($variants as $vk => $vpath) {
                    if ($vk === 'original') continue;
                    $vf = __DIR__ . '/../' . $vpath;
                    if (file_exists($vf)) unlink($vf);
                }
            }
        }
        $conn->prepare("DELETE FROM " . vf_table('photos') . " WHERE photo_group = ?")->execute([$photo_group]);

        // Delete blocks
        $conn->prepare("DELETE FROM " . vf_table('guide_blocks') . " WHERE guide_slug = ?")->execute([$slug]);

        // Delete guide
        $conn->prepare("DELETE FROM " . vf_table('guides') . " WHERE id = ?")->execute([$id]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('[VF Admin] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erreur base de données.']);
    }
    exit;
}

// ═══════════════════════════════════════════
// Save guide block (create or update)
// ═══════════════════════════════════════════
if ($action === 'save_guide_block') {
    $block_id     = (int)($_POST['block_id'] ?? 0);
    $guide_slug   = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($_POST['guide_slug'] ?? '')));
    $block_type   = trim($_POST['block_type'] ?? 'text');
    $block_title  = trim($_POST['block_title'] ?? '');
    $block_content = $_POST['block_content'] ?? '';

    $valid_types = ['text', 'highlight', 'steps', 'list', 'alert'];
    if (!in_array($block_type, $valid_types)) {
        $block_type = 'text';
    }

    if (!$guide_slug) {
        echo json_encode(['success' => false, 'error' => 'Slug guide manquant.']);
        exit;
    }

    // Sanitize content: allow limited HTML
    $block_content = strip_tags($block_content, '<strong><em><br><a>');

    try {
        if ($block_id > 0) {
            // Update existing
            $stmt = $conn->prepare("UPDATE " . vf_table('guide_blocks') . " SET block_type = ?, block_title = ?, block_content = ? WHERE id = ?");
            $stmt->execute([$block_type, $block_title, $block_content, $block_id]);
        } else {
            // Create new
            $max_order = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM " . vf_table('guide_blocks') . " WHERE guide_slug = ?");
            $max_order->execute([$guide_slug]);
            $next_order = $max_order->fetchColumn();

            $stmt = $conn->prepare("INSERT INTO " . vf_table('guide_blocks') . " (guide_slug, block_type, block_title, block_content, sort_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$guide_slug, $block_type, $block_title, $block_content, $next_order]);
            $block_id = $conn->lastInsertId();
        }

        echo json_encode(['success' => true, 'id' => $block_id]);
    } catch (PDOException $e) {
        error_log('[VF Admin] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erreur base de données.']);
    }
    exit;
}

// ═══════════════════════════════════════════
// Delete guide block
// ═══════════════════════════════════════════
if ($action === 'delete_guide_block') {
    $block_id = (int)($_POST['block_id'] ?? 0);

    try {
        $conn->prepare("DELETE FROM " . vf_table('guide_blocks') . " WHERE id = ?")->execute([$block_id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('[VF Admin] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erreur base de données.']);
    }
    exit;
}

// ═══════════════════════════════════════════
// Reorder guide blocks
// ═══════════════════════════════════════════
if ($action === 'reorder_blocks') {
    $order = json_decode($_POST['order'] ?? '[]', true);
    if (!is_array($order)) {
        echo json_encode(['success' => false, 'error' => 'Ordre invalide.']);
        exit;
    }

    try {
        $stmt = $conn->prepare("UPDATE " . vf_table('guide_blocks') . " SET sort_order = ? WHERE id = ?");
        foreach ($order as $i => $block_id) {
            $stmt->execute([$i, (int)$block_id]);
        }
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('[VF Admin] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erreur base de données.']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Action inconnue']);
exit;
