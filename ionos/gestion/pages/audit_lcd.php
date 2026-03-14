<?php
/**
 * audit_lcd.php — Rapport d'analyse d'investissement LCD
 * Frenchy Conciergerie — Tunnel de vente / acquisition propriétaires
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Auth.php';

$auth = new Auth($conn);
$auth->requireAdmin('../login.php');

$csrf_token = $auth->csrfToken();

// Récupération des données (GET ou POST)
$d = array_merge($_GET, $_POST);

// Helper : récupérer une valeur ou placeholder
function v($key, $default = '[À compléter]') {
    global $d;
    return isset($d[$key]) && $d[$key] !== '' ? htmlspecialchars($d[$key]) : $default;
}
function vn($key, $default = 0) {
    global $d;
    return isset($d[$key]) && $d[$key] !== '' ? floatval($d[$key]) : $default;
}

// Le formulaire est toujours visible pour les admins connectés
// Passer ?print=1 pour masquer le formulaire (mode rapport seul)
$hideForm = isset($_GET['print']) && $_GET['print'] == '1';

// Calcul couleur badge risque
function badgeRisque($val) {
    $val = strtolower(trim($val));
    if (in_array($val, ['faible', 'low', 'vert'])) return ['Faible', '#43A047', '#E8F5E9'];
    if (in_array($val, ['modéré', 'moyen', 'moderate', 'orange'])) return ['Modéré', '#FB8C00', '#FFF3E0'];
    return ['Élevé', '#E53935', '#FFEBEE'];
}

function scoreColor($score) {
    if ($score >= 7) return '#43A047';
    if ($score >= 4) return '#FB8C00';
    return '#E53935';
}

function scoreBg($score) {
    if ($score >= 7) return '#E8F5E9';
    if ($score >= 4) return '#FFF3E0';
    return '#FFEBEE';
}

function badgeLabel($score) {
    if ($score >= 7) return 'Favorable';
    if ($score >= 4) return 'À surveiller';
    return 'Défavorable';
}

// Données scénarios
$prix_achat      = vn('prix_achat', 0);
$frais_notaire   = vn('frais_notaire', 0);
$travaux         = vn('travaux_estimes', 0);
$ameublement     = vn('ameublement', 0);
$total_investi   = $prix_achat + $frais_notaire + $travaux + $ameublement;
$mensualite      = vn('mensualite_credit', 0);
$charges_copro   = vn('charges_copro', 0);
$taxe_fonciere   = vn('taxe_fonciere', 0);
$commission_pct  = vn('commission_conciergerie', 20);
$charges_loc     = vn('charges_locataires', 0);
$tarif_nuitee    = vn('tarif_nuitee_moyen', 0);

$charges_fixes = $mensualite + $charges_copro + ($taxe_fonciere / 12) + $charges_loc;

$scenarios = [
    ['label' => 'Pessimiste', 'taux' => 55, 'color' => '#E53935'],
    ['label' => 'Réaliste',   'taux' => 72, 'color' => '#FB8C00'],
    ['label' => 'Optimiste',  'taux' => 85, 'color' => '#43A047'],
];

foreach ($scenarios as &$sc) {
    $nuitees = round(30 * $sc['taux'] / 100, 1);
    $revenus_bruts = $nuitees * $tarif_nuitee;
    $commission = $revenus_bruts * $commission_pct / 100;
    $cashflow = $revenus_bruts - $commission - $charges_fixes;
    $rendement = $total_investi > 0 ? round(($revenus_bruts * 12 / $total_investi) * 100, 2) : 0;
    $seuil = ($tarif_nuitee > 0 && (1 - $commission_pct/100) > 0) ? round($charges_fixes / ($tarif_nuitee * (1 - $commission_pct/100)), 1) : 0;
    $sc['nuitees'] = $nuitees;
    $sc['revenus_bruts'] = $revenus_bruts;
    $sc['commission'] = $commission;
    $sc['cashflow'] = $cashflow;
    $sc['rendement'] = $rendement;
    $sc['seuil'] = $seuil;
}
unset($sc);

// Risque réglementaire
$risque_val = isset($d['risque_reglementaire']) ? $d['risque_reglementaire'] : 'modéré';
$risque = badgeRisque($risque_val);

// Scores
$score_global = vn('score_global', 0);
$score_attractivite = vn('score_attractivite', 0);
$score_concurrence  = vn('score_concurrence', 0);
$score_potentiel    = vn('score_potentiel_tarifaire', 0);
$score_saisonnalite = vn('score_saisonnalite', 0);

// Scores maturité investisseur
$mat_connaissance  = vn('mat_connaissance', 0);
$mat_tresorerie    = vn('mat_tresorerie', 0);
$mat_tolerance     = vn('mat_tolerance', 0);
$mat_fiscal        = vn('mat_fiscal', 0);
$mat_operationnel  = vn('mat_operationnel', 0);

// Verdicts par axe
$verdict_reglementation = vn('verdict_reglementation', 5);
$verdict_marche         = vn('verdict_marche', 5);
$verdict_rentabilite    = vn('verdict_rentabilite', 5);
$verdict_profil         = vn('verdict_profil', 5);

$ref_dossier = v('ref_dossier', 'FC-' . date('Ymd') . '-' . strtoupper(substr(md5(v('nom_client', '')), 0, 4)));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit LCD — <?= v('nom_client', 'Rapport') ?> | Frenchy Conciergerie</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #FFFFFF;
            color: #1a1a1a;
            line-height: 1.6;
            font-size: 14px;
        }

        /* Header */
        .report-header {
            background: #1B3A6B;
            color: #fff;
            padding: 30px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .report-header .logo {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 2px;
        }
        .report-header .logo span { color: #E53935; }
        .report-header .subtitle {
            font-size: 14px;
            opacity: 0.85;
            margin-top: 4px;
            font-weight: 300;
            letter-spacing: 1px;
        }
        .report-header .meta {
            text-align: right;
            font-size: 13px;
            line-height: 1.8;
        }
        .report-header .meta strong { color: #E53935; }

        /* Container */
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 30px 40px;
        }

        /* Client info bar */
        .client-bar {
            background: #F5F7FA;
            border-left: 4px solid #1B3A6B;
            padding: 20px 24px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
        }
        .client-bar .item label {
            font-size: 11px;
            text-transform: uppercase;
            color: #888;
            letter-spacing: 0.5px;
        }
        .client-bar .item p {
            font-size: 15px;
            font-weight: 600;
            color: #1B3A6B;
            margin-top: 2px;
        }

        /* Section */
        .section {
            margin-bottom: 35px;
            border-left: 4px solid #E53935;
            padding-left: 24px;
        }
        .section-number {
            display: inline-block;
            background: #E53935;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 3px;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .section h2 {
            font-size: 20px;
            color: #1B3A6B;
            margin-bottom: 16px;
            font-weight: 700;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 13px;
        }
        table th {
            background: #1B3A6B;
            color: #fff;
            padding: 10px 14px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table td {
            padding: 10px 14px;
            border-bottom: 1px solid #E0E0E0;
        }
        table tr:nth-child(even) td { background: #F5F7FA; }
        table .label-cell {
            background: #F5F7FA;
            font-weight: 600;
            color: #555;
            width: 45%;
        }

        /* KPI Cards */
        .kpi-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }
        .kpi-card {
            background: #F5F7FA;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            border-top: 3px solid #1B3A6B;
        }
        .kpi-card .kpi-value {
            font-size: 28px;
            font-weight: 800;
            color: #1B3A6B;
        }
        .kpi-card .kpi-label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }
        .kpi-card .kpi-sub {
            font-size: 11px;
            color: #aaa;
            margin-top: 2px;
        }

        /* Score Bars */
        .score-bar-container {
            margin-bottom: 12px;
        }
        .score-bar-label {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        .score-bar-track {
            background: #E0E0E0;
            border-radius: 6px;
            height: 12px;
            overflow: hidden;
        }
        .score-bar-fill {
            height: 100%;
            border-radius: 6px;
            transition: width 0.6s ease;
        }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 6px 18px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        .badge-large {
            font-size: 18px;
            padding: 10px 30px;
        }

        /* Verdict badges row */
        .verdict-badges {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 12px;
            margin-top: 16px;
        }
        .verdict-badge-card {
            text-align: center;
            padding: 16px 10px;
            border-radius: 8px;
            background: #F5F7FA;
        }
        .verdict-badge-card .vb-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #888;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .verdict-badge-card .vb-score {
            font-size: 24px;
            font-weight: 800;
        }
        .verdict-badge-card .vb-text {
            font-size: 11px;
            font-weight: 600;
            margin-top: 4px;
        }

        /* Points forts / vigilance */
        .points-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 16px;
        }
        .point-box {
            padding: 16px;
            border-radius: 8px;
        }
        .point-box h4 {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        .point-box ul {
            list-style: none;
            padding: 0;
        }
        .point-box ul li {
            padding: 6px 0;
            font-size: 13px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .point-box ul li:last-child { border: none; }
        .point-box ul li::before {
            margin-right: 8px;
            font-weight: bold;
        }
        .point-box.forts { background: #E8F5E9; }
        .point-box.forts h4 { color: #2E7D32; }
        .point-box.forts ul li::before { content: '✓'; color: #43A047; }
        .point-box.vigilance { background: #FFF3E0; }
        .point-box.vigilance h4 { color: #E65100; }
        .point-box.vigilance ul li::before { content: '⚠'; color: #FB8C00; }

        /* Teaser */
        .teaser {
            background: #1B3A6B;
            color: #fff;
            padding: 40px;
            margin-top: 40px;
            border-radius: 8px;
        }
        .teaser h3 {
            font-size: 20px;
            margin-bottom: 6px;
        }
        .teaser .teaser-sub {
            font-size: 13px;
            opacity: 0.75;
            margin-bottom: 24px;
        }
        .teaser-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .teaser-card {
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 18px;
            border-left: 3px solid #E53935;
        }
        .teaser-card .tc-num {
            font-size: 11px;
            color: #E53935;
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .teaser-card .tc-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .teaser-card .tc-desc {
            font-size: 12px;
            opacity: 0.8;
            line-height: 1.5;
        }

        /* Note libre */
        .note-libre {
            background: #F5F7FA;
            border: 1px solid #E0E0E0;
            border-radius: 6px;
            padding: 16px;
            min-height: 60px;
            font-size: 13px;
            color: #555;
            white-space: pre-wrap;
        }

        /* Legal */
        .legal {
            text-align: center;
            font-size: 11px;
            color: #999;
            padding: 30px 40px;
            border-top: 1px solid #E0E0E0;
            margin-top: 40px;
        }

        /* Cashflow styling */
        .cashflow-positive { color: #43A047; font-weight: 800; }
        .cashflow-negative { color: #E53935; font-weight: 800; }

        /* Print button */
        .btn-print {
            display: inline-block;
            background: #E53935;
            color: #fff;
            border: none;
            padding: 12px 28px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            letter-spacing: 0.5px;
            margin: 20px 0;
        }
        .btn-print:hover { background: #C62828; }

        /* Admin form */
        .admin-panel {
            background: #F5F7FA;
            border: 2px solid #1B3A6B;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .admin-panel h3 {
            color: #1B3A6B;
            margin-bottom: 20px;
            font-size: 18px;
        }
        .admin-panel .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .admin-panel .form-group {
            display: flex;
            flex-direction: column;
        }
        .admin-panel .form-group.full {
            grid-column: 1 / -1;
        }
        .admin-panel label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 4px;
            letter-spacing: 0.3px;
        }
        .admin-panel input, .admin-panel select, .admin-panel textarea {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 13px;
            font-family: inherit;
        }
        .admin-panel input:focus, .admin-panel select:focus, .admin-panel textarea:focus {
            outline: none;
            border-color: #1B3A6B;
        }
        .admin-panel .section-title {
            grid-column: 1 / -1;
            font-size: 14px;
            font-weight: 700;
            color: #E53935;
            margin-top: 16px;
            margin-bottom: 4px;
            padding-top: 12px;
            border-top: 1px solid #E0E0E0;
        }
        .admin-panel .btn-generate {
            grid-column: 1 / -1;
            background: #1B3A6B;
            color: #fff;
            border: none;
            padding: 14px;
            font-size: 15px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 16px;
            letter-spacing: 0.5px;
        }
        .admin-panel .btn-generate:hover { background: #142d54; }

        /* Print styles */
        @media print {
            body { font-size: 12px; }
            .admin-panel, .btn-print, .no-print { display: none !important; }
            .report-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .section { page-break-inside: avoid; }
            .kpi-card, .verdict-badge-card, .teaser-card, .point-box {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .teaser {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            table th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .score-bar-track, .score-bar-fill {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .badge {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .container { padding: 15px 20px; }
            @page { size: A4; margin: 10mm; }
        }
    </style>
</head>
<body>

<?php if (!$hideForm): ?>
<!-- ===== FORMULAIRE ADMIN ===== -->
<div class="container no-print">
    <form class="admin-panel" method="POST" action="audit_lcd.php">
        <h3>⚙ Administration — Générer le rapport d'audit LCD</h3>
        <div class="form-grid">

            <div class="section-title">EN-TÊTE & CLIENT</div>
            <div class="form-group">
                <label>Nom du client</label>
                <input type="text" name="nom_client" value="<?= v('nom_client', '') ?>" placeholder="Jean Dupont">
            </div>
            <div class="form-group">
                <label>Adresse du bien</label>
                <input type="text" name="adresse_bien" value="<?= v('adresse_bien', '') ?>" placeholder="12 Rue de la Paix, 75002 Paris">
            </div>
            <div class="form-group">
                <label>Date d'analyse</label>
                <input type="date" name="date_analyse" value="<?= v('date_analyse', date('Y-m-d')) ?>">
            </div>
            <div class="form-group">
                <label>Référence dossier</label>
                <input type="text" name="ref_dossier" value="<?= v('ref_dossier', '') ?>" placeholder="FC-20260309-XXXX">
            </div>
            <div class="form-group">
                <label>Prix d'acquisition (€)</label>
                <input type="number" name="prix_achat" value="<?= vn('prix_achat', '') ?>" placeholder="250000">
            </div>

            <div class="section-title">SECTION 01 — LE BIEN</div>
            <div class="form-group">
                <label>Commune</label>
                <input type="text" name="commune" value="<?= v('commune', '') ?>" placeholder="Paris 2ème">
            </div>
            <div class="form-group">
                <label>Type de bien</label>
                <input type="text" name="type_bien" value="<?= v('type_bien', '') ?>" placeholder="Appartement T2">
            </div>
            <div class="form-group">
                <label>Surface (m²)</label>
                <input type="number" name="surface" value="<?= v('surface', '') ?>" placeholder="45">
            </div>
            <div class="form-group">
                <label>Nombre de pièces</label>
                <input type="number" name="nb_pieces" value="<?= v('nb_pieces', '') ?>" placeholder="2">
            </div>
            <div class="form-group">
                <label>DPE</label>
                <select name="dpe">
                    <?php foreach (['A','B','C','D','E','F','G'] as $l): ?>
                        <option value="<?= $l ?>" <?= v('dpe','') === $l ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Année de construction</label>
                <input type="number" name="annee_construction" value="<?= v('annee_construction', '') ?>" placeholder="1975">
            </div>
            <div class="form-group">
                <label>Travaux estimés (€)</label>
                <input type="number" name="travaux_estimes" value="<?= vn('travaux_estimes', '') ?>" placeholder="15000">
            </div>
            <div class="form-group">
                <label>Statut copropriété</label>
                <select name="statut_copro">
                    <option value="Oui" <?= v('statut_copro','') === 'Oui' ? 'selected' : '' ?>>Oui</option>
                    <option value="Non" <?= v('statut_copro','') === 'Non' ? 'selected' : '' ?>>Non</option>
                </select>
            </div>
            <div class="form-group">
                <label>Autorisation LCD</label>
                <select name="autorisation_lcd">
                    <option value="Oui" <?= v('autorisation_lcd','') === 'Oui' ? 'selected' : '' ?>>Oui</option>
                    <option value="Non" <?= v('autorisation_lcd','') === 'Non' ? 'selected' : '' ?>>Non</option>
                    <option value="À vérifier" <?= v('autorisation_lcd','') === 'À vérifier' ? 'selected' : '' ?>>À vérifier</option>
                </select>
            </div>
            <div class="form-group">
                <label>Zone réglementée</label>
                <select name="zone_reglementee">
                    <option value="Oui" <?= v('zone_reglementee','') === 'Oui' ? 'selected' : '' ?>>Oui</option>
                    <option value="Non" <?= v('zone_reglementee','') === 'Non' ? 'selected' : '' ?>>Non</option>
                </select>
            </div>
            <div class="form-group">
                <label>Résidence principale/secondaire</label>
                <select name="type_residence">
                    <option value="Principale" <?= v('type_residence','') === 'Principale' ? 'selected' : '' ?>>Principale</option>
                    <option value="Secondaire" <?= v('type_residence','') === 'Secondaire' ? 'selected' : '' ?>>Secondaire</option>
                </select>
            </div>
            <div class="form-group">
                <label>Changement d'usage requis</label>
                <select name="changement_usage">
                    <option value="Non" <?= v('changement_usage','') === 'Non' ? 'selected' : '' ?>>Non</option>
                    <option value="Oui" <?= v('changement_usage','') === 'Oui' ? 'selected' : '' ?>>Oui</option>
                </select>
            </div>
            <div class="form-group">
                <label>Risque réglementaire</label>
                <select name="risque_reglementaire">
                    <option value="faible" <?= (isset($d['risque_reglementaire']) && $d['risque_reglementaire'] === 'faible') ? 'selected' : '' ?>>Faible</option>
                    <option value="modéré" <?= (isset($d['risque_reglementaire']) && $d['risque_reglementaire'] === 'modéré') ? 'selected' : '' ?>>Modéré</option>
                    <option value="élevé" <?= (isset($d['risque_reglementaire']) && $d['risque_reglementaire'] === 'élevé') ? 'selected' : '' ?>>Élevé</option>
                </select>
            </div>

            <div class="section-title">SECTION 02 — LE MARCHÉ</div>
            <div class="form-group">
                <label>Nb biens concurrents</label>
                <input type="number" name="nb_concurrents" value="<?= v('nb_concurrents', '') ?>" placeholder="45">
            </div>
            <div class="form-group">
                <label>Taux occupation secteur (%)</label>
                <input type="number" name="taux_occupation" value="<?= v('taux_occupation', '') ?>" placeholder="72">
            </div>
            <div class="form-group">
                <label>Revenu moyen mensuel (€)</label>
                <input type="number" name="revenu_moyen" value="<?= v('revenu_moyen', '') ?>" placeholder="2800">
            </div>
            <div class="form-group">
                <label>Tarif nuitée moyen (€)</label>
                <input type="number" name="tarif_nuitee_moyen" value="<?= vn('tarif_nuitee_moyen', '') ?>" placeholder="95">
            </div>
            <div class="form-group">
                <label>Saisonnalité</label>
                <input type="text" name="saisonnalite" value="<?= v('saisonnalite', '') ?>" placeholder="Forte en été, modérée hors saison">
            </div>
            <div class="form-group">
                <label>Profil voyageurs</label>
                <input type="text" name="profil_voyageurs" value="<?= v('profil_voyageurs', '') ?>" placeholder="Touristes, professionnels">
            </div>
            <div class="form-group">
                <label>Score attractivité (/10)</label>
                <input type="number" name="score_attractivite" min="0" max="10" step="0.5" value="<?= vn('score_attractivite', '') ?>" placeholder="7">
            </div>
            <div class="form-group">
                <label>Score concurrence (/10)</label>
                <input type="number" name="score_concurrence" min="0" max="10" step="0.5" value="<?= vn('score_concurrence', '') ?>" placeholder="6">
            </div>
            <div class="form-group">
                <label>Score potentiel tarifaire (/10)</label>
                <input type="number" name="score_potentiel_tarifaire" min="0" max="10" step="0.5" value="<?= vn('score_potentiel_tarifaire', '') ?>" placeholder="8">
            </div>
            <div class="form-group">
                <label>Score saisonnalité (/10)</label>
                <input type="number" name="score_saisonnalite" min="0" max="10" step="0.5" value="<?= vn('score_saisonnalite', '') ?>" placeholder="6">
            </div>

            <div class="section-title">SECTION 03 — RENTABILITÉ (Hypothèses)</div>
            <div class="form-group">
                <label>Frais de notaire (€)</label>
                <input type="number" name="frais_notaire" value="<?= vn('frais_notaire', '') ?>" placeholder="20000">
            </div>
            <div class="form-group">
                <label>Ameublement (€)</label>
                <input type="number" name="ameublement" value="<?= vn('ameublement', '') ?>" placeholder="8000">
            </div>
            <div class="form-group">
                <label>Mensualité crédit (€/mois)</label>
                <input type="number" name="mensualite_credit" value="<?= vn('mensualite_credit', '') ?>" placeholder="1100">
            </div>
            <div class="form-group">
                <label>Charges copro (€/mois)</label>
                <input type="number" name="charges_copro" value="<?= vn('charges_copro', '') ?>" placeholder="150">
            </div>
            <div class="form-group">
                <label>Taxe foncière (€/an)</label>
                <input type="number" name="taxe_fonciere" value="<?= vn('taxe_fonciere', '') ?>" placeholder="1200">
            </div>
            <div class="form-group">
                <label>Commission conciergerie (%)</label>
                <input type="number" name="commission_conciergerie" value="<?= vn('commission_conciergerie', '') ?>" placeholder="20">
            </div>
            <div class="form-group">
                <label>Charges locataires (€/mois)</label>
                <input type="number" name="charges_locataires" value="<?= vn('charges_locataires', '') ?>" placeholder="80">
            </div>

            <div class="section-title">SECTION 04 — PROFIL INVESTISSEUR</div>
            <div class="form-group">
                <label>Objectif</label>
                <input type="text" name="objectif_investisseur" value="<?= v('objectif_investisseur', '') ?>" placeholder="Complément de revenus">
            </div>
            <div class="form-group">
                <label>Amplitude opérationnelle</label>
                <input type="text" name="amplitude_operationnelle" value="<?= v('amplitude_operationnelle', '') ?>" placeholder="Délégation totale">
            </div>
            <div class="form-group">
                <label>Compréhension LCD</label>
                <input type="text" name="comprehension_lcd" value="<?= v('comprehension_lcd', '') ?>" placeholder="Bonne connaissance">
            </div>
            <div class="form-group">
                <label>Trésorerie de sécurité</label>
                <input type="text" name="tresorerie_securite" value="<?= v('tresorerie_securite', '') ?>" placeholder="6 mois de charges">
            </div>
            <div class="form-group">
                <label>Tolérance mois creux</label>
                <input type="text" name="tolerance_creux" value="<?= v('tolerance_creux', '') ?>" placeholder="Modérée">
            </div>
            <div class="form-group">
                <label>Régime fiscal</label>
                <input type="text" name="regime_fiscal" value="<?= v('regime_fiscal', '') ?>" placeholder="LMNP réel">
            </div>
            <div class="form-group">
                <label>Situation du bien</label>
                <input type="text" name="situation_bien" value="<?= v('situation_bien', '') ?>" placeholder="Acquisition en cours">
            </div>
            <div class="form-group">
                <label>Score connaissance LCD (/10)</label>
                <input type="number" name="mat_connaissance" min="0" max="10" step="0.5" value="<?= vn('mat_connaissance', '') ?>">
            </div>
            <div class="form-group">
                <label>Score trésorerie (/10)</label>
                <input type="number" name="mat_tresorerie" min="0" max="10" step="0.5" value="<?= vn('mat_tresorerie', '') ?>">
            </div>
            <div class="form-group">
                <label>Score tolérance risque (/10)</label>
                <input type="number" name="mat_tolerance" min="0" max="10" step="0.5" value="<?= vn('mat_tolerance', '') ?>">
            </div>
            <div class="form-group">
                <label>Score maturité fiscale (/10)</label>
                <input type="number" name="mat_fiscal" min="0" max="10" step="0.5" value="<?= vn('mat_fiscal', '') ?>">
            </div>
            <div class="form-group">
                <label>Score opérationnel (/10)</label>
                <input type="number" name="mat_operationnel" min="0" max="10" step="0.5" value="<?= vn('mat_operationnel', '') ?>">
            </div>
            <div class="form-group full">
                <label>Note libre</label>
                <textarea name="note_libre" rows="3" placeholder="Observations complémentaires..."><?= v('note_libre', '') ?></textarea>
            </div>

            <div class="section-title">SECTION 05 — VERDICT</div>
            <div class="form-group">
                <label>Score global (/10)</label>
                <input type="number" name="score_global" min="0" max="10" step="0.5" value="<?= vn('score_global', '') ?>" placeholder="7">
            </div>
            <div class="form-group">
                <label>Verdict réglementation (/10)</label>
                <input type="number" name="verdict_reglementation" min="0" max="10" step="0.5" value="<?= vn('verdict_reglementation', '') ?>">
            </div>
            <div class="form-group">
                <label>Verdict marché (/10)</label>
                <input type="number" name="verdict_marche" min="0" max="10" step="0.5" value="<?= vn('verdict_marche', '') ?>">
            </div>
            <div class="form-group">
                <label>Verdict rentabilité (/10)</label>
                <input type="number" name="verdict_rentabilite" min="0" max="10" step="0.5" value="<?= vn('verdict_rentabilite', '') ?>">
            </div>
            <div class="form-group">
                <label>Verdict profil (/10)</label>
                <input type="number" name="verdict_profil" min="0" max="10" step="0.5" value="<?= vn('verdict_profil', '') ?>">
            </div>
            <div class="form-group">
                <label>Point fort 1</label>
                <input type="text" name="point_fort_1" value="<?= v('point_fort_1', '') ?>" placeholder="Emplacement premium">
            </div>
            <div class="form-group">
                <label>Point fort 2</label>
                <input type="text" name="point_fort_2" value="<?= v('point_fort_2', '') ?>" placeholder="Fort taux d'occupation">
            </div>
            <div class="form-group">
                <label>Point fort 3</label>
                <input type="text" name="point_fort_3" value="<?= v('point_fort_3', '') ?>" placeholder="Cashflow positif dès le 1er mois">
            </div>
            <div class="form-group">
                <label>Point de vigilance 1</label>
                <input type="text" name="vigilance_1" value="<?= v('vigilance_1', '') ?>" placeholder="Réglementation évolutive">
            </div>
            <div class="form-group">
                <label>Point de vigilance 2</label>
                <input type="text" name="vigilance_2" value="<?= v('vigilance_2', '') ?>" placeholder="Saisonnalité marquée">
            </div>

            <button type="submit" class="btn-generate">GÉNÉRER LE RAPPORT</button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- ===== RAPPORT ===== -->

<!-- HEADER -->
<div class="report-header">
    <div>
        <div class="logo">FRENCHY <span>COMPANY</span></div>
        <div class="subtitle">Analyse d'Investissement LCD</div>
    </div>
    <div class="meta">
        <div><strong>Dossier</strong> <?= $ref_dossier ?></div>
        <div><strong>Date</strong> <?= v('date_analyse', date('d/m/Y')) ?></div>
        <div><strong>Client</strong> <?= v('nom_client') ?></div>
    </div>
</div>

<div class="container">

    <!-- Client bar -->
    <div class="client-bar">
        <div class="item">
            <label>Client</label>
            <p><?= v('nom_client') ?></p>
        </div>
        <div class="item">
            <label>Adresse du bien</label>
            <p><?= v('adresse_bien') ?></p>
        </div>
        <div class="item">
            <label>Prix d'acquisition</label>
            <p><?= $prix_achat > 0 ? number_format($prix_achat, 0, ',', ' ') . ' €' : '[À compléter]' ?></p>
        </div>
    </div>

    <!-- Print button -->
    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button class="btn-print" onclick="window.print()">Imprimer / Générer PDF</button>
    </div>

    <!-- ===== SECTION 01 — LE BIEN ===== -->
    <div class="section">
        <span class="section-number">SECTION 01</span>
        <h2>Le Bien</h2>

        <table>
            <tr><td class="label-cell">Adresse</td><td><?= v('adresse_bien') ?></td></tr>
            <tr><td class="label-cell">Commune</td><td><?= v('commune') ?></td></tr>
            <tr><td class="label-cell">Type de bien</td><td><?= v('type_bien') ?></td></tr>
            <tr><td class="label-cell">Surface</td><td><?= v('surface') ?> m²</td></tr>
            <tr><td class="label-cell">Nombre de pièces</td><td><?= v('nb_pieces') ?></td></tr>
            <tr><td class="label-cell">DPE</td><td><?= v('dpe') ?></td></tr>
            <tr><td class="label-cell">Année de construction</td><td><?= v('annee_construction') ?></td></tr>
            <tr><td class="label-cell">Travaux estimés</td><td><?= $travaux > 0 ? number_format($travaux, 0, ',', ' ') . ' €' : '[À compléter]' ?></td></tr>
        </table>

        <h3 style="font-size: 16px; color: #1B3A6B; margin: 20px 0 12px;">Analyse Réglementaire</h3>
        <table>
            <tr><td class="label-cell">Statut copropriété</td><td><?= v('statut_copro') ?></td></tr>
            <tr><td class="label-cell">Autorisation LCD</td><td><?= v('autorisation_lcd') ?></td></tr>
            <tr><td class="label-cell">Zone réglementée</td><td><?= v('zone_reglementee') ?></td></tr>
            <tr><td class="label-cell">Résidence</td><td><?= v('type_residence') ?></td></tr>
            <tr><td class="label-cell">Changement d'usage</td><td><?= v('changement_usage') ?></td></tr>
            <tr>
                <td class="label-cell">Risque réglementaire</td>
                <td>
                    <span class="badge" style="background: <?= $risque[2] ?>; color: <?= $risque[1] ?>;">
                        <?= $risque[0] ?>
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <!-- ===== SECTION 02 — LE MARCHÉ ===== -->
    <div class="section">
        <span class="section-number">SECTION 02</span>
        <h2>Le Marché</h2>

        <table>
            <tr><td class="label-cell">Biens concurrents</td><td><?= v('nb_concurrents') ?></td></tr>
            <tr><td class="label-cell">Taux d'occupation secteur</td><td><?= v('taux_occupation') ?> %</td></tr>
            <tr><td class="label-cell">Revenu moyen mensuel</td><td><?= vn('revenu_moyen') > 0 ? number_format(vn('revenu_moyen'), 0, ',', ' ') . ' €' : '[À compléter]' ?></td></tr>
            <tr><td class="label-cell">Tarif nuitée moyen</td><td><?= $tarif_nuitee > 0 ? number_format($tarif_nuitee, 0, ',', ' ') . ' €' : '[À compléter]' ?></td></tr>
            <tr><td class="label-cell">Saisonnalité</td><td><?= v('saisonnalite') ?></td></tr>
            <tr><td class="label-cell">Profil voyageurs</td><td><?= v('profil_voyageurs') ?></td></tr>
        </table>

        <!-- KPI Cards -->
        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-value"><?= v('taux_occupation', '—') ?><span style="font-size: 16px;">%</span></div>
                <div class="kpi-label">Taux d'occupation</div>
                <div class="kpi-sub">Moyenne du secteur</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value"><?= vn('revenu_moyen') > 0 ? number_format(vn('revenu_moyen'), 0, ',', ' ') : '—' ?><span style="font-size: 16px;">€</span></div>
                <div class="kpi-label">Revenu moyen</div>
                <div class="kpi-sub">Par mois</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value"><?= $tarif_nuitee > 0 ? number_format($tarif_nuitee, 0, ',', ' ') : '—' ?><span style="font-size: 16px;">€</span></div>
                <div class="kpi-label">Nuitée optimale</div>
                <div class="kpi-sub">Tarif moyen</div>
            </div>
        </div>

        <!-- Score bars -->
        <?php
        $scores_marche = [
            ['label' => 'Attractivité', 'score' => $score_attractivite],
            ['label' => 'Concurrence', 'score' => $score_concurrence],
            ['label' => 'Potentiel tarifaire', 'score' => $score_potentiel],
            ['label' => 'Saisonnalité', 'score' => $score_saisonnalite],
        ];
        foreach ($scores_marche as $sm): ?>
        <div class="score-bar-container">
            <div class="score-bar-label">
                <span><?= $sm['label'] ?></span>
                <span><?= $sm['score'] ?>/10</span>
            </div>
            <div class="score-bar-track">
                <div class="score-bar-fill" style="width: <?= $sm['score'] * 10 ?>%; background: <?= scoreColor($sm['score']) ?>;"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ===== SECTION 03 — LA RENTABILITÉ ===== -->
    <div class="section">
        <span class="section-number">SECTION 03</span>
        <h2>La Rentabilité</h2>

        <h3 style="font-size: 15px; color: #1B3A6B; margin-bottom: 12px;">Hypothèses de calcul</h3>
        <table>
            <tr><td class="label-cell">Prix d'achat</td><td><?= number_format($prix_achat, 0, ',', ' ') ?> €</td></tr>
            <tr><td class="label-cell">Frais de notaire</td><td><?= number_format($frais_notaire, 0, ',', ' ') ?> €</td></tr>
            <tr><td class="label-cell">Travaux</td><td><?= number_format($travaux, 0, ',', ' ') ?> €</td></tr>
            <tr><td class="label-cell">Ameublement</td><td><?= number_format($ameublement, 0, ',', ' ') ?> €</td></tr>
            <tr style="font-weight: 700;"><td class="label-cell" style="font-weight:700; color:#1B3A6B;">TOTAL INVESTI</td><td style="font-weight:700; color:#1B3A6B;"><?= number_format($total_investi, 0, ',', ' ') ?> €</td></tr>
            <tr><td class="label-cell">Mensualité crédit</td><td><?= number_format($mensualite, 0, ',', ' ') ?> €/mois</td></tr>
            <tr><td class="label-cell">Charges copro</td><td><?= number_format($charges_copro, 0, ',', ' ') ?> €/mois</td></tr>
            <tr><td class="label-cell">Taxe foncière</td><td><?= number_format(vn('taxe_fonciere'), 0, ',', ' ') ?> €/an</td></tr>
            <tr><td class="label-cell">Commission conciergerie</td><td><?= $commission_pct ?> %</td></tr>
            <tr><td class="label-cell">Charges locataires</td><td><?= number_format($charges_loc, 0, ',', ' ') ?> €/mois</td></tr>
        </table>

        <h3 style="font-size: 15px; color: #1B3A6B; margin: 20px 0 12px;">Scénarios de rentabilité</h3>
        <table>
            <thead>
                <tr>
                    <th></th>
                    <?php foreach ($scenarios as $sc): ?>
                        <th style="text-align: center; color: <?= $sc['color'] ?>; background: #1B3A6B;">
                            <?= $sc['label'] ?><br><small style="opacity:0.8">(<?= $sc['taux'] ?>% occ.)</small>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="label-cell">Nuitées / mois</td>
                    <?php foreach ($scenarios as $sc): ?><td style="text-align:center;"><?= $sc['nuitees'] ?></td><?php endforeach; ?>
                </tr>
                <tr>
                    <td class="label-cell">Revenus bruts</td>
                    <?php foreach ($scenarios as $sc): ?><td style="text-align:center;"><?= number_format($sc['revenus_bruts'], 0, ',', ' ') ?> €</td><?php endforeach; ?>
                </tr>
                <tr>
                    <td class="label-cell">Commission (<?= $commission_pct ?>%)</td>
                    <?php foreach ($scenarios as $sc): ?><td style="text-align:center;">- <?= number_format($sc['commission'], 0, ',', ' ') ?> €</td><?php endforeach; ?>
                </tr>
                <tr>
                    <td class="label-cell">Charges fixes</td>
                    <?php foreach ($scenarios as $sc): ?><td style="text-align:center;">- <?= number_format($charges_fixes, 0, ',', ' ') ?> €</td><?php endforeach; ?>
                </tr>
                <tr style="font-size: 15px;">
                    <td class="label-cell" style="font-weight:800; color:#1B3A6B;">CASHFLOW NET</td>
                    <?php foreach ($scenarios as $sc):
                        $cls = $sc['cashflow'] >= 0 ? 'cashflow-positive' : 'cashflow-negative';
                    ?>
                        <td style="text-align:center;" class="<?= $cls ?>"><?= ($sc['cashflow'] >= 0 ? '+' : '') . number_format($sc['cashflow'], 0, ',', ' ') ?> €</td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="label-cell">Rendement brut</td>
                    <?php foreach ($scenarios as $sc): ?><td style="text-align:center; font-weight:600;"><?= $sc['rendement'] ?> %</td><?php endforeach; ?>
                </tr>
                <tr>
                    <td class="label-cell">Seuil rentabilité</td>
                    <?php foreach ($scenarios as $sc): ?><td style="text-align:center;"><?= $sc['seuil'] ?> nuits/mois</td><?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ===== SECTION 04 — PROFIL INVESTISSEUR ===== -->
    <div class="section">
        <span class="section-number">SECTION 04</span>
        <h2>Profil Investisseur</h2>

        <table>
            <tr><td class="label-cell">Objectif</td><td><?= v('objectif_investisseur') ?></td></tr>
            <tr><td class="label-cell">Amplitude opérationnelle</td><td><?= v('amplitude_operationnelle') ?></td></tr>
            <tr><td class="label-cell">Compréhension LCD</td><td><?= v('comprehension_lcd') ?></td></tr>
            <tr><td class="label-cell">Trésorerie de sécurité</td><td><?= v('tresorerie_securite') ?></td></tr>
            <tr><td class="label-cell">Tolérance mois creux</td><td><?= v('tolerance_creux') ?></td></tr>
            <tr><td class="label-cell">Régime fiscal</td><td><?= v('regime_fiscal') ?></td></tr>
            <tr><td class="label-cell">Situation du bien</td><td><?= v('situation_bien') ?></td></tr>
        </table>

        <h3 style="font-size: 15px; color: #1B3A6B; margin: 20px 0 12px;">Maturité investisseur</h3>
        <?php
        $scores_mat = [
            ['label' => 'Connaissance LCD', 'score' => $mat_connaissance],
            ['label' => 'Solidité trésorerie', 'score' => $mat_tresorerie],
            ['label' => 'Tolérance au risque', 'score' => $mat_tolerance],
            ['label' => 'Maturité fiscale', 'score' => $mat_fiscal],
            ['label' => 'Capacité opérationnelle', 'score' => $mat_operationnel],
        ];
        foreach ($scores_mat as $sm): ?>
        <div class="score-bar-container">
            <div class="score-bar-label">
                <span><?= $sm['label'] ?></span>
                <span><?= $sm['score'] ?>/10</span>
            </div>
            <div class="score-bar-track">
                <div class="score-bar-fill" style="width: <?= $sm['score'] * 10 ?>%; background: <?= scoreColor($sm['score']) ?>;"></div>
            </div>
        </div>
        <?php endforeach; ?>

        <h3 style="font-size: 15px; color: #1B3A6B; margin: 20px 0 8px;">Notes</h3>
        <div class="note-libre"><?= v('note_libre', 'Aucune note complémentaire.') ?></div>
    </div>

    <!-- ===== SECTION 05 — VERDICT ===== -->
    <div class="section">
        <span class="section-number">SECTION 05</span>
        <h2>Verdict Global</h2>

        <div style="text-align: center; margin: 24px 0;">
            <div style="font-size: 13px; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Score global</div>
            <div style="font-size: 48px; font-weight: 900; color: <?= scoreColor($score_global) ?>;">
                <?= $score_global ?><span style="font-size: 24px; color: #aaa;">/10</span>
            </div>
            <span class="badge badge-large" style="background: <?= scoreBg($score_global) ?>; color: <?= scoreColor($score_global) ?>; margin-top: 8px;">
                <?= badgeLabel($score_global) ?>
            </span>
        </div>

        <!-- Points forts / vigilance -->
        <div class="points-grid">
            <div class="point-box forts">
                <h4>Points forts</h4>
                <ul>
                    <li><?= v('point_fort_1', 'Point fort à définir') ?></li>
                    <li><?= v('point_fort_2', 'Point fort à définir') ?></li>
                    <li><?= v('point_fort_3', 'Point fort à définir') ?></li>
                </ul>
            </div>
            <div class="point-box vigilance">
                <h4>Points de vigilance</h4>
                <ul>
                    <li><?= v('vigilance_1', 'Point de vigilance à définir') ?></li>
                    <li><?= v('vigilance_2', 'Point de vigilance à définir') ?></li>
                </ul>
            </div>
        </div>

        <!-- 4 verdict badges -->
        <div class="verdict-badges">
            <?php
            $axes = [
                ['label' => 'Réglementation', 'score' => $verdict_reglementation],
                ['label' => 'Marché', 'score' => $verdict_marche],
                ['label' => 'Rentabilité', 'score' => $verdict_rentabilite],
                ['label' => 'Profil', 'score' => $verdict_profil],
            ];
            foreach ($axes as $axe): ?>
            <div class="verdict-badge-card" style="border-top: 3px solid <?= scoreColor($axe['score']) ?>;">
                <div class="vb-label"><?= $axe['label'] ?></div>
                <div class="vb-score" style="color: <?= scoreColor($axe['score']) ?>;"><?= $axe['score'] ?>/10</div>
                <div class="vb-text" style="color: <?= scoreColor($axe['score']) ?>;"><?= badgeLabel($axe['score']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ===== BLOC TEASER ===== -->
    <div class="teaser">
        <h3>Nos Recommandations</h3>
        <div class="teaser-sub">Frenchy Conciergerie vous accompagne pour maximiser votre investissement LCD.</div>
        <div class="teaser-grid">
            <div class="teaser-card">
                <div class="tc-num">01</div>
                <div class="tc-title">Ameublement & Home Staging</div>
                <div class="tc-desc">Nous sélectionnons le mobilier et la décoration adaptés à votre cible voyageurs pour maximiser les avis et le tarif nuitée.</div>
            </div>
            <div class="teaser-card">
                <div class="tc-num">02</div>
                <div class="tc-title">Photos Professionnelles</div>
                <div class="tc-desc">Shooting photo professionnel optimisé pour les plateformes (Airbnb, Booking). Premier levier de conversion prouvé.</div>
            </div>
            <div class="teaser-card">
                <div class="tc-num">03</div>
                <div class="tc-title">Pricing Dynamique</div>
                <div class="tc-desc">Algorithme de tarification intelligent ajusté quotidiennement selon la demande, la saisonnalité et la concurrence locale.</div>
            </div>
            <div class="teaser-card">
                <div class="tc-num">04</div>
                <div class="tc-title">Prochaine Étape</div>
                <div class="tc-desc">Planifiez un rendez-vous stratégique avec notre équipe pour valider votre projet et lancer votre exploitation LCD.</div>
            </div>
        </div>
    </div>

</div>

<!-- Legal -->
<div class="legal">
    <p>Ce document est un outil d'aide à la décision et ne constitue pas un conseil en investissement. Les projections financières sont basées sur des hypothèses et données de marché indicatives. Les résultats réels peuvent varier. Frenchy Conciergerie décline toute responsabilité quant aux décisions d'investissement prises sur la base de ce rapport.</p>
    <p style="margin-top: 8px; color: #bbb;">© <?= date('Y') ?> Frenchy Conciergerie — Tous droits réservés — Document confidentiel</p>
</div>

</body>
</html>
