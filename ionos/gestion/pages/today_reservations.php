<?php

$q = $conn->query("
  SELECT r.id, r.prenom, r.nom, r.telephone, r.start_sent,
         l.nom_du_logement
    FROM reservation r
    LEFT JOIN liste_logements l ON r.logement_id = l.id
   WHERE r.date_arrivee = CURDATE()
   ORDER BY r.heure_arrivee ASC
");

?>
<div class="container mt-4">
  <h2>Arrivées du jour <?= date('Y-m-d') ?></h2>
  <table class="table table-striped text-center">
    <thead><tr>
      <th>Logement</th><th>Client</th><th>Mobile</th><th>SMS</th>
    </tr></thead>
    <tbody>
    <?php if($q->num_rows):
      while($r = $q->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($r['nom_du_logement']?:'–') ?></td>
        <td><?= htmlspecialchars($r['prenom'].' '.$r['nom']) ?></td>
        <td><?= htmlspecialchars($r['telephone']) ?></td>
        <td>
          <?php if(!$r['start_sent']): ?>
            <a href="send_sms.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-success">
              Envoyer SMS
            </a>
          <?php else: ?>
            <span class="text-muted">Envoyé</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endwhile;
    else: ?>
      <tr><td colspan="4">Aucune arrivée aujourd’hui.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

