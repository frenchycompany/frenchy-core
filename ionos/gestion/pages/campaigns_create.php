<?php
/**
 * Formulaire de creation de campagne (inclus par campaigns.php)
 */
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Nouvelle campagne</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="campaigns.php">
                    <?php echoCsrfField(); ?>

                    <!-- Informations de base -->
                    <div class="form-group">
                        <label for="nom">
                            <i class="fas fa-tag"></i> Nom de la campagne <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="nom"
                               name="nom"
                               placeholder="Ex: Relance clients ete 2025"
                               required>
                        <small class="form-text text-muted">
                            Choisissez un nom descriptif pour identifier votre campagne
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="description">
                            <i class="fas fa-align-left"></i> Description
                        </label>
                        <textarea class="form-control"
                                  id="description"
                                  name="description"
                                  rows="2"
                                  placeholder="Decrivez l'objectif de cette campagne..."></textarea>
                    </div>

                    <!-- Ciblage -->
                    <hr>
                    <h5 class="mb-3"><i class="fas fa-crosshairs"></i> Ciblage</h5>

                    <div class="form-group">
                        <label for="logement_id">
                            <i class="fas fa-home"></i> Logement specifique
                        </label>
                        <select class="form-control" id="logement_id" name="logement_id">
                            <option value="">Tous les logements</option>
                            <?php foreach ($logements as $logement): ?>
                                <option value="<?= $logement['id'] ?>">
                                    <?= htmlspecialchars($logement['nom_du_logement']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">
                            Selectionnez un logement pour cibler uniquement ses clients
                        </small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="date_debut">
                                    <i class="fas fa-calendar-alt"></i> Sejour a partir du
                                </label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut">
                                <small class="form-text text-muted">
                                    Clients ayant sejourne a partir de cette date
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="date_fin">
                                    <i class="fas fa-calendar-alt"></i> Sejour jusqu'au
                                </label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin">
                                <small class="form-text text-muted">
                                    Clients ayant sejourne jusqu'a cette date
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Message -->
                    <hr>
                    <h5 class="mb-3"><i class="fas fa-comment-dots"></i> Message</h5>

                    <div class="form-group">
                        <label for="message">
                            <i class="fas fa-sms"></i> Contenu du SMS <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control"
                                  id="message"
                                  name="message"
                                  rows="6"
                                  maxlength="320"
                                  required
                                  placeholder="Bonjour {prenom},

Nous esperons que vous avez passe un excellent sejour!
N'hesitez pas a revenir nous voir..."></textarea>
                        <small id="message-counter" class="form-text text-muted">
                            0/320 caracteres (2 SMS maximum)
                        </small>
                    </div>

                    <div class="alert alert-info">
                        <strong><i class="fas fa-info-circle"></i> Variables disponibles:</strong>
                        <ul class="mb-0 mt-2">
                            <li><code>{prenom}</code> - Prenom du client</li>
                            <li><code>{nom}</code> - Nom du client</li>
                        </ul>
                    </div>

                    <!-- Boutons -->
                    <div class="text-center mt-4">
                        <a href="campaigns.php" class="btn btn-secondary px-4">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                        <button type="submit" name="create_campaign" class="btn btn-primary px-4">
                            <i class="fas fa-save"></i> Creer la campagne
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Aide -->
        <div class="card shadow-sm border-left-info">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-lightbulb text-info"></i> Conseils
                </h5>
                <ul class="mb-0">
                    <li class="mb-2">Personnalisez votre message avec les variables <code>{prenom}</code> et <code>{nom}</code></li>
                    <li class="mb-2">Un SMS standard fait 160 caracteres, vous pouvez aller jusqu'a 320 (2 SMS)</li>
                    <li class="mb-2">Apres creation, vous pourrez selectionner les destinataires et envoyer la campagne</li>
                    <li>Les clients sans numero de telephone seront automatiquement exclus</li>
                </ul>
            </div>
        </div>

        <!-- Exemple -->
        <div class="card shadow-sm mt-4 border-left-success">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-file-alt text-success"></i> Exemple de message
                </h5>
                <div class="bg-light p-3 rounded">
                    <small class="text-dark">
                        Bonjour {prenom},<br><br>
                        Nous esperons que votre sejour a Compiegne vous a plu! Avez-vous des projets de revenir dans la region prochainement?<br><br>
                        A bientot!
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Compteur de caracteres pour le message
document.getElementById('message').addEventListener('input', function() {
    const count = this.value.length;
    const counter = document.getElementById('message-counter');
    const smsCount = Math.ceil(count / 160) || 1;

    counter.textContent = count + '/320 caracteres (' + smsCount + ' SMS)';

    if (count > 320) {
        counter.classList.add('text-danger');
        counter.classList.remove('text-muted', 'text-warning');
    } else if (count > 160) {
        counter.classList.add('text-warning');
        counter.classList.remove('text-muted', 'text-danger');
    } else {
        counter.classList.add('text-muted');
        counter.classList.remove('text-warning', 'text-danger');
    }
});
</script>
