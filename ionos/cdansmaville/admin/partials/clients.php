<?php
session_start();
require_once '../db/connection.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

try {
    $clients = $conn->query("SELECT * FROM clients")->fetchAll(PDO::FETCH_ASSOC);
    $types_commerce = $conn->query("SELECT * FROM types_commerce")->fetchAll(PDO::FETCH_ASSOC);

    $client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $client = null;
    if ($client_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$client_id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$client) {
            echo "❌ Erreur : Client introuvable.";
            return;
        }
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_client'])) {
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;

        $nom            = trim($_POST['nom'] ?? '');
        $contact_nom    = trim($_POST['contact_nom'] ?? '');
        $contact_tel    = trim($_POST['contact_tel'] ?? '');
        $contact_email  = trim($_POST['contact_email'] ?? '');
        $adresse        = trim($_POST['adresse'] ?? '');
        $siret          = trim($_POST['siret'] ?? '');
        $type_commerce  = trim($_POST['type_commerce'] ?? '');
        $matterport     = trim($_POST['matterport'] ?? '');
        $gtag           = trim($_POST['gtag'] ?? '');
        $gmb            = trim($_POST['gmb'] ?? '');
        $agenda         = trim($_POST['agenda'] ?? '');
        $is_partner     = isset($_POST['is_partner']) ? 1 : 0;
        $offre_speciale = trim($_POST['offre_speciale'] ?? '');
        $airbnb_embed   = trim($_POST['airbnb_embed'] ?? '');
        $ics_url = trim($_POST['ics_url'] ?? '');

        if ($client_id > 0) {
            $stmt = $conn->prepare("UPDATE clients SET nom = ?, contact_nom = ?, contact_tel = ?, contact_email = ?, adresse = ?, siret = ?, type_commerce = ?, matterport = ?, gtag = ?, gmb = ?, agenda = ?, is_partner = ?, offre_speciale = ?, airbnb_embed = ?, ics_url = ? WHERE id = ?");
            $stmt->execute([
                $nom, $contact_nom, $contact_tel, $contact_email, $adresse, $siret,
                $type_commerce, $matterport, $gtag, $gmb, $agenda, $is_partner,
                $offre_speciale, $airbnb_embed, $ics_url, $client_id
            ]);
            
        } else {
            $stmt = $conn->prepare("INSERT INTO clients (nom, contact_nom, contact_tel, contact_email, adresse, siret, type_commerce, matterport, gtag, gmb, agenda, is_partner, offre_speciale, airbnb_embed, ics_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $nom, $contact_nom, $contact_tel, $contact_email, $adresse, $siret,
                $type_commerce, $matterport, $gtag, $gmb, $agenda, $is_partner,
                $offre_speciale, $airbnb_embed, $ics_url
            ]);
            
            $client_id = $conn->lastInsertId();
        }

        header("Location: ?section=clients&id=" . $client_id);
        exit();
    }

    if (isset($_GET['delete'])) {
        $delete_id = intval($_GET['delete']);
        $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->execute([$delete_id]);
        header("Location: ?section=clients");
        exit();
    }
} catch (Exception $e) {
    error_log("🔥 ERREUR : " . $e->getMessage());
    echo "❌ Une erreur est survenue. Consultez le fichier error_log.txt.";
    return;
}
?>

<div>
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
                    <td><?php echo htmlspecialchars($c['nom'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($c['contact_nom'], ENT_QUOTES, 'UTF-8') . " - " . htmlspecialchars($c['contact_tel'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($c['type_commerce'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <a href="?section=clients&id=<?php echo $c['id']; ?>">Modifier</a>
                        <a href="?section=clients&delete=<?php echo $c['id']; ?>" onclick="return confirm('Supprimer ce client ?');">Supprimer</a>
                        <a href="?section=preview&client_id=<?php echo $c['id']; ?>">Personnaliser</a>
                        <a href="?section=texts&client_id=<?php echo $c['id']; ?>">📝 Gérer les textes</a>
                        <a href="?section=modules&client_id=<?php echo $c['id']; ?>">📝 Gérer les modules</a>
                        <a href="?section=content&client_id=<?php echo $c['id']; ?>">📝 Logos & Images</a>
                        <a href="?section=banner&client_id=<?php echo $c['id']; ?>">📝 Banniere</a>
                        <?php if (!empty($c['airbnb_embed'])): ?>
                            <div style="margin-top: 10px;"><?php echo $c['airbnb_embed']; ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2><?php echo $client_id ? "Modifier le client" : "Ajouter un client"; ?></h2>
    <form method="post" action="?section=clients<?php echo $client_id ? "&id=" . $client_id : ''; ?>">
        <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($client['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <label>Nom :</label>
        <input type="text" name="nom" value="<?php echo htmlspecialchars($client['nom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>

        <label>Nom Contact :</label>
        <input type="text" name="contact_nom" value="<?php echo htmlspecialchars($client['contact_nom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <label>Téléphone :</label>
        <input type="text" name="contact_tel" value="<?php echo htmlspecialchars($client['contact_tel'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <label>Email :</label>
        <input type="email" name="contact_email" value="<?php echo htmlspecialchars($client['contact_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <label>Adresse :</label>
        <input type="text" name="adresse" value="<?php echo htmlspecialchars($client['adresse'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <label>SIRET :</label>
        <input type="text" name="siret" value="<?php echo htmlspecialchars($client['siret'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <label>Type de Commerce :</label>
        <select name="type_commerce" required>
            <?php foreach ($types_commerce as $type): ?>
                <option value="<?php echo $type['id']; ?>" <?php echo (isset($client['type_commerce']) && $client['type_commerce'] == $type['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($type['nom'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Code Matterport :</label>
        <input type="text" name="matterport" value="<?php echo htmlspecialchars($client['matterport'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <label>Code Google Analytics :</label>
        <input type="text" name="gtag" value="<?php echo htmlspecialchars($client['gtag'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <label>Code Google My Business :</label>
        <input type="text" name="gmb" value="<?php echo htmlspecialchars($client['gmb'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <label>Code Prise de Rendez-vous :</label>
        <input type="text" name="agenda" value="<?php echo htmlspecialchars($client['agenda'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <label>Partenaire FrenchyConciergerie :</label>
        <input type="checkbox" name="is_partner" value="1" <?php echo (!empty($client['is_partner']) && $client['is_partner'] == 1) ? 'checked' : ''; ?>>

        <label>Offre Spéciale :</label>
        <textarea name="offre_speciale"><?php echo htmlspecialchars($client['offre_speciale'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>

        <label>Code Airbnb Embed :</label>
        <textarea name="airbnb_embed" rows="5"><?php echo $client['airbnb_embed'] ?? ''; ?></textarea>

        <label>Lien ICS Superhote :</label>
        <input type="text" name="ics_url" value="<?php echo htmlspecialchars($client['ics_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">


        <button type="submit" name="save_client">💾 Enregistrer</button>
    </form>
</div>
