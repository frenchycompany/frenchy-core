<?php
// Partial pour afficher un tableau de réservations
// Attend une variable $reservations_to_display contenant les réservations à afficher
?>

<?php if (!empty($reservations_to_display)): ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="thead-light">
                <tr>
                    <th><i class="fas fa-hashtag"></i> Réf</th>
                    <th><i class="fas fa-user"></i> Client</th>
                    <th><i class="fas fa-phone"></i> Téléphone</th>
                    <th><i class="fas fa-home"></i> Logement</th>
                    <th><i class="fas fa-calendar-plus"></i> Arrivée</th>
                    <th><i class="fas fa-calendar-minus"></i> Départ</th>
                    <th><i class="fas fa-moon"></i> Nuits</th>
                    <th><i class="fas fa-globe"></i> Plateforme</th>
                    <th><i class="fas fa-info-circle"></i> Statut</th>
                    <th><i class="fas fa-cog"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservations_to_display as $res): ?>
                    <?php
                    // Déterminer la classe de statut
                    $statut_class = 'secondary';
                    $statut_icon = 'circle';
                    $statut_text = $res['statut'] ?? 'confirmée';

                    switch (strtolower($statut_text)) {
                        case 'confirmée':
                        case 'confirmed':
                            $statut_class = 'success';
                            $statut_icon = 'check-circle';
                            break;
                        case 'annulée':
                        case 'cancelled':
                            $statut_class = 'danger';
                            $statut_icon = 'times-circle';
                            break;
                        case 'en attente':
                        case 'pending':
                            $statut_class = 'warning';
                            $statut_icon = 'clock';
                            break;
                    }

                    // Déterminer si la réservation est passée, en cours ou future
                    $today = date('Y-m-d');
                    $row_class = '';
                    $date_badge = '';

                    if ($res['date_depart'] < $today) {
                        $row_class = 'table-secondary'; // Passée
                    } elseif ($res['date_arrivee'] <= $today && $res['date_depart'] >= $today) {
                        $row_class = 'table-success'; // En cours
                        $date_badge = '<span class="badge badge-success ml-2">En cours</span>';
                    } elseif ($res['jours_avant_arrivee'] <= 7 && $res['jours_avant_arrivee'] > 0) {
                        $row_class = 'table-warning'; // Bientôt
                        $date_badge = '<span class="badge badge-warning ml-2">Dans ' . $res['jours_avant_arrivee'] . ' jour(s)</span>';
                    }
                    ?>
                    <tr class="<?= $row_class ?>">
                        <td>
                            <small class="text-muted"><?= htmlspecialchars(substr($res['reference'] ?? 'N/A', 0, 12)) ?></small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($res['prenom'] ?? '') ?></strong>
                            <?= htmlspecialchars($res['nom'] ?? '') ?>
                            <?php if (!empty($res['ville'])): ?>
                                <br><small class="text-muted">
                                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($res['ville']) ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($res['telephone'])): ?>
                                <a href="tel:<?= htmlspecialchars($res['telephone']) ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($res['telephone']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($res['nom_du_logement'])): ?>
                                <span class="badge badge-info">
                                    <?= htmlspecialchars($res['nom_du_logement']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Non assigné</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap">
                            <?= !empty($res['date_arrivee']) ? date('d/m/Y', strtotime($res['date_arrivee'])) : '-' ?>
                            <?= $date_badge ?>
                        </td>
                        <td class="text-nowrap">
                            <?= !empty($res['date_depart']) ? date('d/m/Y', strtotime($res['date_depart'])) : '-' ?>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-secondary">
                                <?= $res['duree_sejour'] ?? 0 ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($res['plateforme'])): ?>
                                <small class="badge badge-light">
                                    <?= htmlspecialchars($res['plateforme']) ?>
                                </small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $statut_class ?>">
                                <i class="fas fa-<?= $statut_icon ?>"></i>
                                <?= htmlspecialchars($statut_text) ?>
                            </span>
                        </td>
                        <td class="text-nowrap">
                            <a href="reservation_details.php?id=<?= $res['id'] ?>"
                               class="btn btn-sm btn-info"
                               title="Voir détails">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if (!empty($res['telephone'])): ?>
                                <button class="btn btn-sm btn-primary"
                                        onclick="openSmsModal('<?= htmlspecialchars($res['telephone']) ?>', '<?= htmlspecialchars($res['prenom'] ?? '') ?>')"
                                        title="Envoyer SMS">
                                    <i class="fas fa-sms"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Légende des couleurs -->
    <div class="mt-3">
        <small class="text-muted">
            <i class="fas fa-info-circle"></i> Légende:
            <span class="badge badge-success ml-2">En cours</span>
            <span class="badge badge-warning ml-2">Arrivée dans 7 jours</span>
            <span class="badge badge-secondary ml-2">Passée</span>
        </small>
    </div>
