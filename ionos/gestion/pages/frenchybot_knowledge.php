<?php
/**
 * FrenchyBot — Base de connaissances
 * Permet d'enseigner au bot quoi repondre aux voyageurs
 * Les entrees sont injectees dans le system prompt d'OpenAI
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';

// --- Actions POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_entry') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $logementId = !empty($_POST['logement_id']) ? (int)$_POST['logement_id'] : null;
        $active = isset($_POST['active']) ? 1 : 0;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($title && $content) {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE bot_knowledge SET title=?, content=?, logement_id=?, active=?, sort_order=? WHERE id=?");
                $stmt->execute([$title, $content, $logementId, $active, $sortOrder, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO bot_knowledge (title, content, logement_id, active, sort_order) VALUES (?,?,?,?,?)");
                $stmt->execute([$title, $content, $logementId, $active, $sortOrder]);
            }
            $_SESSION['flash'] = 'Entree sauvegardee.';
        }
    }

    if ($action === 'delete_entry') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM bot_knowledge WHERE id = ?")->execute([$id]);
            $_SESSION['flash'] = 'Entree supprimee.';
        }
    }

    if ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE bot_knowledge SET active = NOT active WHERE id = ?")->execute([$id]);
        }
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// --- Donnees ---
$entries = $pdo->query("
    SELECT bk.*, l.nom_du_logement
    FROM bot_knowledge bk
    LEFT JOIN liste_logements l ON bk.logement_id = l.id
    ORDER BY bk.logement_id IS NULL DESC, bk.sort_order, bk.title
")->fetchAll(PDO::FETCH_ASSOC);

$logements = $pdo->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);

// Stats conversations
try {
    $totalConv = $pdo->query("SELECT COUNT(*) FROM bot_conversations WHERE role = 'user'")->fetchColumn();
    $todayConv = $pdo->query("SELECT COUNT(*) FROM bot_conversations WHERE role = 'user' AND DATE(created_at) = CURDATE()")->fetchColumn();
} catch (\PDOException $e) {
    $totalConv = $todayConv = 0;
}
?>

<div class="container-fluid py-4">
    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-brain text-primary"></i> Base de connaissances</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editModal" onclick="editEntry(null)">
            <i class="fas fa-plus"></i> Nouvelle entree
        </button>
    </div>

    <!-- Config OpenAI -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <?php if (env('OPENAI_API_KEY', '')): ?>
                        <div class="fs-4 text-success"><i class="fas fa-check-circle"></i></div>
                        <div class="small text-muted">OpenAI configure</div>
                    <?php else: ?>
                        <div class="fs-4 text-warning"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="small text-muted">OpenAI non configure</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold text-primary"><?= $totalConv ?></div>
                    <div class="small text-muted">Questions posees (total)</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold text-info"><?= $todayConv ?></div>
                    <div class="small text-muted">Questions aujourd'hui</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Explications -->
    <div class="alert alert-info mb-4">
        <h6><i class="fas fa-lightbulb"></i> Comment ca marche ?</h6>
        <p class="mb-1">Le bot utilise les informations ci-dessous + les donnees du logement (wifi, codes, horaires...) pour repondre aux questions des voyageurs.</p>
        <p class="mb-1"><strong>Entrees globales</strong> = s'appliquent a tous les logements (ex: regles de la conciergerie, politique d'annulation)</p>
        <p class="mb-0"><strong>Entrees par logement</strong> = specifiques a un logement (ex: "le parking est au sous-sol place B12")</p>
    </div>

    <?php if (!env('OPENAI_API_KEY', '')): ?>
    <div class="alert alert-warning">
        <i class="fas fa-key"></i> Pour activer le chatbot IA, ajoutez <code>OPENAI_API_KEY=sk-...</code> dans votre fichier .env.
        <br>Sans cle API, les questions des voyageurs sont transmises directement a l'equipe par SMS/WhatsApp.
    </div>
    <?php endif; ?>

    <!-- Entrees globales -->
    <h5 class="mb-3"><i class="fas fa-globe text-primary"></i> Entrees globales (tous les logements)</h5>
    <div class="row g-3 mb-4">
        <?php
        $globals = array_filter($entries, fn($e) => $e['logement_id'] === null);
        foreach ($globals as $entry):
        ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100 <?= $entry['active'] ? '' : 'opacity-50' ?>">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><?= htmlspecialchars($entry['title']) ?></strong>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                        <button type="submit" class="btn btn-sm <?= $entry['active'] ? 'btn-success' : 'btn-secondary' ?>">
                            <?= $entry['active'] ? 'ON' : 'OFF' ?>
                        </button>
                    </form>
                </div>
                <div class="card-body">
                    <div class="bg-light rounded p-2 small" style="white-space:pre-line; max-height:150px; overflow-y:auto;">
                        <?= htmlspecialchars($entry['content']) ?>
                    </div>
                </div>
                <div class="card-footer border-0 bg-transparent">
                    <button class="btn btn-sm btn-outline-primary" onclick='editEntry(<?= json_encode($entry) ?>)' data-bs-toggle="modal" data-bs-target="#editModal">
                        <i class="fas fa-edit"></i> Modifier
                    </button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cette entree ?')">
                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                        <input type="hidden" name="action" value="delete_entry">
                        <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($globals)): ?>
            <div class="col-12 text-center text-muted py-3">
                <p>Aucune entree globale. <a href="#" onclick="editEntry(null)" data-bs-toggle="modal" data-bs-target="#editModal">Ajouter la premiere</a></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Entrees par logement -->
    <h5 class="mb-3"><i class="fas fa-home text-primary"></i> Entrees par logement</h5>
    <div class="row g-3">
        <?php
        $perLogement = array_filter($entries, fn($e) => $e['logement_id'] !== null);
        foreach ($perLogement as $entry):
        ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100 <?= $entry['active'] ? '' : 'opacity-50' ?>">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars($entry['title']) ?></strong>
                        <br><small class="text-muted"><i class="fas fa-home"></i> <?= htmlspecialchars($entry['nom_du_logement'] ?? '?') ?></small>
                    </div>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                        <button type="submit" class="btn btn-sm <?= $entry['active'] ? 'btn-success' : 'btn-secondary' ?>">
                            <?= $entry['active'] ? 'ON' : 'OFF' ?>
                        </button>
                    </form>
                </div>
                <div class="card-body">
                    <div class="bg-light rounded p-2 small" style="white-space:pre-line; max-height:150px; overflow-y:auto;">
                        <?= htmlspecialchars($entry['content']) ?>
                    </div>
                </div>
                <div class="card-footer border-0 bg-transparent">
                    <button class="btn btn-sm btn-outline-primary" onclick='editEntry(<?= json_encode($entry) ?>)' data-bs-toggle="modal" data-bs-target="#editModal">
                        <i class="fas fa-edit"></i>
                    </button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?')">
                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                        <input type="hidden" name="action" value="delete_entry">
                        <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($perLogement)): ?>
            <div class="col-12 text-center text-muted py-3">
                <p>Aucune entree specifique a un logement.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Suggestions -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header"><h6 class="mb-0"><i class="fas fa-lightbulb text-warning"></i> Idees d'entrees a ajouter</h6></div>
        <div class="card-body">
            <div class="row g-2">
                <?php
                $suggestions = [
                    ['Regles de la maison', 'Pas de fête, pas de bruit après 22h, pas de fumée à l\'intérieur...'],
                    ['Politique d\'annulation', 'Annulation gratuite jusqu\'à 7 jours avant l\'arrivée...'],
                    ['Poubelles / tri', 'Bac jaune = recyclage, bac gris = ordures ménagères. Sortir les poubelles le mardi soir.'],
                    ['Parking', 'Le parking est situé au sous-sol. Prendre la rampe à droite de l\'entrée.'],
                    ['Transports', 'Bus n°12 à 200m (arrêt Mairie). Tram ligne A à 5 min à pied.'],
                    ['Restaurants recommandes', 'Le Petit Bistrot (2 min) — cuisine française. Pizza Roma (5 min) — italien.'],
                ];
                foreach ($suggestions as $s):
                ?>
                <div class="col-md-6">
                    <button class="btn btn-sm btn-outline-secondary w-100 text-start" onclick="prefillEntry('<?= htmlspecialchars($s[0], ENT_QUOTES) ?>', '<?= htmlspecialchars($s[1], ENT_QUOTES) ?>')" data-bs-toggle="modal" data-bs-target="#editModal">
                        <i class="fas fa-plus-circle text-primary"></i> <?= htmlspecialchars($s[0]) ?>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edition -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                <input type="hidden" name="action" value="save_entry">
                <input type="hidden" name="id" id="edit_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalTitle">Nouvelle entree</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Titre</label>
                            <input type="text" name="title" id="edit_title" class="form-control" required placeholder="Ex: Regles de la maison">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Logement</label>
                            <select name="logement_id" id="edit_logement" class="form-select">
                                <option value="">Tous (global)</option>
                                <?php foreach ($logements as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nom_du_logement']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Contenu</label>
                            <textarea name="content" id="edit_content" class="form-control" rows="8" required
                                placeholder="Ecrivez ici les informations que le bot doit connaitre..."></textarea>
                            <div class="form-text">Ecrivez de maniere naturelle, comme si vous expliquiez a un collegue. Le bot utilisera ces informations pour repondre aux voyageurs.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ordre d'affichage</label>
                            <input type="number" name="sort_order" id="edit_sort" class="form-control" value="0">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="active" id="edit_active" class="form-check-input" checked>
                                <label class="form-check-label" for="edit_active">Actif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Sauvegarder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editEntry(entry) {
    if (entry) {
        document.getElementById('editModalTitle').textContent = 'Modifier l\'entree';
        document.getElementById('edit_id').value = entry.id;
        document.getElementById('edit_title').value = entry.title;
        document.getElementById('edit_content').value = entry.content;
        document.getElementById('edit_logement').value = entry.logement_id || '';
        document.getElementById('edit_sort').value = entry.sort_order || 0;
        document.getElementById('edit_active').checked = !!entry.active;
    } else {
        document.getElementById('editModalTitle').textContent = 'Nouvelle entree';
        document.getElementById('edit_id').value = 0;
        document.getElementById('edit_title').value = '';
        document.getElementById('edit_content').value = '';
        document.getElementById('edit_logement').value = '';
        document.getElementById('edit_sort').value = 0;
        document.getElementById('edit_active').checked = true;
    }
}

function prefillEntry(title, content) {
    editEntry(null);
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_content').value = content;
}
</script>

<?php include '../includes/footer.php'; ?>
