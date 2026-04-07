<?php
/**
 * HUB Sejour — Page publique par reservation
 * Accessible sans login via token unique
 * URL : /frenchybot/hub/?id=TOKEN
 */

// Charger la config DB sans le menu admin
require_once __DIR__ . '/../../ionos/gestion/includes/env_loader.php';
require_once __DIR__ . '/../../ionos/gestion/db/connection.php';
require_once __DIR__ . '/../includes/hub-functions.php';
require_once __DIR__ . '/../includes/channels.php';

$token = trim($_GET['id'] ?? '');

if (!$token || strlen($token) < 16) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

// Charger les donnees du HUB
$hub = loadHubData($pdo, $token);
if (!$hub) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

// Tracker la visite
trackInteraction($pdo, $hub['hub_token_id'], $hub['reservation_id'], 'view');

$sejour = getSejourInfo($hub);
$equip = $hub['equipements'];
$quickActions = getQuickActions();

// Dates formatees
$dateArrivee = date('d/m/Y', strtotime($hub['date_arrivee']));
$dateDepart = date('d/m/Y', strtotime($hub['date_depart']));
$heureArrivee = $hub['heure_arrivee'] ?: ($equip['heure_checkin'] ?? '16:00');
$heureDepart = $hub['heure_depart'] ?: ($equip['heure_checkout'] ?? '10:00');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Votre sejour — <?= htmlspecialchars($hub['nom_du_logement']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --fc-primary: #2563eb;
            --fc-primary-dark: #1d4ed8;
            --fc-bg: #f8fafc;
            --fc-card-bg: #ffffff;
            --fc-text: #1e293b;
            --fc-text-muted: #64748b;
            --fc-border: #e2e8f0;
            --fc-success: #22c55e;
            --fc-warning: #f59e0b;
            --fc-danger: #ef4444;
        }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--fc-bg);
            color: var(--fc-text);
            margin: 0;
            padding: 0;
            padding-bottom: 80px;
        }
        .hub-header {
            background: linear-gradient(135deg, var(--fc-primary), var(--fc-primary-dark));
            color: white;
            padding: 24px 16px;
            text-align: center;
        }
        .hub-header h1 { font-size: 1.3rem; margin: 0 0 4px; font-weight: 700; }
        .hub-header .sub { opacity: 0.85; font-size: 0.9rem; }
        .hub-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 8px;
        }
        .hub-badge.before { background: rgba(255,255,255,0.2); }
        .hub-badge.during { background: var(--fc-success); }
        .hub-badge.after { background: rgba(255,255,255,0.15); }

        .hub-container { max-width: 480px; margin: 0 auto; padding: 16px; }

        .hub-card {
            background: var(--fc-card-bg);
            border-radius: 12px;
            border: 1px solid var(--fc-border);
            margin-bottom: 12px;
            overflow: hidden;
        }
        .hub-card-header {
            padding: 12px 16px;
            font-weight: 600;
            font-size: 0.9rem;
            border-bottom: 1px solid var(--fc-border);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .hub-card-body { padding: 16px; }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--fc-text-muted); font-size: 0.85rem; }
        .info-value { font-weight: 600; font-size: 0.9rem; }

        .copy-btn {
            background: var(--fc-primary);
            color: white;
            border: none;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .copy-btn:hover { background: var(--fc-primary-dark); }
        .copy-btn.copied { background: var(--fc-success); }

        .quick-action-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 14px 8px;
            border-radius: 10px;
            border: 1px solid var(--fc-border);
            background: var(--fc-card-bg);
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--fc-text);
            text-align: center;
        }
        .quick-action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .quick-action-btn i { font-size: 1.3rem; }

        .upsell-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid var(--fc-border);
            margin-bottom: 8px;
            background: var(--fc-card-bg);
        }
        .upsell-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            background: #eff6ff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--fc-primary);
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .upsell-info { flex: 1; }
        .upsell-info .name { font-weight: 600; font-size: 0.9rem; }
        .upsell-info .desc { font-size: 0.78rem; color: var(--fc-text-muted); }
        .upsell-price {
            font-weight: 700;
            color: var(--fc-primary);
            white-space: nowrap;
        }
        .upsell-buy {
            background: var(--fc-primary);
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
        }

        .chat-bubble {
            background: #eff6ff;
            border-radius: 12px 12px 12px 2px;
            padding: 12px 16px;
            margin-bottom: 8px;
            font-size: 0.88rem;
            line-height: 1.4;
        }
        .chat-response {
            background: var(--fc-card-bg);
            border: 1px solid var(--fc-border);
            border-radius: 12px 12px 2px 12px;
            padding: 12px 16px;
            margin-bottom: 8px;
            font-size: 0.88rem;
        }
        #chatArea { display: none; }

        .chat-container {
            border-top: 1px solid var(--fc-border);
            margin-top: 12px;
            padding-top: 12px;
        }
        .chat-messages {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 12px;
        }
        .chat-input-row {
            display: flex;
            gap: 8px;
        }
        .chat-input-row input {
            flex: 1;
            border: 1px solid var(--fc-border);
            border-radius: 20px;
            padding: 10px 16px;
            font-size: 0.9rem;
            outline: none;
        }
        .chat-input-row input:focus { border-color: var(--fc-primary); }
        .chat-send-btn {
            background: var(--fc-primary);
            color: white;
            border: none;
            border-radius: 50%;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
        }
        .chat-send-btn:disabled { opacity: 0.5; }
        .typing-dots { display: inline-block; }
        .typing-dots span {
            display: inline-block;
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--fc-text-muted);
            margin: 0 1px;
            animation: typing 1.2s infinite;
        }
        .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-4px); }
        }

        .hub-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--fc-card-bg);
            border-top: 1px solid var(--fc-border);
            padding: 12px 16px;
            text-align: center;
            font-size: 0.75rem;
            color: var(--fc-text-muted);
        }

        .instructions-text {
            font-size: 0.88rem;
            line-height: 1.5;
            white-space: pre-line;
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="hub-header">
    <h1><?= htmlspecialchars($hub['nom_du_logement']) ?></h1>
    <div class="sub">
        <?= $dateArrivee ?> → <?= $dateDepart ?>
        (<?= $sejour['nb_nuits'] ?> nuit<?= $sejour['nb_nuits'] > 1 ? 's' : '' ?>)
    </div>
    <div class="hub-badge <?= $sejour['status'] ?>">
        <?php if ($sejour['status'] === 'before'): ?>
            <i class="fas fa-calendar-alt"></i> Arrivee dans <?= $sejour['jours_avant_arrivee'] ?> jour<?= $sejour['jours_avant_arrivee'] > 1 ? 's' : '' ?>
        <?php elseif ($sejour['status'] === 'during'): ?>
            <i class="fas fa-home"></i> Jour <?= $sejour['jour_sejour'] ?> / <?= $sejour['nb_nuits'] ?>
        <?php else: ?>
            <i class="fas fa-check"></i> Sejour termine
        <?php endif; ?>
    </div>
</div>

<div class="hub-container">

    <!-- Bienvenue -->
    <div class="hub-card">
        <div class="hub-card-body" style="text-align:center; padding: 20px;">
            <h2 style="font-size:1.1rem; margin:0 0 4px;">Bonjour <?= htmlspecialchars($hub['prenom']) ?> !</h2>
            <p style="color:var(--fc-text-muted); margin:0; font-size:0.88rem;">
                Bienvenue dans votre espace sejour. Toutes les informations utiles sont ici.
            </p>
        </div>
    </div>

    <!-- Acces -->
    <?php
    $hasAccess = !empty($equip['code_porte']) || !empty($equip['code_boite_cles']) || !empty($equip['etage']) || !empty($equip['parking']);
    if ($hasAccess): ?>
    <div class="hub-card">
        <div class="hub-card-header">
            <i class="fas fa-key" style="color:var(--fc-primary)"></i> Acces au logement
        </div>
        <div class="hub-card-body">
            <?php if (!empty($equip['code_porte'])): ?>
            <div class="info-row">
                <div>
                    <div class="info-label">Code porte</div>
                    <div class="info-value" id="code_porte"><?= htmlspecialchars($equip['code_porte']) ?></div>
                </div>
                <button class="copy-btn" onclick="copyText('code_porte', this)"><i class="fas fa-copy"></i></button>
            </div>
            <?php endif; ?>

            <?php if (!empty($equip['code_boite_cles'])): ?>
            <div class="info-row">
                <div>
                    <div class="info-label">Code boite a cles</div>
                    <div class="info-value" id="code_boite"><?= htmlspecialchars($equip['code_boite_cles']) ?></div>
                </div>
                <button class="copy-btn" onclick="copyText('code_boite', this)"><i class="fas fa-copy"></i></button>
            </div>
            <?php endif; ?>

            <?php if (!empty($equip['etage'])): ?>
            <div class="info-row">
                <span class="info-label">Etage</span>
                <span class="info-value"><?= htmlspecialchars($equip['etage']) ?><?= !empty($equip['ascenseur']) ? ' <i class="fas fa-elevator text-success" title="Ascenseur"></i>' : '' ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($equip['parking'])): ?>
            <div class="info-row">
                <span class="info-label">Parking</span>
                <span class="info-value"><?= htmlspecialchars($equip['parking_type'] ?? 'Oui') ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($equip['digicode'])): ?>
            <div class="info-row">
                <div>
                    <div class="info-label">Digicode</div>
                    <div class="info-value" id="digicode"><?= htmlspecialchars($equip['digicode']) ?></div>
                </div>
                <button class="copy-btn" onclick="copyText('digicode', this)"><i class="fas fa-copy"></i></button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Wifi -->
    <?php if (!empty($equip['nom_wifi'])): ?>
    <div class="hub-card">
        <div class="hub-card-header">
            <i class="fas fa-wifi" style="color:var(--fc-success)"></i> Wifi
        </div>
        <div class="hub-card-body">
            <div class="info-row">
                <div>
                    <div class="info-label">Reseau</div>
                    <div class="info-value" id="wifi_name"><?= htmlspecialchars($equip['nom_wifi']) ?></div>
                </div>
                <button class="copy-btn" onclick="copyText('wifi_name', this)"><i class="fas fa-copy"></i></button>
            </div>
            <?php if (!empty($equip['code_wifi'])): ?>
            <div class="info-row">
                <div>
                    <div class="info-label">Mot de passe</div>
                    <div class="info-value" id="code_wifi"><?= htmlspecialchars($equip['code_wifi']) ?></div>
                </div>
                <button class="copy-btn" onclick="copyText('code_wifi', this)"><i class="fas fa-copy"></i></button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Horaires -->
    <div class="hub-card">
        <div class="hub-card-header">
            <i class="fas fa-clock" style="color:var(--fc-warning)"></i> Horaires
        </div>
        <div class="hub-card-body">
            <div class="info-row">
                <span class="info-label">Check-in</span>
                <span class="info-value"><?= htmlspecialchars($heureArrivee) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Check-out</span>
                <span class="info-value"><?= htmlspecialchars($heureDepart) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Adresse</span>
                <span class="info-value" style="font-size:0.82rem; text-align:right; max-width:60%;"><?= htmlspecialchars($hub['adresse'] ?? '') ?></span>
            </div>
        </div>
    </div>

    <!-- Instructions arrivee -->
    <?php if (!empty($equip['instructions_arrivee'])): ?>
    <div class="hub-card">
        <div class="hub-card-header">
            <i class="fas fa-door-open" style="color:var(--fc-success)"></i> Instructions d'arrivee
        </div>
        <div class="hub-card-body">
            <div class="instructions-text"><?= nl2br(htmlspecialchars($equip['instructions_arrivee'])) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Instructions depart -->
    <?php if (!empty($equip['instructions_depart'])): ?>
    <div class="hub-card">
        <div class="hub-card-header">
            <i class="fas fa-suitcase-rolling" style="color:var(--fc-text-muted)"></i> Instructions de depart
        </div>
        <div class="hub-card-body">
            <div class="instructions-text"><?= nl2br(htmlspecialchars($equip['instructions_depart'])) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Actions rapides -->
    <?php if ($sejour['status'] === 'during' || $sejour['status'] === 'before'): ?>
    <div class="hub-card">
        <div class="hub-card-header">
            <i class="fas fa-bolt" style="color:var(--fc-warning)"></i> Besoin d'aide ?
        </div>
        <div class="hub-card-body">
            <div class="quick-action-grid">
                <?php foreach ($quickActions as $action): ?>
                <button class="quick-action-btn" onclick="handleAction('<?= $action['id'] ?>')">
                    <i class="fas <?= $action['icon'] ?> text-<?= $action['color'] ?>"></i>
                    <?= htmlspecialchars($action['label']) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <div id="chatArea" class="mt-3"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Chat IA -->
    <?php if ($sejour['status'] === 'during' || $sejour['status'] === 'before'): ?>
    <div class="hub-card">
        <div class="hub-card-header">
            <i class="fas fa-comment-dots" style="color:var(--fc-primary)"></i> Une question ?
        </div>
        <div class="hub-card-body">
            <div class="chat-messages" id="chatMessages"></div>
            <div class="chat-input-row">
                <input type="text" id="chatInput" placeholder="Posez votre question..." maxlength="500"
                    onkeydown="if(event.key==='Enter')sendChat()">
                <button class="chat-send-btn" id="chatSendBtn" onclick="sendChat()">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Upsells -->
    <?php if (!empty($hub['upsells']) && $sejour['status'] !== 'after'): ?>
    <div class="hub-card">
        <div class="hub-card-header">
            <i class="fas fa-star" style="color:var(--fc-warning)"></i> Ameliorez votre sejour
        </div>
        <div class="hub-card-body">
            <?php foreach ($hub['upsells'] as $upsell): ?>
            <div class="upsell-card">
                <div class="upsell-icon">
                    <i class="fas <?= htmlspecialchars($upsell['icon'] ?? 'fa-gift') ?>"></i>
                </div>
                <div class="upsell-info">
                    <div class="name"><?= htmlspecialchars($upsell['label']) ?></div>
                    <div class="desc"><?= htmlspecialchars($upsell['description'] ?? '') ?></div>
                </div>
                <button class="upsell-buy" onclick="buyUpsell(<?= $upsell['id'] ?>)">
                    <?= number_format($upsell['price'], 0) ?> €
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Reglement / infos quartier -->
    <?php if (!empty($equip['numeros_urgence']) || !empty($equip['infos_quartier'])): ?>
    <div class="hub-card">
        <div class="hub-card-header">
            <i class="fas fa-info-circle" style="color:var(--fc-text-muted)"></i> Informations utiles
        </div>
        <div class="hub-card-body">
            <?php if (!empty($equip['numeros_urgence'])): ?>
                <p class="mb-2"><strong>Numeros utiles :</strong></p>
                <div class="instructions-text mb-3"><?= nl2br(htmlspecialchars($equip['numeros_urgence'])) ?></div>
            <?php endif; ?>
            <?php if (!empty($equip['infos_quartier'])): ?>
                <p class="mb-2"><strong>Le quartier :</strong></p>
                <div class="instructions-text"><?= nl2br(htmlspecialchars($equip['infos_quartier'])) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<div class="hub-footer">
    <i class="fas fa-bolt"></i> Frenchy Conciergerie — Votre sejour, simplifie.
