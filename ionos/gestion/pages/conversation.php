<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Liste des numéros uniques (clients)
$liste_numeros = [];
$req = $conn->query("
    SELECT DISTINCT sender AS numero FROM sms_in
    UNION
    SELECT DISTINCT receiver AS numero FROM sms_outbox
    ORDER BY numero ASC
");

while ($row = $req->fetch_assoc()) {
    $liste_numeros[] = $row['numero'];
}

// Numéro sélectionné
$numero = isset($_GET['numero']) ? $_GET['numero'] : null;
?>

<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-body">

            <h3 class="mb-4 text-primary">📞 Suivi des conversations</h3>

            <!-- Menu déroulant de sélection du numéro -->
            <form method="get" class="mb-4">
                <label for="numero">Sélectionnez un numéro :</label>
                <div class="input-group">
                    <select name="numero" id="numero" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Choisir un numéro --</option>
                        <?php foreach ($liste_numeros as $num): ?>
                            <option value="<?= htmlspecialchars($num) ?>" <?= $num === $numero ? 'selected' : '' ?>>
                                <?= htmlspecialchars($num) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <?php if ($numero): ?>
                <?php
                // Charger les messages
                $sql = "
                SELECT * FROM (
                    SELECT 'client' AS emetteur, message, received_at AS date_sms
                    FROM sms_in
                    WHERE sender = ?
                    UNION ALL
                    SELECT 'system' AS emetteur, message, sent_at AS date_sms
                    FROM sms_outbox
                    WHERE receiver = ?
                ) AS conversation
                ORDER BY date_sms ASC
                ";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $numero, $numero);
                $stmt->execute();
                $result = $stmt->get_result();
                ?>

                <h5 class="mb-3">Conversation avec <strong><?= htmlspecialchars($numero) ?></strong></h5>

                <?php if ($result->num_rows > 0): ?>
                    <ul class="list-group">
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <li class="list-group-item 
                                <?= $row['emetteur'] === 'client' ? 'list-group-item-light' : 'list-group-item-success' ?>">
                                <strong><?= ucfirst($row['emetteur']) ?> :</strong><br>
                                <?= nl2br(htmlspecialchars($row['message'])) ?>
                                <div class="text-muted small mt-2 text-end"><?= $row['date_sms'] ?></div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <div class="alert alert-info mt-3">Aucun message pour ce numéro.</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-warning mt-4">
                    Veuillez sélectionner un numéro pour afficher la conversation.
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>


