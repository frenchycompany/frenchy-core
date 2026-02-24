<?php
// reservation_list.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../includes/db.php';
include '../includes/header.php';

// Récupérer la date de check-in à filtrer (GET), ou aujourd'hui par défaut
$filterDate = $_GET['checkin_date'] ?? date('Y-m-d');

// Préparer et exécuter la requête : on ne sélectionne que les champs demandés,
// en joignant la table liste_logements pour obtenir le nom du logement.
$stmt = $conn->prepare("
    SELECT 
      l.nom_du_logement AS logement,
      r.prenom,
      r.nom,
      r.reference AS plateforme,
      r.telephone AS mobile
    FROM reservation r
    LEFT JOIN liste_logements l 
      ON r.logement_id = l.id
    WHERE r.date_arrivee = ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param('s', $filterDate);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Réservations - Check-in <?= htmlspecialchars($filterDate) ?></title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
  <h1 class="text-center mb-4">
    Réservations avec check-in le <?= htmlspecialchars($filterDate) ?>
  </h1>

  <form method="get" class="form-inline justify-content-center mb-4">
    <label for="checkin_date" class="mr-2">Date de check-in :</label>
    <input 
      type="date" 
      id="checkin_date" 
      name="checkin_date" 
      class="form-control mr-2"
      value="<?= htmlspecialchars($filterDate) ?>"
      required>
    <button type="submit" class="btn btn-primary">Filtrer</button>
  </form>

  <div class="table-responsive">
    <table class="table table-bordered table-hover">
      <thead class="thead-dark text-center">
        <tr>
          <th>Logement</th>
          <th>Client</th>
          <th>Plateforme</th>
          <th>Mobile</th>
        </tr>
      </thead>
      <tbody class="text-center">
      <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['logement'] ?? '—') ?></td>
            <td>
              <?= htmlspecialchars(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? '')) ?>
            </td>
            <td><?= htmlspecialchars($row['plateforme'] ?? '—') ?></td>
            <td><?= htmlspecialchars($row['mobile'] ?? '—') ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="4" class="text-center">
            Aucune réservation pour cette date.
          </td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
