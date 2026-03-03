<?php
/**
 * Checkup Logement — Lancement d'un checkup
 * La femme de menage choisit un logement et demarre un checkup complet
 */
include '../config.php';
include '../pages/menu.php';

// Creation des tables si elles n'existent pas
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS checkup_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            logement_id INT NOT NULL,
            intervenant_id INT DEFAULT NULL,
            statut ENUM('en_cours','termine') DEFAULT 'en_cours',
            nb_ok INT DEFAULT 0,
            nb_problemes INT DEFAULT 0,
            nb_absents INT DEFAULT 0,
            commentaire_general TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_logement (logement_id),
            INDEX idx_statut (statut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS checkup_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            categorie VARCHAR(50) NOT NULL,
            nom_item VARCHAR(255) NOT NULL,
            statut ENUM('ok','probleme','absent','non_verifie') DEFAULT 'non_verifie',
            commentaire TEXT DEFAULT NULL,
            photo_path VARCHAR(500) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session (session_id),
            FOREIGN KEY (session_id) REFERENCES checkup_sessions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    // Tables existent deja
}

// Traitement : creer une session de checkup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logement_id'])) {
    $logement_id = intval($_POST['logement_id']);
    $intervenant_id = $_SESSION['id_intervenant'] ?? null;

    // Creer la session
    $stmt = $conn->prepare("INSERT INTO checkup_sessions (logement_id, intervenant_id) VALUES (?, ?)");
    $stmt->execute([$logement_id, $intervenant_id]);
    $session_id = $conn->lastInsertId();

    // Generer les items a verifier depuis les equipements du logement
    $stmt = $conn->prepare("SELECT * FROM logement_equipements WHERE logement_id = ?");
    $stmt->execute([$logement_id]);
    $equip = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($equip) {
        // Equipements cuisine
        $cuisine = [
            'bouilloire' => 'Bouilloire',
            'grille_pain' => 'Grille-pain',
            'micro_ondes' => 'Micro-ondes',
            'four' => 'Four',
            'plaque_cuisson' => 'Plaques de cuisson',
            'lave_vaisselle' => 'Lave-vaisselle',
            'refrigerateur' => 'Refrigerateur',
            'congelateur' => 'Congelateur',
            'ustensiles_cuisine' => 'Ustensiles de cuisine',
        ];
        if ($equip['machine_cafe_type'] && $equip['machine_cafe_type'] !== 'aucune') {
            $cuisine['machine_cafe_type'] = 'Machine a cafe (' . $equip['machine_cafe_type'] . ')';
        }

        // Electromenager / entretien
        $entretien = [
            'machine_laver' => 'Machine a laver',
            'seche_linge' => 'Seche-linge',
            'fer_repasser' => 'Fer a repasser',
            'table_repasser' => 'Table a repasser',
            'aspirateur' => 'Aspirateur',
            'produits_menage' => 'Produits menage',
        ];

        // Multimedia
        $multimedia = [
            'tv' => 'Television',
            'enceinte_bluetooth' => 'Enceinte Bluetooth',
            'console_jeux' => 'Console de jeux',
        ];

        // Mobilier
        $mobilier = [
            'canape' => 'Canape',
            'canape_convertible' => 'Canape convertible',
            'table_manger' => 'Table a manger',
            'bureau' => 'Bureau',
            'livres' => 'Livres',
            'jeux_societe' => 'Jeux de societe',
        ];

        // Literie / linge
        $literie = [
            'linge_lit_fourni' => 'Linge de lit',
            'serviettes_fournies' => 'Serviettes',
            'oreillers_supplementaires' => 'Oreillers supplementaires',
            'couvertures_supplementaires' => 'Couvertures supplementaires',
        ];

        // Salle de bain
        $sdb = [
            'baignoire' => 'Baignoire',
            'douche' => 'Douche',
            'seche_cheveux' => 'Seche-cheveux',
            'produits_toilette' => 'Produits de toilette',
        ];

        // Chauffage / climatisation
        $confort = [
            'climatisation' => 'Climatisation',
            'chauffage' => 'Chauffage',
            'ventilateur' => 'Ventilateur',
        ];

        // Exterieur
        $exterieur = [
            'balcon' => 'Balcon',
            'terrasse' => 'Terrasse',
            'jardin' => 'Jardin',
            'parking' => 'Parking',
            'barbecue' => 'Barbecue',
            'salon_jardin' => 'Salon de jardin',
        ];

        // Securite
        $securite = [
            'detecteur_fumee' => 'Detecteur de fumee',
            'detecteur_co' => 'Detecteur CO',
            'extincteur' => 'Extincteur',
            'trousse_secours' => 'Trousse de secours',
            'coffre_fort' => 'Coffre-fort',
        ];

        // Enfants
        $enfants = [
            'lit_bebe' => 'Lit bebe',
            'chaise_haute' => 'Chaise haute',
            'barriere_securite' => 'Barriere securite',
            'jeux_enfants' => 'Jeux enfants',
        ];

        $sections = [
            'Cuisine' => $cuisine,
            'Entretien' => $entretien,
            'Multimedia' => $multimedia,
            'Mobilier' => $mobilier,
            'Literie / Linge' => $literie,
            'Salle de bain' => $sdb,
            'Confort' => $confort,
            'Exterieur' => $exterieur,
            'Securite' => $securite,
            'Enfants' => $enfants,
        ];

        $insertStmt = $conn->prepare(
            "INSERT INTO checkup_items (session_id, categorie, nom_item) VALUES (?, ?, ?)"
        );

        foreach ($sections as $categorie => $items) {
            foreach ($items as $field => $label) {
                // N'ajouter que les equipements qui sont censes etre presents
                if (isset($equip[$field]) && $equip[$field]) {
                    $insertStmt->execute([$session_id, $categorie, $label]);
                }
            }
        }
    }

    // Ajouter les objets de l'inventaire (dernier inventaire valide)
    $stmt = $conn->prepare("
        SELECT io.nom_objet, io.quantite
        FROM inventaire_objets io
        INNER JOIN sessions_inventaire si ON io.session_id = si.id
        WHERE si.logement_id = ? AND si.statut = 'terminee'
        ORDER BY si.id DESC
    ");
    $stmt->execute([$logement_id]);
    $objets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $insertStmt = $conn->prepare(
        "INSERT INTO checkup_items (session_id, categorie, nom_item) VALUES (?, ?, ?)"
    );
    foreach ($objets as $obj) {
        $label = $obj['nom_objet'];
        if ($obj['quantite'] > 1) {
            $label .= ' (x' . $obj['quantite'] . ')';
        }
        $insertStmt->execute([$session_id, 'Inventaire', $label]);
    }

    // Ajouter les items d'etat general
    $etatGeneral = [
        'Proprete generale du logement',
        'Odeurs (pas de mauvaises odeurs)',
        'Sols propres',
        'Vitres / fenetres propres',
        'Poubelles videes',
        'Etat des murs (pas de taches/trous)',
        'Etat des portes',
        'Fonctionnement des lumieres',
        'Fonctionnement des prises electriques',
        'Fonctionnement du WiFi',
        'Etat des cles / codes d\'acces',
    ];
    foreach ($etatGeneral as $item) {
        $insertStmt->execute([$session_id, 'Etat general', $item]);
    }

    // Rediriger vers la page de checkup
    header("Location: checkup_faire.php?session_id=" . $session_id);
    exit;
}

