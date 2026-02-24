<?php
require_once '../db/connection.php';

// Vérifier si un client est sélectionné via une URL unique (ex: ?client_id=2)
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

// Vérifier si le client existe
$client = null;
if ($client_id) {
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Si le client n'existe pas, afficher une erreur
if (!$client) {
    die("Client introuvable.");
}

// Récupérer les modules activés pour ce client et leur ordre
$modules = [];
$stmt = $conn->prepare("
    SELECT m.nom, m.fichier_php, cm.ordre
    FROM client_modules cm 
    JOIN modules m ON cm.module_id = m.id 
    WHERE cm.client_id = ?
    ORDER BY cm.ordre ASC
");
$stmt->execute([$client_id]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le contenu personnalisé du client
$client_content = [];
$stmt = $conn->prepare("SELECT * FROM client_content WHERE client_id = ?");
$stmt->execute([$client_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $client_content[$row['section']] = $row['content'];
}

// Récupérer le CSS personnalisé du client
$css_personnalise = $client['css_personnalise'] ?? "";

// Récupérer le code de la visite virtuelle
$visite_virtuelle_code = $client['visite_virtuelle_code'] ?? "";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($client['nom']); ?></title>
    <link rel="stylesheet" href="assets/css/landing.css">
    <style>
     
      
    :root {
        --primary-color: <?php echo $client['primary_color']; ?>;
        --secondary-color: <?php echo $client['secondary_color']; ?>;
        --background-color: <?php echo $client['background_color']; ?>;
    }

    body {
        background: var(--background-color);
    }

    header {
        background: var(--primary-color);
    }

    nav ul li a:hover {
        background: var(--secondary-color);
    }

    footer {
        background: var(--secondary-color);
    }
/* 📌 Style global responsive */
body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    background: #f9f9f9;
}

.container {
    max-width: 1200px;
    margin: auto;
    padding: 20px;
}

/* 📌 Bannière responsive */
.banner {
    position: relative;
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    overflow: hidden;
    background-color: #f0f0f0;
}

.banner img {
    width: 100%;
    height: auto;
    max-height: 80vh;
    object-fit: contain;
}

/* 📌 Bouton sur la bannière */
.banner-overlay {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.5);
    padding: 10px 20px;
    border-radius: 5px;
}

.banner-button {
    color: white;
    text-decoration: none;
    font-size: 18px;
    font-weight: bold;
    background: #007bff;
    padding: 10px 20px;
    border-radius: 5px;
    transition: background 0.3s;
}

.banner-button:hover {
    background: #0056b3;
}

/* 📌 Modules responsives */
.module {
    background: white;
    margin: 10px 0;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

/* 📌 Formulaires responsives */
form {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

input, textarea, select, button {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 16px;
}

button {
    background: #007bff;
    color: white;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.3s;
}

button:hover {
    background: #0056b3;
}

/* 📌 Responsivité mobile */
@media (max-width: 768px) {
    .container {
        padding: 10px;
    }

    .banner img {
        max-height: 60vh;
    }

    .banner-button {
        font-size: 16px;
        padding: 8px 16px;
    }

    .module {
        padding: 15px;
    }
}

    </style>
</head>

<body>
<header style="background-image: url('<?php echo htmlspecialchars($client['header_image'] ?? '../assets/img/default-header.jpg'); ?>');">
    <div class="logo-container">
        <?php if (!empty($client['logo'])): ?>
            <img src="<?php echo htmlspecialchars($client['logo']); ?>" alt="Logo" class="logo">
        <?php endif; ?>
    </div>
    <h1><?php echo htmlspecialchars($client['nom']); ?></h1>
</header>

    <main>
        <?php 
        foreach ($modules as $module): 
            $module_file = '../modules/' . htmlspecialchars($module['fichier_php']);
            if (file_exists($module_file)) {
                echo "<section id='".htmlspecialchars($module['nom'])."'>";
                include $module_file;
                echo "</section>";
            } else {
                echo "<p>⚠️ Le module <strong>" . htmlspecialchars($module['nom']) . "</strong> est introuvable.</p>";
            }
        endforeach; 
        ?>
    </main>

    <footer>
        <p>© <?php echo date("Y"); ?> <?php echo htmlspecialchars($client['nom']); ?> - Tous droits réservés.</p>
    </footer>

    <script>
    function sendHeight() {
        const height = document.body.scrollHeight;
        parent.postMessage({ type: "setHeight", height: height }, "*");
    }

    window.onload = sendHeight;
    window.onresize = sendHeight;
</script>

</body>
