<?php
/**
 * Creer un nouveau modele de contrat
 */
include '../config.php';
include '../pages/menu.php';

$feedback = '';

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';

    if (empty($title) || empty($content)) {
        $feedback = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Le titre et le contenu sont obligatoires</div>';
    } else {
        try {
            // Extraire les placeholders {{...}} du contenu
            preg_match_all('/\{\{(.*?)\}\}/', $content, $matches);
            $placeholders = implode(',', array_unique($matches[1]));

            $stmt = $conn->prepare("
                INSERT INTO contract_templates (title, content, placeholders, created_at, updated_at)
                VALUES (:title, :content, :placeholders, NOW(), NOW())
            ");
            $stmt->execute([
                ':title' => $title,
                ':content' => $content,
                ':placeholders' => $placeholders
            ]);

            header("Location: list_templates.php?saved=1");
            exit;
        } catch (PDOException $e) {
            $feedback = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Erreur: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Recuperer les champs disponibles
$fields = [];
try {
    $stmt = $conn->query("SELECT field_name, description FROM contract_fields ORDER BY field_name");
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-plus-circle text-success"></i> Nouveau modele de contrat</h2>
            <p class="text-muted">Creez un modele avec des placeholders {{nom_du_champ}} pour les champs dynamiques</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="list_templates.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <?= $feedback ?>

    <form method="POST">
        <?php echoCsrfField(); ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-edit"></i> Contenu du modele</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="title" class="form-label fw-bold">Titre du modele</label>
                            <input type="text" name="title" id="title" class="form-control form-control-lg"
                                   placeholder="Ex: Contrat de conciergerie standard" required
                                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label fw-bold">Contenu (HTML autorise)</label>
                            <textarea name="content" id="content" class="form-control font-monospace" rows="20"
                                      placeholder="Redigez votre contrat ici. Utilisez {{nom_champ}} pour les champs dynamiques." required><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
                            <small class="text-muted">Utilisez les placeholders du panneau de droite pour inserer des champs dynamiques</small>
                        </div>

                        <div class="text-end">
                            <a href="list_templates.php" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-save"></i> Creer le modele
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-start border-info border-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-code"></i> Placeholders disponibles</h5>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-3">Cliquez sur un placeholder pour l'inserer dans le contenu</p>

                        <?php if (!empty($fields)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($fields as $f): ?>
                                    <a href="#" class="list-group-item list-group-item-action shortcode-btn py-2"
                                       data-code="{{<?= htmlspecialchars($f['field_name']) ?>}}">
                                        <code class="text-primary">{{<?= htmlspecialchars($f['field_name']) ?>}}</code>
                                        <br><small class="text-muted"><?= htmlspecialchars($f['description']) ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-muted small">
                                <p>Aucun champ predefini. Vous pouvez utiliser n'importe quel placeholder au format <code>{{nom}}</code>.</p>
                                <p>Exemples courants :</p>
                                <ul>
                                    <li><code>{{nom_du_logement}}</code></li>
                                    <li><code>{{proprietaire_nom}}</code></li>
                                    <li><code>{{adresse}}</code></li>
                                    <li><code>{{date_contrat}}</code></li>
                                    <li><code>{{duree_contrat}}</code></li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.querySelectorAll('.shortcode-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const code = this.dataset.code;
        const textarea = document.getElementById('content');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        textarea.value = text.substring(0, start) + code + text.substring(end);
        textarea.selectionStart = textarea.selectionEnd = start + code.length;
        textarea.focus();
    });
});
</script>
