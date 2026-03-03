<?php
/**
 * Interface de gestion des comptes de plateformes de voyage
 * Permet de connecter Airbnb, Booking.com et les réservations directes
 */

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Comptes Plateformes</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .page-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }

        .page-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }

        .platform-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .platform-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .platform-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .platform-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .platform-logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
            border-radius: 8px;
            padding: 5px;
            background: #f8f9fa;
        }

        .platform-icon {
            font-size: 40px;
            color: #667eea;
        }

        .platform-name {
            font-size: 22px;
            font-weight: 600;
            margin: 0;
            color: #2d3748;
        }

        .btn-connect {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-connect:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .connection-list {
            margin-top: 15px;
        }

        .connection-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .connection-info {
            flex: 1;
        }

        .connection-name {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .connection-email {
            font-size: 14px;
            color: #718096;
        }

        .connection-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 10px;
        }

        .status-connected {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-error {
            background: #f8d7da;
            color: #721c24;
        }

        .connection-actions {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 35px;
            height: 35px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-test {
            background: #e3f2fd;
            color: #1976d2;
        }

        .btn-test:hover {
            background: #1976d2;
            color: white;
        }

        .btn-sync {
            background: #e8f5e9;
            color: #388e3c;
        }

        .btn-sync:hover {
            background: #388e3c;
            color: white;
        }

        .btn-edit {
            background: #fff3e0;
            color: #f57c00;
        }

        .btn-edit:hover {
            background: #f57c00;
            color: white;
        }

        .btn-delete {
            background: #ffebee;
            color: #c62828;
        }

        .btn-delete:hover {
            background: #c62828;
            color: white;
        }

        .modal-content {
            border-radius: 10px;
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0;
        }

        .form-control {
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            padding: 10px 15px;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .listings-count {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .alert-custom {
            border-radius: 8px;
            border: none;
            padding: 15px 20px;
        }

        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
            border-width: 0.15em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-plug"></i> Connexion des Plateformes de Réservation</h1>
            <p>Connectez vos comptes Airbnb, Booking.com et gérez vos réservations directes depuis une seule interface</p>
        </div>

        <div id="alert-container"></div>

        <div id="platforms-container">
            <!-- Les plateformes seront chargées dynamiquement ici -->
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Chargement...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour ajouter/modifier une connexion -->
    <div class="modal fade" id="connectionModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="connectionModalTitle">Ajouter une connexion</h5>
                    <button type="button" class="close text-white" data-bs-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="connectionForm">
                        <input type="hidden" id="connection_id" name="connection_id">
                        <input type="hidden" id="platform_id" name="platform_id">

                        <div class="form-group">
                            <label for="account_name">Nom du compte *</label>
                            <input type="text" class="form-control" id="account_name" name="account_name" required
                                   placeholder="Ex: Mon compte principal">
                        </div>

                        <div class="form-group">
                            <label for="account_email">Email du compte</label>
                            <input type="email" class="form-control" id="account_email" name="account_email"
                                   placeholder="email@example.com">
                        </div>

                        <div class="form-group" id="api_key_group">
                            <label for="api_key">Clé API</label>
                            <input type="text" class="form-control" id="api_key" name="api_key"
                                   placeholder="Votre clé API">
                            <small class="form-text text-muted">
                                Trouvez votre clé API dans les paramètres de votre compte plateforme
                            </small>
                        </div>

                        <div class="form-group" id="api_secret_group">
                            <label for="api_secret">Secret API</label>
                            <input type="password" class="form-control" id="api_secret" name="api_secret"
                                   placeholder="Votre secret API">
                        </div>

                        <div class="form-group">
                            <label for="account_id">ID du compte (optionnel)</label>
                            <input type="text" class="form-control" id="account_id_input" name="account_id"
                                   placeholder="ID ou nom d'utilisateur">
                        </div>

                        <div class="form-group">
                            <label for="ical_url">
                                URL du calendrier iCal
                                <i class="fas fa-info-circle text-muted" title="Lien d'export du calendrier depuis votre plateforme"></i>
                            </label>
                            <input type="text" class="form-control" id="ical_url" name="ical_url"
                                   placeholder="https://www.airbnb.fr/calendar/ical/...">
                            <small class="form-text text-muted">
                                Pour synchroniser automatiquement vos réservations via iCal
                            </small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-connect" id="saveConnection">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let platforms = [];
        let connections = [];
        let currentPlatformCode = '';

        $(document).ready(function() {
            loadData();
        });

        function loadData() {
            Promise.all([
                fetch('travel_accounts_api.php?action=get_platforms').then(r => r.json()),
                fetch('travel_accounts_api.php?action=get_connections').then(r => r.json())
            ]).then(([platformsData, connectionsData]) => {
                platforms = platformsData.platforms;
                connections = connectionsData.connections;
                renderPlatforms();
            }).catch(error => {
                showAlert('Erreur lors du chargement des données', 'danger');
                console.error(error);
            });
        }

        function renderPlatforms() {
            const container = $('#platforms-container');
            container.empty();

            platforms.forEach(platform => {
                const platformConnections = connections.filter(c => c.platform_code === platform.code);

                const card = $(`
                    <div class="platform-card">
                        <div class="platform-header">
                            <div class="platform-info">
                                ${platform.logo_url ?
                                    `<img src="${platform.logo_url}" alt="${platform.name}" class="platform-logo">` :
                                    `<i class="platform-icon fas fa-${getPlatformIcon(platform.code)}"></i>`
                                }
                                <h3 class="platform-name">${platform.name}</h3>
                            </div>
                            <button class="btn btn-connect btn-add-connection" data-platform-id="${platform.id}" data-platform-code="${platform.code}">
                                <i class="fas fa-plus"></i> Ajouter un compte
                            </button>
                        </div>
                        <div class="connection-list" id="connections-${platform.code}">
                            ${platformConnections.length === 0 ?
                                '<p class="text-muted text-center py-3">Aucune connexion configurée</p>' :
                                ''
                            }
                        </div>
                    </div>
                `);

                platformConnections.forEach(conn => {
                    const connItem = createConnectionItem(conn);
                    card.find('.connection-list').append(connItem);
                });

                container.append(card);
            });

            // Event handlers
            $('.btn-add-connection').click(function() {
                openConnectionModal($(this).data('platform-id'), $(this).data('platform-code'));
            });
        }

        function createConnectionItem(conn) {
            const statusClass = `status-${conn.connection_status}`;
            const statusText = {
                'connected': 'Connecté',
                'pending': 'En attente',
                'error': 'Erreur',
                'disconnected': 'Déconnecté'
            }[conn.connection_status] || conn.connection_status;

            return $(`
                <div class="connection-item" data-connection-id="${conn.id}">
                    <div class="connection-info">
                        <div class="connection-name">
                            ${conn.account_name}
                            <span class="connection-status ${statusClass}">${statusText}</span>
                            ${conn.listings_count > 0 ?
                                `<span class="listings-count ml-2"><i class="fas fa-home"></i> ${conn.listings_count} annonce(s)</span>` :
                                ''
                            }
                        </div>
                        ${conn.account_email ? `<div class="connection-email">${conn.account_email}</div>` : ''}
                        ${conn.error_message ? `<div class="text-danger small mt-1">${conn.error_message}</div>` : ''}
                        ${conn.ical_url ? `<div class="text-primary small mt-1"><i class="fas fa-calendar-alt"></i> iCal configuré</div>` : ''}
                        ${conn.ical_last_sync ?
                            `<div class="text-muted small mt-1">Synchro iCal: ${formatDate(conn.ical_last_sync)}</div>` :
                            ''
                        }
                        ${conn.ical_error_message ? `<div class="text-danger small mt-1">iCal: ${conn.ical_error_message}</div>` : ''}
                        ${conn.last_sync_at ?
                            `<div class="text-muted small mt-1">Dernière synchro API: ${formatDate(conn.last_sync_at)}</div>` :
                            ''
                        }
                        ${conn.reservations_count > 0 ?
                            `<div class="text-success small mt-1"><i class="fas fa-calendar-check"></i> ${conn.reservations_count} réservation(s)</div>` :
                            ''
                        }
                    </div>
                    <div class="connection-actions">
                        ${conn.ical_url ?
                            `<button class="btn-icon btn-sync" title="Synchroniser le calendrier iCal" onclick="syncIcal(${conn.id})">
                                <i class="fas fa-calendar-alt"></i>
                            </button>` :
                            ''
                        }
                        <button class="btn-icon btn-test" title="Tester la connexion" onclick="testConnection(${conn.id})">
                            <i class="fas fa-plug"></i>
                        </button>
                        ${conn.is_connected == 1 && conn.platform_code !== 'direct' ?
                            `<button class="btn-icon btn-sync" title="Synchroniser les annonces" onclick="syncListings(${conn.id})">
                                <i class="fas fa-sync"></i>
                            </button>` :
                            ''
                        }
                        <button class="btn-icon btn-edit" title="Modifier" onclick="editConnection(${conn.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon btn-delete" title="Supprimer" onclick="deleteConnection(${conn.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `);
        }

        function openConnectionModal(platformId, platformCode) {
            currentPlatformCode = platformCode;
            $('#connectionModalTitle').text('Ajouter une connexion');
            $('#connectionForm')[0].reset();
            $('#connection_id').val('');
            $('#platform_id').val(platformId);

            // Hide API fields for direct bookings
            if (platformCode === 'direct') {
                $('#api_key_group, #api_secret_group').hide();
            } else {
                $('#api_key_group, #api_secret_group').show();
            }

            $('#connectionModal').modal('show');
        }

        function editConnection(connectionId) {
            const conn = connections.find(c => c.id == connectionId);
            if (!conn) return;

            currentPlatformCode = conn.platform_code;
            $('#connectionModalTitle').text('Modifier la connexion');
            $('#connection_id').val(conn.id);
            $('#platform_id').val(conn.platform_id);
            $('#account_name').val(conn.account_name);
            $('#account_email').val(conn.account_email);
            $('#account_id_input').val(conn.account_id);
            $('#ical_url').val(conn.ical_url);

            if (conn.platform_code === 'direct') {
                $('#api_key_group, #api_secret_group').hide();
            } else {
                $('#api_key_group, #api_secret_group').show();
            }

            $('#connectionModal').modal('show');
        }

        $('#saveConnection').click(function() {
            const formData = {
                connection_id: $('#connection_id').val(),
                platform_id: $('#platform_id').val(),
                account_name: $('#account_name').val(),
                account_email: $('#account_email').val(),
                api_key: $('#api_key').val(),
                api_secret: $('#api_secret').val(),
                account_id: $('#account_id_input').val(),
                ical_url: $('#ical_url').val()
            };

            // DEBUG: Afficher ce qui va être envoyé
            console.log('=== FORMULAIRE CONNEXION ===');
            console.log('ical_url field exists:', $('#ical_url').length > 0);
            console.log('ical_url value:', $('#ical_url').val());
            console.log('Full formData:', formData);
            console.log('==========================');

            const action = formData.connection_id ? 'update_connection' : 'add_connection';
            const button = $(this);
            const originalHtml = button.html();
            button.html('<span class="spinner-border spinner-border-sm mr-2"></span>Enregistrement...').prop('disabled', true);

            fetch(`travel_accounts_api.php?action=${action}`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(formData)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    $('#connectionModal').modal('hide');
                    loadData();
                } else {
                    showAlert(data.error || 'Erreur lors de l\'enregistrement', 'danger');
                }
            })
            .catch(error => {
                showAlert('Erreur de connexion au serveur', 'danger');
                console.error(error);
            })
            .finally(() => {
                button.html(originalHtml).prop('disabled', false);
            });
        });

        function testConnection(connectionId) {
            const button = event.target.closest('button');
            const originalHtml = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            button.disabled = true;

            fetch('travel_accounts_api.php?action=test_connection', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({connection_id: connectionId})
            })
            .then(r => r.json())
            .then(data => {
                showAlert(data.message, data.success ? 'success' : 'warning');
                loadData();
            })
            .catch(error => {
                showAlert('Erreur lors du test de connexion', 'danger');
                console.error(error);
            })
            .finally(() => {
                button.innerHTML = originalHtml;
                button.disabled = false;
            });
        }

        function syncListings(connectionId) {
            const button = event.target.closest('button');
            const originalHtml = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            button.disabled = true;

            fetch('travel_accounts_api.php?action=sync_listings', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({connection_id: connectionId})
            })
            .then(r => r.json())
            .then(data => {
                showAlert(data.message, data.success ? 'success' : 'danger');
                loadData();
            })
            .catch(error => {
                showAlert('Erreur lors de la synchronisation', 'danger');
                console.error(error);
            })
            .finally(() => {
                button.innerHTML = originalHtml;
                button.disabled = false;
            });
        }

        function syncIcal(connectionId) {
            const button = event.target.closest('button');
            const originalHtml = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            button.disabled = true;

            fetch('ical_sync_api.php?action=sync_ical', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({connection_id: connectionId})
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const stats = data.stats || {};
                    const message = `${data.message}<br><small>Trouvé: ${stats.events_found}, Importé: ${stats.events_imported}, Mis à jour: ${stats.events_updated}</small>`;
                    showAlert(message, 'success');
                } else {
                    showAlert(data.error || 'Erreur lors de la synchronisation iCal', 'danger');
                }
                loadData();
            })
            .catch(error => {
                showAlert('Erreur lors de la synchronisation iCal', 'danger');
                console.error(error);
            })
            .finally(() => {
                button.innerHTML = originalHtml;
                button.disabled = false;
            });
        }

        function deleteConnection(connectionId) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cette connexion ?')) {
                return;
            }

            fetch('travel_accounts_api.php?action=delete_connection', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({connection_id: connectionId})
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    loadData();
                } else {
                    showAlert(data.error || 'Erreur lors de la suppression', 'danger');
                }
            })
            .catch(error => {
                showAlert('Erreur de connexion au serveur', 'danger');
                console.error(error);
            });
        }

        function showAlert(message, type) {
            const alert = $(`
                <div class="alert alert-${type} alert-custom alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="close" data-bs-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            `);
            $('#alert-container').append(alert);
            setTimeout(() => alert.fadeOut(() => alert.remove()), 5000);
        }

        function getPlatformIcon(code) {
            const icons = {
                'airbnb': 'home',
                'booking': 'hotel',
                'direct': 'globe'
            };
            return icons[code] || 'plug';
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);

            if (diffMins < 1) return 'À l\'instant';
            if (diffMins < 60) return `Il y a ${diffMins} min`;

            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return `Il y a ${diffHours}h`;

            const diffDays = Math.floor(diffHours / 24);
            if (diffDays < 7) return `Il y a ${diffDays} jour${diffDays > 1 ? 's' : ''}`;

            return date.toLocaleDateString('fr-FR');
        }
    </script>
</body>
</html>