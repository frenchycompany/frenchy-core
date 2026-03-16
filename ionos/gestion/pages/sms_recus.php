<?php
ini_set('display_errors', 0);
include '../config.php';
// Le backend ne charge plus les données — tout est chargé via l'API AJAX paginée
$view_mode = $_GET['view'] ?? 'conversations';
$show_archived = isset($_GET['archived']) && $_GET['archived'] === '1';
$initial_sender = $_GET['sender'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Reçus — FrenchyConciergerie</title>
</head>
<body>
<?php include '../pages/menu.php'; ?>
?>

<div class="container-fluid mt-4">

<!-- Config JS pour les paramètres PHP -->
<script>
const PAGE_CONFIG = {
    viewMode: <?= json_encode($view_mode) ?>,
    showArchived: <?= json_encode($show_archived) ?>,
    initialSender: <?= json_encode($initial_sender) ?>,
    apiBase: '../api/sms_conversations_api.php',
    searchApiBase: '../api/search_api.php',
    pollInterval: 30000 // 30 secondes
};
</script>

<style>
:root {
    --primary: #007bff;
    --success: #28a745;
    --info: #17a2b8;
    --warning: #ffc107;
    --danger: #dc3545;
    --light: #f8f9fa;
    --dark: #343a40;
  }

  body { background-color: #f4f7f9; }

  .view-toggle {
    display: inline-flex;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    background: white;
  }

  .view-toggle a {
    padding: 8px 16px;
    background: white;
    color: #495057;
    text-decoration: none;
    border-right: 1px solid #dee2e6;
    transition: all 0.2s;
    font-weight: 500;
    font-size: 0.9rem;
  }

  .view-toggle a:last-child { border-right: none; }
  .view-toggle a.active { background: var(--primary); color: white; }
  .view-toggle a:hover:not(.active) { background: #e9ecef; }

  /* Mode Conversations */
  .conversations-container {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 15px;
    height: calc(100vh - 150px);
    max-height: 800px;
  }

  .conversations-list {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    overflow-y: auto;
    max-height: 100%;
    border: 1px solid #e9ecef;
  }

  .conversation-item {
    padding: 12px 15px;
    border-bottom: 1px solid #e9ecef;
    cursor: pointer;
    transition: background 0.15s;
    position: relative;
  }
   .conversation-item:last-child { border-bottom: none; }

  .conversation-item:hover { background: #f8f9fa; }
  .conversation-item.active {
    background: #e7f3ff;
    border-left: 4px solid var(--primary);
    padding-left: 11px;
  }

  .conversation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
  }

  .conversation-sender {
    font-weight: 600;
    color: #343a40;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.95rem;
  }

  .conversation-time {
    font-size: 0.7rem;
    color: #6c757d;
    white-space: nowrap;
  }

  .conversation-preview {
    font-size: 0.85rem;
    color: #6c757d;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.4;
  }
   .conversation-item.active .conversation-preview {
      -webkit-line-clamp: 2;
   }

  .unread-badge {
    background: var(--primary);
    color: white;
    border-radius: 50%;
    min-width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1;
    margin-left: 5px;
  }

  .reservation-badge {
    font-size: 0.75rem;
    font-weight: 600;
    margin-right: 5px;
    vertical-align: middle;
    padding: .2em .4em;
    border-radius: .25rem;
  }
   .reservation-info-small {
       color: var(--success);
       font-weight: 500;
       display: block;
       margin-top: 4px;
       font-size: 0.75rem;
       white-space: nowrap;
       overflow: hidden;
       text-overflow: ellipsis;
   }

  .messages-panel {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #e9ecef;
  }

  .messages-header {
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
    background: #ffffff;
    color: #343a40;
  }

  .messages-header h4 { margin: 0; font-size: 1.1rem; }
  .messages-info { font-size: 0.8rem; opacity: 0.8; margin-top: 5px; }
  .messages-info a { font-size: 0.8rem; padding: 2px 6px; }

  .messages-body {
    flex: 1;
    padding: 15px;
    overflow-y: auto;
    background: #f8f9fa;
  }

  .message-bubble {
    padding: 10px 14px;
    border-radius: 18px;
    margin-bottom: 10px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    max-width: 75%;
    position: relative;
    line-height: 1.4;
  }

  .message-bubble.received {
    background: #e9ecef;
    color: #343a40;
    margin-right: auto;
    border-bottom-left-radius: 4px;
  }

  .message-bubble.sent {
    margin-left: auto;
    background: var(--primary);
    color: white;
    border-bottom-right-radius: 4px;
  }

  .message-meta {
    font-size: 0.65rem;
    color: #6c757d;
    margin-top: 5px;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 5px;
  }

  .message-bubble.sent .message-meta { color: rgba(255,255,255,0.7); }
  .message-bubble.received .message-meta { color: #6c757d; }

  .message-text {
    white-space: pre-wrap;
    word-wrap: break-word;
    font-size: 0.9rem;
  }

  .message-text.raw-pdu {
    font-family: monospace;
    font-size: 0.75rem;
    color: #dc3545;
    word-break: break-all;
  }

  .messages-footer {
    padding: 10px 15px;
    border-top: 1px solid #e9ecef;
    background: #f8f9fa;
  }

  .reply-form { display: flex; gap: 8px; }

  .reply-input {
    flex: 1;
    padding: 8px 15px;
    border: 1px solid #ced4da;
    border-radius: 20px;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    font-size: 0.9rem;
  }

  .reply-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.1rem rgba(0, 123, 255, 0.25);
  }

  .send-btn {
    padding: 8px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 20px;
    cursor: pointer;
    font-weight: 500;
    font-size: 0.9rem;
    transition: background-color 0.2s;
  }

  .send-btn:hover { background-color: #0056b3; }

  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #adb5bd;
    text-align: center;
    padding: 20px;
  }

  .empty-state-icon { font-size: 3rem; margin-bottom: 0.8rem; opacity: 0.4; }
  .empty-state h4 { font-size: 1.1rem; color: #6c757d; }
  .empty-state p { font-size: 0.9rem; }

  /* Mode Liste */
  .toolbar {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    padding: 15px;
    border: 1px solid #e9ecef;
  }
  .toolbar .form-group { margin-bottom: 0; }

  .table-responsive {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    overflow: hidden;
    border: 1px solid #e9ecef;
  }
  .table { margin-bottom: 0; }

  .table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f8f9fa !important;
    color: #495057;
    border-bottom-width: 2px !important;
    font-size: 0.85rem;
    font-weight: 600;
    white-space: nowrap;
  }
   .table tbody tr:hover { background-color: #f8f9fa; }
   .table td, .table th { vertical-align: middle !important; padding: 0.6rem 0.75rem; }
   .table td { font-size: 0.85rem; }

  .message-cell { max-width: 450px; white-space: normal; }
  .message-preview {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    font-family: inherit;
    font-size: inherit;
    line-height: 1.4;
  }

  .message-preview.raw-pdu {
    font-family: monospace;
    font-size: 0.75rem;
    color: #dc3545;
    word-break: break-all;
  }

  .nowrap { white-space: nowrap; }
  .copy-btn {
      border: 0;
      background: transparent;
      cursor: pointer;
      padding: 0 4px;
      font-size: 0.8rem;
      color: #6c757d;
      opacity: 0.6;
      transition: opacity 0.2s;
   }
   .copy-btn:hover { opacity: 1; }

  .action-buttons { display: flex; gap: 5px; flex-wrap: nowrap; }
  .action-buttons .btn { padding: 0.2rem 0.5rem; font-size: 0.75rem; }

  .badge { font-weight: 500; }

  ::-webkit-scrollbar { width: 6px; height: 6px;}
  ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 3px;}
  ::-webkit-scrollbar-thumb { background: #ced4da; border-radius: 3px;}
  ::-webkit-scrollbar-thumb:hover { background: #adb5bd;}

  .pagination .page-link { font-size: 0.85rem; padding: 0.4rem 0.7rem; }

  @media (max-width: 992px) {
    .conversations-container {
      grid-template-columns: 1fr;
      grid-template-rows: auto 1fr;
      height: auto;
      max-height: none;
    }
    .conversations-list { max-height: 300px; }
    .messages-panel { min-height: 400px; }
  }
  @media (max-width: 768px) {
      .toolbar .form-row > div { margin-bottom: 10px; }
      .toolbar .form-row .col-md-2 { width: 50%; }
      .toolbar .form-row .col-md-4 { width: 100%; }
      .action-buttons .btn-outline-primary { display: none; }
  }

  /* Panneau suggestions IA */
  .ai-suggestions {
    padding: 10px 15px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-top: 1px solid #e9ecef;
    border-bottom: 1px solid #e9ecef;
  }

  .ai-suggestions-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-size: 0.8rem;
    color: #6c757d;
    font-weight: 500;
  }

  .ai-suggestions-header i {
    color: #667eea;
  }

  .ai-suggestions-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .ai-suggestion-btn {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 20px;
    padding: 6px 14px;
    font-size: 0.8rem;
    color: #495057;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
    max-width: 100%;
  }

  .ai-suggestion-btn:hover {
    background: #667eea;
    border-color: #667eea;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
  }

  .ai-suggestion-btn .suggestion-label {
    font-weight: 500;
    white-space: nowrap;
  }

  .ai-suggestion-btn .suggestion-preview {
    color: #adb5bd;
    font-size: 0.75rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 150px;
  }

  .ai-suggestion-btn:hover .suggestion-preview {
    color: rgba(255,255,255,0.7);
  }

  .ai-suggestions-loading {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #6c757d;
    font-size: 0.8rem;
  }

  .ai-suggestions-empty {
    color: #adb5bd;
    font-size: 0.8rem;
    font-style: italic;
  }

  .confidence-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
  }

  .confidence-high { background: #28a745; }
  .confidence-medium { background: #ffc107; }
  .confidence-low { background: #dc3545; }

  /* Actions de conversation */
  .conversation-actions {
    display: flex;
    gap: 5px;
  }

  .conversation-actions .btn {
    padding: 4px 8px;
    font-size: 0.8rem;
  }

  /* Badge messages non lus */
  .unread-indicator {
    width: 10px;
    height: 10px;
    background: var(--primary);
    border-radius: 50%;
    display: inline-block;
    margin-right: 5px;
    animation: pulse 2s infinite;
  }

  @keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
  }

  .conversation-item.has-unread {
    background: linear-gradient(to right, #e7f3ff, white);
  }

  .conversation-item.has-unread .conversation-sender {
    font-weight: 700;
  }

  /* Direction du message (envoyé vs reçu) */
  .direction-indicator {
    font-size: 0.7rem;
    color: #6c757d;
    margin-right: 3px;
  }

  .direction-indicator.out { color: var(--primary); }
  .direction-indicator.in { color: var(--success); }

  /* Style archives */
  .archived-view .conversation-item {
    opacity: 0.8;
  }

  .archived-view .conversation-item:hover {
    opacity: 1;
  }
</style>

<!-- Header de page -->
<div class="row mb-4">
    <div class="col-md-6">
        <h1 class="display-4">
            <i class="fas fa-inbox text-primary"></i> SMS <span id="pageTitle"><?= $show_archived ? 'Archives' : 'Recus' ?></span>
        </h1>
        <p class="lead text-muted" id="pageSubtitle">
            <?= $show_archived ? 'Conversations archivees' : 'Consultez et repondez aux messages' ?>
        </p>
    </div>
    <div class="col-md-6 d-flex align-items-center justify-content-end">
      <div class="d-flex align-items-center gap-3">
        <!-- Indicateur auto-refresh -->
        <span id="pollIndicator" class="badge badge-light" style="font-size: 0.75rem; opacity: 0.6;" title="Auto-refresh actif">
          <i class="fas fa-sync-alt fa-spin" style="display:none;" id="pollSpinner"></i>
          <span id="pollStatus">Auto-refresh</span>
        </span>

        <div class="view-toggle">
          <a href="?view=conversations<?= $show_archived ? '&archived=1' : '' ?>" class="<?= $view_mode === 'conversations' ? 'active' : '' ?>">
            <i class="fas fa-comments"></i> Conversations
          </a>
          <a href="?view=list<?= $show_archived ? '&archived=1' : '' ?>" class="<?= $view_mode === 'list' ? 'active' : '' ?>">
            <i class="fas fa-list"></i> Liste
          </a>
        </div>

        <?php if ($view_mode === 'conversations'): ?>
        <div class="view-toggle">
          <a href="?view=conversations" class="<?= !$show_archived ? 'active' : '' ?>">
            <i class="fas fa-inbox"></i> Actifs
          </a>
          <a href="?view=conversations&archived=1" class="<?= $show_archived ? 'active' : '' ?>">
            <i class="fas fa-archive"></i> Archives
            <span class="badge badge-secondary ml-1" id="archivedCount" style="display:none;"></span>
          </a>
        </div>
        <?php endif; ?>

        <span id="totalBadge" class="badge badge-primary" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
          <i class="fas fa-spinner fa-spin"></i> Chargement...
        </span>
      </div>
    </div>
</div>

<?php if ($view_mode === 'conversations'): ?>
    <div class="conversations-container">
      <div class="conversations-list">
        <div style="padding: 10px; border-bottom: 1px solid #e9ecef; background: #f8f9fa; position: sticky; top: 0; z-index: 1;">
          <div class="input-group input-group-sm">
            <div class="input-group-prepend">
              <span class="input-group-text"><i class="fas fa-search"></i></span>
            </div>
            <input id="conversationSearch" type="text" class="form-control" placeholder="Rechercher num, nom, message...">
          </div>
        </div>
        <div id="conversationsList">
          <div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>
        </div>
        <!-- Pagination conversations -->
        <div id="convPagination" style="padding: 8px; border-top: 1px solid #e9ecef; display:none;">
          <nav><ul class="pagination pagination-sm justify-content-center mb-0" id="convPager"></ul></nav>
        </div>
      </div>

      <div class="messages-panel">
        <div id="messagesContent" class="empty-state">
          <div class="empty-state-icon"><i class="fas fa-comments fa-4x text-muted"></i></div>
          <h4>Sélectionnez une conversation</h4>
          <p>Choisissez un contact dans la liste pour voir les messages.</p>
        </div>
      </div>
    </div>

    <?php else: ?>
    <div class="toolbar mb-3">
       <div class="form-row align-items-end">
         <div class="form-group col-md-4 mb-md-0">
           <label for="searchInput" class="sr-only">Recherche</label>
           <input id="searchInput" type="text" class="form-control form-control-sm" placeholder="Rechercher expediteur, message...">
         </div>
         <div class="form-group col-md-2 mb-md-0">
           <label for="modemFilter" class="sr-only">Modem</label>
           <select id="modemFilter" class="form-control form-control-sm">
             <option value="">Tous les modems</option>
           </select>
         </div>
         <div class="form-group col-md-2 mb-md-0">
            <label for="dateFrom" class="sr-only">Du</label>
            <input id="dateFrom" type="date" class="form-control form-control-sm">
         </div>
         <div class="form-group col-md-2 mb-md-0">
            <label for="dateTo" class="sr-only">Au</label>
            <input id="dateTo" type="date" class="form-control form-control-sm">
         </div>
         <div class="form-group col-md-2 mb-md-0">
           <button id="resetFilters" class="btn btn-sm btn-outline-secondary btn-block">Réinitialiser</button>
         </div>
       </div>
    </div>

    <div class="table-responsive">
      <table id="smsTable" class="table table-striped table-hover table-sm">
        <thead class="thead-light">
          <tr>
            <th class="nowrap">#</th>
            <th class="nowrap">Expéditeur</th>
            <th>Message</th>
            <th class="nowrap">Date Reçu</th>
            <th class="nowrap">Modem</th>
            <th class="nowrap">Réservation</th>
            <th class="nowrap">Actions</th>
          </tr>
        </thead>
        <tbody id="smsTableBody">
          <tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin"></i> Chargement...</td></tr>
        </tbody>
      </table>
    </div>

    <nav class="mt-3"><ul id="pager" class="pagination pagination-sm justify-content-center"></ul></nav>
    <?php endif; ?>

<div class="modal fade" id="msgModal" tabindex="-1" aria-labelledby="msgModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="msgModalLabel">Message</h5>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <pre id="msgFull" class="mb-0" style="white-space:pre-wrap;word-wrap:break-word; font-family: inherit; font-size: inherit;"></pre>
      </div>
      <div class="modal-footer">
        <button id="copyFullBtn" type="button" class="btn btn-secondary">Copier</button>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<script>
// ═══════════════════════════════════════════════════════════════════════════════
// SMS Recus — Client JS avec pagination serveur + auto-refresh
// Utilise api/sms_conversations_api.php et api/search_api.php
// ═══════════════════════════════════════════════════════════════════════════════

const $ = (q, ctx = document) => ctx.querySelector(q);
const $$ = (q, ctx = document) => Array.from(ctx.querySelectorAll(q));
const debounced = (fn, d = 250) => {
    let t;
    return (...a) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...a), d);
    }
};

let toastTimeout;
const showToast = (msg, type = 'success') => {
    clearTimeout(toastTimeout);
    let toast = $('#toast-notification');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast-notification';
        toast.style.cssText = 'position:fixed; bottom:20px; right:20px; padding:12px 20px; border-radius:6px; z-index:9999; box-shadow:0 4px 12px rgba(0,0,0,0.15); transition: opacity 0.3s; opacity: 0; font-weight: 500;';
        document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.style.background = type === 'success' ? '#28a745' : (type === 'error' ? '#dc3545' : '#6c757d');
    toast.style.color = 'white';
    toast.style.opacity = 1;
    toastTimeout = setTimeout(() => { toast.style.opacity = 0; }, 2500);
};

const copyText = async (txt) => {
    const textToCopy = txt || '';
    if (!textToCopy.trim()) {
        showToast('Rien à copier', 'warning');
        return;
    }
    try {
        await navigator.clipboard.writeText(textToCopy);
        showToast('Copié ! 👍');
    } catch (e) {
        try {
            const ta = document.createElement('textarea');
            ta.style.cssText = 'position:absolute; left:-9999px;';
            ta.value = textToCopy;
            document.body.appendChild(ta);
            ta.select();
            ta.setSelectionRange(0, 99999);
            document.execCommand('copy');
            ta.remove();
            showToast('Copié (fallback) ! 👍');
        } catch(fallbackError) {
             console.error("Copy failed: ", e, fallbackError);
             showToast('Erreur de copie', 'error');
        }
    }
};

function formatRelativeTimeJS(dateString) {
    if (!dateString) return '';
    try {
        const normalized = dateString.includes('T') ? dateString : dateString.replace(' ', 'T') + 'Z';
        const date = new Date(normalized);
        if (isNaN(date)) throw new Error('Invalid Date');
        const now = new Date();

        const localDate = new Date(date.toLocaleString('en-US', { timeZone: "Europe/Paris" }));
        const localNow = new Date(now.toLocaleString('en-US', { timeZone: "Europe/Paris" }));

        const diffSeconds = Math.round((now - date) / 1000);
        const diffMinutes = Math.round(diffSeconds / 60);
        const diffHours = Math.round(diffMinutes / 60);

        const isToday = localDate.toDateString() === localNow.toDateString();
        const isYesterday = new Date(localNow.getTime() - 86400000).toDateString() === localDate.toDateString();

        if (diffSeconds < 60) return 'maintenant';
        if (diffMinutes < 60) return `${diffMinutes} min`;
        if (isToday) return date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: "Europe/Paris" });
        if (isYesterday) return 'Hier ' + date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: "Europe/Paris" });
        if (diffHours / 24 < 7) {
             const days = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
             const browserLocalDate = new Date(dateString.replace(' ', 'T'));
             return `${days[browserLocalDate.getDay()]} ${browserLocalDate.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`;
        }
        return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: "Europe/Paris" });
    } catch(e) {
        console.error("Date formatting error for:", dateString, e);
        return dateString;
    }
}

