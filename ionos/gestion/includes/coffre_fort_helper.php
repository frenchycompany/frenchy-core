<?php
/**
 * coffre_fort_helper.php — Fonctions du coffre-fort numérique
 * Chiffrement AES-256, gestion 2FA SMS, streaming sécurisé, logs
 */

class CoffreFort
{
    private PDO $conn;
    private string $storageDir;
    private string $masterKey;

    // Durée session coffre après 2FA (15 minutes)
    const SESSION_DURATION = 900;
    // Durée validité code 2FA (5 minutes)
    const CODE_EXPIRY = 300;
    // Max tentatives code 2FA
    const MAX_ATTEMPTS = 3;
    // Taille max upload (200 Mo)
    const MAX_FILE_SIZE = 200 * 1024 * 1024;

    const ALLOWED_TYPES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic',
        'video/mp4', 'video/quicktime', 'video/webm', 'video/x-msvideo',
        'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->storageDir = __DIR__ . '/../coffre_fort_storage';
        $this->masterKey = env('COFFRE_FORT_KEY', 'frenchy-coffre-fort-default-key-change-me');

        // Créer le répertoire de stockage s'il n'existe pas
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0700, true);
            // Protection .htaccess — interdit tout accès direct
            file_put_contents($this->storageDir . '/.htaccess', "Deny from all\n");
        }
    }

    // ========================================================
    // CHIFFREMENT AES-256-GCM
    // ========================================================

    /**
     * Chiffre un fichier et le stocke dans le coffre.
     */
    public function chiffrerFichier(string $sourcePath): array
    {
        $iv = random_bytes(12); // GCM utilise 12 octets
        $fileKey = random_bytes(32); // Clé AES-256 unique par fichier

        $plaintext = file_get_contents($sourcePath);
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $fileKey, OPENSSL_RAW_DATA, $iv, $tag);

        // Stocker : IV (12) + Tag (16) + Ciphertext
        $encrypted = $iv . $tag . $ciphertext;

        $nomStockage = bin2hex(random_bytes(32)) . '.vault';
        $cheminComplet = $this->storageDir . '/' . $nomStockage;
        file_put_contents($cheminComplet, $encrypted);

        // Chiffrer la clé du fichier avec la clé maître
        $masterIv = random_bytes(12);
        $encryptedKey = openssl_encrypt($fileKey, 'aes-256-gcm', hash('sha256', $this->masterKey, true), OPENSSL_RAW_DATA, $masterIv, $masterTag);

        return [
            'nom_stockage' => $nomStockage,
            'cle_chiffrement' => base64_encode($masterIv . $masterTag . $encryptedKey),
            'iv' => base64_encode($iv),
            'hash_sha256' => hash_file('sha256', $sourcePath),
        ];
    }

    /**
     * Déchiffre un fichier et retourne son contenu.
     */
    public function dechiffrerFichier(array $fichier): ?string
    {
        $cheminComplet = $this->storageDir . '/' . $fichier['nom_stockage'];
        if (!file_exists($cheminComplet)) {
            error_log("CoffreFort: fichier introuvable: {$cheminComplet}");
            return null;
        }

        // Déchiffrer la clé du fichier
        $encKeyData = base64_decode($fichier['cle_chiffrement']);
        if ($encKeyData === false || strlen($encKeyData) < 29) {
            error_log("CoffreFort: cle_chiffrement invalide pour fichier #{$fichier['id']}");
            return null;
        }
        $masterIv = substr($encKeyData, 0, 12);
        $masterTag = substr($encKeyData, 12, 16);
        $encKey = substr($encKeyData, 28);
        $fileKey = openssl_decrypt($encKey, 'aes-256-gcm', hash('sha256', $this->masterKey, true), OPENSSL_RAW_DATA, $masterIv, $masterTag);
        if ($fileKey === false) {
            error_log("CoffreFort: dechiffrement cle echoue pour fichier #{$fichier['id']} (masterKey len=" . strlen($this->masterKey) . ")");
            return null;
        }

        // Déchiffrer le fichier
        $encrypted = file_get_contents($cheminComplet);
        $iv = substr($encrypted, 0, 12);
        $tag = substr($encrypted, 12, 16);
        $ciphertext = substr($encrypted, 28);

        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $fileKey, OPENSSL_RAW_DATA, $iv, $tag);
        return $plaintext !== false ? $plaintext : null;
    }

    // ========================================================
    // UPLOAD
    // ========================================================

    /**
     * Upload et chiffre un fichier dans le coffre.
     */
    public function upload(array $file, string $categorie, int $userId, string $description = '', string $tags = ''): array
    {
        // Validations
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Erreur upload : code ' . $file['error']];
        }
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ['success' => false, 'error' => 'Fichier trop volumineux (max 200 Mo)'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, self::ALLOWED_TYPES)) {
            return ['success' => false, 'error' => 'Type de fichier non autorisé : ' . $mimeType];
        }

        // Chiffrer et stocker
        $crypto = $this->chiffrerFichier($file['tmp_name']);

        // Insérer en base
        $stmt = $this->conn->prepare(
            "INSERT INTO coffre_fort_fichiers
                (nom_original, nom_stockage, chemin_relatif, type_mime, taille, categorie,
                 description, tags, cle_chiffrement, iv, hash_sha256, uploade_par)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $file['name'],
            $crypto['nom_stockage'],
            'coffre_fort_storage/' . $crypto['nom_stockage'],
            $mimeType,
            $file['size'],
            $categorie,
            $description ?: null,
            $tags ?: null,
            $crypto['cle_chiffrement'],
            $crypto['iv'],
            $crypto['hash_sha256'],
            $userId,
        ]);

        $fichierId = (int) $this->conn->lastInsertId();

        $this->log($userId, 'upload', $fichierId, 'Upload: ' . $file['name']);

        return ['success' => true, 'id' => $fichierId];
    }

    /**
     * Supprime un fichier (soft delete).
     */
    public function supprimer(int $fichierId, int $userId): bool
    {
        $stmt = $this->conn->prepare("UPDATE coffre_fort_fichiers SET supprime = 1 WHERE id = ?");
        $stmt->execute([$fichierId]);
        $this->log($userId, 'suppression', $fichierId);
        return true;
    }

    /**
     * Liste les fichiers du coffre.
     */
    public function lister(string $categorie = '', string $search = ''): array
    {
        $sql = "SELECT f.*, u.nom AS uploade_par_nom
                FROM coffre_fort_fichiers f
                LEFT JOIN users u ON f.uploade_par = u.id
                WHERE f.supprime = 0";
        $params = [];

        if ($categorie) {
            $sql .= " AND f.categorie = ?";
            $params[] = $categorie;
        }
        if ($search) {
            $sql .= " AND (f.nom_original LIKE ? OR f.description LIKE ? OR f.tags LIKE ?)";
            $s = '%' . $search . '%';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $sql .= " ORDER BY f.created_at DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un fichier par ID.
     */
    public function getFichier(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM coffre_fort_fichiers WHERE id = ? AND supprime = 0");
        $stmt->execute([$id]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);
        return $f ?: null;
    }

    // ========================================================
    // 2FA SMS
    // ========================================================

    /**
     * Envoie un code 2FA par SMS via le Raspberry Pi.
     */
    public function envoyer2FA(int $userId, string $telephone): array
    {
        // Nettoyer les anciens codes
        $this->conn->prepare(
            "DELETE FROM coffre_fort_2fa WHERE user_id = ? AND (verifie = 1 OR expire_at < NOW())"
        )->execute([$userId]);

        // Générer le code
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expireAt = date('Y-m-d H:i:s', time() + self::CODE_EXPIRY);

        $stmt = $this->conn->prepare(
            "INSERT INTO coffre_fort_2fa (user_id, code, telephone, expire_at, ip_address)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $code, $telephone, $expireAt, $_SERVER['REMOTE_ADDR'] ?? '']);

        // Envoyer le SMS via le système existant (table sms_outbox)
        require_once __DIR__ . '/rpi_db.php';
        $rpi = getRpiPdo();
        $stmt = $rpi->prepare(
            "INSERT INTO sms_outbox (receiver, message, status, created_at)
             VALUES (?, ?, 'pending', NOW())"
        );
        $message = "FRENCHY COFFRE-FORT\nVotre code d'accès : {$code}\nValable 5 minutes.\nNe partagez jamais ce code.";
        $stmt->execute([$telephone, $message]);

        $this->log($userId, 'login_2fa', null, 'Code 2FA envoyé à ' . substr($telephone, 0, -4) . '****');

        return ['success' => true, 'expire_at' => $expireAt];
    }

    /**
     * Vérifie un code 2FA.
     */
    public function verifier2FA(int $userId, string $code): array
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM coffre_fort_2fa
             WHERE user_id = ? AND verifie = 0 AND expire_at > NOW()
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->log($userId, 'verification_fail', null, 'Code expiré ou inexistant');
            return ['success' => false, 'error' => 'Code expiré. Demandez un nouveau code.'];
        }

        if ($row['tentatives'] >= self::MAX_ATTEMPTS) {
            $this->log($userId, 'verification_fail', null, 'Max tentatives atteint');
            return ['success' => false, 'error' => 'Trop de tentatives. Demandez un nouveau code.'];
        }

        // Incrémenter tentatives
        $this->conn->prepare("UPDATE coffre_fort_2fa SET tentatives = tentatives + 1 WHERE id = ?")
            ->execute([$row['id']]);

        if (!hash_equals($row['code'], $code)) {
            $this->log($userId, 'verification_fail', null, 'Code incorrect');
            $restant = self::MAX_ATTEMPTS - $row['tentatives'] - 1;
            return ['success' => false, 'error' => "Code incorrect. {$restant} tentative(s) restante(s)."];
        }

        // Marquer comme vérifié
        $this->conn->prepare("UPDATE coffre_fort_2fa SET verifie = 1 WHERE id = ?")->execute([$row['id']]);

        // Créer session coffre-fort
        $token = $this->creerSession($userId);

        $this->log($userId, 'verification_ok', null, 'Accès coffre-fort autorisé');

        return ['success' => true, 'token' => $token];
    }

    // ========================================================
    // SESSIONS COFFRE-FORT
    // ========================================================

    /**
     * Crée une session de consultation sécurisée.
     */
    public function creerSession(int $userId): string
    {
        // Invalider les anciennes sessions
        $this->conn->prepare("DELETE FROM coffre_fort_sessions WHERE user_id = ?")->execute([$userId]);

        $token = bin2hex(random_bytes(64));
        $expireAt = date('Y-m-d H:i:s', time() + self::SESSION_DURATION);
        $uaHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

        $stmt = $this->conn->prepare(
            "INSERT INTO coffre_fort_sessions (user_id, token, ip_address, user_agent_hash, expire_at)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $token, $_SERVER['REMOTE_ADDR'] ?? '', $uaHash, $expireAt]);

        $_SESSION['coffre_fort_token'] = $token;

        return $token;
    }

    /**
     * Vérifie que la session coffre-fort est valide.
     */
    public function verifierSession(): ?array
    {
        $token = $_SESSION['coffre_fort_token'] ?? null;
        if (!$token) return null;

        $stmt = $this->conn->prepare(
            "SELECT * FROM coffre_fort_sessions
             WHERE token = ? AND expire_at > NOW()"
        );
        $stmt->execute([$token]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            unset($_SESSION['coffre_fort_token']);
            return null;
        }

        // Vérifier IP et User-Agent
        $uaHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($session['ip_address'] !== ($_SERVER['REMOTE_ADDR'] ?? '') || $session['user_agent_hash'] !== $uaHash) {
            $this->invaliderSession($token);
            return null;
        }

        return $session;
    }

    /**
     * Invalide une session.
     */
    public function invaliderSession(string $token): void
    {
        $this->conn->prepare("DELETE FROM coffre_fort_sessions WHERE token = ?")->execute([$token]);
        unset($_SESSION['coffre_fort_token']);
    }

    /**
     * Prolonge la session active.
     */
    public function prolongerSession(string $token): void
    {
        $expireAt = date('Y-m-d H:i:s', time() + self::SESSION_DURATION);
        $this->conn->prepare("UPDATE coffre_fort_sessions SET expire_at = ? WHERE token = ?")
            ->execute([$expireAt, $token]);
    }

    /**
     * Temps restant de la session en secondes.
     */
    public function tempsRestant(): int
    {
        $session = $this->verifierSession();
        if (!$session) return 0;
        return max(0, strtotime($session['expire_at']) - time());
    }

    // ========================================================
    // STREAMING SÉCURISÉ
    // ========================================================

    /**
     * Stream une image en base64 pour affichage Canvas JS (pas de téléchargement).
     */
    public function streamImageBase64(int $fichierId, int $userId): ?string
    {
        $fichier = $this->getFichier($fichierId);
        if (!$fichier) return null;

        $contenu = $this->dechiffrerFichier($fichier);
        if (!$contenu) return null;

        $this->log($userId, 'consultation', $fichierId);

        return 'data:' . $fichier['type_mime'] . ';base64,' . base64_encode($contenu);
    }

    /**
     * Stream une vidéo déchiffrée avec headers anti-téléchargement.
     */
    public function streamVideo(int $fichierId, int $userId): void
    {
        $fichier = $this->getFichier($fichierId);
        if (!$fichier) {
            http_response_code(404);
            exit;
        }

        $contenu = $this->dechiffrerFichier($fichier);
        if (!$contenu) {
            http_response_code(500);
            exit;
        }

        $this->log($userId, 'consultation', $fichierId);

        $length = strlen($contenu);

        header('Content-Type: ' . $fichier['type_mime']);
        header('Content-Length: ' . $length);
        header('Accept-Ranges: bytes');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Content-Disposition: inline');
        // Anti-téléchargement
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'');

        // Support Range pour le streaming vidéo
        if (isset($_SERVER['HTTP_RANGE'])) {
            preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches);
            $start = (int) $matches[1];
            $end = !empty($matches[2]) ? (int) $matches[2] : $length - 1;

            header('HTTP/1.1 206 Partial Content');
            header("Content-Range: bytes {$start}-{$end}/{$length}");
            header('Content-Length: ' . ($end - $start + 1));
            echo substr($contenu, $start, $end - $start + 1);
        } else {
            echo $contenu;
        }
        exit;
    }

    /**
     * Stream un document PDF en inline.
     */
    public function streamDocument(int $fichierId, int $userId): void
    {
        $fichier = $this->getFichier($fichierId);
        if (!$fichier) {
            http_response_code(404);
            exit;
        }

        $contenu = $this->dechiffrerFichier($fichier);
        if (!$contenu) {
            http_response_code(500);
            exit;
        }

        $this->log($userId, 'consultation', $fichierId);

        header('Content-Type: ' . $fichier['type_mime']);
        header('Content-Length: ' . strlen($contenu));
        header('Content-Disposition: inline');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        echo $contenu;
        exit;
    }

    // ========================================================
    // LOGS
    // ========================================================

    public function log(int $userId, string $action, ?int $fichierId = null, ?string $details = null): void
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO coffre_fort_logs (user_id, action, fichier_id, ip_address, user_agent, details)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $userId,
            $action,
            $fichierId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            $details,
        ]);
    }

    /**
     * Récupère les derniers logs.
     */
    public function getLogs(int $limit = 50): array
    {
        $stmt = $this->conn->prepare(
            "SELECT l.*, u.nom AS user_nom, f.nom_original AS fichier_nom
             FROM coffre_fort_logs l
             LEFT JOIN users u ON l.user_id = u.id
             LEFT JOIN coffre_fort_fichiers f ON l.fichier_id = f.id
             ORDER BY l.created_at DESC LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========================================================
    // STATS
    // ========================================================

    public function getStats(): array
    {
        $stats = [];

        $stmt = $this->conn->query("SELECT COUNT(*) FROM coffre_fort_fichiers WHERE supprime = 0");
        $stats['total_fichiers'] = (int) $stmt->fetchColumn();

        $stmt = $this->conn->query("SELECT COALESCE(SUM(taille), 0) FROM coffre_fort_fichiers WHERE supprime = 0");
        $stats['taille_totale'] = (int) $stmt->fetchColumn();

        $stmt = $this->conn->query(
            "SELECT categorie, COUNT(*) as nb FROM coffre_fort_fichiers WHERE supprime = 0 GROUP BY categorie"
        );
        $stats['par_categorie'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return $stats;
    }

    /**
     * Formate une taille en octets.
     */
    public static function formatTaille(int $bytes): string
    {
        $units = ['o', 'Ko', 'Mo', 'Go'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < 3) {
            $size /= 1024;
            $i++;
        }
        return round($size, 1) . ' ' . $units[$i];
    }
}
