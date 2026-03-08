<?php
/**
 * Traitement formulaire landing page (guide PDF)
 * Insere dans leads + prospection_leads (CRM)
 */
include '../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: index.php");
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
        $score = 25; // base landing page
        if (!empty($email)) $score += 5;
        $score = min(100, $score);

        $conn->prepare("INSERT INTO prospection_leads
            (nom, email, source, score)
            VALUES (?, ?, 'landing_page', ?)")
            ->execute([$name, $email, $score]);

        header("Location: guide_bienvenue.pdf");
        exit;
    } catch (PDOException $e) {
        error_log('leads/submit.php: ' . $e->getMessage());
        echo "Une erreur interne est survenue.";
    }
}
