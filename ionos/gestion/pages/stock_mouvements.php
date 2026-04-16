<?php
/**
 * Stock — Historique des mouvements (entrées, sorties, inventaires)
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';

$feedback = '';
$categories_produit = ['menage' => 'Ménage', 'toilettes' => 'Toilettes', 'cuisine' => 'Cuisine', 'literie' => 'Literie', 'entretien' => 'Entretien', 'bureau' => 'Bureau', 'autre' => 'Autre'];

// POST : nouveau mouvement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrfToken()) {
    if (isset($_POST['add_mouvement'])) {
        $pid = (int)$_POST['produit_id'];
        $type = $_POST['type_mouvement'];
        $qte = (float)$_POST['quantite'];
        $prix = $_POST['prix_unitaire'] !== '' ? (float)$_POST['prix_unitaire'] : null;
        $fid = $_POST['fournisseur_id'] ?: null;
        $lid = $_POST['logement_id'] ?: null;
        $note = trim($_POST['note'] ?? '');

        if ($pid && $qte > 0 && in_array($type, ['entree', 'sortie', 'inventaire'])) {
            try {
                $pdo->prepare("INSERT INTO stock_mouvements (produit_id, type_mouvement, quantite, prix_unitaire, fournisseur_id, logement_id, note) VALUES (?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$pid, $type, $qte, $prix, $fid, $lid, $note]);

                if ($type === 'entree') {
                    $pdo->prepare("UPDATE stock_produits SET stock_actuel = stock_actuel + ? WHERE id = ?")->execute([$qte, $pid]);
                } elseif ($type === 'sortie') {
                    $pdo->prepare("UPDATE stock_produits SET stock_actuel = stock_actuel - ? WHERE id = ?")->execute([$qte, $pid]);
                } elseif ($type === 'inventaire') {
                    $pdo->prepare("UPDATE stock_produits SET stock_actuel = ? WHERE id = ?")->execute([$qte, $pid]);
                }

                if ($prix && $fid) {
                    $pdo->prepare("INSERT INTO stock_prix (produit_id, fournisseur_id, prix_unitaire, date_releve) VALUES (?, ?, ?, CURDATE())")
                        ->execute([$pid, $fid, $prix]);
                }

                $feedback = '<div class="alert alert-success alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>Mouvement enregistré.</div>';
            } catch (PDOException $e) {
                $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    if (isset($_POST['delete_mouvement'])) {
        $mid = (int)$_POST['mouvement_id'];
        $mv = $pdo->prepare("SELECT produit_id, type_mouvement, quantite FROM stock_mouvements WHERE id = ?");
        $mv->execute([$mid]);
        $row = $mv->fetch();
        if ($row) {
            if ($row['type_mouvement'] === 'entree') {
                $pdo->prepare("UPDATE stock_produits SET stock_actuel = stock_actuel - ? WHERE id = ?")->execute([$row['quantite'], $row['produit_id']]);
            } elseif ($row['type_mouvement'] === 'sortie') {
                $pdo->prepare("UPDATE stock_produits SET stock_actuel = stock_actuel + ? WHERE id = ?")->execute([$row['quantite'], $row['produit_id']]);
            }
            $pdo->prepare("DELETE FROM stock_mouvements WHERE id = ?")->execute([$mid]);
            $feedback = '<div class="alert alert-success alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>Mouvement annulé, stock corrigé.</div>';
        }
    }
}

// Filtres
$filtre_type = $_GET['type'] ?? '';
$filtre_produit = $_GET['produit'] ?? '';
$filtre_logement = $_GET['logement'] ?? '';

$sql = "SELECT m.*, p.nom AS produit_nom, p.unite, p.categorie,
               f.nom AS fournisseur_nom, l.nom_du_logement
        FROM stock_mouvements m
        JOIN stock_produits p ON m.produit_id = p.id
        LEFT JOIN stock_fournisseurs f ON m.fournisseur_id = f.id
        LEFT JOIN liste_logements l ON m.logement_id = l.id
        WHERE 1=1";
$params = [];

if ($filtre_type && in_array($filtre_type, ['entree', 'sortie', 'inventaire'])) {
    $sql .= " AND m.type_mouvement = ?";
    $params[] = $filtre_type;
}
if ($filtre_produit) {
    $sql .= " AND m.produit_id = ?";
    $params[] = (int)$filtre_produit;
}
if ($filtre_logement) {
    $sql .= " AND m.logement_id = ?";
    $params[] = (int)$filtre_logement;
}

$sql .= " ORDER BY m.created_at DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$mouvements = $stmt->fetchAll();

$produits = $pdo->query("SELECT id, nom FROM stock_produits WHERE actif = 1 ORDER BY nom")->fetchAll();
$fournisseurs = $pdo->query("SELECT id, nom FROM stock_fournisseurs WHERE actif = 1 ORDER BY nom")->fetchAll();
$logements = $pdo->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll();

$type_labels = ['entree' => ['Entrée', 'bg-success', 'fa-arrow-down'], 'sortie' => ['Sortie', 'bg-danger', 'fa-arrow-up'], 'inventaire' => ['Inventaire', 'bg-info', 'fa-clipboard-check']];
$unites = ['piece' => 'pce', 'litre' => 'L', 'rouleau' => 'rlx', 'kg' => 'kg', 'lot' => 'lot', 'boite' => 'bte', 'sachet' => 'sac', 'bidon' => 'bid'];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fas fa-exchange-alt"></i> Mouvements de stock</h2>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalMvt">
            <i class="fas fa-plus"></i> Nouveau mouvement
        </button>
    </div>

    <?= $feedback ?>

    <!-- Filtres -->
    <div class="d-flex gap-2 mb-3 flex-wrap">
        <a href="?type=" class="btn btn-sm <?= !$filtre_type ? 'btn-primary' : 'btn-outline-secondary' ?>">Tous</a>
        <a href="?type=entree" class="btn btn-sm <?= $filtre_type === 'entree' ? 'btn-success' : 'btn-outline-success' ?>"><i class="fas fa-arrow-down"></i> Entrées</a>
        <a href="?type=sortie" class="btn btn-sm <?= $filtre_type === 'sortie' ? 'btn-danger' : 'btn-outline-danger' ?>"><i class="fas fa-arrow-up"></i> Sorties</a>
        <a href="?type=inventaire" class="btn btn-sm <?= $filtre_type === 'inventaire' ? 'btn-info' : 'btn-outline-info' ?>"><i class="fas fa-clipboard-check"></i> Inventaires</a>
        <select class="form-select form-select-sm" style="width:auto" onchange="location='?produit='+this.value">
            <option value="">Tous produits</option>
            <?php foreach ($produits as $p): ?><option value="<?= $p['id'] ?>" <?= $filtre_produit == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nom']) ?></option><?php endforeach; ?>
        </select>
    </div>

    <!-- Tableau -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Date</th><th>Type</th><th>Produit</th><th class="text-end">Qté</th><th>Prix unit.</th><th>Fournisseur</th><th>Logement</th><th>Note</th><th></th></tr>
                </thead>
                <tbody>
                <?php if (empty($mouvements)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Aucun mouvement enregistré.</td></tr>
                <?php endif; ?>
                <?php foreach ($mouvements as $m):
                    $tl = $type_labels[$m['type_mouvement']] ?? ['?', 'bg-secondary', 'fa-question'];
                ?>
                    <tr>
                        <td><small><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></small></td>
                        <td><span class="badge <?= $tl[1] ?>"><i class="fas <?= $tl[2] ?>"></i> <?= $tl[0] ?></span></td>
                        <td><?= htmlspecialchars($m['produit_nom']) ?></td>
                        <td class="text-end fw-bold"><?= (float)$m['quantite'] ?> <small class="text-muted"><?= $unites[$m['unite']] ?? $m['unite'] ?></small></td>
                        <td><?= $m['prix_unitaire'] ? number_format($m['prix_unitaire'], 2, ',', '') . ' €' : '—' ?></td>
                        <td><small><?= htmlspecialchars($m['fournisseur_nom'] ?? '—') ?></small></td>
                        <td><small><?= htmlspecialchars($m['nom_du_logement'] ?? '—') ?></small></td>
                        <td><small class="text-muted"><?= htmlspecialchars(mb_strimwidth($m['note'] ?? '', 0, 40, '...')) ?></small></td>
                        <td>
                            <form method="post" class="d-inline" onsubmit="return confirm('Annuler ce mouvement ? Le stock sera corrigé.')">
                                <?php echoCsrfField(); ?>
                                <input type="hidden" name="delete_mouvement" value="1">
                                <input type="hidden" name="mouvement_id" value="<?= $m['id'] ?>">
                                <button class="btn btn-outline-danger btn-sm py-0"><i class="fas fa-undo"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal nouveau mouvement -->
<div class="modal fade" id="modalMvt" tabindex="-1">
    <div class="modal-dialog">
        <form method="post">
            <?php echoCsrfField(); ?>
            <input type="hidden" name="add_mouvement" value="1">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="fas fa-exchange-alt"></i> Nouveau mouvement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type_mouvement" class="form-select" required>
                            <option value="entree">Entrée (livraison reçue)</option>
                            <option value="sortie">Sortie (consommation)</option>
                            <option value="inventaire">Inventaire (ajustement)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Produit <span class="text-danger">*</span></label>
                        <select name="produit_id" class="form-select" required>
                            <option value="">— Choisir —</option>
                            <?php foreach ($produits as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Quantité <span class="text-danger">*</span></label>
                            <input type="number" name="quantite" class="form-control" required min="0.5" step="0.5" value="1">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Prix unitaire</label>
                            <input type="number" name="prix_unitaire" class="form-control" min="0" step="0.01" placeholder="€">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Fournisseur</label>
                            <select name="fournisseur_id" class="form-select">
                                <option value="">—</option>
                                <?php foreach ($fournisseurs as $f): ?><option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nom']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Logement</label>
                            <select name="logement_id" class="form-select">
                                <option value="">—</option>
                                <?php foreach ($logements as $l): ?><option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nom_du_logement']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Note</label>
                        <input type="text" name="note" class="form-control" placeholder="Ex: Livraison Action, Ménage apt Zen...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Enregistrer</button>
                </div>
            </div>
        </form>
    </div>
</div>
