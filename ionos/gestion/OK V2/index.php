<?php
// index.php

// Affichage des erreurs en développement (à désactiver en production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Pour le développement, si vous n'utilisez pas HTTPS, définissez 'secure' à false
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '', // ajuster selon votre domaine
        'secure'   => false, // false pour le développement si HTTPS n'est pas utilisé
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Régénération de l'ID de session une seule fois après authentification
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

// Vérifie que l'utilisateur est connecté (vérification des deux variables de session)
if (!isset($_SESSION['nom_utilisateur']) || !isset($_SESSION['id_intervenant'])) {
    header("Location: login.php");
    exit;
}
$nom_utilisateur = filter_var($_SESSION['nom_utilisateur'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Mise en place d'un jeton CSRF pour sécuriser les formulaires
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Inclusion des fichiers de configuration et du menu
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/pages/menu.php';

// Variables initiales
$intervenant_id = null;
$paie_actuelle = 0;
$interventions = [];
$todo_list = [];
$notifications = [];

// Récupération des paramètres GET pour la recherche et la pagination
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $itemsPerPage;
$mois_courant = date('m');
$annee_courante = date('Y');

/**
 * Récupère l'ID de l'intervenant associé au nom_utilisateur
 */
function getIntervenantId($conn, $nom_utilisateur) {
    $stmt = $conn->prepare("SELECT id FROM intervenant WHERE nom_utilisateur = :nom_utilisateur");
    $stmt->bindValue(':nom_utilisateur', $nom_utilisateur, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['id'] ?? null;
}

/**
 * Calcule la paie actuelle pour le mois et l'année donnés
 */
function getPaieActuelle($conn, $intervenant_id, $mois, $annee) {
    $stmt = $conn->prepare("
        SELECT SUM(montant) AS total_paie
        FROM comptabilite
        WHERE intervenant_id = :intervenant_id 
          AND type = 'Charge'
          AND MONTH(date_comptabilisation) = :mois
          AND YEAR(date_comptabilisation) = :annee
    ");
    $stmt->bindValue(':intervenant_id', $intervenant_id, PDO::PARAM_INT);
    $stmt->bindValue(':mois', (int)$mois, PDO::PARAM_INT);
    $stmt->bindValue(':annee', (int)$annee, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total_paie'] ?? 0;
}

/**
 * Récupère les interventions avec pagination et filtre de recherche.
 */
function getInterventions($conn, $intervenant_id, $offset, $limit, $search_query = '') {
    $sql = "SELECT c.date_comptabilisation, c.montant, c.description, l.nom_du_logement, c.source_type
            FROM comptabilite c
            LEFT JOIN liste_logements l ON c.source_id = l.id
            WHERE c.intervenant_id = :intervenant_id";
    if ($search_query !== '') {
        $sql .= " AND (l.nom_du_logement LIKE :search OR c.source_type LIKE :search OR c.description LIKE :search)";
    }
    $sql .= " ORDER BY c.date_comptabilisation DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':intervenant_id', $intervenant_id, PDO::PARAM_INT);
    if ($search_query !== '') {
        $stmt->bindValue(':search', '%' . $search_query . '%', PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère le nombre total d'interventions (pour la pagination) avec recherche éventuelle
 */
function getTotalInterventions($conn, $intervenant_id, $search_query = '') {
    $sql = "SELECT COUNT(*) AS total FROM comptabilite WHERE intervenant_id = :intervenant_id";
    if ($search_query !== '') {
        $sql .= " AND (source_type LIKE :search OR description LIKE :search)";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':intervenant_id', $intervenant_id, PDO::PARAM_INT);
    if ($search_query !== '') {
        $stmt->bindValue(':search', '%' . $search_query . '%', PDO::PARAM_STR);
    }
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

/**
 * Récupère la liste des tâches à faire pour l'utilisateur connecté
 */
function getTodoList($conn, $nom_utilisateur) {
    $stmt = $conn->prepare("
        SELECT p.id AS intervention_id, l.nom_du_logement, p.statut, p.date AS date_intervention
        FROM planning p
        LEFT JOIN liste_logements l ON p.logement_id = l.id
        WHERE (p.conducteur = :nom_utilisateur 
               OR p.femme_de_menage_1 = :nom_utilisateur 
               OR p.femme_de_menage_2 = :nom_utilisateur 
               OR p.laverie = :nom_utilisateur)
          AND p.statut = 'À Faire'
        ORDER BY p.date ASC
    ");
    $stmt->bindValue(':nom_utilisateur', $nom_utilisateur, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les notifications récentes pour l'utilisateur
 */
function getNotifications($conn, $nom_utilisateur) {
    $stmt = $conn->prepare("
        SELECT message, type, date_notification 
        FROM notifications 
        WHERE nom_utilisateur = :nom_utilisateur 
        ORDER BY date_notification DESC 
        LIMIT 5
    ");
    $stmt->bindValue(':nom_utilisateur', $nom_utilisateur, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    $intervenant_id = getIntervenantId($conn, $nom_utilisateur);
    if (!$intervenant_id) {
        echo "Intervenant introuvable pour l'utilisateur connecté.";
        exit;
    }
    $paie_actuelle     = getPaieActuelle($conn, $intervenant_id, $mois_courant, $annee_courante);
    $interventions     = getInterventions($conn, $intervenant_id, $offset, $itemsPerPage, $search_query);
    $totalInterventions = getTotalInterventions($conn, $intervenant_id, $search_query);
    $todo_list         = getTodoList($conn, $nom_utilisateur);
    $notifications     = getNotifications($conn, $nom_utilisateur);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des données : " . $e->getMessage());
}

$totalPages = ceil($totalInterventions / $itemsPerPage);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Accueil</h1>
        <div>
            <a href="profil.php" class="btn btn-secondary me-2">Mon Profil</a>
            <form action="logout.php" method="post" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <button type="submit" class="btn btn-danger">Déconnexion</button>
            </form>
        </div>
    </div>
    <div id="notifications" class="mb-3" aria-live="polite" aria-atomic="true">
        <?php if (!empty($notifications)): ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="alert alert-info" role="alert">
                    <strong><?= htmlspecialchars($notif['date_notification'] ?? '') ?>:</strong>
                    <?= htmlspecialchars($notif['message'] ?? '') ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-secondary" role="alert">Aucune notification.</div>
        <?php endif; ?>
    </div>
    <div class="alert alert-success text-center" role="alert">
        <strong>Bonjour, <?= htmlspecialchars($nom_utilisateur) ?> !</strong>
    </div>
    <div class="alert alert-info text-center" role="alert">
        <strong>Votre paie pour ce mois :</strong>
        <span><?= number_format($paie_actuelle, 2, ',', ' ') ?> €</span>
    </div>
    <form method="get" action="" class="row mb-4">
        <div class="col-md-8">
            <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" class="form-control" placeholder="Rechercher des interventions..." aria-label="Rechercher">
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary w-100">Rechercher</button>
        </div>
    </form>
    <h3>Interventions récentes</h3>
    <table class="table table-striped" aria-describedby="tableInterventions">
        <thead>
        <tr>
            <th scope="col">Date</th>
            <th scope="col">Logement</th>
            <th scope="col">Rôle</th>
            <th scope="col">Montant</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!empty($interventions)): ?>
            <?php foreach ($interventions as $intervention): ?>
                <tr>
                    <td><?= htmlspecialchars($intervention['date_comptabilisation'] ?? '') ?></td>
                    <td><?= htmlspecialchars($intervention['nom_du_logement'] ?? '') ?></td>
                    <td><?= htmlspecialchars($intervention['source_type'] ?? '') ?></td>
                    <td><?= number_format($intervention['montant'] ?? 0, 2, ',', ' ') ?> €</td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4" class="text-center">Aucune intervention trouvée.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php if ($totalPages > 1): ?>
        <nav aria-label="Pagination des interventions">
            <ul class="pagination justify-content-center">
                <?php if ($currentPage > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $currentPage - 1 ?>&search=<?= urlencode($search_query) ?>" aria-label="Précédent">Précédent</a>
                    </li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= ($i === $currentPage) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search_query) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($currentPage < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $currentPage + 1 ?>&search=<?= urlencode($search_query) ?>" aria-label="Suivant">Suivant</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
    <h3>Tâches à faire</h3>
    <table class="table table-bordered" aria-describedby="tableTodo">
        <thead>
        <tr>
            <th scope="col">ID</th>
            <th scope="col">Logement</th>
            <th scope="col">Statut</th>
            <th scope="col">Date</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!empty($todo_list)): ?>
            <?php foreach ($todo_list as $todo): ?>
                <tr>
                    <td><?= htmlspecialchars($todo['intervention_id'] ?? '') ?></td>
                    <td><?= htmlspecialchars($todo['nom_du_logement'] ?? '') ?></td>
                    <td><?= htmlspecialchars($todo['statut'] ?? '') ?></td>
                    <td><?= htmlspecialchars($todo['date_intervention'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4" class="text-center">Aucune tâche à faire.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    <h3>Tableau de bord</h3>
    <canvas id="dashboardChart" aria-label="Graphique de la paie et des interventions" role="img"></canvas>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Rafraîchissement périodique des notifications
    setInterval(function() {
        fetch('ajax/notifications.php')
            .then(response => response.text())
            .then(html => {
                document.getElementById('notifications').innerHTML = html;
            })
            .catch(error => console.error('Erreur AJAX:', error));
    }, 30000);

    // Affichage du graphique avec Chart.js
    const ctx = document.getElementById('dashboardChart').getContext('2d');
    const dashboardChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Paie', 'Interventions', 'Tâches'],
            datasets: [{
                label: 'Statistiques',
                data: [
                    <?= json_encode((float)$paie_actuelle) ?>,
                    <?= json_encode((int)$totalInterventions) ?>,
                    <?= json_encode(count($todo_list)) ?>
                ],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.5)',
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(255, 205, 86, 0.5)'
                ],
                borderColor: [
                    'rgb(54, 162, 235)',
                    'rgb(255, 99, 132)',
                    'rgb(255, 205, 86)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
</script>
</body>
</html>
