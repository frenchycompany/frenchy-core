<?php
/**
 * Inventaire — Page d'accueil
 */
include '../config.php';
include '../pages/menu.php';

// Stats rapides
$statsEnCours = $conn->query("SELECT COUNT(*) FROM sessions_inventaire WHERE statut = 'en_cours'")->fetchColumn();
$statsTerminees = $conn->query("SELECT COUNT(*) FROM sessions_inventaire WHERE statut = 'terminee'")->fetchColumn();
$statsTotalObjets = $conn->query("SELECT COUNT(*) FROM inventaire_objets")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Inventaires</title>
    <style>
        .inv-home {
            max-width: 520px;
            margin: 0 auto;
            padding: 0 12px 30px;
        }
        .inv-home-header {
            background: linear-gradient(135deg, #1976d2, #1565c0);
            color: #fff;
            text-align: center;
            padding: 25px 15px 20px;
            border-radius: 15px;
            margin: 15px 0 20px;
        }
        .inv-home-header h2 { margin: 0 0 8px; font-size: 1.4em; }
        .inv-home-header p { margin: 0; opacity: 0.85; font-size: 0.92em; }
        /* Stats */
        .inv-stats {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .inv-stat-card {
            flex: 1;
            background: #fff;
            border-radius: 12px;
            padding: 15px 10px;
            text-align: center;
            box-shadow: 0 1px 5px rgba(0,0,0,0.07);
        }
        .inv-stat-card .number { font-size: 1.8em; font-weight: 800; line-height: 1; }
        .inv-stat-card .label { font-size: 0.78em; color: #666; margin-top: 3px; }
        .stat-encours .number { color: #e65100; }
        .stat-terminee .number { color: #2e7d32; }
        .stat-objets .number { color: #1565c0; }
        /* Menu actions */
        .inv-menu {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .inv-menu a {
            background: #fff;
            display: flex;
            align-items: center;
            padding: 18px 20px;
            border-radius: 14px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.06);
            text-decoration: none;
            color: #333;
            font-size: 1.05em;
            font-weight: 600;
            transition: box-shadow 0.15s, transform 0.1s;
            gap: 14px;
        }
        .inv-menu a:hover {
            box-shadow: 0 2px 14px rgba(0,0,0,0.12);
        }
        .inv-menu a:active {
            transform: scale(0.98);
        }
        .inv-menu .menu-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
            flex-shrink: 0;
        }
        .icon-green { background: #e8f5e9; color: #2e7d32; }
        .icon-blue { background: #e3f2fd; color: #1565c0; }
        .icon-orange { background: #fff3e0; color: #e65100; }
        .icon-purple { background: #f3e5f5; color: #7b1fa2; }
        .inv-menu .menu-desc {
            font-size: 0.82em;
            font-weight: 400;
            color: #888;
            margin-top: 2px;
        }
        @media (max-width: 600px) {
            .inv-home { padding: 0 6px 30px; }
            .inv-home-header { padding: 18px 10px; }
            .inv-home-header h2 { font-size: 1.2em; }
        }
    </style>
</head>
<body>
<div class="inv-home">
    <div class="inv-home-header">
        <h2><i class="fas fa-boxes-stacked"></i> Inventaires</h2>
        <p>Gestion complete des inventaires par logement</p>
    </div>

    <div class="inv-stats">
        <div class="inv-stat-card stat-encours">
            <div class="number"><?= $statsEnCours ?></div>
            <div class="label">En cours</div>
        </div>
        <div class="inv-stat-card stat-terminee">
            <div class="number"><?= $statsTerminees ?></div>
            <div class="label">Terminees</div>
        </div>
        <div class="inv-stat-card stat-objets">
            <div class="number"><?= $statsTotalObjets ?></div>
            <div class="label">Objets total</div>
        </div>
    </div>

    <div class="inv-menu">
        <a href="inventaire_lancer.php">
            <div class="menu-icon icon-green"><i class="fas fa-plus-circle"></i></div>
            <div>
                Lancer un inventaire
                <div class="menu-desc">Creer une nouvelle session pour un logement</div>
            </div>
        </a>
        <a href="liste_sessions.php">
            <div class="menu-icon icon-blue"><i class="fas fa-clipboard-list"></i></div>
            <div>
                Sessions d'inventaire
                <div class="menu-desc">Voir les sessions en cours et terminees</div>
            </div>
        </a>
        <a href="liste_objets.php">
            <div class="menu-icon icon-orange"><i class="fas fa-list"></i></div>
            <div>
                Tous les objets
                <div class="menu-desc">Synthese des objets inventories par logement</div>
            </div>
        </a>
        <a href="impression_etiquettes.php">
            <div class="menu-icon icon-purple"><i class="fas fa-tags"></i></div>
            <div>
                Etiquettes QR
                <div class="menu-desc">Imprimer les etiquettes des objets Frenchy</div>
            </div>
        </a>
    </div>
</div>
</body>
</html>
