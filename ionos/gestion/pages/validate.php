<?php
// validate.php
ini_set('display_startup_errors', 1);

// ─────────────────────────────────────────────────────────────────────────────
// Anti-cache ultra strict côté serveur
// ─────────────────────────────────────────────────────────────────────────────
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

include '../config.php';   // $conn vers la base locale (planning, liste_logements)
session_start();

// ─────────────────────────────────────────────────────────────────────────────
// 0) Connexion à la base SMS distante (réservations & sms_outbox)
require_once __DIR__ . '/../includes/rpi_db.php';
try {
    $pdoSms = getRpiPdo();
} catch (PDOException $e) {
    http_response_code(500);
    die("Erreur connexion SMS-DB : " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// ─────────────────────────────────────────────────────────────────────────────
// Utilitaires
// ─────────────────────────────────────────────────────────────────────────────
function e($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// ─────────────────────────────────────────────────────────────────────────────
// Récupération du token depuis la query string
// ─────────────────────────────────────────────────────────────────────────────
$token = $_GET['token'] ?? null;
if (!$token) {
    http_response_code(400);
    die("Token manquant.");
}

// Flag de fin (PRG)
$done = isset($_GET['done']) && $_GET['done'] === '1';

// Vérification du token et récupération des infos d’intervention
$stmt = $conn->prepare(
    "SELECT 
        t.id AS token_id, t.used, t.expires_at,
        p.id AS intervention_id, p.date, p.note,
        p.logement_id,
        l.nom_du_logement
     FROM intervention_tokens t
     JOIN planning p           ON p.id = t.intervention_id
     JOIN liste_logements l    ON l.id = p.logement_id
     WHERE t.token = ?"
);
$stmt->execute([$token]);
$rec = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rec) {
    http_response_code(404);
    die("Token invalide.");
}

// Si le lien est déjà utilisé, on envoie 410 Gone (et on n'affiche pas le formulaire)
if ((int)$rec['used'] === 1) {
    http_response_code(410);
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
      <meta charset="UTF-8">
      <title>Lien déjà utilisé</title>
      <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate" />
      <meta http-equiv="Pragma" content="no-cache" />
      <meta http-equiv="Expires" content="0" />
      <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    </head>
    <body class="bg-light p-4">
      <div class="card mx-auto" style="max-width:480px;">
        <div class="card-body text-center">
          <h4 class="mb-3">Lien déjà utilisé</h4>
          <p class="text-muted mb-4">Ce lien de validation a déjà été consommé.</p>
          <button class="btn btn-secondary" onclick="forceRefresh()">Forcer l'actualisation</button>
        </div>
      </div>
      <script>
        function forceRefresh() {
          const url = new URL(window.location.href);
          url.searchParams.set('cb', Date.now().toString());
          window.location.replace(url.toString());
        }
        // On neutralise l'historique pour éviter la réouverture d'un cache
        history.replaceState({}, '', window.location.pathname + '?token=<?php echo e($token); ?>&cb=' + Date.now());
      </script>
    </body>
    </html>
    <?php
    exit;
}

// Si expiré, on renvoie 403
if ($rec['expires_at'] && strtotime($rec['expires_at']) < time()) {
    http_response_code(403);
    die("Lien expiré.");
}

// ─────────────────────────────────────────────────────────────────────────────
// Vérifier s'il y a une réservation pour ce logement à date_arrivee = date intervention
// ─────────────────────────────────────────────────────────────────────────────
$dateInterv = $rec['date'];
$stmtRes = $pdoSms->prepare(
    "SELECT id AS reservation_id, telephone
     FROM reservation
     WHERE logement_id = :lid
       AND date_arrivee = :date_arrivee
     LIMIT 1"
);
$stmtRes->execute([
    ':lid'           => $rec['logement_id'],
    ':date_arrivee'  => $dateInterv,
]);
$resa = $stmtRes->fetch(PDO::FETCH_ASSOC);
$hasReservation = (bool)$resa;
$clientPhone     = $hasReservation ? $resa['telephone'] : null;

// ─────────────────────────────────────────────────────────────────────────────
// DEBUG (commenter en prod)
// ─────────────────────────────────────────────────────────────────────────────
echo "<!-- DEBUG hasReservation=" . ($hasReservation ? 'true' : 'false')
   . " | logement={$rec['logement_id']} | dateInterv={$dateInterv}"
   . " | phone=" . ($clientPhone ?? '') . " -->";

// ─────────────────────────────────────────────────────────────────────────────
// Traitement du POST (validation du ménage + SMS)
// ─────────────────────────────────────────────────────────────────────────────
$errors   = [];
$success  = false;
$sendSms  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['menage','poubelle','attentions','toilettes'] as $c) {
        if (empty($_POST[$c]) || $_POST[$c] !== 'on') {
            $errors[] = "Veuillez cocher « " . ucfirst($c) . " ».";
        }
    }
    if (empty($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Erreur upload vidéo.";
    } else {
        $ext = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['mp4','mov','avi','webm','mkv','3gp','m4v','mlpr','mts','wmv','flv','mpg','mpeg'];
        if (!in_array($ext, $allowedExts)) {
            $errors[] = "Format vidéo non supporté ($ext).";
        }
    }
    if ($hasReservation && isset($_POST['send_sms'])) {
        $sendSms = true;
    }

    if (empty($errors)) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        if (!is_writable($uploadDir)) {
            @chmod($uploadDir, 0775);
        }
        $baseName = $rec['intervention_id'] . '_' . time();
        $origFile = $baseName . '.' . $ext;
        $filename = $baseName . '.mp4'; // Le fichier final sera toujours en MP4
        if (is_writable($uploadDir) && move_uploaded_file($_FILES['video']['tmp_name'], $uploadDir . $origFile)) {

            // Conversion en MP4 si le format d'origine n'est pas déjà mp4
            if ($ext !== 'mp4') {
                $input  = escapeshellarg($uploadDir . $origFile);
                $output = escapeshellarg($uploadDir . $filename);
                // -movflags +faststart = lecture streaming immédiate
                exec("ffmpeg -i $input -c:v libx264 -preset fast -crf 28 -c:a aac -movflags +faststart -y $output 2>&1", $ffOut, $ffCode);
                if ($ffCode === 0 && file_exists($uploadDir . $filename)) {
                    // Supprime le fichier original
                    @unlink($uploadDir . $origFile);
                } else {
                    // Fallback : on garde le fichier original tel quel
                    $filename = $origFile;
                }
            }

            // Marque le token utilisé et met à jour le planning
            $conn->prepare("UPDATE intervention_tokens SET used=1 WHERE id=?")
                 ->execute([$rec['token_id']]);
            $conn->prepare("UPDATE planning SET statut='Fait', commentaire_menage=? WHERE id=?")
                 ->execute([trim($_POST['commentaire'] ?? ''), $rec['intervention_id']]);

            // Envoi SMS éventuel
            if ($sendSms) {
                $videoUrl   = 'https://gestion.frenchyconciergerie.fr/video.php?f=' . urlencode($filename);
                $smsText    = "Bonne nouvelle 😊, votre logement est prêt ! Voici la vidéo du logement : {$videoUrl}, si besoin n'hésitez pas à me contacter au +33647554678 Raphael - Frenchy conciergerie";
                $stmtSms = $pdoSms->prepare(
                    "INSERT INTO sms_outbox (receiver, message, modem, status) VALUES (:recv, :msg, :modem, 'pending')"
                );
                $stmtSms->execute([
                    ':recv'  => $clientPhone,
                    ':msg'   => $smsText,
                    ':modem' => 'modem1'
                ]);
            }

            // Si requête AJAX (XHR), renvoyer du JSON au lieu d'un redirect
            // (XHR suit les 303 automatiquement, ce qui casse le flux PRG)
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                   && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }

            // PRG : redirection pour casser tout cache et empêcher le repost
            // On passe par 303 See Other vers la même page avec done=1
            header('Location: ' . sprintf('%s?token=%s&done=1&cb=%d',
                basename(__FILE__),
                urlencode($token),
                time()
            ), true, 303);
            exit;

        } else {
            $errors[] = "Impossible de sauvegarder la vidéo. Le dossier uploads/ n'est pas accessible en écriture (permissions). Contactez l'administrateur serveur.";
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Affichage (GET ou POST avec erreurs). Si done=1, on montre un écran succès,
// sans possibilité d'action et on nettoie l'URL via history.replaceState.
// ─────────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Validation Intervention</title>

  <!-- Anti-cache côté client -->
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate" />
  <meta http-equiv="Pragma" content="no-cache" />
  <meta http-equiv="Expires" content="0" />

  <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
    .maxw { max-width: 480px; }
  </style>
</head>
<body class="bg-light p-4">
  <div class="card mx-auto maxw">
    <div class="card-body">
      <h4 class="card-title text-center mb-4">
        Intervention #<?= e($rec['intervention_id']) ?> —
        <?= e($rec['nom_du_logement']) ?> (<?= e($rec['date']) ?>)
      </h4>

      <?php if ($done): ?>
        <div class="alert alert-success text-center">Intervention validée !</div>
        <div class="text-center">
          <button class="btn btn-secondary" onclick="forceFreshView()">Forcer l’actualisation</button>
        </div>
        <script>
          // On remplace l'URL courante pour enlever done/cb et empêcher réouverture d'un état ancien
          (function(){
            const clean = window.location.pathname + '?token=<?= e($token) ?>';
            history.replaceState({}, '', clean + '&cb=' + Date.now());
          })();
          function forceFreshView() {
            const url = new URL(window.location.href);
            url.searchParams.set('cb', Date.now().toString());
            window.location.replace(url.toString());
          }
        </script>
      <?php else: ?>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">
            <ul class="mb-0">
              <?php foreach($errors as $err): ?>
                <li><?= e($err) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form id="validationForm" method="POST" enctype="multipart/form-data" autocomplete="off">
          <div class="form-check mb-2">
            <input type="checkbox" name="menage" id="menage" class="form-check-input">
            <label for="menage" class="form-check-label">Ménage fait</label>
          </div>
          <div class="form-check mb-2">
            <input type="checkbox" name="poubelle" id="poubelle" class="form-check-input">
            <label for="poubelle" class="form-check-label">Poubelle vidée</label>
          </div>
          <div class="form-check mb-2">
            <input type="checkbox" name="attentions" id="attentions" class="form-check-input">
            <label for="attentions" class="form-check-label">Attentions mises</label>
          </div>
          <div class="form-check mb-3">
            <input type="checkbox" name="toilettes" id="toilettes" class="form-check-input">
            <label for="toilettes" class="form-check-label">Toilettes faites</label>
          </div>

          <?php if ($hasReservation): ?>
            <div class="form-check mb-3">
              <input type="checkbox" name="send_sms" id="send_sms" class="form-check-input" checked>
              <label for="send_sms" class="form-check-label">
                Envoyer un SMS au client (<?= e($clientPhone) ?>)
              </label>
            </div>
          <?php endif; ?>

          <div class="form-group">
            <label for="commentaire">Commentaire (optionnel)</label>
            <textarea name="commentaire" id="commentaire" class="form-control" placeholder="Commentaire ménage…"></textarea>
          </div>
          <div class="form-group">
            <label for="video">Vidéo (min. 30s)</label>
            <input type="file" name="video" id="video" class="form-control-file" accept="video/*" required>
          </div>

          <!-- Barre de progression -->
          <div class="form-group">
            <progress id="uploadProgress" value="0" max="100" style="width:100%; display:none;"></progress>
          </div>

          <button type="submit" class="btn btn-primary btn-block">Valider</button>
        </form>

        <script>
        // Empêche le navigateur de restaurer un formulaire depuis le cache (bfcache)
        window.addEventListener('pageshow', function(event) {
          if (event.persisted) {
            // si la page provient du cache, on force un "fresh" avec un cache buster
            const url = new URL(window.location.href);
            url.searchParams.set('cb', Date.now().toString());
            window.location.replace(url.toString());
          }
        });

        // Upload XHR + barre de progression (inchangé, avec légers durcissements)
        document.getElementById('validationForm').addEventListener('submit', function(e) {
          e.preventDefault();
          var form = this;
          var fileInput = document.getElementById('video');
          if (fileInput.files.length === 0) {
            form.submit();
            return;
          }
          var formData = new FormData(form);
          var xhr = new XMLHttpRequest();
          xhr.open('POST', window.location.href, true);
          xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
          xhr.setRequestHeader('Cache-Control', 'no-store');
          xhr.upload.addEventListener('loadstart', function() {
            document.getElementById('uploadProgress').style.display = 'block';
          });
          xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
              var percent = Math.round((e.loaded / e.total) * 100);
              document.getElementById('uploadProgress').value = percent;
            }
          });
          xhr.onload = function() {
            if (xhr.status === 200) {
              try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                  window.location.href = 'validate.php?token=<?= urlencode($token) ?>&done=1&cb=' + Date.now();
                  return;
                }
              } catch(e) { console.error('validate.php: ' + e.message); }
              // Fallback : si la réponse n'est pas du JSON, c'est la page HTML (erreurs de validation)
              document.open();
              document.write(xhr.responseText);
              document.close();
            } else {
              alert('Erreur lors de l\'upload (' + xhr.status + ')');
            }
          };
          xhr.send(formData);
        });

        // Nettoyage de l'URL (enlève un éventuel cache buster précédent)
        (function(){
          const url = new URL(window.location.href);
          if (url.searchParams.has('cb')) {
            url.searchParams.delete('cb');
            history.replaceState({}, '', url.pathname + '?' + url.searchParams.toString());
          }
        })();
        </script>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
