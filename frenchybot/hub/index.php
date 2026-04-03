<?php
/**
 * FrenchyBot - Mode iframe / standalone
 * Usage:
 *   Inline:   <iframe src="https://bot.frenchycompany.fr/api/v1/iframe.php?token=TOKEN"></iframe>
 *   Fullpage: <iframe src="https://bot.frenchycompany.fr/api/v1/iframe.php?token=TOKEN&mode=fullpage"></iframe>
 *   Direct:   https://bot.frenchycompany.fr/api/v1/iframe.php?token=TOKEN&mode=fullpage (partageable sur Facebook, etc.)
 */
define('FRENCHYBOT', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$token = $_GET['token'] ?? '';
$mode = $_GET['mode'] ?? 'inline'; // inline ou fullpage
$chatbot = getChatbotByToken($token);

if (!$chatbot) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body><p>Chatbot introuvable.</p></body></html>';
    exit;
}

$color = $chatbot['primary_color'] ?: '#1a5653';
$name = htmlspecialchars($chatbot['name'] ?: 'FrenchyBot');
$welcome = htmlspecialchars($chatbot['welcome_message'] ?: 'Bonjour ! Comment puis-je vous aider ?');
$apiUrl = FB_BASE_URL . '/api/v1/chat.php';
$isFullpage = ($mode === 'fullpage');

