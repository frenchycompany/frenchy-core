<?php
include '../config.php'; // Inclut la configuration de la base de données
include '../pages/menu.php'; // Inclut le menu de navigation

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "ID du modèle non valide.";
    exit;
}

$template_id = (int)$_GET['id'];

try {
    // Récupérer le modèle de contrat
    $stmt = $conn->prepare("SELECT id, title, content FROM contract_templates WHERE id = :id");
    $stmt->execute([':id' => $template_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        echo "Modèle introuvable.";
        exit;
    }

    // Récupérer la liste des champs dynamiques disponibles depuis `contract_fields`
    $fieldsStmt = $conn->query("SELECT field_name, description FROM contract_fields");
    $fields = $fieldsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le Modèle de Contrat</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Optionnel : Intégrer un éditeur WYSIWYG comme CKEditor pour améliorer la saisie du contenu -->
</head>
<body>
<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h2>Modifier le Modèle de Contrat</h2>
        </div>
        <div class="card-body">
            <form action="save_template.php" method="POST">
                <input type="hidden" name="id" value="<?= $template['id'] ?>">
                <div class="form-group">
                    <label for="title">Titre du modèle :</label>
                    <input type="text" name="title" id="title" class="form-control" value="<?= htmlspecialchars($template['title']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="content">Contenu du modèle :</label>
                    <textarea name="content" id="content" rows="15" class="form-control" required><?= htmlspecialchars($template['content']) ?></textarea>
                </div>
                <button type="submit" class="btn btn-success mt-3">Enregistrer les modifications</button>
            </form>
        </div>
    </div>

    <div class="mt-5">
        <h3>Champs Dynamiques Disponibles (Shortcodes)</h3>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Shortcode</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fields as $field): ?>
                    <tr>
                        <td><code>{{<?= htmlspecialchars($field['field_name']) ?>}}</code></td>
                        <td><?= htmlspecialchars($field['description']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Inclusion de jQuery et Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Optionnel : Initialiser l'éditeur WYSIWYG si intégré -->
</body>
</html>
