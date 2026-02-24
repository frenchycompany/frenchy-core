<?php
/**
 * Gestion du Ménage - Frenchy Conciergerie
 * Interface complète pour la gestion des interventions de ménage
 */
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Configuration admin
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'frenchyconciergerie2026');

// Gestion de la connexion
if (isset($_POST['login'])) {
    if ($_POST['username'] === ADMIN_USER && $_POST['password'] === ADMIN_PASS) {
        $_SESSION['admin_logged'] = true;
    } else {
        $loginError = 'Identifiants incorrects';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged']);
    header('Location: menage.php');
    exit;
}

$isLogged = isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true;

if (!$isLogged) {
    header('Location: index.php');
    exit;
}

// Page courante
$page = $_GET['page'] ?? 'dashboard';
$message = '';
$messageType = '';

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ajouter un prestataire
    if (isset($_POST['add_prestataire'])) {
        $stmt = $conn->prepare("INSERT INTO FC_prestataires (nom, prenom, email, telephone, tarif_horaire, tarif_forfait_studio, tarif_forfait_t2, tarif_forfait_t3, tarif_forfait_t4_plus, zones_intervention, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['nom'],
            $_POST['prenom'] ?? '',
            $_POST['email'] ?? '',
            $_POST['telephone'],
            $_POST['tarif_horaire'] ?? null,
            $_POST['tarif_forfait_studio'] ?? null,
            $_POST['tarif_forfait_t2'] ?? null,
            $_POST['tarif_forfait_t3'] ?? null,
            $_POST['tarif_forfait_t4_plus'] ?? null,
            $_POST['zones_intervention'] ?? '',
            $_POST['notes'] ?? ''
        ]);
        $message = 'Prestataire ajouté avec succès';
        $messageType = 'success';
    }

    // Modifier un prestataire
    if (isset($_POST['edit_prestataire'])) {
        $stmt = $conn->prepare("UPDATE FC_prestataires SET nom = ?, prenom = ?, email = ?, telephone = ?, tarif_horaire = ?, tarif_forfait_studio = ?, tarif_forfait_t2 = ?, tarif_forfait_t3 = ?, tarif_forfait_t4_plus = ?, zones_intervention = ?, notes = ?, statut = ? WHERE id = ?");
        $stmt->execute([
            $_POST['nom'],
            $_POST['prenom'] ?? '',
            $_POST['email'] ?? '',
            $_POST['telephone'],
            $_POST['tarif_horaire'] ?? null,
            $_POST['tarif_forfait_studio'] ?? null,
            $_POST['tarif_forfait_t2'] ?? null,
            $_POST['tarif_forfait_t3'] ?? null,
            $_POST['tarif_forfait_t4_plus'] ?? null,
            $_POST['zones_intervention'] ?? '',
            $_POST['notes'] ?? '',
            $_POST['statut'] ?? 'actif',
            $_POST['prestataire_id']
        ]);
        $message = 'Prestataire mis à jour';
        $messageType = 'success';
    }

    // Supprimer un prestataire
    if (isset($_POST['delete_prestataire'])) {
        $stmt = $conn->prepare("UPDATE FC_prestataires SET statut = 'inactif' WHERE id = ?");
        $stmt->execute([$_POST['prestataire_id']]);
        $message = 'Prestataire désactivé';
        $messageType = 'success';
    }

    // Planifier une intervention
    if (isset($_POST['add_menage'])) {
        $stmt = $conn->prepare("INSERT INTO FC_menages (logement_id, prestataire_id, reservation_id, date_intervention, heure_debut, heure_fin_prevue, type_menage, instructions, montant, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'planifie')");
        $stmt->execute([
            $_POST['logement_id'],
            $_POST['prestataire_id'] ?: null,
            $_POST['reservation_id'] ?: null,
            $_POST['date_intervention'],
            $_POST['heure_debut'] ?? '10:00',
            $_POST['heure_fin_prevue'] ?? null,
            $_POST['type_menage'],
            $_POST['instructions'] ?? '',
            $_POST['montant'] ?? null
        ]);
        $message = 'Intervention planifiée avec succès';
        $messageType = 'success';
    }

    // Modifier une intervention
    if (isset($_POST['edit_menage'])) {
        $stmt = $conn->prepare("UPDATE FC_menages SET logement_id = ?, prestataire_id = ?, date_intervention = ?, heure_debut = ?, heure_fin_prevue = ?, type_menage = ?, instructions = ?, montant = ?, statut = ? WHERE id = ?");
        $stmt->execute([
            $_POST['logement_id'],
            $_POST['prestataire_id'] ?: null,
            $_POST['date_intervention'],
            $_POST['heure_debut'] ?? '10:00',
            $_POST['heure_fin_prevue'] ?? null,
            $_POST['type_menage'],
            $_POST['instructions'] ?? '',
            $_POST['montant'] ?? null,
            $_POST['statut'],
            $_POST['menage_id']
        ]);
        $message = 'Intervention mise à jour';
        $messageType = 'success';
    }

    // Marquer comme terminé
    if (isset($_POST['complete_menage'])) {
        $stmt = $conn->prepare("UPDATE FC_menages SET statut = 'termine', heure_fin_reelle = NOW() WHERE id = ?");
        $stmt->execute([$_POST['menage_id']]);
        $message = 'Intervention marquée comme terminée';
        $messageType = 'success';
    }

    // Annuler une intervention
    if (isset($_POST['cancel_menage'])) {
        $stmt = $conn->prepare("UPDATE FC_menages SET statut = 'annule' WHERE id = ?");
        $stmt->execute([$_POST['menage_id']]);
        $message = 'Intervention annulée';
        $messageType = 'success';
    }

    // Ajouter un tarif spécifique
    if (isset($_POST['add_tarif'])) {
        $stmt = $conn->prepare("INSERT INTO FC_tarifs_menage (logement_id, type_menage, montant, duree_estimee, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['logement_id'],
            $_POST['type_menage'],
            $_POST['montant'],
            $_POST['duree_estimee'] ?? null,
            $_POST['description'] ?? ''
        ]);
        $message = 'Tarif ajouté';
        $messageType = 'success';
    }

    // Ajouter une fourniture
    if (isset($_POST['add_fourniture'])) {
        $stmt = $conn->prepare("INSERT INTO FC_stock_fournitures (nom, categorie, quantite, unite, seuil_alerte, prix_unitaire, fournisseur) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['nom'],
            $_POST['categorie'] ?? 'autre',
            $_POST['quantite'] ?? 0,
            $_POST['unite'] ?? 'unité',
            $_POST['seuil_alerte'] ?? 5,
            $_POST['prix_unitaire'] ?? null,
            $_POST['fournisseur'] ?? ''
        ]);
        $message = 'Fourniture ajoutée';
        $messageType = 'success';
    }

    // Mouvement de stock
    if (isset($_POST['stock_movement'])) {
        $quantite = $_POST['type_mouvement'] === 'sortie' ? -abs($_POST['quantite']) : abs($_POST['quantite']);

        // Mise à jour du stock
        $stmt = $conn->prepare("UPDATE FC_stock_fournitures SET quantite = quantite + ? WHERE id = ?");
        $stmt->execute([$quantite, $_POST['fourniture_id']]);

        // Enregistrement du mouvement
        $stmt = $conn->prepare("INSERT INTO FC_stock_mouvements (fourniture_id, type_mouvement, quantite, menage_id, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['fourniture_id'],
            $_POST['type_mouvement'],
            abs($_POST['quantite']),
            $_POST['menage_id'] ?: null,
            $_POST['notes'] ?? ''
        ]);
        $message = 'Mouvement de stock enregistré';
        $messageType = 'success';
    }

    // Enregistrer un paiement prestataire
    if (isset($_POST['add_paiement'])) {
        $stmt = $conn->prepare("INSERT INTO FC_paiements_prestataires (prestataire_id, montant, date_paiement, periode_debut, periode_fin, mode_paiement, reference, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['prestataire_id'],
            $_POST['montant'],
            $_POST['date_paiement'],
            $_POST['periode_debut'] ?? null,
            $_POST['periode_fin'] ?? null,
            $_POST['mode_paiement'] ?? 'virement',
            $_POST['reference'] ?? '',
            $_POST['notes'] ?? ''
        ]);

        // Marquer les interventions comme payées
        if (!empty($_POST['menages_ids'])) {
            $ids = implode(',', array_map('intval', $_POST['menages_ids']));
            $conn->exec("UPDATE FC_menages SET paye = 1 WHERE id IN ($ids)");
        }

        $message = 'Paiement enregistré';
        $messageType = 'success';
    }
}

