<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservation directe — FrenchyConciergerie</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/booking.css">
    <style>
        body {
            margin: 0; padding: 0;
            background: #f5f5f0;
            font-family: 'Inter', sans-serif;
        }
        .page-header {
            background: #2d5016; color: white; padding: 32px 0; text-align: center;
        }
        .page-header h1 {
            font-family: 'Playfair Display', serif; font-size: 32px; font-weight: 700; margin: 0;
        }
        .page-header p { margin: 8px 0 0; opacity: 0.8; font-size: 15px; }
        .page-container {
            max-width: 1100px; margin: 32px auto; padding: 0 20px;
        }
        .widget-container {
            background: white; border-radius: 12px; padding: 32px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .demo-info {
            max-width: 1100px; margin: 16px auto; padding: 12px 20px;
            background: #fffbeb; border: 1px solid #fbbf24; border-radius: 8px;
            font-size: 13px; color: #92400e;
        }
        .demo-info strong { color: #78350f; }
        .property-selector {
            max-width: 1100px; margin: 0 auto 16px; padding: 0 20px;
            display: flex; align-items: center; gap: 12px;
        }
        .property-selector label { font-weight: 500; font-size: 14px; }
        .property-selector select {
            padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px;
            font-size: 14px; font-family: inherit;
        }
    </style>
</head>
<body>

<div class="page-header">
    <h1>Réservez votre séjour</h1>
    <p>Sélectionnez une ou plusieurs périodes et réservez en direct</p>
</div>

<div class="demo-info">
    <strong>Mode développement :</strong> Ce widget est autonome et peut être intégré dans n'importe quelle page via
    <code>&lt;div id="booking"&gt;&lt;/div&gt;</code> + inclusion du JS/CSS.
</div>

<?php
require_once __DIR__ . '/includes/db.php';
try {
    $pdo = getBookingPdo();
    $stmt = $pdo->query("SELECT id, nom_du_logement FROM liste_logements ORDER BY nom_du_logement");
    $logements = $stmt->fetchAll();
} catch (Exception $e) {
    $logements = [];
}
?>

<div class="property-selector">
    <label for="logement-select">Hébergement :</label>
    <select id="logement-select">
        <?php foreach ($logements as $l): ?>
            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nom_du_logement']) ?></option>
        <?php endforeach; ?>
        <?php if (empty($logements)): ?>
            <option value="1">Logement de test (ID 1)</option>
        <?php endif; ?>
    </select>
</div>

<div class="page-container">
    <div class="widget-container">
        <div id="booking-widget"></div>
    </div>
</div>

<script src="assets/booking.js"></script>
<script>
    let widget = null;

    function initWidget(logementId) {
        widget = new BookingWidget('booking-widget', {
            logementId: logementId,
            apiBase: './api',
        });
    }

    document.getElementById('logement-select').addEventListener('change', function() {
        initWidget(parseInt(this.value));
    });

    initWidget(parseInt(document.getElementById('logement-select').value));
</script>

</body>
</html>
