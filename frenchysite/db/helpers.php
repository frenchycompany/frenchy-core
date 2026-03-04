<?php
/**
 * DB Helpers — Fonctions de chargement des données depuis la base.
 * Utilise config/property.php comme source de fallback.
 */

/**
 * Charge la configuration de la propriété (avec cache statique).
 */
function vf_load_property() {
    static $property = null;
    if ($property === null) {
        $config_file = __DIR__ . '/../config/property.php';
        $property = file_exists($config_file) ? require $config_file : [];
    }
    return $property;
}

/**
 * Retourne le nom de table préfixé.
 * Ex : vf_table('settings') => 'cv01_settings'
 */
function vf_table($name) {
    static $prefix = null;
    if ($prefix === null) {
        $p = vf_load_property();
        $prefix = $p['db_prefix'] ?? 'vf_';
    }
    return $prefix . $name;
}

/**
 * Shortcut : récupère un setting avec fallback.
 */
function vf_get($settings, $key, $default = '') {
    return isset($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : $default;
}

/**
 * Shortcut : récupère un texte de section avec fallback.
 */
function vf_text($texts, $section, $field, $default = '') {
    return isset($texts[$section][$field]) && $texts[$section][$field] !== '' ? $texts[$section][$field] : $default;
}

/**
 * Charge tous les settings en array clé => valeur.
 */
function vf_load_settings($conn) {
    $out = [];
    if (!$conn) return $out;
    try {
        $rows = $conn->query("SELECT setting_key, setting_value FROM " . vf_table('settings'))->fetchAll();
        foreach ($rows as $r) {
            $out[$r['setting_key']] = $r['setting_value'];
        }
    } catch (PDOException $e) {
        error_log('[VF] vf_load_settings: ' . $e->getMessage());
    }
    return $out;
}

/**
 * Charge les textes regroupés par section.
 * Retourne : ['hero' => ['kicker' => '...', 'title' => '...'], ...]
 */
function vf_load_texts($conn) {
    $out = [];
    if (!$conn) return $out;
    try {
        $rows = $conn->query("SELECT section_key, field_key, field_value FROM " . vf_table('texts') . " ORDER BY section_key, sort_order")->fetchAll();
        foreach ($rows as $r) {
            $out[$r['section_key']][$r['field_key']] = $r['field_value'];
        }
    } catch (PDOException $e) {
        error_log('[VF] vf_load_texts: ' . $e->getMessage());
    }
    return $out;
}

/**
 * Charge les photos regroupées par groupe.
 */
function vf_load_photos($conn) {
    $out = [];
    if (!$conn) return $out;
    try {
        $rows = $conn->query("SELECT * FROM " . vf_table('photos') . " ORDER BY photo_group, sort_order")->fetchAll();
        foreach ($rows as $r) {
            $out[$r['photo_group']][] = $r;
        }
    } catch (PDOException $e) {
        error_log('[VF] vf_load_photos: ' . $e->getMessage());
    }
    return $out;
}

/**
 * Construit le tableau $site compatible avec les templates existants.
 * Fallbacks depuis config/property.php.
 */
function vf_build_site_config($settings) {
    $p = vf_load_property();
    return [
        'name'       => vf_get($settings, 'site_name',     $p['name']          ?? 'Mon Logement'),
        'tagline'    => vf_get($settings, 'site_tagline',  $p['tagline']       ?? 'Bienvenue'),
        'location'   => vf_get($settings, 'site_location', $p['location']      ?? ''),
        'phone'      => vf_get($settings, 'phone',         $p['phone']         ?? ''),
        'phone_raw'  => vf_get($settings, 'phone_raw',     $p['phone_raw']     ?? ''),
        'email'      => vf_get($settings, 'email',         $p['email']         ?? ''),
        'address'    => vf_get($settings, 'address',       $p['address']       ?? ''),
        'airbnb_id'  => vf_get($settings, 'airbnb_id',    $p['airbnb_id']     ?? ''),
        'matterport' => vf_get($settings, 'matterport_id',$p['matterport_id'] ?? ''),
        'monogram'   => $p['monogram'] ?? 'FC',
        'year'       => date('Y'),
        'logo'       => 'assets/img/logo.png',
    ];
}

/**
 * Construit le CSS inline :root avec les couleurs/typo de la BDD.
 */
function vf_build_css_vars($settings) {
    $p = vf_load_property();
    $colors = $p['colors'] ?? [];

    $vars = [
        'color_green'    => ['--vf-green',     $colors['green']    ?? '#1D5345'],
        'color_green_dk' => ['--vf-green-dk',  $colors['green_dk'] ?? '#153d33'],
        'color_beige'    => ['--vf-beige',     $colors['beige']    ?? '#CFCDB0'],
        'color_grey'     => ['--vf-grey',      $colors['grey']     ?? '#B2ACA9'],
        'color_brown'    => ['--vf-brown',     $colors['brown']    ?? '#6C5C4F'],
        'color_offwhite' => ['--vf-offwhite',  $colors['offwhite'] ?? '#E8E4D0'],
        'color_dark'     => ['--vf-dark',      $colors['dark']     ?? '#2B2924'],
    ];

    $lines = [];
    foreach ($vars as $key => [$prop, $default]) {
        $val = vf_get($settings, $key, $default);
        $lines[] = "    $prop: $val;";
    }

    $font_display = vf_get($settings, 'font_display', $p['font_display'] ?? 'Playfair Display');
    $font_body    = vf_get($settings, 'font_body',    $p['font_body']    ?? 'Inter');
    $lines[] = "    --font-display: '$font_display', Georgia, 'Times New Roman', serif;";
    $lines[] = "    --font-body: '$font_body', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;";

    return ":root {\n" . implode("\n", $lines) . "\n}";
}

/**
 * Construit l'URL Google Fonts depuis les settings.
 */
function vf_build_font_url($settings) {
    $p = vf_load_property();
    $display = vf_get($settings, 'font_display', $p['font_display'] ?? 'Playfair Display');
    $body    = vf_get($settings, 'font_body',    $p['font_body']    ?? 'Inter');

    $display_param = str_replace(' ', '+', $display);
    $body_param    = str_replace(' ', '+', $body);

    return "https://fonts.googleapis.com/css2?family={$display_param}:ital,wght@0,400;0,500;0,600;1,400;1,500&family={$body_param}:wght@300;400;500;600&display=swap";
}

/**
 * Retourne les sections actives (filtrées par la BDD).
 * Si la clé active_sections n'existe pas en BDD, toutes les sections du config sont actives.
 */
function vf_get_active_sections($settings) {
    $property = vf_load_property();
    $all_sections = $property['sections'] ?? [];

    $json = $settings['active_sections'] ?? null;
    if ($json === null || $json === '') {
        return $all_sections;
    }

    $active_keys = json_decode($json, true) ?: [];
    $result = [];
    foreach ($all_sections as $key => $cfg) {
        if (in_array($key, $active_keys)) {
            $result[$key] = $cfg;
        }
    }
    return $result;
}

/**
 * @deprecated Utiliser vf_load_active_guides($conn) à la place.
 */
function vf_get_active_guides($settings) {
    $property = vf_load_property();
    $all_guides = $property['guides'] ?? [];

    $json = $settings['active_guides'] ?? null;
    if ($json === null || $json === '') {
        return $all_guides;
    }

    $active_keys = json_decode($json, true) ?: [];
    $result = [];
    foreach ($all_guides as $key => $cfg) {
        if (in_array($key, $active_keys)) {
            $result[$key] = $cfg;
        }
    }
    return $result;
}

/**
 * Résout le chemin photo : BDD (par clé) > BDD (première du groupe) > fallback.
 */
function vf_photo_url($db_photos, $group, $key, $fallback = '') {
    if (!empty($db_photos[$group])) {
        // 1. Chercher par clé exacte
        foreach ($db_photos[$group] as $p) {
            if ($p['photo_key'] === $key && file_exists($p['file_path'])) {
                return $p['file_path'];
            }
        }
        // 2. Fallback : première photo du groupe (quelle que soit la clé)
        $first = $db_photos[$group][0];
        if (!empty($first['file_path']) && file_exists($first['file_path'])) {
            return $first['file_path'];
        }
    }
    return $fallback;
}

/**
 * Génère l'attribut srcset HTML à partir du JSON de variantes.
 * Retourne '' si pas de variantes.
 */
function vf_srcset($photo) {
    if (empty($photo['srcset_json'])) return '';
    $variants = json_decode($photo['srcset_json'], true);
    if (!is_array($variants) || count($variants) <= 1) return '';

    $widths = ['sm' => '640w', 'md' => '1024w', 'lg' => '1600w'];
    $parts = [];
    foreach ($widths as $key => $w) {
        if (!empty($variants[$key])) {
            $parts[] = htmlspecialchars($variants[$key]) . ' ' . $w;
        }
    }
    // Original as largest
    if (!empty($variants['original'])) {
        $parts[] = htmlspecialchars($variants['original']) . ' 2000w';
    }
    return $parts ? 'srcset="' . implode(', ', $parts) . '" sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"' : '';
}

/**
 * Seed la base de données avec les valeurs par défaut du config.
 * Appelé après la création initiale du schéma.
 */
function vf_seed_from_config($conn) {
    $p = vf_load_property();
    if (!$conn || empty($p)) return;

    $stmt = $conn->prepare("UPDATE " . vf_table('settings') . " SET setting_value = ? WHERE setting_key = ?");

    // Identity
    $identity_map = [
        'site_name'     => $p['name']          ?? '',
        'site_tagline'  => $p['tagline']       ?? '',
        'site_location' => $p['location']      ?? '',
        'phone'         => $p['phone']         ?? '',
        'phone_raw'     => $p['phone_raw']     ?? '',
        'email'         => $p['email']         ?? '',
        'address'       => $p['address']       ?? '',
    ];
    foreach ($identity_map as $key => $value) {
        if ($value) $stmt->execute([$value, $key]);
    }

    // Integrations
    if (!empty($p['airbnb_id']))     $stmt->execute([$p['airbnb_id'], 'airbnb_id']);
    if (!empty($p['matterport_id'])) $stmt->execute([$p['matterport_id'], 'matterport_id']);

    // Colors
    $color_map = [
        'color_green'    => 'green',
        'color_green_dk' => 'green_dk',
        'color_beige'    => 'beige',
        'color_grey'     => 'grey',
        'color_brown'    => 'brown',
        'color_offwhite' => 'offwhite',
        'color_dark'     => 'dark',
    ];
    foreach ($color_map as $setting_key => $config_key) {
        if (!empty($p['colors'][$config_key])) {
            $stmt->execute([$p['colors'][$config_key], $setting_key]);
        }
    }

    // Typography
    if (!empty($p['font_display'])) $stmt->execute([$p['font_display'], 'font_display']);
    if (!empty($p['font_body']))    $stmt->execute([$p['font_body'], 'font_body']);
}

/**
 * Compresse et redimensionne une image uploadée.
 * Crée des variantes responsive (sm, md, lg) si GD est disponible.
 * Retourne le tableau des variantes : ['original' => '...', 'sm' => '...', 'md' => '...']
 */
function vf_process_image($full_path, $rel_path) {
    if (!extension_loaded('gd')) return ['original' => $rel_path];

    $ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
    if ($ext === 'svg') return ['original' => $rel_path];

    $info = @getimagesize($full_path);
    if (!$info) return ['original' => $rel_path];

    $orig_w = $info[0];
    $orig_h = $info[1];
    $mime = $info['mime'];

    // Load source image
    switch ($mime) {
        case 'image/jpeg': $src = @imagecreatefromjpeg($full_path); break;
        case 'image/png':  $src = @imagecreatefrompng($full_path);  break;
        case 'image/webp': $src = @imagecreatefromwebp($full_path); break;
        default: return ['original' => $rel_path];
    }
    if (!$src) return ['original' => $rel_path];

    // Compress original (quality 82 for JPEG)
    if ($mime === 'image/jpeg') {
        imagejpeg($src, $full_path, 82);
    } elseif ($mime === 'image/png') {
        imagepng($src, $full_path, 8);
    }

    // Generate responsive variants
    $widths = ['sm' => 640, 'md' => 1024, 'lg' => 1600];
    $base = pathinfo($full_path, PATHINFO_FILENAME);
    $dir  = dirname($full_path) . '/';
    $rel_dir = dirname($rel_path) . '/';
    $variants = ['original' => $rel_path];

    foreach ($widths as $suffix => $max_w) {
        if ($orig_w <= $max_w) continue;

        $ratio = $max_w / $orig_w;
        $new_w = $max_w;
        $new_h = (int)round($orig_h * $ratio);

        $thumb = imagecreatetruecolor($new_w, $new_h);

        // Preserve transparency for PNG
        if ($mime === 'image/png') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }

        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);

        $variant_name = $base . '-' . $suffix . '.' . $ext;
        $variant_path = $dir . $variant_name;

        switch ($mime) {
            case 'image/jpeg': imagejpeg($thumb, $variant_path, 80); break;
            case 'image/png':  imagepng($thumb, $variant_path, 8);   break;
            case 'image/webp': imagewebp($thumb, $variant_path, 80); break;
        }

        imagedestroy($thumb);
        $variants[$suffix] = $rel_dir . $variant_name;
    }

    imagedestroy($src);
    return $variants;
}

