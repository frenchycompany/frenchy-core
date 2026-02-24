<?php
require_once '../db/connection.php';

// 🔍 Récupérer les partenaires
$stmt = $conn->query("SELECT id, nom, type_commerce, logo, offre_speciale FROM clients WHERE is_partner = 1");
$partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if (!empty($partners)): ?>
<section class="partners">
    <h2>Nos Partenaires</h2>
    <div class="partner-list">
        <?php foreach ($partners as $partner): ?>
            <div class="partner-card">
                <img src="<?php echo htmlspecialchars($partner['logo']); ?>" alt="<?php echo htmlspecialchars($partner['nom']); ?>">
                <div class="partner-info">
                    <h3><?php echo htmlspecialchars($partner['nom']); ?></h3>
                    <p><strong><?php echo htmlspecialchars($partner['type_commerce']); ?></strong></p>
                    <p><?php echo htmlspecialchars($partner['offre_speciale']); ?></p>
                    <a href="https://frenchyconciergerie.fr/cdansmaville/admin/landing.php?client_id=<?php echo $partner['id']; ?>" target="_blank" class="partner-link">Voir l'offre</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
