<?php
/**
 * Helper pour la gestion des uploads de photos
 * Cree les dossiers si necessaire, valide les fichiers, securise les noms
 */

define('UPLOAD_BASE', realpath(__DIR__ . '/..') . '/uploads');
define('UPLOAD_MAX_SIZE', 500 * 1024 * 1024); // 500 Mo
define('UPLOAD_ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'webp', 'heic', 'gif', 'mp4', 'mov', 'webm', 'avi', 'mkv', 'pdf']);
define('UPLOAD_ALLOWED_MIME', [
    'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/gif',
    'video/mp4', 'video/quicktime', 'video/webm', 'video/x-msvideo', 'video/x-matroska',
    'application/pdf',
]);

/**
 * Initialise les dossiers d'upload avec .htaccess de protection
 */
function initUploadDirs(): void {
    $dirs = [
        UPLOAD_BASE,
        UPLOAD_BASE . '/checkup',
        UPLOAD_BASE . '/inventaire',
        UPLOAD_BASE . '/signatures',
        UPLOAD_BASE . '/qrcodes',
    ];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    // .htaccess pour empecher l'execution de scripts dans uploads
    $htaccess = UPLOAD_BASE . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, implode("\n", [
            '# Empecher execution de scripts',
            '<FilesMatch "\.(php|phtml|php3|php4|php5|pl|py|cgi|sh|bash)$">',
            '    Require all denied',
            '</FilesMatch>',
            '',
            '# Autoriser images, videos et PDF',
            '<FilesMatch "\.(jpg|jpeg|png|gif|webp|heic|svg|mp4|mov|webm|avi|mkv|pdf)$">',
            '    Require all granted',
            '</FilesMatch>',
            '',
            '# Pas de listing de repertoire',
            'Options -Indexes',
            ''
        ]));
    }
}

/**
 * Valide et deplace un fichier uploade
 * @param array $file $_FILES['photo'] ou equivalent
 * @param string $subdir Sous-dossier (checkup, inventaire, signatures)
 * @param string $prefix Prefixe du nom de fichier
 * @return string|null Chemin relatif du fichier uploade ou null si erreur
 */
function handleUpload(array $file, string $subdir, string $prefix = ''): ?string {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // Verifier la taille
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return null;
    }

    // Verifier l'extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_ALLOWED_EXT)) {
        return null;
    }

    // Verifier le type MIME (sauf HEIC pas toujours detecte)
    if ($ext !== 'heic') {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, UPLOAD_ALLOWED_MIME)) {
            return null;
        }
    }

    // Creer le dossier si necessaire
    $uploadDir = UPLOAD_BASE . '/' . $subdir;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generer un nom securise
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = $uploadDir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        return 'uploads/' . $subdir . '/' . $filename;
    }

    return null;
}

// Initialiser les dossiers au chargement
initUploadDirs();
