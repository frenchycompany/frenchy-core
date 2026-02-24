<?php
/**
 * Calculateur de revenus locatifs - Frenchy Conciergerie
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

$security = new Security($conn);
$settings = getAllSettings($conn);

// Traitement AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    // Rate limiting
    $rateCheck = $security->checkRateLimit('calculateur');
    if (!$rateCheck['allowed']) {
        echo json_encode(['success' => false, 'message' => $rateCheck['message']]);
        exit;
    }

    // Validation CSRF
    if (!$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Token de sécurité invalide']);
        exit;
    }

    $security->recordAttempt('calculateur');

    // Récupération des données
    $type_bien = $security->sanitize($_POST['type_bien'] ?? 'Appartement');
    $localisation = $security->sanitize($_POST['localisation'] ?? 'Compiègne');
    $surface = (int)($_POST['surface'] ?? 30);
    $nb_chambres = (int)($_POST['nb_chambres'] ?? 1);
    $equipements = $_POST['equipements'] ?? [];
    $email = $security->sanitize($_POST['email'] ?? '', 'email');

    // Calcul de l'estimation
    $estimation = calculerRevenus($type_bien, $localisation, $surface, $nb_chambres, $equipements);

    // Sauvegarde de la simulation
    if (!empty($email) && $security->validateEmail($email)) {
        $stmt = $conn->prepare("INSERT INTO FC_simulations (email, type_bien, localisation, surface, nb_chambres, equipements, estimation_basse, estimation_haute, estimation_moyenne, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $email,
            $type_bien,
            $localisation,
            $surface,
            $nb_chambres,
            json_encode($equipements),
            $estimation['basse'],
            $estimation['haute'],
            $estimation['moyenne'],
            $security->getClientIP()
        ]);

        $security->trackConversion('simulation', 'calculateur', [
            'type_bien' => $type_bien,
            'surface' => $surface
        ]);
    }

    echo json_encode([
        'success' => true,
        'estimation' => $estimation
    ]);
    exit;
}

/**
 * Fonction de calcul des revenus estimés
 */
function calculerRevenus($type_bien, $localisation, $surface, $nb_chambres, $equipements) {
    // Prix de base par nuit selon le type et la localisation
    $prix_base = [
        'Studio' => ['Compiègne' => 55, 'Autre' => 45],
        'Appartement' => ['Compiègne' => 70, 'Autre' => 55],
        'Maison' => ['Compiègne' => 95, 'Autre' => 75],
        'Loft' => ['Compiègne' => 85, 'Autre' => 70],
        'Duplex' => ['Compiègne' => 90, 'Autre' => 75]
    ];

    // Taux d'occupation moyen
    $taux_occupation = [
        'Compiègne' => 0.65,
        'Autre' => 0.55
    ];

    $loc_key = ($localisation === 'Compiègne' || strpos($localisation, 'Compiègne') !== false) ? 'Compiègne' : 'Autre';
    $type_key = isset($prix_base[$type_bien]) ? $type_bien : 'Appartement';

    $prix_nuit = $prix_base[$type_key][$loc_key];
    $occupation = $taux_occupation[$loc_key];

    // Ajustements selon la surface
    if ($surface > 50) {
        $prix_nuit *= 1.15;
    } elseif ($surface > 70) {
        $prix_nuit *= 1.30;
    } elseif ($surface > 100) {
        $prix_nuit *= 1.50;
    }

    // Ajustements selon le nombre de chambres
    $prix_nuit += ($nb_chambres - 1) * 10;

    // Bonus équipements
    $bonus_equipements = 0;
    if (is_array($equipements)) {
        $bonus_map = [
            'parking' => 5,
            'wifi' => 0,
            'climatisation' => 8,
            'jacuzzi' => 20,
            'piscine' => 25,
            'jardin' => 10,
            'terrasse' => 8,
            'vue' => 10,
            'netflix' => 3,
            'lave_linge' => 2
        ];
        foreach ($equipements as $equip) {
            $bonus_equipements += $bonus_map[$equip] ?? 0;
        }
    }
    $prix_nuit += $bonus_equipements;

    // Calcul des revenus mensuels
    $nuits_mois = 30;
    $nuits_louees = $nuits_mois * $occupation;

    $revenu_brut_moyen = $prix_nuit * $nuits_louees;
    $revenu_brut_bas = $revenu_brut_moyen * 0.8;
    $revenu_brut_haut = $revenu_brut_moyen * 1.25;

    // Revenus annuels
    $revenu_annuel_moyen = $revenu_brut_moyen * 12;
    $revenu_annuel_bas = $revenu_brut_bas * 12;
    $revenu_annuel_haut = $revenu_brut_haut * 12;

    return [
        'prix_nuit' => round($prix_nuit, 2),
        'taux_occupation' => round($occupation * 100),
        'nuits_louees' => round($nuits_louees),
        'mensuel' => [
            'basse' => round($revenu_brut_bas),
            'moyenne' => round($revenu_brut_moyen),
            'haute' => round($revenu_brut_haut)
        ],
        'annuel' => [
            'basse' => round($revenu_annuel_bas),
            'moyenne' => round($revenu_annuel_moyen),
            'haute' => round($revenu_annuel_haut)
        ],
        'basse' => round($revenu_annuel_bas),
        'haute' => round($revenu_annuel_haut),
        'moyenne' => round($revenu_annuel_moyen)
    ];
}

