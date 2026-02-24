<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'errors.log');
header('Content-Type: text/html; charset=UTF-8');

require 'db/connection.php'; // Connexion à la base de données
// Définir 'non' comme valeur par défaut si aucun statut n'est spécifié
$filterStatus = $_GET['status'] ?? 'non';
// Récupérer le statut choisi par l'utilisateur pour le filtre
// $filterStatus = $_GET['status'] ?? '';

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Nombre d'entrées par page
$offset = ($page - 1) * $limit;

// Construction de la requête avec filtre et pagination
$query = "SELECT * FROM image_process";
$params = [];
if ($filterStatus) {
    $query .= " WHERE process_chatgpt = ?";
    $params[] = $filterStatus;
}
$totalQuery = $query;
$query .= " LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le nombre total d'entrées pour la pagination
$totalStmt = $conn->prepare($totalQuery);
$totalStmt->execute($params);
$total = $totalStmt->rowCount();
$totalPages = ceil($total / $limit);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Images</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome (icônes) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: auto;
        }
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 50%;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .close {
            cursor: pointer;
            font-size: 1.5rem;
            font-weight: bold;
            color: #aaa;
        }
    </style>
</head>
<body>
<div class="container my-4">

    <!-- Filtre par statut -->
 
<form method="GET" style="margin-bottom: 20px;">
    <label for="status">Filtrer par statut :</label>
    <select name="status" id="status" class="form-select">
        <option value="" <?= $filterStatus === '' ? 'selected' : '' ?>>Tous</option>
        <option value="non" <?= $filterStatus === 'non' ? 'selected' : '' ?>>Non traité</option>
        <option value="en_cours" <?= $filterStatus === 'en_cours' ? 'selected' : '' ?>>En cours</option>
        <option value="ok" <?= $filterStatus === 'ok' ? 'selected' : '' ?>>Traité avec succès</option>
        <option value="ko" <?= $filterStatus === 'ko' ? 'selected' : '' ?>>Échec du traitement</option>
    </select>
    <button type="submit" class="btn btn-primary">Appliquer</button>
</form>

    <!-- Table responsive -->
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Source</th>
                <th>Texte</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($images as $image): ?>
    <?php $imageContent = preg_replace('/[^\x20-\x7E]/', '', $image['image_content']); // Retirer les caractères non imprimables ?>
    <tr>
        <td><?= htmlspecialchars($image['imageid'], ENT_QUOTES) ?></td>
        <td><a href="<?= htmlspecialchars($image['image_source'], ENT_QUOTES) ?>" target="_blank" class="btn btn-sm btn-secondary">Voir l'image</a></td>
        <td>
            <button class="btn btn-sm btn-info" onclick="openModal(<?= htmlspecialchars($image['imageid'], ENT_QUOTES) ?>, '<?= htmlspecialchars(json_encode($imageContent, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES) ?>')">
                Voir le texte
            </button>
        </td>
                    <td><?= htmlspecialchars($image['process_chatgpt'], ENT_QUOTES) ?></td>
                    <td>
                        <form method="POST" action="process_chatgpt.php" style="display: inline;">
                            <input type="hidden" name="imageid" value="<?= $image['imageid'] ?>">
                            <button type="submit" class="btn btn-sm btn-primary">Traiter GPT</button>
                        </form>
    <!-- Nettoyer le texte -->
    <form method="POST" action="process_cleangpt.php" style="display: inline;">
        <input type="hidden" name="imageid" value="<?= htmlspecialchars($image['imageid'], ENT_QUOTES) ?>">
        <button type="submit" class="btn btn-sm btn-warning">Nettoyer le texte</button>
    </form>
    <form method="POST" action="process_split.php" style="display: inline;">
        <input type="hidden" name="imageid" value="<?= $image['imageid'] ?>">
        <button type="submit" class="btn btn-sm btn-warning">Alléger</button>
    </form>



                        <button class="btn btn-sm btn-danger" onclick="deleteImage(<?= $image['imageid'] ?>)">
                            <i class="fa-solid fa-trash"></i> Supprimer
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <nav>
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a href="?page=<?= $i ?>&status=<?= htmlspecialchars($filterStatus) ?>" class="page-link"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>

<!-- Modale -->
<div id="modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Modifier le texte</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <textarea id="modal-text" class="form-control mb-3" rows="5"></textarea>
        <input type="hidden" id="modal-id">
        <button class="btn btn-success" onclick="saveText()">Sauvegarder</button>
    </div>
</div>

<!-- Scripts -->
<script>
function openModal(id, text) {
    try {
        document.getElementById('modal').style.display = 'block';
        document.getElementById('modal-text').value = JSON.parse(text);
        document.getElementById('modal-id').value = id;
    } catch (e) {
        alert('Erreur lors de l\'ouverture de la modale : ' + e.message);
        console.error('Contenu JSON invalide :', text);
    }
}

    function closeModal() {
        document.getElementById('modal').style.display = 'none';
    }

    function saveText() {
        const id = document.getElementById('modal-id').value;
        const text = document.getElementById('modal-text').value;

        fetch('save_text.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, text })
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  alert('Texte sauvegardé avec succès.');
                  closeModal();
                  location.reload();
              } else {
                  alert('Erreur : ' + data.message);
              }
          });
    }
</script>
</body>
</html>
