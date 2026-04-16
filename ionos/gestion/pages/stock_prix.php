<?php
/**
 * Stock — Relevé de prix & comparatif fournisseurs
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';

$feedback = '';

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrfToken()) {
    if (isset($_POST['add_prix'])) {
        try {
            $pdo->prepare("INSERT INTO stock_prix (produit_id, fournisseur_id, prix_unitaire, url_produit, date_releve) VALUES (?, ?, ?, ?, ?)")
                ->execute([
                    (int)$_POST['produit_id'],
                    (int)$_POST['fournisseur_id'],
                    (float)$_POST['prix_unitaire'],
                    trim($_POST['url_produit'] ?? '') ?: null,
                    $_POST['date_releve'] ?: date('Y-m-d'),
                ]);
            $feedback = '<div class="alert alert-success alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>Prix enregistré.</div>';
        } catch (PDOException $e) {
            $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    if (isset($_POST['add_fournisseur'])) {
        $nom = trim($_POST['nom_fournisseur']);
        if ($nom) {
            $pdo->prepare("INSERT INTO stock_fournisseurs (nom, url, contact) VALUES (?, ?, ?)")
                ->execute([$nom, trim($_POST['url_fournisseur'] ?? ''), trim($_POST['contact_fournisseur'] ?? '')]);
            $feedback = '<div class="alert alert-success alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>Fournisseur ajouté.</div>';
        }
    }

    if (isset($_POST['delete_prix'])) {
        $pdo->prepare("DELETE FROM stock_prix WHERE id = ?")->execute([(int)$_POST['prix_id']]);
        $feedback = '<div class="alert alert-success alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>Prix supprimé.</div>';
    }
}

$produits = $pdo->query("SELECT id, nom FROM stock_produits WHERE actif = 1 ORDER BY nom")->fetchAll();
$fournisseurs = $pdo->query("SELECT id, nom, url FROM stock_fournisseurs WHERE actif = 1 ORDER BY nom")->fetchAll();

// Comparatif : meilleur prix par produit (dernier relevé par fournisseur)
$comparatif = $pdo->query("
    SELECT p.id AS produit_id, p.nom AS produit_nom, p.unite,
           sp.fournisseur_id, f.nom AS fournisseur_nom,
           sp.prix_unitaire, sp.date_releve, sp.url_produit
    FROM stock_prix sp
    JOIN stock_produits p ON sp.produit_id = p.id
    JOIN stock_fournisseurs f ON sp.fournisseur_id = f.id
    WHERE sp.id IN (
        SELECT MAX(sp2.id) FROM stock_prix sp2 GROUP BY sp2.produit_id, sp2.fournisseur_id
    )
    ORDER BY p.nom, sp.prix_unitaire ASC
")->fetchAll();

// Grouper par produit
$par_produit = [];
foreach ($comparatif as $row) {
    $par_produit[$row['produit_id']]['nom'] = $row['produit_nom'];
    $par_produit[$row['produit_id']]['unite'] = $row['unite'];
    $par_produit[$row['produit_id']]['prix'][] = $row;
}

// Historique récent
$filtre_produit = $_GET['produit'] ?? '';
$sql_hist = "SELECT sp.*, p.nom AS produit_nom, f.nom AS fournisseur_nom
             FROM stock_prix sp
             JOIN stock_produits p ON sp.produit_id = p.id
             JOIN stock_fournisseurs f ON sp.fournisseur_id = f.id";
$params_hist = [];
if ($filtre_produit) {
    $sql_hist .= " WHERE sp.produit_id = ?";
    $params_hist[] = (int)$filtre_produit;
}
$sql_hist .= " ORDER BY sp.date_releve DESC, sp.id DESC LIMIT 100";
$stmt = $pdo->prepare($sql_hist);
$stmt->execute($params_hist);
$historique = $stmt->fetchAll();

$unites = ['piece' => 'pce', 'litre' => 'L', 'rouleau' => 'rlx', 'kg' => 'kg', 'lot' => 'lot', 'boite' => 'bte', 'sachet' => 'sac', 'bidon' => 'bid'];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fas fa-tags"></i> Prix & Fournisseurs</h2>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalFournisseur"><i class="fas fa-truck"></i> + Fournisseur</button>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalPrix"><i class="fas fa-plus"></i> Relever un prix</button>
        </div>
    </div>

    <?= $feedback ?>

    <!-- Comparatif -->
    <?php if ($par_produit): ?>
    <h5 class="mb-2"><i class="fas fa-balance-scale"></i> Comparatif meilleur prix</h5>
    <div class="row mb-4">
        <?php foreach ($par_produit as $pid => $data): ?>
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card h-100">
                <div class="card-header py-2">
                    <strong><?= htmlspecialchars($data['nom']) ?></strong>
                    <small class="text-muted">/ <?= $unites[$data['unite']] ?? $data['unite'] ?></small>
                </div>
                <div class="card-body py-2">
                    <?php foreach ($data['prix'] as $idx => $prix): ?>
                    <div class="d-flex justify-content-between align-items-center <?= $idx > 0 ? 'border-top pt-1 mt-1' : '' ?>">
                        <span>
                            <?php if ($idx === 0): ?><i class="fas fa-trophy text-warning"></i><?php endif; ?>
                            <?= htmlspecialchars($prix['fournisseur_nom']) ?>
                        </span>
                        <span>
                            <strong class="<?= $idx === 0 ? 'text-success' : '' ?>"><?= number_format($prix['prix_unitaire'], 2, ',', '') ?> €</strong>
                            <small class="text-muted ms-1"><?= date('d/m', strtotime($prix['date_releve'])) ?></small>
                            <?php if ($prix['url_produit']): ?>
                                <a href="<?= htmlspecialchars($prix['url_produit']) ?>" target="_blank" class="ms-1"><i class="fas fa-external-link-alt text-muted"></i></a>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Historique des prix -->
    <h5 class="mb-2"><i class="fas fa-history"></i> Historique des relevés</h5>
    <div class="d-flex gap-2 mb-2">
        <select class="form-select form-select-sm" style="width:auto" onchange="location='?produit='+this.value">
            <option value="">Tous produits</option>
            <?php foreach ($produits as $p): ?><option value="<?= $p['id'] ?>" <?= $filtre_produit == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nom']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Date</th><th>Produit</th><th>Fournisseur</th><th class="text-end">Prix</th><th>Lien</th><th></th></tr>
                </thead>
                <tbody>
                <?php if (empty($historique)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">Aucun relevé de prix.</td></tr>
                <?php endif; ?>
                <?php foreach ($historique as $h): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($h['date_releve'])) ?></td>
                        <td><?= htmlspecialchars($h['produit_nom']) ?></td>
                        <td><?= htmlspecialchars($h['fournisseur_nom']) ?></td>
                        <td class="text-end fw-bold"><?= number_format($h['prix_unitaire'], 2, ',', '') ?> €</td>
                        <td><?php if ($h['url_produit']): ?><a href="<?= htmlspecialchars($h['url_produit']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm py-0"><i class="fas fa-external-link-alt"></i></a><?php endif; ?></td>
                        <td>
                            <form method="post" class="d-inline" onsubmit="return confirm('Supprimer ?')">
                                <?php echoCsrfField(); ?>
                                <input type="hidden" name="delete_prix" value="1">
                                <input type="hidden" name="prix_id" value="<?= $h['id'] ?>">
                                <button class="btn btn-outline-danger btn-sm py-0"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal relever un prix -->
<div class="modal fade" id="modalPrix" tabindex="-1">
    <div class="modal-dialog">
        <form method="post">
            <?php echoCsrfField(); ?>
            <input type="hidden" name="add_prix" value="1">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="fas fa-tags"></i> Relever un prix</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Produit <span class="text-danger">*</span></label>
                        <select name="produit_id" class="form-select" required><option value="">—</option><?php foreach ($produits as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom']) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="mb-3"><label class="form-label">Fournisseur <span class="text-danger">*</span></label>
                        <select name="fournisseur_id" class="form-select" required><option value="">—</option><?php foreach ($fournisseurs as $f): ?><option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nom']) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6"><label class="form-label">Prix unitaire <span class="text-danger">*</span></label><input type="number" name="prix_unitaire" class="form-control" required min="0" step="0.01" placeholder="€"></div>
                        <div class="col-6"><label class="form-label">Date</label><input type="date" name="date_releve" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Lien produit</label><input type="url" name="url_produit" class="form-control" placeholder="https://..."></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Enregistrer</button></div>
            </div>
        </form>
    </div>
</div>

<!-- Modal ajouter fournisseur -->
<div class="modal fade" id="modalFournisseur" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="post">
            <?php echoCsrfField(); ?>
            <input type="hidden" name="add_fournisseur" value="1">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="fas fa-truck"></i> Fournisseur</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nom <span class="text-danger">*</span></label><input type="text" name="nom_fournisseur" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Site web</label><input type="url" name="url_fournisseur" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Contact</label><input type="text" name="contact_fournisseur" class="form-control"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter</button></div>
            </div>
        </form>
    </div>
</div>