</div>

<script>
const TOKEN = '<?= htmlspecialchars($token) ?>';
const HUB_TOKEN_ID = <?= (int)$hub['hub_token_id'] ?>;

function copyText(elId, btn) {
    const text = document.getElementById(elId).textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
        btn.classList.add('copied');
        btn.innerHTML = '<i class="fas fa-check"></i>';
        // Track
        fetch('action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({token: TOKEN, action: elId + '_copy'})
        });
        setTimeout(() => {
            btn.classList.remove('copied');
            btn.innerHTML = '<i class="fas fa-copy"></i>';
        }, 2000);
    });
}

function handleAction(actionId) {
    const messages = {
        'access_problem': "J'ai un problème d'accès au logement. Pouvez-vous me donner les codes et instructions pour entrer ?",
        'wifi_help': "J'ai un problème avec le wifi. Quel est le nom du réseau et le mot de passe ?",
        'cleaning_request': "J'aurais besoin d'un ménage supplémentaire ou de linge propre. C'est possible ?",
        'checkout_info': "Quelles sont les instructions pour le départ / check-out ?",
        'other': null
    };

    const msg = messages[actionId];

    // Tracker + notifier l'admin via action.php (fire and forget)
    fetch('action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({token: TOKEN, action: actionId})
    });

    if (msg) {
        // Envoyer au chat IA pour une reponse intelligente
        const chatMessages = document.getElementById('chatMessages');
        chatMessages.innerHTML += '<div class="chat-bubble" style="border-radius:12px 12px 2px 12px; background:var(--fc-primary); color:white; margin-left:40px;">' + escapeHtml(msg) + '</div>';

        const typingId = 'typing-' + Date.now();
        chatMessages.innerHTML += '<div class="chat-response" id="' + typingId + '"><div class="typing-dots"><span></span><span></span><span></span></div></div>';
        chatMessages.scrollTop = chatMessages.scrollHeight;

        fetch('../api/chat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({token: TOKEN, message: msg})
        })
        .then(r => r.json())
        .then(data => {
            const typing = document.getElementById(typingId);
            if (typing) typing.remove();
            if (data.reply) {
                chatMessages.innerHTML += '<div class="chat-response">' + escapeHtml(data.reply) + '</div>';
            } else {
                chatMessages.innerHTML += '<div class="chat-response">Votre demande a ete transmise a notre equipe.</div>';
            }
            chatMessages.scrollTop = chatMessages.scrollHeight;
        })
        .catch(() => {
            const typing = document.getElementById(typingId);
            if (typing) typing.remove();
            chatMessages.innerHTML += '<div class="chat-response">Votre demande a ete transmise. Nous revenons vers vous rapidement.</div>';
        });
    }
}

