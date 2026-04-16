<?php
/**
 * Stock — Dashboard : alertes, coûts, liste de courses
 */
include '../config.php';
include '../pages/menu.php';

// Auto-create tables
try {
    $pdo->exec(file_get_contents(__DIR__ . '/../sql/stock_setup.sql'));
} catch (PDOException $e) { /* tables exist */ }

$categories = ['menage' => 'Ménage', 'toilettes' => 'Toilettes', 'cuisine' => 'Cuisine', 'literie' => 'Literie', 'entretien' => 'Entretien', 'bureau' => 'Bureau', 'autre' => 'Autre'];
$unites = ['piece' => 'pce', 'litre' => 'L', 'rouleau' => 'rlx', 'kg' => 'kg', 'lot' => 'lot', 'boite' => 'bte', 'sachet' => 'sac', 'bidon' => 'bid'];

// Stats globales
$total_produits = (int)$pdo->query("SELECT COUNT(*) FROM stock_produits WHERE actif = 1")->fetchColumn();
$alertes = $pdo->query("SELECT p.*, l.nom_du_logement FROM stock_produits p LEFT JOIN liste_logements l ON p.logement_id = l.id WHERE p.actif = 1 AND p.stock_actuel <= p.seuil_alerte ORDER BY p.stock_actuel ASC")->fetchAll();
$total_mouvements_mois = (int)$pdo->query("SELECT COUNT(*) FROM stock_mouvements WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();

// Coût des entrées ce mois
$cout_mois = $pdo->query("SELECT COALESCE(SUM(quantite * prix_unitaire), 0) FROM stock_mouvements WHERE type_mouvement = 'entree' AND prix_unitaire IS NOT NULL AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();

// Coût par logement (sorties du mois)
$couts_logements = $pdo->query("
    SELECT l.nom_du_logement, COUNT(m.id) AS nb_sorties,
           COALESCE(SUM(m.quantite * m.prix_unitaire), 0) AS cout
    FROM stock_mouvements m
    JOIN liste_logements l ON m.logement_id = l.id
    WHERE m.type_mouvement = 'sortie'
    AND m.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
    GROUP BY m.logement_id
    ORDER BY cout DESC
")->fetchAll();

// Top consommation (produits les plus sortis ce mois)
$top_conso = $pdo->query("
    SELECT p.nom, SUM(m.quantite) AS total_sorti, p.unite
    FROM stock_mouvements m
    JOIN stock_produits p ON m.produit_id = p.id
    WHERE m.type_mouvement = 'sortie'
    AND m.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
    GROUP BY m.produit_id
    ORDER BY total_sorti DESC
    LIMIT 10
")->fetchAll();

// Liste de courses : produits sous le seuil + meilleur prix
$courses = $pdo->query("
    SELECT p.id, p.nom, p.stock_actuel, p.seuil_alerte, p.unite, p.categorie,
           l.nom_du_logement,
           sp_best.prix_unitaire AS meilleur_prix,
           f_best.nom AS meilleur_fournisseur
    FROM stock_produits p
    LEFT JOIN liste_logements l ON p.logement_id = l.id
    LEFT JOIN (
        SELECT sp1.produit_id, sp1.prix_unitaire, sp1.fournisseur_id
        FROM stock_prix sp1
        WHERE sp1.id = (
            SELECT sp2.id FROM stock_prix sp2
            WHERE sp2.produit_id = sp1.produit_id
            ORDER BY sp2.prix_unitaire ASC, sp2.date_releve DESC
            LIMIT 1
        )
    ) sp_best ON sp_best.produit_id = p.id
    LEFT JOIN stock_fournisseurs f_best ON sp_best.fournisseur_id = f_best.id
    WHERE p.actif = 1 AND p.stock_actuel <= p.seuil_alerte
    ORDER BY p.categorie, p.nom
")->fetchAll();

// Derniers mouvements
$derniers = $pdo->query("
    SELECT m.*, p.nom AS produit_nom, l.nom_du_logement
    FROM stock_mouvements m
    JOIN stock_produits p ON m.produit_id = p.id
    LEFT JOIN liste_logements l ON m.logement_id = l.id
    ORDER BY m.created_at DESC LIMIT 10
")->fetchAll();

$type_labels = ['entree' => ['Entrée', 'text-success', 'fa-arrow-down'], 'sortie' => ['Sortie', 'text-danger', 'fa-arrow-up'], 'inventaire' => ['Inventaire', 'text-info', 'fa-clipboard-check']];
?>

<div class="container-fluid py-4">
    <h2 class="mb-3"><i class="fas fa-warehouse"></i> Stock — Dashboard</h2>

    <!-- KPIs -->
    <div class="row g-2 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center py-2 <?= count($alertes) > 0 ? 'border-danger' : '' ?>">
                <h3 class="mb-0 <?= count($alertes) > 0 ? 'text-danger' : '' ?>"><?= count($alertes) ?></h3>
                <small class="text-muted">Alertes stock</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center py-2">
                <h3 class="mb-0"><?= $total_produits ?></h3>
                <small class="text-muted">Produits</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center py-2">
                <h3 class="mb-0"><?= $total_mouvements_mois ?></h3>
                <small class="text-muted">Mvts ce mois</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center py-2">
                <h3 class="mb-0"><?= number_format($cout_mois, 0, ',', ' ') ?> €</h3>
                <small class="text-muted">Achats ce mois</small>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Colonne gauche -->
        <div class="col-lg-8">
            <!-- Liste de courses -->
            <?php if ($courses): ?>
            <div class="card mb-4 border-warning">
                <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between">
                    <span><i class="fas fa-shopping-cart"></i> Liste de courses (<?= count($courses) ?> produits)</span>
                    <button class="btn btn-sm btn-outline-warning" onclick="printCourses()"><i class="fas fa-print"></i> Imprimer</button>
                </div>
                <div class="table-responsive" id="tableCourses">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Produit</th><th>Catégorie</th><th class="text-center">Stock</th><th class="text-center">Seuil</th><th class="text-end">Meilleur prix</th><th>Fournisseur</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($courses as $c): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($c['nom']) ?></strong>
                                    <?php if ($c['nom_du_logement']): ?><br><small class="text-muted"><?= htmlspecialchars($c['nom_du_logement']) ?></small><?php endif; ?>
                                </td>
                                <td><small><?= $categories[$c['categorie']] ?? $c['categorie'] ?></small></td>
                                <td class="text-center text-danger fw-bold"><?= (float)$c['stock_actuel'] ?> <?= $unites[$c['unite']] ?? '' ?></td>
                                <td class="text-center"><?= (float)$c['seuil_alerte'] ?></td>
                                <td class="text-end"><?= $c['meilleur_prix'] ? number_format($c['meilleur_prix'], 2, ',', '') . ' €' : '—' ?></td>
                                <td><small><?= htmlspecialchars($c['meilleur_fournisseur'] ?? '—') ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Coût par logement -->
            <?php if ($couts_logements): ?>
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-home"></i> Coût par logement (ce mois)</div>
                <div class="card-body py-2">
                    <?php $max_cout = max(array_column($couts_logements, 'cout')) ?: 1; foreach ($couts_logements as $cl): ?>
                    <div class="d-flex align-items-center mb-1">
                        <span class="me-2 text-nowrap" style="min-width:180px;font-size:0.9rem"><?= htmlspecialchars($cl['nom_du_logement']) ?> <small class="text-muted">(<?= $cl['nb_sorties'] ?> sorties)</small></span>
                        <div class="flex-grow-1">
                            <div class="progress" style="height:18px">
                                <div class="progress-bar bg-primary" style="width:<?= round($cl['cout'] / $max_cout * 100) ?>%"><?= number_format($cl['cout'], 0, ',', '') ?> €</div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top consommation -->
            <?php if ($top_conso): ?>
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-fire"></i> Top consommation (ce mois)</div>
                <div class="card-body py-2">
                    <?php $max_conso = max(array_column($top_conso, 'total_sorti')) ?: 1; foreach ($top_conso as $tc): ?>
                    <div class="d-flex align-items-center mb-1">
                        <span class="me-2 text-nowrap" style="min-width:180px;font-size:0.9rem"><?= htmlspecialchars($tc['nom']) ?></span>
                        <div class="flex-grow-1">
                            <div class="progress" style="height:18px">
                                <div class="progress-bar bg-danger bg-opacity-75" style="width:<?= round($tc['total_sorti'] / $max_conso * 100) ?>%"><?= (float)$tc['total_sorti'] ?> <?= $unites[$tc['unite']] ?? '' ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Colonne droite -->
        <div class="col-lg-4">
            <!-- Derniers mouvements -->
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-clock"></i> Derniers mouvements</div>
                <div class="list-group list-group-flush">
                    <?php if (empty($derniers)): ?>
                        <div class="list-group-item text-muted text-center py-3">Aucun mouvement.</div>
                    <?php endif; ?>
                    <?php foreach ($derniers as $d):
                        $tl = $type_labels[$d['type_mouvement']] ?? ['?', 'text-secondary', 'fa-question'];
                    ?>
                    <div class="list-group-item py-2">
                        <div class="d-flex justify-content-between">
                            <span class="<?= $tl[1] ?>"><i class="fas <?= $tl[2] ?>"></i> <?= htmlspecialchars($d['produit_nom']) ?></span>
                            <strong class="<?= $tl[1] ?>"><?= $d['type_mouvement'] === 'sortie' ? '-' : '+' ?><?= (float)$d['quantite'] ?></strong>
                        </div>
                        <small class="text-muted"><?= date('d/m H:i', strtotime($d['created_at'])) ?><?= $d['nom_du_logement'] ? ' — ' . htmlspecialchars($d['nom_du_logement']) : '' ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer text-center">
                    <a href="stock_mouvements.php" class="text-decoration-none">Voir tout <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <!-- Liens rapides -->
            <div class="card">
                <div class="card-header"><i class="fas fa-bolt"></i> Accès rapide</div>
                <div class="list-group list-group-flush">
                    <a href="stock_produits.php" class="list-group-item list-group-item-action"><i class="fas fa-box me-2"></i> Catalogue produits</a>
                    <a href="stock_mouvements.php" class="list-group-item list-group-item-action"><i class="fas fa-exchange-alt me-2"></i> Mouvements</a>
                    <a href="stock_prix.php" class="list-group-item list-group-item-action"><i class="fas fa-tags me-2"></i> Prix & Fournisseurs</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function printCourses() {
    const table = document.getElementById('tableCourses').innerHTML;
    const w = window.open('', '', 'width=800,height=600');
    w.document.write('<html><head><title>Liste de courses</title><style>table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px;text-align:left;font-size:13px}th{background:#f5f5f5}</style></head><body>');
    w.document.write('<h2>Liste de courses — ' + new Date().toLocaleDateString('fr-FR') + '</h2>');
    w.document.write(table);
    w.document.write('</body></html>');
    w.document.close();
    w.print();
}
</script>