$security->trackVisit('/calculateur');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculateur de revenus locatifs - <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></title>
    <meta name="description" content="Estimez les revenus potentiels de votre bien en location saisonnière avec notre calculateur gratuit.">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .calculateur-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .calculateur-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 768px) {
            .calculateur-grid {
                grid-template-columns: 1fr;
            }
        }

        .calculateur-form {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        .calculateur-form h2 {
            color: var(--bleu-frenchy);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
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
            padding: 0.8rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--bleu-clair);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .range-container {
            position: relative;
        }

        .range-value {
            position: absolute;
            right: 0;
            top: 0;
            background: var(--bleu-clair);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        input[type="range"] {
            width: 100%;
            height: 8px;
            border-radius: 4px;
            background: #e5e7eb;
            outline: none;
            -webkit-appearance: none;
        }

        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--bleu-clair);
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }

        .equipements-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }

        .equipement-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: var(--gris-clair);
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .equipement-item:hover {
            background: #e5e7eb;
        }

        .equipement-item input {
            width: auto;
        }

        .btn-calculer {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--bleu-frenchy), var(--bleu-clair));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-calculer:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(30, 58, 138, 0.4);
        }

        .btn-calculer:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* Résultats */
        .resultats-container {
            background: linear-gradient(135deg, var(--bleu-frenchy), var(--bleu-clair));
            padding: 2rem;
            border-radius: 15px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .resultats-container h2 {
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .resultat-placeholder {
            text-align: center;
            padding: 3rem 1rem;
            opacity: 0.9;
        }

        .resultat-placeholder .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .resultats-content {
            display: none;
        }

        .resultats-content.visible {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .resultat-principal {
            text-align: center;
            margin-bottom: 2rem;
        }

        .resultat-principal .montant {
            font-size: 3.5rem;
            font-weight: bold;
            margin: 0.5rem 0;
        }

        .resultat-principal .periode {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .resultats-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .detail-card {
            background: rgba(255,255,255,0.15);
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
        }

        .detail-card .value {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .detail-card .label {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .fourchette {
            background: rgba(255,255,255,0.1);
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
        }

        .fourchette-title {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }

        .fourchette-range {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
        }

        .cta-contact {
            margin-top: 1.5rem;
            text-align: center;
        }

        .cta-contact a {
            display: inline-block;
            background: white;
            color: var(--bleu-frenchy);
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: transform 0.2s;
        }

        .cta-contact a:hover {
            transform: scale(1.05);
        }

        .loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }

        .loading.visible {
            display: block;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .disclaimer {
            font-size: 0.8rem;
            opacity: 0.8;
            margin-top: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section class="hero" style="padding: 2rem 0;">
        <div class="container">
            <h1>Calculateur de Revenus Locatifs</h1>
            <p>Estimez gratuitement les revenus potentiels de votre bien en location saisonnière</p>
        </div>
    </section>

    <div class="calculateur-container">
        <div class="calculateur-grid">
            <!-- Formulaire -->
            <div class="calculateur-form">
                <h2>Décrivez votre bien</h2>
                <form id="calculateur-form">
                    <?= $security->csrfField() ?>
                    <input type="hidden" name="ajax" value="1">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="type_bien">Type de bien</label>
                            <select name="type_bien" id="type_bien">
                                <option value="Studio">Studio</option>
                                <option value="Appartement" selected>Appartement</option>
                                <option value="Maison">Maison</option>
                                <option value="Loft">Loft</option>
                                <option value="Duplex">Duplex</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="localisation">Localisation</label>
                            <select name="localisation" id="localisation">
                                <option value="Compiègne">Compiègne</option>
                                <option value="Compiègne Centre">Compiègne Centre</option>
                                <option value="Proche Compiègne">Proche Compiègne</option>
                                <option value="Oise">Autre (Oise)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="surface">Surface (m²)</label>
                        <div class="range-container">
                            <span class="range-value" id="surface-value">30 m²</span>
                            <input type="range" name="surface" id="surface" min="15" max="200" value="30">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="nb_chambres">Nombre de chambres</label>
                        <div class="range-container">
                            <span class="range-value" id="chambres-value">1</span>
                            <input type="range" name="nb_chambres" id="nb_chambres" min="0" max="6" value="1">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Équipements</label>
                        <div class="equipements-grid">
                            <label class="equipement-item">
                                <input type="checkbox" name="equipements[]" value="parking"> Parking
                            </label>
                            <label class="equipement-item">
                                <input type="checkbox" name="equipements[]" value="wifi" checked> WiFi
                            </label>
                            <label class="equipement-item">
                                <input type="checkbox" name="equipements[]" value="climatisation"> Climatisation
                            </label>
                            <label class="equipement-item">
                                <input type="checkbox" name="equipements[]" value="terrasse"> Terrasse
                            </label>
                            <label class="equipement-item">
                                <input type="checkbox" name="equipements[]" value="jardin"> Jardin
                            </label>
                            <label class="equipement-item">
                                <input type="checkbox" name="equipements[]" value="vue"> Belle vue
                            </label>
                            <label class="equipement-item">
                                <input type="checkbox" name="equipements[]" value="jacuzzi"> Jacuzzi
                            </label>
                            <label class="equipement-item">
                                <input type="checkbox" name="equipements[]" value="piscine"> Piscine
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email (optionnel - pour recevoir l'estimation)</label>
                        <input type="email" name="email" id="email" placeholder="votre@email.fr">
                    </div>

                    <button type="submit" class="btn-calculer" id="btn-calculer">
                        Calculer mes revenus
                    </button>
                </form>
            </div>

            <!-- Résultats -->
            <div class="resultats-container">
                <h2>Estimation de vos revenus</h2>

                <div class="resultat-placeholder" id="resultat-placeholder">
                    <div class="icon">📊</div>
                    <p>Remplissez le formulaire pour obtenir une estimation personnalisée de vos revenus locatifs potentiels.</p>
                </div>

                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <p>Calcul en cours...</p>
                </div>

                <div class="resultats-content" id="resultats-content">
                    <div class="resultat-principal">
                        <div class="periode">Revenus annuels estimés</div>
                        <div class="montant" id="revenu-annuel">0 €</div>
                    </div>

                    <div class="resultats-details">
                        <div class="detail-card">
                            <div class="value" id="revenu-mensuel">0 €</div>
                            <div class="label">Par mois</div>
                        </div>
                        <div class="detail-card">
                            <div class="value" id="prix-nuit">0 €</div>
                            <div class="label">Par nuit</div>
                        </div>
                        <div class="detail-card">
                            <div class="value" id="taux-occupation">0%</div>
                            <div class="label">Taux d'occupation</div>
                        </div>
                        <div class="detail-card">
                            <div class="value" id="nuits-louees">0</div>
                            <div class="label">Nuits/mois</div>
                        </div>
                    </div>

                    <div class="fourchette">
                        <div class="fourchette-title">Fourchette annuelle estimée</div>
                        <div class="fourchette-range">
                            <span id="fourchette-basse">0 €</span>
                            <span>→</span>
                            <span id="fourchette-haute">0 €</span>
                        </div>
                    </div>

                    <div class="cta-contact">
                        <a href="index.php#contact">Contactez-nous pour en savoir plus</a>
                    </div>

                    <p class="disclaimer">* Estimation indicative basée sur les données du marché local. Les revenus réels peuvent varier.</p>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('calculateur-form');
            const surfaceInput = document.getElementById('surface');
            const chambresInput = document.getElementById('nb_chambres');
            const surfaceValue = document.getElementById('surface-value');
            const chambresValue = document.getElementById('chambres-value');

            // Mise à jour des valeurs des sliders
            surfaceInput.addEventListener('input', function() {
                surfaceValue.textContent = this.value + ' m²';
            });

            chambresInput.addEventListener('input', function() {
                chambresValue.textContent = this.value;
            });

            // Soumission du formulaire
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const btn = document.getElementById('btn-calculer');
                const placeholder = document.getElementById('resultat-placeholder');
                const loading = document.getElementById('loading');
                const results = document.getElementById('resultats-content');

                btn.disabled = true;
                btn.textContent = 'Calcul en cours...';
                placeholder.style.display = 'none';
                results.classList.remove('visible');
                loading.classList.add('visible');

                try {
                    const formData = new FormData(form);
                    const response = await fetch('calculateur.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        const est = data.estimation;

                        document.getElementById('revenu-annuel').textContent = formatMoney(est.annuel.moyenne);
                        document.getElementById('revenu-mensuel').textContent = formatMoney(est.mensuel.moyenne);
                        document.getElementById('prix-nuit').textContent = formatMoney(est.prix_nuit);
                        document.getElementById('taux-occupation').textContent = est.taux_occupation + '%';
                        document.getElementById('nuits-louees').textContent = est.nuits_louees;
                        document.getElementById('fourchette-basse').textContent = formatMoney(est.annuel.basse);
                        document.getElementById('fourchette-haute').textContent = formatMoney(est.annuel.haute);

                        loading.classList.remove('visible');
                        results.classList.add('visible');
                    } else {
                        alert(data.message || 'Une erreur est survenue');
                        placeholder.style.display = 'block';
                        loading.classList.remove('visible');
                    }
                } catch (error) {
                    alert('Erreur de connexion. Veuillez réessayer.');
                    placeholder.style.display = 'block';
                    loading.classList.remove('visible');
                }

                btn.disabled = false;
                btn.textContent = 'Calculer mes revenus';
            });

            function formatMoney(amount) {
                return new Intl.NumberFormat('fr-FR', {
                    style: 'currency',
                    currency: 'EUR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }).format(amount);
            }
        });
    </script>
</body>
</html>
