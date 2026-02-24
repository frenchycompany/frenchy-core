<?php
session_start();
require_once 'config.php';
require_once '../db/connection.php';

// 🔥 Activer la gestion des erreurs et logs
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

try {
    // 📌 Récupérer tous les clients
    $clients = $conn->query("SELECT * FROM clients")->fetchAll(PDO::FETCH_ASSOC);

    // 📌 Récupérer les types de commerce
    $types_commerce = $conn->query("SELECT * FROM types_commerce")->fetchAll(PDO::FETCH_ASSOC);

    // 📌 Vérifier si un client est sélectionné
    $client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $client = null;
    if ($client_id) {
        $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$client_id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$client) {
            die("❌ Erreur : Client introuvable.");
        }
    }

    // 📌 Ajout ou modification d'un client
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_client'])) {
        // Vérification de l'ID du client
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    
        $nom = $_POST['nom'] ?? '';
        $contact_nom = $_POST['contact_nom'] ?? '';
        $contact_tel = $_POST['contact_tel'] ?? '';
        $contact_email = $_POST['contact_email'] ?? '';
        $adresse = $_POST['adresse'] ?? '';
        $siret = $_POST['siret'] ?? '';
        $type_commerce = $_POST['type_commerce'] ?? '';
        $matterport = $_POST['matterport'] ?? '';
        $gtag = $_POST['gtag'] ?? '';
        $gmb = $_POST['gmb'] ?? '';
        $agenda = $_POST['agenda'] ?? '';
    
        // ✅ Ajout des nouveaux champs pour les partenaires
        $is_partner = isset($_POST['is_partner']) ? 1 : 0;
        $offre_speciale = $_POST['offre_speciale'] ?? '';
    
        if ($client_id > 0) {
            // 🔄 Mise à jour du client existant
            $stmt = $conn->prepare("UPDATE clients SET nom=?, contact_nom=?, contact_tel=?, contact_email=?, adresse=?, siret=?, type_commerce=?, matterport=?, gtag=?, gmb=?, agenda=?, is_partner=?, offre_speciale=? WHERE id=?");
            $stmt->execute([$nom, $contact_nom, $contact_tel, $contact_email, $adresse, $siret, $type_commerce, $matterport, $gtag, $gmb, $agenda, $is_partner, $offre_speciale, $client_id]);
        } else {
            // ➕ Ajout d'un nouveau client
            $stmt = $conn->prepare("INSERT INTO clients (nom, contact_nom, contact_tel, contact_email, adresse, siret, type_commerce, matterport, gtag, gmb, agenda, is_partner, offre_speciale) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nom, $contact_nom, $contact_tel, $contact_email, $adresse, $siret, $type_commerce, $matterport, $gtag, $gmb, $agenda, $is_partner, $offre_speciale]);
    
            // Récupération de l'ID du nouveau client créé
            $client_id = $conn->lastInsertId();
        }
    
        header("Location: clients.php?id=" . $client_id);
        exit();
    }
    

    // 🗑️ Suppression d'un client
    if (isset($_GET['delete'])) {
        $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        header("Location: clients.php");
        exit();
    }
} catch (Exception $e) {
    error_log("🔥 ERREUR : " . $e->getMessage());
    die("❌ Une erreur est survenue. Consultez le fichier error_log.txt.");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Clients</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <h1>Gestion des Clients</h1>
        <a href="index.php">Retour au Tableau de Bord</a>
    </header>
    <main>
        <h2>Liste des Clients</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Contact</th>
                    <th>Type</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['nom']); ?></td>
                        <td><?php echo htmlspecialchars($c['contact_nom']) . " - " . htmlspecialchars($c['contact_tel']); ?></td>
                        <td><?php echo htmlspecialchars($c['type_commerce']); ?></td>
                        <td>
                            <a href="clients.php?id=<?php echo $c['id']; ?>">Modifier</a>
                            <a href="clients.php?delete=<?php echo $c['id']; ?>" onclick="return confirm('Supprimer ce client ?');">Supprimer</a>
                            <a href="preview_landing.php?client_id=<?php echo $c['id']; ?>">Personnaliser</a>
                            <a href="manage_texts.php?client_id=<?php echo $c['id']; ?>">📝 Gérer les textes</a>

                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2><?php echo $client_id ? "Modifier le client" : "Ajouter un client"; ?></h2>
        <form action="clients.php" method="post">
    <!-- Champ caché pour l'ID du client -->
    <input type="hidden" name="client_id" value="<?php echo $client['id'] ?? ''; ?>">

    <label>Nom :</label>
    <input type="text" name="nom" value="<?php echo htmlspecialchars($client['nom'] ?? ''); ?>" required>

    <label>Nom Contact :</label>
    <input type="text" name="contact_nom" value="<?php echo htmlspecialchars($client['contact_nom'] ?? ''); ?>">

    <label>Téléphone :</label>
    <input type="text" name="contact_tel" value="<?php echo htmlspecialchars($client['contact_tel'] ?? ''); ?>">

    <label>Email :</label>  
    <input type="email" name="contact_email" value="<?php echo htmlspecialchars($client['contact_email'] ?? ''); ?>">

    <label>Adresse :</label>
    <input type="text" name="adresse" value="<?php echo htmlspecialchars($client['adresse'] ?? ''); ?>">

    <label>SIRET :</label>
    <input type="text" name="siret" value="<?php echo htmlspecialchars($client['siret'] ?? ''); ?>">

    <label>Type de Commerce :</label>
    <select name="type_commerce" required>
        <?php foreach ($types_commerce as $type): ?>
            <option value="<?php echo $type['id']; ?>" 
                <?php echo ($client['type_commerce'] ?? '') == $type['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($type['nom']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Code Matterport :</label>
    <input type="text" name="matterport" value="<?php echo htmlspecialchars($client['matterport'] ?? ''); ?>">

    <label>Code Google Analytics :</label>
    <input type="text" name="gtag" value="<?php echo htmlspecialchars($client['gtag'] ?? ''); ?>">

    <label>Code Google My Business :</label>
    <input type="text" name="gmb" value="<?php echo htmlspecialchars($client['gmb'] ?? ''); ?>">

    <label>Code Prise de Rendez-vous :</label>
    <input type="text" name="agenda" value="<?php echo htmlspecialchars($client['agenda'] ?? ''); ?>">

    <label>Partenaire FrenchyConciergerie :</label>
    <input type="checkbox" name="is_partner" value="1" 
        <?php echo (!empty($client['is_partner']) && $client['is_partner'] == 1) ? 'checked' : ''; ?>>
    
    <label>Offre Spéciale :</label>
    <textarea name="offre_speciale"><?php echo htmlspecialchars($client['offre_speciale'] ?? ''); ?></textarea>

    <button type="submit" name="save_client">💾 Enregistrer</button>
</form>

    </main>
</body>
</html>
