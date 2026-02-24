<?php
/**
 * Simulateur de revenus locatifs - Frenchy Conciergerie
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$settings = getAllSettings($conn);
$message = '';
$result = null;

// Traitement AJAX (depuis l'index)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    // Vérifier la connexion
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Pas de connexion base de données']);
        exit;
    }

    $surface = floatval($_POST['surface'] ?? 0);
    $capacite = intval($_POST['capacite'] ?? 0);
    $ville = trim($_POST['ville'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $centreVille = intval($_POST['centre_ville'] ?? 0);
    $fibre = intval($_POST['fibre'] ?? 0);
    $equipementsSpeciaux = intval($_POST['equipements_speciaux'] ?? 0);
    $machineCafe = intval($_POST['machine_cafe'] ?? 0);
    $machineLaver = intval($_POST['machine_laver'] ?? 0);
    $autreEquipement = trim($_POST['autre_equipement'] ?? '');
    $tarifNuitEstime = floatval($_POST['tarif_nuit_estime'] ?? 0);
    $revenuMensuelEstime = floatval($_POST['revenu_mensuel_estime'] ?? 0);

    if ($surface > 0 && $capacite > 0 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            // Créer la table si elle n'existe pas
            $conn->exec("CREATE TABLE IF NOT EXISTS FC_simulations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                surface DECIMAL(10,2),
                capacite INT,
                ville VARCHAR(100),
                centre_ville TINYINT(1) DEFAULT 0,
                fibre TINYINT(1) DEFAULT 0,
                equipements_speciaux TINYINT(1) DEFAULT 0,
                machine_cafe TINYINT(1) DEFAULT 0,
                machine_laver TINYINT(1) DEFAULT 0,
                autre_equipement VARCHAR(255),
                tarif_nuit_estime DECIMAL(10,2),
                revenu_mensuel_estime DECIMAL(10,2),
                contacted TINYINT(1) DEFAULT 0,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $stmt = $conn->prepare("INSERT INTO FC_simulations (email, surface, capacite, ville, centre_ville, fibre, equipements_speciaux, machine_cafe, machine_laver, autre_equipement, tarif_nuit_estime, revenu_mensuel_estime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([
                $email, $surface, $capacite, $ville,
                $centreVille, $fibre, $equipementsSpeciaux, $machineCafe, $machineLaver,
                $autreEquipement, $tarifNuitEstime, $revenuMensuelEstime
            ]);

            if ($result) {
                echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Insert failed: ' . implode(', ', $stmt->errorInfo())]);
            }
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'PDO Exception: ' . $e->getMessage()]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'error' => 'Données invalides: surface=' . $surface . ', capacite=' . $capacite . ', email=' . $email]);
    exit;
}

// Tarifs moyens par nuit selon la ville et la capacité (données estimatives)
$tarifsMoyens = [
    'compiegne' => ['base' => 55, 'par_personne' => 8, 'par_m2' => 0.5],
    'paris' => ['base' => 95, 'par_personne' => 15, 'par_m2' => 1.2],
    'margny-les-compiegne' => ['base' => 50, 'par_personne' => 7, 'par_m2' => 0.4],
    'venette' => ['base' => 48, 'par_personne' => 7, 'par_m2' => 0.4],
    'lacroix-saint-ouen' => ['base' => 45, 'par_personne' => 6, 'par_m2' => 0.35],
    'jaux' => ['base' => 50, 'par_personne' => 7, 'par_m2' => 0.4],
    'choisy-au-bac' => ['base' => 45, 'par_personne' => 6, 'par_m2' => 0.35],
    'autre' => ['base' => 50, 'par_personne' => 7, 'par_m2' => 0.4],
];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $surface = floatval($_POST['surface'] ?? 0);
    $capacite = intval($_POST['capacite'] ?? 0);
    $ville = strtolower(trim($_POST['ville'] ?? ''));
    $email = trim($_POST['email'] ?? '');

    // Validation
    if ($surface < 10 || $surface > 500) {
        $message = 'La surface doit être comprise entre 10 et 500 m²';
    } elseif ($capacite < 1 || $capacite > 20) {
        $message = 'La capacité doit être comprise entre 1 et 20 personnes';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Veuillez entrer une adresse email valide';
    } else {
        // Trouver le tarif de la ville
        $villeKey = 'autre';
        foreach (array_keys($tarifsMoyens) as $key) {
            if (strpos($ville, $key) !== false || strpos($key, $ville) !== false) {
                $villeKey = $key;
                break;
            }
        }

        $tarifs = $tarifsMoyens[$villeKey];

        // Calcul du tarif par nuit estimé
        $tarifNuit = $tarifs['base'] + ($capacite * $tarifs['par_personne']) + ($surface * $tarifs['par_m2']);
        $tarifNuit = round($tarifNuit, 0);

        // Taux d'occupation estimé (65-80% selon la saison)
        $tauxOccupation = 0.70; // 70% en moyenne

        // Calcul des revenus
        $nuitsParMois = 30 * $tauxOccupation;
        $revenuBrutMensuel = $tarifNuit * $nuitsParMois;
        $revenuBrutAnnuel = $revenuBrutMensuel * 12;

        // Commission Frenchy (24% pour logement équipé)
        $commissionMensuelle = $revenuBrutMensuel * 0.24;
        $revenuNetMensuel = $revenuBrutMensuel - $commissionMensuelle;
        $revenuNetAnnuel = $revenuNetMensuel * 12;

        $result = [
            'surface' => $surface,
            'capacite' => $capacite,
            'ville' => ucfirst($_POST['ville']),
            'tarif_nuit' => $tarifNuit,
            'taux_occupation' => $tauxOccupation * 100,
            'revenu_brut_mensuel' => $revenuBrutMensuel,
            'revenu_brut_annuel' => $revenuBrutAnnuel,
            'commission_mensuelle' => $commissionMensuelle,
            'revenu_net_mensuel' => $revenuNetMensuel,
            'revenu_net_annuel' => $revenuNetAnnuel,
        ];

        // Enregistrer la demande dans la base de données
        try {
            $stmt = $conn->prepare("INSERT INTO FC_simulations (email, surface, capacite, ville, tarif_nuit_estime, revenu_mensuel_estime) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$email, $surface, $capacite, $_POST['ville'], $tarifNuit, $revenuNetMensuel]);
        } catch (PDOException $e) {
            // Table n'existe peut-être pas encore, on continue
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulateur de Revenus - <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></title>
    <meta name="description" content="Estimez vos revenus locatifs avec notre simulateur gratuit. Découvrez combien peut vous rapporter votre bien en location saisonnière.">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bleu-frenchy: #1E3A8A;
            --bleu-clair: #3B82F6;
            --rouge-frenchy: #EF4444;
            --vert: #10B981;
            --gris-clair: #F3F4F6;
            --gris-fonce: #1F2937;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--bleu-frenchy) 0%, #3B82F6 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .back-link {
            display: inline-block;
            color: white;
            text-decoration: none;
            margin-bottom: 1rem;
            opacity: 0.8;
            transition: opacity 0.3s;
        }

        .back-link:hover { opacity: 1; }

        .simulator-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .form-section {
            padding: 2.5rem;
        }

        .form-section h2 {
            color: var(--bleu-frenchy);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gris-fonce);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--bleu-clair);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-group small {
            display: block;
            margin-top: 0.3rem;
            color: #6B7280;
            font-size: 0.85rem;
        }

        .btn-calculate {
            width: 100%;
            padding: 1.2rem;
            background: linear-gradient(135deg, var(--bleu-frenchy) 0%, var(--bleu-clair) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            margin-top: 1rem;
        }

        .btn-calculate:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(30, 58, 138, 0.3);
        }

        .error-message {
            background: #FEE2E2;
            color: #991B1B;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--rouge-frenchy);
        }

        .results-section {
            background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%);
            padding: 2.5rem;
            border-top: 3px solid var(--vert);
        }

        .results-section h2 {
            color: var(--vert);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .result-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .result-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--bleu-frenchy);
            margin-bottom: 0.3rem;
        }

        .result-card .value.highlight {
            color: var(--vert);
            font-size: 2.5rem;
        }

        .result-card .label {
            color: #6B7280;
            font-size: 0.9rem;
        }

        .summary-box {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid var(--bleu-frenchy);
        }

        .summary-box h3 {
            color: var(--bleu-frenchy);
            margin-bottom: 1rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1rem;
            padding-top: 1rem;
            color: var(--vert);
        }

        .cta-section {
            background: var(--bleu-frenchy);
            padding: 2rem;
            text-align: center;
            color: white;
        }

        .cta-section h3 {
            margin-bottom: 1rem;
        }

        .cta-section p {
            margin-bottom: 1.5rem;
            opacity: 0.9;
        }

        .btn-cta {
            display: inline-block;
            padding: 1rem 2.5rem;
            background: white;
            color: var(--bleu-frenchy);
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            transition: transform 0.3s;
        }

        .btn-cta:hover {
            transform: scale(1.05);
        }

        .disclaimer {
            margin-top: 2rem;
            padding: 1rem;
            background: #FEF3C7;
            border-radius: 10px;
            font-size: 0.85rem;
            color: #92400E;
        }

        @media (max-width: 600px) {
            body { padding: 1rem; }
            .header h1 { font-size: 1.8rem; }
            .form-section, .results-section { padding: 1.5rem; }
            .result-card .value { font-size: 1.5rem; }
            .result-card .value.highlight { font-size: 2rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Retour au site</a>

        <div class="header">
            <h1>Simulateur de Revenus</h1>
            <p>Estimez combien votre bien peut vous rapporter en location saisonnière</p>
        </div>

        <div class="simulator-card">
            <div class="form-section">
                <h2>Décrivez votre bien</h2>

                <?php if ($message): ?>
                <div class="error-message"><?= e($message) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="surface">Surface (m²)</label>
                            <input type="number" id="surface" name="surface" min="10" max="500" required
                                   value="<?= e($_POST['surface'] ?? '') ?>" placeholder="Ex: 45">
                            <small>Surface habitable du logement</small>
                        </div>

                        <div class="form-group">
                            <label for="capacite">Capacité d'accueil</label>
                            <input type="number" id="capacite" name="capacite" min="1" max="20" required
                                   value="<?= e($_POST['capacite'] ?? '') ?>" placeholder="Ex: 4">
                            <small>Nombre de voyageurs maximum</small>
                        </div>

                        <div class="form-group">
                            <label for="ville">Ville</label>
                            <input type="text" id="ville" name="ville" required
                                   value="<?= e($_POST['ville'] ?? '') ?>" placeholder="Ex: Compiègne"
                                   list="villes-list">
                            <datalist id="villes-list">
                                <option value="Compiègne">
                                <option value="Paris">
                                <option value="Margny-lès-Compiègne">
                                <option value="Venette">
                                <option value="Lacroix-Saint-Ouen">
                                <option value="Jaux">
                                <option value="Choisy-au-Bac">
                            </datalist>
                            <small>Localisation du bien</small>
                        </div>

                        <div class="form-group">
                            <label for="email">Votre email</label>
                            <input type="email" id="email" name="email" required
                                   value="<?= e($_POST['email'] ?? '') ?>" placeholder="votre@email.com">
                            <small>Pour recevoir votre avis de valeur détaillé</small>
                        </div>
                    </div>

                    <button type="submit" class="btn-calculate">
                        Calculer mes revenus potentiels
                    </button>
                </form>
            </div>

            <?php if ($result): ?>
            <div class="results-section">
                <h2>Avis de valeur locative</h2>

                <div class="results-grid">
                    <div class="result-card">
                        <div class="value"><?= number_format($result['tarif_nuit'], 0, ',', ' ') ?> €</div>
                        <div class="label">Tarif/nuit estimé</div>
                    </div>
                    <div class="result-card">
                        <div class="value"><?= $result['taux_occupation'] ?>%</div>
                        <div class="label">Taux d'occupation moyen</div>
                    </div>
                    <div class="result-card">
                        <div class="value highlight"><?= number_format($result['revenu_net_mensuel'], 0, ',', ' ') ?> €</div>
                        <div class="label">Revenu net/mois</div>
                    </div>
                    <div class="result-card">
                        <div class="value highlight"><?= number_format($result['revenu_net_annuel'], 0, ',', ' ') ?> €</div>
                        <div class="label">Revenu net/an</div>
                    </div>
                </div>

                <div class="summary-box">
                    <h3>Détail de l'avis de valeur</h3>
                    <div class="summary-row">
                        <span>Bien</span>
                        <span><?= e($result['ville']) ?> - <?= $result['surface'] ?>m² - <?= $result['capacite'] ?> pers.</span>
                    </div>
                    <div class="summary-row">
                        <span>Revenu brut mensuel</span>
                        <span><?= number_format($result['revenu_brut_mensuel'], 0, ',', ' ') ?> €</span>
                    </div>
                    <div class="summary-row">
                        <span>Commission Frenchy (24%)</span>
                        <span>-<?= number_format($result['commission_mensuelle'], 0, ',', ' ') ?> €</span>
                    </div>
                    <div class="summary-row">
                        <span>Votre revenu net mensuel</span>
                        <span><?= number_format($result['revenu_net_mensuel'], 0, ',', ' ') ?> €</span>
                    </div>
                </div>

                <div class="disclaimer">
                    <strong>Mentions légales :</strong> Cet avis de valeur est fourni à titre purement indicatif et informatif.
                    Il ne constitue en aucun cas une garantie de revenus, un engagement contractuel, ni une promesse de résultats.
                    Les montants sont calculés sur la base de moyennes de marché et peuvent varier significativement selon de nombreux facteurs.
                    Seule une étude personnalisée peut fournir une analyse précise. En utilisant ce simulateur, vous consentez à être recontacté.
                </div>
            </div>

            <div class="cta-section">
                <h3>Prêt à maximiser vos revenus ?</h3>
                <p>Nos experts sont là pour vous accompagner et optimiser la rentabilité de votre bien.</p>
                <a href="index.php#contact" class="btn-cta">Nous contacter</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