function formatDateJS(dateString, formatOptions = { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) {
    if (!dateString) return '';
     try {
         const normalized = dateString.includes('T') ? dateString : dateString.replace(' ', 'T') + 'Z';
        const date = new Date(normalized);
         if (isNaN(date)) throw new Error('Invalid Date');
         return date.toLocaleString('fr-FR', { timeZone: "Europe/Paris", ...formatOptions });
     } catch(e) {
         return dateString;
     }
}

// Escape strings for safe use in template literals
// Escape strings for safe use in template literals
function escapeHTML(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/`/g, '&#96;') // Corrigé
        .replace(/\$/g, '&#36;'); // Corrigé
}

// Escape for HTML attribute values
function escapeAttr(str) {
    if (!str) return '';
    return String(str)
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/`/g, '&#96;') // Corrigé
        .replace(/\$/g, '&#36;'); // Corrigé
}

// Create DOM elements safely without template literals
function createMessageBubble(message, direction, date, status) {
    const bubble = document.createElement('div');
    bubble.className = `message-bubble ${direction}`;

    const textDiv = document.createElement('div');
    textDiv.className = 'message-text';
    textDiv.textContent = message; // textContent auto-escapes

    const metaDiv = document.createElement('div');
    metaDiv.className = 'message-meta';

    if (direction === 'sent') {
        const statusSpan = document.createElement('span');
        if (status === 'sent' || status === 'SendingOK' || status === 'DeliveryOK') {
            statusSpan.title = 'Envoyé';
            statusSpan.textContent = '✓✓';
        } else if (status === 'failed' || status === 'SendingError' || status === 'DeliveryFailed') {
            statusSpan.title = 'Échec';
            statusSpan.textContent = '❌';
        } else {
            statusSpan.title = 'En attente';
            statusSpan.textContent = '⏳';
        }
        metaDiv.appendChild(statusSpan);
    }

    const dateSpan = document.createElement('span');
    dateSpan.title = formatDateJS(date);
    dateSpan.textContent = formatRelativeTimeJS(date);
    metaDiv.appendChild(dateSpan);

    const copyBtn = document.createElement('button');
    copyBtn.className = 'copy-btn';
    copyBtn.title = 'Copier';
    copyBtn.dataset.copy = message;
    copyBtn.textContent = '📋';
    metaDiv.appendChild(copyBtn);

    bubble.appendChild(textDiv);
    bubble.appendChild(metaDiv);

    return bubble;
}
// ─────────────────────────────────────────────────────────────────────────────
// State
// ─────────────────────────────────────────────────────────────────────────────
let conversationsData = [];
let currentReservation = null;
let currentConvPage = 1;
let currentListPage = 1;
let lastPollTime = new Date().toISOString().replace('T', ' ').substring(0, 19);
let pollTimer = null;
let currentActiveSender = null;

