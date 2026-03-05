<?php
// test_correlation_planning.php
// Affiche pour chaque logement :
// - Réservation arrivée (date_arrivee = aujourd’hui)
// - Réservation départ  (date_depart  = aujourd’hui)
// - Intervention locale planifiée (date = aujourd’hui)
// - Occupé ? (réservation couvrant aujourd’hui)

// 1) Connexion base locale (Ionos)
include '../config.php'; // fournit $conn (PDO) sur dbs13515816

// 2) Connexion base distante (Raspberry Pi)
require_once __DIR__ . '/../includes/rpi_db.php';
try {
    $pdoRemote = getRpiPdo();
} catch (PDOException $e) {
    die("Erreur connexion distante : " . $e->getMessage());
}

// 3) Date du jour
date_default_timezone_set('Europe/Paris');
$today = date('Y-m-d');

// 4) Récupérer tous les logements
$stmtLocal = $conn->prepare("SELECT id, nom_du_logement FROM liste_logements ORDER BY id");
$stmtLocal->execute();
$logements = $stmtLocal->fetchAll(PDO::FETCH_ASSOC);

// Préparation de la requête d’occupation
$stmtOcc = $pdoRemote->prepare(
    "SELECT id FROM reservation
     WHERE logement_id = :lid
       AND date_arrivee <= :today
       AND date_depart   >= :today
     LIMIT 1"
);

// 5) Parcourir et construire résultats
$results = [];
foreach ($logements as $lot) {
    $lid   = $lot['id'];
    $lname = $lot['nom_du_logement'];

    // 5a) Réservation arrivée
    $stmtResIn = $pdoRemote->prepare(
        "SELECT id AS reservation_id, telephone
         FROM reservation
         WHERE logement_id = :lid
           AND date_arrivee = :date_arrivee"
    );
    $stmtResIn->execute([':lid'=>$lid, ':date_arrivee'=>$today]);
    $resaIn = $stmtResIn->fetch(PDO::FETCH_ASSOC);

    // 5a2) Réservation départ
    $stmtResOut = $pdoRemote->prepare(
        "SELECT id AS reservation_checkout_id, telephone AS telephone_checkout
         FROM reservation
         WHERE logement_id = :lid
           AND date_depart = :date_depart"
    );
    $stmtResOut->execute([':lid'=>$lid, ':date_depart'=>$today]);
    $resaOut = $stmtResOut->fetch(PDO::FETCH_ASSOC);

    // 5c) Occupé aujourd’hui ?
    $stmtOcc->execute([':lid'=>$lid, ':today'=>$today]);
    $occ = (bool)$stmtOcc->fetch();

    // 5b) Intervention locale planifiée
    $stmtInt = $conn->prepare(
        "SELECT id AS intervention_id, statut
         FROM planning
         WHERE logement_id = :lid
           AND date = :date_intervention"
    );
    $stmtInt->execute([':lid'=>$lid, ':date_intervention'=>$today]);
    $interv = $stmtInt->fetch(PDO::FETCH_ASSOC);

    // stocker ligne
    $results[] = [
        'logement_id'               => $lid,
        'nom_du_logement'           => $lname,
        'reservation_id'            => $resaIn['reservation_id'] ?? null,
        'telephone_arrivee'         => $resaIn['telephone'] ?? null,
        'reservation_checkout_id'   => $resaOut['reservation_checkout_id'] ?? null,
        'telephone_depart'          => $resaOut['telephone_checkout'] ?? null,
        'occupied'                  => $occ,
        'intervention_id'           => $interv['intervention_id'] ?? null,
        'statut'                    => $interv['statut'] ?? null,
    ];
}

// 6) Affichage HTML
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Test Corrélation Résa & Intervention</title>
  <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <h1>Corrélation Logements ↔ Réservations ↔ Interventions</h1>
  <p class="text-muted">Date = <?= htmlspecialchars($today) ?></p>
  <table class="table table-bordered table-striped">
    <thead class="thead-dark">
      <tr>
        <th>Logement #</th>
        <th>Nom du logement</th>
        <th>Arrivée #</th>
        <th>Tél. arrivée</th>
        <th>Départ #</th>
        <th>Tél. départ</th>
        <th>Occupé ?</th>
        <th>Intervention #</th>
        <th>Statut intervention</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($results as $row): ?>
      <tr>
        <td><?= htmlspecialchars($row['logement_id']) ?></td>
        <td><?= htmlspecialchars($row['nom_du_logement']) ?></td>
        <td>
          <?= $row['reservation_id']
               ? htmlspecialchars($row['reservation_id'])
               : '<span class="text-muted">aucune</span>' ?>
        </td>
        <td>
          <?= $row['telephone_arrivee']
               ? htmlspecialchars($row['telephone_arrivee'])
               : '<span class="text-muted">—</span>' ?>
        </td>
        <td>
          <?= $row['reservation_checkout_id']
               ? htmlspecialchars($row['reservation_checkout_id'])
               : '<span class="text-muted">aucune</span>' ?>
        </td>
        <td>
          <?= $row['telephone_depart']
               ? htmlspecialchars($row['telephone_depart'])
               : '<span class="text-muted">—</span>' ?>
        </td>
        <td class="text-center">
          <?= $row['occupied']
               ? '<span class="text-success">Oui</span>'
               : '<span class="text-muted">Non</span>' ?>
        </td>
        <td>
          <?= $row['intervention_id']
               ? htmlspecialchars($row['intervention_id'])
               : '<span class="text-muted">aucune</span>' ?>
        </td>
        <td>
          <?= $row['statut']
               ? htmlspecialchars($row['statut'])
               : '<span class="text-muted">—</span>' ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