<?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
        <h4 class="text-muted">Aucune réservation trouvée</h4>
        <p class="text-muted">Modifiez vos filtres pour voir plus de résultats</p>
    </div>
<?php endif; ?>

<!-- Modal pour envoyer un SMS (réutilisable) -->
<div class="modal fade" id="smsQuickModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-paper-plane"></i> Envoyer un SMS rapide
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="smsQuickForm">
                <div class="modal-body">
                    <div id="sms-quick-alert" class="alert" style="display: none;"></div>

                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Destinataire</label>
                        <input type="text"
                               class="form-control"
                               id="sms_quick_receiver"
                               name="receiver"
                               readonly
                               required>
                        <small id="sms_quick_name" class="text-muted"></small>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-comment-dots"></i> Message</label>
                        <textarea class="form-control"
                                  id="sms_quick_message"
                                  name="message"
                                  rows="5"
                                  maxlength="160"
                                  required></textarea>
                        <small id="sms_quick_counter" class="form-text text-muted">0/160 caractères</small>
                    </div>

                    <input type="hidden" name="modem" value="/dev/ttyUSB0">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-primary" id="sms-quick-send-btn">
                        <i class="fas fa-paper-plane"></i> Envoyer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Fonction pour ouvrir le modal SMS
function openSmsModal(phone, name) {
    document.getElementById('sms_quick_receiver').value = phone;
    document.getElementById('sms_quick_name').textContent = name ? 'Client: ' + name : '';
    document.getElementById('sms_quick_message').value = '';
    document.getElementById('sms_quick_counter').textContent = '0/160 caractères';
    document.getElementById('sms-quick-alert').style.display = 'none';
    $('#smsQuickModal').modal('show');
}

// Compteur de caractères
document.getElementById('sms_quick_message').addEventListener('input', function() {
    const count = this.value.length;
    const counter = document.getElementById('sms_quick_counter');
    counter.textContent = count + '/160 caractères';

    if (count > 160) {
        counter.classList.add('text-danger');
    } else {
        counter.classList.remove('text-danger');
    }
});

// Soumission AJAX
document.getElementById('smsQuickForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const alertDiv = document.getElementById('sms-quick-alert');
    const sendBtn = document.getElementById('sms-quick-send-btn');

    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';

    fetch('send_sms_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alertDiv.style.display = 'block';
        alertDiv.className = 'alert ' + (data.success ? 'alert-success' : 'alert-danger');
        alertDiv.innerHTML = '<i class="fas fa-' + (data.success ? 'check' : 'exclamation') + '-circle"></i> ' + data.message;

        if (data.success) {
            setTimeout(() => {
                $('#smsQuickModal').modal('hide');
            }, 2000);
        }

        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer';
    })
    .catch(error => {
        alertDiv.style.display = 'block';
        alertDiv.className = 'alert alert-danger';
        alertDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Erreur de connexion';

        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer';
    });
});
</script>
