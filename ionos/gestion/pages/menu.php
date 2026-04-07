<?php
/**
 * Menu de navigation unifié — FrenchyConciergerie
 * Toutes les pages organisées par catégories
 * Permissions : admin = tout, user = pages assignées en BDD
 */

// Output buffering : permet aux pages d'appeler header() après cet include
if (!ob_get_level()) {
    ob_start();
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/..'));
}
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification de la connexion (compatible ancien + nouveau système)
if (!isset($_SESSION['id_intervenant']) && !isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// Timeout de session (30 min d'inactivite)
$sessionTimeout = 1800;
if (isset($_SESSION['_auth_last_activity']) && (time() - $_SESSION['_auth_last_activity']) > $sessionTimeout) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php?expired=1');
    exit;
}
$_SESSION['_auth_last_activity'] = time();

$id_intervenant  = $_SESSION['id_intervenant'] ?? $_SESSION['user_id'] ?? 0;
$role            = $_SESSION['role'] ?? (in_array($_SESSION['user_role'] ?? '', ['gestionnaire', 'super_admin']) ? 'admin' : 'user');
$nom_utilisateur = $_SESSION['nom_utilisateur'] ?? $_SESSION['user_nom'] ?? 'Compte';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Charger le systeme i18n
if (file_exists(__DIR__ . '/../includes/i18n.php')) {
    require_once __DIR__ . '/../includes/i18n.php';
}

// Pages accessibles depuis la BDD (compatible ancien + nouveau système)
$pages_accessibles = [];
try {
    if ($role === 'admin') {
        $stmt = $conn->query("SELECT id, nom, chemin FROM pages WHERE afficher_menu = 1");
        $pages_accessibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Essayer d'abord le nouveau système (user_permissions)
        $user_id = $_SESSION['user_id'] ?? null;
        if ($user_id) {
            try {
                $stmt = $conn->prepare(
                    "SELECT p.id, p.nom, p.chemin
                     FROM pages p
                     INNER JOIN user_permissions up ON p.id = up.page_id
                     WHERE up.user_id = :user_id
                       AND p.afficher_menu = 1"
                );
                $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->execute();
                $pages_accessibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // Table user_permissions n'existe pas — essayer l'ancien système
            }
        }

        // Fallback : ancien système (intervenants_pages)
        if (empty($pages_accessibles) && $id_intervenant) {
            try {
                $stmt = $conn->prepare(
                    "SELECT p.id, p.nom, p.chemin
                     FROM pages p
                     INNER JOIN intervenants_pages ip ON p.id = ip.page_id
                     WHERE ip.intervenant_id = :id_intervenant
                       AND p.afficher_menu = 1"
                );
                $stmt->bindValue(':id_intervenant', $id_intervenant, PDO::PARAM_INT);
                $stmt->execute();
                $pages_accessibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // Table intervenants_pages n'existe pas non plus — pas de permissions
            }
        }
    }
} catch (PDOException $e) {
    error_log('Erreur BD dans menu.php : ' . $e->getMessage());
}

$currentFile = basename($_SERVER['PHP_SELF']);

// Set de noms de fichiers accessibles pour vérification rapide
$fichiers_accessibles = array_map(fn($p) => basename($p['chemin']), $pages_accessibles);

/**
 * Extrait le nom de fichier d'un chemin (sans query string)
 */
function extractBasename(string $chemin): string {
    $path = parse_url($chemin, PHP_URL_PATH) ?: $chemin;
    return basename($path);
}

/**
 * Vérifie si l'utilisateur a accès à une page
 */
function userCanAccess(string $chemin, string $role, array $fichiers_accessibles): bool {
    if ($role === 'admin') return true;
    return in_array(extractBasename($chemin), $fichiers_accessibles);
}

// ============================================
// DÉFINITION DES CATÉGORIES DU MENU (centralisée)
// ============================================
require_once __DIR__ . '/menu_categories.php';

// Pages déjà listées dans les catégories (pour éviter les doublons)
$pages_dans_categories = [];
foreach ($menu_categories as $cat) {
    foreach ($cat['items'] as $item) {
        $pages_dans_categories[] = extractBasename($item['chemin']);
    }
}

