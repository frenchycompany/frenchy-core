<?php
require '../db/connection.php';

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$banner = null;

if ($client_id) {
    $stmt = $conn->prepare("SELECT * FROM client_banners WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($banner): ?>
    <style>
        .banner {
            width: 100%;
            height: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            background-color: #f0f0f0; /* Arrière-plan en cas d'image trop petite */
        }

        .banner-image {
            width: 100%;
            height: auto;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .banner-image img {
            width: 100%;
            height: auto;
            max-height: 80vh; /* Ajustable selon tes besoins */
            object-fit: contain; /* ✅ Permet de voir l'image en entier */
        }

        .banner-overlay {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.5);
            padding: 10px 20px;
            border-radius: 5px;
        }

        .banner-button {
            color: white;
            text-decoration: none;
            font-size: 18px;
            font-weight: bold;
            background: #007bff;
            padding: 10px 20px;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .banner-button:hover {
            background: #0056b3;
        }
    </style>

    <section class="banner">
        <div class="banner-image">
            <img src="<?php echo htmlspecialchars($banner['image_path']); ?>" alt="Bannière">
            <?php if (!empty($banner['button_text']) && !empty($banner['button_link'])): ?>
                <div class="banner-overlay">
                    <a href="<?php echo htmlspecialchars($banner['button_link']); ?>" class="banner-button">
                        <?php echo htmlspecialchars($banner['button_text']); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
