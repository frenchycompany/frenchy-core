<?php
// statistiques_rentabilite.php
include '../config.php';       // configuration de la BDD
include '../pages/menu.php';   // menu de navigation

// Mode exception + affichage des erreurs PDO
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Récupération des filtres
$filters = [];
$whereClauses = [];

if (!empty($_GET['logement'])) {
    $filters['logement'] = $_GET['logement'];
    // filtre sur le nom du logement via la table liste_logements
    $whereClauses[] = "ll.nom_du_logement = :logement";
}

if (!empty($_GET['date_debut']) && !empty($_GET['date_fin'])) {
    $filters['date_debut'] = $_GET['date_debut'];
    $filters['date_fin']   = $_GET['date_fin'];
    $whereClauses[] = "pl.date BETWEEN :date_debut AND :date_fin";
}

if (!empty($_GET['conducteur'])) {
    $filters['conducteur'] = $_GET['conducteur'];
    $whereClauses[] = "pl.conducteur = :conducteur";
}

if (!empty($_GET['femme_de_menage_1'])) {
    $filters['femme_de_menage_1'] = $_GET['femme_de_menage_1'];
    $whereClauses[] = "pl.femme_de_menage_1 = :femme_de_menage_1";
}

if (!empty($_GET['femme_de_menage_2'])) {
    $filters['femme_de_menage_2'] = $_GET['femme_de_menage_2'];
    $whereClauses[] = "pl.femme_de_menage_2 = :femme_de_menage_2";
}

if (!empty($_GET['laverie'])) {
    $filters['laverie'] = $_GET['laverie'];
    $whereClauses[] = "pl.laverie = :laverie";
}

$whereSQL = $whereClauses
    ? 'WHERE ' . implode(' AND ', $whereClauses)
    : '';

// Requête principale
$query = "
    SELECT
        ll.nom_du_logement   AS nom_du_logement,
        pl.date,
        CASE
            WHEN pl.conducteur IS NOT NULL AND pl.conducteur != ''
            THEN COALESCE(rc.valeur, 0)
            ELSE 0
        END AS cout_conducteur,
        CASE
            WHEN pl.femme_de_menage_1 IS NOT NULL AND pl.femme_de_menage_1 != ''
            THEN COALESCE(rm.valeur, 0) * ll.poid_menage
            ELSE 0
        END AS cout_menage1,
        CASE
            WHEN pl.femme_de_menage_2 IS NOT NULL AND pl.femme_de_menage_2 != ''
            THEN COALESCE(rm.valeur, 0) * ll.poid_menage
            ELSE 0
        END AS cout_menage2,
        CASE
            WHEN pl.laverie IS NOT NULL AND pl.laverie != ''
            THEN COALESCE(rl.valeur, 0)
            ELSE 0
        END AS cout_laverie,
        3                          AS cout_lavage_sechage,
        ll.prix_vente_menage       AS prix_vente,
        -- rentabilité = prix_vente – (tous les coûts)
        ll.prix_vente_menage
          - (
              CASE
                WHEN pl.conducteur IS NOT NULL AND pl.conducteur != ''
                THEN COALESCE(rc.valeur, 0)
                ELSE 0
              END
              + CASE
                  WHEN pl.femme_de_menage_1 IS NOT NULL AND pl.femme_de_menage_1 != ''
                  THEN COALESCE(rm.valeur, 0) * ll.poid_menage
                  ELSE 0
                END
              + CASE
                  WHEN pl.femme_de_menage_2 IS NOT NULL AND pl.femme_de_menage_2 != ''
                  THEN COALESCE(rm.valeur, 0) * ll.poid_menage
                  ELSE 0
                END
              + CASE
                  WHEN pl.laverie IS NOT NULL AND pl.laverie != ''
                  THEN COALESCE(rl.valeur, 0)
                  ELSE 0
                END
              + 3
            ) AS rentabilite
    FROM planning pl
    LEFT JOIN liste_logements ll ON pl.logement_id = ll.id
    LEFT JOIN role rc            ON rc.role = 'Conducteur'
    LEFT JOIN role rm            ON rm.role = 'Femme de ménage'
    LEFT JOIN role rl            ON rl.role = 'Laverie'
    $whereSQL
    ORDER BY pl.date DESC
";

$stmt = $conn->prepare($query);

// Liaison des paramètres
foreach ($filters as $key => $value) {
    $type = (strpos($key, 'date') === 0) ? PDO::PARAM_STR : PDO::PARAM_STR;
    $stmt->bindValue(":$key", $value, $type);
}

$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Somme de la rentabilité
$totalRentabilite = array_sum(array_column($results, 'rentabilite'));

