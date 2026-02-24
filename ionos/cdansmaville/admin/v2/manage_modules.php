<?php
session_start();
require_once '../db/connection.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// 📌 Récupérer la liste des clients
$clients = $conn->query("SELECT * FROM clients")->fetchAll(PDO::FETCH_ASSOC);

// 📌 Récupérer la liste des modules standards
$modules = $conn->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);

// 📌 Vérifier si un client est sélectionné
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

// 📌 Récupérer les modules activés pour le client sélectionné
$client_modules = [];
$client_modules_texts = [];
if ($client_id) {
    // Récupérer les modules classiques activés
    $stmt = $conn->prepare("SELECT module_id FROM client_modules WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $client_modules = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Récupérer les modules texte personnalisés
    $stmt = $conn->prepare("SELECT * FROM client_modules_texts WHERE client_id = ? ORDER BY ordre ASC");
    $stmt->execute([$client_id]);
    $client_modules_texts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 📌 Ajouter un module texte spécifique
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_text_module'])) {
    $nom_module = htmlspecialchars($_POST['nom_module']);

    // Insérer le module texte unique
    $stmt = $conn->prepare("INSERT INTO client_modules (client_id, module_id) VALUES (?, ?)");
    $stmt->execute([$client_id, 1]); // 1 = ID du module texte générique

    // Récupérer l'ID du module inséré
    $client_module_id = $conn->lastInsertId();

    // Insérer dans client_modules_texts avec un nom spécifique
    $stmt = $conn->prepare("INSERT INTO client_modules_texts (client_id, module_id, nom_module, content) VALUES (?, ?, ?, '')");
    $stmt->execute([$client_id, $client_module_id, $nom_module]);

    header("Location: manage_modules.php?client_id=" . $client_id);
    exit();
}

// 📌 Supprimer un module texte
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_text_module'])) {
    $text_module_id = intval($_POST['module_id']);

    // Supprimer de `client_modules_texts`
    $stmt = $conn->prepare("DELETE FROM client_modules_texts WHERE id = ?");
    $stmt->execute([$text_module_id]);

    header("Location: manage_modules.php?client_id=" . $client_id);
    exit();
}

// 📌 Activer/Désactiver un module standard
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['toggle_module'])) {
    $module_id = intval($_POST['module_id']);

    if (in_array($module_id, $client_modules)) {
        $stmt = $conn->prepare("DELETE FROM client_modules WHERE client_id = ? AND module_id = ?");
        $stmt->execute([$client_id, $module_id]);
    } else {
        $stmt = $conn->prepare("INSERT INTO client_modules (client_id, module_id) VALUES (?, ?)");
        $stmt->execute([$client_id, $module_id]);
    }

    header("Location: manage_modules.php?client_id=" . $client_id);
    exit();
}

// 📌 Mise à jour de l'ordre des modules texte
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['module_order'])) {
    $module_ids = explode(",", $_POST['module_order']);
    $order = 1;

    foreach ($module_ids as $module_id) {
        $stmt = $conn->prepare("UPDATE client_modules_texts SET ordre = ? WHERE id = ?");
        $stmt->execute([$order, intval($module_id)]);
        $order++;
    }

    header("Location: manage_modules.php?client_id=" . $client_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Modules</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
</head>
<body>
    <header>
        <h1>Gestion des Modules</h1>
        <a href="index.php">Retour au Tableau de Bord</a>
        <a href="logout.php">Déconnexion</a>
    </header>

    <main>
        <h2>Sélectionner un Client</h2>
        <form action="manage_modules.php" method="get">
            <label for="client_id">Client :</label>
            <select name="client_id" onchange="this.form.submit()">
                <option value="">-- Sélectionner --</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($client_id == $c['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['nom']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($client_id): ?>
            <h2>Activer/Désactiver des Modules</h2>
            <form action="manage_modules.php?client_id=<?php echo $client_id; ?>" method="post">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Activé</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modules as $module): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($module['nom']); ?></td>
                                <td>
                                    <button type="submit" name="toggle_module" value="<?php echo $module['id']; ?>">
                                        <?php echo in_array($module['id'], $client_modules) ? 'Désactiver' : 'Activer'; ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>

            <h2>Ajouter un Module Texte</h2>
            <form action="manage_modules.php?client_id=<?php echo $client_id; ?>" method="post">
                <label for="nom_module">Nom du Module Texte :</label>
                <input type="text" name="nom_module" required>
                <button type="submit" name="add_text_module">Ajouter</button>
            </form>
            <?php if (in_array(17, $client_modules)) : ?>
    <a href="manage_texts.php?client_id=<?php echo $client_id; ?>" class="btn">📝 Modifier les textes</a>
<?php endif; ?>
            <h2>Modules Texte du Client</h2>
            <ul id="sortable">
                <?php foreach ($client_modules_texts as $text_module): ?>
                    <li class="sortable-item" data-module-id="<?php echo $text_module['id']; ?>">
                        <?php echo htmlspecialchars($text_module['nom_module']); ?>
                        <form action="manage_modules.php?client_id=<?php echo $client_id; ?>" method="post" style="display:inline;">
                            <input type="hidden" name="module_id" value="<?php echo $text_module['id']; ?>">
                            <button type="submit" name="delete_text_module">Supprimer</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
            <form action="manage_modules.php?client_id=<?php echo $client_id; ?>" method="post">
                <input type="hidden" name="module_order" id="module_order_input">
                <button type="submit">Mettre à jour l'ordre</button>
            </form>
        <?php endif; ?>
    </main>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let sortableList = document.getElementById("sortable");
            let moduleOrderInput = document.getElementById("module_order_input");

            new Sortable(sortableList, {
                animation: 150,
                onEnd: function () {
                    let order = [];
                    document.querySelectorAll(".sortable-item").forEach((item) => {
                        order.push(item.getAttribute("data-module-id"));
                    });
                    moduleOrderInput.value = order.join(",");
                }
            });
        });
    </script>
</body>
</html>
