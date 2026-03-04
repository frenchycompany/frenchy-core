<?php
session_start();
include '../config.php'; // Inclut la configuration de la base de données
include '../pages/menu.php'; // Inclut le menu de navigation

// Vérifie si l'utilisateur est admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Auto-migration : colonne actif pour intervenant
try {
    $conn->exec("ALTER TABLE intervenant ADD COLUMN actif TINYINT(1) NOT NULL DEFAULT 1");
} catch (PDOException $e) { /* colonne existe déjà */ }

// Toggle actif/inactif
if (isset($_POST['toggle_actif'])) {
    $tid = (int)$_POST['toggle_actif'];
    $conn->prepare("UPDATE intervenant SET actif = NOT actif WHERE id = ?")->execute([$tid]);
    header("Location: intervenants.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['toggle_actif'])) {
    // Récupération des données du formulaire
    $intervenant_id = filter_input(INPUT_POST, 'intervenant_id', FILTER_VALIDATE_INT);
    $nom = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING);
    $numero = filter_input(INPUT_POST, 'numero', FILTER_SANITIZE_STRING);
    $role1 = filter_input(INPUT_POST, 'role1', FILTER_SANITIZE_STRING);
    $role2 = filter_input(INPUT_POST, 'role2', FILTER_SANITIZE_STRING);
    $role3 = filter_input(INPUT_POST, 'role3', FILTER_SANITIZE_STRING);
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
    $pages_accessibles = isset($_POST['pages_accessibles']) ? $_POST['pages_accessibles'] : [];

    try {
        if ($intervenant_id) {
            // Mise à jour d'un intervenant existant
            if (!empty($password)) {
                // Mot de passe fourni → hasher et mettre à jour
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE intervenant SET nom = ?, numero = ?, role1 = ?, role2 = ?, role3 = ?, nom_utilisateur = ?, mot_de_passe = ? WHERE id = ?");
                $stmt->execute([$nom, $numero, $role1, $role2, $role3, $username, $hashedPassword, $intervenant_id]);
            } else {
                // Pas de mot de passe → ne pas toucher au hash existant
                $stmt = $conn->prepare("UPDATE intervenant SET nom = ?, numero = ?, role1 = ?, role2 = ?, role3 = ?, nom_utilisateur = ? WHERE id = ?");
                $stmt->execute([$nom, $numero, $role1, $role2, $role3, $username, $intervenant_id]);
            }

            // Supprime les pages existantes pour cet intervenant
            $stmt = $conn->prepare("DELETE FROM intervenants_pages WHERE intervenant_id = ?");
            $stmt->execute([$intervenant_id]);

            // Ajoute les nouvelles pages accessibles
            if (!empty($pages_accessibles)) {
                $stmt = $conn->prepare("INSERT INTO intervenants_pages (intervenant_id, page_id) VALUES (?, ?)");
                foreach ($pages_accessibles as $page_id) {
                    $stmt->execute([$intervenant_id, (int)$page_id]);
                }
            }
        } else {
            // Création d'un nouvel intervenant (avec mot de passe hashé)
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO intervenant (nom, numero, role1, role2, role3, nom_utilisateur, mot_de_passe) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nom, $numero, $role1, $role2, $role3, $username, $hashedPassword]);
            $intervenant_id = $conn->lastInsertId();

            // Ajoute les pages accessibles
            if (!empty($pages_accessibles)) {
                $stmt = $conn->prepare("INSERT INTO intervenants_pages (intervenant_id, page_id) VALUES (?, ?)");
                foreach ($pages_accessibles as $page_id) {
                    $stmt->execute([$intervenant_id, (int)$page_id]);
                }
            }
        }
    } catch (PDOException $e) {
        die("Erreur lors de la mise à jour : " . $e->getMessage());
    }
}

// Récupération de tous les intervenants
$query = $conn->query("SELECT * FROM intervenant ORDER BY actif DESC, nom ASC");
$intervenants = $query->fetchAll(PDO::FETCH_ASSOC);

// Liste des rôles disponibles
$roles_disponibles = ['Conducteur', 'Femme de ménage', 'Laverie', 'Maintenance', 'Superviseur'];