// Recuperer les logements
$logements = $conn->query("
    SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement
")->fetchAll(PDO::FETCH_ASSOC);

// Recuperer les checkups recents
$recents = $conn->query("
    SELECT cs.*, l.nom_du_logement,
           COALESCE(i.nom, 'Inconnu') AS nom_intervenant
    FROM checkup_sessions cs
    JOIN liste_logements l ON cs.logement_id = l.id
    LEFT JOIN intervenant i ON cs.intervenant_id = i.id
    ORDER BY cs.created_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkup Logement</title>
    <style>
        .checkup-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 0 15px;
        }
        .checkup-header {
            background: linear-gradient(135deg, #1976d2, #1565c0);
            color: #fff;
            text-align: center;
            padding: 25px 15px;
            border-radius: 15px;
            margin-bottom: 25px;
        }
        .checkup-header h2 {
            margin: 0;
            font-size: 1.4em;
        }
        .checkup-header p {
            margin: 8px 0 0;
            opacity: 0.85;
            font-size: 0.95em;
        }
        .launch-card {
            background: #fff;
            border-radius: 15px;
            padding: 25px 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .launch-card label {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            display: block;
            font-size: 1.05em;
        }
        .launch-card select {
            width: 100%;
            padding: 14px 12px;
            font-size: 1.1em;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background: #fafafa;
            margin-bottom: 18px;
            appearance: auto;
        }
        .launch-card select:focus {
            border-color: #1976d2;
            outline: none;
        }
        .btn-launch {
            width: 100%;
            padding: 16px;
            font-size: 1.15em;
            font-weight: 700;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #43a047, #388e3c);
            color: #fff;
            cursor: pointer;
            transition: transform 0.1s;
        }
        .btn-launch:active {
            transform: scale(0.97);
        }
        .history-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #555;
            margin-bottom: 12px;
        }
        .history-card {
            background: #fff;
            border-radius: 12px;
            padding: 15px 18px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.06);
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-decoration: none;
            color: inherit;
            transition: box-shadow 0.15s;
        }
        .history-card:hover {
            box-shadow: 0 2px 12px rgba(0,0,0,0.12);
        }
        .history-info h4 {
            margin: 0 0 4px;
            font-size: 1em;
            color: #333;
        }
        .history-info small {
            color: #888;
        }
        .history-stats {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .stat-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.82em;
            font-weight: 600;
        }
        .stat-ok { background: #e8f5e9; color: #2e7d32; }
        .stat-problem { background: #fbe9e7; color: #c62828; }
        .stat-absent { background: #fff3e0; color: #e65100; }
        .stat-encours { background: #e3f2fd; color: #1565c0; }
        @media (max-width: 600px) {
            .checkup-container { margin: 10px auto; }
            .checkup-header { padding: 18px 10px; }
            .checkup-header h2 { font-size: 1.2em; }
        }
    </style>
</head>
<body>
<div class="checkup-container">
    <div class="checkup-header">
        <h2><i class="fas fa-clipboard-check"></i> Checkup Logement</h2>
        <p>Verification complete : equipements, inventaire, etat general</p>
    </div>

    <div class="launch-card">
        <form method="POST">
            <label for="logement_id"><i class="fas fa-home"></i> Choisir un logement</label>
            <select name="logement_id" id="logement_id" required>
                <option value="">-- Selectionnez --</option>
                <?php foreach ($logements as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nom_du_logement']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-launch">
                <i class="fas fa-play-circle"></i> Lancer le checkup
            </button>
        </form>
    </div>

    <?php if (!empty($recents)): ?>
    <div class="history-title"><i class="fas fa-history"></i> Checkups recents</div>
    <?php foreach ($recents as $r): ?>
        <a class="history-card" href="<?= $r['statut'] === 'en_cours' ? 'checkup_faire.php?session_id=' . $r['id'] : 'checkup_rapport.php?session_id=' . $r['id'] ?>">
            <div class="history-info">
                <h4><?= htmlspecialchars($r['nom_du_logement']) ?></h4>
                <small><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?> — <?= htmlspecialchars($r['nom_intervenant']) ?></small>
            </div>
            <div class="history-stats">
                <?php if ($r['statut'] === 'en_cours'): ?>
                    <span class="stat-badge stat-encours">En cours</span>
                <?php else: ?>
                    <?php if ($r['nb_ok'] > 0): ?><span class="stat-badge stat-ok"><?= $r['nb_ok'] ?> OK</span><?php endif; ?>
                    <?php if ($r['nb_problemes'] > 0): ?><span class="stat-badge stat-problem"><?= $r['nb_problemes'] ?> pb</span><?php endif; ?>
                    <?php if ($r['nb_absents'] > 0): ?><span class="stat-badge stat-absent"><?= $r['nb_absents'] ?> abs</span><?php endif; ?>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