// Pour les listes déroulantes
$logements    = $conn->query("SELECT DISTINCT nom_du_logement FROM liste_logements")->fetchAll(PDO::FETCH_ASSOC);
$intervenants = $conn->query("SELECT DISTINCT nom FROM intervenant")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Statistiques de Rentabilité</title>
</head>
<body>
<div class="container mt-4">
  <h2 class="text-center">Statistiques de Rentabilité des Ménages</h2>

  <!-- Formulaire de filtres -->
  <form method="GET" class="mb-4">
    <div class="form-row">
      <div class="form-group col-md-4">
        <label for="logement">Logement</label>
        <select name="logement" id="logement" class="form-control">
          <option value="">Tous</option>
          <?php foreach ($logements as $l): ?>
            <option value="<?= htmlspecialchars($l['nom_du_logement']) ?>"
              <?= (isset($_GET['logement']) && $_GET['logement'] === $l['nom_du_logement']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($l['nom_du_logement']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group col-md-4">
        <label for="date_debut">Date de Début</label>
        <input type="date" name="date_debut" id="date_debut" class="form-control"
               value="<?= htmlspecialchars($_GET['date_debut'] ?? '') ?>">
      </div>
      <div class="form-group col-md-4">
        <label for="date_fin">Date de Fin</label>
        <input type="date" name="date_fin" id="date_fin" class="form-control"
               value="<?= htmlspecialchars($_GET['date_fin'] ?? '') ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group col-md-4">
        <label for="conducteur">Conducteur</label>
        <select name="conducteur" id="conducteur" class="form-control">
          <option value="">Tous</option>
          <?php foreach ($intervenants as $i): ?>
            <option value="<?= htmlspecialchars($i['nom']) ?>"
              <?= (isset($_GET['conducteur']) && $_GET['conducteur'] === $i['nom']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($i['nom']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group col-md-4">
        <label for="femme_de_menage_1">Femme de Ménage 1</label>
        <select name="femme_de_menage_1" id="femme_de_menage_1" class="form-control">
          <option value="">Tous</option>
          <?php foreach ($intervenants as $i): ?>
            <option value="<?= htmlspecialchars($i['nom']) ?>"
              <?= (isset($_GET['femme_de_menage_1']) && $_GET['femme_de_menage_1'] === $i['nom']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($i['nom']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group col-md-4">
        <label for="femme_de_menage_2">Femme de Ménage 2</label>
        <select name="femme_de_menage_2" id="femme_de_menage_2" class="form-control">
          <option value="">Tous</option>
          <?php foreach ($intervenants as $i): ?>
            <option value="<?= htmlspecialchars($i['nom']) ?>"
              <?= (isset($_GET['femme_de_menage_2']) && $_GET['femme_de_menage_2'] === $i['nom']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($i['nom']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label for="laverie">Laverie</label>
      <select name="laverie" id="laverie" class="form-control">
        <option value="">Tous</option>
        <?php foreach ($intervenants as $i): ?>
          <option value="<?= htmlspecialchars($i['nom']) ?>"
            <?= (isset($_GET['laverie']) && $_GET['laverie'] === $i['nom']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($i['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filtrer</button>
  </form>

  <!-- Résultats -->
  <h3 class="text-center">
    Total Rentabilité : <?= number_format($totalRentabilite, 2, ',', ' ') ?> €
  </h3>

  <table class="table table-bordered">
    <thead class="thead-dark">
      <tr>
        <th>Logement</th>
        <th>Date</th>
        <th>Conducteur (€)</th>
        <th>Femme de Ménage 1 (€)</th>
        <th>Femme de Ménage 2 (€)</th>
        <th>Laverie (€)</th>
        <th>Lavage + Séchage (€)</th>
        <th>Prix de Vente (€)</th>
        <th>Rentabilité (€)</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($results): ?>
        <?php foreach ($results as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['nom_du_logement']) ?></td>
            <td><?= htmlspecialchars($r['date']) ?></td>
            <td><?= number_format($r['cout_conducteur'],     2, ',', ' ') ?></td>
            <td><?= number_format($r['cout_menage1'],        2, ',', ' ') ?></td>
            <td><?= number_format($r['cout_menage2'],        2, ',', ' ') ?></td>
            <td><?= number_format($r['cout_laverie'],        2, ',', ' ') ?></td>
            <td><?= number_format($r['cout_lavage_sechage'], 2, ',', ' ') ?></td>
            <td><?= number_format($r['prix_vente'],          2, ',', ' ') ?></td>
            <td><?= number_format($r['rentabilite'],         2, ',', ' ') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="9" class="text-center">Aucun résultat pour ces filtres.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
</body>
</html>