document.addEventListener('DOMContentLoaded', () => {
    // Delegation globale pour copy/view buttons
    document.body.addEventListener('click', (e) => {
        const copyBtn = e.target.closest('.copy-btn');
        if (copyBtn && copyBtn.dataset.copy) {
            e.preventDefault();
            copyText(copyBtn.dataset.copy);
        }
        const viewBtn = e.target.closest('.view-btn');
        if (viewBtn) {
            e.preventDefault();
            $('#msgModalLabel').textContent = viewBtn.dataset.title || 'Message';
            $('#msgFull').textContent = viewBtn.dataset.full || '';
            $('#copyFullBtn').dataset.copy = viewBtn.dataset.full || '';
            const msgModalEl = document.getElementById('msgModal');
            if (msgModalEl) bootstrap.Modal.getOrCreateInstance(msgModalEl).show();
        }
    });

    const copyFullBtn = $('#copyFullBtn');
    if (copyFullBtn) copyFullBtn.addEventListener('click', () => copyText(copyFullBtn.dataset.copy));

    if (PAGE_CONFIG.viewMode === 'conversations') {
        loadConversations(1);
        initConversationSearch();
    } else {
        loadListView(1);
        initListFilters();
    }

    // Démarrer auto-refresh
    startPolling();
});

// ─────────────────────────────────────────────────────────────────────────────
// Auto-refresh (polling)
// ─────────────────────────────────────────────────────────────────────────────
function startPolling() {
    pollTimer = setInterval(async () => {
        const spinner = $('#pollSpinner');
        if (spinner) spinner.style.display = 'inline-block';
        try {
            const resp = await fetch(PAGE_CONFIG.apiBase + '?action=poll&since=' + encodeURIComponent(lastPollTime));
            const data = await resp.json();
            if (data.server_time) lastPollTime = data.server_time;

            if ((data.new_in || 0) + (data.new_out || 0) > 0) {
                // Refresh la vue active
                if (PAGE_CONFIG.viewMode === 'conversations') {
                    loadConversations(currentConvPage, true);
                    // Si une conversation est ouverte, ajouter les nouveaux messages
                    if (currentActiveSender && data.messages) {
                        const normSender = currentActiveSender.replace(/^\+33/, '0').replace(/[^0-9]/g, '');
                        const newMsgs = data.messages.filter(m => {
                            const normPhone = (m.phone || '').replace(/^\+33/, '0').replace(/[^0-9]/g, '');
                            return normPhone === normSender;
                        });
                        if (newMsgs.length > 0) appendNewMessages(newMsgs);
                    }
                } else {
                    loadListView(currentListPage, true);
                }

                // Notification visuelle
                const pollStatus = $('#pollStatus');
                if (pollStatus) {
                    pollStatus.textContent = `+${data.new_in + data.new_out} nouveau(x)`;
                    setTimeout(() => { pollStatus.textContent = 'Auto-refresh'; }, 3000);
                }
            }
        } catch (e) {
            console.error('Poll error:', e);
        } finally {
            if (spinner) spinner.style.display = 'none';
        }
    }, PAGE_CONFIG.pollInterval);
}

