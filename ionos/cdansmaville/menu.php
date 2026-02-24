<?php
// Menu principal
?>
<nav class="navbar">
    <div class="container">
        <a href="index.php" class="navbar-logo">
            <img src="images/logo.png" alt="Cdansmaville">
        </a>
        <button class="navbar-toggle" aria-label="Menu">
            <span class="line"></span>
            <span class="line"></span>
            <span class="line"></span>
        </button>
        <ul class="nav-links">
            <li><a href="index.php">Accueil</a></li>
            <li><a href="directory.php">Annuaire</a></li>
            <li><a href="promotions.php">Promotions</a></li>
            <li><a href="news.php">Actualités</a></li>
            <li><a href="signup.php">Inscription</a></li>
            <li><a href="login.php">Connexion</a></li>
        </ul>
    </div>
</nav>

<style>
    /* Global Styling */
    body {
        margin: 0;
        font-family: Arial, sans-serif;
    }

    .container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 20px;
    }

    /* Navbar Styling */
    .navbar {
        background-color: #000000;
        color: #fff;
        position: sticky;
        top: 0;
        z-index: 1000;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .navbar-logo img {
        max-height: 50px;
        vertical-align: middle;
    }

    .nav-links {
        list-style: none;
        display: flex;
        margin: 0;
        padding: 0;
    }

    .nav-links li {
        margin: 0 10px;
    }

    .nav-links a {
        color: #fff;
        text-decoration: none;
        font-size: 1rem;
        font-weight: bold;
        padding: 10px 15px;
        border-radius: 5px;
        transition: background-color 0.3s ease;
    }

    .nav-links a:hover {
        background-color: #0056b3;
    }

    /* Toggle Button Styling */
    .navbar-toggle {
        display: none;
        background: none;
        border: none;
        cursor: pointer;
        flex-direction: column;
        gap: 5px;
    }

    .navbar-toggle .line {
        width: 25px;
        height: 3px;
        background-color: #fff;
        border-radius: 3px;
    }

    /* Responsive Styling */
    @media screen and (max-width: 768px) {
        .nav-links {
            display: none;
            flex-direction: column;
            background-color: #007bff;
            position: absolute;
            top: 100%;
            right: 0;
            width: 100%;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .nav-links.show {
            display: flex;
        }

        .navbar-toggle {
            display: flex;
        }
    }
</style>

<script>
    // Script pour le menu responsive
    document.addEventListener('DOMContentLoaded', () => {
        const toggleButton = document.querySelector('.navbar-toggle');
        const navLinks = document.querySelector('.nav-links');

        toggleButton.addEventListener('click', () => {
            navLinks.classList.toggle('show');
        });
    });
</script>
