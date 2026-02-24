<?php
session_start();
require_once '../db/connection.php';

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$client = null;
if ($client_id) {
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$client) {
    die("Client introuvable.");
}

$stmt = $conn->prepare("SELECT m.nom, m.fichier_php, cm.ordre FROM client_modules cm JOIN modules m ON cm.module_id = m.id WHERE cm.client_id = ? ORDER BY cm.ordre ASC");
$stmt->execute([$client_id]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$client_content = [];
$stmt = $conn->prepare("SELECT * FROM client_content WHERE client_id = ?");
$stmt->execute([$client_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $client_content[$row['section']] = $row['content'];
}

$css_personnalise = $client['css_personnalise'] ?? "";
$visite_virtuelle_code = $client['visite_virtuelle_code'] ?? "";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($client['nom']); ?></title>
    <meta property="og:title" content="<?php echo htmlspecialchars($client['nom']); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($client['header_image'] ?? '../assets/img/default-header.jpg'); ?>">
    <link rel="icon" href="<?php echo htmlspecialchars($client['logo'] ?? '../assets/img/favicon.png'); ?>" type="image/png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <style>
        :root {
            --primary-color: <?php echo $client['primary_color']; ?>;
            --secondary-color: <?php echo $client['secondary_color']; ?>;
            --background-color: <?php echo $client['background_color']; ?>;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background: var(--background-color);
        }

        header {
            background: var(--primary-color);
            background-image: url('<?php echo htmlspecialchars($client['header_image'] ?? '../assets/img/default-header.jpg'); ?>');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 40px 20px;
            text-align: center;
        }

        header h1 {
            margin: 0;
            font-size: 36px;
        }

        .logo-container img {
            max-height: 80px;
            margin-bottom: 10px;
        }

        nav {
            position: sticky;
            top: 0;
            background: var(--primary-color);
            padding: 10px;
            text-align: center;
            z-index: 1000;
        }

        nav a {
            margin: 0 10px;
            color: white;
            font-weight: bold;
            text-decoration: none;
        }

        .module {
            background: white;
            margin: 20px auto;
            padding: 20px;
            max-width: 1000px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .module:hover {
            transform: scale(1.01);
        }

        footer {
            text-align: center;
            padding: 20px;
            background: var(--secondary-color);
            color: white;
        }

        .fixed-cta-mobile {
            display: none;
            position: fixed;
            bottom: 0;
            width: 100%;
            background: #28a745;
            color: white;
            text-align: center;
            padding: 14px;
            font-weight: bold;
            z-index: 9999;
        }

        .fixed-cta-mobile a {
            color: white;
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .fixed-cta-mobile {
                display: block;
            }
        }
    </style>
</head>

<body>
<header>
    <div class="logo-container">
        <?php if (!empty($client['logo'])): ?>
            <img src="<?php echo htmlspecialchars($client['logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Logo">
        <?php endif; ?>
    </div>
    <h1><?php echo htmlspecialchars($client['nom'], ENT_QUOTES, 'UTF-8'); ?></h1>
</header>

<nav>
    <?php foreach ($modules as $module): ?>
        <a href="#<?php echo htmlspecialchars($module['nom'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo ucfirst(htmlspecialchars($module['nom'], ENT_QUOTES, 'UTF-8')); ?>
        </a>
    <?php endforeach; ?>
</nav>

<main>
    <?php 
    foreach ($modules as $module): 
        $module_file = '../modules/' . htmlspecialchars($module['fichier_php'], ENT_QUOTES, 'UTF-8');
        if (file_exists($module_file)) {
            echo "<section id='" . htmlspecialchars($module['nom'], ENT_QUOTES, 'UTF-8') . "' class='module'>";
            echo "<h2 style='text-align:center; margin-bottom:20px;'>" . ucfirst(htmlspecialchars($module['nom'], ENT_QUOTES, 'UTF-8')) . "</h2>";
            include $module_file;
            echo "</section>";
        } else {
            echo "<p>⚠️ Le module <strong>" . htmlspecialchars($module['nom'], ENT_QUOTES, 'UTF-8') . "</strong> est introuvable.</p>";
        }
    endforeach; 
    ?>
</main>

<footer>
    <p>© <?php echo date("Y"); ?> <?php echo htmlspecialchars($client['nom'], ENT_QUOTES, 'UTF-8'); ?> - Tous droits réservés.</p>
</footer>

<div class="fixed-cta-mobile">
    📱 <a href='sms:0647554678?body=Bonjour%2C%20je%20souhaite%20r%C3%A9server%20ce%20logement.'>Réserver par SMS</a>
</div>

<script>
    function sendHeight() {
        const height = document.body.scrollHeight;
        parent.postMessage({ type: "setHeight", height: height }, "*");
    }
    window.onload = sendHeight;
    window.onresize = sendHeight;
</script>
</body>
</html>
