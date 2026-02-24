<?php
// reservation_list.php (version PDO)
require_once __DIR__ . '/../includes/error_handler.php';  // Gestion centralisée des erreurs
require_once __DIR__ . '/../includes/db.php';   // doit fournir $pdo (PDO)
require_once __DIR__ . '/../includes/header_minimal.php';
require_once __DIR__ . '/../includes/template_helper.php';  // Helper pour templates avec fallback
require_once __DIR__ . '/../includes/csrf.php';  // Protection CSRF

if (!($pdo instanceof PDO)) {
    die('Erreur: PDO non disponible. Vérifiez la connexion à la base de données.');
}
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$default_modem = '/dev/ttyUSB0';
$feedback      = '';

// --- Traitement du formulaire d'envoi (PDO) ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    !empty($_POST['receiver']) &&
    !empty($_POST['prenom']) &&
    !empty($_POST['res_id'])
) {
    // Vérification CSRF
    validateCsrfToken();
    $receiver = $_POST['receiver'];
    $prenom   = $_POST['prenom'];
    $nom      = $_POST['nom'] ?? '';
    $resId    = (int)$_POST['res_id'];

    // Récupérer le logement_id de la réservation
    $logement_id = null;
    try {
        $stmt = $pdo->prepare("SELECT logement_id FROM reservation WHERE id = :id");
        $stmt->execute([':id' => $resId]);
        $logement_id = $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Continuer sans logement_id
    }

    // Déterminer le modèle et le champ drapeau
    if (isset($_POST['send_sms_checkout'])) {
        $tplName   = 'checkout';
        $flagField = 'dep_sent';
    } elseif (isset($_POST['send_sms_accueil'])) {
        $tplName   = 'accueil';
        $flagField = 'j1_sent';
    } elseif (isset($_POST['send_sms_preparation'])) {
        $tplName   = 'preparation';
        $flagField = 'start_sent';
    } else {
        $tplName = $flagField = '';
    }

    if ($tplName !== '') {
        // Utiliser le helper pour obtenir le template approprié (avec fallback automatique)
        $message = get_personalized_sms($pdo, $tplName, [
            'prenom' => $prenom,
            'nom' => $nom
        ], $logement_id);

        if ($message !== null) {
            $sentAt  = date('Y-m-d H:i:s');

            try {
                $pdo->beginTransaction();

            // 1) File d'attente SMS (sms_outbox.sent_at)
            $stmt1 = $pdo->prepare("
                INSERT INTO sms_outbox(receiver, message, modem, status, sent_at)
                VALUES (:receiver, :message, :modem, 'pending', :sent_at)
            ");
            $stmt1->execute([
                ':receiver' => $receiver,
                ':message'  => $message,
                ':modem'    => $default_modem,
                ':sent_at'  => $sentAt,
            ]);

            // 2) Historique (facultatif)
            $stmt2 = $pdo->prepare("
                INSERT INTO satisfaction_conversations(sender, role, message)
                VALUES (:sender, 'assistant', :message)
            ");
            $stmt2->execute([
                ':sender'  => $receiver,
                ':message' => $message,
            ]);

            // 3) Flag = 1 sur la réservation
            // Validation stricte du nom de colonne pour éviter l'injection SQL
            $allowedFields = ['dep_sent', 'j1_sent', 'start_sent'];
            if (!in_array($flagField, $allowedFields, true)) {
                throw new PDOException("Nom de champ non autorisé : $flagField");
            }
            $stmt3 = $pdo->prepare("
                UPDATE reservation
                   SET {$flagField} = 1
                 WHERE id = :id
            ");
            $stmt3->execute([':id' => $resId]);

                $pdo->commit();
                $feedback = "<div class='alert alert-success'>
                    SMS « " . htmlspecialchars($tplName) . " » mis en file d'attente pour " . htmlspecialchars($prenom) . ".
                </div>";
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $feedback = "<div class='alert alert-danger'>
                    Échec de l'envoi du SMS : " . htmlspecialchars($e->getMessage()) . "
                </div>";
            }
        } else {
            $feedback = "<div class='alert alert-warning'>
                Template non trouvé pour « " . htmlspecialchars($tplName) . " ».
            </div>";
        }
    }
}

// --- Préparation des listes avec drapeaux (PDO) ---
function prepareList(PDO $pdo, string $field, string $date): array {
    $sql = "
      SELECT r.id,
             r.prenom,
             r.nom,
             r.telephone AS mobile,
             r.logement_id,
             l.nom_du_logement AS logement,
             r.plateforme,
             r.dep_sent,
             r.j1_sent,
             r.start_sent
        FROM reservation r
        LEFT JOIN liste_logements l ON r.logement_id = l.id
       WHERE r.`{$field}` = :date
       ORDER BY r.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':date' => $date]);
    return $stmt->fetchAll();
}

$today   = date('Y-m-d');
$in4Days = date('Y-m-d', strtotime('+4 days'));

$checkoutRows = prepareList($pdo, 'date_depart',  $today);
$checkinRows  = prepareList($pdo, 'date_arrivee', $today);
$prepRows     = prepareList($pdo, 'date_arrivee', $in4Days);
?>

<div class="container mt-4">
  <!-- En-tête de la page -->
  <div class="text-center mb-5">
    <h1 class="display-4 text-gradient-primary">
      <i class="fas fa-calendar-check"></i> Réservations
    </h1>
    <p class="lead text-muted">Gestion des SMS automatiques pour vos réservations</p>
  </div>

  <?php if ($feedback): ?>
    <?= $feedback ?>
  <?php endif; ?>

  <?php
  /**
   * Affiche une section de liste en utilisant le drapeau réservé.
   */
  function renderSection(
      array $rows,
      string $buttonName,
      string $label,
      string $dateLabel,
      string $tplName,
      string $flagField
  ) {
  ?>
      <h2 class="mt-4"><?= htmlspecialchars($label) ?> (<?= htmlspecialchars($dateLabel) ?>)</h2>
      <div class="table-responsive">
        <table class="table table-bordered table-hover text-center">
          <thead class="thead-dark">
            <tr>
              <th>Logement</th>
              <th>Client</th>
              <th>Plateforme</th>
              <th>Mobile</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($rows) > 0): ?>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td>
                    <?php if (!empty($row['logement_id'])): ?>
                      <a href="logement_equipements.php?id=<?= (int)$row['logement_id'] ?>"
                         class="text-primary" title="Voir la fiche du logement">
                        <i class="fas fa-home"></i> <?= htmlspecialchars($row['logement']) ?>
                      </a>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? '')) ?></td>
                  <td><?= htmlspecialchars($row['plateforme'] ?? '') ?></td>
                  <td><?= htmlspecialchars($row['mobile'] ?? '') ?></td>
                  <td>
                    <a href="reservation_details.php?id=<?= (int)$row['id'] ?>"
                       class="btn btn-sm btn-info"
                       title="Voir les détails">
                      <i class="fas fa-eye"></i> Détails
                    </a>
                    <?php if (!empty($row['mobile'])): ?>
                      <?php if ((int)($row[$flagField] ?? 0) === 0): ?>
                        <form method="POST" style="display:inline" class="sms-send-form">
                          <?php echoCsrfField(); ?>
                          <input type="hidden" name="res_id"  value="<?= (int)$row['id'] ?>">
                          <input type="hidden" name="receiver" value="<?= htmlspecialchars($row['mobile']) ?>">
                          <input type="hidden" name="prenom"   value="<?= htmlspecialchars($row['prenom'] ?? '') ?>">
                          <input type="hidden" name="nom"   value="<?= htmlspecialchars($row['nom'] ?? '') ?>">
                          <button type="submit"
                                  name="<?= htmlspecialchars($buttonName) ?>"
                                  class="btn btn-sm btn-warning sms-send-btn">
                            <i class="fas fa-paper-plane"></i> Envoyer
                          </button>
                        </form>
                      <?php else: ?>
                        <span class="badge badge-success">
                          <i class="fas fa-check-circle"></i> Envoyé
                        </span>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="text-muted">
                        <i class="fas fa-phone-slash"></i> Aucun mobile
                      </span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="5">Aucune réservation.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
  <?php
  }

  renderSection($checkoutRows, 'send_sms_checkout',    'Check-out du jour',     $today,  'checkout',    'dep_sent');
  renderSection($checkinRows,  'send_sms_accueil',     'Check-in du jour',      $today,  'accueil',     'j1_sent');
  renderSection($prepRows,     'send_sms_preparation', 'Arrivées dans 4 jours', $in4Days,'preparation', 'start_sent');
  ?>