// OG tags pour partage Facebook
$ogTitle = $name . ' - Discutez avec nous';
$ogDescription = strip_tags($chatbot['welcome_message'] ?: 'Posez vos questions, obtenez des reponses instantanees !');
$ogUrl = FB_BASE_URL . '/api/v1/iframe.php?token=' . urlencode($token) . '&mode=fullpage';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $name ?></title>

    <!-- Open Graph pour Facebook -->
    <meta property="og:title" content="<?= $ogTitle ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $ogUrl ?>">
    <?php if ($chatbot['logo_url']): ?>
    <meta property="og:image" content="<?= htmlspecialchars($chatbot['logo_url']) ?>">
    <?php endif; ?>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: <?= $isFullpage ? 'linear-gradient(135deg, ' . $color . ', #0f3d3a)' : '#f5f7f9' ?>;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .chat-container {
            width: 100%;
            max-width: <?= $isFullpage ? '500px' : '100%' ?>;
            height: <?= $isFullpage ? '90vh' : '100vh' ?>;
            max-height: <?= $isFullpage ? '700px' : '100vh' ?>;
            background: #fff;
            border-radius: <?= $isFullpage ? '20px' : '0' ?>;
            box-shadow: <?= $isFullpage ? '0 20px 60px rgba(0,0,0,0.3)' : 'none' ?>;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .chat-header {
            background: linear-gradient(135deg, <?= $color ?>, #0f3d3a);
            color: #fff;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
        .chat-header-icon {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .chat-header-icon img {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
        }
        .chat-header h1 {
            font-size: 17px;
            font-weight: 700;
        }
        .chat-header small {
            font-size: 11px;
            opacity: .7;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            background: #f5f7f9;
        }
        .msg {
            margin: 6px 0;
            padding: 10px 14px;
            border-radius: 16px;
            max-width: 88%;
            font-size: 14px;
            line-height: 1.55;
            word-wrap: break-word;
            animation: fadeIn .3s ease;
        }
        .msg-bot {
            background: #fff;
            color: #333;
            margin-right: auto;
            border: 1px solid #e8e8e8;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .msg-user {
            background: <?= $color ?>;
            color: #fff;
            margin-left: auto;
            border-bottom-right-radius: 4px;
            max-width: 75%;
        }
        .chips {
            margin: 8px 0;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .chip {
            padding: 8px 14px;
            background: #fff;
            border: 1.5px solid <?= $color ?>;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            color: <?= $color ?>;
            font-weight: 500;
            transition: all .15s;
            white-space: nowrap;
        }
        .chip:hover {
            background: <?= $color ?>;
            color: #fff;
        }
        .chat-input {
            padding: 12px 16px;
            background: #fff;
            border-top: 1px solid #eee;
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }
        .chat-input input {
            flex: 1;
            padding: 10px 14px;
            border: 1.5px solid #ddd;
            border-radius: 24px;
            font-size: 14px;
            outline: none;
            font-family: inherit;
        }
        .chat-input input:focus {
            border-color: <?= $color ?>;
        }
        .chat-input button {
            padding: 10px 18px;
            background: <?= $color ?>;
            color: #fff;
            border: none;
            border-radius: 24px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
        }
        .typing {
            display: inline-flex;
            gap: 5px;
            padding: 10px 14px;
            background: #fff;
            border-radius: 16px;
            border-bottom-left-radius: 4px;
            border: 1px solid #e8e8e8;
            margin: 6px 0;
        }
        .dot {
            width: 6px; height: 6px;
            background: #bbb;
            border-radius: 50%;
            animation: dotBounce 1.2s infinite;
        }
        .dot:nth-child(2) { animation-delay: .2s; }
        .dot:nth-child(3) { animation-delay: .4s; }
        @keyframes dotBounce {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-6px); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        <?php if ($isFullpage): ?>
        @media (max-width: 540px) {
            .chat-container {
                max-width: 100%;
                height: 100vh;
                max-height: 100vh;
                border-radius: 0;
            }
            body { background: #fff; }
        }
        <?php endif; ?>

        /* Powered by */
        .powered-by {
            text-align: center;
            padding: 6px;
            font-size: 11px;
            color: #aaa;
            background: #fff;
            border-top: 1px solid #f0f0f0;
        }
        .powered-by a { color: #888; text-decoration: none; }
        .powered-by a:hover { color: <?= $color ?>; }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <div class="chat-header-icon">
                <?php if ($chatbot['logo_url']): ?>
                <img src="<?= htmlspecialchars($chatbot['logo_url']) ?>" alt="">
                <?php else: ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                <?php endif; ?>
            </div>
            <div>
                <h1><?= $name ?></h1>
                <small>En ligne</small>
            </div>
        </div>

        <div class="chat-messages" id="messages"></div>

        <div class="chat-input" id="inputBar">
            <input type="text" id="userInput" placeholder="Tapez votre message..." autocomplete="off">
            <button id="sendBtn">&uarr;</button>
        </div>

        <div class="powered-by">
            Propulse par <a href="https://frenchycompany.fr" target="_blank">FrenchyBot</a>
        </div>
    </div>

    <script>
    (function() {
        var token = <?= json_encode($token) ?>;
        var apiUrl = <?= json_encode($apiUrl) ?>;
        var chatId = null;
        var currentStep = 1;
        var msgContainer = document.getElementById('messages');
        var input = document.getElementById('userInput');
        var sendBtn = document.getElementById('sendBtn');

        sendBtn.onclick = sendText;
        input.onkeypress = function(e) { if (e.key === 'Enter') sendText(); };

        function sendText() {
            var txt = input.value.trim();
            if (!txt) return;
            input.value = '';
            send(txt);
        }

        function send(val, label) {
            addMsg(label || val, 'user');
            clearChips();
            showTyping();

            post('action=message&token=' + encodeURIComponent(token) + '&conversation_id=' + chatId + '&message=' + encodeURIComponent(val), function(d) {
                hideTyping();
                if (d.error) { addMsg(d.error, 'bot'); return; }
                currentStep = d.step || currentStep;
                if (d.message) addMsg(d.message, 'bot');

                if (d.type === 'final') {
                    document.getElementById('inputBar').style.display = 'none';
                    if (d.options) showChips(d.options);
                } else if (d.type === 'results_then_form') {
                    showInlineForm();
                    showChips([
                        {label: 'Autres criteres', value: 'autre', next: 40},
                        {label: 'J\'ai une question', value: 'go_question', next: 40}
                    ]);
                } else if (d.type === 'form') {
                    showInlineForm();
                } else if (d.options) {
                    showChips(d.options);
                }
            });
        }

        function addMsg(text, type) {
            if (!text) return;
            var d = document.createElement('div');
            d.className = 'msg msg-' + (type === 'user' ? 'user' : 'bot');
            var safe = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            d.innerHTML = safe.replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>').replace(/\n/g,'<br>');
            msgContainer.appendChild(d);
            msgContainer.scrollTop = msgContainer.scrollHeight;
        }

        function showChips(opts) {
            if (!opts || !opts.length) return;
            var w = document.createElement('div');
            w.className = 'chips';
            opts.forEach(function(o) {
                var b = document.createElement('button');
                b.className = 'chip';
                b.textContent = o.label;
                b.onclick = function() {
                    if (o.action === 'link' && o.url) { window.open(o.url, '_blank'); return; }
                    send(o.value, o.label);
                };
                w.appendChild(b);
            });
            msgContainer.appendChild(w);
            msgContainer.scrollTop = msgContainer.scrollHeight;
        }

        function clearChips() {
            var all = document.querySelectorAll('.chips');
            for (var i = 0; i < all.length; i++) all[i].style.display = 'none';
        }

        function showInlineForm() {
            if (document.getElementById('if-form')) return;
            var f = document.createElement('div');
            f.id = 'if-form';
            f.style.cssText = 'margin:8px 0;background:#fff;border:1.5px solid <?= $color ?>;border-radius:12px;padding:16px;animation:fadeIn .3s ease;';
            f.innerHTML =
                '<div style="font-weight:600;font-size:14px;color:<?= $color ?>;margin-bottom:12px;">Vos coordonnees</div>' +
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">' +
                    '<input type="text" id="if-prenom" placeholder="Prenom *" style="padding:8px 12px;border:1px solid #ddd;border-radius:8px;font-size:13px;font-family:inherit;outline:none;">' +
                    '<input type="text" id="if-nom" placeholder="Nom *" style="padding:8px 12px;border:1px solid #ddd;border-radius:8px;font-size:13px;font-family:inherit;outline:none;">' +
                '</div>' +
                '<div style="margin-bottom:8px;"><input type="email" id="if-email" placeholder="Email *" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:8px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;"></div>' +
                '<div style="margin-bottom:10px;"><input type="tel" id="if-tel" placeholder="Telephone * (06 12 34 56 78)" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:8px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;"></div>' +
                '<div id="if-error" style="display:none;color:#e74c3c;font-size:12px;margin-bottom:8px;"></div>' +
                '<button id="if-submit" style="width:100%;padding:10px;background:<?= $color ?>;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:700;">Envoyer</button>';
            msgContainer.appendChild(f);
            msgContainer.scrollTop = msgContainer.scrollHeight;
            document.getElementById('if-prenom').focus();
            document.getElementById('if-submit').onclick = submitForm;
            ['if-prenom','if-nom','if-email','if-tel'].forEach(function(id, i, arr) {
                document.getElementById(id).onkeypress = function(e) {
                    if (e.key === 'Enter') { e.preventDefault(); if (i < arr.length-1) document.getElementById(arr[i+1]).focus(); else submitForm(); }
                };
            });
        }

        function submitForm() {
            var prenom = (document.getElementById('if-prenom').value||'').trim();
            var nom = (document.getElementById('if-nom').value||'').trim();
            var email = (document.getElementById('if-email').value||'').trim();
            var tel = (document.getElementById('if-tel').value||'').trim();
            var err = document.getElementById('if-error');
            var errors = [];
            if (prenom.length<2) errors.push('Prenom');
            if (nom.length<2) errors.push('Nom');
            if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) errors.push('Email');
            if (!tel.match(/^0[1-9][\s.\-]?(\d{2}[\s.\-]?){4}$/)) errors.push('Telephone');
            if (errors.length) { err.textContent='Veuillez corriger : '+errors.join(', '); err.style.display='block'; return; }
            var btn = document.getElementById('if-submit');
            btn.textContent='Envoi...'; btn.style.opacity='0.6'; btn.disabled=true;
            var data = JSON.stringify({prenom:prenom,nom:nom,email:email,telephone:tel});
            post('action=form&token='+encodeURIComponent(token)+'&conversation_id='+chatId+'&data='+encodeURIComponent(data), function(d) {
                var form = document.getElementById('if-form'); if (form) form.style.display='none';
                if (d.error) { addMsg(d.error,'bot'); return; }
                currentStep = d.step||currentStep;
                if (d.message) addMsg(d.message,'bot');
                if (d.type==='final') { document.getElementById('inputBar').style.display='none'; if (d.options) showChips(d.options); }
            });
        }

        function showTyping() {
            if (document.getElementById('typing')) return;
            var d = document.createElement('div');
            d.id = 'typing';
            d.className = 'typing';
            d.innerHTML = '<span class="dot"></span><span class="dot"></span><span class="dot"></span>';
            msgContainer.appendChild(d);
            msgContainer.scrollTop = msgContainer.scrollHeight;
        }

        function hideTyping() {
            var e = document.getElementById('typing');
            if (e) e.remove();
        }

        function post(body, cb) {
            var x = new XMLHttpRequest();
            x.open('POST', apiUrl, true);
            x.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            x.timeout = 15000;
            x.onreadystatechange = function() {
                if (x.readyState === 4) {
                    if (x.status === 200) { try { cb(JSON.parse(x.responseText)); } catch(e) { cb({error:'Erreur'}); } }
                    else { cb({error:'Connexion impossible'}); }
                }
            };
            x.ontimeout = function() { cb({error:'Delai depasse'}); };
            x.send(body);
        }

        // Init
        showTyping();
        post('action=init&token=' + encodeURIComponent(token), function(d) {
            hideTyping();
            if (d.error) { addMsg(d.error, 'bot'); return; }
            chatId = d.conversation_id;
            currentStep = d.step || 1;
            if (d.is_new) {
                addMsg(d.message, 'bot');
                if (d.options) showChips(d.options);
            } else if (d.history && d.history.length) {
                d.history.forEach(function(m) { addMsg(m.message, m.type); });
            }
        });
    })();
    </script>
</body>
</html>
