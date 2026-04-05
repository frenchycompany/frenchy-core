<?php
/**
 * FrenchyBot — Securite des endpoints API publics
 * Rate limiting, validation des inputs, protection anti-abuse
 *
 * Usage dans les endpoints :
 *   require_once __DIR__ . '/../includes/api-security.php';
 *   apiRateLimit('chat', 10, 60);  // 10 req/min
 *   $input = apiValidateJson(['token' => 'required|string|min:16', 'message' => 'required|string|max:1000']);
 */

/**
 * Rate limiting par IP + endpoint (en memoire fichier, pas de Redis requis)
 * @param string $endpoint Nom de l'endpoint (ex: 'chat', 'upsell', 'action')
 * @param int $maxRequests Nombre max de requetes
 * @param int $windowSeconds Fenetre en secondes
 */
function apiRateLimit(string $endpoint, int $maxRequests = 20, int $windowSeconds = 60): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = md5($endpoint . ':' . $ip);

    $rateLimitDir = sys_get_temp_dir() . '/frenchybot_ratelimit';
    if (!is_dir($rateLimitDir)) {
        @mkdir($rateLimitDir, 0755, true);
    }

    $file = $rateLimitDir . '/' . $key;

    $data = ['count' => 0, 'window_start' => time()];
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        $data = $raw ? json_decode($raw, true) : $data;
        if (!$data || !isset($data['count'])) {
            $data = ['count' => 0, 'window_start' => time()];
        }
    }

    // Reset si la fenetre est expiree
    if (time() - $data['window_start'] > $windowSeconds) {
        $data = ['count' => 0, 'window_start' => time()];
    }

    $data['count']++;

    @file_put_contents($file, json_encode($data), LOCK_EX);

    if ($data['count'] > $maxRequests) {
        http_response_code(429);
        header('Retry-After: ' . ($windowSeconds - (time() - $data['window_start'])));
        echo json_encode(['error' => 'Trop de requetes. Reessayez dans quelques instants.']);
        exit;
    }

    // Nettoyage periodique des anciens fichiers (1% des requetes)
    if (random_int(1, 100) === 1) {
        cleanupRateLimitFiles($rateLimitDir, $windowSeconds * 2);
    }
}

/**
 * Nettoie les fichiers de rate limit expires
 */
function cleanupRateLimitFiles(string $dir, int $maxAge): void
{
    $files = @scandir($dir);
    if (!$files) return;

    $now = time();
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $dir . '/' . $f;
        if (is_file($path) && ($now - filemtime($path)) > $maxAge) {
            @unlink($path);
        }
    }
}

/**
 * Valide et parse l'input JSON du body POST
 * @param array $rules Regles de validation : ['field' => 'required|string|min:5|max:100']
 * @return array Les donnees validees
 */
function apiValidateJson(array $rules): array
{
    $raw = file_get_contents('php://input');

    if (empty($raw)) {
        http_response_code(400);
        echo json_encode(['error' => 'Body JSON requis']);
        exit;
    }

    $input = json_decode($raw, true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'JSON invalide']);
        exit;
    }

    $errors = [];
    $validated = [];

    foreach ($rules as $field => $ruleString) {
        $fieldRules = explode('|', $ruleString);
        $value = $input[$field] ?? null;
        $isRequired = in_array('required', $fieldRules);

        if ($value === null || $value === '') {
            if ($isRequired) {
                $errors[] = "Champ '$field' requis";
            }
            continue;
        }

        foreach ($fieldRules as $rule) {
            if ($rule === 'required') continue;

            if ($rule === 'string') {
                if (!is_string($value)) {
                    $errors[] = "'$field' doit etre une chaine";
                    break;
                }
                $value = trim($value);
            }

            if ($rule === 'int' || $rule === 'integer') {
                if (!is_numeric($value)) {
                    $errors[] = "'$field' doit etre un nombre";
                    break;
                }
                $value = (int)$value;
            }

            if (str_starts_with($rule, 'min:')) {
                $min = (int)substr($rule, 4);
                if (is_string($value) && mb_strlen($value) < $min) {
                    $errors[] = "'$field' trop court (min $min)";
                } elseif (is_int($value) && $value < $min) {
                    $errors[] = "'$field' trop petit (min $min)";
                }
            }

            if (str_starts_with($rule, 'max:')) {
                $max = (int)substr($rule, 4);
                if (is_string($value) && mb_strlen($value) > $max) {
                    $value = mb_substr($value, 0, $max); // Tronquer plutot que rejeter
                } elseif (is_int($value) && $value > $max) {
                    $errors[] = "'$field' trop grand (max $max)";
                }
            }
        }

        $validated[$field] = $value;
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['error' => implode(', ', $errors)]);
        exit;
    }

    return $validated;
}

/**
 * Verifie un token HUB et retourne les donnees de base
 * @return array Les donnees du hub_token + reservation
 */
function apiValidateHubToken(PDO $pdo, string $token): array
{
    if (empty($token) || strlen($token) < 16) {
        http_response_code(400);
        echo json_encode(['error' => 'Token invalide']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT ht.id AS hub_token_id, ht.reservation_id, ht.logement_id,
               r.prenom, r.nom, r.telephone, r.email
        FROM hub_tokens ht
        JOIN reservation r ON ht.reservation_id = r.id
        WHERE ht.token = ? AND ht.active = 1
    ");
    $stmt->execute([$token]);
    $hub = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$hub) {
        http_response_code(404);
        echo json_encode(['error' => 'Lien invalide ou expire']);
        exit;
    }

    return $hub;
}

/**
 * Headers de securite pour les endpoints API
 */
function apiSecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    // CORS : meme domaine uniquement
    $allowedOrigins = [
        'https://gestion.frenchyconciergerie.fr',
        'http://localhost',
        'http://127.0.0.1',
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }

    // Preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
