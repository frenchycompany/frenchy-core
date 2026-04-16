<?php
/**
 * Stock — Catalogue produits & niveaux de stock
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';

// Auto-create tables
try {
    $pdo->exec(file_get_contents(__DIR__ . '/../sql/stock_setup.sql'));
} catch (PDOException $e) { /* tables exist */ }

$feedback = '';
$categories = ['menage' => 'Ménage', 'toilettes' => 'Toilettes', 'cuisine' => 'Cuisine', 'literie' => 'Literie', 'entretien' => 'Entretien', 'bureau' => 'Bureau', 'autre' => 'Autre'];
$unites = ['piece' => 'Pièce', 'litre' => 'Litre', 'rouleau' => 'Rouleau', 'kg' => 'Kg', 'lot' => 'Lot', 'boite' => 'Boîte', 'sachet' => 'Sachet', 'bidon' => 'Bidon'];

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrfToken()) {
    if (isset($_POST['add_produit'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO stock_produits (nom, categorie, unite, stock_actuel, seuil_alerte, logement_id, reference, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                trim($_POST['nom']),
                $_POST['categorie'],
                $_POST['unite'],
                (float)($_POST['stock_actuel'] ?? 0),
                (float)($_POST['seuil_alerte'] ?? 5),
                $_POST['logement_id'] ?: null,
                trim($_POST['reference'] ?? ''),
                trim($_POST['notes'] ?? ''),
            ]);
            $feedback = '<div class="alert alert-success alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>Produit ajouté.</div>';
        } catch (PDOException $e) {
            $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    if (isset($_POST['edit_produit'])) {
        try {
            $stmt = $pdo->prepare("UPDATE stock_produits SET nom=?, categorie=?, unite=?, seuil_alerte=?, logement_id=?, reference=?, notes=? WHERE id=?");
            $stmt->execute([
                trim($_POST['nom']),
                $_POST['categorie'],
                $_POST['unite'],
                (float)($_POST['seuil_alerte'] ?? 5),
                $_POST['logement_id'] ?: null,
                trim($_POST['reference'] ?? ''),
                trim($_POST['notes'] ?? ''),
                (int)$_POST['produit_id'],
            ]);
            $feedback = '<div class="alert alert-success alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>Produit modifié.</div>';
        } catch (PDOException $e) {
            $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    if (isset($_POST['delete_produit'])) {
        $pdo->prepare("DELETE FROM stock_produits WHERE id = ?")->execute([(int)$_POST['produit_id']]);
        $feedback = '<div class="alert alert-success alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>Produit supprimé.</div>';
    }

    if (isset($_POST['mouvement_rapide'])) {
        $pid = (int)$_POST['produit_id'];
        $type = $_POST['type_mouvement'];
        $qte = (float)$_POST['quantite'];
        if ($qte > 0 && in_array($type, ['entree', 'sortie'])) {
            $pdo->prepare("INSERT INTO stock_mouvements (produit_id, type_mouvement, quantite, logement_id, note) VALUES (?, ?, ?, ?, ?)")
                ->execute([$pid, $type, $qte, $_POST['logement_id'] ?: null, trim($_POST['note'] ?? '')]);
            $signe = $type === 'entree' ? '+' : '-';
            $pdo->prepare("UPDATE stock_produits SET stock_actuel = stock_actuel {$signe} ? WHERE id = ?")->execute([$qte, $pid]);
            $feedback = '<div class="alert alert-success alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>Stock mis à jour.</div>';
        }
    }
}

// Filtre catégorie
$filtre_cat = $_GET['cat'] ?? '';
$sql = "SELECT p.*, l.nom_du_logement FROM stock_produits p LEFT JOIN liste_logements l ON p.logement_id = l.id WHERE p.actif = 1";
$params = [];
if ($filtre_cat && array_key_exists($filtre_cat, $categories)) {
    $sql .= " AND p.categorie = ?";
    $params[] = $filtre_cat;
}
$sql .= " ORDER BY p.categorie, p.nom";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produits = $stmt->fetchAll();

$logements = $pdo->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll();

$alertes = array_filter($produits, fn($p) => $p['stock_actuel'] <= $p['seuil_alerte']);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fas fa-box"></i> Catalogue produits</h2>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAdd">
            <i class="fas fa-plus"></i> Nouveau produit
        </button>
    </div>

    <?= $feedback ?>

    <?php if ($alertes): ?>
    <div class="alert alert-warning py-2">
        <i class="fas fa-exclamation-triangle"></i> <strong><?= count($alertes) ?> produit(s) sous le seuil :</strong>
        <?php foreach (array_slice($alertes, 0, 5) as $a): ?>
            <span class="badge bg-danger ms-1"><?= htmlspecialchars($a['nom']) ?> (<?= $a['stock_actuel'] ?>)</span>
        <?php endforeach; ?>
        <?php if (count($alertes) > 5): ?><span class="text-muted">+<?= count($alertes) - 5 ?> autre(s)</span><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="d-flex gap-2 mb-3 flex-wrap">
        <a href="?cat=" class="btn btn-sm <?= !$filtre_cat ? 'btn-primary' : 'btn-outline-secondary' ?>">Tous (<?= count($produits) ?>)</a>
        <?php foreach ($categories as $key => $label): ?>
            <?php $count = count(array_filter($produits, fn($p) => $p['categorie'] === $key)); if (!$count && $filtre_cat !== $key) continue; ?>
            <a href="?cat=<?= $key ?>" class="btn btn-sm <?= $filtre_cat === $key ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $label ?> (<?= $count ?>)</a>
        <?php endforeach; ?>
    </div>

    <!-- Tableau produits -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Produit</th>
                        <th>Catégorie</th>
                        <th class="text-center">Stock</th>
                        <th>Unité</th>
                        <th>Logement</th>
                        <th class="text-center">Mvt rapide</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($produits)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Aucun produit. Cliquez sur "Nouveau produit" pour commencer.</td></tr>
                <?php endif; ?>
                <?php foreach ($produits as $p): ?>
                    <?php $alerte = $p['stock_actuel'] <= $p['seuil_alerte']; ?>
                    <tr class="<?= $alerte ? 'table-warning' : '' ?>">
                        <td>
                            <strong><?= htmlspecialchars($p['nom']) ?></strong>
                            <?php if ($p['reference']): ?><br><small class="text-muted"><?= htmlspecialchars($p['reference']) ?></small><?php endif; ?>
                        </td>
                        <td><span class="badge bg-secondary"><?= $categories[$p['categorie']] ?? $p['categorie'] ?></span></td>
                        <td class="text-center">
                            <span class="fw-bold <?= $alerte ? 'text-danger' : 'text-success' ?>"><?= (float)$p['stock_actuel'] ?></span>
                            <?php if ($alerte): ?><i class="fas fa-exclamation-circle text-danger ms-1" title="Sous le seuil (<?= (float)$p['seuil_alerte'] ?>)"></i><?php endif; ?>
                        </td>
                        <td><?= $unites[$p['unite']] ?? $p['unite'] ?></td>
                        <td><small><?= htmlspecialchars($p['nom_du_logement'] ?? 'Global') ?></small></td>
                        <td class="text-center">
                            <form method="post" class="d-inline-flex gap-1 align-items-center">
                                <?php echoCsrfField(); ?>
                                <input type="hidden" name="mouvement_rapide" value="1">
                                <input type="hidden" name="produit_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="logement_id" value="<?= $p['logement_id'] ?? '' ?>">
                                <input type="hidden" name="note" value="">
                                <input type="number" name="quantite" value="1" min="0.5" step="0.5" class="form-control form-control-sm" style="width:60px">
                                <button type="submit" name="type_mouvement" value="entree" class="btn btn-sm btn-outline-success py-0" title="Entrée"><i class="fas fa-plus"></i></button>
                                <button type="submit" name="type_mouvement" value="sortie" class="btn btn-sm btn-outline-danger py-0" title="Sortie"><i class="fas fa-minus"></i></button>
                            </form>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary py-0" onclick='editProduit(<?= json_encode($p) ?>)' title="Modifier"><i class="fas fa-edit"></i></button>
                                <form method="post" class="d-inline" onsubmit="return confirm('Supprimer ce produit ?')">
                                    <?php echoCsrfField(); ?>
                                    <input type="hidden" name="delete_produit" value="1">
                                    <input type="hidden" name="produit_id" value="<?= $p['id'] ?>">
                                    <button class="btn btn-outline-danger py-0"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Ajouter -->
<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog">
        <form method="post">
            <?php echoCsrfField(); ?>
            <input type="hidden" name="add_produit" value="1">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus"></i> Nouveau produit</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom <span class="text-danger">*</span></label>
                        <input type="text" name="nom" class="form-control" required placeholder="Ex: Papier toilette 3 plis">
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Catégorie</label>
                            <select name="categorie" class="form-select">
                                <?php foreach ($categories as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Unité</label>
                            <select name="unite" class="form-select">
                                <?php foreach ($unites as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Stock initial</label>
                            <input type="number" name="stock_actuel" class="form-control" value="0" min="0" step="0.5">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Seuil alerte</label>
                            <input type="number" name="seuil_alerte" class="form-control" value="5" min="0" step="0.5">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Logement (vide = stock global)</label>
                        <select name="logement_id" class="form-select">
                            <option value="">Stock global</option>
                            <?php foreach ($logements as $l): ?><option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nom_du_logement']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Référence</label>
                        <input type="text" name="reference" class="form-control" placeholder="Ex: code EAN, réf fournisseur">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Ajouter</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Modifier -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <form method="post">
            <?php echoCsrfField(); ?>
            <input type="hidden" name="edit_produit" value="1">
            <input type="hidden" name="produit_id" id="editId">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit"></i> Modifier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nom</label><input type="text" name="nom" id="editNom" class="form-control" required></div>
                    <div class="row mb-3">
                        <div class="col-6"><label class="form-label">Catégorie</label><select name="categorie" id="editCat" class="form-select"><?php foreach ($categories as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                        <div class="col-6"><label class="form-label">Unité</label><select name="unite" id="editUnite" class="form-select"><?php foreach ($unites as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Seuil alerte</label><input type="number" name="seuil_alerte" id="editSeuil" class="form-control" min="0" step="0.5"></div>
                    <div class="mb-3"><label class="form-label">Logement</label><select name="logement_id" id="editLogement" class="form-select"><option value="">Stock global</option><?php foreach ($logements as $l): ?><option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nom_du_logement']) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Référence</label><input type="text" name="reference" id="editRef" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" id="editNotes" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function editProduit(p) {
    document.getElementById('editId').value = p.id;
    document.getElementById('editNom').value = p.nom;
    document.getElementById('editCat').value = p.categorie;
    document.getElementById('editUnite').value = p.unite;
    document.getElementById('editSeuil').value = p.seuil_alerte;
    document.getElementById('editLogement').value = p.logement_id || '';
    document.getElementById('editRef').value = p.reference || '';
    document.getElementById('editNotes').value = p.notes || '';
    new bootstrap.Modal(document.getElementById('modalEdit')).show();
}
</script>
