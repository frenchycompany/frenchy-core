<?php
// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclure la configuration et l'en-tête
require_once __DIR__ . '/../includes/rpi_db.php';
$pdo = getRpiPdo();

// 2) Déterminer l'action (create, edit, delete, list)
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// 3) Fonctions utilitaires

// Récupérer la liste des logements pour le <select>
function getListeLogements($pdo) {
    $stmt = $pdo->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer un scénario par ID
function getScenarioById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM ia_scenario WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Insérer un nouveau scénario
function createScenario($pdo, $logement_id, $nom, $regles, $message_modele) {
    $stmt = $pdo->prepare("
        INSERT INTO ia_scenario (logement_id, nom, regles, message_modele)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$logement_id, $nom, $regles, $message_modele]);
}

// Mettre à jour un scénario
function updateScenario($pdo, $id, $logement_id, $nom, $regles, $message_modele) {
    $stmt = $pdo->prepare("
        UPDATE ia_scenario
        SET logement_id = ?, nom = ?, regles = ?, message_modele = ?
        WHERE id = ?
    ");
    $stmt->execute([$logement_id, $nom, $regles, $message_modele, $id]);
}

// Supprimer un scénario
function deleteScenario($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM ia_scenario WHERE id = ?");
    $stmt->execute([$id]);
}

// 4) Routage en fonction de $action

if ($action == 'create_form') {
    // Formulaire de création
    $logements = getListeLogements($pdo);
    ?>
    <h1>Créer un nouveau scénario IA</h1>
    <form method="post" action="?action=create_submit">
        <label>Logement :</label>
        <select name="logement_id">
            <?php foreach ($logements as $l): ?>
                <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nom_du_logement']) ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <label>Nom du scénario :</label><br>
        <input type="text" name="nom" required><br><br>

        <label>Règles (regles) :</label><br>
        <textarea name="regles" rows="5" cols="50"></textarea><br><br>

        <label>Message modèle (message_modele) :</label><br>
        <textarea name="message_modele" rows="5" cols="50"></textarea><br><br>

        <input type="submit" value="Créer">
    </form>
    <p><a href="?action=list">Retour à la liste</a></p>
    <?php

} elseif ($action == 'create_submit') {
    // Traiter le formulaire de création
    $logement_id    = $_POST['logement_id']    ?? null;
    $nom            = $_POST['nom']            ?? '';
    $regles         = $_POST['regles']         ?? '';
    $message_modele = $_POST['message_modele'] ?? '';

    createScenario($pdo, $logement_id, $nom, $regles, $message_modele);
    echo "<p>Scénario créé avec succès.</p>";
    echo '<p><a href="?action=list">Retour à la liste</a></p>';

} elseif ($action == 'edit_form') {
    // Formulaire d'édition
    $id = $_GET['id'] ?? 0;
    $scenario = getScenarioById($pdo, $id);
    if (!$scenario) {
        echo "<p>Scénario introuvable.</p>";
        echo '<p><a href="?action=list">Retour à la liste</a></p>';
        exit;
    }
    $logements = getListeLogements($pdo);
    ?>
    <h1>Modifier le scénario IA</h1>
    <form method="post" action="?action=edit_submit">
        <input type="hidden" name="id" value="<?= $scenario['id'] ?>">

        <label>Logement :</label>
        <select name="logement_id">
            <?php foreach ($logements as $l): ?>
                <option value="<?= $l['id'] ?>"
                    <?php if ($l['id'] == $scenario['logement_id']) echo 'selected'; ?>>
                    <?= htmlspecialchars($l['nom_du_logement']) ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>

        <label>Nom du scénario :</label><br>
        <input type="text" name="nom" value="<?= htmlspecialchars($scenario['nom']) ?>" required><br><br>

        <label>Règles (regles) :</label><br>
        <textarea name="regles" rows="5" cols="50"><?= htmlspecialchars($scenario['regles']) ?></textarea><br><br>

        <label>Message modèle (message_modele) :</label><br>
        <textarea name="message_modele" rows="5" cols="50"><?= htmlspecialchars($scenario['message_modele']) ?></textarea><br><br>

        <input type="submit" value="Enregistrer">
    </form>
    <p><a href="?action=list">Retour à la liste</a></p>
    <?php

} elseif ($action == 'edit_submit') {
    // Traiter le formulaire d'édition
    $id             = $_POST['id']             ?? 0;
    $logement_id    = $_POST['logement_id']    ?? null;
    $nom            = $_POST['nom']            ?? '';
    $regles         = $_POST['regles']         ?? '';
    $message_modele = $_POST['message_modele'] ?? '';

    updateScenario($pdo, $id, $logement_id, $nom, $regles, $message_modele);
    echo "<p>Scénario mis à jour avec succès.</p>";
    echo '<p><a href="?action=list">Retour à la liste</a></p>';

} elseif ($action == 'delete') {
    // Supprimer un scénario
    $id = $_GET['id'] ?? 0;
    deleteScenario($pdo, $id);
    echo "<p>Scénario supprimé.</p>";
    echo '<p><a href="?action=list">Retour à la liste</a></p>';

} else {
    // action = list (afficher la liste)
    ?>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-gradient-primary">
                <i class="fas fa-project-diagram"></i> Scénarios IA
            </h1>
            <a href="?action=create_form" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nouveau scénario
            </a>
        </div>

        <?php
        // Récupérer la liste des scénarios + le nom du logement
        $sql = "
            SELECT s.id, s.nom, s.logement_id, l.nom_du_logement
            FROM ia_scenario s
            LEFT JOIN liste_logements l ON s.logement_id = l.id
            ORDER BY s.id DESC
        ";
        $stmt = $pdo->query($sql);
        $scenarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$scenarios) {
            echo "<div class='alert alert-info'><i class='fas fa-info-circle'></i> Aucun scénario trouvé.</div>";
        } else {
            ?>
            <div class="card shadow-custom">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> ID</th>
                                <th><i class="fas fa-home"></i> Logement</th>
                                <th><i class="fas fa-tag"></i> Nom</th>
                                <th class="text-center"><i class="fas fa-cog"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scenarios as $sc): ?>
                                <?php
                                $id            = $sc['id'];
                                $nom_scenario  = htmlspecialchars($sc['nom']);
                                $nom_logement  = $sc['nom_du_logement'] ? htmlspecialchars($sc['nom_du_logement']) : "(Inconnu)";
                                ?>
                                <tr>
                                    <td><?= $id ?></td>
                                    <td><span class="badge text-bg-info"><?= $nom_logement ?></span></td>
                                    <td><?= $nom_scenario ?></td>
                                    <td class="text-center">
                                        <a href='?action=edit_form&id=<?= $id ?>' class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i> Modifier
                                        </a>
                                        <a href='?action=delete&id=<?= $id ?>'
                                           class="btn btn-sm btn-outline-danger"
                                           onclick='return confirm("Supprimer ce scénario ?")'>
                                            <i class="fas fa-trash"></i> Supprimer
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php
        }
        ?>
    </div>
    <?php
}

?>
