<?php
session_start();
require '../db/connection.php';

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

if (!$client_id) {
    die("❌ Erreur : Aucun client sélectionné.");
}

// 📌 Récupérer les informations du client
$stmt = $conn->prepare("SELECT nom FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    die("❌ Erreur : Client introuvable.");
}

// 📌 Récupérer les textes existants pour ce client
$stmt = $conn->prepare("SELECT id, section, content FROM client_texts WHERE client_id = ?");
$stmt->execute([$client_id]);
$texts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 📌 Sauvegarde des textes (si soumis via POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($_POST['content'] as $section_id => $content) {
        if (is_numeric($section_id)) {
            // 🔄 Mise à jour du texte existant
            $stmt = $conn->prepare("UPDATE client_texts SET content = ? WHERE id = ?");
            $stmt->execute([$content, $section_id]);
        } else {
            // ➕ Insertion d'un nouveau texte avec son nom
            $section_name = $_POST['section_names'][$section_id] ?? "Nouvelle section";
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
    <script src="https://cdn.tiny.cloud/1/8j8s19p9w1s3m41da54zl7j7r2n61hxwr8vx10lkp06qar8e/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
    
    <script>
        tinymce.init({
            selector: "textarea.editor",
            height: 300,
            plugins: "advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount",
            toolbar: "undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help"
        });

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
                    <textarea class="editor" name="content[${newId}]"></textarea>
                `;
                container.appendChild(newSection);
                tinymce.init({ selector: 'textarea.editor' });
            }
        }
    </script>

    <style>
        .btn-add { background: #28a745; color: white; padding: 10px; border: none; cursor: pointer; margin-bottom: 10px; }
        .btn-save { background: #007bff; color: white; padding: 10px; border: none; cursor: pointer; margin-top: 10px; }
        .text-section { margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>Gérer les textes - <?php echo htmlspecialchars($client['nom']); ?></h1>

    <form method="post">
        <div id="text-sections">
            <?php foreach ($texts as $text): ?>
                <div class="text-section">
                    <label><?php echo htmlspecialchars($text['section']); ?></label>
                    <textarea class="editor" name="content[<?php echo $text['id']; ?>]"><?php echo htmlspecialchars($text['content']); ?></textarea>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="btn-add" onclick="addTextSection()">➕ Ajouter une section</button>
        <button type="submit" class="btn-save">💾 Enregistrer</button>
    </form>
</body>
</html>
