<?php
declare(strict_types=1);
session_start();

include '../config.php';
require_once __DIR__ . '/../includes/rpi_db.php';
$pdo = getRpiPdo();
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require __DIR__ . '/../../vendor/autoload.php';
}

function db(): PDO {
    global $pdo;
    return $pdo;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function requireLogin(): void {
    if (empty($_SESSION['agent_user'])) {
        header("Location: ?login=1");
        exit;
    }
}

/**
 * 🔗 Liens outils centralisés
 */
$links = [
    "SuperHote (login : julien / mdp : motdepassefort)" => "https://app.superhote.com/#/calendar/month",
    "Réservations (serveur)" => "http://109.219.194.30/pages/reservation_list.php",
    "Reçus (serveur)"        => "http://109.219.194.30/pages/recus.php",
    "Planning (Frenchy)"     => "https://gestion.frenchyconciergerie.fr/pages/planning.php",
    "Superhote Messagerie"   => "https://app.superhote.com/#/messages",

];

/**
 * Tarifs (table agent_action_rates)
 */
function getRates(): array {
    $pdo = db();
    $stmt = $pdo->query("SELECT action_type, rate_eur FROM agent_action_rates");
    $rates = [];
    foreach ($stmt as $row) {
        $rates[(string)$row['action_type']] = (float)$row['rate_eur'];
    }
    return $rates;
}

$actionTypesDefault = [
    "SMS_envoye",
    "SMS_recu_repondu",
    "Airbnb_reponse",
    "Booking_reponse",
    "Pulse_reponse",
    "Demande_enregistree",
    "Relance_upsell",
    "Autre"
];

/**
 * ✅ Import / liste des réservations depuis reservation + JOIN liste_logements
 * - confirmées
 * - date_arrivee >= (today - 7 jours)
 * - tri par date_arrivee ASC
 * - limite 250
 */
function fetchReservations(): array {
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.reference,
            r.prenom,
            r.nom,
            r.telephone,
            r.plateforme,
            r.logement_id,
            r.date_arrivee,
            r.date_depart,
            l.nom_du_logement
        FROM reservation r
        LEFT JOIN liste_logements l ON l.id = r.logement_id
        WHERE (r.statut = 'confirmée' OR r.statut IS NULL)
          AND (r.date_arrivee IS NULL OR r.date_arrivee >= DATE_SUB(CURDATE(), INTERVAL 7 DAY))
        ORDER BY
          (r.date_arrivee IS NULL) ASC,
          r.date_arrivee ASC,
          r.id DESC
        LIMIT 250
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * LOGOUT
 */
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ?");
    exit;
}

/**
 * LOGIN
 */
if (isset($_GET['login']) || empty($_SESSION['agent_user'])) {
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_login'])) {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $pdo2 = db();
        $stmt = $ros = $pdo2->prepare("SELECT id, username, password_hash, is_active FROM agent_users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$u || (int)$u['is_active'] !== 1 || !password_verify($password, (string)$u['password_hash'])) {
            $error = "Identifiants invalides.";
        } else {
            $_SESSION['agent_user'] = ['id' => (int)$u['id'], 'username' => (string)$u['username']];
            header("Location: ?");
            exit;
        }
    }
    ?>
    <!doctype html>
    <html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Agent Com - Connexion</title>
        <style>
            body{font-family:Arial,sans-serif;background:#f6f7fb;margin:0;padding:30px;}
            .card{max-width:420px;margin:0 auto;background:#fff;border-radius:12px;padding:20px;box-shadow:0 8px 24px rgba(0,0,0,.08);}
            label{display:block;margin:12px 0 6px;}
            input{width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;}
            button{margin-top:14px;width:100%;padding:10px;border:0;border-radius:10px;cursor:pointer;}
            .err{color:#b00020;margin-top:10px;}
            .muted{opacity:.7;font-size:12px;}
        </style>
    </head>
    <body>
        <div class="card">
            <h2>Connexion Agent</h2>
            <form method="post">
                <input type="hidden" name="do_login" value="1">
                <label>Utilisateur</label>
                <input name="username" required>
                <label>Mot de passe</label>
                <input type="password" name="password" required>
                <button type="submit">Se connecter</button>
                <?php if ($error): ?><div class="err"><?=h($error)?></div><?php endif; ?>
            </form>
            <p class="muted" style="margin-top:16px;">
                Si besoin, crée l'utilisateur via SQL dans <b>agent_users</b>.
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * DASHBOARD
 */
requireLogin();

$pdo3 = db();
$rates = getRates();
$actionTypes = array_keys($rates);
if (!$actionTypes) $actionTypes = $actionTypesDefault;

$reservations = fetchReservations();

/**
 * ENREGISTRER UNE ACTION
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_action'])) {
    $action_type = trim((string)($_POST['action_type'] ?? 'Autre'));
    $channel = (string)($_POST['channel'] ?? 'autre');

    $reservation_ref = trim((string)($_POST['reservation_ref'] ?? ''));
    $logement = trim((string)($_POST['logement'] ?? ''));
    $client_name = trim((string)($_POST['client_name'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    if (!in_array($action_type, $actionTypes, true)) $action_type = "Autre";
    $allowedChannels = ['sms','airbnb','booking','pulse','autre'];
    if (!in_array($channel, $allowedChannels, true)) $channel = "autre";

    $stmt = $pdo3->prepare("
        INSERT INTO agent_actions (agent_user_id, action_type, channel, reservation_ref, logement, client_name, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['agent_user']['id'],
        $action_type,
        $channel,
        $reservation_ref !== '' ? $reservation_ref : null,
        $logement !== '' ? $logement : null,
        $client_name !== '' ? $client_name : null,
        $notes !== '' ? $notes : null,
    ]);

    header("Location: ?saved=1");
    exit;
}

/**
 * REPORTING période
 */
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-t');

$fromDT = $from . " 00:00:00";
$toDT   = $to   . " 23:59:59";

$stmt = $pdo3->prepare("
    SELECT a.*
    FROM agent_actions a
    WHERE a.agent_user_id = ?
      AND a.created_at BETWEEN ? AND ?
    ORDER BY a.id DESC
    LIMIT 200
");
$stmt->execute([$_SESSION['agent_user']['id'], $fromDT, $toDT]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalActions = count($rows);
$totalEur = 0.0;
foreach ($rows as $r) {
    $totalEur += (float)($rates[(string)$r['action_type']] ?? 0.0);
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agent Com - Dashboard</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f6f7fb;margin:0;padding:18px;}
        .wrap{max-width:1100px;margin:0 auto;display:grid;gap:14px;grid-template-columns: 420px 1fr;}
        .card{background:#fff;border-radius:14px;padding:16px;box-shadow:0 8px 24px rgba(0,0,0,.08);}
        h2,h3{margin:0 0 10px;}
        .links a{display:block;padding:10px;border:1px solid #eee;border-radius:12px;text-decoration:none;margin:8px 0;color:#111;background:#fafafa;}
        .links a:hover{background:#f2f2f2}
        label{display:block;margin:10px 0 6px;font-size:13px;opacity:.85;}
        input,select,textarea{width:100%;padding:10px;border:1px solid #ddd;border-radius:12px;}
        textarea{min-height:90px;}
        button{padding:10px 12px;border:0;border-radius:12px;cursor:pointer;}
        .row{display:flex;gap:10px;}
        .row > div{flex:1;}
        table{width:100%;border-collapse:collapse;font-size:13px;}
        th,td{padding:8px;border-bottom:1px solid #eee;vertical-align:top;}
        .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;}
        .pill{display:inline-block;padding:6px 10px;border-radius:999px;background:#f0f3ff;}
        .muted{opacity:.7}
        .ok{margin-top:10px;opacity:.75}
        .kpi{display:flex;gap:10px;margin:10px 0 0}
        .kpi > div{background:#fafafa;border:1px solid #eee;padding:10px;border-radius:12px;flex:1}
        .kpi strong{font-size:16px}
        .logout a{color:#111;text-decoration:none}
        .hint{font-size:12px;opacity:.65;margin-top:6px}
    </style>
</head>
<body>

<div class="topbar">
    <div>
        <strong>Agent :</strong> <?=h($_SESSION['agent_user']['username'])?>
        <span class="pill">Période : <?=h($from)?> → <?=h($to)?></span>
    </div>
    <div class="logout"><a href="?logout=1">Se déconnecter</a></div>
</div>

<div class="wrap">

    <div class="card">
        <h2>Outils</h2>
        <div class="links">
            <?php foreach ($links as $label => $url): ?>
                <a target="_blank" rel="noopener" href="<?=h($url)?>"><?=h($label)?></a>
            <?php endforeach; ?>
        </div>

        <hr style="border:0;border-top:1px solid #eee;margin:14px 0;">

        <h3>Enregistrer une action</h3>
        <form method="post" id="actionForm">
            <input type="hidden" name="save_action" value="1">

            <label>Réservation</label>
            <select id="reservation_select">
                <option value="">— Choisir une réservation (auto-remplissage) —</option>
                <?php foreach ($reservations as $r): ?>
                    <?php
                        $ref = (string)($r['reference'] ?? '');
                        $fullName = trim((string)$r['prenom'] . ' ' . (string)$r['nom']);
                        $arr = (string)($r['date_arrivee'] ?? '');
                        $dep = (string)($r['date_depart'] ?? '');
                        $logementName = (string)($r['nom_du_logement'] ?? '');
                        $plateforme = (string)($r['plateforme'] ?? '');
                        $tel = (string)($r['telephone'] ?? '');

                        $label = trim(
                            ($ref !== '' ? $ref . ' — ' : '') .
                            ($fullName !== '' ? $fullName : 'Client') .
                            ($arr ? " ($arr→$dep)" : '') .
                            ($logementName !== '' ? " — " . $logementName : '') .
                            ($plateforme !== '' ? " — $plateforme" : '')
                        );
                    ?>
                    <option
                        value="<?=h((string)$r['id'])?>"
                        data-reference="<?=h($ref)?>"
                        data-client="<?=h($fullName)?>"
                        data-logement="<?=h($logementName)?>"
                        data-plateforme="<?=h($plateforme)?>"
                        data-tel="<?=h($tel)?>"
                        data-arr="<?=h($arr)?>"
                        data-dep="<?=h($dep)?>"
                    ><?=h($label)?></option>
                <?php endforeach; ?>
            </select>
            <div class="hint">Choisis une réservation pour remplir automatiquement Réf / Logement / Client.</div>

            <label>Type d’action</label>
            <select name="action_type" required>
                <?php foreach ($actionTypes as $t): ?>
                    <option value="<?=h($t)?>"><?=h($t)?> (<?=number_format((float)($rates[$t] ?? 0), 2)?> €)</option>
                <?php endforeach; ?>
            </select>

            <label>Canal</label>
            <select name="channel" required>
                <option value="sms">sms</option>
                <option value="airbnb">airbnb</option>
                <option value="booking">booking</option>
                <option value="pulse">pulse</option>
                <option value="autre">autre</option>
            </select>

            <div class="row">
                <div>
                    <label>Réf réservation (optionnel)</label>
                    <input name="reservation_ref" id="reservation_ref" placeholder="ex: AB-123 / BK-987">
                </div>
                <div>
                    <label>Logement (optionnel)</label>
                    <input name="logement" id="logement" placeholder="ex: Château Vertefeuille">
                </div>
            </div>

            <label>Nom client (optionnel)</label>
            <input name="client_name" id="client_name" placeholder="ex: Mme Dupont">

            <label>Notes / demande enregistrée</label>
            <textarea name="notes" id="notes" placeholder="Résumé + réponse + prochaine action"></textarea>

            <button type="submit">✅ Valider l’action</button>
            <?php if (isset($_GET['saved'])): ?>
                <div class="ok">Action enregistrée.</div>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h2>Suivi & Paiement</h2>

        <form method="get" class="row" style="align-items:end;margin-bottom:10px;">
            <div>
                <label>Du</label>
                <input type="date" name="from" value="<?=h($from)?>">
            </div>
            <div>
                <label>Au</label>
                <input type="date" name="to" value="<?=h($to)?>">
            </div>
            <div style="flex:0 0 160px;">
                <button type="submit">Filtrer</button>
            </div>
        </form>

        <div class="kpi">
            <div>
                <div class="muted">Total actions</div>
                <strong><?=h((string)$totalActions)?></strong>
            </div>
            <div>
                <div class="muted">Total à payer</div>
                <strong><?=number_format($totalEur, 2)?> €</strong>
            </div>
        </div>

        <div style="margin-top:12px;"></div>

        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Action</th>
                <th>Canal</th>
                <th>Réf / Logement</th>
                <th>Client / Notes</th>
                <th>€</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <?php $eur = (float)($rates[(string)$r['action_type']] ?? 0); ?>
                <tr>
                    <td><?=h((string)$r['created_at'])?></td>
                    <td><?=h((string)$r['action_type'])?></td>
                    <td><?=h((string)$r['channel'])?></td>
                    <td>
                        <div><?=h((string)($r['reservation_ref'] ?? ''))?></div>
                        <div class="muted"><?=h((string)($r['logement'] ?? ''))?></div>
                    </td>
                    <td>
                        <div><strong><?=h((string)($r['client_name'] ?? ''))?></strong></div>
                        <div class="muted"><?=nl2br(h((string)($r['notes'] ?? '')))?></div>
                    </td>
                    <td><?=number_format($eur, 2)?> €</td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="muted">Aucune action sur la période.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <p class="muted" style="margin-top:10px;">
            Historique limité à 200 lignes.
        </p>
    </div>

</div>

<script>
(function(){
  const sel = document.getElementById('reservation_select');
  const ref = document.getElementById('reservation_ref');
  const logement = document.getElementById('logement');
  const client = document.getElementById('client_name');
  const notes = document.getElementById('notes');

  if(!sel) return;

  // Flag: notes modifiées manuellement
  let notesTouched = false;

  if (notes) {
    notes.addEventListener('input', function(){
      // Dès que quelqu’un tape dans notes, on considère que c’est manuel
      notesTouched = true;
      notes.dataset.autofill = "0";
    });
  }

  function buildNotes(opt){
    const plateforme = opt.getAttribute('data-plateforme') || '';
    const tel = opt.getAttribute('data-tel') || '';
    const arr = opt.getAttribute('data-arr') || '';
    const dep = opt.getAttribute('data-dep') || '';

    let base = [];
    if(plateforme) base.push("Plateforme: " + plateforme);
    if(tel) base.push("Tel: " + tel);
    if(arr || dep) base.push("Séjour: " + (arr || '?') + " → " + (dep || '?'));

    return base.join(" | ");
  }

  sel.addEventListener('change', function(){
    const opt = sel.options[sel.selectedIndex];
    if(!opt || !opt.value) return;

    const reference = opt.getAttribute('data-reference') || '';
    const clientName = opt.getAttribute('data-client') || '';
    const logementName = opt.getAttribute('data-logement') || '';

    if(reference) ref.value = reference;
    if(logementName) logement.value = logementName;
    if(clientName) client.value = clientName;

    // ✅ Notes:
    // - Si jamais modifiées manuellement => on n’écrase pas
    // - Sinon => on met à jour à chaque changement de réservation
    if (notes && !notesTouched) {
      notes.value = buildNotes(opt);
      notes.dataset.autofill = "1";
    }
  });

  // Optionnel: si tu veux forcer le remplissage dès la 1ère sélection même si notes vide
  // et permettre un reset manuel:
  // Ajoute un bouton "Réinitialiser notes auto" si tu veux (je te le fais si besoin).

})();
</script>


</body>
</html>
