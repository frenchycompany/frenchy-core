<?php
// pages/clients_resa.php — ETAPE 2 (SMS+)
// - Préremplissages / Modèles de message (arrivée, départ, code, retard…)
// - Historique conversation latéral (drawer) consommant get_conversation.php
// - Lier l’envoi à une réservation (reservation_id) avec auto-suggestion
// - Aucune suppression de fonctionnalités existantes


require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';

// Récupère le handle PDO ($conn ou $pdo selon ton db.php)
if (isset($conn) && $conn instanceof PDO) {
    $db = $conn;
} elseif (isset($pdo) && $pdo instanceof PDO) {
    $db = $pdo;
} else {
    http_response_code(500);
    die('Aucune connexion PDO disponible depuis includes/db.php (attendu $conn ou $pdo).');
}

// ─────────────────────────────────────────────────────────────────────────────
// Utils
// ─────────────────────────────────────────────────────────────────────────────
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
/** Normalisation FR simple -> E.164 */
function phone_normalize_php(?string $raw): string {
    if (!$raw) return '';
    $p = preg_replace('/[()\.\s-]+/', '', $raw);
    if (strpos($p, '00') === 0) $p = '+' . substr($p, 2);
    if (strlen($p) === 10 && $p[0] === '0') return '+33' . substr($p, 1);
    if (strlen($p) === 11 && substr($p, 0, 2) === '33') return '+' . $p;
    if (substr($p, 0, 1) === '+') return $p;
    return $p;
}
/** Expression SQL équivalente à la normalisation ci-dessus */
function sql_phone_norm_expr($col = 'telephone'){
    return "
    CASE
      WHEN LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($col,' ',''),'.',''),'-',''),'(',''),')',''), 2) = '00'
        THEN CONCAT('+', SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($col,' ',''),'.',''),'-',''),'(',''),')',''), 3))
      WHEN LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($col,' ',''),'.',''),'-',''),'(',''),')',''), 1) = '0'
           AND LENGTH(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($col,' ',''),'.',''),'-',''),'(',''),')','')) = 10
        THEN CONCAT('+33', SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($col,' ',''),'.',''),'-',''),'(',''),')',''), 2))
      WHEN LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($col,' ',''),'.',''),'-',''),'(',''),')',''), 2) = '33'
           AND LENGTH(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($col,' ',''),'.',''),'-',''),'(',''),')','')) = 11
        THEN CONCAT('+', REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($col,' ',''),'.',''),'-',''),'(',''),')',''))
      ELSE
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($col,' ',''),'.',''),'-',''),'(',''),')','')
    END
    ";
}

// ─────────────────────────────────────────────────────────────────────────────
// API légère pour auto-suggestion de réservations par téléphone normalisé
// ─────────────────────────────────────────────────────────────────────────────
$phoneExpr = sql_phone_norm_expr('r.telephone');

if (isset($_GET['api']) && $_GET['api'] === 'reservations_by_phone') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $ph = phone_normalize_php($_GET['phone'] ?? '');
        if ($ph === '') {
            echo json_encode(['success'=>false,'message'=>'phone manquant']); exit;
        }
        $w = "WHERE r.telephone IS NOT NULL AND r.telephone <> '' AND $phoneExpr = :ph";
        $sql = "
          SELECT r.id, r.reference, r.prenom, r.nom, r.logement_id,
                 r.date_arrivee, r.heure_arrivee, r.date_depart, r.heure_depart,
                 r.statut
          FROM reservation r
          $w
          ORDER BY r.date_arrivee DESC, r.id DESC
          LIMIT 100
        ";
        $st = $db->prepare($sql);
        $st->execute([':ph'=>$ph]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'reservations'=>$rows], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        exit;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Paramètres
// ─────────────────────────────────────────────────────────────────────────────
$q              = trim($_GET['q'] ?? '');
$onlyMulti      = isset($_GET['only_multi']) ? 1 : 0;       // ≥2 résa
$onlyConfirmed  = isset($_GET['only_confirmed']) ? 1 : 0;   // statut = confirmée
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = min(200, max(10, (int)($_GET['per_page'] ?? 50)));
$exportCsv      = isset($_GET['export']) && $_GET['export'] === 'csv';
$viewPhoneParam = $_GET['phone'] ?? '';
$viewPhoneNorm  = phone_normalize_php($viewPhoneParam);

