<?php
session_start();
require_once 'config.php';
require_once '../db/connection.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Récupérer l'ID du client depuis GET
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
if (!$client_id) {
    echo "<p>Aucun client sélectionné.</p>";
    return;
}

// Récupérer le nom du client
$stmt = $conn->prepare("SELECT nom FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) {
    echo "<p>Client introuvable.</p>";
    return;
}

// Gestion de la suppression d'une section via GET
if (isset($_GET['delete_text'])) {
    $delete_text_id = intval($_GET['delete_text']);
    $stmt = $conn->prepare("DELETE FROM client_texts WHERE id = ? AND client_id = ?");
    $stmt->execute([$delete_text_id, $client_id]);
    header("Location: ?section=texts&client_id=" . $client_id);
    exit();
}

// Récupérer les textes existants pour ce client
$stmt = $conn->prepare("SELECT id, section, content FROM client_texts WHERE client_id = ?");
$stmt->execute([$client_id]);
$texts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sauvegarde des textes (mise à jour ou insertion) lors de la soumission du formulaire
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_texts'])) {
    if (isset($_POST['content']) && is_array($_POST['content'])) {
        foreach ($_POST['content'] as $section_id => $content) {
            $content = trim($content);
            if (is_numeric($section_id)) {
                // Mise à jour d'une section existante
                $stmt = $conn->prepare("UPDATE client_texts SET content = ? WHERE id = ? AND client_id = ?");
                $stmt->execute([$content, $section_id, $client_id]);
            } else {
                // Insertion d'une nouvelle section avec son nom
                $section_name = isset($_POST['section_names'][$section_id]) ? trim($_POST['section_names'][$section_id]) : "Nouvelle section";
                $stmt = $conn->prepare("INSERT INTO client_texts (client_id, section, content) VALUES (?, ?, ?)");
                $stmt->execute([$client_id, $section_name, $content]);
            }
        }
    }
    header("Location: ?section=texts&client_id=" . $client_id);
    exit();
}
?>

<div>
    <h2>Gérer les textes - <?php echo htmlspecialchars($client['nom'], ENT_QUOTES, 'UTF-8'); ?></h2>
    <form method="post" action="?section=texts&client_id=<?php echo $client_id; ?>">
        <div id="text-sections">
            <?php foreach ($texts as $text): ?>
                <div class="text-section">
                    <label>
                        <?php echo htmlspecialchars($text['section'], ENT_QUOTES, 'UTF-8'); ?>
                        <a href="?section=texts&client_id=<?php echo $client_id; ?>&delete_text=<?php echo $text['id']; ?>" class="btn-delete" onclick="return confirm('Supprimer cette section ?');">Supprimer</a>
                    </label>
                    <textarea class="editor" name="content[<?php echo $text['id']; ?>]"><?php echo htmlspecialchars($text['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn-add" onclick="addTextSection()">➕ Ajouter une section</button>
        <button type="submit" class="btn-save" name="save_texts">💾 Enregistrer</button>
    </form>
</div>

<!-- Inclusion de TinyMCE -->
<script src="https://cdn.tiny.cloud/1/8j8s19p9w1s3m41da54zl7j7r2n61hxwr8vx10lkp06qar8e/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    // Initialiser TinyMCE sur les zones d'édition existantes
    tinymce.init({
        selector: "textarea.editor",
        height: 300,
        plugins: "advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount",
        toolbar: "undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help"
    });

    // Fonction pour ajouter une nouvelle section
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
            // Initialiser TinyMCE uniquement sur la nouvelle zone
            tinymce.init({
                selector: '#editor_' + newId,
                height: 300,
                plugins: "advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount",
                toolbar: "undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help"
            });
        }
    }
</script>
