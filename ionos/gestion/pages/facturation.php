<?php
include '../config.php'; // Connexion à la base de données
include '../pages/menu.php'; // Inclusion du menu

// Configuration des erreurs pour débogage
ini_set('display_startup_errors', 1);

// Récupération des intervenants
try {
    $intervenants = $conn->query("
        SELECT id, nom 
        FROM intervenant 
        ORDER BY nom ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des intervenants : " . $e->getMessage());
}

// Récupération des données du formulaire
$intervenantId = filter_input(INPUT_GET, 'intervenant', FILTER_VALIDATE_INT);
$mois = filter_input(INPUT_GET, 'mois', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
$annee = filter_input(INPUT_GET, 'annee', FILTER_VALIDATE_INT);

$prestations = [];
$totalFacture = 0;
$nomIntervenant = '';
$numeroFacture = '';

if ($intervenantId && $mois && $annee) {
    // Période sélectionnée
    $dateDebut = "$annee-$mois-01";
    $dateFin = date('Y-m-t', strtotime($dateDebut));

    try {
        // Récupération des prestations par intervenant pour la période donnée
        $stmt = $conn->prepare("
            SELECT 
                c.type AS prestation_type,
                COUNT(c.id) AS nombre_prestations,
                ROUND(AVG(c.montant), 2) AS prix_moyen,
                ROUND(COUNT(c.id) * AVG(c.montant), 2) AS montant_total
            FROM comptabilite c
            WHERE c.intervenant_id = :intervenantId 
              AND c.date_comptabilisation BETWEEN :dateDebut AND :dateFin
            GROUP BY c.type
        ");
        $stmt->execute([
            ':intervenantId' => $intervenantId,
            ':dateDebut' => $dateDebut,
            ':dateFin' => $dateFin,
        ]);
        $prestations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcul du montant total global
        $totalFacture = array_sum(array_column($prestations, 'montant_total'));

        // Génération du numéro de facture unique
        $numeroFacture = sprintf("%d-%02d-%d-%03d", $intervenantId, $mois, $annee, rand(100, 999));
    } catch (PDOException $e) {
        die("Erreur lors de la récupération des prestations : " . $e->getMessage());
    }

    // Récupération des informations de l'intervenant
    $stmtIntervenant = $conn->prepare("SELECT nom FROM intervenant WHERE id = :id");
    $stmtIntervenant->execute([':id' => $intervenantId]);
    $nomIntervenant = $stmtIntervenant->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Édition de Facture</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                font-family: Arial, sans-serif;
            }
            .container, .facture {
                margin: 0;
                padding: 0;
            }
            .facture {
                page-break-inside: avoid;
            }
            .facture .btn, form, .alert {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <h2>Édition de Facture</h2>

    <!-- Formulaire de sélection -->
    <form method="GET" action="facturation.php" class="mb-4">
        <div class="row">
            <div class="col-md-4">
                <label for="intervenant">Intervenant :</label>
                <select name="intervenant" id="intervenant" class="form-control" required>
                    <option value="">-- Sélectionnez --</option>
                    <?php foreach ($intervenants as $intervenant): ?>
                        <option value="<?= $intervenant['id'] ?>" <?= $intervenant['id'] == $intervenantId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($intervenant['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="mois">Mois :</label>
                <select name="mois" id="mois" class="form-control" required>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m == $mois ? 'selected' : '' ?>>
                            <?= (new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE))->format(mktime(0, 0, 0, $m, 1)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="annee">Année :</label>
                <select name="annee" id="annee" class="form-control" required>
                    <?php for ($a = date('Y'); $a >= date('Y') - 5; $a--): ?>
                        <option value="<?= $a ?>" <?= $a == $annee ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-12 text-right">
                <button type="submit" class="btn btn-primary">Générer la Facture</button>
            </div>
        </div>
    </form>

    <!-- Affichage de la facture -->
    <?php if (!empty($prestations)): ?>
        <div class="facture mt-4" id="facture">
            <h4>Facture - <?= htmlspecialchars($nomIntervenant) ?></h4>
            <p><strong>Numéro de Facture :</strong> <?= htmlspecialchars($numeroFacture) ?></p>
            <p><strong>Frenchy Company</strong><br>
            94 rue Jean Jaurès<br>
            60610 Lacroix-Saint-Ouen<br>
            Immatriculée sous le RCS 931784433<br></p>

            <p><strong>Période :</strong> <?= (new IntlDateFormatter('fr_FR', IntlDateFormatter::LONG, IntlDateFormatter::NONE))->format(mktime(0, 0, 0, $mois, 1, $annee)) ?></p>

            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nombre de Prestations</th>
                        <th>Type de Prestation</th>
                        <th>Prix Moyen (€)</th>
                        <th>Montant (€)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prestations as $prestation): ?>
                        <tr>
                            <td><?= $prestation['nombre_prestations'] ?></td>
                            <td><?= htmlspecialchars($prestation['prestation_type']) ?></td>
                            <td><?= number_format($prestation['prix_moyen'], 2, ',', ' ') ?></td>
                            <td><?= number_format($prestation['montant_total'], 2, ',', ' ') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-right">Total :</th>
                        <th><?= number_format($totalFacture, 2, ',', ' ') ?> €</th>
                    </tr>
                </tfoot>
            </table>
            <p class="text-right">Montant total non soumis à la TVA.</p>
            <button onclick="imprimerFacture()" class="btn btn-primary mt-3">Imprimer la Facture</button>
        </div>
    <?php elseif ($intervenantId && $mois && $annee): ?>
        <div class="alert alert-warning">Aucune prestation trouvée pour cet intervenant et cette période.</div>
    <?php endif; ?>
</div>

<script>
    function imprimerFacture() {
        const facture = document.getElementById('facture');
        const nouvelleFenetre = window.open('', '_blank');
        nouvelleFenetre.document.write(`
            <html>
            <head>
                <title>Facture</title>
                <link rel="stylesheet" href="../css/style.css">
            </head>
            <body>${facture.outerHTML}</body>
            </html>
        `);
        nouvelleFenetre.document.close();
        nouvelleFenetre.print();
    }
</script>
</body>
</html>
