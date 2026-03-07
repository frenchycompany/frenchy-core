<?php
/**
 * Carnet de clients - Gestion complete des voyageurs
 * - Creation de fiches clients
 * - Notes et preferences
 * - Historique des sejours et SMS
 * Adapte de la version RPI pour le VPS unifie
 */
include '../config.php';
require_once __DIR__ . '/../includes/rpi_bridge.php';

// Fonction utilitaire
if (!function_exists('e')) {
    function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
}

/** Normalisation FR simple -> E.164 */
if (!function_exists('phone_normalize_php')) {
    function phone_normalize_php(?string $raw): string {
        if (!$raw) return '';
        $p = preg_replace('/[()\.\s-]+/', '', $raw);
        if (strpos($p, '00') === 0) $p = '+' . substr($p, 2);
        if (strlen($p) === 10 && $p[0] === '0') return '+33' . substr($p, 1);
        if (strlen($p) === 11 && substr($p, 0, 2) === '33') return '+' . $p;
        if (substr($p, 0, 1) === '+') return $p;
        return $p;
    }
}

// --- Creation de la table clients si elle n'existe pas ---
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            telephone VARCHAR(20) NOT NULL UNIQUE,
            prenom VARCHAR(100),
            nom VARCHAR(100),
            email VARCHAR(255),
            adresse TEXT,
            notes TEXT,
            tags VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    // Table existe deja
}

$feedback = '';

// --- Traitement des actions POST (AVANT menu.php pour permettre les redirects) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    // Creer un nouveau client
    if (isset($_POST['create_client'])) {
        $telephone = phone_normalize_php(trim($_POST['telephone'] ?? ''));
        $prenom = trim($_POST['prenom'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $tags = trim($_POST['tags'] ?? '');

        if (empty($telephone)) {
            $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Le telephone est obligatoire</div>";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO clients (telephone, prenom, nom, email, adresse, notes, tags)
                    VALUES (:telephone, :prenom, :nom, :email, :adresse, :notes, :tags)
                ");
                $stmt->execute([
                    ':telephone' => $telephone,
                    ':prenom' => $prenom ?: null,
                    ':nom' => $nom ?: null,
                    ':email' => $email ?: null,
                    ':adresse' => $adresse ?: null,
                    ':notes' => $notes ?: null,
                    ':tags' => $tags ?: null
                ]);
                header("Location: clients.php?phone=" . urlencode($telephone) . "&created=1");
                exit;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    $feedback = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Ce numero existe deja. <a href='?phone=" . urlencode($telephone) . "'>Voir la fiche</a></div>";
                } else {
                    $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur: " . e($e->getMessage()) . "</div>";
                }
            }
        }
    }

    // Mettre a jour un client
    if (isset($_POST['update_client'])) {
        $client_id = (int)($_POST['client_id'] ?? 0);
        $prenom = trim($_POST['prenom'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $tags = trim($_POST['tags'] ?? '');

        if ($client_id > 0) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE clients
                    SET prenom = :prenom, nom = :nom, email = :email,
                        adresse = :adresse, notes = :notes, tags = :tags
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':prenom' => $prenom ?: null,
                    ':nom' => $nom ?: null,
                    ':email' => $email ?: null,
                    ':adresse' => $adresse ?: null,
                    ':notes' => $notes ?: null,
                    ':tags' => $tags ?: null,
                    ':id' => $client_id
                ]);
                $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Fiche client mise a jour</div>";
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur: " . e($e->getMessage()) . "</div>";
            }
        }
    }

    // Supprimer un client
    if (isset($_POST['delete_client'])) {
        $client_id = (int)($_POST['client_id'] ?? 0);
        if ($client_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM clients WHERE id = :id");
                $stmt->execute([':id' => $client_id]);
                header("Location: clients.php?deleted=1");
                exit;
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur: " . e($e->getMessage()) . "</div>";
            }
        }
    }
}

// Menu apres le traitement POST (pour ne pas bloquer les redirects header())
include '../pages/menu.php';

