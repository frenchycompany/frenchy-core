<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../config.php';

// ── 1) Réinitialisation d’un token (admin) ───────────────────────────────────
if (isset($_GET['reset_token_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    $id = filter_input(INPUT_GET, 'reset_token_id', FILTER_VALIDATE_INT);
    if ($id) {
        // On remet à 0 tous les tokens liés à l’intervention (au cas où il y en ait plusieurs)
        $stmt = $conn->prepare("UPDATE intervention_tokens SET used = 0 WHERE intervention_id = ?");
        $stmt->execute([$id]);
        $_SESSION['flash'] = [
            'type' => 'success',
            'msg'  => "Le flag `used` a été remis à 0 pour l’intervention #{$id}."
        ];
    }
    header("Location: validations.php?date=" . urlencode($_GET['date'] ?? date('Y-m-d')));
    exit;
}

// ── 2) Menu + init ───────────────────────────────────────────────────────────
include '../pages/menu.php';
date_default_timezone_set('Europe/Paris');

$role   = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'] 
       ?? $_SESSION['id'] 
       ?? $_SESSION['idPrimaire'] 
       ?? null;

$date = $_GET['date'] ?? date('Y-m-d');

// ── Helpers ──────────────────────────────────────────────────────────────────
function e($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// ── 3) Flash message ─────────────────────────────────────────────────────────
if (isset($_SESSION['flash'])) {
    echo '<div class="alert alert-'.e($_SESSION['flash']['type']).'">';
    echo $_SESSION['flash']['msg'];
    echo '</div>';
    unset($_SESSION['flash']);
}

// ── 4) Construction requête avec token de validation ─────────────────────────
// On récupère pour chaque intervention :
// - Le token non utilisé le plus récent (it_unused)
// - Sinon, à défaut, le token le plus récent (it_latest)
// Puis on utilise COALESCE pour choisir le bon token à afficher.

$sql = "
    SELECT
        p.id AS intervention_id,
        p.date,
        p.statut,
        p.commentaire_menage,
        l.nom_du_logement,
        COALESCE(it_unused.token, it_latest.token) AS validation_token
    FROM planning p
    JOIN liste_logements l
        ON p.logement_id = l.id
    LEFT JOIN (
        SELECT t1.intervention_id, t1.token
        FROM intervention_tokens t1
        JOIN (
            SELECT intervention_id, MAX(id) AS max_id
            FROM intervention_tokens
            WHERE used = 0
            GROUP BY intervention_id
        ) t2 ON t1.intervention_id = t2.intervention_id AND t1.id = t2.max_id
    ) it_unused
        ON it_unused.intervention_id = p.id
    LEFT JOIN (
        SELECT t1.intervention_id, t1.token
        FROM intervention_tokens t1
        JOIN (
            SELECT intervention_id, MAX(id) AS max_id
            FROM intervention_tokens
            GROUP BY intervention_id
        ) t2 ON t1.intervention_id = t2.intervention_id AND t1.id = t2.max_id
    ) it_latest
        ON it_latest.intervention_id = p.id
";

// Filtres
$where  = ["p.date = :date"];
$params = [":date" => $date];

if ($role === 'intervenant' && $userId) {
    // On garde tes conditions exactes (aucune fonctionnalité retirée)
    $where[]           = "p.intervenant_id = :userId";
    $where[]           = "MONTH(p.date) = MONTH(:date) AND YEAR(p.date) = YEAR(:date)";
    $params[':userId'] = $userId;
} elseif ($role === 'proprietaire' && $userId) {
    $where[]           = "l.proprietaire_id = :userId";
    $params[':userId'] = $userId;
}

$sql .= " WHERE " . implode(" AND ", $where) . " ORDER BY p.date DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$interventions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── 5) Dernière vidéo uploadée par intervention ─────────────────────────────
$uploadDir = __DIR__ . '/../uploads/';
foreach ($interventions as &$int) {
    $pattern = $uploadDir . $int['intervention_id'] . '_*';
    $files   = glob($pattern);
    if ($files) {
        usort($files, fn($a,$b)=> filemtime($b) - filemtime($a));
        $int['video'] = basename($files[0]);
    } else {
        $int['video'] = null;
    }
}
unset($int);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Prestations du <?= e(date('d/m/Y', strtotime($date))) ?></title>
</head>
<body>
  <div class="container mt-4">
    <h2 class="text-center mb-4">
      Prestations du <?= e(date('d/m/Y', strtotime($date))) ?>
    </h2>

    <form method="GET" class="form-inline mb-3">
      <label for="date" class="mr-2">Date :</label>
      <input type="date" id="date" name="date" value="<?= e($date) ?>" class="form-control mr-2">
      <button type="submit" class="btn btn-primary">Filtrer</button>
    </form>

    <table class="table table-bordered table-striped">
      <thead class="thead-dark">
        <tr>
          <th>ID</th>
          <th>Statut</th>
          <th>Logement</th>
          <th>Date</th>
          <th>Commentaire</th>
          <th>Vidéo</th>
          <th>Lien validation</th>
          <th>Valider</th>
          <th>Réinitialiser</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($interventions)): ?>
          <tr>
            <td colspan="9" class="text-center">Aucune prestation pour cette date.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($interventions as $int): ?>
            <tr>
              <td><?= e($int['intervention_id']) ?></td>
              <td><?= e($int['statut']) ?></td>
              <td><?= e($int['nom_du_logement']) ?></td>
              <td><?= e($int['date']) ?></td>
              <td><?= e($int['commentaire_menage'] ?: '—') ?></td>
              <td>
                <?php if ($int['video']): ?>
                  <a href="../uploads/<?= e($int['video']) ?>" target="_blank">
                    <?= e($int['video']) ?>
                  </a>
                <?php else: ?>
                  <span class="text-muted">Pas de vidéo</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($int['validation_token'])): ?>
                  <a href="validate.php?token=<?= e($int['validation_token']) ?>" target="_blank" class="btn btn-sm btn-info">
                    Ouvrir le lien
                  </a>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($role === 'intervenant'): ?>
                  <a href="valider_intervention.php?id=<?= e($int['intervention_id']) ?>"
                     class="btn btn-sm btn-success">Valider</a>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($role === 'admin'): ?>
                  <a href="validations.php?reset_token_id=<?= e($int['intervention_id']) ?>&date=<?= e($date) ?>"
                     class="btn btn-sm btn-warning">
                    Réinitialiser
                  </a>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
