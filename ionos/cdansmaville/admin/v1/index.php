<?php
// Activer le rapport d'erreur pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Vérifier si une session est déjà active avant de l'initialiser
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclure les fichiers de configuration et de connexion
require_once 'config.php';
require_once '../db/connection.php';

try {
    // ✅ Récupérer toutes les statistiques en une seule requête
    $stmt = $conn->query("
        SELECT 
            (SELECT COUNT(*) FROM clients) AS totalClients, 
            (SELECT COUNT(*) FROM modules) AS totalModules, 
            (SELECT COUNT(*) FROM news) AS totalNews,
            (SELECT nom FROM clients ORDER BY id DESC LIMIT 1) AS lastClient,
            (SELECT nom FROM modules ORDER BY id DESC LIMIT 1) AS lastModule,
            (SELECT titre FROM news ORDER BY id DESC LIMIT 1) AS lastNews,
            (SELECT COUNT(*) FROM clients WHERE id NOT IN (SELECT DISTINCT client_id FROM client_content)) AS clientsWithoutContent
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Affectation des résultats
    $totalClients = $stats['totalClients'];
    $totalModules = $stats['totalModules'];
    $totalNews = $stats['totalNews'];
    $lastClient = $stats['lastClient'] ?? 'Aucun';
    $lastModule = $stats['lastModule'] ?? 'Aucun';
    $lastNews = $stats['lastNews'] ?? 'Aucune';
    $clientsWithoutContent = $stats['clientsWithoutContent'];
} catch (PDOException $e) {
    die("Erreur lors de la récupération des statistiques : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f9f9f9;
        }

        header {
            background: #007bff;
            color: white;
            padding: 15px;
            text-align: center;
        }

        nav {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 10px;
        }

        nav a {
            background: white;
            padding: 10px;
            border-radius: 5px;
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
            transition: 0.3s;
        }

        nav a:hover {
            background: #0056b3;
            color: white;
        }

        main {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
        }

        .stats, .latest-updates, .notifications {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .stat-box {
            flex: 1;
            background: #007bff;
            color: white;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
        }

        .notifications p {
            background: #f8d7da;
            padding: 10px;
            border-radius: 5px;
            color: #721c24;
            text-align: center;
            font-weight: bold;
        }

        .notifications p.success {
            background: #d4edda;
            color: #155724;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }

        input, select, button {
            padding: 10px;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        button {
            background: #007bff;
            color: white;
            cursor: pointer;
            font-weight: bold;
        }

        button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>

    <header>
        <h1>Tableau de bord</h1>
        <nav>
            <a href="clients.php">Gérer Clients</a>
            <a href="modules.php">Gérer Modules</a>
            <a href="types_commerce.php">Gérer Types de Commerce</a>
            <a href="assign_modules.php">Assignation Modules</a>
            <a href="manage_content.php">Gestion Logo & Images</a>
        </nav>
    </header>

    <main>
        <h2>Statistiques générales</h2>
        <div class="stats">
            <div class="stat-box">
                <h2>Total Clients</h2>
                <p><?php echo $totalClients; ?></p>
            </div>
            <div class="stat-box">
                <h2>Total Modules</h2>
                <p><?php echo $totalModules; ?></p>
            </div>
            <div class="stat-box">
                <h2>Total News</h2>
                <p><?php echo $totalNews; ?></p>
            </div>
        </div>

        <h2>Dernières mises à jour</h2>
        <div class="latest-updates">
            <p>🆕 Dernier client ajouté : <strong><?php echo $lastClient; ?></strong></p>
            <p>🛠️ Dernier module ajouté : <strong><?php echo $lastModule; ?></strong></p>
            <p>📰 Dernière news publiée : <strong><?php echo $lastNews; ?></strong></p>
        </div>

        <h2>Notifications</h2>
        <div class="notifications">
            <?php if ($clientsWithoutContent > 0): ?>
                <p>⚠️ <strong><?php echo $clientsWithoutContent; ?></strong> clients n'ont pas encore de contenu défini ! <a href="manage_content.php">Gérer maintenant</a></p>
            <?php else: ?>
                <p class="success">✅ Tous les clients ont du contenu défini.</p>
            <?php endif; ?>
        </div>

        <h2>Prévisualiser une landing page</h2>
        <form action="preview_landing.php" method="get">
            <label for="client_id">Sélectionner un client :</label>
            <select name="client_id" required>
                <?php
                $clients = $conn->query("SELECT id, nom FROM clients ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($clients as $client) {
                    echo "<option value=\"{$client['id']}\">{$client['nom']}</option>";
                }
                ?>
            </select>
            <button type="submit">Voir la Landing Page</button>
        </form>
    </main>

</body>
</html>
