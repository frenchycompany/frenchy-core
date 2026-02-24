<?php
// Afficher les erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/db.php';
include '../includes/header.php';

// Modem fixe
$default_modem = '/dev/ttyUSB9';

// --- Gestion du prompt IA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prompt'])) {
    $new_prompt = $conn->real_escape_string($_POST['ia_prompt']);
    $sql = "
      INSERT INTO ai_prompts (`key`, content)
      VALUES ('satisfaction', '$new_prompt')
      ON DUPLICATE KEY UPDATE
        content = '$new_prompt'
    ";
    if ($conn->query($sql)) {
        echo "<div class='alert alert-success'>✅ Prompt IA mis à jour.</div>";
    } else {
        echo "<div class='alert alert-danger'>❌ " . htmlspecialchars($conn->error) . "</div>";
    }
}
$sql = "SELECT content FROM ai_prompts WHERE `key` = 'satisfaction'";
$res = $conn->query($sql);
$ia_prompt = ($res && $res->num_rows) ? $res->fetch_assoc()['content'] : '';

// --- Charger les templates de SMS ---
$templates = [];
$sql_tpl   = "SELECT id, name FROM sms_templates WHERE campaign = 'satisfaction' ORDER BY name";
$res_tpl   = $conn->query($sql_tpl);
if ($res_tpl) {
    while ($row = $res_tpl->fetch_assoc()) {
        $templates[] = $row;
    }
}

// --- Envoi de SMS via template ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms'])) {
    $receiver    = $conn->real_escape_string($_POST['receiver']);
    $prenom      = $conn->real_escape_string($_POST['prenom']);
    $template_id = intval($_POST['template_id']);
    $modem       = $default_modem;

    if ($receiver && $prenom && $template_id) {
        // Récupérer le texte du template
        $sql_t = "SELECT template FROM sms_templates WHERE id = $template_id";
        $t_res = $conn->query($sql_t);
        if ($t_res && $t_res->num_rows) {
            $tpl = $t_res->fetch_assoc()['template'];
            // Remplacer {prenom}
            $message = str_replace('{prenom}', $prenom, $tpl);

            // Planifier l'envoi
            $sql1 = "
              INSERT INTO sms_outbox (receiver,message,modem,status)
              VALUES ('$receiver','$message','$modem','pending')
            ";
            if ($conn->query($sql1)) {
                echo "<div class='alert alert-success'>📤 SMS en attente d'envoi.</div>";
                // Historiser
                $sql2 = "
                  INSERT INTO satisfaction_conversations (sender,role,message)
                  VALUES ('$receiver','assistant','$message')
                ";
                if (!$conn->query($sql2)) {
                    echo "<div class='alert alert-warning'>⚠️ Historisation partielle : "
                         . htmlspecialchars($conn->error)
                         . "</div>";
                }
            } else {
                echo "<div class='alert alert-danger'>❌ " 
                     . htmlspecialchars($conn->error) 
                     . "</div>";
            }
        } else {
            echo "<div class='alert alert-danger'>❌ Modèle introuvable.</div>";
        }
    } else {
        echo "<div class='alert alert-warning'>⚠️ Tous les champs sont obligatoires.</div>";
    }
}

// --- Récupérer tous les échanges ---
$sql_ex    = "
    SELECT id, sender, role, message
      FROM satisfaction_conversations
     ORDER BY id DESC
     LIMIT 200
";
$result_ex = $conn->query($sql_ex);
?>

<h2>🛠️ Configuration du prompt IA</h2>
<form method="POST">
  <div class="form-group">
    <label for="ia_prompt">Prompt IA (clé = 'satisfaction') :</label>
    <textarea id="ia_prompt" name="ia_prompt"
              class="form-control" rows="6"
              required><?= htmlspecialchars($ia_prompt) ?></textarea>
  </div>
  <button type="submit" name="save_prompt" class="btn btn-secondary">
    💾 Enregistrer le prompt
  </button>
</form>

<hr>

<h2>📤 Envoyer un SMS</h2>
<form method="POST">
  <div class="form-row">
    <div class="form-group col-md-4">
      <label for="receiver">Numéro du destinataire :</label>
      <input type="text" id="receiver" name="receiver"
             class="form-control" required>
    </div>
    <div class="form-group col-md-4">
      <label for="prenom">Prénom :</label>
      <input type="text" id="prenom" name="prenom"
             class="form-control" required>
    </div>
    <div class="form-group col-md-4">
      <label for="template_id">Modèle de SMS :</label>
      <select id="template_id" name="template_id"
              class="form-control" required>
        <option value="">-- Sélectionnez un modèle --</option>
        <?php foreach ($templates as $tpl): ?>
          <option value="<?= $tpl['id'] ?>">
            <?= htmlspecialchars($tpl['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <button type="submit" name="send_sms" class="btn btn-primary">
    📩 Envoyer
  </button>
</form>

<hr>

<h2>📊 Suivi des échanges</h2>
<?php if ($result_ex && $result_ex->num_rows): ?>
<table class="table table-striped">
  <thead><tr>
    <th>ID</th><th>Sender</th><th>Rôle</th><th>Message</th>
  </tr></thead>
  <tbody>
    <?php while ($row = $result_ex->fetch_assoc()): ?>
    <tr>
      <td><?= $row['id'] ?></td>
      <td><?= htmlspecialchars($row['sender']) ?></td>
      <td><?= htmlspecialchars($row['role']) ?></td>
      <td><?= nl2br(htmlspecialchars($row['message'])) ?></td>
    </tr>
    <?php endwhile; ?>
  </tbody>
</table>
<?php else: ?>
  <p>Aucun échange enregistré.</p>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
