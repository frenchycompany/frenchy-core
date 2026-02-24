<?php
/**
 * Carnet de clients — Page unifiée
 * Gestion des fiches clients, historique séjours et SMS
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_bridge.php';

function phone_normalize(?string $raw): string {
    if (!$raw) return '';
    $p = preg_replace('/[()\.\s-]+/', '', $raw);
    if (strpos($p, '00') === 0) $p = '+' . substr($p, 2);
    if (strlen($p) === 10 && $p[0] === '0') return '+33' . substr($p, 1);
    if (strlen($p) === 11 && substr($p, 0, 2) === '33') return '+' . $p;
    if (substr($p, 0, 1) === '+') return $p;
    return $p;
}

$feedback = '';

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    if (isset($_POST['create_client'])) {
        $telephone = phone_normalize(trim($_POST['telephone'] ?? ''));
        $prenom = trim($_POST['prenom'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $tags = trim($_POST['tags'] ?? '');

        if (!empty($telephone)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO clients (telephone, prenom, nom, email, notes, tags)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE prenom = VALUES(prenom), nom = VALUES(nom),
                        email = VALUES(email), notes = VALUES(notes), tags = VALUES(tags)
                ");
                $stmt->execute([$telephone, $prenom, $nom, $email, $notes, $tags]);
                $feedback = '<div class="alert alert-success">Client enregistré.</div>';
            } catch (PDOException $e) {
                $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }
}

// Recherche
$search = trim($_GET['q'] ?? '');

// Récupérer les clients
$clients = [];
try {
    if (!empty($search)) {
        $stmt = $pdo->prepare("
            SELECT c.*, COUNT(DISTINCT r.id) as nb_reservations,
                   MAX(r.date_depart) as dernier_sejour
            FROM clients c
            LEFT JOIN reservation r ON c.telephone = r.telephone
            WHERE c.telephone LIKE ? OR c.prenom LIKE ? OR c.nom LIKE ? OR c.email LIKE ?
            GROUP BY c.id
            ORDER BY c.updated_at DESC
        ");
        $like = "%$search%";
        $stmt->execute([$like, $like, $like, $like]);
    } else {
        $stmt = $pdo->query("
            SELECT c.*, COUNT(DISTINCT r.id) as nb_reservations,
                   MAX(r.date_depart) as dernier_sejour
            FROM clients c
            LEFT JOIN reservation r ON c.telephone = r.telephone
            GROUP BY c.id
            ORDER BY c.updated_at DESC
            LIMIT 100
        ");
    }
    $clients = $stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients — FrenchyConciergerie</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-address-book text-primary"></i> Carnet de clients</h2>
            <p class="text-muted"><?= count($clients) ?> client(s)</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addClientModal">
                <i class="fas fa-plus"></i> Nouveau client
            </button>
        </div>
    </div>

    <?= $feedback ?>

    <!-- Recherche -->
    <form method="GET" class="mb-4">
        <div class="input-group" style="max-width:400px;">
            <input type="text" name="q" class="form-control" placeholder="Rechercher (nom, tel, email...)" value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
        </div>
    </form>

    <!-- Tableau clients -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Téléphone</th><th>Prénom</th><th>Nom</th><th>Email</th><th>Séjours</th><th>Dernier séjour</th><th>Tags</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($clients as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['telephone']) ?></strong></td>
                            <td><?= htmlspecialchars($c['prenom'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['nom'] ?? '') ?></td>
                            <td><small><?= htmlspecialchars($c['email'] ?? '') ?></small></td>
                            <td><span class="badge bg-info"><?= $c['nb_reservations'] ?></span></td>
                            <td><small><?= $c['dernier_sejour'] ? date('d/m/Y', strtotime($c['dernier_sejour'])) : '—' ?></small></td>
                            <td>
                                <?php foreach (explode(',', $c['tags'] ?? '') as $tag): ?>
                                    <?php if (trim($tag)): ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars(trim($tag)) ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($clients)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucun client trouvé.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal ajout client -->
<div class="modal fade" id="addClientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Nouveau client</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echoCsrfField(); ?>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Téléphone *</label><input type="text" class="form-control" name="telephone" required placeholder="+33612345678"></div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Prénom</label><input type="text" class="form-control" name="prenom"></div>
                        <div class="col-6 mb-3"><label class="form-label">Nom</label><input type="text" class="form-control" name="nom"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email"></div>
                    <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
                    <div class="mb-3"><label class="form-label">Tags (séparés par virgules)</label><input type="text" class="form-control" name="tags" placeholder="vip, fidèle, problème"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="create_client" class="btn btn-success"><i class="fas fa-save"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
