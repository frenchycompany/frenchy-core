<?php
/**
 * Interface pour visualiser toutes les annonces des plateformes connectées
 */

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Annonces</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .container {
            max-width: 1400px;
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

        .filters-bar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }

        .listings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .listing-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .listing-card:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            transform: translateY(-4px);
        }

        .listing-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .listing-content {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .listing-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }

        .listing-title {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin: 0;
            flex: 1;
        }

        .platform-badge {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 10px;
        }

        .platform-airbnb {
            background: #ffe8f0;
            color: #ff385c;
        }

        .platform-booking {
            background: #e3f2fd;
            color: #003b95;
        }

        .platform-direct {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .listing-location {
            color: #718096;
            font-size: 14px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .listing-description {
            color: #4a5568;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 15px;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .listing-details {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-top: 1px solid #e2e8f0;
            margin-bottom: 15px;
        }

        .listing-detail-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #718096;
            font-size: 14px;
        }

        .listing-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }

        .listing-price {
            font-size: 22px;
            font-weight: 700;
            color: #2d3748;
        }

        .listing-price-label {
            font-size: 13px;
            font-weight: 400;
            color: #718096;
        }

        .btn-view {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            color: white;
            text-decoration: none;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e0;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #2d3748;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #718096;
            margin-bottom: 20px;
        }

        .stats-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            flex: 1;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon-purple {
            background: #ede9fe;
            color: #7c3aed;
        }

        .stat-icon-blue {
            background: #e3f2fd;
            color: #1976d2;
        }

        .stat-icon-green {
            background: #e8f5e9;
            color: #388e3c;
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            color: #2d3748;
        }

        .stat-info p {
            margin: 0;
            color: #718096;
            font-size: 14px;
        }

        .filter-select {
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            padding: 8px 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-home"></i> Mes Annonces</h1>
            <p>Toutes vos annonces Airbnb, Booking.com et réservations directes en un seul endroit</p>
        </div>

        <div class="stats-bar" id="stats-bar">
            <div class="stat-card">
                <div class="stat-icon stat-icon-purple">
                    <i class="fas fa-home"></i>
                </div>
                <div class="stat-info">
                    <h3 id="total-listings">0</h3>
                    <p>Annonces totales</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-blue">
                    <i class="fas fa-plug"></i>
                </div>
                <div class="stat-info">
                    <h3 id="total-connections">0</h3>
                    <p>Comptes connectés</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-green">
                    <i class="fas fa-euro-sign"></i>
                </div>
                <div class="stat-info">
                    <h3 id="avg-price">-</h3>
                    <p>Prix moyen/nuit</p>
                </div>
            </div>
        </div>

        <div class="filters-bar">
            <div class="row">
                <div class="col-md-4">
                    <label for="platform-filter">Plateforme</label>
                    <select id="platform-filter" class="form-control filter-select">
                        <option value="">Toutes les plateformes</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="city-filter">Ville</label>
                    <select id="city-filter" class="form-control filter-select">
                        <option value="">Toutes les villes</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search-filter">Recherche</label>
                    <input type="text" id="search-filter" class="form-control filter-select" placeholder="Rechercher...">
                </div>
            </div>
        </div>

        <div id="listings-container">
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Chargement...</span>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let allListings = [];
        let filteredListings = [];

        $(document).ready(function() {
            loadData();

            // Filter handlers
            $('#platform-filter, #city-filter').change(applyFilters);
            $('#search-filter').on('input', applyFilters);
        });

        function loadData() {
            Promise.all([
                fetch('travel_accounts_api.php?action=get_listings').then(r => r.json()),
                fetch('travel_accounts_api.php?action=get_connections').then(r => r.json())
            ]).then(([listingsData, connectionsData]) => {
                allListings = listingsData.listings || [];
                filteredListings = allListings;

                updateStats(allListings, connectionsData.connections || []);
                populateFilters(allListings);
                renderListings(filteredListings);
            }).catch(error => {
                console.error(error);
                showEmptyState('Erreur lors du chargement des annonces');
            });
        }

        function updateStats(listings, connections) {
            $('#total-listings').text(listings.length);
            $('#total-connections').text(connections.filter(c => c.is_connected == 1).length);

            if (listings.length > 0) {
                const avgPrice = listings.reduce((sum, l) => sum + parseFloat(l.price_per_night || 0), 0) / listings.length;
                $('#avg-price').text(avgPrice.toFixed(0) + ' €');
            } else {
                $('#avg-price').text('-');
            }
        }

        function populateFilters(listings) {
            // Populate platform filter
            const platforms = [...new Set(listings.map(l => l.platform_code))];
            const platformNames = {
                'airbnb': 'Airbnb',
                'booking': 'Booking.com',
                'direct': 'Réservations Directes'
            };

            platforms.forEach(code => {
                $('#platform-filter').append(
                    $('<option>').val(code).text(platformNames[code] || code)
                );
            });

            // Populate city filter
            const cities = [...new Set(listings.map(l => l.city).filter(c => c))].sort();
            cities.forEach(city => {
                $('#city-filter').append(
                    $('<option>').val(city).text(city)
                );
            });
        }

        function applyFilters() {
            const platformFilter = $('#platform-filter').val();
            const cityFilter = $('#city-filter').val();
            const searchFilter = $('#search-filter').val().toLowerCase();

            filteredListings = allListings.filter(listing => {
                if (platformFilter && listing.platform_code !== platformFilter) return false;
                if (cityFilter && listing.city !== cityFilter) return false;
                if (searchFilter) {
                    const searchText = `${listing.title} ${listing.description} ${listing.city} ${listing.address}`.toLowerCase();
                    if (!searchText.includes(searchFilter)) return false;
                }
                return true;
            });

            renderListings(filteredListings);
        }

        function renderListings(listings) {
            const container = $('#listings-container');
            container.empty();

            if (listings.length === 0) {
                showEmptyState('Aucune annonce trouvée');
                return;
            }

            const grid = $('<div class="listings-grid"></div>');

            listings.forEach(listing => {
                const card = createListingCard(listing);
                grid.append(card);
            });

            container.append(grid);
        }

        function createListingCard(listing) {
            const platformClass = `platform-${listing.platform_code}`;
            const platformNames = {
                'airbnb': 'Airbnb',
                'booking': 'Booking.com',
                'direct': 'Direct'
            };

            return $(`
                <div class="listing-card">
                    <div class="listing-image">
                        ${listing.image_url ?
                            `<img src="${listing.image_url}" alt="${listing.title}" style="width:100%;height:100%;object-fit:cover;">` :
                            ''
                        }
                    </div>
                    <div class="listing-content">
                        <div class="listing-header">
                            <h3 class="listing-title">${listing.title}</h3>
                            <span class="platform-badge ${platformClass}">
                                <i class="fas fa-${getPlatformIcon(listing.platform_code)}"></i>
                                ${platformNames[listing.platform_code] || listing.platform_name}
                            </span>
                        </div>

                        ${listing.city ?
                            `<div class="listing-location">
                                <i class="fas fa-map-marker-alt"></i>
                                ${listing.city}${listing.country ? ', ' + listing.country : ''}
                            </div>` :
                            ''
                        }

                        ${listing.description ?
                            `<div class="listing-description">${listing.description}</div>` :
                            ''
                        }

                        <div class="listing-details">
                            ${listing.bedrooms ?
                                `<div class="listing-detail-item">
                                    <i class="fas fa-bed"></i>
                                    ${listing.bedrooms} chambre${listing.bedrooms > 1 ? 's' : ''}
                                </div>` :
                                ''
                            }
                            ${listing.bathrooms ?
                                `<div class="listing-detail-item">
                                    <i class="fas fa-bath"></i>
                                    ${listing.bathrooms} sdb
                                </div>` :
                                ''
                            }
                            ${listing.max_guests ?
                                `<div class="listing-detail-item">
                                    <i class="fas fa-users"></i>
                                    ${listing.max_guests} pers.
                                </div>` :
                                ''
                            }
                        </div>

                        <div class="listing-footer">
                            <div>
                                <div class="listing-price">${parseFloat(listing.price_per_night).toFixed(0)} €</div>
                                <div class="listing-price-label">par nuit</div>
                            </div>
                            ${listing.listing_url ?
                                `<a href="${listing.listing_url}" target="_blank" class="btn-view">
                                    Voir <i class="fas fa-external-link-alt ml-1"></i>
                                </a>` :
                                `<button class="btn-view" disabled>
                                    Non disponible
                                </button>`
                            }
                        </div>
                    </div>
                </div>
            `);
        }

        function showEmptyState(message) {
            const container = $('#listings-container');
            container.html(`
                <div class="empty-state">
                    <i class="fas fa-home"></i>
                    <h3>${message}</h3>
                    <p>Connectez vos comptes de plateformes et synchronisez vos annonces pour les voir ici</p>
                    <a href="travel_accounts.php" class="btn btn-connect">
                        <i class="fas fa-plug"></i> Gérer les connexions
                    </a>
                </div>
            `);
        }

        function getPlatformIcon(code) {
            const icons = {
                'airbnb': 'home',
                'booking': 'hotel',
                'direct': 'globe'
            };
            return icons[code] || 'home';
        }
    </script>
</body>
</html>