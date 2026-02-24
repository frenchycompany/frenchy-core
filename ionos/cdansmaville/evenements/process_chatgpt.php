<?php
ini_set('display_errors', 1);
header('Content-Type: application/json');
require 'db/connection.php'; // Connexion à la base de données

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imageId = $_POST['imageid'] ?? null;

    if (!$imageId) {
        echo json_encode(['success' => false, 'message' => 'ID de l\'image non fourni.']);
        exit;
    }

    try {
        // Récupérer l'année actuelle (pour gérer les dates implicites)
        $currentYear = date('Y');

        // Récupérer le texte nettoyé depuis la table `image_process`
        $stmt = $conn->prepare("SELECT image_content FROM image_process WHERE imageid = ?");
        $stmt->execute([$imageId]);
        $imageData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$imageData) {
            echo json_encode(['success' => false, 'message' => 'Image non trouvée.']);
            exit;
        }

        $textToProcess = $imageData['image_content'];

        // Appel à GPT pour structurer les données
        $response = @file_get_contents("https://api.openai.com/v1/chat/completions", false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer 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\r\n",
                'content' => json_encode([
                    'model' => 'gpt-4o',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => "Le texte suivant contient plusieurs événements extraits d'une reconnaissance optique (OCR). Structure chaque événement en JSON distinct avec les champs suivants :
                            - titre
                            - description
                            - date_debut
                            - date_fin
                            - heure_debut
                            - heure_fin
                            - nom_lieu
                            - ville
                            - contact_nom
                            - contact_telephone
                            - contact_email
                            - site_web
                            - prix
                            - tags (sous forme de tableau)
                            Tous les champs sont optionnels. Si une date est partielle (par exemple, uniquement jour et mois), suppose que l'année est $currentYear. Retourne STRICTEMENT un tableau JSON. Voici le texte : $textToProcess"
                        ]
                    ],
                    'max_tokens' => 3000
                ])
            ]
        ]));

        // Vérification de l'appel API
        if ($response === false) {
            $error = error_get_last();
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la requête OpenAI.', 'debug' => $error]);
            exit;
        }

        $result = json_decode($response, true);

        // Validation de la réponse OpenAI
        if (!isset($result['choices'][0]['message']['content'])) {
            echo json_encode(['success' => false, 'message' => 'Réponse OpenAI non valide.', 'debug' => $result]);
            exit;
        }

        // Récupérer le contenu renvoyé par GPT
        $gptContent = $result['choices'][0]['message']['content'];

        // Nettoyer les balises Markdown (```json```) et séparer les blocs JSON individuels
        $cleanContent = preg_replace('/^```json|```$/m', '', $gptContent);

        // Décoder le JSON nettoyé
        $events = json_decode($cleanContent, true);

        // Vérifier si la réponse contient plusieurs événements sous forme de tableau
        if (!is_array($events)) {
            // Si le JSON est mal formé, insérer tel quel dans un champ "brut" de la base pour analyse
            $stmt = $conn->prepare("INSERT INTO structured_events_raw (imageid, raw_content) VALUES (?, ?)");
            $stmt->execute([$imageId, $gptContent]);
            echo json_encode([
                'success' => false,
                'message' => 'Le contenu GPT est mal formé et a été sauvegardé brut.',
                'debug' => $cleanContent
            ]);
            exit;
        }

        // Insérer les événements dans la base de données
        $insertedCount = 0;
        $failedEvents = [];
        foreach ($events as $event) {
            try {
                // Compléter les dates ambiguës ou manquantes
                $dateDebut = $event['date_debut'] ?? null;
                $dateFin = $event['date_fin'] ?? $dateDebut;

                // Si aucune année n'est donnée, ajouter l'année actuelle
                if ($dateDebut && preg_match('/^\d{2}-\d{2}$/', $dateDebut)) {
                    $dateDebut = "$currentYear-$dateDebut";
                }
                if ($dateFin && preg_match('/^\d{2}-\d{2}$/', $dateFin)) {
                    $dateFin = "$currentYear-$dateFin";
                }

                // Si `date_debut` est absente mais `date_fin` est présente, générer une date aléatoire antérieure
                if (!$dateDebut && $dateFin) {
                    $dateDebut = date('Y-m-d', strtotime("$dateFin -" . rand(30, 90) . " days"));
                }

                // Si une date est multiple (comme dans les horaires groupés), insérer chaque occurrence
                if (isset($event['horaires']) && is_array($event['horaires'])) {
                    foreach ($event['horaires'] as $horaire) {
                        $stmt = $conn->prepare("INSERT INTO structured_events 
                            (titre, description, date_debut, date_fin, heure_debut, heure_fin, nom_lieu, ville, contact_nom, contact_telephone, contact_email, site_web, prix, tags) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $event['titre'] ?? null,
                            $event['description'] ?? null,
                            $horaire['date'] ?? $dateDebut,
                            $horaire['date'] ?? $dateFin,
                            $horaire['heure_debut'] ?? $event['heure_debut'] ?? null,
                            $horaire['heure_fin'] ?? $event['heure_fin'] ?? null,
                            $event['nom_lieu'] ?? null,
                            $event['ville'] ?? null,
                            $event['contact_nom'] ?? null,
                            $event['contact_telephone'] ?? null,
                            $event['contact_email'] ?? null,
                            $event['site_web'] ?? null,
                            $event['prix'] ?? null,
                            isset($event['tags']) && is_array($event['tags']) ? implode(',', $event['tags']) : null
                        ]);
                        $insertedCount++;
                    }
                } else {
                    // Insérer un événement normal
                    $stmt = $conn->prepare("INSERT INTO structured_events 
                        (titre, description, date_debut, date_fin, heure_debut, heure_fin, nom_lieu, ville, contact_nom, contact_telephone, contact_email, site_web, prix, tags) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $event['titre'] ?? null,
                        $event['description'] ?? null,
                        $dateDebut,
                        $dateFin,
                        $event['heure_debut'] ?? null,
                        $event['heure_fin'] ?? null,
                        $event['nom_lieu'] ?? null,
                        $event['ville'] ?? null,
                        $event['contact_nom'] ?? null,
                        $event['contact_telephone'] ?? null,
                        $event['contact_email'] ?? null,
                        $event['site_web'] ?? null,
                        $event['prix'] ?? null,
                        isset($event['tags']) && is_array($event['tags']) ? implode(',', $event['tags']) : null
                    ]);
                    $insertedCount++;
                }
            } catch (Exception $e) {
                // Sauvegarder les erreurs d'insertion
                $failedEvents[] = [
                    'event' => $event,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Mettre à jour le statut dans la table `image_process` si au moins un événement a été inséré
        if ($insertedCount > 0) {
            $updateStmt = $conn->prepare("UPDATE image_process SET process_chatgpt = 'ok' WHERE imageid = ?");
            $updateStmt->execute([$imageId]);
        }

        echo json_encode([
            'success' => true,
            'message' => "$insertedCount événement(s) structuré(s) et enregistré(s) avec succès.",
            'failed' => $failedEvents
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors du traitement.', 'debug' => $e->getMessage()]);
    }
}
