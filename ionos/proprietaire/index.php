<?php
/**
 * Espace Propriétaire - Dashboard
 * Frenchy Conciergerie
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

$security = new Security($conn);
$settings = getAllSettings($conn);

// Vérification de l'authentification
if (!isset($_SESSION['proprietaire_id'])) {
    header('Location: login.php');
    exit;
}

$proprietaire_id = $_SESSION['proprietaire_id'];

// Récupération des infos du propriétaire
$stmt = $conn->prepare("SELECT * FROM FC_proprietaires WHERE id = ? AND actif = 1");
$stmt->execute([$proprietaire_id]);
$proprietaire = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proprietaire) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Mise à jour de la dernière connexion
$stmt = $conn->prepare("UPDATE FC_proprietaires SET derniere_connexion = NOW() WHERE id = ?");
$stmt->execute([$proprietaire_id]);

// Récupération des logements du propriétaire
$stmt = $conn->prepare("SELECT * FROM FC_logements WHERE proprietaire_id = ? AND actif = 1 ORDER BY titre");
$stmt->execute([$proprietaire_id]);
$logements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques globales
$stats = [
    'total_logements' => count($logements),
    'revenu_total' => 0,
    'revenu_mois' => 0,
    'reservations_mois' => 0,
    'taux_occupation' => 0
];

if (!empty($logements)) {
    $logement_ids = array_column($logements, 'id');
    $placeholders = str_repeat('?,', count($logement_ids) - 1) . '?';

    // Revenus du mois en cours
    $stmt = $conn->prepare("SELECT SUM(revenu_net) as total, SUM(nb_reservations) as reservations, AVG(taux_occupation) as occupation
                            FROM FC_revenus
                            WHERE logement_id IN ($placeholders)
                            AND MONTH(mois) = MONTH(CURRENT_DATE)
                            AND YEAR(mois) = YEAR(CURRENT_DATE)");
    $stmt->execute($logement_ids);
    $mois_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $stats['revenu_mois'] = $mois_stats['total'] ?? 0;
    $stats['reservations_mois'] = $mois_stats['reservations'] ?? 0;
    $stats['taux_occupation'] = round($mois_stats['occupation'] ?? 0);

    // Revenus totaux
    $stmt = $conn->prepare("SELECT SUM(revenu_net) as total FROM FC_revenus WHERE logement_id IN ($placeholders)");
    $stmt->execute($logement_ids);
    $stats['revenu_total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Graphique des 12 derniers mois
    $stmt = $conn->prepare("SELECT DATE_FORMAT(mois, '%Y-%m') as mois, SUM(revenu_net) as revenu
                            FROM FC_revenus
                            WHERE logement_id IN ($placeholders)
                            AND mois >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
                            GROUP BY DATE_FORMAT(mois, '%Y-%m')
                            ORDER BY mois");
    $stmt->execute($logement_ids);
    $revenus_mensuels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prochaines réservations
    $stmt = $conn->prepare("SELECT r.*, l.titre as logement_titre
                            FROM FC_reservations r
                            JOIN FC_logements l ON r.logement_id = l.id
                            WHERE r.logement_id IN ($placeholders)
                            AND r.date_debut >= CURRENT_DATE
                            AND r.statut = 'confirmee'
                            ORDER BY r.date_debut
                            LIMIT 5");
    $stmt->execute($logement_ids);
    $prochaines_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$security->trackVisit('/proprietaire/dashboard');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Propriétaire - <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bleu-frenchy: #1E3A8A;
            --bleu-clair: #3B82F6;
            --rouge-frenchy: #EF4444;
            --gris-clair: #F3F4F6;
            --gris-fonce: #1F2937;
            --vert: #10B981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gris-clair);
            min-height: 100vh;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, var(--bleu-frenchy), #1e40af);
            color: white;
            padding: 1.5rem;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            text-align: center;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 1.5rem;
        }

        .sidebar-header img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: white;
            padding: 5px;
            margin-bottom: 0.5rem;
        }

        .sidebar-header h2 {
            font-size: 1.1rem;
        }

        .sidebar-header p {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 0.8rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.3rem;
            transition: all 0.2s;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255,255,255,0.15);
            color: white;
        }

        .sidebar-nav .icon {
            width: 20px;
            text-align: center;
        }

        /* Main content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: var(--gris-fonce);
            font-size: 1.8rem;
        }

        .date-info {
            color: #6B7280;
        }

        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.blue { background: rgba(59, 130, 246, 0.1); }
        .stat-icon.green { background: rgba(16, 185, 129, 0.1); }
        .stat-icon.orange { background: rgba(245, 158, 11, 0.1); }
        .stat-icon.purple { background: rgba(139, 92, 246, 0.1); }

        .stat-content h3 {
            font-size: 1.8rem;
            color: var(--gris-fonce);
            margin-bottom: 0.2rem;
        }

        .stat-content p {
            color: #6B7280;
            font-size: 0.9rem;
        }

        /* Content grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 1.5rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .card-header h2 {
            font-size: 1.2rem;
            color: var(--gris-fonce);
        }

        .card-header a {
            color: var(--bleu-clair);
            text-decoration: none;
            font-size: 0.9rem;
        }

        /* Chart */
        .chart-container {
            height: 300px;
        }

        /* Reservations list */
        .reservation-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--gris-clair);
        }

        .reservation-item:last-child {
            border-bottom: none;
        }

        .reservation-info h4 {
            color: var(--gris-fonce);
            margin-bottom: 0.3rem;
        }

        .reservation-info p {
            color: #6B7280;
            font-size: 0.85rem;
        }

        .reservation-dates {
            text-align: right;
        }

        .reservation-dates .date {
            font-weight: 600;
            color: var(--bleu-frenchy);
        }

        .reservation-dates .nights {
            font-size: 0.85rem;
            color: #6B7280;
        }

        /* Logements list */
        .logement-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--gris-clair);
        }

        .logement-item:last-child {
            border-bottom: none;
        }

        .logement-thumb {
            width: 60px;
            height: 45px;
            border-radius: 6px;
            object-fit: cover;
            background: var(--gris-clair);
        }

        .logement-info h4 {
            color: var(--gris-fonce);
            font-size: 0.95rem;
        }

        .logement-info p {
            color: #6B7280;
            font-size: 0.85rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main-content {
                margin-left: 0;
            }

            .dashboard-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="../frenchyconciergerie.png.png" alt="Logo">
                <h2><?= e($proprietaire['prenom'] ?? '') ?> <?= e($proprietaire['nom']) ?></h2>
                <p>Propriétaire</p>
            </div>

            <nav class="sidebar-nav">
                <a href="index.php" class="active">
                    <span class="icon">📊</span> Tableau de bord
                </a>
                <a href="logements.php">
                    <span class="icon">🏠</span> Mes logements
                </a>
                <a href="reservations.php">
                    <span class="icon">📅</span> Réservations
                </a>
                <a href="revenus.php">
                    <span class="icon">💰</span> Revenus
                </a>
                <a href="documents.php">
                    <span class="icon">📄</span> Documents
                </a>
                <a href="profil.php">
                    <span class="icon">👤</span> Mon profil
                </a>
                <a href="logout.php">
                    <span class="icon">🚪</span> Déconnexion
                </a>
            </nav>
        </aside>

        <!-- Main content -->
        <main class="main-content">
            <div class="page-header">
                <h1>Bonjour, <?= e($proprietaire['prenom'] ?? $proprietaire['nom']) ?> !</h1>
                <span class="date-info"><?= strftime('%A %d %B %Y') ?></span>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">🏠</div>
                    <div class="stat-content">
                        <h3><?= $stats['total_logements'] ?></h3>
                        <p>Logements gérés</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">💰</div>
                    <div class="stat-content">
                        <h3><?= number_format($stats['revenu_mois'], 0, ',', ' ') ?> €</h3>
                        <p>Revenus ce mois</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange">📅</div>
                    <div class="stat-content">
                        <h3><?= $stats['reservations_mois'] ?></h3>
                        <p>Réservations ce mois</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple">📈</div>
                    <div class="stat-content">
                        <h3><?= $stats['taux_occupation'] ?>%</h3>
                        <p>Taux d'occupation</p>
                    </div>
                </div>
            </div>

            <!-- Content grid -->
            <div class="content-grid">
                <!-- Graphique revenus -->
                <div class="card">
                    <div class="card-header">
                        <h2>Évolution des revenus</h2>
                        <a href="revenus.php">Voir détails →</a>
                    </div>
                    <div class="chart-container">
                        <canvas id="revenusChart"></canvas>
                    </div>
                </div>

                <!-- Prochaines réservations -->
                <div class="card">
                    <div class="card-header">
                        <h2>Prochaines réservations</h2>
                        <a href="reservations.php">Voir tout →</a>
                    </div>

                    <?php if (empty($prochaines_reservations ?? [])): ?>
                    <p style="color: #6B7280; text-align: center; padding: 2rem;">Aucune réservation à venir</p>
                    <?php else: ?>
                    <?php foreach ($prochaines_reservations as $resa): ?>
                    <div class="reservation-item">
                        <div class="reservation-info">
                            <h4><?= e($resa['logement_titre']) ?></h4>
                            <p><?= e($resa['nom_voyageur'] ?? 'Voyageur') ?> • <?= $resa['nb_voyageurs'] ?> pers.</p>
                        </div>
                        <div class="reservation-dates">
                            <div class="date"><?= date('d/m', strtotime($resa['date_debut'])) ?></div>
                            <div class="nights"><?= (strtotime($resa['date_fin']) - strtotime($resa['date_debut'])) / 86400 ?> nuits</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mes logements -->
            <div class="card" style="margin-top: 1.5rem;">
                <div class="card-header">
                    <h2>Mes logements</h2>
                    <a href="logements.php">Gérer →</a>
                </div>

                <?php if (empty($logements)): ?>
                <p style="color: #6B7280; text-align: center; padding: 2rem;">Aucun logement associé à votre compte</p>
                <?php else: ?>
                <?php foreach ($logements as $logement): ?>
                <div class="logement-item">
                    <img src="../<?= e($logement['image']) ?>" alt="<?= e($logement['titre']) ?>" class="logement-thumb" onerror="this.src='../assets/img/placeholder.jpg'">
                    <div class="logement-info">
                        <h4><?= e($logement['titre']) ?></h4>
                        <p><?= e($logement['localisation']) ?> • <?= e($logement['type_bien']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Graphique des revenus
        const ctx = document.getElementById('revenusChart').getContext('2d');

        <?php
        $labels = [];
        $data = [];
        $mois_fr = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];

        if (!empty($revenus_mensuels)) {
            foreach ($revenus_mensuels as $rm) {
                $m = (int)substr($rm['mois'], 5, 2);
                $labels[] = $mois_fr[$m - 1];
                $data[] = $rm['revenu'];
            }
        } else {
            $labels = $mois_fr;
            $data = array_fill(0, 12, 0);
        }
        ?>

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [{
                    label: 'Revenus (€)',
                    data: <?= json_encode($data) ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + ' €';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