</div>

<!-- Script pour éviter les doubles clics -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sélectionner tous les formulaires d'envoi SMS
    const smsForms = document.querySelectorAll('.sms-send-form');

    smsForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.sms-send-btn');

            // Vérifier si le bouton est déjà désactivé
            if (submitBtn.disabled) {
                e.preventDefault();
                return false;
            }

            // IMPORTANT: Ajouter un input hidden pour garantir l'envoi du nom du bouton
            // (car modifier innerHTML peut empêcher la soumission de la valeur du bouton)
            const buttonName = submitBtn.getAttribute('name');
            if (buttonName) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = buttonName;
                hiddenInput.value = '1';
                this.appendChild(hiddenInput);
            }

            // Désactiver le bouton
            submitBtn.disabled = true;

            // Changer l'apparence et le texte
            submitBtn.classList.remove('btn-warning');
            submitBtn.classList.add('btn-secondary');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';

            // Optionnel : réactiver après 10 secondes si la page ne se recharge pas
            // (au cas où il y aurait une erreur réseau)
            setTimeout(() => {
                if (!submitBtn.disabled) return;
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-secondary');
                submitBtn.classList.add('btn-warning');
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer';
            }, 10000);
        });
    });

    // Empêcher les doubles clics sur tous les boutons d'envoi
    const sendButtons = document.querySelectorAll('.sms-send-btn');
    sendButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (this.disabled) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer_minimal.php'; ?>
