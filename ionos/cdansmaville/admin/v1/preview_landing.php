<?php
session_start();
require_once 'config.php';
require_once '../db/connection.php';

// Vérifier si un client a été sélectionné
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
    die("❌ Client introuvable.");
}

// Récupérer les modules activés pour le client et leur ordre
$modules = [];
$stmt = $conn->prepare("
    SELECT m.id, m.nom, cm.ordre 
    FROM client_modules cm 
    JOIN modules m ON cm.module_id = m.id 
    WHERE cm.client_id = ? 
    ORDER BY cm.ordre ASC
");
$stmt->execute([$client_id]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le CSS personnalisé du client
$css_personnalise = $client['css_personnalise'] ?? "";

// Générer l'URL unique de la landing page
$landing_url = "https://frenchyconciergerie.fr/cdansmaville/admin/landing.php?client_id=" . $client_id;
$iframe_code = "<iframe src=\"$landing_url\" width=\"100%\" height=\"800\" frameborder=\"0\"></iframe>";

// 🎨 Mise à jour des couleurs et du CSS personnalisé
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_colors'])) {
    $primary_color = $_POST['primary_color'];
    $secondary_color = $_POST['secondary_color'];
    $background_color = $_POST['background_color'];
    $css_personnalise = $_POST['css_personnalise'];

    $stmt = $conn->prepare("UPDATE clients SET primary_color = ?, secondary_color = ?, background_color = ?, css_personnalise = ? WHERE id = ?");
    $stmt->execute([$primary_color, $secondary_color, $background_color, $css_personnalise, $client_id]);

    header("Location: preview_landing.php?client_id=" . $client_id);
    exit();
}

// 📌 Mise à jour de l'ordre des modules
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['module_order'])) {
    $module_ids = explode(",", $_POST['module_order']); // Convertir la chaîne en tableau
    $order = 1;

    foreach ($module_ids as $module_id) {
        if (!empty($module_id)) {
            $stmt = $conn->prepare("UPDATE client_modules SET ordre = ? WHERE client_id = ? AND module_id = ?");
            $stmt->execute([$order, $client_id, intval($module_id)]);
            $order++;
        }
    }

    header("Location: preview_landing.php?client_id=" . $client_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prévisualisation de la Landing Page</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
</head>
<body>
    <header>
        <h1>Prévisualisation de la Landing Page</h1>
        <nav>
            <a href="index.php">Retour au Tableau de Bord</a>
            <a href="clients.php">Gestion des Clients</a>
        </nav>
    </header>
    
    <main>
        <h2>Prévisualisation</h2>
        <iframe src="<?php echo $landing_url; ?>" width="100%" height="800" frameborder="0"></iframe>

        <h2>Code à intégrer</h2>
        <p>Copiez-collez ce code dans la page du site du client :</p>
        <textarea style="width:100%; height:80px;" readonly><?php echo htmlspecialchars($iframe_code); ?></textarea>

        <h2>Personnalisation des Couleurs et du CSS</h2>
        <form action="preview_landing.php?client_id=<?php echo $client_id; ?>" method="post">
            <label for="primary_color">Couleur Principale :</label>
            <input type="color" name="primary_color" value="<?php echo htmlspecialchars($client['primary_color']); ?>">

            <label for="secondary_color">Couleur Secondaire :</label>
            <input type="color" name="secondary_color" value="<?php echo htmlspecialchars($client['secondary_color']); ?>">

            <label for="background_color">Couleur de Fond :</label>
            <input type="color" name="background_color" value="<?php echo htmlspecialchars($client['background_color']); ?>">

            <label for="css_personnalise">CSS Personnalisé :</label>
            <textarea name="css_personnalise" rows="10" style="width:100%;"><?php echo htmlspecialchars($css_personnalise); ?></textarea>

            <button type="submit" name="update_colors">Enregistrer</button>
        </form>

        <h2>Modifier l'ordre des modules</h2>
        <form action="preview_landing.php?client_id=<?php echo $client_id; ?>" method="post">
            <ul id="sortable">
                <?php foreach ($modules as $module): ?>
                    <li class="sortable-item" data-module-id="<?php echo $module['id']; ?>">
                        <span class="drag-handle">☰</span>
                        <?php echo htmlspecialchars($module['nom']); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <input type="hidden" name="module_order" id="module_order_input">
            <button type="submit">Mettre à jour l'ordre</button>
        </form>
    </main>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let sortableList = document.getElementById("sortable");
            let moduleOrderInput = document.getElementById("module_order_input");

            new Sortable(sortableList, {
                animation: 150,
                handle: ".drag-handle",
                onEnd: function () {
                    let order = [];
                    document.querySelectorAll(".sortable-item").forEach((item) => {
                        order.push(item.getAttribute("data-module-id"));
                    });

                    // Mettre à jour l'input caché avec la nouvelle liste d'IDs
                    moduleOrderInput.value = order.join(",");
                }
            });
        });
    </script>
</body>
</html>
