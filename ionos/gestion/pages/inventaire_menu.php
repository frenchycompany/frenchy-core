<!-- inventaire_menu.php -->
<style>
.menu-inventaire {
    width: 100%;
    background: #1976d2;
    display: flex;
    justify-content: space-around;
    align-items: center;
    position: sticky;
    top: 0;
    left: 0;
    z-index: 99;
    padding: 0;
    margin: 0 0 15px 0;
    box-shadow: 0 2px 6px #e0e0e0;
}
.menu-inventaire a {
    flex: 1;
    padding: 14px 0 11px 0;
    text-align: center;
    color: #fff;
    text-decoration: none;
    font-size: 1.12em;
    font-weight: 500;
    border-right: 1px solid #1565c0;
    transition: background 0.12s;
    background: none;
}
.menu-inventaire a:last-child { border-right: none; }
.menu-inventaire a:hover, .menu-inventaire a.active {
    background: #1565c0;
    color: #ffe082;
}
@media (max-width:600px) {
    .menu-inventaire { font-size: 0.97em; }
    .menu-inventaire a { padding: 11px 0 8px 0; }
}
</style>
<nav class="menu-inventaire">
    <a href="inventaire.php" title="Accueil inventaire">🏠 Accueil</a>
    <a href="inventaire_lancer.php" title="Nouveau">🆕 Lancer</a>
    <a href="liste_sessions.php" title="Sessions en cours">📝 Sessions</a>
    <a href="impression_etiquettes.php" title="Etiquettes">🏷️ Etiquettes</a>
</nav>
