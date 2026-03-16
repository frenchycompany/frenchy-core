<?php
/**
 * Gestion des Propriétaires — FrenchyConciergerie
 * CRUD complet + attribution de logements
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';

// Vérification admin
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../error.php?message=" . urlencode('Accès réservé aux administrateurs.'));
    exit;
}

// Tables requises : voir db/install_tables.php

// Auto-migration : table FC_proprietaires (s'assurer que les colonnes existent)
try {
    $cols = array_column($conn->query("SHOW COLUMNS FROM FC_proprietaires")->fetchAll(), 'Field');
    if (!in_array('actif', $cols)) {
        $conn->exec("ALTER TABLE FC_proprietaires ADD COLUMN actif TINYINT(1) DEFAULT 1");
    }
    // Champs adresse structurée
    $addrCols = [
        'adresse_ligne2' => "VARCHAR(255) DEFAULT NULL AFTER adresse",
        'code_postal'    => "VARCHAR(10) DEFAULT NULL AFTER adresse_ligne2",
        'ville'          => "VARCHAR(100) DEFAULT NULL AFTER code_postal",
    ];
    foreach ($addrCols as $col => $def) {
        if (!in_array($col, $cols)) {
            $conn->exec("ALTER TABLE FC_proprietaires ADD COLUMN $col $def");
        }
    }
    // Nouveaux champs proprietaire
    $newCols = [
        'societe'       => "VARCHAR(255) DEFAULT NULL AFTER ville",
        'siret'         => "VARCHAR(20) DEFAULT NULL AFTER societe",
        'rib_iban'      => "VARCHAR(40) DEFAULT NULL AFTER siret",
        'rib_bic'       => "VARCHAR(15) DEFAULT NULL AFTER rib_iban",
        'rib_banque'    => "VARCHAR(100) DEFAULT NULL AFTER rib_bic",
        'commission'    => "DECIMAL(5,2) DEFAULT NULL AFTER rib_banque",
        'notes'         => "TEXT DEFAULT NULL AFTER commission",
    ];
    foreach ($newCols as $col => $def) {
        if (!in_array($col, $cols)) {
            $conn->exec("ALTER TABLE FC_proprietaires ADD COLUMN $col $def");
        }
    }
} catch (PDOException $e) {
    error_log('proprietaires.php: ' . $e->getMessage());
}

// Auto-migration : colonne proprietaire_id dans liste_logements
try {
    $cols = array_column($conn->query("SHOW COLUMNS FROM liste_logements")->fetchAll(), 'Field');
    if (!in_array('proprietaire_id', $cols)) {
        $conn->exec("ALTER TABLE liste_logements ADD COLUMN proprietaire_id INT DEFAULT NULL");
    }
} catch (PDOException $e) { error_log('proprietaires.php: ' . $e->getMessage()); }

$feedback = '';

// ============================================================
// ACTIONS POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    // --- Créer ou modifier un propriétaire ---
    if (isset($_POST['save_proprietaire'])) {
        $prop_id    = (int) ($_POST['proprietaire_id'] ?? 0);
        $nom        = trim($_POST['nom'] ?? '');
        $prenom     = trim($_POST['prenom'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $telephone  = trim($_POST['telephone'] ?? '');
        $adresse    = trim($_POST['adresse'] ?? '');
        $adresse_ligne2 = trim($_POST['adresse_ligne2'] ?? '');
        $code_postal = trim($_POST['code_postal'] ?? '');
        $ville      = trim($_POST['ville'] ?? '');
        $societe    = trim($_POST['societe'] ?? '');
        $siret      = trim($_POST['siret'] ?? '');
        $rib_iban   = trim($_POST['rib_iban'] ?? '');
        $rib_bic    = trim($_POST['rib_bic'] ?? '');
        $rib_banque = trim($_POST['rib_banque'] ?? '');
        $commission = $_POST['commission'] !== '' ? (float)$_POST['commission'] : null;
        $notes      = trim($_POST['notes'] ?? '');
        $password   = $_POST['password'] ?? '';
        $logements  = $_POST['logements'] ?? [];

        if (empty($nom)) {
            $feedback = '<div class="alert alert-danger">Le nom est obligatoire.</div>';
        } elseif (empty($email)) {
            $feedback = '<div class="alert alert-danger">L\'email est obligatoire (utilisé pour la connexion).</div>';
        } elseif ($prop_id === 0 && empty($password)) {
            $feedback = '<div class="alert alert-danger">Le mot de passe est obligatoire pour un nouveau propriétaire.</div>';
        } else {
            try {
                if ($prop_id > 0) {
                    // Mise à jour — mot de passe optionnel
                    $sql = "UPDATE FC_proprietaires SET nom=?, prenom=?, email=?, telephone=?, adresse=?, adresse_ligne2=?, code_postal=?, ville=?, societe=?, siret=?, rib_iban=?, rib_bic=?, rib_banque=?, commission=?, notes=?";
                    $params = [$nom, $prenom ?: null, $email, $telephone ?: null, $adresse ?: null, $adresse_ligne2 ?: null, $code_postal ?: null, $ville ?: null, $societe ?: null, $siret ?: null, $rib_iban ?: null, $rib_bic ?: null, $rib_banque ?: null, $commission, $notes ?: null];
                    if (!empty($password)) {
                        $sql .= ", password_hash=?";
                        $params[] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $sql .= " WHERE id=?";
                    $params[] = $prop_id;
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($params);
                    $feedback = '<div class="alert alert-success">Propriétaire mis à jour.</div>';
                } else {
                    $stmt = $conn->prepare("INSERT INTO FC_proprietaires (nom, prenom, email, telephone, adresse, adresse_ligne2, code_postal, ville, societe, siret, rib_iban, rib_bic, rib_banque, commission, notes, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nom, $prenom ?: null, $email, $telephone ?: null, $adresse ?: null, $adresse_ligne2 ?: null, $code_postal ?: null, $ville ?: null, $societe ?: null, $siret ?: null, $rib_iban ?: null, $rib_bic ?: null, $rib_banque ?: null, $commission, $notes ?: null, password_hash($password, PASSWORD_DEFAULT)]);
                    $prop_id = $conn->lastInsertId();
                    $feedback = '<div class="alert alert-success">Propriétaire créé.</div>';
                }

                // Mettre à jour les logements attribués
                // D'abord retirer ce propriétaire de tous les logements
                $conn->prepare("UPDATE liste_logements SET proprietaire_id = NULL WHERE proprietaire_id = ?")->execute([$prop_id]);
                // Puis attribuer les logements sélectionnés
                if (!empty($logements)) {
                    $stmt = $conn->prepare("UPDATE liste_logements SET proprietaire_id = ? WHERE id = ?");
                    foreach ($logements as $lid) {
                        $stmt->execute([$prop_id, (int) $lid]);
                    }
                }
            } catch (PDOException $e) {
                $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    // --- Toggle actif/inactif ---
    if (isset($_POST['toggle_actif'])) {
        $tid = (int) $_POST['toggle_actif'];
        try {
            $conn->prepare("UPDATE FC_proprietaires SET actif = NOT actif WHERE id = ?")->execute([$tid]);
            $feedback = '<div class="alert alert-success">Statut mis à jour.</div>';
        } catch (PDOException $e) {
            $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    // --- Supprimer ---
    if (isset($_POST['delete_proprietaire'])) {
        $did = (int) $_POST['delete_proprietaire'];
        try {
            // Retirer le lien avec les logements
            $conn->prepare("UPDATE liste_logements SET proprietaire_id = NULL WHERE proprietaire_id = ?")->execute([$did]);
            $conn->prepare("DELETE FROM FC_proprietaires WHERE id = ?")->execute([$did]);
            $feedback = '<div class="alert alert-success">Propriétaire supprimé.</div>';
        } catch (PDOException $e) {
            $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// ============================================================
// DONNÉES
// ============================================================
$proprietaires = $conn->query("SELECT * FROM FC_proprietaires ORDER BY actif DESC, nom ASC")->fetchAll(PDO::FETCH_ASSOC);
$logements_all = $conn->query("SELECT id, nom_du_logement, proprietaire_id FROM liste_logements ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);

// Logements par propriétaire
$logements_par_proprio = [];
foreach ($logements_all as $l) {
    if ($l['proprietaire_id']) {
        $logements_par_proprio[$l['proprietaire_id']][] = $l;
    }
}

$nb_actifs = count(array_filter($proprietaires, fn($p) => !empty($p['actif'])));
$nb_inactifs = count($proprietaires) - $nb_actifs;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Propriétaires — FrenchyConciergerie</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .proprio-inactif { opacity: 0.55; }
        .logement-badge { font-size: 0.78rem; margin: 2px; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">

    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-user-tie text-success"></i> Gestion des Propriétaires</h2>
            <p class="text-muted">
                <?= count($proprietaires) ?> propriétaire(s) —
                <span class="text-success"><?= $nb_actifs ?> actif(s)</span>
                <?php if ($nb_inactifs > 0): ?>
                    / <span class="text-secondary"><?= $nb_inactifs ?> inactif(s)</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="col-auto">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#proprioModal" onclick="resetModal()">
                <i class="fas fa-plus"></i> Nouveau propriétaire
            </button>
        </div>
    </div>

    <?= $feedback ?>

    <!-- Tableau des propriétaires -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Logements</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($proprietaires as $p): ?>
                        <?php $pLogements = $logements_par_proprio[$p['id']] ?? []; ?>
                        <tr class="<?= empty($p['actif']) ? 'proprio-inactif' : '' ?>">
                            <td>
                                <strong><?= htmlspecialchars($p['nom']) ?></strong>
                                <?php if (!empty($p['prenom'])): ?>
                                    <?= htmlspecialchars($p['prenom']) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($p['email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($p['email']) ?>"><?= htmlspecialchars($p['email']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($p['telephone'])): ?>
                                    <a href="tel:<?= htmlspecialchars($p['telephone']) ?>"><?= htmlspecialchars($p['telephone']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($pLogements)): ?>
                                    <?php foreach ($pLogements as $lg): ?>
                                        <span class="badge bg-primary logement-badge"><?= htmlspecialchars($lg['nom_du_logement']) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">Aucun</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <?php echoCsrfField(); ?>
                                    <input type="hidden" name="toggle_actif" value="<?= $p['id'] ?>">
                                    <?php if (!empty($p['actif'])): ?>
                                        <button type="submit" class="btn btn-sm btn-success" title="Cliquer pour désactiver">
                                            <i class="fas fa-check-circle"></i> Actif
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-sm btn-secondary" title="Cliquer pour activer">
                                            <i class="fas fa-pause-circle"></i> Inactif
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                            <td class="text-nowrap">
                                <button type="button" class="btn btn-sm btn-warning"
                                        onclick="editProprio(<?= htmlspecialchars(json_encode(array_merge($p, ['logements' => array_column($pLogements, 'id')]))) ?>)"
                                        title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce propriétaire ?')">
                                    <?php echoCsrfField(); ?>
                                    <input type="hidden" name="delete_proprietaire" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($proprietaires)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Aucun propriétaire enregistré.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════ -->
<!-- MODAL : Créer / Modifier un propriétaire                -->
<!-- ════════════════════════════════════════════════════════ -->
<div class="modal fade" id="proprioModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white" id="modal-header">
                <h5 class="modal-title" id="modal-title"><i class="fas fa-plus"></i> Nouveau propriétaire</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echoCsrfField(); ?>
                <input type="hidden" name="proprietaire_id" id="m_id" value="0">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Identite</h6>
                            <div class="mb-3">
                                <label class="form-label">Nom *</label>
                                <input type="text" class="form-control" name="nom" id="m_nom" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Prénom</label>
                                <input type="text" class="form-control" name="prenom" id="m_prenom">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email * <small class="text-muted">(connexion espace propriétaire)</small></label>
                                <input type="email" class="form-control" name="email" id="m_email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Mot de passe <span id="pwd_required">*</span></label>
                                <input type="password" class="form-control" name="password" id="m_password" autocomplete="new-password">
                                <small class="text-muted" id="pwd_hint">Obligatoire à la création. Laisser vide pour ne pas modifier.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" name="telephone" id="m_telephone">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Adresse ligne 1</label>
                                <input type="text" class="form-control" name="adresse" id="m_adresse" placeholder="N° et rue">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Adresse ligne 2</label>
                                <input type="text" class="form-control" name="adresse_ligne2" id="m_adresse_ligne2" placeholder="Bâtiment, appartement, étage...">
                            </div>
                            <div class="row mb-3">
                                <div class="col-4">
                                    <label class="form-label">Code postal</label>
                                    <input type="text" class="form-control" name="code_postal" id="m_code_postal" placeholder="60000" maxlength="10">
                                </div>
                                <div class="col-8">
                                    <label class="form-label">Ville</label>
                                    <input type="text" class="form-control" name="ville" id="m_ville" placeholder="Compiègne">
                                </div>
                            </div>

                            <hr>
                            <h6 class="text-muted mb-3">Societe / Facturation</h6>
                            <div class="mb-3">
                                <label class="form-label">Societe</label>
                                <input type="text" class="form-control" name="societe" id="m_societe" placeholder="Nom de la societe">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">SIRET</label>
                                <input type="text" class="form-control" name="siret" id="m_siret" placeholder="XXX XXX XXX XXXXX" maxlength="20">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Commission (%)</label>
                                <input type="number" class="form-control" name="commission" id="m_commission" step="0.01" min="0" max="100" placeholder="Ex: 20.00">
                            </div>

                            <hr>
                            <h6 class="text-muted mb-3">Coordonnees bancaires</h6>
                            <div class="mb-3">
                                <label class="form-label">IBAN</label>
                                <input type="text" class="form-control" name="rib_iban" id="m_rib_iban" placeholder="FR76 XXXX XXXX XXXX XXXX XXXX XXX" maxlength="40">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">BIC</label>
                                <input type="text" class="form-control" name="rib_bic" id="m_rib_bic" placeholder="BNPAFRPP" maxlength="15">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Banque</label>
                                <input type="text" class="form-control" name="rib_banque" id="m_rib_banque" placeholder="Nom de la banque">
                            </div>

                            <hr>
                            <h6 class="text-muted mb-3">Notes</h6>
                            <div class="mb-3">
                                <textarea class="form-control" name="notes" id="m_notes" rows="3" placeholder="Notes internes sur ce proprietaire..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Logements attribués</h6>
                            <div style="max-height:350px; overflow-y:auto; border:1px solid #dee2e6; border-radius:6px; padding:0.75rem;">
                                <?php foreach ($logements_all as $lg): ?>
                                <div class="form-check">
                                    <input class="form-check-input logement-check" type="checkbox" name="logements[]"
                                           value="<?= $lg['id'] ?>" id="lg_<?= $lg['id'] ?>">
                                    <label class="form-check-label" for="lg_<?= $lg['id'] ?>">
                                        <?= htmlspecialchars($lg['nom_du_logement']) ?>
                                        <?php if ($lg['proprietaire_id']): ?>
                                            <small class="text-muted">(attribué)</small>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                                <?php if (empty($logements_all)): ?>
                                    <p class="text-muted mb-0">Aucun logement disponible.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="save_proprietaire" class="btn btn-success" id="m_submit">
                        <i class="fas fa-save"></i> Créer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetModal() {
    ['m_id','m_nom','m_prenom','m_email','m_password','m_telephone','m_adresse','m_adresse_ligne2','m_code_postal','m_ville',
     'm_societe','m_siret','m_rib_iban','m_rib_bic','m_rib_banque','m_commission','m_notes'
    ].forEach(id => { const el = document.getElementById(id); if(el) el.value = id === 'm_id' ? '0' : ''; });
    document.querySelectorAll('.logement-check').forEach(c => c.checked = false);
    document.getElementById('modal-title').innerHTML = '<i class="fas fa-plus"></i> Nouveau propriétaire';
    document.getElementById('modal-header').className = 'modal-header bg-success text-white';
    document.getElementById('m_submit').innerHTML = '<i class="fas fa-plus"></i> Créer';
    document.getElementById('m_submit').className = 'btn btn-success';
    document.getElementById('pwd_required').style.display = '';
    document.getElementById('pwd_hint').textContent = 'Obligatoire à la création.';
    document.getElementById('m_password').required = true;
}

function editProprio(p) {
    document.getElementById('m_id').value = p.id;
    document.getElementById('m_nom').value = p.nom || '';
    document.getElementById('m_prenom').value = p.prenom || '';
    document.getElementById('m_email').value = p.email || '';
    document.getElementById('m_password').value = '';
    document.getElementById('m_telephone').value = p.telephone || '';
    document.getElementById('m_adresse').value = p.adresse || '';
    document.getElementById('m_adresse_ligne2').value = p.adresse_ligne2 || '';
    document.getElementById('m_code_postal').value = p.code_postal || '';
    document.getElementById('m_ville').value = p.ville || '';
    document.getElementById('m_societe').value = p.societe || '';
    document.getElementById('m_siret').value = p.siret || '';
    document.getElementById('m_rib_iban').value = p.rib_iban || '';
    document.getElementById('m_rib_bic').value = p.rib_bic || '';
    document.getElementById('m_rib_banque').value = p.rib_banque || '';
    document.getElementById('m_commission').value = p.commission || '';
    document.getElementById('m_notes').value = p.notes || '';
    document.getElementById('pwd_required').style.display = 'none';
    document.getElementById('pwd_hint').textContent = 'Laisser vide pour ne pas modifier le mot de passe.';
    document.getElementById('m_password').required = false;

    document.querySelectorAll('.logement-check').forEach(c => {
        c.checked = (p.logements || []).includes(parseInt(c.value));
    });

    document.getElementById('modal-title').innerHTML = '<i class="fas fa-edit"></i> Modifier : ' + (p.nom || '');
    document.getElementById('modal-header').className = 'modal-header bg-warning text-dark';
    document.getElementById('m_submit').innerHTML = '<i class="fas fa-save"></i> Enregistrer';
    document.getElementById('m_submit').className = 'btn btn-warning';

    new bootstrap.Modal(document.getElementById('proprioModal')).show();
}
</script>
</body>
</html>
