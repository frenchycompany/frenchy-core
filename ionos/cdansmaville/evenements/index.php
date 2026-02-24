<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Extractor</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        h1, h2 {
            font-size: 1.3rem;
        }
        .btn-lg {
            font-size: 1rem;
            padding: 0.7rem;
        }
        #result {
            font-size: 0.9rem;
        }
        iframe {
            width: 100%;
            height: 400px; /* Réduction pour mobile */
            border: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .progress {
            display: none;
            height: 0.5rem;
        }
        .spinner-border {
            display: none;
        }
        @media (max-width: 768px) {
            .container {
                padding: 0 10px;
            }
            h1 {
                font-size: 1.2rem;
            }
            iframe {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-3">
        <!-- Titre -->
        <h1 class="text-center mb-4">📸 Event Extractor</h1>
        
        <!-- Carte principale -->
        <div class="card p-3 shadow-sm">
            <label for="imageInput" class="form-label">📷 Sélectionnez une image :</label>
            <input type="file" id="imageInput" accept="image/*" class="form-control mb-3">
            <button id="uploadBtn" class="btn btn-primary btn-lg w-100">
                <i class="fa-solid fa-upload"></i> Envoyer & Traiter
            </button>

            <!-- Barre de progression -->
            <div class="progress mt-3">
                <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 0%"></div>
            </div>

            <!-- Résultats -->
            <div id="result" class="mt-3 alert alert-info" style="display:none;"></div>
        </div>

        <!-- Section pour afficher les événements -->
        <div class="mt-4">
            <h2 class="text-center">📅 Événements Récents</h2>
            <iframe src="view_events.php" title="Voir les Événements" id="eventsFrame"></iframe>
            <button id="refreshBtn" class="btn btn-secondary w-100 mt-2">
                <i class="fa-solid fa-sync"></i> Rafraîchir les événements
            </button>
        </div>
    </div>

    <!-- Script JS -->
    <script>
        const uploadBtn = document.getElementById('uploadBtn');
        const refreshBtn = document.getElementById('refreshBtn');
        const imageInput = document.getElementById('imageInput');
        const resultDiv = document.getElementById('result');
        const progressBar = document.getElementById('progressBar');
        const eventsFrame = document.getElementById('eventsFrame');

        // Gestion du bouton d'upload
        uploadBtn.addEventListener('click', () => {
            const file = imageInput.files[0];
            if (!file) {
                showResult('Veuillez sélectionner une image.', 'danger');
                return;
            }

            const formData = new FormData();
            formData.append('image', file);

            // Reset
            progressBar.style.width = '0%';
            progressBar.parentElement.style.display = 'block';
            resultDiv.style.display = 'none';

            // Étape 1 : Envoi de l'image
            fetch('process_image.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);

                    // Mettre à jour la barre de progression
                    updateProgress(50);

                    // Appel à GPT
                    return fetch('process_chatgpt.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `imageid=${data.imageId}`
                    });
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message);

                    updateProgress(100);
                    showResult(data.message, 'success');
                })
                .catch(error => {
                    console.error(error);
                    showResult(error.message, 'danger');
                });
        });

        // Rafraîchir les événements
        refreshBtn.addEventListener('click', () => {
            eventsFrame.src = eventsFrame.src;
        });

        // Afficher un message résultat
        function showResult(message, type) {
            resultDiv.textContent = message;
            resultDiv.className = `alert alert-${type}`;
            resultDiv.style.display = 'block';
        }

        // Mettre à jour la barre de progression
        function updateProgress(value) {
            progressBar.style.width = value + '%';
            if (value === 100) {
                progressBar.parentElement.style.display = 'none';
            }
        }
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
