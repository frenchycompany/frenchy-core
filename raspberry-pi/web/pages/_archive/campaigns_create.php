<!-- Formulaire de création de campagne -->
<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php"><i class="fas fa-home"></i> Accueil</a></li>
            <li class="breadcrumb-item"><a href="campaigns.php"><i class="fas fa-bullhorn"></i> Campagnes</a></li>
            <li class="breadcrumb-item active">Nouvelle campagne</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <h1 class="text-gradient-primary mb-4">
                <i class="fas fa-plus-circle"></i> Créer une nouvelle campagne
            </h1>

            <?php if ($feedback): ?>
                <?= $feedback ?>
            <?php endif; ?>

            <div class="card shadow-custom">
                <div class="card-body p-4">
                    <form method="POST" action="">
                        <!-- Informations de base -->
                        <div class="form-group">
                            <label for="nom">
                                <i class="fas fa-tag"></i> Nom de la campagne <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="nom"
                                   name="nom"
                                   placeholder="Ex: Relance clients été 2025"
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
                                      rows="3"
                                      placeholder="Décrivez l'objectif de cette campagne..."></textarea>
                        </div>

                        <!-- Ciblage -->
                        <hr>
                        <h4 class="mb-3"><i class="fas fa-crosshairs"></i> Ciblage</h4>

                        <div class="form-group">
                            <label for="logement_id">
                                <i class="fas fa-home"></i> Logement spécifique
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
                                Sélectionnez un logement pour cibler uniquement ses clients
                            </small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="date_debut">
                                        <i class="fas fa-calendar-alt"></i> Date de début (séjour)
                                    </label>
                                    <input type="date"
                                           class="form-control"
                                           id="date_debut"
                                           name="date_debut">
                                    <small class="form-text text-muted">
                                        Clients ayant séjourné à partir de cette date
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="date_fin">
                                        <i class="fas fa-calendar-alt"></i> Date de fin (séjour)
                                    </label>
                                    <input type="date"
                                           class="form-control"
                                           id="date_fin"
                                           name="date_fin">
                                    <small class="form-text text-muted">
                                        Clients ayant séjourné jusqu'à cette date
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Message -->
                        <hr>
                        <h4 class="mb-3"><i class="fas fa-comment-dots"></i> Message</h4>

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

Nous espérons que vous avez passé un excellent séjour!
N'hésitez pas à revenir nous voir..."></textarea>
                            <small id="message-counter" class="form-text text-muted">
                                0/320 caractères (2 SMS maximum)
                            </small>
                            <div class="alert alert-info mt-2 mb-0">
                                <strong><i class="fas fa-info-circle"></i> Variables disponibles:</strong>
                                <ul class="mb-0 mt-2">
                                    <li><code>{prenom}</code> - Prénom du client</li>
                                    <li><code>{nom}</code> - Nom du client</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Boutons -->
                        <div class="text-center mt-4">
                            <a href="campaigns.php" class="btn btn-secondary btn-lg px-5">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="submit" name="create_campaign" class="btn btn-primary btn-lg px-5">
                                <i class="fas fa-save"></i> Créer la campagne
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Aide -->
            <div class="card mt-4 border-info">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-lightbulb text-info"></i> Conseils
                    </h5>
                    <ul class="mb-0">
                        <li>Personnalisez votre message avec les variables {prenom} et {nom}</li>
                        <li>Un SMS standard fait 160 caractères, vous pouvez aller jusqu'à 320 (2 SMS)</li>
                        <li>Après création, vous pourrez sélectionner les destinataires et envoyer la campagne</li>
                        <li>Les clients sans numéro de téléphone seront automatiquement exclus</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Compteur de caractères pour le message
document.getElementById('message').addEventListener('input', function() {
    const count = this.value.length;
    const counter = document.getElementById('message-counter');
    const smsCount = Math.ceil(count / 160);

    counter.textContent = count + '/320 caractères (' + smsCount + ' SMS)';

    if (count > 320) {
        counter.classList.add('text-danger');
        counter.classList.remove('text-muted');
    } else if (count > 160) {
        counter.classList.add('text-warning');
        counter.classList.remove('text-muted', 'text-danger');
    } else {
        counter.classList.add('text-muted');
        counter.classList.remove('text-warning', 'text-danger');
    }
});
</script>