// ─────────────────────────────────────────────────────────────────────────────
// Vue DÉTAIL (avec drawer conversation + modèles + lien reservation_id)
// ─────────────────────────────────────────────────────────────────────────────
if ($viewPhoneNorm !== '') {
    $where = "WHERE r.telephone IS NOT NULL AND r.telephone <> '' AND $phoneExpr = :ph";
    if ($onlyConfirmed) $where .= " AND r.statut = 'confirmée'";

    $sql = "
      SELECT
        r.id, r.reference, r.logement_id, r.date_reservation, r.date_arrivee, r.heure_arrivee,
        r.date_depart, r.heure_depart, r.statut, r.prenom, r.nom, r.email, r.plateforme,
        $phoneExpr AS phone_norm
      FROM reservation r
      $where
      ORDER BY r.date_arrivee DESC, r.id DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([':ph' => $viewPhoneNorm]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $nb = count($rows);
    $first = $nb ? $rows[array_key_last($rows)]['date_arrivee'] : null;
    $last  = $nb ? $rows[0]['date_arrivee'] : null;

    $nameFromRow = function($r) {
        $n = trim(($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? ''));
        return $n !== '' ? $n : 'Voyageur';
    };
    $displayName = $nb ? $nameFromRow($rows[0]) : 'Voyageur';

    // Valeurs utiles pour modèles
    $ctxPrenom  = $nb ? trim($rows[0]['prenom'] ?? '') : '';
    $ctxArrivee = $nb ? trim($rows[0]['date_arrivee'] . (empty($rows[0]['heure_arrivee'])?'':' '.$rows[0]['heure_arrivee'])) : '';
    $ctxDepart  = $nb ? trim($rows[0]['date_depart'] . (empty($rows[0]['heure_depart'])?'':' '.$rows[0]['heure_depart'])) : '';
    $ctxCode    = ''; // à remplir si tu stockes des codes ailleurs (ex: serrure)

    ?>
    <!doctype html>
    <html lang="fr">
    <head>
      <meta charset="utf-8">
      <title>Client — <?= e($viewPhoneNorm) ?></title>
      <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
      <style>
        .drawer {
          position: fixed; top:0; right:-420px; width: 400px; height:100vh; background:#fff; box-shadow:-2px 0 12px rgba(0,0,0,0.1);
          transition: right .25s ease; z-index: 1050; display:flex; flex-direction:column;
        }
        .drawer.open { right:0; }
        .drawer-header { padding:12px 16px; border-bottom:1px solid #e9ecef; }
        .drawer-body   { flex:1; overflow:auto; background:#f8f9fa; padding:12px; }
        .drawer-footer { padding:12px; border-top:1px solid #e9ecef; background:#fff; }
        .bubble { padding:10px 14px; border-radius:16px; margin-bottom:10px; max-width: 85%; box-shadow:0 1px 2px rgba(0,0,0,0.06); }
        .bubble.in  { background:#e9ecef; color:#343a40; margin-right:auto; border-bottom-left-radius:4px; }
        .bubble.out { background:#007bff; color:#fff; margin-left:auto; border-bottom-right-radius:4px; }
        .meta { font-size: .75rem; opacity: .75; margin-top:6px; text-align:right; }
        .raw-pdu { font-family: monospace; font-size:.75rem; color:#dc3545; word-break: break-all;}
        .smallmuted { font-size:.8rem; color:#6c757d; }
      </style>
    </head>
    <body class="bg-light p-3">
      <div class="container-fluid">
        <div class="d-flex align-items-center mb-3">
          <a href="<?= e($_SERVER['PHP_SELF']) ?>?<?= $onlyConfirmed ? 'only_confirmed=1&' : '' ?>" class="btn btn-secondary btn-sm mr-2">← Retour</a>
          <h4 class="mb-0">Voyageur : <?= e($viewPhoneNorm) ?></h4>
          <div class="ml-auto">
            <a class="btn btn-info btn-sm"
               href="./sms.php?view=conversations&sender=<?= urlencode($viewPhoneNorm) ?>">
               💬 Ouvrir conversation SMS
            </a>
            <button class="btn btn-primary btn-sm" id="btnOpenDrawer">Historique & Envoi</button>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-body">
            <div class="row">
              <div class="col-md-3"><strong>Réservations</strong> : <?= e($nb) ?></div>
              <div class="col-md-3"><strong>Première arrivée</strong> : <?= e($first ?? '—') ?></div>
              <div class="col-md-3"><strong>Dernière arrivée</strong> : <?= e($last ?? '—') ?></div>
              <div class="col-md-3"><strong>Nom courant</strong> : <?= e($displayName) ?></div>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-striped">
            <thead class="thead-light">
              <tr>
                <th>#</th>
                <th>Référence</th>
                <th>Plateforme</th>
                <th>Nom</th>
                <th>Email</th>
                <th>Arrivée</th>
                <th>Départ</th>
                <th>Logement</th>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= e($r['id']) ?></td>
                <td><?= e($r['reference']) ?></td>
                <td><?= e($r['plateforme']) ?></td>
                <td><?= e(trim(($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? ''))) ?></td>
                <td><?= e($r['email']) ?></td>
                <td><?= e($r['date_arrivee'] . ($r['heure_arrivee'] ? ' '.$r['heure_arrivee'] : '')) ?></td>
                <td><?= e($r['date_depart'] . ($r['heure_depart'] ? ' '.$r['heure_depart'] : '')) ?></td>
                <td><?= e($r['logement_id']) ?></td>
                <td><?= e($r['statut']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </div>

      <!-- Drawer latéral conversation + envoi -->
      <div id="smsDrawer" class="drawer" aria-hidden="true">
        <div class="drawer-header d-flex align-items-center">
          <strong>SMS — <?= e($viewPhoneNorm) ?></strong>
          <button class="btn btn-sm btn-outline-secondary ml-auto" id="btnCloseDrawer">Fermer</button>
        </div>
        <div id="drawerBody" class="drawer-body">
          <div class="text-center text-muted small">Chargement de la conversation…</div>
        </div>
        <div class="drawer-footer">
          <div class="form-group mb-2">
            <label class="small">Modèle</label>
            <select id="tplSelect" class="form-control form-control-sm">
              <option value="">— Aucun —</option>
              <option value="arrivee">Arrivée</option>
              <option value="depart">Départ</option>
              <option value="code">Code d'accès</option>
              <option value="retard">Retard / ETA</option>
              <option value="libre">Message libre</option>
            </select>
            <small class="smallmuted">Variables dispo : {prenom} {arrivee} {depart} {code}</small>
          </div>
          <div class="form-group mb-2">
            <label class="small">Lier à une réservation (optionnel)</label>
            <select id="reservationSelect" class="form-control form-control-sm">
              <option value="">— Aucune —</option>
            </select>
          </div>
          <div class="form-group mb-2">
            <textarea id="smsText" rows="4" class="form-control" placeholder="Votre message…"></textarea>
          </div>
          <div class="d-flex align-items-center">
            <small class="smallmuted mr-auto" id="smsHint">Modem: /dev/ttyUSB0</small>
            <button id="btnSendSms" class="btn btn-primary btn-sm">Envoyer</button>
          </div>
        </div>
      </div>

      <script>
        // Contexte pour modèles
        const CTX = {
          phone: <?= json_encode($viewPhoneNorm) ?>,
          prenom: <?= json_encode($ctxPrenom) ?>,
          arrivee: <?= json_encode($ctxArrivee) ?>,
          depart: <?= json_encode($ctxDepart) ?>,
          code: <?= json_encode($ctxCode) ?>
        };

        const drawer = document.getElementById('smsDrawer');
        const drawerBody = document.getElementById('drawerBody');
        const btnOpenDrawer = document.getElementById('btnOpenDrawer');
        const btnCloseDrawer = document.getElementById('btnCloseDrawer');
        const tplSelect = document.getElementById('tplSelect');
        const smsText = document.getElementById('smsText');
        const reservationSelect = document.getElementById('reservationSelect');
        const btnSend = document.getElementById('btnSendSms');

        const TEMPLATES = {
          arrivee: "Bonjour {prenom},\nVotre arrivée est prévue le {arrivee}. N'hésitez pas à me confirmer votre heure d'arrivée. Bonne route !",
          depart:  "Bonjour {prenom},\nPetit rappel : départ le {depart}. Merci de laisser les clés sur la table et d'éteindre les lumières. Bonne journée !",
          code:    "Bonjour {prenom},\nVoici le code d'accès : {code}. Le logement sera prêt à partir de {arrivee}.",
          retard:  "Bonjour {prenom},\nAvez-vous une estimation de votre heure d'arrivée ? Nous ajusterons la préparation si besoin.",
          libre:   ""
        };

        function applyTemplate(key){
          const raw = TEMPLATES[key] ?? '';
          const out = raw
            .replace(/{prenom}/g, CTX.prenom || 'vous')
            .replace(/{arrivee}/g, CTX.arrivee || '')
            .replace(/{depart}/g, CTX.depart || '')
            .replace(/{code}/g, CTX.code || '');
          smsText.value = out;
        }

        async function loadReservationsForPhone(phone){
          try{
            const url = new URL(window.location.href);
            url.search = '';
            url.pathname = "<?= e($_SERVER['PHP_SELF']) ?>";
            url.searchParams.set('api','reservations_by_phone');
            url.searchParams.set('phone', phone);
            const resp = await fetch(url.toString(), { headers: { 'Accept':'application/json' }});
            const data = await resp.json();
            if (!data.success) throw new Error(data.message || 'Erreur API');
            reservationSelect.innerHTML = '<option value="">— Aucune —</option>';
            data.reservations.forEach(r => {
              const txt = `#${r.id} — ${r.date_arrivee}${r.heure_arrivee?' '+r.heure_arrivee:''} → ${r.date_depart}${r.heure_depart?' '+r.heure_depart:''}` +
                          (r.reference ? ' — ' + r.reference : '');
              const opt = document.createElement('option');
              opt.value = r.id;
              opt.textContent = txt;
              reservationSelect.appendChild(opt);
            });
            // Ajuster le contexte pour modèles (dernière résa connue)
            if (data.reservations.length > 0) {
              const r = data.reservations[0];
              CTX.prenom  = (r.prenom || CTX.prenom || '');
              CTX.arrivee = (r.date_arrivee || '') + (r.heure_arrivee ? ' ' + r.heure_arrivee : '');
              CTX.depart  = (r.date_depart || '')  + (r.heure_depart ? ' ' + r.heure_depart : '');
            }
          }catch(e){
            console.warn('reservations_by_phone error:', e);
          }
        }

        function openDrawer(){
          drawer.classList.add('open');
          drawer.setAttribute('aria-hidden','false');
          loadConversation(CTX.phone);
          loadReservationsForPhone(CTX.phone);
        }
        function closeDrawer(){
          drawer.classList.remove('open');
          drawer.setAttribute('aria-hidden','true');
        }

        btnOpenDrawer.addEventListener('click', openDrawer);
        btnCloseDrawer.addEventListener('click', closeDrawer);

        tplSelect.addEventListener('change', () => {
          applyTemplate(tplSelect.value);
        });

        async function loadConversation(phone){
          drawerBody.innerHTML = '<div class="text-center text-muted small">Chargement de la conversation…</div>';
          try{
            const resp = await fetch('get_conversation.php?sender=' + encodeURIComponent(phone), { headers: { 'Accept':'application/json' }});
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const rawText = await resp.text();
            let list;
            try { list = JSON.parse(rawText); } catch(e){ throw new Error('Réponse non JSON : ' + rawText.slice(0,200)); }
            if (!Array.isArray(list)) list = (list && Array.isArray(list.messages)) ? list.messages : [];
            const frag = document.createDocumentFragment();

            const renderBubble = (m) => {
              const dir = (m && m.direction === 'in') ? 'in' : 'out';
              const msg = String((m && m.message)!=null ? m.message : '');
              const isPdu = msg.startsWith(',145,') || /^[0-9A-F]{10,}/i.test(msg);
              const wrap = document.createElement('div');
              wrap.className = 'bubble ' + (dir==='in'?'in':'out');
              const txt = document.createElement('div');
              txt.className = isPdu ? 'raw-pdu' : '';
              txt.textContent = isPdu ? ('[Message non décodé: ' + msg + ']') : msg;
              const meta = document.createElement('div');
              meta.className = 'meta';
              const d = (m && m.date) ? m.date : new Date().toISOString();
              meta.textContent = d;
              wrap.appendChild(txt); wrap.appendChild(meta);
              return wrap;
            };

            if (list.length === 0) {
              const empty = document.createElement('div');
              empty.className = 'text-center text-muted small';
              empty.textContent = 'Aucun message pour ce numéro.';
              frag.appendChild(empty);
            } else {
              list.forEach(m => frag.appendChild(renderBubble(m)));
            }
            drawerBody.innerHTML = '';
            drawerBody.appendChild(frag);
            drawerBody.scrollTop = drawerBody.scrollHeight;
          }catch(e){
            drawerBody.innerHTML = '<div class="text-danger small">Erreur chargement: ' + (e.message || e) + '</div>';
          }
        }

        btnSend.addEventListener('click', async () => {
          const text = (smsText.value || '').trim();
          if (!text) { alert('Message vide'); return; }
          const resId = reservationSelect.value ? parseInt(reservationSelect.value, 10) : null;
          btnSend.disabled = true; btnSend.textContent = '...';
          try{
            const resp = await fetch('send_sms.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                receiver: CTX.phone,
                message: text,
                reservation_id: resId,
                modem: '/dev/ttyUSB0'
              })
            });
            const result = await resp.json();
            if (!resp.ok || !result.success) throw new Error(result.message || ('HTTP ' + resp.status));
            // Ajout local de la bulle envoyée
            const now = new Date().toISOString();
            const wrap = document.createElement('div');
            wrap.className = 'bubble out';
            const txt = document.createElement('div');
            txt.textContent = text;
            const meta = document.createElement('div');
            meta.className = 'meta'; meta.textContent = now + ' (queued)';
            wrap.appendChild(txt); wrap.appendChild(meta);
            drawerBody.appendChild(wrap);
            drawerBody.scrollTop = drawerBody.scrollHeight;
            smsText.value = '';
            alert('SMS ajouté à la file (id ' + result.id + ').');
          }catch(e){
            alert('Erreur envoi: ' + (e.message || e));
          }finally{
            btnSend.disabled = false; btnSend.textContent = 'Envoyer';
          }
        });
      </script>
    </body>
    </html>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Vue LISTE groupée — Ajout boutons SMS : Conversation/Envoyer (réutilise drawer global)
// ─────────────────────────────────────────────────────────────────────────────
$where = "WHERE r.telephone IS NOT NULL AND r.telephone <> ''";
$params = [];
if ($onlyConfirmed) $where .= " AND r.statut = 'confirmée'";
if ($q !== '') {
    $where .= " AND (
        r.prenom LIKE :q OR r.nom LIKE :q OR r.email LIKE :q
        OR r.telephone LIKE :q OR $phoneExpr LIKE :q
    )";
    $params[':q'] = '%'.$q.'%';
}

// Count pour pagination
$countSql = "
  SELECT COUNT(*) AS total FROM (
    SELECT $phoneExpr AS phone_norm
    FROM reservation r
    $where
    GROUP BY phone_norm
    " . ($onlyMulti ? "HAVING COUNT(*) >= 2" : "") . "
  ) t
";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$offset     = ($page - 1) * $perPage;

// Liste paginée
$listSql = "
  SELECT
    $phoneExpr                                 AS phone_norm,
    COUNT(*)                                   AS nb_resa,
    MIN(r.date_arrivee)                        AS first_arrivee,
    MAX(r.date_arrivee)                        AS last_arrivee,
    GROUP_CONCAT(DISTINCT NULLIF(CONCAT(TRIM(COALESCE(r.prenom,'')), ' ', TRIM(COALESCE(r.nom,''))), ' ') SEPARATOR ' / ') AS names,
    GROUP_CONCAT(DISTINCT NULLIF(r.email,'') SEPARATOR ' / ') AS emails,
    GROUP_CONCAT(DISTINCT NULLIF(r.plateforme,'') SEPARATOR ' / ') AS plateformes
  FROM reservation r
  $where
  GROUP BY phone_norm
  " . ($onlyMulti ? "HAVING COUNT(*) >= 2" : "") . "
  ORDER BY nb_resa DESC, last_arrivee DESC
  LIMIT :lim OFFSET :off
";
$listStmt = $db->prepare($listSql);
foreach ($params as $k=>$v) $listStmt->bindValue($k, $v);
$listStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':off', $offset, PDO::PARAM_INT);
$listStmt->execute();
$data = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// Export CSV
if ($exportCsv) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=clients_resa.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['phone_norm','nb_reservations','premiere_arrivee','derniere_arrivee','noms','emails','plateformes'], ';');
    foreach ($data as $row) {
        fputcsv($out, [
            $row['phone_norm'],
            $row['nb_resa'],
            $row['first_arrivee'],
            $row['last_arrivee'],
            $row['names'],
            $row['emails'],
            $row['plateformes'],
        ], ';');
    }
    fclose($out);
    exit;
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Voyageurs (groupés par téléphone)</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
    .badge-pill{ border-radius: 999px; }
    .table td, .table th{ vertical-align: middle; }
    .nowrap{ white-space: nowrap; }
    .drawer {
      position: fixed; top:0; right:-420px; width: 400px; height:100vh; background:#fff; box-shadow:-2px 0 12px rgba(0,0,0,0.1);
      transition: right .25s ease; z-index: 1050; display:flex; flex-direction:column;
    }
    .drawer.open { right:0; }
    .drawer-header { padding:12px 16px; border-bottom:1px solid #e9ecef; }
    .drawer-body   { flex:1; overflow:auto; background:#f8f9fa; padding:12px; }
    .drawer-footer { padding:12px; border-top:1px solid #e9ecef; background:#fff; }
    .bubble { padding:10px 14px; border-radius:16px; margin-bottom:10px; max-width: 85%; box-shadow:0 1px 2px rgba(0,0,0,0.06); }
    .bubble.in  { background:#e9ecef; color:#343a40; margin-right:auto; border-bottom-left-radius:4px; }
    .bubble.out { background:#007bff; color:#fff; margin-left:auto; border-bottom-right-radius:4px; }
    .meta { font-size: .75rem; opacity: .75; margin-top:6px; text-align:right; }
    .raw-pdu { font-family: monospace; font-size:.75rem; color:#dc3545; word-break: break-all;}
    .smallmuted { font-size:.8rem; color:#6c757d; }
  </style>
</head>
<body class="bg-light p-3">
<div class="container-fluid">
  <div class="d-flex align-items-center mb-3">
    <h4 class="mb-0">Voyageurs groupés par téléphone</h4>
    <span class="ml-3 badge badge-info">Total groupes: <?= e($totalRows) ?></span>
    <div class="ml-auto">
      <button class="btn btn-sm btn-outline-secondary" id="btnOpenDrawerList" data-phone="">Ouvrir drawer (sélectionnera après clic ✉️/💬)</button>
    </div>
  </div>

  <form class="card mb-3 p-3" method="get" action="<?= e($_SERVER['PHP_SELF']) ?>">
    <div class="form-row">
      <div class="col-sm-4">
        <input type="text" name="q" class="form-control" placeholder="Recherche (nom, email, téléphone…)" value="<?= e($q) ?>">
      </div>
      <div class="col-sm-2">
        <select name="per_page" class="form-control">
          <?php foreach([25,50,100,200] as $pp): ?>
            <option value="<?= $pp ?>" <?= $pp==$perPage?'selected':'' ?>><?= $pp ?>/page</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-3 d-flex align-items-center">
        <div class="form-check mr-3">
          <input class="form-check-input" type="checkbox" id="only_multi" name="only_multi" <?= $onlyMulti?'checked':'' ?>>
          <label class="form-check-label" for="only_multi">Réservations multiples (≥2)</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="only_confirmed" name="only_confirmed" <?= $onlyConfirmed?'checked':'' ?>>
          <label class="form-check-label" for="only_confirmed">Confirmées seulement</label>
        </div>
      </div>
      <div class="col-sm-3 text-right">
        <button class="btn btn-primary" type="submit">Filtrer</button>
        <a class="btn btn-outline-secondary" href="<?= e($_SERVER['PHP_SELF']) ?>">Réinitialiser</a>
        <a class="btn btn-success" href="<?= e($_SERVER['PHP_SELF']) ?>?<?= http_build_query(array_merge($_GET, ['export'=>'csv','page'=>1])) ?>">Export CSV</a>
      </div>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-sm table-striped">
      <thead class="thead-light">
        <tr>
          <th class="nowrap">Téléphone</th>
          <th class="text-center"># Réservations</th>
          <th>Première arrivée</th>
          <th>Dernière arrivée</th>
          <th>Noms vus</th>
          <th>Emails vus</th>
          <th>Plateformes</th>
          <th class="text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($data as $row): ?>
        <tr>
          <td class="nowrap"><span class="font-weight-bold"><?= e($row['phone_norm']) ?></span></td>
          <td class="text-center">
            <span class="badge badge-pill <?= (int)$row['nb_resa']>=2?'badge-success':'badge-secondary' ?>">
              <?= e($row['nb_resa']) ?>
            </span>
          </td>
          <td><?= e($row['first_arrivee']) ?></td>
          <td><?= e($row['last_arrivee']) ?></td>
          <td><?= e($row['names']) ?></td>
          <td><?= e($row['emails']) ?></td>
          <td><?= e($row['plateformes']) ?></td>
          <td class="text-right">
            <a class="btn btn-sm btn-outline-primary"
               href="<?= e($_SERVER['PHP_SELF']) ?>?<?= http_build_query(array_merge($_GET, ['phone'=>$row['phone_norm'], 'page'=>NULL])) ?>">
              Détail
            </a>
            <a class="btn btn-sm btn-outline-info"
               href="./sms.php?view=conversations&sender=<?= urlencode($row['phone_norm']) ?>"
               target="_blank">
               💬 SMS
            </a>
            <button type="button" class="btn btn-sm btn-primary btn-open-drawer"
                    data-phone="<?= e($row['phone_norm']) ?>"
                    data-names="<?= e($row['names']) ?>">
              ✉️ Envoyer (drawer)
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($data)): ?>
        <tr><td colspan="8" class="text-center text-muted">Aucun résultat.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
    <nav aria-label="pagination">
      <ul class="pagination">
        <?php
          $base = $_GET; unset($base['page']);
          $mk = function($p) use ($base){ return e($_SERVER['PHP_SELF']).'?'.http_build_query($base + ['page'=>$p]); };
        ?>
        <li class="page-item <?= $page<=1?'disabled':'' ?>">
          <a class="page-link" href="<?= $mk(max(1,$page-1)) ?>">«</a>
        </li>
        <?php
          $start = max(1, $page-2);
          $end   = min($totalPages, $page+2);
          for ($i=$start; $i<=$end; $i++):
        ?>
          <li class="page-item <?= $i==$page?'active':'' ?>">
            <a class="page-link" href="<?= $mk($i) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
          <a class="page-link" href="<?= $mk(min($totalPages,$page+1)) ?>">»</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<!-- Drawer global (liste) -->
<div id="smsDrawer" class="drawer" aria-hidden="true">
  <div class="drawer-header d-flex align-items-center">
    <strong id="drawerTitle">SMS —</strong>
    <button class="btn btn-sm btn-outline-secondary ml-auto" id="btnCloseDrawer">Fermer</button>
  </div>
  <div id="drawerBody" class="drawer-body">
    <div class="text-center text-muted small">Sélectionnez un numéro via ✉️</div>
  </div>
  <div class="drawer-footer">
    <div class="form-group mb-2">
      <label class="small">Modèle</label>
      <select id="tplSelect" class="form-control form-control-sm">
        <option value="">— Aucun —</option>
        <option value="arrivee">Arrivée</option>
        <option value="depart">Départ</option>
        <option value="code">Code d'accès</option>
        <option value="retard">Retard / ETA</option>
        <option value="libre">Message libre</option>
      </select>
      <small class="smallmuted">Variables : {prenom} {arrivee} {depart} {code}</small>
    </div>
    <div class="form-group mb-2">
      <label class="small">Lier à une réservation (optionnel)</label>
      <select id="reservationSelect" class="form-control form-control-sm">
        <option value="">— Aucune —</option>
      </select>
    </div>
    <div class="form-group mb-2">
      <textarea id="smsText" rows="4" class="form-control" placeholder="Votre message…"></textarea>
    </div>
    <div class="d-flex align-items-center">
      <small class="smallmuted mr-auto" id="smsHint">Modem: /dev/ttyUSB0</small>
      <button id="btnSendSms" class="btn btn-primary btn-sm">Envoyer</button>
    </div>
  </div>
</div>

<script>
  // Contexte dynamique pour la LISTE
  const drawer = document.getElementById('smsDrawer');
  const drawerBody = document.getElementById('drawerBody');
  const drawerTitle = document.getElementById('drawerTitle');
  const btnCloseDrawer = document.getElementById('btnCloseDrawer');
  const tplSelect = document.getElementById('tplSelect');
  const smsText = document.getElementById('smsText');
  const reservationSelect = document.getElementById('reservationSelect');
  const btnSend = document.getElementById('btnSendSms');

  const TEMPLATES = {
    arrivee: "Bonjour {prenom},\nVotre arrivée est prévue le {arrivee}. N'hésitez pas à me confirmer votre heure d'arrivée. Bonne route !",
    depart:  "Bonjour {prenom},\nPetit rappel : départ le {depart}. Merci de laisser les clés sur la table et d'éteindre les lumières. Bonne journée !",
    code:    "Bonjour {prenom},\nVoici le code d'accès : {code}. Le logement sera prêt à partir de {arrivee}.",
    retard:  "Bonjour {prenom},\nAvez-vous une estimation de votre heure d'arrivée ? Nous ajusterons la préparation si besoin.",
    libre:   ""
  };

  const CTX = { phone: '', prenom:'', arrivee:'', depart:'', code:'' };

  function openDrawerFor(phone) {
    CTX.phone = phone;
    drawerTitle.textContent = 'SMS — ' + phone;
    drawer.classList.add('open');
    drawer.setAttribute('aria-hidden','false');
    drawerBody.innerHTML = '<div class="text-center text-muted small">Chargement de la conversation…</div>';
    loadConversation(phone);
    loadReservationsForPhone(phone);
  }
  function closeDrawer(){
    drawer.classList.remove('open');
    drawer.setAttribute('aria-hidden','true');
  }
  btnCloseDrawer.addEventListener('click', closeDrawer);

  document.querySelectorAll('.btn-open-drawer').forEach(btn => {
    btn.addEventListener('click', () => {
      const phone = btn.dataset.phone;
      openDrawerFor(phone);
    });
  });

  async function loadReservationsForPhone(phone){
    try{
      const url = new URL(window.location.href);
      url.search = '';
      url.pathname = "<?= e($_SERVER['PHP_SELF']) ?>";
      url.searchParams.set('api','reservations_by_phone');
      url.searchParams.set('phone', phone);
      const resp = await fetch(url.toString(), { headers: { 'Accept':'application/json' }});
      const data = await resp.json();
      reservationSelect.innerHTML = '<option value="">— Aucune —</option>';
      if (data && data.success && Array.isArray(data.reservations)) {
        data.reservations.forEach(r => {
          const txt = `#${r.id} — ${r.date_arrivee}${r.heure_arrivee?' '+r.heure_arrivee:''} → ${r.date_depart}${r.heure_depart?' '+r.heure_depart:''}` +
                      (r.reference ? ' — ' + r.reference : '');
          const opt = document.createElement('option');
          opt.value = r.id; opt.textContent = txt;
          reservationSelect.appendChild(opt);
        });
        if (data.reservations.length > 0) {
          const r = data.reservations[0];
          CTX.prenom  = (r.prenom || '');
          CTX.arrivee = (r.date_arrivee || '') + (r.heure_arrivee ? ' ' + r.heure_arrivee : '');
          CTX.depart  = (r.date_depart || '')  + (r.heure_depart ? ' ' + r.heure_depart : '');
        } else {
          CTX.prenom = CTX.arrivee = CTX.depart = '';
        }
      }
    }catch(e){
      console.warn('reservations_by_phone error:', e);
    }
  }

  async function loadConversation(phone){
    try{
      const resp = await fetch('get_conversation.php?sender=' + encodeURIComponent(phone), { headers: { 'Accept':'application/json' }});
      if (!resp.ok) throw new Error('HTTP ' + resp.status);
      const rawText = await resp.text();
      let list;
      try { list = JSON.parse(rawText); } catch(e){ throw new Error('Réponse non JSON : ' + rawText.slice(0,200)); }
      if (!Array.isArray(list)) list = (list && Array.isArray(list.messages)) ? list.messages : [];
      const frag = document.createDocumentFragment();
      const renderBubble = (m) => {
        const dir = (m && m.direction === 'in') ? 'in' : 'out';
        const msg = String((m && m.message)!=null ? m.message : '');
        const isPdu = msg.startsWith(',145,') || /^[0-9A-F]{10,}/i.test(msg);
        const wrap = document.createElement('div');
        wrap.className = 'bubble ' + (dir==='in'?'in':'out');
        const txt = document.createElement('div');
        txt.className = isPdu ? 'raw-pdu' : '';
        txt.textContent = isPdu ? ('[Message non décodé: ' + msg + ']') : msg;
        const meta = document.createElement('div');
        meta.className = 'meta';
        const d = (m && m.date) ? m.date : new Date().toISOString();
        meta.textContent = d;
        wrap.appendChild(txt); wrap.appendChild(meta);
        return wrap;
      };
      if (list.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'text-center text-muted small';
        empty.textContent = 'Aucun message pour ce numéro.';
        frag.appendChild(empty);
      } else {
        list.forEach(m => frag.appendChild(renderBubble(m)));
      }
      drawerBody.innerHTML = '';
      drawerBody.appendChild(frag);
      drawerBody.scrollTop = drawerBody.scrollHeight;
    }catch(e){
      drawerBody.innerHTML = '<div class="text-danger small">Erreur chargement: ' + (e.message || e) + '</div>';
    }
  }

  function applyTemplate(key){
    const raw = TEMPLATES[key] ?? '';
    const out = raw
      .replace(/{prenom}/g, CTX.prenom || 'vous')
      .replace(/{arrivee}/g, CTX.arrivee || '')
      .replace(/{depart}/g, CTX.depart || '')
      .replace(/{code}/g, CTX.code || '');
    smsText.value = out;
  }
  tplSelect.addEventListener('change', () => applyTemplate(tplSelect.value));

  btnSend.addEventListener('click', async () => {
    const text = (smsText.value || '').trim();
    if (!CTX.phone) { alert('Téléphone non sélectionné'); return; }
    if (!text) { alert('Message vide'); return; }
    const resId = reservationSelect.value ? parseInt(reservationSelect.value, 10) : null;
    btnSend.disabled = true; btnSend.textContent = '...';
    try{
      const resp = await fetch('send_sms.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          receiver: CTX.phone,
          message: text,
          reservation_id: resId,
          modem: '/dev/ttyUSB0'
        })
      });
      const result = await resp.json();
      if (!resp.ok || !result.success) throw new Error(result.message || ('HTTP ' + resp.status));
      // Ajout local de la bulle envoyée
      const now = new Date().toISOString();
      const wrap = document.createElement('div');
      wrap.className = 'bubble out';
      const txt = document.createElement('div');
      txt.textContent = text;
      const meta = document.createElement('div');
      meta.className = 'meta'; meta.textContent = now + ' (queued)';
      wrap.appendChild(txt); wrap.appendChild(meta);
      drawerBody.appendChild(wrap);
      drawerBody.scrollTop = drawerBody.scrollHeight;
      smsText.value = '';
      alert('SMS ajouté à la file (id ' + result.id + ').');
    }catch(e){
      alert('Erreur envoi: ' + (e.message || e));
    }finally{
      btnSend.disabled = false; btnSend.textContent = 'Envoyer';
    }
  });
</script>
</body>
</html>
