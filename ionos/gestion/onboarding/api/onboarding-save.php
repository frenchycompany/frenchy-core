<?php
/**
 * API AJAX — Sauvegarde des etapes d'onboarding
 * POST JSON: { token, etape, ...fields }
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

// Ne pas demarrer de session HTML/redirect depuis l'API
require_once __DIR__ . '/../../includes/env_loader.php';
require_once __DIR__ . '/../../db/connection.php';
require_once __DIR__ . '/../includes/onboarding-helper.php';

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur connexion DB']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token manquant']);
    exit;
}

$token = $input['token'];
$etape = (int)($input['etape'] ?? 1);

// Verifier que la demande existe
$request = onboarding_load($conn, $token);
if (!$request) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Demande introuvable']);
    exit;
}

if ($request['statut'] === 'termine') {
    echo json_encode(['success' => false, 'error' => 'Cette demande est deja finalisee']);
    exit;
}

// Validation par etape
$errors = [];
switch ($etape) {
    case 1:
        if (empty($input['adresse'])) $errors[] = 'Adresse requise';
        if (empty($input['code_postal'])) $errors[] = 'Code postal requis';
        if (empty($input['ville'])) $errors[] = 'Ville requise';
        if (empty($input['typologie'])) $errors[] = 'Type de bien requis';
        if (empty($input['superficie'])) $errors[] = 'Superficie requise';
        break;
    case 2:
        if (empty($input['prenom'])) $errors[] = 'Prenom requis';
        if (empty($input['nom'])) $errors[] = 'Nom requis';
        if (empty($input['email']) || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email valide requis';
        if (empty($input['telephone'])) $errors[] = 'Telephone requis';

        // Verifier unicite email
        if (!empty($input['email'])) {
            $stmtCheck = $conn->prepare("SELECT id FROM FC_proprietaires WHERE email = ?");
            $stmtCheck->execute([$input['email']]);
            if ($stmtCheck->fetch()) {
                $errors[] = 'Cet email est deja utilise. Contactez-nous si c\'est le votre.';
            }
        }
        break;
    case 3:
        // Equipements optionnels — pas de validation bloquante
        break;
    case 4:
        if (empty($input['pack']) || !in_array($input['pack'], ['autonome', 'serenite', 'cle_en_main'])) {
            $errors[] = 'Pack invalide';
        }
        break;
    case 5:
        if (empty($input['prix_souhaite']) || $input['prix_souhaite'] < 15) {
            $errors[] = 'Prix par nuit requis (minimum 15 EUR)';
        }
        break;
    case 6:
        if (empty($input['conditions_acceptees'])) $errors[] = 'Conditions requises';
        if (empty($input['rgpd_accepte'])) $errors[] = 'RGPD requis';
        break;
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'error' => implode('. ', $errors)]);
    exit;
}

// Sauvegarder l'etape
$saved = onboarding_save_step($conn, $token, $etape, $input);

if (!$saved) {
    echo json_encode(['success' => false, 'error' => 'Erreur de sauvegarde']);
    exit;
}

// Si etape 6 + finalize → creer le proprietaire et le logement
$result = ['success' => true, 'etape' => $etape];

if ($etape === 6 && !empty($input['finalize'])) {
    try {
        $finalized = onboarding_finalize($conn, $token);
        if ($finalized) {
            $result['finalized'] = true;
            $result['proprietaire_id'] = $finalized['proprietaire_id'];
            $result['logement_id'] = $finalized['logement_id'];
            $result['code_parrainage'] = $finalized['code_parrainage'];
        } else {
            $result['success'] = false;
            $result['error'] = 'Erreur lors de la finalisation';
        }
    } catch (Exception $e) {
        error_log('onboarding finalize error: ' . $e->getMessage());
        $result['success'] = false;
        $result['error'] = 'Erreur interne lors de la finalisation';
    }
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
