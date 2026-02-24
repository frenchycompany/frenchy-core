<?php
session_start();
require '../db/connection.php';

// Récupération de l'ID du client depuis GET
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
if (!$client_id) {
    die("❌ Erreur : Aucun client sélectionné.");
}

// Récupérer le nom du client
$stmt = $conn->prepare("SELECT nom FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) {
    die("❌ Erreur : Client introuvable.");
}

// Gestion de la suppression d'une section via un paramètre GET
if (isset($_GET['delete_text'])) {
    $delete_text_id = intval($_GET['delete_text']);
    $stmt = $conn->prepare("DELETE FROM client_texts WHERE id = ? AND client_id = ?");
    $stmt->execute([$delete_text_id, $client_id]);
    header("Location: manage_texts.php?client_id=" . $client_id);
    exit();
}

// Récupérer les textes existants pour ce client
$stmt = $conn->prepare("SELECT id, section, content FROM client_texts WHERE client_id = ?");
$stmt->execute([$client_id]);
$texts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sauvegarde des textes (mise à jour ou insertion) lors de la soumission du formulaire
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($_POST['content'] as $section_id => $content) {
        $content = trim($content);
        if (is_numeric($section_id)) {
            // Mise à jour d'une section existante
            $stmt = $conn->prepare("UPDATE client_texts SET content = ? WHERE id = ?");
            $stmt->execute([$content, $section_id]);
        } else {
            // Insertion d'une nouvelle section avec son nom
            $section_name = trim($_POST['section_names'][$section_id] ?? "Nouvelle section");
            $stmt = $conn->prepare("INSERT INTO client_texts (client_id, section, content) VALUES (?, ?, ?)");
            $stmt->execute([$client_id, $section_name, $content]);
        }
    }
    header("Location: manage_texts.php?client_id=" . $client_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les textes - <?php echo htmlspecialchars($client['nom']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Styles personnalisés et responsive inspirés de index.php -->
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f9f9f9;
            margin: 0;
            padding: 0;
        }
        header {
            background: #007bff;
            color: white;
            padding: 15px;
            text-align: center;
        }
        nav {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }
        nav a {
            background: white;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
            transition: background 0.3s;
        }
        nav a:hover {
            background: #0056b3;
            color: white;
        }
        main {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 5px;
        }
        .btn-add, .btn-save, .btn-delete {
            border: none;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
        }
        .btn-add {
            background: #28a745;
            color: white;
            padding: 10px;
            margin-bottom: 10px;
        }
        .btn-save {
            background: #007bff;
            color: white;
            padding: 10px 15px;
            margin-top: 10px;
        }
        .btn-delete {
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            margin-left: 10px;
            font-size: 0.9em;
        }
        .text-section {
            margin-bottom: 20px;
        }
        .text-section label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        /* Adaptation responsive */
        @media screen and (max-width: 600px) {
            header, nav, main {
                padding: 10px;
            }
            nav a {
                padding: 8px 10px;
                font-size: 0.9em;
            }
            .btn-add, .btn-save, .btn-delete {
                font-size: 0.9em;
                padding: 8px;
            }
        }
    </style>

    <!-- Inclusion de TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/8j8s19p9w1s3m41da54zl7j7r2n61hxwr8vx10lkp06qar8e/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        // Initialisation de TinyMCE sur toutes les zones d'édition existantes
        tinymce.init({
            selector: "textarea.editor",
            height: 300,
            plugins: "advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount",
            toolbar: "undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help"
        });

        // Fonction pour ajouter une nouvelle section de texte
        function addTextSection() {
            let container = document.getElementById("text-sections");
            let sectionName = prompt("Nom de la nouvelle section (ex: À propos, Services...)");
            if (sectionName) {
                let newId = "new_" + Date.now(); // ID unique temporaire
                let newSection = document.createElement("div");
                newSection.classList.add("text-section");
                newSection.innerHTML = `
                    <label>${sectionName}</label>
                    <input type="hidden" name="section_names[${newId}]" value="${sectionName}">
                    <textarea id="editor_${newId}" class="editor" name="content[${newId}]"></textarea>
                `;
                container.appendChild(newSection);
                // Initialiser TinyMCE uniquement sur la nouvelle zone sans réinitialiser les autres
                tinymce.init({
                    selector: '#editor_' + newId,
                    height: 300,
                    plugins: "advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount",
                    toolbar: "undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help"
                });
            }
        }
    </script>
</head>
<body>
    <header>
        <h1>Gérer les textes - <?php echo htmlspecialchars($client['nom']); ?></h1>
        <nav>
            <a href="index.php">Tableau de bord</a>
            <a href="clients.php">Gérer Clients</a>
        </nav>
    </header>
    <main>
        <form method="post">
            <div id="text-sections">
                <?php foreach ($texts as $text): ?>
                    <div class="text-section">
                        <label>
                            <?php echo htmlspecialchars($text['section']); ?>
                            <a href="manage_texts.php?client_id=<?php echo $client_id; ?>&delete_text=<?php echo $text['id']; ?>" class="btn-delete" onclick="return confirm('Supprimer cette section ?');">Supprimer</a>
                        </label>
                        <textarea class="editor" name="content[<?php echo $text['id']; ?>]"><?php echo htmlspecialchars($text['content']); ?></textarea>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn-add" onclick="addTextSection()">➕ Ajouter une section</button>
            <button type="submit" class="btn-save">💾 Enregistrer</button>
        </form>
    </main>
</body>
</html>