// Récupération des données
$logements = getLogements($conn);

// Prestataires
$stmt = $conn->query("SELECT * FROM FC_prestataires ORDER BY statut = 'actif' DESC, nom ASC");
$prestataires = $stmt->fetchAll(PDO::FETCH_ASSOC);
$prestatairesActifs = array_filter($prestataires, fn($p) => $p['statut'] === 'actif');

// Interventions du jour
$stmt = $conn->prepare("
    SELECT m.*, l.titre as logement_titre, l.adresse as logement_adresse,
           p.nom as prestataire_nom, p.prenom as prestataire_prenom, p.telephone as prestataire_tel
    FROM FC_menages m
    LEFT JOIN FC_logements l ON m.logement_id = l.id
    LEFT JOIN FC_prestataires p ON m.prestataire_id = p.id
    WHERE m.date_intervention = CURDATE()
    ORDER BY m.heure_debut ASC
");
$stmt->execute();
$interventionsJour = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Interventions à venir (7 jours)
$stmt = $conn->prepare("
    SELECT m.*, l.titre as logement_titre, p.nom as prestataire_nom, p.prenom as prestataire_prenom
    FROM FC_menages m
    LEFT JOIN FC_logements l ON m.logement_id = l.id
    LEFT JOIN FC_prestataires p ON m.prestataire_id = p.id
    WHERE m.date_intervention > CURDATE() AND m.date_intervention <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND m.statut NOT IN ('annule', 'termine')
    ORDER BY m.date_intervention ASC, m.heure_debut ASC
");
$stmt->execute();
$interventionsAVenir = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stats = [];

// Interventions ce mois
$stmt = $conn->query("SELECT COUNT(*) FROM FC_menages WHERE MONTH(date_intervention) = MONTH(CURDATE()) AND YEAR(date_intervention) = YEAR(CURDATE())");
$stats['interventions_mois'] = $stmt->fetchColumn();

// Interventions terminées ce mois
$stmt = $conn->query("SELECT COUNT(*) FROM FC_menages WHERE statut = 'termine' AND MONTH(date_intervention) = MONTH(CURDATE()) AND YEAR(date_intervention) = YEAR(CURDATE())");
$stats['terminees_mois'] = $stmt->fetchColumn();

// CA ménage ce mois
$stmt = $conn->query("SELECT COALESCE(SUM(montant), 0) FROM FC_menages WHERE statut = 'termine' AND MONTH(date_intervention) = MONTH(CURDATE()) AND YEAR(date_intervention) = YEAR(CURDATE())");
$stats['ca_mois'] = $stmt->fetchColumn();

// Interventions non assignées
$stmt = $conn->query("SELECT COUNT(*) FROM FC_menages WHERE prestataire_id IS NULL AND statut = 'planifie' AND date_intervention >= CURDATE()");
$stats['non_assignees'] = $stmt->fetchColumn();

// Stock en alerte
$stmt = $conn->query("SELECT COUNT(*) FROM FC_stock_fournitures WHERE quantite <= seuil_alerte AND actif = 1");
$stats['stock_alerte'] = $stmt->fetchColumn();

// Fonction pour les types de ménage
function getTypeMenageLabel($type) {
    $labels = [
        'depart' => 'Ménage départ',
        'arrivee' => 'Ménage arrivée',
        'complet' => 'Ménage complet',
        'entretien' => 'Entretien',
        'grand_menage' => 'Grand ménage'
    ];
    return $labels[$type] ?? $type;
}

function getStatutBadge($statut) {
    $badges = [
        'planifie' => '<span class="badge badge-info">Planifié</span>',
        'confirme' => '<span class="badge badge-primary">Confirmé</span>',
        'en_cours' => '<span class="badge badge-warning">En cours</span>',
        'termine' => '<span class="badge badge-success">Terminé</span>',
        'annule' => '<span class="badge badge-danger">Annulé</span>',
        'reporte' => '<span class="badge badge-secondary">Reporté</span>'
    ];
    return $badges[$statut] ?? $statut;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du Ménage - Frenchy Conciergerie</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bleu-frenchy: #1E3A8A;
            --bleu-clair: #3B82F6;
            --rouge-frenchy: #EF4444;
            --vert: #10B981;
            --orange: #F59E0B;
            --gris-clair: #F3F4F6;
            --gris-fonce: #1F2937;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gris-clair);
            min-height: 100vh;
        }

        .admin-container { display: flex; min-height: 100vh; }

        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, var(--bleu-frenchy) 0%, #152a5e 100%);
            color: white;
            padding: 1.5rem;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar h2 {
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sidebar .subtitle {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.7);
            margin-bottom: 2rem;
        }

        .sidebar nav a {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 0.8rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.3rem;
            transition: all 0.3s;
        }

        .sidebar nav a:hover,
        .sidebar nav a.active {
            background: rgba(255,255,255,0.15);
            color: white;
        }

        .sidebar nav a .badge {
            background: var(--rouge-frenchy);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            margin-left: auto;
        }

        .sidebar nav a .badge.warning { background: var(--orange); }

        .sidebar .nav-section {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: rgba(255,255,255,0.5);
            margin: 1.5rem 0 0.5rem;
            padding-left: 1rem;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            color: var(--bleu-frenchy);
            font-size: 1.8rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-card .icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-card .icon.blue { background: #DBEAFE; }
        .stat-card .icon.green { background: #D1FAE5; }
        .stat-card .icon.orange { background: #FEF3C7; }
        .stat-card .icon.red { background: #FEE2E2; }

        .stat-card .info .number {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--gris-fonce);
        }

        .stat-card .info .label {
            color: #6B7280;
            font-size: 0.9rem;
        }

        .card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gris-clair);
        }

        .card-header h3 {
            color: var(--bleu-frenchy);
            font-size: 1.1rem;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary { background: var(--bleu-frenchy); color: white; }
        .btn-primary:hover { background: var(--bleu-clair); }
        .btn-success { background: var(--vert); color: white; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: var(--rouge-frenchy); color: white; }
        .btn-danger:hover { background: #DC2626; }
        .btn-warning { background: var(--orange); color: white; }
        .btn-secondary { background: #6B7280; color: white; }
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.8rem; }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group { margin-bottom: 1rem; }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gris-fonce);
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.7rem;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--bleu-clair);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th,
        table td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid var(--gris-clair);
        }

        table th {
            background: var(--gris-clair);
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--gris-fonce);
        }

        table tr:hover { background: #F9FAFB; }

        .badge {
            padding: 0.3rem 0.7rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-info { background: #DBEAFE; color: #1E40AF; }
        .badge-primary { background: #EDE9FE; color: #6D28D9; }
        .badge-warning { background: #FEF3C7; color: #B45309; }
        .badge-success { background: #D1FAE5; color: #065F46; }
        .badge-danger { background: #FEE2E2; color: #991B1B; }
        .badge-secondary { background: #E5E7EB; color: #4B5563; }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success { background: #D1FAE5; color: #065F46; border-left: 4px solid var(--vert); }
        .alert-error { background: #FEE2E2; color: #991B1B; border-left: 4px solid var(--rouge-frenchy); }

        .intervention-card {
            background: #F9FAFB;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.8rem;
            border-left: 4px solid var(--bleu-clair);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .intervention-card.urgent { border-left-color: var(--rouge-frenchy); }
        .intervention-card.en_cours { border-left-color: var(--orange); }
        .intervention-card.termine { border-left-color: var(--vert); }

        .intervention-info h4 {
            color: var(--gris-fonce);
            margin-bottom: 0.3rem;
        }

        .intervention-info p {
            color: #6B7280;
            font-size: 0.85rem;
        }

        .intervention-meta {
            display: flex;
            gap: 1rem;
            align-items: center;
            font-size: 0.85rem;
            color: #6B7280;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            background: #E5E7EB;
            border-radius: 8px;
            overflow: hidden;
        }

        .calendar-header {
            background: var(--bleu-frenchy);
            color: white;
            padding: 0.8rem;
            text-align: center;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .calendar-day {
            background: white;
            min-height: 100px;
            padding: 0.5rem;
        }

        .calendar-day.other-month { background: #F9FAFB; }
        .calendar-day.today { background: #DBEAFE; }

        .calendar-day .day-number {
            font-weight: 600;
            color: var(--gris-fonce);
            margin-bottom: 0.3rem;
        }

        .calendar-day .event {
            background: var(--bleu-clair);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            margin-bottom: 2px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .calendar-day .event.depart { background: var(--orange); }
        .calendar-day .event.arrivee { background: var(--vert); }
        .calendar-day .event.complet { background: var(--bleu-frenchy); }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active { display: flex; }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-header h3 { color: var(--bleu-frenchy); }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6B7280;
        }

        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--gris-clair);
            padding-bottom: 0.5rem;
        }

        .tab {
            padding: 0.5rem 1rem;
            border: none;
            background: none;
            cursor: pointer;
            color: #6B7280;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .tab:hover { background: var(--gris-clair); }
        .tab.active { background: var(--bleu-frenchy); color: white; }

        .stock-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem;
            border-bottom: 1px solid var(--gris-clair);
        }

        .stock-item.alerte { background: #FEF3C7; }

        .stock-item .stock-info h4 { margin-bottom: 0.2rem; }
        .stock-item .stock-info p { font-size: 0.85rem; color: #6B7280; }

        .stock-item .stock-qty {
            font-size: 1.2rem;
            font-weight: bold;
        }

        .stock-item .stock-qty.low { color: var(--rouge-frenchy); }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content { margin-left: 0; }
            .admin-container { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <h2>Frenchy Conciergerie</h2>
            <p class="subtitle">Gestion du Ménage</p>

            <nav>
                <a href="?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">
                    Dashboard
                </a>
                <a href="?page=calendrier" class="<?= $page === 'calendrier' ? 'active' : '' ?>">
                    Calendrier
                </a>
                <a href="?page=interventions" class="<?= $page === 'interventions' ? 'active' : '' ?>">
                    Interventions
                    <?php if ($stats['non_assignees'] > 0): ?>
                    <span class="badge warning"><?= $stats['non_assignees'] ?></span>
                    <?php endif; ?>
                </a>

                <div class="nav-section">Gestion</div>

                <a href="?page=prestataires" class="<?= $page === 'prestataires' ? 'active' : '' ?>">
                    Prestataires
                </a>
                <a href="?page=tarifs" class="<?= $page === 'tarifs' ? 'active' : '' ?>">
                    Tarifs
                </a>
                <a href="?page=stock" class="<?= $page === 'stock' ? 'active' : '' ?>">
                    Stock
                    <?php if ($stats['stock_alerte'] > 0): ?>
                    <span class="badge"><?= $stats['stock_alerte'] ?></span>
                    <?php endif; ?>
                </a>

                <div class="nav-section">Finances</div>

                <a href="?page=paiements" class="<?= $page === 'paiements' ? 'active' : '' ?>">
                    Paiements
                </a>

                <div class="nav-section">Navigation</div>

                <a href="index.php">Admin principal</a>
                <a href="../index.php" target="_blank">Voir le site</a>
                <a href="?logout=1">Déconnexion</a>
            </nav>
        </aside>

        <main class="main-content">
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>

            <?php if ($page === 'dashboard'): ?>
            <!-- DASHBOARD -->
            <div class="page-header">
                <h1 class="page-title">Tableau de bord Ménage</h1>
                <button class="btn btn-primary" onclick="openModal('addIntervention')">+ Nouvelle intervention</button>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon blue">📅</div>
                    <div class="info">
                        <div class="number"><?= $stats['interventions_mois'] ?></div>
                        <div class="label">Interventions ce mois</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon green">✓</div>
                    <div class="info">
                        <div class="number"><?= $stats['terminees_mois'] ?></div>
                        <div class="label">Terminées</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon orange">€</div>
                    <div class="info">
                        <div class="number"><?= number_format($stats['ca_mois'], 0, ',', ' ') ?> €</div>
                        <div class="label">CA Ménage ce mois</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon red">!</div>
                    <div class="info">
                        <div class="number"><?= $stats['non_assignees'] ?></div>
                        <div class="label">Non assignées</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Interventions du jour (<?= date('d/m/Y') ?>)</h3>
                </div>

                <?php if (empty($interventionsJour)): ?>
                <p style="color: #6B7280; text-align: center; padding: 2rem;">Aucune intervention prévue aujourd'hui</p>
                <?php else: ?>
                <?php foreach ($interventionsJour as $int): ?>
                <div class="intervention-card <?= $int['statut'] ?>">
                    <div class="intervention-info">
                        <h4><?= e($int['logement_titre']) ?></h4>
                        <p><?= getTypeMenageLabel($int['type_menage']) ?> - <?= e($int['heure_debut']) ?></p>
                        <?php if ($int['prestataire_nom']): ?>
                        <p>👤 <?= e($int['prestataire_prenom'] . ' ' . $int['prestataire_nom']) ?> - <?= e($int['prestataire_tel']) ?></p>
                        <?php else: ?>
                        <p style="color: var(--rouge-frenchy);">⚠ Non assigné</p>
                        <?php endif; ?>
                    </div>
                    <div class="intervention-actions">
                        <?= getStatutBadge($int['statut']) ?>
                        <?php if ($int['statut'] !== 'termine' && $int['statut'] !== 'annule'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="menage_id" value="<?= $int['id'] ?>">
                            <button type="submit" name="complete_menage" class="btn btn-success btn-sm">Terminer</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Interventions à venir (7 jours)</h3>
                    <a href="?page=calendrier" class="btn btn-secondary btn-sm">Voir calendrier</a>
                </div>

                <?php if (empty($interventionsAVenir)): ?>
                <p style="color: #6B7280; text-align: center; padding: 2rem;">Aucune intervention planifiée</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Logement</th>
                            <th>Type</th>
                            <th>Prestataire</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($interventionsAVenir as $int): ?>
                        <tr>
                            <td><?= date('d/m', strtotime($int['date_intervention'])) ?> <?= e($int['heure_debut']) ?></td>
                            <td><?= e($int['logement_titre']) ?></td>
                            <td><?= getTypeMenageLabel($int['type_menage']) ?></td>
                            <td>
                                <?php if ($int['prestataire_nom']): ?>
                                <?= e($int['prestataire_prenom'] . ' ' . $int['prestataire_nom']) ?>
                                <?php else: ?>
                                <span style="color: var(--orange);">Non assigné</span>
                                <?php endif; ?>
                            </td>
                            <td><?= getStatutBadge($int['statut']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <?php elseif ($page === 'calendrier'): ?>
            <!-- CALENDRIER -->
            <?php
            $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
            $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

            $firstDay = mktime(0, 0, 0, $month, 1, $year);
            $daysInMonth = date('t', $firstDay);
            $startingDay = date('N', $firstDay);

            $prevMonth = $month - 1;
            $prevYear = $year;
            if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

            $nextMonth = $month + 1;
            $nextYear = $year;
            if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

            // Récupérer les interventions du mois
            $stmt = $conn->prepare("
                SELECT m.*, l.titre as logement_titre
                FROM FC_menages m
                LEFT JOIN FC_logements l ON m.logement_id = l.id
                WHERE MONTH(m.date_intervention) = ? AND YEAR(m.date_intervention) = ?
                AND m.statut != 'annule'
                ORDER BY m.heure_debut ASC
            ");
            $stmt->execute([$month, $year]);
            $interventionsMois = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Grouper par jour
            $interventionsParJour = [];
            foreach ($interventionsMois as $int) {
                $jour = (int)date('j', strtotime($int['date_intervention']));
                if (!isset($interventionsParJour[$jour])) {
                    $interventionsParJour[$jour] = [];
                }
                $interventionsParJour[$jour][] = $int;
            }

            $monthNames = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
            ?>

            <div class="page-header">
                <h1 class="page-title">Calendrier - <?= $monthNames[$month] ?> <?= $year ?></h1>
                <div>
                    <a href="?page=calendrier&month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-secondary btn-sm">← Précédent</a>
                    <a href="?page=calendrier&month=<?= date('m') ?>&year=<?= date('Y') ?>" class="btn btn-primary btn-sm">Aujourd'hui</a>
                    <a href="?page=calendrier&month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-secondary btn-sm">Suivant →</a>
                </div>
            </div>

            <div class="card">
                <div class="calendar-grid">
                    <div class="calendar-header">Lun</div>
                    <div class="calendar-header">Mar</div>
                    <div class="calendar-header">Mer</div>
                    <div class="calendar-header">Jeu</div>
                    <div class="calendar-header">Ven</div>
                    <div class="calendar-header">Sam</div>
                    <div class="calendar-header">Dim</div>

                    <?php
                    // Jours du mois précédent
                    $prevMonthDays = date('t', mktime(0, 0, 0, $prevMonth, 1, $prevYear));
                    for ($i = 1; $i < $startingDay; $i++):
                        $day = $prevMonthDays - $startingDay + $i + 1;
                    ?>
                    <div class="calendar-day other-month">
                        <div class="day-number"><?= $day ?></div>
                    </div>
                    <?php endfor; ?>

                    <?php
                    // Jours du mois courant
                    for ($day = 1; $day <= $daysInMonth; $day++):
                        $isToday = ($day == date('j') && $month == date('m') && $year == date('Y'));
                        $dayInterventions = $interventionsParJour[$day] ?? [];
                    ?>
                    <div class="calendar-day <?= $isToday ? 'today' : '' ?>">
                        <div class="day-number"><?= $day ?></div>
                        <?php foreach (array_slice($dayInterventions, 0, 3) as $int): ?>
                        <div class="event <?= $int['type_menage'] ?>" title="<?= e($int['logement_titre']) ?>">
                            <?= e($int['heure_debut']) ?> <?= e(substr($int['logement_titre'], 0, 10)) ?>
                        </div>
                        <?php endforeach; ?>
                        <?php if (count($dayInterventions) > 3): ?>
                        <div class="event" style="background: #6B7280;">+<?= count($dayInterventions) - 3 ?> autres</div>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>

                    <?php
                    // Jours du mois suivant
                    $totalCells = $startingDay - 1 + $daysInMonth;
                    $remainingCells = 7 - ($totalCells % 7);
                    if ($remainingCells < 7):
                        for ($day = 1; $day <= $remainingCells; $day++):
                    ?>
                    <div class="calendar-day other-month">
                        <div class="day-number"><?= $day ?></div>
                    </div>
                    <?php
                        endfor;
                    endif;
                    ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Légende</h3>
                </div>
                <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                    <div><span class="event depart" style="display: inline-block;">Ménage départ</span></div>
                    <div><span class="event arrivee" style="display: inline-block;">Ménage arrivée</span></div>
                    <div><span class="event complet" style="display: inline-block;">Ménage complet</span></div>
                    <div><span class="event" style="display: inline-block;">Entretien/Autre</span></div>
                </div>
            </div>

            <?php elseif ($page === 'interventions'): ?>
            <!-- LISTE DES INTERVENTIONS -->
            <?php
            $filter = $_GET['filter'] ?? 'all';
            $where = "1=1";
            if ($filter === 'today') $where = "m.date_intervention = CURDATE()";
            elseif ($filter === 'week') $where = "m.date_intervention BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
            elseif ($filter === 'unassigned') $where = "m.prestataire_id IS NULL AND m.statut = 'planifie'";
            elseif ($filter === 'pending') $where = "m.statut IN ('planifie', 'confirme')";

            $stmt = $conn->query("
                SELECT m.*, l.titre as logement_titre, l.adresse as logement_adresse,
                       p.nom as prestataire_nom, p.prenom as prestataire_prenom
                FROM FC_menages m
                LEFT JOIN FC_logements l ON m.logement_id = l.id
                LEFT JOIN FC_prestataires p ON m.prestataire_id = p.id
                WHERE $where
                ORDER BY m.date_intervention DESC, m.heure_debut ASC
                LIMIT 100
            ");
            $interventions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <div class="page-header">
                <h1 class="page-title">Interventions</h1>
                <button class="btn btn-primary" onclick="openModal('addIntervention')">+ Nouvelle intervention</button>
            </div>

            <div class="tabs">
                <a href="?page=interventions&filter=all" class="tab <?= $filter === 'all' ? 'active' : '' ?>">Toutes</a>
                <a href="?page=interventions&filter=today" class="tab <?= $filter === 'today' ? 'active' : '' ?>">Aujourd'hui</a>
                <a href="?page=interventions&filter=week" class="tab <?= $filter === 'week' ? 'active' : '' ?>">7 jours</a>
                <a href="?page=interventions&filter=unassigned" class="tab <?= $filter === 'unassigned' ? 'active' : '' ?>">Non assignées</a>
                <a href="?page=interventions&filter=pending" class="tab <?= $filter === 'pending' ? 'active' : '' ?>">En attente</a>
            </div>

            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Logement</th>
                            <th>Type</th>
                            <th>Prestataire</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($interventions as $int): ?>
                        <tr>
                            <td>
                                <strong><?= date('d/m/Y', strtotime($int['date_intervention'])) ?></strong><br>
                                <small><?= e($int['heure_debut']) ?></small>
                            </td>
                            <td>
                                <strong><?= e($int['logement_titre']) ?></strong><br>
                                <small><?= e($int['logement_adresse']) ?></small>
                            </td>
                            <td><?= getTypeMenageLabel($int['type_menage']) ?></td>
                            <td>
                                <?php if ($int['prestataire_nom']): ?>
                                <?= e($int['prestataire_prenom'] . ' ' . $int['prestataire_nom']) ?>
                                <?php else: ?>
                                <span class="badge badge-warning">Non assigné</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $int['montant'] ? number_format($int['montant'], 2, ',', ' ') . ' €' : '-' ?></td>
                            <td><?= getStatutBadge($int['statut']) ?></td>
                            <td>
                                <?php if ($int['statut'] === 'planifie' || $int['statut'] === 'confirme'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="menage_id" value="<?= $int['id'] ?>">
                                    <button type="submit" name="complete_menage" class="btn btn-success btn-sm">✓</button>
                                    <button type="submit" name="cancel_menage" class="btn btn-danger btn-sm">✕</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php elseif ($page === 'prestataires'): ?>
            <!-- PRESTATAIRES -->
            <div class="page-header">
                <h1 class="page-title">Prestataires</h1>
                <button class="btn btn-primary" onclick="openModal('addPrestataire')">+ Nouveau prestataire</button>
            </div>

            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Contact</th>
                            <th>Tarifs forfaitaires</th>
                            <th>Note</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prestataires as $p): ?>
                        <tr>
                            <td>
                                <strong><?= e($p['prenom'] . ' ' . $p['nom']) ?></strong>
                            </td>
                            <td>
                                <?= e($p['telephone']) ?><br>
                                <small><?= e($p['email']) ?></small>
                            </td>
                            <td>
                                Studio: <?= $p['tarif_forfait_studio'] ? $p['tarif_forfait_studio'] . ' €' : '-' ?><br>
                                T2: <?= $p['tarif_forfait_t2'] ? $p['tarif_forfait_t2'] . ' €' : '-' ?><br>
                                T3: <?= $p['tarif_forfait_t3'] ? $p['tarif_forfait_t3'] . ' €' : '-' ?>
                            </td>
                            <td>
                                <?php if ($p['note_moyenne'] > 0): ?>
                                <?= number_format($p['note_moyenne'], 1) ?>/5
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['statut'] === 'actif'): ?>
                                <span class="badge badge-success">Actif</span>
                                <?php elseif ($p['statut'] === 'en_pause'): ?>
                                <span class="badge badge-warning">En pause</span>
                                <?php else: ?>
                                <span class="badge badge-secondary">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-secondary btn-sm" onclick="editPrestataire(<?= htmlspecialchars(json_encode($p)) ?>)">Modifier</button>
                                <?php if ($p['statut'] === 'actif'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="prestataire_id" value="<?= $p['id'] ?>">
                                    <button type="submit" name="delete_prestataire" class="btn btn-danger btn-sm" onclick="return confirm('Désactiver ce prestataire ?')">Désactiver</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php elseif ($page === 'tarifs'): ?>
            <!-- TARIFS -->
            <?php
            $stmt = $conn->query("
                SELECT t.*, l.titre as logement_titre
                FROM FC_tarifs_menage t
                LEFT JOIN FC_logements l ON t.logement_id = l.id
                WHERE t.actif = 1
                ORDER BY l.titre, t.type_menage
            ");
            $tarifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <div class="page-header">
                <h1 class="page-title">Tarifs Ménage</h1>
                <button class="btn btn-primary" onclick="openModal('addTarif')">+ Nouveau tarif</button>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Tarifs par logement</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Logement</th>
                            <th>Type de ménage</th>
                            <th>Montant</th>
                            <th>Durée estimée</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tarifs as $t): ?>
                        <tr>
                            <td><?= e($t['logement_titre']) ?></td>
                            <td><?= getTypeMenageLabel($t['type_menage']) ?></td>
                            <td><strong><?= number_format($t['montant'], 2, ',', ' ') ?> €</strong></td>
                            <td><?= $t['duree_estimee'] ? $t['duree_estimee'] . ' min' : '-' ?></td>
                            <td><?= e($t['description']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php elseif ($page === 'stock'): ?>
            <!-- STOCK -->
            <?php
            $stmt = $conn->query("SELECT * FROM FC_stock_fournitures WHERE actif = 1 ORDER BY categorie, nom");
            $fournitures = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $categories = [
                'produits_menage' => 'Produits ménagers',
                'linge' => 'Linge',
                'consommables' => 'Consommables',
                'equipements' => 'Équipements',
                'autre' => 'Autre'
            ];
            ?>

            <div class="page-header">
                <h1 class="page-title">Stock & Fournitures</h1>
                <button class="btn btn-primary" onclick="openModal('addFourniture')">+ Nouvelle fourniture</button>
            </div>

            <?php foreach ($categories as $catKey => $catLabel): ?>
            <?php $catItems = array_filter($fournitures, fn($f) => $f['categorie'] === $catKey); ?>
            <?php if (!empty($catItems)): ?>
            <div class="card">
                <div class="card-header">
                    <h3><?= $catLabel ?></h3>
                </div>
                <?php foreach ($catItems as $f): ?>
                <div class="stock-item <?= $f['quantite'] <= $f['seuil_alerte'] ? 'alerte' : '' ?>">
                    <div class="stock-info">
                        <h4><?= e($f['nom']) ?></h4>
                        <p>Seuil alerte: <?= $f['seuil_alerte'] ?> <?= e($f['unite']) ?></p>
                    </div>
                    <div class="stock-qty <?= $f['quantite'] <= $f['seuil_alerte'] ? 'low' : '' ?>">
                        <?= $f['quantite'] ?> <?= e($f['unite']) ?>
                    </div>
                    <button class="btn btn-secondary btn-sm" onclick="stockMovement(<?= $f['id'] ?>, '<?= e($f['nom']) ?>')">+/-</button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>

            <?php elseif ($page === 'paiements'): ?>
            <!-- PAIEMENTS -->
            <?php
            $stmt = $conn->query("
                SELECT pp.*, p.nom as prestataire_nom, p.prenom as prestataire_prenom
                FROM FC_paiements_prestataires pp
                LEFT JOIN FC_prestataires p ON pp.prestataire_id = p.id
                ORDER BY pp.date_paiement DESC
                LIMIT 50
            ");
            $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Interventions non payées par prestataire
            $stmt = $conn->query("
                SELECT p.id, p.nom, p.prenom, COUNT(m.id) as nb_interventions, SUM(m.montant) as total_du
                FROM FC_prestataires p
                JOIN FC_menages m ON m.prestataire_id = p.id
                WHERE m.statut = 'termine' AND m.paye = 0
                GROUP BY p.id
                HAVING total_du > 0
            ");
            $impayesParPrestataire = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <div class="page-header">
                <h1 class="page-title">Paiements Prestataires</h1>
                <button class="btn btn-primary" onclick="openModal('addPaiement')">+ Nouveau paiement</button>
            </div>

            <?php if (!empty($impayesParPrestataire)): ?>
            <div class="card" style="border-left: 4px solid var(--orange);">
                <div class="card-header">
                    <h3>À payer</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Prestataire</th>
                            <th>Interventions</th>
                            <th>Total dû</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($impayesParPrestataire as $ip): ?>
                        <tr>
                            <td><?= e($ip['prenom'] . ' ' . $ip['nom']) ?></td>
                            <td><?= $ip['nb_interventions'] ?> interventions</td>
                            <td><strong><?= number_format($ip['total_du'], 2, ',', ' ') ?> €</strong></td>
                            <td>
                                <button class="btn btn-success btn-sm" onclick="payerPrestataire(<?= $ip['id'] ?>, <?= $ip['total_du'] ?>)">Payer</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3>Historique des paiements</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Prestataire</th>
                            <th>Montant</th>
                            <th>Mode</th>
                            <th>Référence</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paiements as $pmt): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($pmt['date_paiement'])) ?></td>
                            <td><?= e($pmt['prestataire_prenom'] . ' ' . $pmt['prestataire_nom']) ?></td>
                            <td><strong><?= number_format($pmt['montant'], 2, ',', ' ') ?> €</strong></td>
                            <td><?= ucfirst($pmt['mode_paiement']) ?></td>
                            <td><?= e($pmt['reference']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- MODALS -->

    <!-- Modal Nouvelle Intervention -->
    <div class="modal" id="addIntervention">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Planifier une intervention</h3>
                <button class="modal-close" onclick="closeModal('addIntervention')">&times;</button>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Logement *</label>
                        <select name="logement_id" required>
                            <option value="">Sélectionner...</option>
                            <?php foreach ($logements as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= e($l['titre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Prestataire</label>
                        <select name="prestataire_id">
                            <option value="">Non assigné</option>
                            <?php foreach ($prestatairesActifs as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['prenom'] . ' ' . $p['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="date_intervention" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Heure début</label>
                        <input type="time" name="heure_debut" value="10:00">
                    </div>
                    <div class="form-group">
                        <label>Heure fin prévue</label>
                        <input type="time" name="heure_fin_prevue">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Type de ménage *</label>
                        <select name="type_menage" required>
                            <option value="depart">Ménage départ</option>
                            <option value="arrivee">Ménage arrivée</option>
                            <option value="complet">Ménage complet</option>
                            <option value="entretien">Entretien</option>
                            <option value="grand_menage">Grand ménage</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Montant (€)</label>
                        <input type="number" name="montant" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label>Instructions</label>
                    <textarea name="instructions" rows="3" placeholder="Instructions spéciales..."></textarea>
                </div>
                <div class="form-group">
                    <label>Réservation liée (optionnel)</label>
                    <input type="number" name="reservation_id" placeholder="ID réservation">
                </div>
                <button type="submit" name="add_menage" class="btn btn-primary">Planifier</button>
            </form>
        </div>
    </div>

    <!-- Modal Nouveau Prestataire -->
    <div class="modal" id="addPrestataire">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Ajouter un prestataire</h3>
                <button class="modal-close" onclick="closeModal('addPrestataire')">&times;</button>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="nom" required>
                    </div>
                    <div class="form-group">
                        <label>Prénom</label>
                        <input type="text" name="prenom">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Téléphone *</label>
                        <input type="tel" name="telephone" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email">
                    </div>
                </div>
                <div class="form-group">
                    <label>Tarif horaire (€)</label>
                    <input type="number" name="tarif_horaire" step="0.01">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Forfait Studio (€)</label>
                        <input type="number" name="tarif_forfait_studio" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Forfait T2 (€)</label>
                        <input type="number" name="tarif_forfait_t2" step="0.01">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Forfait T3 (€)</label>
                        <input type="number" name="tarif_forfait_t3" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Forfait T4+ (€)</label>
                        <input type="number" name="tarif_forfait_t4_plus" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label>Zones d'intervention</label>
                    <input type="text" name="zones_intervention" placeholder="Compiègne, Margny, Venette...">
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="2"></textarea>
                </div>
                <button type="submit" name="add_prestataire" class="btn btn-primary">Ajouter</button>
            </form>
        </div>
    </div>

    <!-- Modal Nouveau Tarif -->
    <div class="modal" id="addTarif">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Ajouter un tarif</h3>
                <button class="modal-close" onclick="closeModal('addTarif')">&times;</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Logement *</label>
                    <select name="logement_id" required>
                        <option value="">Sélectionner...</option>
                        <?php foreach ($logements as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= e($l['titre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Type de ménage *</label>
                        <select name="type_menage" required>
                            <option value="depart">Ménage départ</option>
                            <option value="arrivee">Ménage arrivée</option>
                            <option value="complet">Ménage complet</option>
                            <option value="entretien">Entretien</option>
                            <option value="grand_menage">Grand ménage</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Montant (€) *</label>
                        <input type="number" name="montant" step="0.01" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Durée estimée (minutes)</label>
                    <input type="number" name="duree_estimee">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="2"></textarea>
                </div>
                <button type="submit" name="add_tarif" class="btn btn-primary">Ajouter</button>
            </form>
        </div>
    </div>

    <!-- Modal Nouvelle Fourniture -->
    <div class="modal" id="addFourniture">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Ajouter une fourniture</h3>
                <button class="modal-close" onclick="closeModal('addFourniture')">&times;</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Nom *</label>
                    <input type="text" name="nom" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Catégorie</label>
                        <select name="categorie">
                            <option value="produits_menage">Produits ménagers</option>
                            <option value="linge">Linge</option>
                            <option value="consommables">Consommables</option>
                            <option value="equipements">Équipements</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Unité</label>
                        <input type="text" name="unite" value="unité">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Quantité initiale</label>
                        <input type="number" name="quantite" value="0">
                    </div>
                    <div class="form-group">
                        <label>Seuil d'alerte</label>
                        <input type="number" name="seuil_alerte" value="5">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Prix unitaire (€)</label>
                        <input type="number" name="prix_unitaire" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Fournisseur</label>
                        <input type="text" name="fournisseur">
                    </div>
                </div>
                <button type="submit" name="add_fourniture" class="btn btn-primary">Ajouter</button>
            </form>
        </div>
    </div>

    <!-- Modal Mouvement Stock -->
    <div class="modal" id="stockMovement">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Mouvement de stock</h3>
                <button class="modal-close" onclick="closeModal('stockMovement')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="fourniture_id" id="stock_fourniture_id">
                <p id="stock_fourniture_nom" style="margin-bottom: 1rem; font-weight: bold;"></p>
                <div class="form-row">
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type_mouvement" required>
                            <option value="entree">Entrée (+)</option>
                            <option value="sortie">Sortie (-)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantité</label>
                        <input type="number" name="quantite" required min="1">
                    </div>
                </div>
                <div class="form-group">
                    <label>Intervention liée (optionnel)</label>
                    <input type="number" name="menage_id" placeholder="ID intervention">
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="2"></textarea>
                </div>
                <button type="submit" name="stock_movement" class="btn btn-primary">Enregistrer</button>
            </form>
        </div>
    </div>

    <!-- Modal Paiement -->
    <div class="modal" id="addPaiement">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Enregistrer un paiement</h3>
                <button class="modal-close" onclick="closeModal('addPaiement')">&times;</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Prestataire *</label>
                    <select name="prestataire_id" required id="paiement_prestataire">
                        <option value="">Sélectionner...</option>
                        <?php foreach ($prestatairesActifs as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['prenom'] . ' ' . $p['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Montant (€) *</label>
                        <input type="number" name="montant" step="0.01" required id="paiement_montant">
                    </div>
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="date_paiement" required value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Période début</label>
                        <input type="date" name="periode_debut">
                    </div>
                    <div class="form-group">
                        <label>Période fin</label>
                        <input type="date" name="periode_fin">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Mode de paiement</label>
                        <select name="mode_paiement">
                            <option value="virement">Virement</option>
                            <option value="cheque">Chèque</option>
                            <option value="especes">Espèces</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Référence</label>
                        <input type="text" name="reference">
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="2"></textarea>
                </div>
                <button type="submit" name="add_paiement" class="btn btn-primary">Enregistrer</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function stockMovement(id, nom) {
            document.getElementById('stock_fourniture_id').value = id;
            document.getElementById('stock_fourniture_nom').textContent = nom;
            openModal('stockMovement');
        }

        function payerPrestataire(id, montant) {
            document.getElementById('paiement_prestataire').value = id;
            document.getElementById('paiement_montant').value = montant;
            openModal('addPaiement');
        }

        function editPrestataire(data) {
            // Implémenter l'édition
            alert('Fonctionnalité à venir');
        }

        // Fermer modal en cliquant à l'extérieur
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
