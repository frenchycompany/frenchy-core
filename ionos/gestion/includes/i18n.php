<?php
/**
 * Systeme d'internationalisation (i18n)
 * Gere les traductions francais/anglais pour le systeme checkup/inventaire
 */

// Detecter la langue : session > parametre GET > defaut
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

$GLOBALS['lang'] = $_SESSION['lang'] ?? 'fr';

// Fichiers de traduction
$GLOBALS['translations'] = [];

function loadTranslations(string $lang): void {
    $file = __DIR__ . '/lang/' . $lang . '.php';
    if (file_exists($file)) {
        $GLOBALS['translations'] = include $file;
    }
}

/**
 * Traduit une cle. Si pas trouvee, retourne la cle elle-meme.
 * Usage : echo __('checkup.title');
 */
function __(string $key, array $params = []): string {
    $text = $GLOBALS['translations'][$key] ?? $key;

    // Remplacement de parametres {:name}
    foreach ($params as $k => $v) {
        $text = str_replace('{:' . $k . '}', $v, $text);
    }

    return $text;
}

/**
 * Retourne la langue courante
 */
function currentLang(): string {
    return $GLOBALS['lang'];
}

/**
 * Genere le selecteur de langue HTML
 */
function langSelector(): string {
    $current = currentLang();
    $currentUrl = $_SERVER['REQUEST_URI'];
    $separator = strpos($currentUrl, '?') !== false ? '&' : '?';

    // Supprimer le parametre lang existant
    $cleanUrl = preg_replace('/[&?]lang=[a-z]{2}/', '', $currentUrl);
    $separator = strpos($cleanUrl, '?') !== false ? '&' : '?';

    $html = '<div class="lang-selector" style="display:inline-flex;gap:4px;align-items:center;">';
    $langs = ['fr' => 'FR', 'en' => 'EN'];
    foreach ($langs as $code => $label) {
        $active = $code === $current;
        $style = $active
            ? 'background:#1976d2;color:#fff;padding:3px 8px;border-radius:4px;text-decoration:none;font-size:0.8em;font-weight:600;'
            : 'background:#e0e0e0;color:#555;padding:3px 8px;border-radius:4px;text-decoration:none;font-size:0.8em;';
        $html .= '<a href="' . htmlspecialchars($cleanUrl . $separator . 'lang=' . $code) . '" style="' . $style . '">' . $label . '</a>';
    }
    $html .= '</div>';
    return $html;
}

// Charger les traductions
loadTranslations($GLOBALS['lang']);
