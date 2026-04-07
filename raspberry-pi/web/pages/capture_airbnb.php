<?php
/**
 * Page de capture des donnees Airbnb
 * Recoit les donnees du bookmarklet et permet de les confirmer/modifier
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header_minimal.php';

$message = '';
$messageType = '';

// Debug: verifier que PDO est disponible
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Erreur: Connexion base de donnees non disponible');
}

// Traitement du formulaire (verifie url au lieu de save car certains navigateurs n'envoient pas le bouton)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['url'])) {
    $message = "Traitement en cours... ";
    try {
        $airbnbId = null;
        if (!empty($_POST['url'])) {
            if (preg_match('/rooms\/(\d+)/', $_POST['url'], $matches)) {
                $airbnbId = $matches[1];
            }
        }

        // Verifier si existe deja
        $existingId = null;
        if ($airbnbId) {
            $stmt = $pdo->prepare("SELECT id FROM market_competitors WHERE airbnb_id = ?");
            $stmt->execute([$airbnbId]);
            $existingId = $stmt->fetchColumn();
        }

        // Preparer les noms de co-hotes en JSON
        $cohostNames = !empty($_POST['cohost_names']) ? json_encode(array_map('trim', explode(',', $_POST['cohost_names']))) : null;

        if ($existingId) {
            // Mise a jour
            $stmt = $pdo->prepare("
                UPDATE market_competitors SET
                    nom = ?, url = ?, ville = ?, type_logement = ?,
                    capacite = ?, chambres = ?, note_moyenne = ?, nb_avis = ?,
                    superhost = ?, cohotes = ?, host_name = ?, host_profile_id = ?,
                    cohost_names = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['nom'],
                $_POST['url'],
                $_POST['ville'],
                $_POST['type_logement'],
                $_POST['capacite'] ?: null,
                $_POST['chambres'] ?: null,
                $_POST['note_moyenne'] ?: null,
                $_POST['nb_avis'] ?: null,
                isset($_POST['superhost']) ? 1 : 0,
                $_POST['cohotes'] ?: 0,
                $_POST['host_name'] ?: null,
                $_POST['host_id'] ?: null,
                $cohostNames,
                $existingId
            ]);
            $competitorId = $existingId;
            $message = "Concurrent mis a jour !";
        } else {
            // Insertion
            $stmt = $pdo->prepare("
                INSERT INTO market_competitors
                (airbnb_id, nom, url, ville, type_logement, capacite, chambres, note_moyenne, nb_avis, superhost, cohotes, host_name, host_profile_id, cohost_names)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $airbnbId,
                $_POST['nom'],
                $_POST['url'],
                $_POST['ville'],
                $_POST['type_logement'],
                $_POST['capacite'] ?: null,
                $_POST['chambres'] ?: null,
                $_POST['note_moyenne'] ?: null,
                $_POST['nb_avis'] ?: null,
                isset($_POST['superhost']) ? 1 : 0,
                $_POST['cohotes'] ?: 0,
                $_POST['host_name'] ?: null,
                $_POST['host_id'] ?: null,
                $cohostNames
            ]);
            $competitorId = $pdo->lastInsertId();
            $message = "Nouveau concurrent ajoute !";
        }

        // Ajouter le prix si fourni
        if (!empty($_POST['prix_nuit']) && !empty($_POST['date_sejour'])) {
            $stmt = $pdo->prepare("
                INSERT INTO market_prices (competitor_id, date_sejour, prix_nuit, source)
                VALUES (?, ?, ?, 'bookmarklet')
                ON DUPLICATE KEY UPDATE prix_nuit = VALUES(prix_nuit)
            ");
            $stmt->execute([$competitorId, $_POST['date_sejour'], $_POST['prix_nuit']]);
            $message .= " Prix enregistre.";
        }

        $messageType = 'success';

    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Donnees recues du bookmarklet (GET) ou du formulaire (POST)
$data = [
    'url' => $_POST['url'] ?? $_GET['url'] ?? '',
    'nom' => $_POST['nom'] ?? $_GET['nom'] ?? '',
    'ville' => $_POST['ville'] ?? $_GET['ville'] ?? '',
    'capacite' => $_POST['capacite'] ?? $_GET['capacite'] ?? '',
    'chambres' => $_POST['chambres'] ?? $_GET['chambres'] ?? '',
    'note' => $_POST['note_moyenne'] ?? $_GET['note'] ?? '',
    'avis' => $_POST['nb_avis'] ?? $_GET['avis'] ?? '',
    'prix' => $_POST['prix_nuit'] ?? $_GET['prix'] ?? '',
    'superhost' => isset($_POST['superhost']) || (isset($_GET['superhost']) && $_GET['superhost'] == '1'),
    'type_logement' => $_POST['type_logement'] ?? $_GET['type'] ?? 'appartement',
    'cohotes' => $_POST['cohotes'] ?? $_GET['cohotes'] ?? '0',
    'host_name' => $_POST['host_name'] ?? $_GET['host_name'] ?? '',
    'host_id' => $_POST['host_id'] ?? $_GET['host_id'] ?? '',
    'cohost_names' => $_POST['cohost_names'] ?? $_GET['cohost_names'] ?? '',
];
?>

<div class="container" style="max-width: 600px; margin-top: 30px;">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-plus-circle"></i> Capturer une annonce Airbnb</h4>
        </div>
        <div class="card-body">

            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
                <br><a href="analyse_marche.php" class="alert-link">Retour a l'analyse</a>
            </div>
            <?php endif; ?>

            <form method="POST" action="capture_airbnb.php">
                <div class="form-group">
                    <label>URL Airbnb</label>
                    <input type="url" name="url" class="form-control" value="<?= htmlspecialchars($_POST['url'] ?? $data['url']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Nom de l'annonce</label>
                    <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($data['nom']) ?>" required>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Ville</label>
                            <input type="text" name="ville" class="form-control" value="<?= htmlspecialchars($data['ville']) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Type</label>
                            <select name="type_logement" class="form-control">
                                <option value="appartement" <?= $data['type_logement'] == 'appartement' ? 'selected' : '' ?>>Appartement</option>
                                <option value="maison" <?= $data['type_logement'] == 'maison' ? 'selected' : '' ?>>Maison</option>
                                <option value="studio" <?= $data['type_logement'] == 'studio' ? 'selected' : '' ?>>Studio</option>
                                <option value="chambre" <?= $data['type_logement'] == 'chambre' ? 'selected' : '' ?>>Chambre</option>
                                <option value="autre" <?= $data['type_logement'] == 'autre' ? 'selected' : '' ?>>Autre</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Capacite (voyageurs)</label>
                            <input type="number" name="capacite" class="form-control" value="<?= htmlspecialchars($data['capacite']) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Chambres</label>
                            <input type="number" name="chambres" class="form-control" value="<?= htmlspecialchars($data['chambres']) ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Note moyenne</label>
                            <input type="number" name="note_moyenne" class="form-control" step="0.01" min="0" max="5" value="<?= htmlspecialchars($data['note']) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Nombre d'avis</label>
                            <input type="number" name="nb_avis" class="form-control" value="<?= htmlspecialchars($data['avis']) ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="superhost" name="superhost" <?= $data['superhost'] ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="superhost">Superhost</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Co-hotes</label>
                            <input type="number" name="cohotes" class="form-control" min="0" value="<?= htmlspecialchars($data['cohotes']) ?>">
                        </div>
                    </div>
                </div>

                <hr>
                <h5><i class="fas fa-user"></i> Proprietaire</h5>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Nom du proprietaire</label>
                            <input type="text" name="host_name" class="form-control" value="<?= htmlspecialchars($data['host_name']) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>ID Airbnb</label>
                            <input type="text" name="host_id" class="form-control" value="<?= htmlspecialchars($data['host_id']) ?>" placeholder="Ex: 1470191666290255551">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Noms des co-hotes</label>
                    <input type="text" name="cohost_names" class="form-control" value="<?= htmlspecialchars($data['cohost_names']) ?>" placeholder="Separes par virgule (ex: Marie, Jean)">
                    <small class="form-text text-muted">Detectes automatiquement par le bookmarklet</small>
                </div>

                <hr>
                <h5>Prix observe (optionnel)</h5>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Date du sejour</label>
                            <input type="date" name="date_sejour" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Prix par nuit (EUR)</label>
                            <input type="number" name="prix_nuit" class="form-control" value="<?= htmlspecialchars($data['prix']) ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group mt-4">
                    <button type="submit" name="save" class="btn btn-primary btn-lg btn-block">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