function appendNewMessages(messages) {
    const body = $('#messagesBody');
    if (!body) return;
    messages.forEach(msg => {
        const direction = msg.direction === 'in' ? 'received' : 'sent';
        const bubble = createMessageBubble(String(msg.message || ''), direction, msg.date, msg.status || 'pending');
        body.appendChild(bubble);
    });
    body.scrollTop = body.scrollHeight;
}

// ─────────────────────────────────────────────────────────────────────────────
// Vue CONVERSATIONS (paginée côté serveur)
// ─────────────────────────────────────────────────────────────────────────────
async function loadConversations(page = 1, silent = false) {
    currentConvPage = page;
    const list = $('#conversationsList');
    const search = ($('#conversationSearch') || {}).value || '';

    if (!silent) list.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>';

    try {
        const params = new URLSearchParams({
            action: 'conversations',
            page: page,
            per_page: 30,
            archived: PAGE_CONFIG.showArchived ? '1' : '0',
            search: search
        });
        const resp = await fetch(PAGE_CONFIG.apiBase + '?' + params);
        const data = await resp.json();

        conversationsData = data.conversations || [];

        // Update badge
        const badge = $('#totalBadge');
        if (badge) badge.innerHTML = '<i class="fas fa-comment-dots"></i> ' + data.total + ' conversations';

        // Update archived count
        const arcBadge = $('#archivedCount');
        if (arcBadge && data.archived_count > 0) {
            arcBadge.textContent = data.archived_count;
            arcBadge.style.display = '';
        }

        renderConversationsList(conversationsData);
        renderPagination($('#convPager'), data.total_pages, page, (p) => loadConversations(p));

        const paginationEl = $('#convPagination');
        if (paginationEl) paginationEl.style.display = data.total_pages > 1 ? '' : 'none';

        // Rouvrir la conversation active
        if (PAGE_CONFIG.initialSender && !currentActiveSender) {
            selectConversation(PAGE_CONFIG.initialSender);
        } else if (currentActiveSender) {
            highlightActiveConversation(currentActiveSender);
        }
    } catch (e) {
        list.innerHTML = '<div class="text-center text-danger p-3">Erreur chargement</div>';
        console.error('loadConversations:', e);
    }
}

