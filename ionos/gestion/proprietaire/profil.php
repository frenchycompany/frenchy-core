<?php
/**
 * Espace Propriétaire - Mon profil
 */
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil - Espace Proprietaire</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="proprio.css">
    <style>
        .profil-field { padding: 0.8rem 0; border-bottom: 1px solid #F3F4F6; }
        .profil-field:last-child { border-bottom: none; }
        .profil-label { font-size: 0.82rem; color: #6B7280; margin-bottom: 2px; }
        .profil-value { color: #1F2937; font-size: 1rem; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php proprioSidebar($proprietaire, $currentPage, $has_sites); ?>

    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-user"></i> Mon profil</h1>
        </div>

        <div class="card" style="max-width:600px;">
            <div class="profil-field">
                <div class="profil-label">Nom</div>
                <div class="profil-value"><?= e($proprietaire['nom'] ?? '') ?></div>
            </div>
            <div class="profil-field">
                <div class="profil-label">Prenom</div>
                <div class="profil-value"><?= e($proprietaire['prenom'] ?? '') ?></div>
            </div>
            <div class="profil-field">
                <div class="profil-label">Email</div>
                <div class="profil-value"><?= e($proprietaire['email'] ?? '') ?></div>
            </div>
            <div class="profil-field">
                <div class="profil-label">Telephone</div>
                <div class="profil-value"><?= e($proprietaire['telephone'] ?? '-') ?></div>
            </div>
            <div class="profil-field">
                <div class="profil-label">Adresse</div>
                <div class="profil-value"><?= e($proprietaire['adresse'] ?? '-') ?></div>
            </div>
        </div>

        <div class="card" style="max-width:600px; margin-top:1.5rem;">
            <div class="card-header">
                <h2><i class="fas fa-home"></i> Mes logements</h2>
            </div>
            <?php if (empty($logements)): ?>
                <p class="empty-state">Aucun logement associe</p>
            <?php else: ?>
                <?php foreach ($logements as $logement): ?>
                <div class="list-item">
                    <div>
                        <h4><?= e($logement['nom_du_logement']) ?></h4>
                        <small><?= e($logement['adresse'] ?? '') ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
