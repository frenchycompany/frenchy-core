<?php
require_once __DIR__ . '/includes/db.php';

$ref = $_GET['ref'] ?? '';
$success = isset($_GET['success']);
$cancelled = isset($_GET['cancelled']);

$booking = null;
$pricing = null;
$logementName = '';

if ($ref) {
    try {
        $pdo = getBookingPdo();
        $stmt = $pdo->prepare("SELECT * FROM direct_bookings WHERE booking_ref = ?");
        $stmt->execute([$ref]);
        $booking = $stmt->fetch();

        if ($booking) {
            $pricing = json_decode($booking['pricing_json'], true);
            $stmt = $pdo->prepare("SELECT nom_du_logement FROM liste_logements WHERE id = ?");
            $stmt->execute([$booking['logement_id']]);
            $l = $stmt->fetch();
            if ($l) $logementName = $l['nom_du_logement'];
        }
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $success ? 'Réservation confirmée' : ($cancelled ? 'Paiement annulé' : 'Réservation') ?> — FrenchyConciergerie</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f5f5f0; min-height: 100vh; display: flex; flex-direction: column; }
        .header { background: #2d5016; padding: 20px; text-align: center; }
        .header h1 { font-family: 'Playfair Display', serif; color: white; font-size: 24px; }
        .container { max-width: 600px; margin: 40px auto; padding: 0 20px; flex: 1; }
        .card { background: white; border-radius: 12px; padding: 32px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); text-align: center; }
        .icon { font-size: 64px; margin-bottom: 16px; }
        .icon-success { color: #16a34a; }
        .icon-cancelled { color: #dc2626; }
        .icon-pending { color: #f59e0b; }
        h2 { font-size: 24px; margin-bottom: 8px; color: #1a1a1a; }
        .ref { color: #6b7280; margin-bottom: 24px; font-size: 14px; }
        .detail-card { background: #f9fafb; border-radius: 8px; padding: 16px; margin: 16px 0; text-align: left; }
        .detail-card h3 { color: #2d5016; font-size: 16px; margin-bottom: 8px; }
        .period-line { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; border-bottom: 1px solid #f3f4f6; }
        .period-line:last-child { border: none; }
        .total-line { display: flex; justify-content: space-between; font-size: 18px; font-weight: 700; padding: 12px 0; color: #2d5016; }
        .discount-line { color: #16a34a; font-size: 14px; display: flex; justify-content: space-between; padding: 4px 0; }
        .virement-box { background: #f5f0e0; border-radius: 8px; padding: 16px; margin: 16px 0; text-align: left; }
        .virement-box h3 { color: #8b6914; margin-bottom: 8px; }
        .virement-box p { font-size: 14px; margin: 4px 0; }
        .btn { display: inline-block; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 8px 4px; font-size: 14px; }
        .btn-primary { background: #2d5016; color: white; }
        .btn-outline { background: white; color: #2d5016; border: 1px solid #2d5016; }
        .actions { margin-top: 24px; }
    </style>
</head>
<body>

<div class="header"><h1>FrenchyConciergerie</h1></div>

<div class="container">
    <div class="card">
        <?php if ($cancelled): ?>
            <div class="icon icon-cancelled">&#10007;</div>
            <h2>Paiement annulé</h2>
            <p class="ref">Votre paiement n'a pas abouti. La réservation n'a pas été confirmée.</p>
            <div class="actions">
                <a href="./" class="btn btn-primary">Réessayer</a>
            </div>

        <?php elseif ($booking && ($success || $booking['status'] === 'paid')): ?>
            <div class="icon icon-success">&#10003;</div>
            <h2>Réservation confirmée !</h2>
            <p class="ref">Référence : <strong><?= htmlspecialchars($booking['booking_ref']) ?></strong></p>

            <div class="detail-card">
                <h3><?= htmlspecialchars($logementName) ?></h3>
                <?php foreach ($pricing['periods'] as $p): ?>
                    <div class="period-line">
                        <span><?= date('d/m/Y', strtotime($p['checkin'])) ?> → <?= date('d/m/Y', strtotime($p['checkout'])) ?></span>
                        <span><?= $p['nb_nights'] ?> nuit<?= $p['nb_nights'] > 1 ? 's' : '' ?> — <?= number_format($p['total'], 2, ',', ' ') ?>€</span>
                    </div>
                <?php endforeach; ?>
                <?php if ($pricing['long_stay_discount_amount'] > 0): ?>
                    <div class="discount-line">
                        <span>Remise long séjour (-<?= $pricing['long_stay_discount_percent'] ?>%)</span>
                        <span>-<?= number_format($pricing['long_stay_discount_amount'], 2, ',', ' ') ?>€</span>
                    </div>
                <?php endif; ?>
                <div class="total-line">
                    <span>Total</span>
                    <span><?= number_format($pricing['total'], 2, ',', ' ') ?>€</span>
                </div>
            </div>

            <p style="color:#6b7280;font-size:13px">Un email de confirmation a été envoyé à <strong><?= htmlspecialchars($booking['guest_email']) ?></strong></p>

            <?php if ($booking['invoice_number']): ?>
            <div class="actions">
                <a href="api/invoice.php?ref=<?= urlencode($booking['booking_ref']) ?>&email=<?= urlencode($booking['guest_email']) ?>" class="btn btn-primary" target="_blank">Télécharger la facture</a>
            </div>
            <?php endif; ?>

        <?php elseif ($booking && $booking['payment_method'] === 'virement'): ?>
            <div class="icon icon-pending">&#9888;</div>
            <h2>Réservation enregistrée</h2>
            <p class="ref">Référence : <strong><?= htmlspecialchars($booking['booking_ref']) ?></strong></p>
            <p style="color:#6b7280;margin-bottom:16px">En attente de votre virement bancaire.</p>

            <div class="detail-card">
                <h3><?= htmlspecialchars($logementName) ?></h3>
                <?php foreach ($pricing['periods'] as $p): ?>
                    <div class="period-line">
                        <span><?= date('d/m/Y', strtotime($p['checkin'])) ?> → <?= date('d/m/Y', strtotime($p['checkout'])) ?></span>
                        <span><?= $p['nb_nights'] ?> nuit<?= $p['nb_nights'] > 1 ? 's' : '' ?> — <?= number_format($p['total'], 2, ',', ' ') ?>€</span>
                    </div>
                <?php endforeach; ?>
                <div class="total-line">
                    <span>Total à virer</span>
                    <span><?= number_format($pricing['total'], 2, ',', ' ') ?>€</span>
                </div>
            </div>

            <div class="virement-box">
                <h3>Instructions de virement</h3>
                <p><strong>Bénéficiaire :</strong> FrenchyConciergerie</p>
                <p><strong>IBAN :</strong> FR76 XXXX XXXX XXXX XXXX XXXX XXX</p>
                <p><strong>Référence :</strong> <?= htmlspecialchars($booking['booking_ref']) ?></p>
                <p><strong>Montant :</strong> <?= number_format($pricing['total'], 2, ',', ' ') ?>€</p>
                <p style="margin-top:8px;font-style:italic;color:#666">Merci d'effectuer le virement sous 48h avec la référence en objet.</p>
            </div>

            <?php if ($booking['invoice_number']): ?>
            <div class="actions">
                <a href="api/invoice.php?ref=<?= urlencode($booking['booking_ref']) ?>&email=<?= urlencode($booking['guest_email']) ?>" class="btn btn-primary" target="_blank">Télécharger la facture proforma</a>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="icon icon-cancelled">?</div>
            <h2>Réservation introuvable</h2>
            <p class="ref">La référence indiquée n'existe pas ou a expiré.</p>
            <div class="actions">
                <a href="./" class="btn btn-primary">Retour</a>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
