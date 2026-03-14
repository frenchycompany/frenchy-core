<?php
// Afficher les erreurs pour le débogage

include '../includes/db.php';
include '../includes/header.php';

// --- Traitement de l'envoi de SMS ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $receiver = trim($_POST["receiver"] ?? '');
    $message  = trim($_POST["message"] ?? '');
    $modem    = trim($_POST["modem"] ?? '');

    if ($receiver !== '' && $message !== '' && $modem !== '') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO sms_outbox (receiver, message, modem, status)
                VALUES (:receiver, :message, :modem, 'pending')
            ");
            $stmt->execute([
                ':receiver' => $receiver,
                ':message'  => $message,
                ':modem'    => $modem
            ]);

            echo "<div class='alert alert-success'><i class='fas fa-check-circle'></i> SMS mis en file d'attente pour envoi.</div>";
        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Tous les champs sont obligatoires.</div>";
    }
}

// --- Récupérer la liste des modems disponibles ---
try {
    $stmt_modems = $pdo->query("SELECT DISTINCT modem FROM sms_in");
    $modems = $stmt_modems->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $modems = [];
}
?>

<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <!-- En-tête -->
        <div class="text-center mb-4">
            <h2 class="text-gradient-primary">
                <i class="fas fa-paper-plane"></i> Envoyer un SMS
            </h2>
            <p class="text-muted">Rédigez et envoyez un SMS à vos clients</p>
        </div>

        <!-- Formulaire d'envoi -->
        <div class="card shadow-custom">
            <div class="card-body p-4">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="receiver">
                            <i class="fas fa-phone"></i> Numéro du destinataire
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-mobile-alt"></i>
                                </span>
                            </div>
                            <input type="text"
                                   name="receiver"
                                   id="receiver"
                                   class="form-control"
                                   placeholder="Ex: 0612345678 ou +33612345678"
                                   required>
                        </div>
                        <small class="form-text text-muted">
                            Format: 0612345678 ou +33612345678
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="message">
                            <i class="fas fa-comment-dots"></i> Message
                        </label>
                        <textarea name="message"
                                  id="message"
                                  class="form-control"
                                  rows="5"
                                  placeholder="Saisissez votre message ici..."
                                  maxlength="160"
                                  required></textarea>
                        <small id="message-counter" class="form-text text-muted">
                            0/160 caractères
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="modem">
                            <i class="fas fa-sim-card"></i> Sélectionner le modem
                        </label>
                        <select name="modem" id="modem" class="form-control" required>
                            <?php if (!empty($modems)): ?>
                                <?php foreach ($modems as $m): ?>
                                    <option value="<?= htmlspecialchars($m) ?>">
                                        <?= htmlspecialchars($m) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="/dev/ttyUSB0">/dev/ttyUSB0</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-paper-plane"></i> Envoyer le SMS
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Informations supplémentaires -->
        <div class="card mt-4 border-info">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-info-circle text-info"></i> Informations
                </h5>
                <ul class="mb-0">
                    <li>La limite d'un SMS est de <strong>160 caractères</strong></li>
                    <li>Les numéros français doivent commencer par <strong>0</strong> ou <strong>+33</strong></li>
                    <li>Le SMS sera mis en file d'attente pour envoi</li>
                    <li>Vous recevrez une confirmation une fois le SMS envoyé</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
