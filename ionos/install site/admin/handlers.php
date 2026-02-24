<?php
/**
 * Admin AJAX Handlers — save_settings, save_texts, upload_photo, delete_photo
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

// Save modules (active sections & guides)
if ($action === 'save_modules') {
    try {
        $sections = $_POST['sections'] ?? '[]';
        $guides   = $_POST['guides'] ?? '[]';

        // Validate JSON arrays against config keys
        $property = vf_load_property();
        $valid_sections = array_keys($property['sections'] ?? []);
        $valid_guides   = array_keys($property['guides'] ?? []);

        $sections_arr = json_decode($sections, true);
        $guides_arr   = json_decode($guides, true);
        if (!is_array($sections_arr)) $sections_arr = [];
        if (!is_array($guides_arr))   $guides_arr   = [];

        $sections_arr = array_values(array_intersect($sections_arr, $valid_sections));
        $guides_arr   = array_values(array_intersect($guides_arr, $valid_guides));

        $sections_json = json_encode($sections_arr);
        $guides_json   = json_encode($guides_arr);

        // Upsert active_sections
        $stmt = $conn->prepare("INSERT INTO " . vf_table('settings') . " (setting_key, setting_value, setting_group, label, sort_order)
            VALUES (?, ?, 'modules', ?, 0)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute(['active_sections', $sections_json, 'Sections actives']);
        $stmt->execute(['active_guides',   $guides_json,   'Guides actifs']);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('[VF Admin] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erreur base de données.']);
    }
    exit;
}

// Save settings
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

// Save texts
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

// Upload photo
if ($action === 'upload_photo') {
    $property = vf_load_property();
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

    // Build dir_map dynamically from config
    $dir_map = [
        'hero'       => 'assets/photos/hero/',
        'galerie'    => 'assets/photos/galerie/',
        'experience' => 'assets/photos/experience/',
        'logo'       => 'assets/img/',
    ];
    foreach ($property['guides'] ?? [] as $slug => $g) {
        $dir_map['guide_' . $slug] = "assets/photos/guides/{$slug}/";
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
        // Only check for existing photo if a specific key was given (e.g., hero, logo)
        // Empty key = always add new photo (galerie, etc.)
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

// Delete photo
if ($action === 'delete_photo') {
    $id = (int)($_POST['photo_id'] ?? 0);
    try {
        $stmt = $conn->prepare("SELECT file_path, srcset_json FROM " . vf_table('photos') . " WHERE id = ?");
        $stmt->execute([$id]);
        $photo = $stmt->fetch();

        if ($photo) {
            // Delete original file
            $file_full = __DIR__ . '/../' . $photo['file_path'];
            if (file_exists($file_full)) {
                unlink($file_full);
            }
            // Delete responsive variants
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

echo json_encode(['success' => false, 'error' => 'Action inconnue']);
exit;
