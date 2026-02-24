<?php
require '../db/connection.php';

// Récupérer les partenaires avec une offre spéciale et une image promo
$stmt = $conn->query("SELECT id, nom, promo_image, offre_speciale FROM clients WHERE is_partner = 1");
$partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si aucun partenaire, ne rien afficher
if (empty($partners)) {
    return;
}
?>

<style>
.partners-container {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 20px;
    margin: 20px 0;
}

.partner-card {
    width: 250px; /* 🔥 Augmenté */
    height: auto;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    text-align: center;
    transition: transform 0.3s ease-in-out;
    display: flex;
    flex-direction: column;
}

.partner-card:hover {
    transform: scale(1.05);
}

.partner-card img {
    width: 100%;
    height: 180px; /* 🔥 Ajusté pour moins de crop */
    object-fit: contain; /* 🔥 Remplace "cover" pour voir toute l'image */
    background: #f8f8f8;
}

.partner-info {
    padding: 15px;
    background: #ffffff;
    flex-grow: 1; /* Permet au texte de prendre toute la hauteur nécessaire */
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.partner-info h3 {
    font-size: 16px;
    margin: 0;
    color: #333;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.partner-info p {
    font-size: 14px;
    margin: 5px 0 0;
    color: #555;
    word-wrap: break-word; /* 🔥 Permet au texte long de s'afficher */
    overflow-wrap: break-word;
}

.partner-card a {
    display: block;
    width: 100%;
    height: 100%;
    text-decoration: none;
    color: inherit;
}
</style>

<section class="partners-container">
    <?php foreach ($partners as $partner): ?>
        <a href="landing.php?client_id=<?php echo $partner['id']; ?>" class="partner-card">
            <img src="<?php echo !empty($partner['promo_image']) ? htmlspecialchars($partner['promo_image']) : '../assets/img/default-placeholder.jpg'; ?>" alt="Promo de <?php echo htmlspecialchars($partner['nom']); ?>">
            <div class="partner-info">
                <h3><?php echo htmlspecialchars($partner['nom']); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($partner['offre_speciale'])); ?></p>
            </div>
        </a>
    <?php endforeach; ?>
</section>
