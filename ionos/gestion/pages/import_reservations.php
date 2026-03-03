<?php
/**
 * Import CSV Reservations — FrenchyConciergerie
 * Importe des reservations depuis un fichier CSV (export Superhote/OTA)
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_bridge.php';

function phone_normalize_import(?string $raw): string {
    if (!$raw) return '';
    $p = preg_replace('/[()\.\s-]+/', '', $raw);
    if (strpos($p, '00') === 0) $p = '+' . substr($p, 2);
    if (strlen($p) === 10 && $p[0] === '0') return '+33' . substr($p, 1);
    if (strlen($p) === 11 && substr($p, 0, 2) === '33') return '+' . $p;
    if (substr($p, 0, 1) === '+') return $p;
    return $p;
}

$feedback = '';
$importees = 0;
$ignorees = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fichier'])) {
    validateCsrfToken();

    $fichier = $_FILES['fichier']['tmp_name'];
    if (!file_exists($fichier) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
        $feedback = '<div class="alert alert-danger">Fichier introuvable ou erreur d\'upload.</div>';
    } else {
        $handle = fopen($fichier, 'r');
        $header = fgetcsv($handle, 4096, ',');
        if (!$header) {
            $feedback = '<div class="alert alert-danger">Impossible de lire l\'en-tete du fichier CSV.</div>';
        } else {
            // Nettoyer BOM UTF-8 si present
            $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
            $header = array_map('trim', $header);

            while (($row = fgetcsv($handle, 4096, ',')) !== false) {
                $row = array_map('trim', $row);
                // Ajuster la taille du row
                while (count($row) > count($header) && end($row) === '') {
                    array_pop($row);
                }
                if (count($row) !== count($header)) {
                    $ignorees++;
                    continue;
                }

                $data = array_combine($header, $row);

                // On n'importe que les "confirmee"
                if (!isset($data['status']) ||
                    !in_array(strtolower($data['status']), ['confirmée', 'confirmed', 'confirmee'], true)) {
                    $ignorees++;
                    continue;
                }

                $reference = $data['id'] ?? '';
                if (empty($reference)) {
                    $ignorees++;
                    continue;
                }

                // Verifier doublon
                try {
                    $stmt = $pdo->prepare("SELECT id FROM reservation WHERE reference = ?");
                    $stmt->execute([$reference]);
                    if ($stmt->fetch()) {
                        $ignorees++;
                        continue;
                    }
                } catch (PDOException $e) {
                    $ignorees++;
                    continue;
                }

                // Recuperer le logement par nom
                $rentalName = $data['rental'] ?? '';
                $logement_id = null;
                if (!empty($rentalName)) {
                    try {
                        $stmt = $pdo->prepare("SELECT id FROM liste_logements WHERE nom_du_logement = ? LIMIT 1");
                        $stmt->execute([$rentalName]);
                        $row_log = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($row_log) {
                            $logement_id = (int)$row_log['id'];
                        } elseif (preg_match('/(\d+)\s*$/', $rentalName, $m)) {
                            $logement_id = (int)$m[1];
                        }
                    } catch (PDOException $e) {}
                }

                $date_res  = $data['booking date'] ?? '';
                $date_in   = $data['checkin'] ?? '';
                $date_out  = $data['checkout'] ?? '';
                $prenom    = $data['guest first name'] ?? '';
                $nom       = $data['guest last name'] ?? '';
                $telephone = phone_normalize_import($data['guest phone number'] ?? '');
                $email     = $data['guest email'] ?? '';
                $adults    = (int)($data['nbr adults'] ?? 0);
                $children  = (int)($data['nbr children'] ?? 0);
                $ville     = $data['city'] ?? '';
                $cp        = $data['post code'] ?? '';

                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO reservation (
                            reference, client_id, logement_id,
                            date_reservation, date_arrivee, heure_arrivee,
                            date_depart, heure_depart, statut,
                            prenom, nom, telephone, email,
                            nb_adultes, nb_enfants, nb_bebes,
                            ville, code_postal,
                            j1_sent, dep_sent, start_sent, scenario_state
                        ) VALUES (
                            :reference, NULL, :logement_id,
                            :date_res, :date_in, '',
                            :date_out, '', 'confirmée',
                            :prenom, :nom, :telephone, :email,
                            :adults, :children, 0,
                            :ville, :cp,
                            0, 0, 0, 0
                        )
                    ");
                    $stmt->execute([
                        ':reference'   => $reference,
                        ':logement_id' => $logement_id,
                        ':date_res'    => $date_res,
                        ':date_in'     => $date_in,
                        ':date_out'    => $date_out,
                        ':prenom'      => $prenom,
                        ':nom'         => $nom,
                        ':telephone'   => $telephone,
                        ':email'       => $email,
                        ':adults'      => $adults,
                        ':children'    => $children,
                        ':ville'       => $ville,
                        ':cp'          => $cp
                    ]);
                    $importees++;
                } catch (PDOException $e) {
                    error_log("Import CSV - Erreur insert ref=$reference: " . $e->getMessage());
                    $errors[] = "ref=$reference : " . $e->getMessage();
                    $ignorees++;
                }
            }
            fclose($handle);

            $feedback = '<div class="alert alert-info alert-dismissible fade show">';
            $feedback .= "<strong>$importees</strong> reservation(s) importee(s), <strong>$ignorees</strong> ignoree(s).";
            if (!empty($errors)) {
                $feedback .= '<br><small class="text-danger">' . count($errors) . ' erreur(s) : ' . htmlspecialchars(implode(' | ', array_slice($errors, 0, 3))) . '</small>';
            }
            $feedback .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import CSV Reservations — FrenchyConciergerie</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-file-import text-primary"></i> Import CSV Reservations</h2>
            <p class="text-muted">Importez des reservations depuis un fichier CSV (export Superhote ou OTA)</p>
        </div>
    </div>

    <?= $feedback ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-upload"></i> Importer un fichier</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?php echoCsrfField(); ?>
                        <div class="mb-3">
                            <label for="fichier" class="form-label">Fichier CSV</label>
                            <input type="file" name="fichier" id="fichier" class="form-control" accept=".csv" required>
                            <div class="form-text">Format CSV avec les colonnes : id, status, rental, booking date, checkin, checkout, guest first name, guest last name, guest phone number, guest email, nbr adults, nbr children, city, post code</div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-file-import"></i> Importer
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Format attendu</h5>
                </div>
                <div class="card-body">
                    <p>Le fichier CSV doit contenir les colonnes suivantes :</p>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr><th>Colonne</th><th>Description</th><th>Requis</th></tr>
                        </thead>
                        <tbody>
                            <tr><td><code>id</code></td><td>Reference de la reservation</td><td><span class="badge bg-danger">Oui</span></td></tr>
                            <tr><td><code>status</code></td><td>Statut (seules les "confirmed" sont importees)</td><td><span class="badge bg-danger">Oui</span></td></tr>
                            <tr><td><code>rental</code></td><td>Nom du logement</td><td><span class="badge bg-warning text-dark">Recommande</span></td></tr>
                            <tr><td><code>booking date</code></td><td>Date de reservation</td><td><span class="badge bg-secondary">Non</span></td></tr>
                            <tr><td><code>checkin</code></td><td>Date d'arrivee</td><td><span class="badge bg-danger">Oui</span></td></tr>
                            <tr><td><code>checkout</code></td><td>Date de depart</td><td><span class="badge bg-danger">Oui</span></td></tr>
                            <tr><td><code>guest first name</code></td><td>Prenom du voyageur</td><td><span class="badge bg-secondary">Non</span></td></tr>
                            <tr><td><code>guest last name</code></td><td>Nom du voyageur</td><td><span class="badge bg-secondary">Non</span></td></tr>
                            <tr><td><code>guest phone number</code></td><td>Telephone</td><td><span class="badge bg-secondary">Non</span></td></tr>
                            <tr><td><code>guest email</code></td><td>Email</td><td><span class="badge bg-secondary">Non</span></td></tr>
                        </tbody>
                    </table>
                    <div class="alert alert-warning mb-0">
                        <small><i class="fas fa-exclamation-triangle"></i> Les doublons (meme reference) sont automatiquement ignores.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