/**
 * Charge un guide unique par slug.
 * Retourne le row ou null.
 */
function vf_load_guide($conn, $slug) {
    if (!$conn) return null;
    try {
        $stmt = $conn->prepare("SELECT * FROM " . vf_table('guides') . " WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Charge tous les guides depuis la BDD.
 * Retourne un tableau indexé par slug.
 */
function vf_load_guides($conn) {
    $out = [];
    if (!$conn) return $out;
    try {
        $rows = $conn->query("SELECT * FROM " . vf_table('guides') . " ORDER BY sort_order, id")->fetchAll();
        foreach ($rows as $r) {
            $out[$r['slug']] = $r;
        }
    } catch (PDOException $e) {
        // Table may not exist yet
    }
    return $out;
}

/**
 * Charge les guides actifs uniquement.
 */
function vf_load_active_guides($conn) {
    $all = vf_load_guides($conn);
    return array_filter($all, function($g) { return $g['is_active']; });
}

/**
 * Charge les blocs d'un guide.
 */
function vf_load_guide_blocks($conn, $slug) {
    $out = [];
    if (!$conn) return $out;
    try {
        $stmt = $conn->prepare("SELECT * FROM " . vf_table('guide_blocks') . " WHERE guide_slug = ? ORDER BY sort_order, id");
        $stmt->execute([$slug]);
        $out = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('[VF] vf_load_guide_blocks: ' . $e->getMessage());
    }
    return $out;
}
