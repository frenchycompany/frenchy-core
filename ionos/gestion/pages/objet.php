<?php
include '../config.php';
include '../pages/menu.php';

if (!isset($_GET['id'])) {
    die("ID manquant.");
}
$id = intval($_GET['id']);

// Récupération depuis inventaire_objets (table de l'inventaire)
$stmt = $conn->prepare("
    SELECT o.*, l.nom_du_logement 
    FROM inventaire_objets o
    JOIN liste_logements l ON o.logement_id = l.id
    WHERE o.id = ?
");
$stmt->execute([$id]);
$objet = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$objet) {
    die("Objet introuvable.");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fiche de l’objet : <?= htmlspecialchars($objet['nom_objet']) ?></title>
<style>
body { font-family: Arial,sans-serif; margin:0; background:#f8fafd; font-size:17px; }
h2 { margin: 30px 0 20px 0; color:#1976d2; text-align:center;}
p { margin: 13px 0; }
.img-big { max-width: 97vw; max-height: 350px; border-radius:8px; box-shadow:0 1px 9px #eee; }
@media (max-width: 600px) {
    body { font-size: 15px; }
    .img-big { max-width: 97vw; max-height: 190px; }
}
</style>
</head>
<body>
<h2>Fiche de l’objet : <?= htmlspecialchars($objet['nom_objet']) ?></h2>

<p><strong>Logement :</strong> <?= htmlspecialchars($objet['nom_du_logement']) ?></p>
<p><strong>Marque :</strong> <?= htmlspecialchars($objet['marque']) ?></p>
<p><strong>État :</strong> <?= htmlspecialchars($objet['etat']) ?></p>
<p><strong>Valeur :</strong> <?= htmlspecialchars($objet['valeur']) ?> €</p>
<p><strong>Quantité :</strong> <?= (int)$objet['quantite'] ?></p>
<p><strong>Date d’acquisition :</strong> <?= htmlspecialchars($objet['date_acquisition']) ?></p>
<p><strong>Propriétaire :</strong> <?= htmlspecialchars($objet['proprietaire']) ?></p>
<p><strong>Remarques :</strong> <?= nl2br(htmlspecialchars($objet['remarques'])) ?></p>

<?php if ($objet['photo_path']) : ?>
    <p><strong>Photo :</strong><br>
        <img class="img-big" src="<?= htmlspecialchars($objet['photo_path']) ?>" alt="Photo objet">
    </p>
<?php endif; ?>

<!-- Si tu veux aussi afficher la facture (si ce champ existe dans inventaire_objets) -->
<?php if (isset($objet['facture_path']) && $objet['facture_path']) : ?>
    <p><strong>Facture :</strong><br>
        <a href="<?= htmlspecialchars($objet['facture_path']) ?>" target="_blank">📄 Voir le fichier</a>
    </p>
<?php endif; ?>

</body>
</html>
