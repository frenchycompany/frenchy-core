<?php
// web/pages/manage_logements.php
// DB loaded via config.php
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_db.php';

$pdo = getRpiPdo();
// header loaded via menu.php

$feedback = '';

// 1) Traitement du formulaire (ajout / modification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    // Récupération safe des champs
    $id               = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $nom              = $conn->real_escape_string($_POST['nom_du_logement']);
    $adresse          = $conn->real_escape_string($_POST['adresse']);
    $m2               = $_POST['m2'] !== '' ? (float)$_POST['m2'] : null;
    $nb_pers          = $_POST['nombre_de_personnes'] !== '' ? (int)$_POST['nombre_de_personnes'] : null;
    $poid             = $_POST['poid_menage'] !== '' ? (float)$_POST['poid_menage'] : null;
    $prix             = $_POST['prix_vente_menage'] !== '' ? (float)$_POST['prix_vente_menage'] : null;
    $code             = $conn->real_escape_string($_POST['code']);
    $val_loc          = $_POST['valeur_locative'] !== '' ? (float)$_POST['valeur_locative'] : 0;
    $val_fonc         = $_POST['valeur_fonciere'] !== '' ? (float)$_POST['valeur_fonciere'] : 0;
    $ics_url          = $conn->real_escape_string($_POST['ics_url']);

    if ($id) {
        // UPDATE
        $stmt = $conn->prepare("
            UPDATE liste_logements SET
                nom_du_logement     = ?,
                adresse             = ?,
                m2                  = ?,
                nombre_de_personnes = ?,
                poid_menage         = ?,
                prix_vente_menage   = ?,
                code                = ?,
                valeur_locative     = ?,
                valeur_fonciere     = ?,
                ics_url             = ?
            WHERE id = ?
        ");
        // types : s, s, d, i, d, d, s, d, d, s, i
        $stmt->bind_param(
            'ssdiddsddsi',
            $nom,
            $adresse,
            $m2,
            $nb_pers,
            $poid,
            $prix,
            $code,
            $val_loc,
            $val_fonc,
            $ics_url,
            $id
        );
        if ($stmt->execute()) {
            $feedback = "<div class='alert alert-success'>Logement #{$id} mis à jour.</div>";
        } else {
            $feedback = "<div class='alert alert-danger'>Erreur SQL : {$stmt->error}</div>";
        }
    } else {
        // INSERT
        $stmt = $conn->prepare("
            INSERT INTO liste_logements (
                nom_du_logement,
                adresse,
                m2,
                nombre_de_personnes,
                poid_menage,
                prix_vente_menage,
                code,
                valeur_locative,
                valeur_fonciere,
                ics_url
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        // types : s, s, d, i, d, d, s, d, d, s
        $stmt->bind_param(
            'ssdiddsdds',
            $nom,
            $adresse,
            $m2,
            $nb_pers,
            $poid,
            $prix,
            $code,
            $val_loc,
            $val_fonc,
            $ics_url
        );
        if ($stmt->execute()) {
            $newId    = $stmt->insert_id;
            $feedback = "<div class='alert alert-success'>Logement #{$newId} ajouté.</div>";
        } else {
            $feedback = "<div class='alert alert-danger'>Erreur SQL : {$stmt->error}</div>";
        }
    }
}

// 2) Suppression
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    if ($conn->query("DELETE FROM liste_logements WHERE id = {$delId}")) {
        $feedback = "<div class='alert alert-warning'>Logement #{$delId} supprimé.</div>";
    } else {
        $feedback = "<div class='alert alert-danger'>Erreur SQL : {$conn->error}</div>";
    }
}

// 3) Chargement pour édition
$edit = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM liste_logements WHERE id = {$eid}");
    $edit = $res->fetch_assoc();
}

// 4) Récupérer tous les logements
$logements = $conn->query("SELECT * FROM liste_logements ORDER BY id ASC");
?>

