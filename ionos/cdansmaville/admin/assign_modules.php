
<?php
session_start();
require_once 'config.php';
require_once '../db/connection.php';

// Récupérer tous les clients
$clients = $conn->query("SELECT * FROM clients")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer tous les modules
$modules = $conn->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);

// Assigner un module à un client
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_module'])) {
    $client_id = intval($_POST['client_id']);
    $module_id = intval($_POST['module_id']);
    
    $stmt = $conn->prepare("INSERT INTO client_modules (client_id, module_id) VALUES (?, ?)");
    $stmt->execute([$client_id, $module_id]);
    header("Location: assign_modules.php");
    exit();
}

// Désassigner un module d'un client
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_module'])) {
    $client_id = intval($_POST['client_id']);
    $module_id = intval($_POST['module_id']);
    
    $stmt = $conn->prepare("DELETE FROM client_modules WHERE client_id = ? AND module_id = ?");
    $stmt->execute([$client_id, $module_id]);
    header("Location: assign_modules.php");
    exit();
}

// Récupérer les assignations actuelles
$assigned_modules = $conn->query("SELECT cm.client_id, cm.module_id, c.nom as client_nom, m.nom as module_nom FROM client_modules cm INNER JOIN clients c ON cm.client_id = c.id INNER JOIN modules m ON cm.module_id = m.id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignation des Modules</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <h1>Assignation des Modules</h1>
        <a href="index.php">Retour au Tableau de Bord</a>
        <a href="logout.php">Déconnexion</a>
    </header>
    <main>
        <h2>Modules Assignés</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Module</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assigned_modules as $assigned): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($assigned['client_nom']); ?></td>
                        <td><?php echo htmlspecialchars($assigned['module_nom']); ?></td>
                        <td>
                            <form action="assign_modules.php" method="post" style="display:inline;">
                                <input type="hidden" name="client_id" value="<?php echo $assigned['client_id']; ?>">
                                <input type="hidden" name="module_id" value="<?php echo $assigned['module_id']; ?>">
                                <button type="submit" name="remove_module" class="btn btn-danger" onclick="return confirm('Voulez-vous vraiment désassigner ce module ?');">Désassigner</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <h2>Assigner un Module à un Client</h2>
        <form action="assign_modules.php" method="post">
            <label for="client_id">Sélectionner un Client :</label>
            <select name="client_id" required>
                <?php foreach ($clients as $client): ?>
                    <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['nom']); ?></option>
                <?php endforeach; ?>
            </select>
            <label for="module_id">Sélectionner un Module :</label>
            <select name="module_id" required>
                <?php foreach ($modules as $module): ?>
                    <option value="<?php echo $module['id']; ?>"><?php echo htmlspecialchars($module['nom']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="assign_module">Assigner</button>
        </form>
    </main>
</body>
</html>
