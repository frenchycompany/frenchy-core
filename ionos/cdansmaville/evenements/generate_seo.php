<?php
ini_set('display_errors', 1);
header('Content-Type: text/html');

require 'db/connection.php'; // Connexion à la base de données

$apiKey = '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'; // Remplacez par votre clé OpenAI

// Étape 1 : Récupérer les événements pour le mois en cours
$stmt = $conn->query("
    SELECT titre, description, DATE_FORMAT(date_debut, '%d/%m/%Y') AS date, nom_lieu, ville 
    FROM structured_events 
    WHERE MONTH(date_debut) = MONTH(CURRENT_DATE()) AND YEAR(date_debut) = YEAR(CURRENT_DATE())
    ORDER BY date_debut ASC
");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "Aucun événement trouvé pour ce mois.";
    exit;
}

// Créer un dossier pour les images du mois en cours
$monthFolder = 'imagesia/' . date('Y_m');
if (!is_dir($monthFolder)) {
    mkdir($monthFolder, 0755, true);
}

// Étape 2 : Générer 3 images maximum pour l'article complet
$imagePaths = [];
$imagePrompts = [
    "Génère une image illustrant un calendrier d'événements festifs dans une ville.",
    "Crée une image représentant une ambiance de soirée culturelle avec des spectacles et des lumières.",
    "Produis une image de jour, montrant un marché ou une exposition en plein air."
];

foreach ($imagePrompts as $index => $prompt) {
    $dalleResponse = @file_get_contents("https://api.openai.com/v1/images/generations", false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer $apiKey\r\n",
            'content' => json_encode([
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1024x1024'
            ])
        ]
    ]));

    $dalleData = json_decode($dalleResponse, true);
    $imageUrl = $dalleData['data'][0]['url'] ?? null;

    if ($imageUrl) {
        $imageName = "image_{$index}_" . time() . ".png";
        $localPath = "$monthFolder/$imageName";

        $imageData = file_get_contents($imageUrl);
        if ($imageData) {
            file_put_contents($localPath, $imageData);
            $imagePaths[] = $localPath;
        }
    }
}

// Étape 3 : Préparer le texte brut pour l'IA
$eventList = "";
foreach ($events as $event) {
    $eventList .= "Titre : {$event['titre']}\n";
    $eventList .= "Date : {$event['date']}\n";
    $eventList .= "Lieu : {$event['nom_lieu']}, {$event['ville']}\n";
    $eventList .= "Description : {$event['description']}\n\n";
}

$prompt = "Génère un article SEO optimisé pour WordPress à partir des événements suivants. 
Structure le texte en HTML avec les balises suivantes :
- Une balise <h1> pour le titre principal.
- Plusieurs <h2> pour les sections.
- Du texte avec <p>.
- Ajoute les balises <img> pour inclure les images générées aux positions suivantes : 
  - Première image en début d'article.
  - Deuxième image au milieu.
  - Troisième image à la fin.
Inclut des mots-clés naturels pour le SEO et un paragraphe de conclusion.

Voici les événements :
$eventList";

$response = @file_get_contents("https://api.openai.com/v1/chat/completions", false, stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\nAuthorization: Bearer $apiKey\r\n",
        'content' => json_encode([
            'model' => 'gpt-4o',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 3000
        ])
    ]
]));

$result = json_decode($response, true);
$seoContent = $result['choices'][0]['message']['content'] ?? '';

if (empty($seoContent)) {
    echo "Aucun contenu généré par OpenAI.";
    exit;
}

// Étape 4 : Intégrer les images aux positions spécifiées dans le contenu HTML
if (!empty($imagePaths)) {
    $imagesHTML = [
        "<p><img src='{$imagePaths[0]}' alt='Image d'ouverture' style='width:100%; max-width:1024px; margin-bottom:20px;'></p>",
        "<p><img src='{$imagePaths[1]}' alt='Image au milieu' style='width:100%; max-width:1024px; margin-bottom:20px;'></p>",
        "<p><img src='{$imagePaths[2]}' alt='Image de fin' style='width:100%; max-width:1024px; margin-bottom:20px;'></p>"
    ];

    // Ajouter les images dans le contenu à des positions spécifiques
    $parts = explode("</h2>", $seoContent, 3); // Découpage du contenu pour insertion
    $seoContent = $imagesHTML[0] . $parts[0] . "</h2>" . $imagesHTML[1] . $parts[1] . "</h2>" . $imagesHTML[2] . (isset($parts[2]) ? $parts[2] : '');
}

// Étape 5 : Sauvegarder le contenu enrichi avec les images
$fileName = 'contenu_seo_mensuel_' . date('Y_m') . '.html';
file_put_contents($fileName, $seoContent);

echo "Contenu SEO enrichi avec images généré avec succès : <a href='$fileName'>$fileName</a>";
?>
