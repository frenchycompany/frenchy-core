<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Obtenez votre Guide de Bienvenue - FrenchyBnB</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <header class="text-center p-3 bg-light">
        <img src="logo-frenchybnb.png" alt="FrenchyBnB Logo" width="150">
        <h1>Recevez votre cadeau exclusif !</h1>
        <p>Un guide complet pour rendre votre séjour inoubliable.</p>
    </header>
    <main class="container my-5">
        <section class="text-center">
            <h2>Téléchargez votre Guide de Bienvenue</h2>
            <p class="lead">Remplissez simplement le formulaire ci-dessous pour obtenir votre guide immédiatement.</p>
            <form action="submit.php" method="POST" class="w-50 mx-auto">
                <div class="form-group">
                    <label for="name">Votre Nom</label>
                    <input type="text" id="name" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="email">Votre Email</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg mt-3">Recevoir mon Guide</button>
            </form>
        </section>
    </main>
    <footer class="text-center py-4 bg-light">
        <p>&copy; 2024 FrenchyBnB. Tous droits réservés.</p>
        <a href="/privacy-policy">Politique de confidentialité</a>
    </footer>
</body>
</html>
