<?php
require_once '../includes/error_handler.php';  // Gestion centralisée des erreurs
require_once '../includes/db.php';
require_once '../includes/phone.php';  // Fonction de normalisation téléphone centralisée

// Alias pour compatibilité
function formatPhoneNumber($phone) {
    return normalize_phone($phone);
}

$feedback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fichier'])) {
    $fichier = $_FILES['fichier']['tmp_name'];

    if (!file_exists($fichier)) {
        $feedback = "<div class='alert alert-danger'>Fichier introuvable.</div>";
    } else {
        $handle = fopen($fichier, 'r');
        $header = fgetcsv($handle, 1000, ',');
        if (!$header) {
            $feedback = "<div class='alert alert-danger'>Impossible de lire l'en-tête du fichier CSV.</div>";
        } else {
            $importees = 0;
            $ignorees = 0;

            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $row = array_map('trim', $row);
                while (count($row) > count($header) && end($row) === "") {
                    array_pop($row);
                }
                if (count($row) !== count($header)) {
                    $ignorees++;
                    continue;
                }

                $data = array_combine($header, $row);

                // On n'importe que les "confirmée"
                if (!isset($data['status']) ||
                    !in_array(strtolower($data['status']), ['confirmée','confirmed'], true)) {
                    $ignorees++;
                    continue;
                }

                $reference = $data['id'] ?? '';
                // doublon ? - Utilisation de requête préparée
                $stmt_dup = $conn->prepare("SELECT id FROM reservation WHERE reference = ?");
                $stmt_dup->bind_param('s', $reference);
                $stmt_dup->execute();
                $dup = $stmt_dup->get_result();
                if ($dup && $dup->num_rows) {
                    $stmt_dup->close();
                    $ignorees++;
                    continue;
                }
                $stmt_dup->close();

                // Récupérer le nom du logement depuis le CSV (champ 'rental')
                $rentalName = $data['rental'] ?? '';
                // Chercher son ID dans liste_logements - Utilisation de requête préparée
                $stmt_log = $conn->prepare("
                    SELECT id
                    FROM liste_logements
                    WHERE nom_du_logement = ?
                    LIMIT 1
                ");
                $stmt_log->bind_param('s', $rentalName);
                $stmt_log->execute();
                $resLog = $stmt_log->get_result();
                if ($resLog && $resLog->num_rows === 1) {
                    $logement_id = (int)$resLog->fetch_assoc()['id'];
                } else {
                    // on peut tenter d'extraire un ID numérique en suffixe, sinon NULL
                    if (preg_match('/(\d+)\s*$/', $data['rental'], $m)) {
                        $logement_id = (int)$m[1];
                    } else {
                        $logement_id = null;
                    }
                }
                $stmt_log->close();
                $logement_value = is_null($logement_id) ? "NULL" : $logement_id;

                // Les autres champs (sans échappement - utilisation de requête préparée)
                $date_res     = $data['booking date'] ?? '';
                $date_in      = $data['checkin'] ?? '';
                $date_out     = $data['checkout'] ?? '';
                $heure_in     = '';
                $heure_out    = '';
                $prenom       = $data['guest first name'] ?? '';
                $nom          = $data['guest last name'] ?? '';
                $telephone    = formatPhoneNumber($data['guest phone number'] ?? '');
                $email        = $data['guest email'] ?? '';
                $adults       = (int)($data['nbr adults'] ?? 0);
                $children     = (int)($data['nbr children'] ?? 0);
                $ville        = $data['city'] ?? '';
                $cp           = $data['post code'] ?? '';

                // Insertion avec requête préparée
                $stmt_insert = $conn->prepare("
                  INSERT INTO reservation (
                    reference, client_id, logement_id,
                    date_reservation, date_arrivee, heure_arrivee,
                    date_depart, heure_depart, statut,
                    prenom, nom, telephone, email,
                    nb_adultes, nb_enfants, nb_bebes,
                    ville, code_postal,
                    j1_sent, dep_sent, start_sent, scenario_state
                  ) VALUES (
                    ?, NULL, ?,
                    ?, ?, ?,
                    ?, ?, 'confirmée',
                    ?, ?, ?, ?,
                    ?, ?, 0,
                    ?, ?,
                    0, 0, 0, 0
                  )");
                $stmt_insert->bind_param('sisssssssssiiss',
                    $reference, $logement_id,
                    $date_res, $date_in, $heure_in,
                    $date_out, $heure_out,
                    $prenom, $nom, $telephone, $email,
                    $adults, $children,
                    $ville, $cp
                );
                if ($stmt_insert->execute()) {
                    $importees++;
                } else {
                    error_log("Erreur insert ref={$reference}: " . $stmt_insert->error);
                }
                $stmt_insert->close();
                    $ignorees++;
                }
            }
            fclose($handle);
            $feedback = "<div class='alert alert-info'>
                <strong>$importees</strong> résas importées, <strong>$ignorees</strong> ignorées.
            </div>";
        }
    }
}
?>

<?php include '../includes/header.php'; ?>
<div class="container mt-4">
  <h3>Import CSV Réservations</h3>
  <form method="post" enctype="multipart/form-data">
    <div class="form-group">
      <label for="fichier">Fichier CSV</label>
      <input type="file" name="fichier" id="fichier" class="form-control-file" required>
    </div>
    <button class="btn btn-primary">Importer</button>
  </form>
  <div class="mt-3"><?= $feedback ?></div>
</div>
<?php include '../includes/footer.php'; ?>
