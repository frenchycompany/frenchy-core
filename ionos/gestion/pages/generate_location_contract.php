<?php
/**
 * Generer un contrat de location - Apercu + telechargement + impression
 * Utilise le meme dossier generated_contracts/ que les contrats de conciergerie
 */
include '../config.php';
include '../pages/menu.php';

// Tables requises : voir db/install_tables.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: create_location_contract.php");
    exit;
}

// Validation CSRF
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Jeton CSRF invalide. Veuillez recharger la page.</div>';
    echo '<a href="create_location_contract.php" class="btn btn-secondary">Retour</a></div>';
    exit;
}

try {
    if (empty($_POST['template_id']) || !is_numeric($_POST['template_id'])) {
        throw new Exception("Modele de contrat non selectionne.");
    }
    $template_id = (int) $_POST['template_id'];

    if (empty($_POST['logement_id']) || !is_numeric($_POST['logement_id'])) {
        throw new Exception("Logement non selectionne.");
    }
    $logement_id = (int) $_POST['logement_id'];

    // Recuperer le modele
    $stmt = $conn->prepare("SELECT title, content FROM location_contract_templates WHERE id = :id");
    $stmt->execute([':id' => $template_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        throw new Exception("Modele de contrat introuvable.");
    }

    $contract_content = $template['content'];

    // Remplacement des placeholders par les donnees POST
    foreach ($_POST as $field_name => $field_value) {
        if (in_array($field_name, ['template_id', 'logement_id', 'csrf_token'])) continue;
        $contract_content = str_replace("{{{$field_name}}}", htmlspecialchars($field_value), $contract_content);
    }

    // Sauvegarder le fichier — meme dossier que les contrats conciergerie
    $timestamp = time();
    $dir = __DIR__ . '/../generated_contracts';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $file_name = "location_contract_{$timestamp}.html";
    $file_path = "$dir/$file_name";

    $full_html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($template['title']) . '</title>
    <style>
        body { font-family: "Times New Roman", serif; font-size: 12pt; line-height: 1.6; margin: 40px; color: #333; }
        h1, h2, h3 { color: #2c3e50; }
        h1 { font-size: 20pt; text-align: center; margin-bottom: 30px; }
        h2 { font-size: 14pt; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-top: 25px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        td, th { padding: 8px; border: 1px solid #ddd; }
        th { background-color: #f5f5f5; text-align: left; width: 40%; }
        .signature { margin-top: 60px; display: flex; justify-content: space-between; }
        .signature-block { width: 45%; border-top: 1px solid #333; padding-top: 10px; text-align: center; }
        .header-info { text-align: center; margin-bottom: 30px; color: #666; }
        ul { margin: 5px 0; padding-left: 20px; }
        @media print { body { margin: 20mm; } }
    </style>
</head>
<body>' . $contract_content . '</body>
</html>';

    file_put_contents($file_path, $full_html);

    // Sauvegarder en BDD
    $user_id = (int)($_SESSION['id_intervenant'] ?? $_SESSION['user_id'] ?? 0);
    if (!$user_id) {
        throw new Exception("Session utilisateur invalide.");
    }
    $voyageur_nom = trim(($_POST['prenom_voyageur'] ?? '') . ' ' . ($_POST['nom_voyageur'] ?? ''));
    $date_arrivee = !empty($_POST['date_arrivee']) ? $_POST['date_arrivee'] : null;
    $date_depart = !empty($_POST['date_depart']) ? $_POST['date_depart'] : null;
    $prix_total = !empty($_POST['prix_total']) ? (float)$_POST['prix_total'] : null;

    // Nom du logement
    $logementStmt = $conn->prepare("SELECT nom_du_logement FROM liste_logements WHERE id = ?");
    $logementStmt->execute([$logement_id]);
    $logement_nom = $logementStmt->fetchColumn() ?: 'Logement #' . $logement_id;

    $stmt = $conn->prepare("
        INSERT INTO generated_location_contracts (user_id, logement_id, template_title, logement_nom, voyageur_nom, date_arrivee, date_depart, prix_total, file_path)
        VALUES (:user_id, :logement_id, :template_title, :logement_nom, :voyageur_nom, :date_arrivee, :date_depart, :prix_total, :file_path)
    ");
    $stmt->execute([
        ':user_id' => $user_id,
        ':logement_id' => $logement_id,
        ':template_title' => $template['title'],
        ':logement_nom' => $logement_nom,
        ':voyageur_nom' => $voyageur_nom ?: null,
        ':date_arrivee' => $date_arrivee,
        ':date_depart' => $date_depart,
        ':prix_total' => $prix_total,
        ':file_path' => "generated_contracts/$file_name"
    ]);
    $contract_id = $conn->lastInsertId();

} catch (Exception $e) {
    echo '<div class="container mt-4"><div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<a href="create_location_contract.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a></div>';
    exit;
}
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-check-circle text-success"></i> Contrat de location genere</h2>
            <p class="text-muted">
                <strong><?= htmlspecialchars($template['title']) ?></strong> — <?= htmlspecialchars($logement_nom) ?>
                <?php if ($voyageur_nom): ?> — <span class="text-primary"><?= htmlspecialchars($voyageur_nom) ?></span><?php endif; ?>
            </p>
        </div>
        <div class="col-md-4 text-end">
            <a href="create_location_contract.php" class="btn btn-warning text-dark">
                <i class="fas fa-plus"></i> Nouveau contrat
            </a>
            <a href="location_contrats_generes.php" class="btn btn-outline-dark">
                <i class="fas fa-history"></i> Historique
            </a>
        </div>
    </div>

    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> Le contrat de location a ete genere avec succes !
        <?php if ($voyageur_nom): ?>
            <br><strong>Voyageur :</strong> <?= htmlspecialchars($voyageur_nom) ?>
        <?php endif; ?>
        <?php if ($date_arrivee && $date_depart): ?>
            | <strong>Sejour :</strong> <?= date('d/m/Y', strtotime($date_arrivee)) ?> au <?= date('d/m/Y', strtotime($date_depart)) ?>
        <?php endif; ?>
        <?php if ($prix_total): ?>
            | <strong>Total :</strong> <?= number_format($prix_total, 2, ',', ' ') ?> EUR
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="card shadow-sm mb-4">
        <div class="card-body d-flex gap-2 flex-wrap">
            <a href="../generated_contracts/<?= $file_name ?>" download class="btn btn-primary btn-lg">
                <i class="fas fa-download"></i> Telecharger (HTML)
            </a>
            <button onclick="printContract()" class="btn btn-outline-primary btn-lg">
                <i class="fas fa-print"></i> Imprimer / PDF
            </button>
            <button onclick="togglePreview()" class="btn btn-outline-secondary btn-lg" id="toggleBtn">
                <i class="fas fa-eye"></i> Apercu
            </button>
        </div>
    </div>

    <!-- Apercu -->
    <div class="card shadow-sm" id="previewCard">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-eye"></i> Apercu du contrat</h5>
            <span class="badge bg-warning text-dark">Contrat location #<?= $contract_id ?></span>
        </div>
        <div class="card-body p-0">
            <iframe id="contractPreview" src="../generated_contracts/<?= $file_name ?>"
                    style="width: 100%; height: 800px; border: none;"></iframe>
        </div>
    </div>
</div>

<script>
function printContract() {
    const iframe = document.getElementById('contractPreview');
    iframe.contentWindow.focus();
    iframe.contentWindow.print();
}

function togglePreview() {
    const card = document.getElementById('previewCard');
    const btn = document.getElementById('toggleBtn');
    if (card.style.display === 'none') {
        card.style.display = 'block';
        btn.innerHTML = '<i class="fas fa-eye-slash"></i> Masquer';
    } else {
        card.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-eye"></i> Apercu';
    }
}
</script>