<div class="container mt-4">
  <h2>Gestion des Logements</h2>
  <?= $feedback ?>

  <!-- Formulaire add / edit -->
  <form method="post" class="border p-3 mb-4">
    <input type="hidden" name="id" value="<?= htmlspecialchars($edit['id'] ?? '') ?>">
    <div class="form-row">
      <div class="form-group col-md-4">
        <label>Nom du logement</label>
        <input type="text" name="nom_du_logement" class="form-control" required
               value="<?= htmlspecialchars($edit['nom_du_logement'] ?? '') ?>">
      </div>
      <div class="form-group col-md-4">
        <label>Adresse</label>
        <input type="text" name="adresse" class="form-control"
               value="<?= htmlspecialchars($edit['adresse'] ?? '') ?>">
      </div>
      <div class="form-group col-md-2">
        <label>Surface (m²)</label>
        <input type="number" step="0.01" name="m2" class="form-control"
               value="<?= htmlspecialchars($edit['m2'] ?? '') ?>">
      </div>
      <div class="form-group col-md-2">
        <label>Nb pers.</label>
        <input type="number" name="nombre_de_personnes" class="form-control"
               value="<?= htmlspecialchars($edit['nombre_de_personnes'] ?? '') ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group col-md-3">
        <label>Poids ménage</label>
        <input type="number" step="0.01" name="poid_menage" class="form-control"
               value="<?= htmlspecialchars($edit['poid_menage'] ?? '') ?>">
      </div>
      <div class="form-group col-md-3">
        <label>Prix vente ménage</label>
        <input type="number" step="0.01" name="prix_vente_menage" class="form-control"
               value="<?= htmlspecialchars($edit['prix_vente_menage'] ?? '') ?>">
      </div>
      <div class="form-group col-md-2">
        <label>Code</label>
        <input type="text" name="code" class="form-control"
               value="<?= htmlspecialchars($edit['code'] ?? '') ?>">
      </div>
      <div class="form-group col-md-2">
        <label>Valeur locative</label>
        <input type="number" step="0.01" name="valeur_locative" class="form-control"
               value="<?= htmlspecialchars($edit['valeur_locative'] ?? '') ?>">
      </div>
      <div class="form-group col-md-2">
        <label>Valeur foncière</label>
        <input type="number" step="0.01" name="valeur_fonciere" class="form-control"
               value="<?= htmlspecialchars($edit['valeur_fonciere'] ?? '') ?>">
      </div>
    </div>
    <div class="form-group">
      <label>Lien ICS</label>
      <input type="url" name="ics_url" class="form-control"
             value="<?= htmlspecialchars($edit['ics_url'] ?? '') ?>">
    </div>
    <button type="submit" name="save" class="btn btn-primary">
      <?= $edit ? 'Enregistrer' : 'Ajouter' ?>
    </button>
    <?php if ($edit): ?>
      <a href="manage_logements.php" class="btn btn-secondary">Annuler</a>
    <?php endif; ?>
  </form>

  <!-- Tableau des logements -->
  <table class="table table-bordered table-hover">
    <thead class="thead-dark text-center">
      <tr>
        <th>ID</th>
        <th>Nom</th>
        <th>Adresse</th>
        <th>m²</th>
        <th>Pers.</th>
        <th>Code</th>
        <th>ICS URL</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $logements->fetch_assoc()): ?>
      <tr class="text-center">
        <td><?= $row['id'] ?></td>
        <td><?= htmlspecialchars($row['nom_du_logement']) ?></td>
        <td><?= htmlspecialchars($row['adresse']) ?></td>
        <td><?= htmlspecialchars($row['m2']) ?></td>
        <td><?= htmlspecialchars($row['nombre_de_personnes']) ?></td>
        <td><?= htmlspecialchars($row['code']) ?></td>
        <td>
          <?php if ($row['ics_url']): ?>
            <a href="<?= htmlspecialchars($row['ics_url']) ?>" target="_blank">Voir ICS</a>
          <?php endif; ?>
        </td>
        <td>
          <a href="?edit=<?= $row['id'] ?>" class="btn btn-sm btn-info">✏️</a>
          <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger"
             onclick="return confirm('Supprimer ce logement ?')">🗑️</a>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php // footer inline ?>
