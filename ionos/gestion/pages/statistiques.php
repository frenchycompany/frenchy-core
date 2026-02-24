<?php
include '../config.php';
include '../pages/menu.php';

// Initialiser les filtres
$dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-01'); // Premier jour du mois courant
$dateFin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-t'); // Dernier jour du mois courant

// Calcul du nombre total de jours dans la période
$totalJoursPeriode = (new DateTime($dateDebut))->diff(new DateTime($dateFin))->days + 1;

try {
    // Statistiques de remplissage pour tous les logements
    $stmtLogementsStats = $conn->prepare("
        SELECT 
            l.nom_du_logement AS logement,
            SUM(p.nombre_de_jours_reservation) AS total_nuits,
            SUM(p.nombre_de_personnes) AS total_voyageurs,
            COUNT(p.id) AS interventions,
            ROUND(SUM(p.nombre_de_jours_reservation) / :total_jours * 100, 2) AS taux_remplissage
        FROM planning p
        JOIN liste_logements l ON p.logement_id = l.id
        WHERE p.date BETWEEN :date_debut AND :date_fin
        GROUP BY l.id
        ORDER BY taux_remplissage DESC
    ");
    $stmtLogementsStats->execute([
        ':total_jours' => $totalJoursPeriode,
        ':date_debut' => $dateDebut,
        ':date_fin' => $dateFin
    ]);
    $logementsStats = $stmtLogementsStats->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des statistiques : " . $e->getMessage());
}

// Récupérer les données principales
try {
    // Rentabilité par m²
    $query = $conn->prepare("
        SELECT 
            l.nom_du_logement AS logement,
            l.m2 AS surface_m2,
            COUNT(p.id) AS nb_interventions,
            SUM(CASE WHEN c.type = 'CA' THEN c.montant ELSE 0 END) AS ca_total,
            SUM(CASE WHEN c.type = 'Charge' THEN c.montant ELSE 0 END) AS charges_total,
            ROUND(
                (SUM(CASE WHEN c.type = 'CA' THEN c.montant ELSE 0 END) - SUM(CASE WHEN c.type = 'Charge' THEN c.montant ELSE 0 END)) 
                / (l.m2 * COUNT(p.id)), 
                2
            ) AS rentabilite_m2
        FROM 
            comptabilite c
        JOIN 
            planning p ON c.source_id = p.id
        JOIN 
            liste_logements l ON p.logement_id = l.id
        WHERE 
            c.date_comptabilisation BETWEEN :date_debut AND :date_fin
        GROUP BY 
            l.id
        ORDER BY 
            rentabilite_m2 DESC
    ");
    $query->execute([
        ':date_debut' => $dateDebut,
        ':date_fin' => $dateFin
    ]);
    $result = $query->fetchAll(PDO::FETCH_ASSOC);

    // Activité par jour de la semaine
    $stmtJoursSemaine = $conn->prepare("
        SELECT 
            DAYOFWEEK(p.date) AS jour_semaine, 
            COUNT(*) AS nombre_interventions
        FROM planning p
        WHERE p.date BETWEEN :date_debut AND :date_fin
        GROUP BY DAYOFWEEK(p.date)
        ORDER BY jour_semaine
    ");
    $stmtJoursSemaine->execute([
        ':date_debut' => $dateDebut,
        ':date_fin' => $dateFin
    ]);
    $resultatsJoursSemaine = $stmtJoursSemaine->fetchAll(PDO::FETCH_ASSOC);

    // Initialiser un tableau pour les jours de la semaine
    $joursSemaine = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    $interventionsParJour = array_fill(0, 7, 0);

    foreach ($resultatsJoursSemaine as $resultat) {
        $index = (int)$resultat['jour_semaine'] - 1; // Index basé sur DAYOFWEEK (1 = Dimanche)
        if ($index === 0) {
            $index = 6; // Dimanche à la fin
        } else {
            $index -= 1;
        }
        $interventionsParJour[$index] = $resultat['nombre_interventions'];
    }

    // Performances des logements (CA généré)
    $stmtCA = $conn->prepare("
        SELECT 
            l.nom_du_logement AS logement, 
            SUM(c.montant) AS total_ca
        FROM comptabilite c
        JOIN planning p ON c.source_id = p.id
        JOIN liste_logements l ON p.logement_id = l.id
        WHERE c.type = 'CA' 
          AND c.date_comptabilisation BETWEEN :date_debut AND :date_fin
        GROUP BY l.nom_du_logement
        ORDER BY total_ca DESC
        LIMIT 10
    ");
    $stmtCA->execute([
        ':date_debut' => $dateDebut,
        ':date_fin' => $dateFin
    ]);
    $resultCA = $stmtCA->fetchAll(PDO::FETCH_ASSOC);

    // Charges détaillées
    $chargesFixes = $conn->prepare("
        SELECT SUM(montant) 
        FROM comptabilite 
        WHERE type = 'Charge' AND description LIKE '%fixe%' 
          AND date_comptabilisation BETWEEN :date_debut AND :date_fin
    ");
    $chargesFixes->execute([':date_debut' => $dateDebut, ':date_fin' => $dateFin]);
    $chargesFixes = $chargesFixes->fetchColumn() ?: 0;

    $chargesChauffeur = $conn->prepare("
        SELECT SUM(montant) 
        FROM comptabilite 
        WHERE type = 'Charge' AND description LIKE '%conducteur%' 
          AND date_comptabilisation BETWEEN :date_debut AND :date_fin
    ");
    $chargesChauffeur->execute([':date_debut' => $dateDebut, ':date_fin' => $dateFin]);
    $chargesChauffeur = $chargesChauffeur->fetchColumn() ?: 0;

    $chargesFemmeMenage = $conn->prepare("
        SELECT SUM(montant) 
        FROM comptabilite 
        WHERE type = 'Charge' AND description LIKE '%femme de ménage%' 
          AND date_comptabilisation BETWEEN :date_debut AND :date_fin
    ");
    $chargesFemmeMenage->execute([':date_debut' => $dateDebut, ':date_fin' => $dateFin]);
    $chargesFemmeMenage = $chargesFemmeMenage->fetchColumn() ?: 0;

    $chargesLaverie = $conn->prepare("
        SELECT SUM(montant) 
        FROM comptabilite 
        WHERE type = 'Charge' AND description LIKE '%variable laverie%' 
          AND date_comptabilisation BETWEEN :date_debut AND :date_fin
    ");
    $chargesLaverie->execute([':date_debut' => $dateDebut, ':date_fin' => $dateFin]);
    $chargesLaverie = $chargesLaverie->fetchColumn() ?: 0;

} catch (PDOException $e) {
    die("Erreur lors de la récupération des données : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
 <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Éditer le planning</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-…"
    crossorigin="anonymous"
  />
</head>
<body>
<div class="container mt-4">
    <h2>Statistiques - Performance des Logements</h2>

    <!-- Filtres de période -->
    <form method="GET" class="mb-4">
        <div class="row">
            <div class="col-md-4">
                <label for="date_debut">Date de début :</label>
                <input type="date" id="date_debut" name="date_debut" class="form-control" value="<?= htmlspecialchars($dateDebut) ?>" required>
            </div>
            <div class="col-md-4">
                <label for="date_fin">Date de fin :</label>
                <input type="date" id="date_fin" name="date_fin" class="form-control" value="<?= htmlspecialchars($dateFin) ?>" required>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
            </div>
        </div>
    </form>

    <!-- Résumé des performances -->
    <h3>Rentabilité par m²</h3>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Logement</th>
                <th>Surface (m²)</th>
                <th>CA Total (€)</th>
                <th>Rentabilité (€ / m²)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($result as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['logement']) ?></td>
                    <td><?= number_format($row['surface_m2'], 2, ',', ' ') ?></td>
                    <td><?= number_format($row['ca_total'], 2, ',', ' ') ?></td>
                    <td><?= number_format($row['rentabilite_m2'], 2, ',', ' ') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Graphique des interventions par jour -->
    <h3>Activité Hebdomadaire</h3>
    <canvas id="graphJoursSemaine" height="100"></canvas>
    <script>
        const ctx = document.getElementById('graphJoursSemaine').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($joursSemaine) ?>,
                datasets: [{
                    label: "Nombre d'interventions",
                    data: <?= json_encode($interventionsParJour) ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Nombre d\'interventions'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Jours de la semaine'
                        }
                    }
                }
            }
        });
    </script>

    <!-- Graphique des performances CA -->
    <h3>Performance des logements (CA généré)</h3>
    <?php if (!empty($resultCA)): ?>
        <canvas id="performanceChart" height="100"></canvas>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const ctx = document.getElementById('performanceChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_column($resultCA, 'logement')) ?>,
                        datasets: [{
                            label: 'CA généré (€)',
                            data: <?= json_encode(array_column($resultCA, 'total_ca')) ?>,
                            backgroundColor: 'rgba(153, 102, 255, 0.6)',
                            borderColor: 'rgba(153, 102, 255, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'CA Total (€)'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Logements'
                                }
                            }
                        }
                    }
                });
            });
        </script>
    <?php else: ?>
        <div class="alert alert-warning">Aucune donnée disponible pour la période sélectionnée.</div>
    <?php endif; ?>

    <!-- Tableau des charges -->
    <h3>Détails des Charges</h3>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Type de Charge</th>
                <th>Total (€)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Charges Fixes</td>
                <td><?= number_format($chargesFixes, 2, ',', ' ') ?> €</td>
            </tr>
            <tr>
                <td>Charges Chauffeur</td>
                <td><?= number_format($chargesChauffeur, 2, ',', ' ') ?> €</td>
            </tr>
            <tr>
                <td>Charges Femme de Ménage</td>
                <td><?= number_format($chargesFemmeMenage, 2, ',', ' ') ?> €</td>
            </tr>
            <tr>
                <td>Charges Laverie</td>
                <td><?= number_format($chargesLaverie, 2, ',', ' ') ?> €</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="container mt-4">
    <h2>Statistiques - Taux de Remplissage</h2>

    <!-- Filtres de période -->
    <form method="GET" action="statistiques.php" class="mb-4">
        <div class="row">
            <div class="col-md-4">
                <label for="date_debut">Date de début :</label>
                <input type="date" id="date_debut" name="date_debut" class="form-control" value="<?= htmlspecialchars($dateDebut) ?>" required>
            </div>
            <div class="col-md-4">
                <label for="date_fin">Date de fin :</label>
                <input type="date" id="date_fin" name="date_fin" class="form-control" value="<?= htmlspecialchars($dateFin) ?>" required>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
            </div>
        </div>
    </form>

    <!-- Tableau des statistiques de remplissage -->
    <h3>Statistiques des Logements</h3>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Logement</th>
                <th>Nuits Réservées</th>
                <th>Voyageurs</th>
                <th>Interventions</th>
                <th>Taux de Remplissage (%)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logementsStats as $logement): ?>
                <tr>
                    <td><?= htmlspecialchars($logement['logement']) ?></td>
                    <td><?= $logement['total_nuits'] ?></td>
                    <td><?= $logement['total_voyageurs'] ?></td>
                    <td><?= $logement['interventions'] ?></td>
                    <td><?= number_format($logement['taux_remplissage'], 2, ',', ' ') ?>%</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>


<!-- Bootstrap JS -->
  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-…"
    crossorigin="anonymous"
  ></script>
</body>
</html>