// Récupération des pages disponibles
$stmt = $conn->query("SELECT id, chemin FROM pages");
$pages_disponibles = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Associe ID à chemin
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Intervenants</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="container mt-4">
    <h2>Gestion des Intervenants</h2>
    <table class="table table-striped">
        <thead>
        <tr>
            <th>Nom</th>
            <th>Numéro</th>
            <th>Nom d'utilisateur</th>
            <th>Mot de passe</th>
            <th>Rôle 1</th>
            <th>Rôle 2</th>
            <th>Rôle 3</th>
            <th>Pages Accessibles</th>
            <th>Statut</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($intervenants as $intervenant): ?>
            <?php
            // Récupère les pages accessibles pour cet intervenant
            $stmt = $conn->prepare("SELECT page_id FROM intervenants_pages WHERE intervenant_id = ?");
            $stmt->execute([$intervenant['id']]);
            $pages_utilisateur = $stmt->fetchAll(PDO::FETCH_COLUMN);
            ?>
            <tr>
                <form method="POST" action="">
                    <td>
                        <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($intervenant['nom']) ?>" required>
                        <input type="hidden" name="intervenant_id" value="<?= $intervenant['id'] ?>">
                    </td>
                    <td>
                        <input type="text" name="numero" class="form-control" value="<?= htmlspecialchars($intervenant['numero']) ?>" required>
                    </td>
                    <td>
                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($intervenant['nom_utilisateur']) ?>" required>
                    </td>
                    <td>
                        <input type="text" name="password" class="form-control" value="<?= htmlspecialchars($intervenant['mot_de_passe']) ?>" required>
                    </td>
                    <td>
                        <select name="role1" class="form-control">
                            <option value="">-- Sélectionnez --</option>
                            <?php foreach ($roles_disponibles as $role): ?>
                                <option value="<?= htmlspecialchars($role) ?>" <?= $intervenant['role1'] === $role ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($role) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select name="role2" class="form-control">
                            <option value="">-- Sélectionnez --</option>
                            <?php foreach ($roles_disponibles as $role): ?>
                                <option value="<?= htmlspecialchars($role) ?>" <?= $intervenant['role2'] === $role ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($role) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select name="role3" class="form-control">
                            <option value="">-- Sélectionnez --</option>
                            <?php foreach ($roles_disponibles as $role): ?>
                                <option value="<?= htmlspecialchars($role) ?>" <?= $intervenant['role3'] === $role ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($role) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <?php foreach ($pages_disponibles as $id => $chemin): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="pages_accessibles[]" value="<?= $id ?>" <?= in_array($id, $pages_utilisateur) ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= $chemin ?></label>
                            </div>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <?php if (!empty($intervenant['actif'])): ?>
                            <span class="badge badge-success text-bg-success">Actif</span>
                        <?php else: ?>
                            <span class="badge badge-secondary text-bg-secondary">Inactif</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="submit" class="btn btn-primary btn-sm">Modifier</button>
                </form>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="toggle_actif" value="<?= $intervenant['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= !empty($intervenant['actif']) ? 'btn-outline-warning' : 'btn-outline-success' ?>" onclick="return confirm('<?= !empty($intervenant['actif']) ? 'Désactiver' : 'Réactiver' ?> cet intervenant ?')">
                                <?= !empty($intervenant['actif']) ? 'Désactiver' : 'Activer' ?>
                            </button>
                        </form>
                    </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Créer un nouvel intervenant</h3>
    <form method="POST" action="" class="mb-4">
        <div class="form-group">
            <label for="nom">Nom :</label>
            <input type="text" name="nom" id="nom" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="numero">Numéro :</label>
            <input type="text" name="numero" id="numero" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="username">Nom d'utilisateur :</label>
            <input type="text" name="username" id="username" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="password">Mot de passe :</label>
            <input type="text" name="password" id="password" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="role1">Rôle 1 :</label>
            <select name="role1" id="role1" class="form-control">
                <option value="">-- Sélectionnez --</option>
                <?php foreach ($roles_disponibles as $role): ?>
                    <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="role2">Rôle 2 :</label>
            <select name="role2" id="role2" class="form-control">
                <option value="">-- Sélectionnez --</option>
                <?php foreach ($roles_disponibles as $role): ?>
                    <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="role3">Rôle 3 :</label>
            <select name="role3" id="role3" class="form-control">
                <option value="">-- Sélectionnez --</option>
                <?php foreach ($roles_disponibles as $role): ?>
                    <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Pages Accessibles :</label>
            <?php foreach ($pages_disponibles as $id => $chemin): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="pages_accessibles[]" value="<?= $id ?>">
                    <label class="form-check-label"><?= $chemin ?></label>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-success">Créer</button>
    </form>
</div>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
</body>
</html>