function renderConversationsList(conversations) {
    const list = $('#conversationsList');
    if (!conversations.length) {
        list.innerHTML = '<p class="text-center text-muted p-3">Aucune conversation trouvee.</p>';
        return;
    }

    list.innerHTML = '';
    conversations.forEach((conv, idx) => {
        const is_pdu = /^(?:,145,|,145,6[48],|,145,4,)|(^[0-9A-F]{10,})/i.test(conv.last_message || '');
        const preview = is_pdu ? '[Message non decode]' : (conv.last_message || '');

        const item = document.createElement('div');
        item.className = 'conversation-item';
        item.dataset.sender = conv.phone;
        item.dataset.index = idx;

        let senderHtml = escapeHTML(conv.phone);
        if (conv.reservation) {
            senderHtml = '<span class="badge badge-success reservation-badge" title="' +
                escapeAttr(conv.reservation.nom_du_logement || '') + '">Res.</span> ' + senderHtml;
        }

        let resInfo = '';
        if (conv.reservation) {
            resInfo = '<div class="reservation-info-small">' +
                escapeHTML(conv.reservation.client_name || 'Client inconnu') +
                ' (Res. #' + conv.reservation.id + ')</div>';
        }

        item.innerHTML =
            '<div class="conversation-header">' +
                '<div class="conversation-sender">' + senderHtml + '</div>' +
                '<div><span class="conversation-time" title="' + escapeAttr(formatDateJS(conv.last_date)) + '">' +
                    formatRelativeTimeJS(conv.last_date) + '</span></div>' +
            '</div>' +
            '<div class="conversation-preview' + (is_pdu ? ' raw-pdu' : '') + '">' + escapeHTML(preview) + '</div>' +
            resInfo;

        item.addEventListener('click', () => {
            $$('#conversationsList .conversation-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            currentActiveSender = conv.phone;
            currentReservation = conv.reservation;

            const url = new URL(window.location);
            url.searchParams.set('sender', conv.phone);
            window.history.pushState({}, '', url);

            loadAndDisplayConversation(conv.phone, conv.reservation);
        });

        list.appendChild(item);
    });
}

function highlightActiveConversation(sender) {
    $$('#conversationsList .conversation-item').forEach(item => {
        item.classList.toggle('active', item.dataset.sender === sender);
    });
}

function selectConversation(sender) {
    const item = $$('#conversationsList .conversation-item').find(i => i.dataset.sender === sender);
    if (item) {
        item.click();
        item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

function initConversationSearch() {
    const input = $('#conversationSearch');
    if (!input) return;
    input.addEventListener('input', debounced(() => {
        currentConvPage = 1;
        loadConversations(1);
    }, 400));
}

// ─────────────────────────────────────────────────────────────────────────────
// Vue LISTE (paginée côté serveur)
// ─────────────────────────────────────────────────────────────────────────────
async function loadListView(page = 1, silent = false) {
    currentListPage = page;
    const tbody = $('#smsTableBody');
    if (!silent && tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin"></i> Chargement...</td></tr>';

    const params = new URLSearchParams({
        action: 'list',
        page: page,
        per_page: 25,
        search: ($('#searchInput') || {}).value || '',
        modem: ($('#modemFilter') || {}).value || '',
        date_from: ($('#dateFrom') || {}).value || '',
        date_to: ($('#dateTo') || {}).value || ''
    });

    try {
        const resp = await fetch(PAGE_CONFIG.apiBase + '?' + params);
        const data = await resp.json();

        const badge = $('#totalBadge');
        if (badge) badge.innerHTML = '<i class="fas fa-envelope"></i> ' + data.total + ' messages';

        // Remplir les modems du filtre
        const modemSelect = $('#modemFilter');
        if (modemSelect && modemSelect.options.length <= 1 && data.modems) {
            data.modems.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m;
                opt.textContent = m;
                modemSelect.appendChild(opt);
            });
        }

        renderListTable(data.sms_list || []);
        renderPagination($('#pager'), data.total_pages, page, (p) => loadListView(p));
    } catch (e) {
        if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Erreur chargement</td></tr>';
        console.error('loadListView:', e);
    }
}

function renderListTable(smsList) {
    const tbody = $('#smsTableBody');
    if (!tbody) return;

    if (!smsList.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Aucun message recu trouve.</td></tr>';
        return;
    }

    tbody.innerHTML = '';
    smsList.forEach(sms => {
        const is_pdu = /^(?:,145,|,145,6[48],|,145,4,)|(^[0-9A-F]{10,})/i.test(sms.message || '');
        const preview = is_pdu ? '[Message non decode]' : (sms.message || '');
        const fullText = is_pdu ? '[Message non decode]\n\nDonnees brutes:\n' + sms.message : sms.message;
        const resa = sms.reservation;

        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td class="nowrap">' + sms.id + '</td>' +
            '<td class="nowrap">' + escapeHTML(sms.sender) +
                ' <button class="copy-btn" title="Copier" data-copy="' + escapeAttr(sms.sender) + '">&#x1F4CB;</button></td>' +
            '<td class="message-cell"><div class="message-preview' + (is_pdu ? ' raw-pdu' : '') + '">' + escapeHTML(preview) + '</div></td>' +
            '<td class="nowrap" title="' + escapeAttr(sms.received_at) + '">' + formatDateJS(sms.received_at) + '</td>' +
            '<td class="nowrap">' + (sms.modem ? '<span class="badge badge-info">' + escapeHTML(sms.modem) + '</span>' : '<span class="badge badge-light">-</span>') + '</td>' +
            '<td class="nowrap">' + (resa ? '<a href="reservation_details.php?id=' + resa.id + '" class="badge badge-success">Res. #' + resa.id + '</a>' : '<span class="badge badge-secondary">-</span>') + '</td>' +
            '<td class="nowrap"><div class="action-buttons">' +
                '<button class="btn btn-sm btn-outline-primary view-btn" data-full="' + escapeAttr(fullText) + '" data-title="Message #' + sms.id + ' - ' + escapeAttr(sms.sender) + '">&#x1F441;</button>' +
                '<button class="btn btn-sm btn-outline-secondary copy-btn" data-copy="' + escapeAttr(sms.message) + '">&#x1F4CB;</button>' +
                '<a href="?view=conversations&sender=' + encodeURIComponent(sms.sender) + '" class="btn btn-sm btn-outline-info">&#x1F4AC;</a>' +
            '</div></td>';
        tbody.appendChild(tr);
    });
}

function initListFilters() {
    const handler = debounced(() => loadListView(1), 400);
    ['searchInput', 'modemFilter', 'dateFrom', 'dateTo'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', handler);
    });
    const resetBtn = $('#resetFilters');
    if (resetBtn) resetBtn.addEventListener('click', () => {
        ['searchInput', 'dateFrom', 'dateTo'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
        const modem = $('#modemFilter'); if (modem) modem.value = '';
        loadListView(1);
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// Pagination générique
// ─────────────────────────────────────────────────────────────────────────────
function renderPagination(container, totalPages, current, onClick) {
    if (!container) return;
    container.innerHTML = '';
    if (totalPages <= 1) return;

    const MAX = 7;
    let start = Math.max(1, current - Math.floor(MAX / 2));
    let end = Math.min(totalPages, start + MAX - 1);
    start = Math.max(1, end - MAX + 1);

    const addLink = (p, text, disabled = false, active = false) => {
        const li = document.createElement('li');
        li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
        const a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.textContent = text;
        if (!disabled) a.addEventListener('click', (e) => { e.preventDefault(); onClick(p); });
        li.appendChild(a);
        container.appendChild(li);
    };

    addLink(current - 1, '\u00AB', current === 1);
    if (start > 1) addLink(1, '1');
    if (start > 2) { const li = document.createElement('li'); li.className = 'page-item disabled'; li.innerHTML = '<span class="page-link">...</span>'; container.appendChild(li); }
    for (let i = start; i <= end; i++) addLink(i, String(i), false, i === current);
    if (end < totalPages - 1) { const li = document.createElement('li'); li.className = 'page-item disabled'; li.innerHTML = '<span class="page-link">...</span>'; container.appendChild(li); }
    if (end < totalPages) addLink(totalPages, String(totalPages));
    addLink(current + 1, '\u00BB', current === totalPages);
}

async function loadAndDisplayConversation(sender, reservation) {
  const messagesContent = $('#messagesContent');
  messagesContent.innerHTML = '<div class="empty-state"><div class="spinner-border text-primary" role="status"><span class="sr-only">Chargement...</span></div></div>';

  try {
    const resp = await fetch('get_conversation.php?sender=' + encodeURIComponent(sender), {
      headers: { 'Accept': 'application/json' }
    });

    if (!resp.ok) {
      throw new Error('Erreur réseau: ' + resp.status + ' ' + resp.statusText);
    }

    const rawText = await resp.text();
    let parsed;
    try {
      parsed = JSON.parse(rawText);
    } catch (e) {
      throw new Error('Réponse non JSON : ' + rawText.slice(0, 300));
    }

    let messages = Array.isArray(parsed) ? parsed
                 : (parsed && Array.isArray(parsed.messages)) ? parsed.messages
                 : (parsed && Array.isArray(parsed.data)) ? parsed.data
                 : [];

    if (!Array.isArray(messages)) {
      throw new Error('JSON inattendu (pas un tableau). Aperçu: ' + rawText.slice(0, 200));
    }

    // Clear content
    messagesContent.innerHTML = '';

    // ----- HEADER -----
    const headerDiv = document.createElement('div');
    headerDiv.className = 'messages-header';

    const h4 = document.createElement('h4');
    h4.textContent = sender;
    headerDiv.appendChild(h4);

    if (reservation) {
      const resInfo = document.createElement('div');
      resInfo.className = 'messages-info';

      const badge = document.createElement('span');
      badge.className = 'badge badge-success mr-1';
      badge.textContent = 'Rés. #' + reservation.id;
      resInfo.appendChild(badge);

      const strong = document.createElement('strong');
      strong.textContent = reservation.client_name || 'Client';
      resInfo.appendChild(strong);

      const dates = document.createTextNode(' (' +
        formatDateJS(reservation.start_date, { day: 'numeric', month: 'short' }) + ' - ' +
        formatDateJS(reservation.end_date,   { day: 'numeric', month: 'short' }) + ')');
      resInfo.appendChild(dates);

      if (reservation.nom_du_logement) {
        const logement = document.createTextNode(' - ' + reservation.nom_du_logement);
        resInfo.appendChild(logement);
      }

      const link = document.createElement('a');
      link.href = 'reservation_details.php?id=' + reservation.id;
      link.className = 'btn btn-sm btn-outline-secondary ml-2 py-0 px-1';
      link.target = '_blank';
      link.title = 'Voir Réservation';
      link.textContent = '👁️';
      resInfo.appendChild(link);

      headerDiv.appendChild(resInfo);
    }

    messagesContent.appendChild(headerDiv);

    // ----- BODY -----
    const bodyDiv = document.createElement('div');
    bodyDiv.className = 'messages-body';
    bodyDiv.id = 'messagesBody';

    if (messages.length === 0) {
      const emptyP = document.createElement('p');
      emptyP.className = 'text-center text-muted mt-3';
      emptyP.textContent = 'Aucun message dans cette conversation.';
      bodyDiv.appendChild(emptyP);
    } else {
      messages.forEach((msg) => {
        const direction = (msg && msg.direction === 'in') ? 'received' : 'sent';
        const rawMessage = String((msg && msg.message) != null ? msg.message : '');
        let is_pdu = false;
        try {
          is_pdu = rawMessage.startsWith(',145,') || /^[0-9A-F]{10,}/i.test(rawMessage);
        } catch (_) {
          is_pdu = false;
        }
        const displayMessage = is_pdu ? '[Message non décodé: ' + rawMessage + ']' : rawMessage;

        const dateValue = (msg && msg.date) ? msg.date : new Date().toISOString();
        const statusValue = (msg && msg.status) ? msg.status : 'pending';

        const bubble = createMessageBubble(displayMessage, direction, dateValue, statusValue);
        if (is_pdu) {
          const textDiv = bubble.querySelector('.message-text');
          if (textDiv) textDiv.classList.add('raw-pdu');
        }
        bodyDiv.appendChild(bubble);
      });
    }

    messagesContent.appendChild(bodyDiv);

    // ----- AI SUGGESTIONS -----
    const suggestionsDiv = document.createElement('div');
    suggestionsDiv.className = 'ai-suggestions';
    suggestionsDiv.id = 'aiSuggestions';
    suggestionsDiv.innerHTML = `
      <div class="ai-suggestions-header">
        <i class="fas fa-robot"></i> Suggestions de reponse
      </div>
      <div class="ai-suggestions-list" id="aiSuggestionsList">
        <div class="ai-suggestions-loading">
          <div class="spinner-border spinner-border-sm" role="status"></div>
          Analyse du message...
        </div>
      </div>
    `;
    messagesContent.appendChild(suggestionsDiv);

    // Charger les suggestions IA pour le dernier message recu
    const lastReceivedMsg = messages.filter(m => m && m.direction === 'in').pop();
    if (lastReceivedMsg && lastReceivedMsg.message) {
      loadAISuggestions(lastReceivedMsg.message, sender, reservation);
    } else {
      document.getElementById('aiSuggestionsList').innerHTML = '<div class="ai-suggestions-empty">Aucun message a analyser</div>';
    }

    // Ajouter les boutons d'actions dans le header
    const actionsDiv = document.createElement('div');
    actionsDiv.className = 'conversation-actions mt-2';
    actionsDiv.innerHTML = `
      <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-secondary" onclick="markConversationRead('${escapeAttr(sender)}')" title="Marquer comme lu">
          <i class="fas fa-check-double"></i>
        </button>
        <button type="button" class="btn btn-outline-warning" onclick="toggleStar('${escapeAttr(sender)}')" title="Marquer important">
          <i class="fas fa-star"></i>
        </button>
        <button type="button" class="btn btn-outline-info" onclick="archiveConversation('${escapeAttr(sender)}')" title="Archiver">
          <i class="fas fa-archive"></i>
        </button>
        <button type="button" class="btn btn-outline-danger" onclick="deleteConversation('${escapeAttr(sender)}')" title="Supprimer">
          <i class="fas fa-trash"></i>
        </button>
      </div>
    `;
    headerDiv.appendChild(actionsDiv);

    // ----- FOOTER -----
    const footerDiv = document.createElement('div');
    footerDiv.className = 'messages-footer';

    const form = document.createElement('form');
    form.className = 'reply-form';
    form.id = 'replyForm';
    form.dataset.sender = sender;

    const input = document.createElement('input');
    input.type = 'text';
    input.id = 'replyInput';
    input.className = 'reply-input';
    input.placeholder = 'Répondre...';
    input.required = true;
    input.autocomplete = 'off';

    const button = document.createElement('button');
    button.type = 'submit';
    button.className = 'send-btn';
    button.textContent = 'Envoyer';

    form.appendChild(input);
    form.appendChild(button);
    footerDiv.appendChild(form);
    messagesContent.appendChild(footerDiv);

    // Scroll bottom
    bodyDiv.scrollTop = bodyDiv.scrollHeight;

    // Submit handler
    form.addEventListener('submit', handleReplySubmit);

  } catch (error) {
    messagesContent.innerHTML = '';
    const errorDiv = document.createElement('div');
    errorDiv.className = 'empty-state';
    errorDiv.textContent = '❌ Erreur chargement: ' + (error.message || 'Erreur inconnue');
    messagesContent.appendChild(errorDiv);
    console.error('Error loading conversation:', error);
  }
}

// Charger les suggestions IA
async function loadAISuggestions(message, sender, reservation) {
  const listEl = document.getElementById('aiSuggestionsList');
  if (!listEl) return;

  try {
    const params = new URLSearchParams({
      message: message,
      sender: sender
    });
    if (reservation && reservation.id) {
      params.append('reservation_id', reservation.id);
    }

    const resp = await fetch('sms_ai_suggest.php?' + params.toString());
    const data = await resp.json();

    if (data.success && data.suggestions && data.suggestions.length > 0) {
      listEl.innerHTML = '';

      data.suggestions.forEach((suggestion, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ai-suggestion-btn';
        btn.dataset.text = suggestion.text;

        // Indicateur de confiance
        let confidenceClass = 'confidence-low';
        if (suggestion.confidence >= 0.8) confidenceClass = 'confidence-high';
        else if (suggestion.confidence >= 0.5) confidenceClass = 'confidence-medium';

        const preview = suggestion.text.length > 40 ? suggestion.text.substring(0, 40) + '...' : suggestion.text;

        btn.innerHTML = `
          <span class="confidence-dot ${confidenceClass}" title="Confiance: ${Math.round(suggestion.confidence * 100)}%"></span>
          <span class="suggestion-label">${escapeHTML(suggestion.label)}</span>
          <span class="suggestion-preview">${escapeHTML(preview)}</span>
        `;

        btn.addEventListener('click', () => {
          const replyInput = document.getElementById('replyInput');
          if (replyInput) {
            replyInput.value = suggestion.text;
            replyInput.focus();
            // Highlight the input briefly
            replyInput.style.backgroundColor = '#e7f3ff';
            setTimeout(() => { replyInput.style.backgroundColor = ''; }, 500);
          }
        });

        listEl.appendChild(btn);
      });

      // Ajouter les categories detectees si present
      if (data.detected_categories && data.detected_categories.length > 0) {
        const catInfo = document.createElement('div');
        catInfo.className = 'mt-2';
        catInfo.style.cssText = 'font-size: 0.7rem; color: #adb5bd;';
        catInfo.innerHTML = '<i class="fas fa-tags"></i> Detecte: ' + data.detected_categories.join(', ');
        listEl.appendChild(catInfo);
      }

    } else {
      listEl.innerHTML = '<div class="ai-suggestions-empty">Aucune suggestion disponible</div>';
    }
  } catch (error) {
    console.error('Erreur chargement suggestions:', error);
    listEl.innerHTML = '<div class="ai-suggestions-empty">Erreur lors du chargement des suggestions</div>';
  }
}

async function handleReplySubmit(e) {
  e.preventDefault();
  const form = e.target;
  const input = form.querySelector('#replyInput');
  const sendBtn = form.querySelector('.send-btn');
  const message = (input.value || '').trim();
  const receiver = form.dataset.sender;

  if (!message || !receiver) return;

  const originalButtonText = sendBtn.innerHTML;
  sendBtn.disabled = true;
  sendBtn.innerHTML = '...';

  try {
    const formData = new FormData();
    formData.append('receiver', receiver);
    formData.append('message', message);
    formData.append('modem', 'modem1');
    const response = await fetch('send_sms_ajax.php', {
      method: 'POST',
      body: formData
    });
    const result = await response.json();

    if (result.success) {
      const body = $('#messagesBody');
      const now = new Date();
      const bubble = createMessageBubble(String(message), 'sent', now.toISOString(), 'pending');
      body.appendChild(bubble);
      body.scrollTop = body.scrollHeight;
      input.value = '';
      showToast('SMS ajouté à la file d\'attente.');

      const convItem = $$('#conversationsList .conversation-item').find(item => item.dataset.sender === receiver);
      if (convItem) {
        const preview = $('.conversation-preview', convItem);
        const time = $('.conversation-time', convItem);
        if (preview) preview.textContent = "Vous: " + message;
        if (time) time.textContent = 'maintenant';
        $('#conversationsList').prepend(convItem);
      }
    } else {
      showToast('Erreur: ' + (result.message || 'Impossible d\'ajouter à la file'), 'error');
    }
  } catch (error) {
    showToast('Erreur réseau lors de l\'envoi.', 'error');
    console.error("Send SMS error:", error);
  } finally {
    sendBtn.disabled = false;
    sendBtn.innerHTML = originalButtonText;
  }
}

// Old initListView removed — now using loadListView() with server-side pagination
</script>

<!-- Modal d'envoi SMS -->
<div class="modal fade" id="smsModal" tabindex="-1" role="dialog" aria-labelledby="smsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="smsModalLabel">
          <i class="fas fa-paper-plane"></i> Envoyer un SMS
        </h5>
        <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form id="smsForm" method="POST" action="">
        <div class="modal-body">
          <div class="form-group">
            <label for="modal_receiver">
              <i class="fas fa-phone"></i> Numéro du destinataire
            </label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text">
                  <i class="fas fa-mobile-alt"></i>
                </span>
              </div>
              <input type="text"
                     name="receiver"
                     id="modal_receiver"
                     class="form-control"
                     placeholder="Ex: 0612345678 ou +33612345678"
                     required>
            </div>
            <small class="form-text text-muted">Format: 0612345678 ou +33612345678</small>
          </div>

          <div class="form-group">
            <label for="modal_message">
              <i class="fas fa-comment-dots"></i> Message
            </label>
            <textarea name="message"
                      id="modal_message"
                      class="form-control"
                      rows="4"
                      placeholder="Saisissez votre message ici..."
                      maxlength="160"
                      required></textarea>
            <small id="modal_message_counter" class="form-text text-muted">0/160 caractères</small>
          </div>

          <div class="form-group">
            <label for="modal_modem">
              <i class="fas fa-sim-card"></i> Sélectionner le modem
            </label>
            <select name="modem" id="modal_modem" class="form-control" required>
              <?php if (!empty($modems)): ?>
                <?php foreach ($modems as $m): ?>
                  <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                <?php endforeach; ?>
              <?php else: ?>
                <option value="/dev/ttyUSB0">/dev/ttyUSB0</option>
              <?php endif; ?>
            </select>
          </div>

          <div id="modalAlert" class="alert d-none" role="alert"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times"></i> Annuler
          </button>
          <button type="submit" class="btn btn-primary" id="modalSendBtn">
            <i class="fas fa-paper-plane"></i> Envoyer le SMS
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bouton flottant pour envoyer un SMS -->
<button class="btn btn-primary btn-lg floating-btn" data-bs-toggle="modal" data-bs-target="#smsModal" title="Envoyer un SMS">
  <i class="fas fa-plus"></i>
</button>

<style>
.floating-btn {
  position: fixed;
  bottom: 30px;
  right: 30px;
  width: 60px;
  height: 60px;
  border-radius: 50%;
  box-shadow: 0 4px 12px rgba(0,0,0,0.3);
  z-index: 1000;
  transition: all 0.3s ease;
}

.floating-btn:hover {
  transform: scale(1.1);
  box-shadow: 0 6px 20px rgba(0,0,0,0.4);
}

.floating-btn i {
  font-size: 1.5rem;
}

/* Style pour les boutons d'envoi dans les conversations */
.conversation-item {
  position: relative;
}

.conversation-item .quick-send-btn {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  opacity: 0;
  transition: opacity 0.2s;
}

.conversation-item:hover .quick-send-btn {
  opacity: 1;
}
</style>

<script>
// Compteur de caractères pour la modal
document.getElementById('modal_message').addEventListener('input', function() {
  const count = this.value.length;
  const counter = document.getElementById('modal_message_counter');
  counter.textContent = count + '/160 caractères';

  if (count > 140) {
    counter.style.color = '#FF6B6B';
  } else {
    counter.style.color = '#7F8C8D';
  }
});

// Fonction pour pré-remplir la modal avec un numéro
function openSmsModal(phoneNumber, name) {
  document.getElementById('modal_receiver').value = phoneNumber || '';

  if (name) {
    const messageField = document.getElementById('modal_message');
    messageField.value = 'Bonjour ' + name + ', ';
    messageField.focus();
    // Mettre le curseur à la fin
    messageField.setSelectionRange(messageField.value.length, messageField.value.length);
    // Trigger le compteur
    messageField.dispatchEvent(new Event('input'));
  }

  bootstrap.Modal.getOrCreateInstance(document.getElementById('smsModal')).show();
}

// Soumission du formulaire modal en AJAX
document.getElementById('smsForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const submitBtn = document.getElementById('modalSendBtn');
  const alertDiv = document.getElementById('modalAlert');
  const formData = new FormData(this);

  // Désactiver le bouton
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';

  // Envoyer en AJAX
  fetch('send_sms_ajax.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    alertDiv.className = 'alert ' + (data.success ? 'alert-success' : 'alert-danger');
    alertDiv.textContent = data.message;
    alertDiv.classList.remove('d-none');

    if (data.success) {
      // Réinitialiser le formulaire après 2 secondes
      setTimeout(() => {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('smsModal')).hide();
        document.getElementById('smsForm').reset();
        alertDiv.classList.add('d-none');
        document.getElementById('modal_message_counter').textContent = '0/160 caractères';
      }, 2000);
    }
  })
  .catch(error => {
    alertDiv.className = 'alert alert-danger';
    alertDiv.textContent = 'Erreur lors de l\'envoi du SMS';
    alertDiv.classList.remove('d-none');
  })
  .finally(() => {
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer le SMS';
  });
});

// Ajouter des boutons d'envoi rapide dans les conversations
document.addEventListener('DOMContentLoaded', function() {
  const conversationItems = document.querySelectorAll('.conversation-item');

  conversationItems.forEach(item => {
    const sender = item.getAttribute('data-sender');

    // Créer un bouton d'envoi rapide
    const sendBtn = document.createElement('button');
    sendBtn.className = 'btn btn-sm btn-primary quick-send-btn';
    sendBtn.innerHTML = '<i class="fas fa-reply"></i>';
    sendBtn.title = 'Répondre';
    sendBtn.onclick = function(e) {
      e.stopPropagation();
      openSmsModal(sender, '');
    };

    item.appendChild(sendBtn);
  });
});

// Actions sur les conversations
async function conversationAction(phone, action) {
  try {
    const response = await fetch('conversation_action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=${encodeURIComponent(action)}&phone=${encodeURIComponent(phone)}`
    });
    const result = await response.json();

    if (result.success) {
      showToast(result.message, 'success');
      return result;
    } else {
      showToast('Erreur: ' + result.error, 'error');
      return null;
    }
  } catch (error) {
    showToast('Erreur réseau', 'error');
    console.error('Action error:', error);
    return null;
  }
}

function markConversationRead(phone) {
  conversationAction(phone, 'mark_read').then(result => {
    if (result) {
      // Mettre à jour l'UI (retirer le badge non lu)
      const item = document.querySelector(`.conversation-item[data-sender="${phone}"]`);
      if (item) {
        const badge = item.querySelector('.unread-badge');
        if (badge) badge.remove();
      }
    }
  });
}

function toggleStar(phone) {
  conversationAction(phone, 'star').then(result => {
    if (result) {
      // Animation visuelle
      const btn = document.querySelector('.conversation-actions .btn-outline-warning');
      if (btn) {
        btn.classList.toggle('btn-warning');
        btn.classList.toggle('btn-outline-warning');
      }
    }
  });
}

function archiveConversation(phone) {
  if (!confirm('Archiver cette conversation ?')) return;

  conversationAction(phone, 'archive').then(result => {
    if (result) {
      // Retirer de la liste actuelle
      const item = document.querySelector(`.conversation-item[data-sender="${phone}"]`);
      if (item) {
        item.style.transition = 'opacity 0.3s, transform 0.3s';
        item.style.opacity = '0';
        item.style.transform = 'translateX(-100%)';
        setTimeout(() => item.remove(), 300);
      }

      // Afficher le panel vide
      const messagesContent = document.getElementById('messagesContent');
      if (messagesContent) {
        messagesContent.innerHTML = `
          <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-archive fa-4x text-muted"></i></div>
            <h4>Conversation archivée</h4>
            <p>Sélectionnez une autre conversation ou <a href="?view=conversations&archived=1">voir les archives</a></p>
          </div>
        `;
      }
    }
  });
}

function deleteConversation(phone) {
  if (!confirm('Supprimer définitivement cette conversation ?\n\nCette action est irréversible.')) return;

  conversationAction(phone, 'delete').then(result => {
    if (result) {
      // Retirer de la liste
      const item = document.querySelector(`.conversation-item[data-sender="${phone}"]`);
      if (item) {
        item.style.transition = 'opacity 0.3s, transform 0.3s';
        item.style.opacity = '0';
        item.style.transform = 'translateX(-100%)';
        setTimeout(() => item.remove(), 300);
      }

      // Afficher le panel vide
      const messagesContent = document.getElementById('messagesContent');
      if (messagesContent) {
        messagesContent.innerHTML = `
          <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-trash fa-4x text-muted"></i></div>
            <h4>Conversation supprimée</h4>
            <p>Sélectionnez une autre conversation.</p>
          </div>
        `;
      }
    }
  });
}
</script>

</div><!-- /container-fluid -->
</body>
</html>
