<?php
// Inclusion de la configuration
include '../config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"> 
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recevez votre Cadeau</title>
    <!-- Styles -->
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <header class="text-center py-3 bg-light">
        <h1>Recevez votre cadeau exclusif</h1>
        <p>Téléchargez votre guide en quelques secondes !</p>
    </header>
    <main class="container my-5">
        <form action="submit.php" method="POST" class="w-50 mx-auto">
            <div class="form-group">
                <label for="name">Votre Nom</label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="email">Votre Email</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg mt-3">Recevoir mon cadeau</button>
        </form>
    </main>
    <footer class="text-center py-3">
        <p>&copy; 2024 FrenchyBnB. Tous droits réservés.</p>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
