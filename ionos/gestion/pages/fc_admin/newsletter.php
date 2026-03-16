<?php
/** Newsletter & Emails — Page FC Admin */
$nbEmailsSim = 0; $nbEmailsContacts = 0; $emailsSent = [];
try { $nbEmailsSim = $conn->query("SELECT COUNT(DISTINCT email) FROM FC_simulations")->fetchColumn(); } catch (PDOException $e) {}
try { $nbEmailsContacts = $conn->query("SELECT COUNT(DISTINCT email) FROM FC_contacts")->fetchColumn(); } catch (PDOException $e) {}
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS FC_emails_sent (id INT AUTO_INCREMENT PRIMARY KEY, email_to VARCHAR(255), subject VARCHAR(255), body TEXT, sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $emailsSent = $conn->query("SELECT * FROM FC_emails_sent ORDER BY sent_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card text-center p-3"><h2 class="text-success"><?= $nbEmailsSim ?></h2><small>Emails simulations</small></div></div>
    <div class="col-md-4"><div class="card text-center p-3"><h2 class="text-primary"><?= $nbEmailsContacts ?></h2><small>Emails contacts</small></div></div>
    <div class="col-md-4"><div class="card text-center p-3"><h2 class="text-info"><?= count($emailsSent) ?></h2><small>Emails envoyés</small></div></div>
</div>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white"><h6 class="mb-0"><i class="fas fa-envelope"></i> Email individuel</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?= fcCsrfField() ?>
                    <div class="row mb-2">
                        <div class="col"><label class="form-label">Destinataire</label><input type="email" name="email_to" class="form-control" required></div>
                        <div class="col"><label class="form-label">Sujet</label><input type="text" name="email_subject" class="form-control" required></div>
                    </div>
                    <div class="mb-2"><label class="form-label">Message</label><textarea name="email_body" class="form-control" rows="6" required></textarea></div>
                    <button type="submit" name="send_email" class="btn btn-primary w-100">Envoyer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white"><h6 class="mb-0"><i class="fas fa-paper-plane"></i> Newsletter groupée</h6></div>
            <div class="card-body">
                <form method="POST" onsubmit="return confirm('Envoyer à tous les destinataires sélectionnés ?')">
                    <?= fcCsrfField() ?>
                    <div class="mb-2">
                        <label class="form-label">Destinataires</label>
                        <div class="d-flex gap-3">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="to_simulations" checked id="toSim"><label class="form-check-label" for="toSim">Prospects (<?= $nbEmailsSim ?>)</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="to_contacts" id="toCon"><label class="form-check-label" for="toCon">Contacts (<?= $nbEmailsContacts ?>)</label></div>
                        </div>
                    </div>
                    <div class="mb-2"><label class="form-label">Sujet</label><input type="text" name="newsletter_subject" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Contenu</label><textarea name="newsletter_body" class="form-control" rows="6" required></textarea></div>
                    <div class="alert alert-warning py-2 mb-2"><small><strong>RGPD :</strong> Assurez-vous du consentement des destinataires.</small></div>
                    <button type="submit" name="send_newsletter" class="btn btn-success w-100">Envoyer la newsletter</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php if (!empty($emailsSent)): ?>
<div class="card shadow-sm mt-3">
    <div class="card-header bg-secondary text-white"><h6 class="mb-0">Historique des envois</h6></div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead><tr><th>Date</th><th>Destinataire</th><th>Sujet</th></tr></thead>
            <tbody>
            <?php foreach ($emailsSent as $em): ?>
                <tr><td><small><?= date('d/m/Y H:i', strtotime($em['sent_at'])) ?></small></td><td><?= e($em['email_to']) ?></td><td><?= e($em['subject']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
