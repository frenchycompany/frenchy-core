<?php
/**
 * API for managing travel platform account connections
 * Handles Airbnb, Booking.com, and direct booking accounts
 */

header('Content-Type: application/json; charset=utf-8');

// Include database connection
require_once '../includes/db.php';

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_platforms':
            getPlatforms($pdo);
            break;

        case 'get_connections':
            getConnections($pdo);
            break;

        case 'add_connection':
            if ($method === 'POST') {
                addConnection($pdo);
            } else {
                throw new Exception('Method not allowed');
            }
            break;

        case 'update_connection':
            if ($method === 'POST') {
                updateConnection($pdo);
            } else {
                throw new Exception('Method not allowed');
            }
            break;

        case 'delete_connection':
            if ($method === 'POST') {
                deleteConnection($pdo);
            } else {
                throw new Exception('Method not allowed');
            }
            break;

        case 'test_connection':
            if ($method === 'POST') {
                testConnection($pdo);
            } else {
                throw new Exception('Method not allowed');
            }
            break;

        case 'sync_listings':
            if ($method === 'POST') {
                syncListings($pdo);
            } else {
                throw new Exception('Method not allowed');
            }
            break;

        case 'get_listings':
            getListings($pdo);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Get all available platforms
 */
function getPlatforms($pdo) {
    $stmt = $pdo->query("
        SELECT id, name, code, logo_url, is_active
        FROM travel_platforms
        WHERE is_active = 1
        ORDER BY name
    ");

    echo json_encode([
        'success' => true,
        'platforms' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

/**
 * Get all account connections
 */
function getConnections($pdo) {
    $stmt = $pdo->query("
        SELECT
            c.id,
            c.account_name,
            c.account_email,
            c.account_id,
            c.is_connected,
            c.connection_status,
            c.last_sync_at,
            c.error_message,
            c.created_at,
            c.ical_url,
            c.ical_last_sync,
            c.ical_sync_status,
            c.ical_error_message,
            p.name as platform_name,
            p.code as platform_code,
            p.logo_url as platform_logo,
            (SELECT COUNT(*) FROM travel_listings WHERE connection_id = c.id AND is_active = 1) as listings_count,
            (SELECT COUNT(*) FROM ical_reservations WHERE connection_id = c.id) as reservations_count
        FROM travel_account_connections c
        JOIN travel_platforms p ON c.platform_id = p.id
        ORDER BY c.created_at DESC
    ");

    echo json_encode([
        'success' => true,
        'connections' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

/**
 * Add a new account connection
 */
function addConnection($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);

    $platform_id = $input['platform_id'] ?? null;
    $account_name = $input['account_name'] ?? '';
    $account_email = $input['account_email'] ?? '';
    $api_key = $input['api_key'] ?? '';
    $api_secret = $input['api_secret'] ?? '';
    $account_id = $input['account_id'] ?? '';
    $ical_url = $input['ical_url'] ?? '';

    if (!$platform_id || !$account_name) {
        throw new Exception('Platform ID and account name are required');
    }

    $stmt = $pdo->prepare("
        INSERT INTO travel_account_connections
        (platform_id, account_name, account_email, api_key, api_secret, account_id, ical_url, connection_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ");

    $stmt->execute([
        $platform_id,
        $account_name,
        $account_email,
        $api_key,
        $api_secret,
        $account_id,
        $ical_url
    ]);

    $connection_id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Connexion ajoutée avec succès',
        'connection_id' => $connection_id
    ]);
}

/**
 * Update an existing connection
 */
function updateConnection($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);

    $connection_id = $input['connection_id'] ?? null;
    $account_name = $input['account_name'] ?? '';
    $account_email = $input['account_email'] ?? '';
    $api_key = $input['api_key'] ?? '';
    $api_secret = $input['api_secret'] ?? '';
    $account_id = $input['account_id'] ?? '';
    $ical_url = $input['ical_url'] ?? '';

    if (!$connection_id) {
        throw new Exception('Connection ID is required');
    }

    $stmt = $pdo->prepare("
        UPDATE travel_account_connections
        SET account_name = ?,
            account_email = ?,
            api_key = ?,
            api_secret = ?,
            account_id = ?,
            ical_url = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");

    $stmt->execute([
        $account_name,
        $account_email,
        $api_key,
        $api_secret,
        $account_id,
        $ical_url,
        $connection_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Connexion mise à jour avec succès'
    ]);
}

/**
 * Delete a connection
 */
function deleteConnection($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);

    $connection_id = $input['connection_id'] ?? null;

    if (!$connection_id) {
        throw new Exception('Connection ID is required');
    }

    $stmt = $pdo->prepare("DELETE FROM travel_account_connections WHERE id = ?");
    $stmt->execute([$connection_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Connexion supprimée avec succès'
    ]);
}

/**
 * Test connection to a platform
 */
function testConnection($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);

    $connection_id = $input['connection_id'] ?? null;

    if (!$connection_id) {
        throw new Exception('Connection ID is required');
    }

    // Get connection details
    $stmt = $pdo->prepare("
        SELECT c.*, p.code as platform_code
        FROM travel_account_connections c
        JOIN travel_platforms p ON c.platform_id = p.id
        WHERE c.id = ?
    ");
    $stmt->execute([$connection_id]);
    $connection = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$connection) {
        throw new Exception('Connection not found');
    }

    // Simulate connection test (in production, this would call the actual API)
    $is_valid = false;
    $error_message = null;

    switch ($connection['platform_code']) {
        case 'airbnb':
            // In production: test Airbnb API with credentials
            $is_valid = !empty($connection['api_key']);
            $error_message = $is_valid ? null : 'Clé API Airbnb invalide ou manquante';
            break;

        case 'booking':
            // In production: test Booking.com API with credentials
            $is_valid = !empty($connection['api_key']);
            $error_message = $is_valid ? null : 'Clé API Booking.com invalide ou manquante';
            break;

        case 'direct':
            // Direct bookings always valid (no external API)
            $is_valid = true;
            break;
    }

    // Update connection status
    $status = $is_valid ? 'connected' : 'error';
    $stmt = $pdo->prepare("
        UPDATE travel_account_connections
        SET connection_status = ?,
            is_connected = ?,
            error_message = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([
        $status,
        $is_valid ? 1 : 0,
        $error_message,
        $connection_id
    ]);

    echo json_encode([
        'success' => $is_valid,
        'message' => $is_valid ? 'Connexion réussie' : 'Échec de la connexion',
        'error' => $error_message,
        'status' => $status
    ]);
}

/**
 * Sync listings from a platform
 */
function syncListings($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);

    $connection_id = $input['connection_id'] ?? null;

    if (!$connection_id) {
        throw new Exception('Connection ID is required');
    }

    // Get connection details
    $stmt = $pdo->prepare("
        SELECT c.*, p.code as platform_code
        FROM travel_account_connections c
        JOIN travel_platforms p ON c.platform_id = p.id
        WHERE c.id = ? AND c.is_connected = 1
    ");
    $stmt->execute([$connection_id]);
    $connection = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$connection) {
        throw new Exception('Connection not found or not connected');
    }

    // In production, this would fetch listings from the actual API
    // For now, we'll simulate with sample data
    $listings = [];

    switch ($connection['platform_code']) {
        case 'airbnb':
            // Simulate Airbnb API response
            $listings = [
                [
                    'platform_listing_id' => 'airbnb_' . rand(10000, 99999),
                    'title' => 'Appartement cosy centre-ville',
                    'description' => 'Bel appartement lumineux avec vue sur la ville',
                    'address' => '123 Rue Example',
                    'city' => 'Paris',
                    'country' => 'France',
                    'price_per_night' => 89.00,
                    'bedrooms' => 2,
                    'bathrooms' => 1,
                    'max_guests' => 4,
                    'property_type' => 'Appartement'
                ]
            ];
            break;

        case 'booking':
            // Simulate Booking.com API response
            $listings = [
                [
                    'platform_listing_id' => 'booking_' . rand(10000, 99999),
                    'title' => 'Studio moderne avec parking',
                    'description' => 'Studio entièrement équipé avec parking privé',
                    'address' => '456 Avenue Test',
                    'city' => 'Lyon',
                    'country' => 'France',
                    'price_per_night' => 65.00,
                    'bedrooms' => 1,
                    'bathrooms' => 1,
                    'max_guests' => 2,
                    'property_type' => 'Studio'
                ]
            ];
            break;
    }

    // Insert or update listings
    $synced_count = 0;
    foreach ($listings as $listing) {
        $stmt = $pdo->prepare("
            INSERT INTO travel_listings
            (connection_id, platform_listing_id, title, description, address, city, country,
             price_per_night, bedrooms, bathrooms, max_guests, property_type, last_synced_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                description = VALUES(description),
                address = VALUES(address),
                city = VALUES(city),
                country = VALUES(country),
                price_per_night = VALUES(price_per_night),
                bedrooms = VALUES(bedrooms),
                bathrooms = VALUES(bathrooms),
                max_guests = VALUES(max_guests),
                property_type = VALUES(property_type),
                last_synced_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([
            $connection_id,
            $listing['platform_listing_id'],
            $listing['title'],
            $listing['description'],
            $listing['address'],
            $listing['city'],
            $listing['country'],
            $listing['price_per_night'],
            $listing['bedrooms'],
            $listing['bathrooms'],
            $listing['max_guests'],
            $listing['property_type']
        ]);

        $synced_count++;
    }

    // Update last sync time
    $stmt = $pdo->prepare("
        UPDATE travel_account_connections
        SET last_sync_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$connection_id]);

    echo json_encode([
        'success' => true,
        'message' => "$synced_count annonce(s) synchronisée(s)",
        'count' => $synced_count
    ]);
}

/**
 * Get all listings
 */
function getListings($pdo) {
    $connection_id = $_GET['connection_id'] ?? null;

    $sql = "
        SELECT
            l.*,
            c.account_name,
            p.name as platform_name,
            p.code as platform_code,
            p.logo_url as platform_logo
        FROM travel_listings l
        JOIN travel_account_connections c ON l.connection_id = c.id
        JOIN travel_platforms p ON c.platform_id = p.id
        WHERE l.is_active = 1
    ";

    if ($connection_id) {
        $stmt = $pdo->prepare($sql . " AND l.connection_id = ? ORDER BY l.created_at DESC");
        $stmt->execute([$connection_id]);
    } else {
        $stmt = $pdo->query($sql . " ORDER BY l.created_at DESC");
    }

    echo json_encode([
        'success' => true,
        'listings' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}