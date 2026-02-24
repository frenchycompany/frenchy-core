<?php
// reservation_list.php (Version Finale Stable)

// Affiche toutes les erreurs PHP à l'écran.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclusion des fichiers nécessaires
include '../includes/db.php';
include '../includes/header.php';

$feedback = '';
$db_connection_error = false;
$checkoutResult = $checkinResult = $prepResult = null;

// ======================================================================
// VÉRIFICATION DE LA CONNEXION À LA BASE DE DONNÉES
// Empêche les erreurs 500 si la connexion échoue.
// ======================================================================
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
    $db_connection_error = true;
    $error_message = 'La variable $conn n\'existe pas ou n\'est pas un objet mysqli valide.';
    if (isset($conn) && $conn->connect_error) {
        $error_message = htmlspecialchars($conn->connect_error);
    }
    // On définit un message d'erreur clair qui sera affiché à l'utilisateur.
    $feedback = "<div class='alert alert-danger'>
                    <strong>Erreur Critique :</strong> Impossible de se connecter à la base de données.<br>
                    Veuillez vérifier le fichier <code>../includes/db.php</code>.<br>
                    <small>Détail technique : " . $error_message . "</small>
                 </div>";
} else {
    // --- Le reste du script ne s'exécute QUE si la connexion est OK ---

    // Traitement de l'envoi de SMS via le formulaire POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receiver'], $_POST['prenom'])) {
        
        $receiver = $_POST['receiver'];
        $prenom = $_POST['prenom'];
        $tplName = '';

        if (isset($_POST['send_sms_checkout'])) { $tplName = 'checkout'; } 
        elseif (isset($_POST['send_sms_accueil'])) { $tplName = 'accueil'; } 
        elseif (isset($_POST['send_sms_preparation'])) { $tplName = 'preparation'; }

        if (!empty($tplName)) {
            // ETAPE 1 : Récupérer le modèle de SMS
            $stmt_template = $conn->prepare("SELECT template FROM sms_templates WHERE campaign = 'accueil' AND name = ? LIMIT 1");
            $stmt_template->bind_param('s', $tplName);
            $stmt_template->execute();
            $result_template = $stmt_template->get_result();

            if ($result_template && $result_template->num_rows > 0) {
                $template_row = $result_template->fetch_assoc();
                $message = str_replace('{prenom}', $prenom, $template_row['template']);

                // ETAPE 2 : Insérer le SMS dans la base de données (TRANSACTION)
                $conn->begin_transaction();
                try {
                    $creator_id = 'PHP_WebApp';
                    $stmt_outbox = $conn->prepare(
                        "INSERT INTO outbox (DestinationNumber, TextDecoded, CreatorID) VALUES (?, ?, ?)"
                    );
                    $stmt_outbox->bind_param('sss', $receiver, $message, $creator_id);
                    $stmt_outbox->execute();

                    $stmt_satisfaction = $conn->prepare("INSERT INTO satisfaction_conversations(sender, role, message) VALUES (?, 'assistant', ?)");
                    $stmt_satisfaction->bind_param('ss', $receiver, $message);
                    $stmt_satisfaction->execute();

                    $conn->commit();
                    $feedback = "<div class='alert alert-success'>SMS de type “{$tplName}” mis en file d'attente pour {$prenom}.</div>";
                } catch (mysqli_sql_exception $exception) {
                    $conn->rollback();
                    $feedback = "<div class='alert alert-danger'>Erreur SQL: " . htmlspecialchars($exception->getMessage()) . "</div>";
                }
            } else {
                $feedback = "<div class='alert alert-danger'>Modèle de SMS “" . htmlspecialchars($tplName) . "” introuvable.</div>";
            }
        }
    }

    // --- Fonctions et Récupération des Données pour l'Affichage ---
    function prepareList($db_conn, $field, $date) {
        $sql = "SELECT l.nom_du_logement AS logement, r.prenom, r.nom, r.plateforme, r.telephone AS mobile FROM reservation r LEFT JOIN liste_logements l ON r.logement_id = l.id WHERE r.{$field} = ? ORDER BY r.created_at DESC";
        $stmt = $db_conn->prepare($sql);
        $stmt->bind_param('s', $date);
        $stmt->execute();
        return $stmt->get_result();
    }

    $today   = date('Y-m-d');
    $in4Days = date('Y-m-d', strtotime('+4 days'));

    $checkoutResult = prepareList($conn, 'date_depart', $today);
    $checkinResult  = prepareList($conn, 'date_arrivee', $today);
    $prepResult     = prepareList($conn, 'date_arrivee', $in4Days);
}

function renderSection($res, $buttonName, $label, $dateLabel) {
    if ($res === null) { return; }
    ?>
    <h2><?= htmlspecialchars($label) ?> (<?= htmlspecialchars($dateLabel) ?>)</h2>
    <div class="table-responsive">
      <table class="table table-bordered table-hover">
        <thead class="thead-dark text-center"><tr><th>Logement</th><th>Client</th><th>Plateforme</th><th>Mobile</th><th>Action</th></tr></thead>
        <tbody class="text-center">
          <?php if ($res->num_rows > 0): ?>
            <?php while ($row = $res->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['logement'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? '')) ?></td>
                <td><?= htmlspecialchars($row['plateforme'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($row['mobile'] ?? 'N/A') ?></td>
                <td>
                  <?php if (!empty($row['mobile']) && !empty($row['prenom'])): ?>
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="receiver" value="<?= htmlspecialchars($row['mobile']) ?>">
                    <input type="hidden" name="prenom" value="<?= htmlspecialchars($row['prenom']) ?>">
                    <button type="submit" name="<?= htmlspecialchars($buttonName) ?>" class="btn btn-sm btn-warning">Envoyer</button>
                  </form>
                  <?php else: echo '—'; endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5">Aucune réservation.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Réservations & SMS</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
  <h1 class="text-center mb-4">Gestion des SMS</h1>
  
  <?php if (!empty($feedback)): ?>
    <?= $feedback ?>
  <?php endif; ?>

  <?php if (!$db_connection_error): ?>
    <?php
      renderSection($checkoutResult, 'send_sms_checkout', 'Check-out du jour', $today);
      renderSection($checkinResult,  'send_sms_accueil', 'Check-in du jour', $today);
      renderSection($prepResult,     'send_sms_preparation','Arrivées dans 4 jours', $in4Days);
    ?>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