// Messages flash
if (isset($_GET['created'])) {
    $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Fiche client creee avec succes!</div>";
}
if (isset($_GET['deleted'])) {
    $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Fiche client supprimee</div>";
}

// --- Parametres ---
$q = trim($_GET['q'] ?? '');
$onlyMulti = isset($_GET['only_multi']) ? 1 : 0;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(200, max(10, (int)($_GET['per_page'] ?? 50)));
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';
$viewPhoneParam = $_GET['phone'] ?? '';
$viewPhoneNorm = phone_normalize_php($viewPhoneParam);
$action = $_GET['action'] ?? '';

// --- API pour auto-suggestion de reservations ---
if (isset($_GET['api']) && $_GET['api'] === 'reservations_by_phone') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $ph = phone_normalize_php($_GET['phone'] ?? '');
        if ($ph === '') {
            echo json_encode(['success' => false, 'message' => 'phone manquant']);
            exit;
        }
        $sql = "
            SELECT r.id, r.reference, r.prenom, r.nom, r.logement_id,
                   r.date_arrivee, r.heure_arrivee, r.date_depart, r.heure_depart,
                   r.statut, l.nom_du_logement
            FROM reservation r
            LEFT JOIN liste_logements l ON r.logement_id = l.id
            WHERE r.telephone IS NOT NULL AND r.telephone <> ''
              AND REPLACE(REPLACE(REPLACE(r.telephone,' ',''),'.',''),'-','') LIKE :ph
            ORDER BY r.date_arrivee DESC
            LIMIT 100
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':ph' => '%' . str_replace('+', '', $ph) . '%']);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'reservations' => $rows], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// --- Vue: Creer un nouveau client ---
if ($action === 'create'):
?>
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="display-4">
                <i class="fas fa-user-plus text-primary"></i> Nouveau client
            </h1>
            <p class="lead text-muted">Creer une nouvelle fiche client</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="clients.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <?= $feedback ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user"></i> Informations client</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php echoCsrfField(); ?>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-phone"></i> Telephone <span class="text-danger">*</span></label>
                                    <input type="tel" name="telephone" class="form-control" required
                                           placeholder="0612345678 ou +33612345678">
                                    <small class="text-muted">Sera normalise automatiquement</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-envelope"></i> Email</label>
                                    <input type="email" name="email" class="form-control" placeholder="client@email.com">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-user"></i> Prenom</label>
                                    <input type="text" name="prenom" class="form-control" placeholder="Jean">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-user"></i> Nom</label>
                                    <input type="text" name="nom" class="form-control" placeholder="Dupont">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-map-marker-alt"></i> Adresse</label>
                            <textarea name="adresse" class="form-control" rows="2" placeholder="Adresse du client"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-tags"></i> Tags</label>
                            <input type="text" name="tags" class="form-control" placeholder="fidele, vip, professionnel...">
                            <small class="text-muted">Separes par des virgules</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-sticky-note"></i> Notes et preferences</label>
                            <textarea name="notes" class="form-control" rows="4"
                                      placeholder="Ex: Prefere check-in tardif, allergique aux chats, client fidele depuis 2020..."></textarea>
                        </div>

                        <div class="text-center mt-4">
                            <a href="clients.php" class="btn btn-secondary px-4">Annuler</a>
                            <button type="submit" name="create_client" class="btn btn-primary px-4">
                                <i class="fas fa-save"></i> Creer la fiche
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-start border-info border-3">
                <div class="card-body">
                    <h6><i class="fas fa-lightbulb text-info"></i> Conseils</h6>
                    <ul class="small mb-0">
                        <li class="mb-2">Le <strong>telephone</strong> est la cle unique du client</li>
                        <li class="mb-2">Les reservations avec ce numero seront automatiquement liees</li>
                        <li class="mb-2">Utilisez les <strong>notes</strong> pour les preferences et remarques importantes</li>
                        <li>Les <strong>tags</strong> permettent de categoriser vos clients (VIP, fidele, etc.)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