function sendChat() {
    const input = document.getElementById('chatInput');
    const btn = document.getElementById('chatSendBtn');
    const messages = document.getElementById('chatMessages');
    const text = input.value.trim();
    if (!text) return;

    // Afficher le message utilisateur
    messages.innerHTML += '<div class="chat-bubble" style="border-radius:12px 12px 2px 12px; background:var(--fc-primary); color:white; margin-left:40px;">' + escapeHtml(text) + '</div>';
    input.value = '';
    btn.disabled = true;

    // Indicateur de frappe
    const typingId = 'typing-' + Date.now();
    messages.innerHTML += '<div class="chat-response" id="' + typingId + '"><div class="typing-dots"><span></span><span></span><span></span></div></div>';
    messages.scrollTop = messages.scrollHeight;

    fetch('../api/chat.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({token: TOKEN, message: text})
    })
    .then(r => r.json())
    .then(data => {
        const typing = document.getElementById(typingId);
        if (typing) typing.remove();
        if (data.reply) {
            messages.innerHTML += '<div class="chat-response">' + escapeHtml(data.reply) + '</div>';
        } else if (data.error) {
            messages.innerHTML += '<div class="chat-response">Votre message a ete transmis a notre equipe.</div>';
        }
        messages.scrollTop = messages.scrollHeight;
        btn.disabled = false;
        input.focus();
    })
    .catch(() => {
        const typing = document.getElementById(typingId);
        if (typing) typing.remove();
        messages.innerHTML += '<div class="chat-response">Erreur de connexion. Reessayez.</div>';
        btn.disabled = false;
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function buyUpsell(upsellId) {
    fetch('../api/upsell.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({token: TOKEN, upsell_id: upsellId})
    })
    .then(r => r.json())
    .then(data => {
        if (data.checkout_url) {
            window.location.href = data.checkout_url;
        } else {
            alert(data.message || 'Service temporairement indisponible.');
        }
    })
    .catch(() => {
        alert('Erreur de connexion. Reessayez.');
    });
}
</script>
</body>
</html>
