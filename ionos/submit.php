<?php
/**
 * Traitement formulaire de contact — Site Frenchy Conciergerie
 * Insere dans la table leads + prospection_leads (CRM)
 */

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: index.php?error=1");
        exit;
    }

    try {
        // Table leads legacy
        $stmt = $conn->prepare("SELECT COUNT(*) FROM leads WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if (!$stmt->fetchColumn()) {
            $conn->prepare("INSERT INTO leads (name, email) VALUES (:name, :email)")
                ->execute([':name' => $name, ':email' => $email]);
        }

        // Creer le lead dans le CRM
        $score = 30; // base formulaire contact
        if (!empty($email)) $score += 5;
        if (!empty($telephone)) $score += 15;
        $score = min(100, $score);

        $conn->prepare("INSERT INTO prospection_leads
            (nom, email, telephone, source, score, notes)
            VALUES (?, ?, ?, 'formulaire_contact', ?, ?)")
            ->execute([$name, $email, $telephone ?: null, $score, $message ?: null]);

        header("Location: merci.php");
        exit;
    } catch (PDOException $e) {
        error_log('submit.php: ' . $e->getMessage());
        header("Location: index.php?error=1");
        exit;
    }
}

header("Location: index.php");
exit;