<?php
// --- Vue: Fiche client detaillee ---
elseif ($viewPhoneNorm !== ''):
    // Recuperer la fiche client si elle existe
    $clientProfile = null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE telephone = :phone");
        $stmt->execute([':phone' => $viewPhoneNorm]);
        $clientProfile = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    // Recuperer les reservations liees
    $reservations = [];
    try {
        $sql = "
            SELECT r.*, l.nom_du_logement
            FROM reservation r
            LEFT JOIN liste_logements l ON r.logement_id = l.id
            WHERE r.telephone IS NOT NULL AND r.telephone <> ''
              AND REPLACE(REPLACE(REPLACE(r.telephone,' ',''),'.',''),'-','') LIKE :phone
            ORDER BY r.date_arrivee DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':phone' => '%' . str_replace('+', '', $viewPhoneNorm) . '%']);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    $nb = count($reservations);
    $first = $nb ? $reservations[array_key_last($reservations)]['date_arrivee'] : null;
    $last = $nb ? $reservations[0]['date_arrivee'] : null;

    // Determiner le nom a afficher
    $displayName = 'Client';
    if ($clientProfile && !empty($clientProfile['prenom'])) {
        $displayName = trim($clientProfile['prenom'] . ' ' . ($clientProfile['nom'] ?? ''));
    } elseif ($nb > 0) {
        $displayName = trim(($reservations[0]['prenom'] ?? '') . ' ' . ($reservations[0]['nom'] ?? ''));
    }
    if (empty(trim($displayName))) $displayName = 'Client';

    $email = $clientProfile['email'] ?? '';
    if (empty($email) && $nb > 0) {
        foreach ($reservations as $r) {
            if (!empty($r['email'])) { $email = $r['email']; break; }
        }
    }