// Pages dynamiques de la BDD qui ne sont dans aucune catégorie
$pages_hors_categories = [];
foreach ($pages_accessibles as $page) {
    $bn = basename($page['chemin']);
    if (!in_array($bn, $pages_dans_categories)) {
        $pages_hors_categories[] = $page;
    }
}
?>

<!-- Bootstrap 5 + FontAwesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/menu.css?v=2">
<script>
// Apply theme immediately to prevent flash
(function(){var t=localStorage.getItem('fc_theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');})();
</script>

<!-- Sidebar -->
<aside class="fc-sidebar" id="fcSidebar">
    <div class="fc-sidebar-header">
        <a href="<?= BASE_URL ?>index.php">
            <i class="fas fa-bolt"></i>
            <span>Frenchy</span>
        </a>
    </div>

    <nav class="fc-sidebar-nav">
        <a class="fc-nav-link <?= $currentFile === 'index.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>index.php">
            <i class="fas fa-tachometer-alt"></i><span>Accueil</span>
        </a>

        <?php foreach ($menu_categories as $categorie_nom => $categorie): ?>
            <?php
            $items_visibles = [];
            $cat_active = false;
            foreach ($categorie['items'] as $item) {
                if (userCanAccess($item['chemin'], $role, $fichiers_accessibles)) {
                    $items_visibles[] = $item;
                    if (extractBasename($item['chemin']) === $currentFile) {
                        $cat_active = true;
                    }
                }
            }
            if (empty($items_visibles)) continue;
            ?>
            <div class="fc-nav-group <?= $cat_active ? 'open' : '' ?>">
                <div class="fc-nav-group-toggle" onclick="fcToggleGroup(this)">
                    <span><i class="fas <?= $categorie['icon'] ?>"></i> <?= $categorie_nom ?></span>
                    <i class="fas fa-chevron-right fc-chevron"></i>
                </div>
                <div class="fc-nav-group-items">
                    <?php foreach ($items_visibles as $item):
                        $url = BASE_URL . ltrim($item['chemin'], '/');
                        $isActive = (extractBasename($item['chemin']) === $currentFile);
                    ?>
                        <a class="fc-nav-link <?= $isActive ? 'active' : '' ?>" href="<?= htmlspecialchars($url) ?>">
                            <i class="fas <?= $item['icon'] ?>"></i><span><?= htmlspecialchars($item['nom']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!empty($pages_hors_categories)): ?>
            <div class="fc-nav-group">
                <div class="fc-nav-group-toggle" onclick="fcToggleGroup(this)">
                    <span><i class="fas fa-ellipsis-h"></i> Autres</span>
                    <i class="fas fa-chevron-right fc-chevron"></i>
                </div>
                <div class="fc-nav-group-items">
                    <?php foreach ($pages_hors_categories as $page):
                        $chemin = $page['chemin'];
                        if (!str_starts_with($chemin, 'pages/') && file_exists(BASE_PATH . '/pages/' . basename($chemin))) {
                            $chemin = 'pages/' . basename($chemin);
                        }
                        $url = BASE_URL . ltrim($chemin, '/');
                        $isActive = (basename($chemin) === $currentFile);
                    ?>
                        <a class="fc-nav-link <?= $isActive ? 'active' : '' ?>" href="<?= htmlspecialchars($url) ?>">
                            <span><?= htmlspecialchars($page['nom']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </nav>

    <div class="fc-sidebar-footer">
        <button class="fc-dark-toggle" onclick="fcToggleDark()" title="Mode sombre">
            <i class="fas fa-moon" id="fcDarkIcon"></i>
        </button>
        <a href="<?= BASE_URL ?>profil.php" class="fc-user-block">
            <i class="fas fa-user-circle"></i>
            <span><?= htmlspecialchars($nom_utilisateur) ?></span>
            <?php if ($role === 'admin'): ?>
                <span class="fc-badge-admin">Admin</span>
            <?php endif; ?>
        </a>
    </div>
</aside>

<!-- Top Bar -->
<header class="fc-topbar" id="fcTopbar">
    <button class="fc-hamburger" onclick="fcToggleSidebar()" aria-label="Menu">
        <i class="fas fa-bars"></i>
    </button>
    <!-- Search bar -->
    <div class="fc-topbar-search">
        <form action="<?= BASE_URL ?>pages/search.php" method="GET" class="fc-search-form" id="fcSearchForm">
            <i class="fas fa-search fc-search-icon"></i>
            <input type="text" name="q" class="fc-search-input" id="fcSearchInput"
                   placeholder="Rechercher..." autocomplete="off"
                   aria-label="Recherche globale">
            <kbd class="fc-search-kbd" id="fcSearchKbd">Ctrl+K</kbd>
        </form>
        <div class="fc-search-dropdown" id="fcSearchDropdown" hidden>
            <div class="fc-search-results" id="fcSearchResults"></div>
        </div>
    </div>

    <div class="fc-topbar-right">
        <?php if (function_exists('langSelector')): ?>
            <div class="fc-topbar-item"><?= langSelector() ?></div>
        <?php endif; ?>
        <?php if ($role === 'admin'): ?>
        <div class="fc-topbar-dropdown">
            <button class="fc-topbar-btn" onclick="fcToggleDropdown(this)" title="Administration">
                <i class="fas fa-cog"></i>
            </button>
            <div class="fc-dropdown-menu">
                <a href="<?= BASE_URL ?>pages/admin.php"><i class="fas fa-cog"></i> Administration</a>
                <a href="<?= BASE_URL ?>pages/gestion_utilisateurs.php"><i class="fas fa-users-cog"></i> Utilisateurs & Droits</a>
                <a href="<?= BASE_URL ?>pages/gestion_pages.php"><i class="fas fa-file-circle-plus"></i> Gestion pages</a>
                <a href="<?= BASE_URL ?>pages/intervenants.php"><i class="fas fa-users"></i> Intervenants</a>
            </div>
        </div>
        <?php endif; ?>
        <form action="<?= BASE_URL ?>logout.php" method="post" class="fc-logout-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <button type="submit" class="fc-topbar-btn fc-btn-logout" title="Déconnexion">
                <i class="fas fa-sign-out-alt"></i>
            </button>
        </form>
    </div>
</header>

<div class="fc-sidebar-overlay" id="fcOverlay" onclick="fcToggleSidebar()"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function fcToggleSidebar(){
    document.getElementById('fcSidebar').classList.toggle('open');
    document.getElementById('fcOverlay').classList.toggle('visible');
}
function fcToggleGroup(el){
    el.closest('.fc-nav-group').classList.toggle('open');
    fcSaveNav();
}
function fcSaveNav(){
    var g=[];document.querySelectorAll('.fc-nav-group.open .fc-nav-group-toggle span').forEach(function(s){g.push(s.textContent.trim());});
    localStorage.setItem('fc_nav_open',JSON.stringify(g));
}
function fcRestoreNav(){
    try{var s=JSON.parse(localStorage.getItem('fc_nav_open'));if(s&&Array.isArray(s)){document.querySelectorAll('.fc-nav-group').forEach(function(g){var l=g.querySelector('.fc-nav-group-toggle span');if(l&&s.indexOf(l.textContent.trim())!==-1)g.classList.add('open');});}}catch(e){}
}
function fcToggleDark(){
    var h=document.documentElement,d=h.getAttribute('data-theme')==='dark';
    h.setAttribute('data-theme',d?'light':'dark');
    localStorage.setItem('fc_theme',d?'light':'dark');
    fcUpdateDarkIcon();
}
function fcUpdateDarkIcon(){
    var i=document.getElementById('fcDarkIcon');
    if(i)i.className=document.documentElement.getAttribute('data-theme')==='dark'?'fas fa-sun':'fas fa-moon';
}
function fcToggleDropdown(btn){
    var m=btn.nextElementSibling;m.classList.toggle('show');
    setTimeout(function(){document.addEventListener('click',function h(e){if(!btn.parentElement.contains(e.target)){m.classList.remove('show');document.removeEventListener('click',h);}});},0);
}
document.addEventListener('DOMContentLoaded',function(){fcUpdateDarkIcon();fcRestoreNav();});

// ── Global Search (topbar) ──
(function(){
    var input=document.getElementById('fcSearchInput');
    var dropdown=document.getElementById('fcSearchDropdown');
    var results=document.getElementById('fcSearchResults');
    var form=document.getElementById('fcSearchForm');
    var kbd=document.getElementById('fcSearchKbd');
    if(!input||!dropdown||!results)return;

    var debounceTimer=null;
    var activeIdx=-1;
    var baseUrl='<?= rtrim(BASE_URL, '/') ?>/';

    function show(){dropdown.hidden=false;}
    function hide(){dropdown.hidden=true;activeIdx=-1;}

    input.addEventListener('focus',function(){
        if(input.value.trim().length>=2)show();
        if(kbd)kbd.style.display='none';
    });
    input.addEventListener('blur',function(){
        setTimeout(hide,200);
        if(kbd&&!input.value)kbd.style.display='';
    });

    input.addEventListener('input',function(){
        clearTimeout(debounceTimer);
        var q=input.value.trim();
        if(q.length<2){hide();results.innerHTML='';return;}
        debounceTimer=setTimeout(function(){
            fetch(baseUrl+'api/search_api.php?q='+encodeURIComponent(q))
                .then(function(r){return r.json();})
                .then(function(data){
                    if(!data.results||data.results.length===0){
                        results.innerHTML='<div class="fc-sr-empty">Aucun résultat pour «&nbsp;'+escHtml(q)+'&nbsp;»</div>';
                        show();return;
                    }
                    var html='';
                    data.results.forEach(function(item,i){
                        var icon=item.icon?'<i class="fas '+escHtml(item.icon)+' me-1"></i>':'';
                        var href=item.url.indexOf('http')===0?item.url:baseUrl+item.url;
                        html+='<a class="fc-sr-item" href="'+escHtml(href)+'" data-idx="'+i+'">'
                            +'<span class="fc-sr-badge fc-sr-badge--'+escHtml(item.type)+'">'+escHtml(item.type_label)+'</span>'
                            +'<span class="fc-sr-title">'+icon+escHtml(item.title)+'</span>'
                            +'<span class="fc-sr-sub">'+escHtml(item.subtitle||'')+'</span>'
                            +'</a>';
                    });
                    html+='<a class="fc-sr-all" href="'+baseUrl+'pages/search.php?q='+encodeURIComponent(q)+'"><i class="fas fa-search"></i> Voir tous les résultats</a>';
                    results.innerHTML=html;
                    activeIdx=-1;
                    show();
                })
                .catch(function(){});
        },250);
    });

    // Keyboard nav
    input.addEventListener('keydown',function(e){
        var items=results.querySelectorAll('.fc-sr-item');
        if(e.key==='Escape'){hide();input.blur();return;}
        if(e.key==='ArrowDown'){e.preventDefault();activeIdx=Math.min(activeIdx+1,items.length-1);highlightItem(items);}
        else if(e.key==='ArrowUp'){e.preventDefault();activeIdx=Math.max(activeIdx-1,0);highlightItem(items);}
        else if(e.key==='Enter'&&activeIdx>=0&&items[activeIdx]){e.preventDefault();items[activeIdx].click();}
    });

    function highlightItem(items){
        items.forEach(function(it,i){it.classList.toggle('active',i===activeIdx);});
        if(items[activeIdx])items[activeIdx].scrollIntoView({block:'nearest'});
    }

    // Ctrl+K shortcut
    document.addEventListener('keydown',function(e){
        if((e.ctrlKey||e.metaKey)&&e.key==='k'){e.preventDefault();input.focus();input.select();}
    });

    function escHtml(s){var d=document.createElement('div');d.appendChild(document.createTextNode(s||''));return d.innerHTML;}
})();
</script>