?>
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="display-4">
                <i class="fas fa-user text-primary"></i> <?= e($displayName) ?>
                <?php if ($clientProfile): ?>
                    <?php
                    $tags = array_filter(array_map('trim', explode(',', $clientProfile['tags'] ?? '')));
                    foreach ($tags as $tag):
                    ?>
                        <span class="badge bg-info"><?= e($tag) ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </h1>
            <p class="lead text-muted">
                <i class="fas fa-phone"></i> <?= e($viewPhoneNorm) ?>
                <?php if ($email): ?>
                    &nbsp;&bull;&nbsp; <i class="fas fa-envelope"></i> <?= e($email) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="col-md-4 text-end">
            <a href="clients.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
            <button class="btn btn-primary" id="btnOpenDrawer">
                <i class="fas fa-comment-dots"></i> SMS
            </button>
        </div>
    </div>

    <?= $feedback ?>

    <!-- Stats client -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-primary border-3">
                <div class="card-body text-center">
                    <h3 class="text-primary mb-0"><?= $nb ?></h3>
                    <small class="text-muted">Sejour(s)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-success border-3">
                <div class="card-body text-center">
                    <h5 class="mb-0"><?= $first ? date('d/m/Y', strtotime($first)) : '-' ?></h5>
                    <small class="text-muted">Premier sejour</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-info border-3">
                <div class="card-body text-center">
                    <h5 class="mb-0"><?= $last ? date('d/m/Y', strtotime($last)) : '-' ?></h5>
                    <small class="text-muted">Dernier sejour</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-warning border-3">
                <div class="card-body text-center">
                    <h5 class="mb-0"><?= $nb >= 2 ? '<i class="fas fa-star text-warning"></i> Oui' : 'Non' ?></h5>
                    <small class="text-muted">Client fidele</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Colonne gauche: Reservations -->
        <div class="col-lg-8">
            <!-- Fiche client / Notes -->
            <div class="card shadow-sm mb-4 border-start border-info border-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-sticky-note"></i> Fiche & Notes</h5>
                    <?php if (!$clientProfile): ?>
                        <form method="POST" style="display:inline">
                            <?php echoCsrfField(); ?>
                            <input type="hidden" name="telephone" value="<?= e($viewPhoneNorm) ?>">
                            <input type="hidden" name="prenom" value="<?= e(!empty($reservations) ? ($reservations[0]['prenom'] ?? '') : '') ?>">
                            <input type="hidden" name="nom" value="<?= e(!empty($reservations) ? ($reservations[0]['nom'] ?? '') : '') ?>">
                            <input type="hidden" name="email" value="<?= e($email) ?>">
                            <button type="submit" name="create_client" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> Creer la fiche
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($clientProfile): ?>
                        <form method="POST">
                            <?php echoCsrfField(); ?>
                            <input type="hidden" name="client_id" value="<?= $clientProfile['id'] ?>">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Prenom</label>
                                        <input type="text" name="prenom" class="form-control" value="<?= e($clientProfile['prenom']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nom</label>
                                        <input type="text" name="nom" class="form-control" value="<?= e($clientProfile['nom']) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" value="<?= e($clientProfile['email']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Tags</label>
                                        <input type="text" name="tags" class="form-control" value="<?= e($clientProfile['tags']) ?>"
                                               placeholder="fidele, vip, professionnel...">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Adresse</label>
                                <textarea name="adresse" class="form-control" rows="2"><?= e($clientProfile['adresse']) ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-sticky-note"></i> Notes et preferences</label>
                                <textarea name="notes" class="form-control" rows="4"
                                          placeholder="Preferences, remarques, historique..."><?= e($clientProfile['notes']) ?></textarea>
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="submit" name="delete_client" class="btn btn-outline-danger"
                                        onclick="return confirm('Supprimer cette fiche client?')">
                                    <i class="fas fa-trash"></i> Supprimer
                                </button>
                                <button type="submit" name="update_client" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Enregistrer
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-user-plus fa-3x mb-3"></i>
                            <p>Aucune fiche client pour ce numero.<br>Cliquez sur "Creer la fiche" pour ajouter des notes et preferences.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Historique des reservations -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Historique des sejours</h5>
                </div>
                <div class="card-body">
                    <?php if ($nb > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Logement</th>
                                        <th>Arrivee</th>
                                        <th>Depart</th>
                                        <th>Plateforme</th>
                                        <th>Statut</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reservations as $r): ?>
                                        <tr>
                                            <td><code><?= e($r['reference'] ?? '-') ?></code></td>
                                            <td>
                                                <?php if (!empty($r['nom_du_logement'])): ?>
                                                    <span class="badge bg-info"><?= e($r['nom_du_logement']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($r['date_arrivee'])) ?></td>
                                            <td><?= date('d/m/Y', strtotime($r['date_depart'])) ?></td>
                                            <td><small><?= e($r['plateforme'] ?? '-') ?></small></td>
                                            <td>
                                                <?php
                                                $statut = $r['statut'] ?? '';
                                                $badge = 'secondary';
                                                if (stripos($statut, 'confirm') !== false) $badge = 'success';
                                                elseif (stripos($statut, 'annul') !== false) $badge = 'danger';
                                                ?>
                                                <span class="badge bg-<?= $badge ?>"><?= e($statut ?: '-') ?></span>
                                            </td>
                                            <td>
                                                <a href="reservations.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <p>Aucune reservation trouvee pour ce numero</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Colonne droite: Actions -->
        <div class="col-lg-4">
            <!-- Actions rapides -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bolt"></i> Actions rapides</h5>
                </div>
                <div class="card-body">
                    <a href="sms_recus.php?filter=<?= urlencode($viewPhoneNorm) ?>" class="btn btn-outline-info w-100 mb-2">
                        <i class="fas fa-inbox"></i> Voir les SMS
                    </a>
                    <a href="sms_campagnes.php?action=create" class="btn btn-outline-warning w-100 mb-2">
                        <i class="fas fa-bullhorn"></i> Nouvelle campagne
                    </a>
                    <button class="btn btn-primary w-100" id="btnOpenDrawer2">
                        <i class="fas fa-paper-plane"></i> Envoyer un SMS
                    </button>
                </div>
            </div>

            <!-- Info creation -->
            <?php if ($clientProfile): ?>
                <div class="card shadow-sm">
                    <div class="card-body small text-muted">
                        <p class="mb-1"><strong>Fiche creee:</strong> <?= date('d/m/Y H:i', strtotime($clientProfile['created_at'])) ?></p>
                        <p class="mb-0"><strong>Derniere MAJ:</strong> <?= date('d/m/Y H:i', strtotime($clientProfile['updated_at'])) ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Drawer SMS -->
    <div id="smsDrawer" class="drawer" aria-hidden="true">
        <div class="drawer-header d-flex align-items-center">
            <strong><i class="fas fa-sms"></i> SMS - <?= e($viewPhoneNorm) ?></strong>
            <button class="btn btn-sm btn-outline-secondary ms-auto" id="btnCloseDrawer">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="drawerBody" class="drawer-body">
            <div class="text-center text-muted small py-4">Chargement...</div>
        </div>
        <div class="drawer-footer">
            <div class="mb-2">
                <label class="small fw-bold">Modele</label>
                <select id="tplSelect" class="form-select form-select-sm">
                    <option value="">-- Aucun --</option>
                    <option value="arrivee">Arrivee</option>
                    <option value="depart">Depart</option>
                    <option value="relance">Relance client</option>
                    <option value="libre">Message libre</option>
                </select>
            </div>
            <div class="mb-2">
                <textarea id="smsText" rows="4" class="form-control" placeholder="Votre message..."></textarea>
            </div>
            <button id="btnSendSms" class="btn btn-primary w-100">
                <i class="fas fa-paper-plane"></i> Envoyer
            </button>
        </div>
    </div>

    <style>
        .drawer { position: fixed; top: 0; right: -420px; width: 400px; height: 100vh;
            background: #fff; box-shadow: -2px 0 12px rgba(0,0,0,0.15);
            transition: right .25s ease; z-index: 1050; display: flex; flex-direction: column; }
        .drawer.open { right: 0; }
        .drawer-header { padding: 16px; border-bottom: 1px solid #e9ecef; background: #f8f9fa; }
        .drawer-body { flex: 1; overflow: auto; background: #f1f3f4; padding: 12px; }
        .drawer-footer { padding: 16px; border-top: 1px solid #e9ecef; background: #fff; }
        .bubble { padding: 10px 14px; border-radius: 16px; margin-bottom: 10px; max-width: 85%; }
        .bubble.in { background: #e9ecef; color: #343a40; margin-right: auto; border-bottom-left-radius: 4px; }
        .bubble.out { background: #667eea; color: #fff; margin-left: auto; border-bottom-right-radius: 4px; }
        .bubble .meta { font-size: .7rem; opacity: .7; margin-top: 4px; text-align: right; }
    </style>

    <script>
        const CTX = {
            phone: <?= json_encode($viewPhoneNorm) ?>,
            prenom: <?= json_encode(($clientProfile['prenom'] ?? null) ?: (!empty($reservations) ? ($reservations[0]['prenom'] ?? '') : '')) ?>
        };

        const TEMPLATES = {
            arrivee: "Bonjour {prenom},\nVotre arrivee est bientot! N'hesitez pas a nous contacter pour toute question.",
            depart: "Bonjour {prenom},\nNous esperons que votre sejour s'est bien passe. Merci et a bientot!",
            relance: "Bonjour {prenom},\nNous esperons que vous gardez un bon souvenir de votre sejour! Avez-vous des projets de revenir sur Compiegne prochainement?",
            libre: ""
        };

        const drawer = document.getElementById('smsDrawer');
        const drawerBody = document.getElementById('drawerBody');
        const tplSelect = document.getElementById('tplSelect');
        const smsText = document.getElementById('smsText');

        function openDrawer() {
            drawer.classList.add('open');
            loadConversation(CTX.phone);
        }

        document.getElementById('btnOpenDrawer').addEventListener('click', openDrawer);
        document.getElementById('btnOpenDrawer2')?.addEventListener('click', openDrawer);
        document.getElementById('btnCloseDrawer').addEventListener('click', () => drawer.classList.remove('open'));

        tplSelect.addEventListener('change', () => {
            const raw = TEMPLATES[tplSelect.value] || '';
            smsText.value = raw.replace(/{prenom}/g, CTX.prenom || 'vous');
        });

        async function loadConversation(phone) {
            drawerBody.innerHTML = '<div class="text-center text-muted small py-4">Chargement...</div>';
            try {
                const resp = await fetch('get_conversation.php?sender=' + encodeURIComponent(phone));
                const data = await resp.json();
                const list = Array.isArray(data) ? data : (data.messages || []);
                if (list.length === 0) {
                    drawerBody.innerHTML = '<div class="text-center text-muted small py-4">Aucun message</div>';
                    return;
                }
                drawerBody.innerHTML = '';
                list.forEach(m => {
                    const div = document.createElement('div');
                    div.className = 'bubble ' + (m.direction === 'in' ? 'in' : 'out');
                    div.innerHTML = '<div>' + (m.message || '').replace(/\n/g, '<br>') + '</div>' +
                                   '<div class="meta">' + (m.date || '') + '</div>';
                    drawerBody.appendChild(div);
                });
                drawerBody.scrollTop = drawerBody.scrollHeight;
            } catch (e) {
                drawerBody.innerHTML = '<div class="text-danger small">Erreur: ' + e.message + '</div>';
            }
        }

        document.getElementById('btnSendSms').addEventListener('click', async () => {
            const text = smsText.value.trim();
            if (!text) { alert('Message vide'); return; }
            const btn = document.getElementById('btnSendSms');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';
            try {
                const resp = await fetch('send_sms_ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ receiver: CTX.phone, message: text })
                });
                const result = await resp.json();
                if (result.success) {
                    alert('SMS envoye!');
                    smsText.value = '';
                    loadConversation(CTX.phone);
                } else {
                    throw new Error(result.message || 'Erreur');
                }
            } catch (e) {
                alert('Erreur: ' + e.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer';
            }
        });
    </script>

<?php
// --- Vue: Liste des clients ---
else:
    // Recuperer les clients enregistres
    $clientsProfiles = [];
    try {
        $stmt = $pdo->query("SELECT * FROM clients ORDER BY updated_at DESC");
        $clientsProfiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    // Construire la liste combinee (clients enregistres + clients des reservations)
    $where = "WHERE r.telephone IS NOT NULL AND r.telephone <> ''";
    $params = [];

    if ($q !== '') {
        $where .= " AND (r.prenom LIKE :q OR r.nom LIKE :q OR r.email LIKE :q OR r.telephone LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }

    // Count pour pagination
    $countSql = "
        SELECT COUNT(DISTINCT REPLACE(REPLACE(REPLACE(r.telephone,' ',''),'.',''),'-','')) as total
        FROM reservation r $where
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRows = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $offset = ($page - 1) * $perPage;

    // Liste paginee
    $listSql = "
        SELECT
            REPLACE(REPLACE(REPLACE(r.telephone,' ',''),'.',''),'-','') AS phone_clean,
            COUNT(*) AS nb_resa,
            MIN(r.date_arrivee) AS first_arrivee,
            MAX(r.date_arrivee) AS last_arrivee,
            GROUP_CONCAT(DISTINCT CONCAT(TRIM(COALESCE(r.prenom,'')), ' ', TRIM(COALESCE(r.nom,''))) SEPARATOR ' / ') AS names,
            MAX(r.email) AS email
        FROM reservation r
        $where
        GROUP BY phone_clean
        " . ($onlyMulti ? "HAVING COUNT(*) >= 2" : "") . "
        ORDER BY nb_resa DESC, last_arrivee DESC
        LIMIT :lim OFFSET :off
    ";
    $listStmt = $pdo->prepare($listSql);
    foreach ($params as $k => $v) $listStmt->bindValue($k, $v);
    $listStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $listStmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $listStmt->execute();
    $data = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    // Export CSV
    if ($exportCsv) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=clients_' . date('Y-m-d') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Telephone', 'Nb Reservations', 'Premiere arrivee', 'Derniere arrivee', 'Noms', 'Email'], ';');
        foreach ($data as $row) {
            fputcsv($out, [$row['phone_clean'], $row['nb_resa'], $row['first_arrivee'], $row['last_arrivee'], $row['names'], $row['email']], ';');
        }
        fclose($out);
        exit;
    }

    // Stats
    $statsTotal = $totalRows;
    $statsFiches = count($clientsProfiles);
    $statsFideles = 0;
    try {
        $statsFideles = $pdo->query("
            SELECT COUNT(*) FROM (
                SELECT REPLACE(REPLACE(REPLACE(telephone,' ',''),'.',''),'-','') as phone
                FROM reservation WHERE telephone IS NOT NULL GROUP BY phone HAVING COUNT(*) >= 2
            ) t
        ")->fetchColumn();
    } catch (PDOException $e) {}
?>

    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="display-4">
                <i class="fas fa-address-book text-primary"></i> Carnet de clients
            </h1>
            <p class="lead text-muted">Gerez vos clients, ajoutez des notes et suivez leur historique</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="?action=create" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Nouveau client
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv', 'page' => 1])) ?>" class="btn btn-success">
                <i class="fas fa-file-csv"></i> Export
            </a>
        </div>
    </div>

    <?= $feedback ?>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-primary border-3">
                <div class="card-body">
                    <div class="row g-0 align-items-center">
                        <div class="col me-2">
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total clients</div>
                            <div class="h5 mb-0 fw-bold"><?= $statsTotal ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-success border-3">
                <div class="card-body">
                    <div class="row g-0 align-items-center">
                        <div class="col me-2">
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">Fiches creees</div>
                            <div class="h5 mb-0 fw-bold"><?= $statsFiches ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-id-card fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-warning border-3">
                <div class="card-body">
                    <div class="row g-0 align-items-center">
                        <div class="col me-2">
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">Clients fideles (2+)</div>
                            <div class="h5 mb-0 fw-bold"><?= $statsFideles ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-star fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-auto">
                    <input type="text" name="q" class="form-control" placeholder="Rechercher..." value="<?= e($q) ?>">
                </div>
                <div class="col-auto">
                    <select name="per_page" class="form-select">
                        <?php foreach ([25, 50, 100] as $pp): ?>
                            <option value="<?= $pp ?>" <?= $pp == $perPage ? 'selected' : '' ?>><?= $pp ?>/page</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="only_multi" name="only_multi" <?= $onlyMulti ? 'checked' : '' ?>>
                        <label class="form-check-label" for="only_multi">Clients fideles</label>
                    </div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                    <a class="btn btn-outline-secondary" href="clients.php"><i class="fas fa-times"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste -->
    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list"></i> Liste des clients</h5>
        </div>
        <div class="card-body">
            <?php if (empty($data)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-search fa-4x mb-3"></i>
                    <p>Aucun client trouve</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Telephone</th>
                                <th>Sejours</th>
                                <th>Dernier sejour</th>
                                <th>Nom(s)</th>
                                <th>Fiche</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $row):
                                $phoneNorm = phone_normalize_php($row['phone_clean']);
                                $hasFiche = false;
                                foreach ($clientsProfiles as $cp) {
                                    if ($cp['telephone'] === $phoneNorm) { $hasFiche = true; break; }
                                }
                            ?>
                                <tr>
                                    <td><code><?= e($row['phone_clean']) ?></code></td>
                                    <td>
                                        <span class="badge bg-<?= (int)$row['nb_resa'] >= 2 ? 'success' : 'secondary' ?> rounded-pill">
                                            <?= $row['nb_resa'] ?>
                                        </span>
                                    </td>
                                    <td><?= $row['last_arrivee'] ? date('d/m/Y', strtotime($row['last_arrivee'])) : '-' ?></td>
                                    <td><?= e(substr($row['names'] ?? '', 0, 30)) ?><?= strlen($row['names'] ?? '') > 30 ? '...' : '' ?></td>
                                    <td>
                                        <?php if ($hasFiche): ?>
                                            <span class="badge bg-info"><i class="fas fa-check"></i> Oui</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?phone=<?= urlencode($row['phone_clean']) ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-user"></i> Fiche
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php $base = $_GET; unset($base['page']); ?>
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query($base + ['page' => max(1, $page - 1)]) ?>">&laquo;</a>
                            </li>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query($base + ['page' => $i]) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query($base + ['page' => min($totalPages, $page + 1)]) ?>">&raquo;</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
