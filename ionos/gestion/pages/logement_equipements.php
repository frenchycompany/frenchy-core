<?php
/**
 * Page de gestion des equipements et informations des logements
 * Permet de decrire chaque logement pour les voyageurs
 */

include '../config.php';
include '../pages/menu.php';

// Utiliser $conn (base locale) — logement_equipements est sur frenchyconciergerie
$pdo = $conn;



$message = '';
$messageType = '';

// Creer la table si elle n'existe pas
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS logement_equipements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            logement_id INT NOT NULL,
            nombre_couchages INT DEFAULT 0,
            nombre_chambres INT DEFAULT 0,
            nombre_salles_bain INT DEFAULT 0,
            superficie_m2 INT DEFAULT 0,
            etage VARCHAR(50) DEFAULT NULL,
            ascenseur TINYINT(1) DEFAULT 0,
            code_wifi VARCHAR(100) DEFAULT NULL,
            nom_wifi VARCHAR(100) DEFAULT NULL,
            code_porte VARCHAR(100) DEFAULT NULL,
            code_boite_cles VARCHAR(100) DEFAULT NULL,
            instructions_arrivee TEXT DEFAULT NULL,
            machine_cafe_type VARCHAR(50) DEFAULT 'aucune',
            machine_cafe_autre VARCHAR(100) DEFAULT NULL,
            bouilloire TINYINT(1) DEFAULT 0,
            grille_pain TINYINT(1) DEFAULT 0,
            micro_ondes TINYINT(1) DEFAULT 0,
            four TINYINT(1) DEFAULT 0,
            plaque_cuisson TINYINT(1) DEFAULT 0,
            plaque_cuisson_type VARCHAR(50) DEFAULT NULL,
            lave_vaisselle TINYINT(1) DEFAULT 0,
            refrigerateur TINYINT(1) DEFAULT 0,
            congelateur TINYINT(1) DEFAULT 0,
            ustensiles_cuisine TINYINT(1) DEFAULT 0,
            machine_laver TINYINT(1) DEFAULT 0,
            seche_linge TINYINT(1) DEFAULT 0,
            fer_repasser TINYINT(1) DEFAULT 0,
            table_repasser TINYINT(1) DEFAULT 0,
            aspirateur TINYINT(1) DEFAULT 0,
            produits_menage TINYINT(1) DEFAULT 0,
            tv TINYINT(1) DEFAULT 0,
            tv_type VARCHAR(100) DEFAULT NULL,
            tv_pouces INT DEFAULT NULL,
            netflix TINYINT(1) DEFAULT 0,
            amazon_prime TINYINT(1) DEFAULT 0,
            disney_plus TINYINT(1) DEFAULT 0,
            chaines_tv TEXT DEFAULT NULL,
            enceinte_bluetooth TINYINT(1) DEFAULT 0,
            console_jeux TINYINT(1) DEFAULT 0,
            console_jeux_type VARCHAR(100) DEFAULT NULL,
            livres TINYINT(1) DEFAULT 0,
            jeux_societe TINYINT(1) DEFAULT 0,
            canape TINYINT(1) DEFAULT 0,
            canape_type VARCHAR(100) DEFAULT NULL,
            canape_convertible TINYINT(1) DEFAULT 0,
            table_manger TINYINT(1) DEFAULT 0,
            table_manger_places INT DEFAULT NULL,
            bureau TINYINT(1) DEFAULT 0,
            type_lits TEXT DEFAULT NULL,
            linge_lit_fourni TINYINT(1) DEFAULT 1,
            serviettes_fournies TINYINT(1) DEFAULT 1,
            oreillers_supplementaires TINYINT(1) DEFAULT 0,
            couvertures_supplementaires TINYINT(1) DEFAULT 0,
            climatisation TINYINT(1) DEFAULT 0,
            chauffage TINYINT(1) DEFAULT 1,
            chauffage_type VARCHAR(50) DEFAULT 'electrique',
            ventilateur TINYINT(1) DEFAULT 0,
            baignoire TINYINT(1) DEFAULT 0,
            douche TINYINT(1) DEFAULT 1,
            seche_cheveux TINYINT(1) DEFAULT 0,
            produits_toilette TINYINT(1) DEFAULT 0,
            balcon TINYINT(1) DEFAULT 0,
            terrasse TINYINT(1) DEFAULT 0,
            jardin TINYINT(1) DEFAULT 0,
            parking TINYINT(1) DEFAULT 0,
            parking_type VARCHAR(50) DEFAULT NULL,
            barbecue TINYINT(1) DEFAULT 0,
            salon_jardin TINYINT(1) DEFAULT 0,
            detecteur_fumee TINYINT(1) DEFAULT 1,
            detecteur_co TINYINT(1) DEFAULT 0,
            extincteur TINYINT(1) DEFAULT 0,
            trousse_secours TINYINT(1) DEFAULT 0,
            coffre_fort TINYINT(1) DEFAULT 0,
            lit_bebe TINYINT(1) DEFAULT 0,
            chaise_haute TINYINT(1) DEFAULT 0,
            barriere_securite TINYINT(1) DEFAULT 0,
            jeux_enfants TINYINT(1) DEFAULT 0,
            animaux_acceptes TINYINT(1) DEFAULT 0,
            animaux_conditions TEXT DEFAULT NULL,
            guide_tv TEXT DEFAULT NULL,
            guide_canape_convertible TEXT DEFAULT NULL,
            guide_plaque_cuisson TEXT DEFAULT NULL,
            guide_four TEXT DEFAULT NULL,
            guide_micro_ondes TEXT DEFAULT NULL,
            guide_chauffage TEXT DEFAULT NULL,
            guide_climatisation TEXT DEFAULT NULL,
            guide_machine_cafe TEXT DEFAULT NULL,
            guide_machine_laver TEXT DEFAULT NULL,
            guide_lave_vaisselle TEXT DEFAULT NULL,
            guide_seche_linge TEXT DEFAULT NULL,
            fumer_autorise TINYINT(1) DEFAULT 0,
            fetes_autorisees TINYINT(1) DEFAULT 0,
            heure_checkin VARCHAR(50) DEFAULT '15:00',
            heure_checkout VARCHAR(50) DEFAULT '11:00',
            instructions_depart TEXT DEFAULT NULL,
            infos_quartier TEXT DEFAULT NULL,
            numeros_urgence TEXT DEFAULT NULL,
            notes_supplementaires TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_logement (logement_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Ajouter les colonnes guide_* si elles n'existent pas encore
    $guideColumns = [
        'guide_tv', 'guide_canape_convertible', 'guide_plaque_cuisson', 'guide_four',
        'guide_micro_ondes', 'guide_chauffage', 'guide_climatisation', 'guide_machine_cafe',
        'guide_machine_laver', 'guide_lave_vaisselle', 'guide_seche_linge'
    ];
    foreach ($guideColumns as $col) {
        try { $pdo->exec("ALTER TABLE logement_equipements ADD COLUMN $col TEXT DEFAULT NULL"); } catch (PDOException $e) {}
    }

    // Inserer les logements manquants
    $pdo->exec("
        INSERT IGNORE INTO logement_equipements (logement_id)
        SELECT id FROM liste_logements
    ");
} catch (PDOException $e) {
    // Table existe deja ou autre erreur
}

// Creer la table des recommandations (partenaires, restaurants, activites)
try {
    // Table des villes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS villes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Table des recommandations liees aux villes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ville_recommandations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ville_id INT NOT NULL,
            categorie ENUM('partenaire', 'restaurant', 'activite') NOT NULL,
            nom VARCHAR(200) NOT NULL,
            description TEXT,
            adresse VARCHAR(255),
            telephone VARCHAR(50),
            site_web VARCHAR(255),
            prix_indicatif VARCHAR(100),
            note_interne TEXT,
            ordre INT DEFAULT 0,
            actif TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ville_categorie (ville_id, categorie),
            FOREIGN KEY (ville_id) REFERENCES villes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Ajouter colonne ville_id a liste_logements si elle n'existe pas
    $pdo->exec("ALTER TABLE liste_logements ADD COLUMN ville_id INT NULL");
} catch (PDOException $e) {
    // Tables/colonnes existent deja
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'save_equipements':
            $logementId = intval($_POST['logement_id'] ?? 0);

            if ($logementId > 0) {
                // Liste des champs checkbox (boolean)
                $checkboxFields = [
                    'ascenseur', 'bouilloire', 'grille_pain', 'micro_ondes', 'four', 'plaque_cuisson',
                    'lave_vaisselle', 'refrigerateur', 'congelateur', 'ustensiles_cuisine',
                    'machine_laver', 'seche_linge', 'fer_repasser', 'table_repasser', 'aspirateur', 'produits_menage',
                    'tv', 'netflix', 'amazon_prime', 'disney_plus', 'enceinte_bluetooth', 'console_jeux',
                    'livres', 'jeux_societe', 'canape', 'canape_convertible', 'table_manger', 'bureau',
                    'linge_lit_fourni', 'serviettes_fournies', 'oreillers_supplementaires', 'couvertures_supplementaires',
                    'climatisation', 'chauffage', 'ventilateur', 'baignoire', 'douche', 'seche_cheveux', 'produits_toilette',
                    'balcon', 'terrasse', 'jardin', 'parking', 'barbecue', 'salon_jardin',
                    'detecteur_fumee', 'detecteur_co', 'extincteur', 'trousse_secours', 'coffre_fort',
                    'lit_bebe', 'chaise_haute', 'barriere_securite', 'jeux_enfants',
                    'animaux_acceptes', 'fumer_autorise', 'fetes_autorisees'
                ];

                // Liste des champs texte/nombre
                $textFields = [
                    'nombre_couchages', 'nombre_chambres', 'nombre_salles_bain', 'superficie_m2', 'etage',
                    'code_wifi', 'nom_wifi', 'code_porte', 'code_boite_cles', 'instructions_arrivee',
                    'machine_cafe_type', 'machine_cafe_autre', 'plaque_cuisson_type',
                    'tv_type', 'tv_pouces', 'chaines_tv', 'console_jeux_type',
                    'canape_type', 'table_manger_places', 'type_lits', 'chauffage_type',
                    'parking_type', 'animaux_conditions',
                    'heure_checkin', 'heure_checkout', 'instructions_depart',
                    'infos_quartier', 'numeros_urgence', 'notes_supplementaires',
                    'guide_tv', 'guide_canape_convertible', 'guide_plaque_cuisson', 'guide_four',
                    'guide_micro_ondes', 'guide_chauffage', 'guide_climatisation', 'guide_machine_cafe',
                    'guide_machine_laver', 'guide_lave_vaisselle', 'guide_seche_linge'
                ];

                $data = [];
                $data['logement_id'] = $logementId;

                // Traiter les checkboxes
                foreach ($checkboxFields as $field) {
                    $data[$field] = isset($_POST[$field]) ? 1 : 0;
                }

                // Traiter les champs texte
                foreach ($textFields as $field) {
                    $data[$field] = $_POST[$field] ?? null;
                    if ($data[$field] === '') $data[$field] = null;
                }

                // Construire la requete
                $fields = array_keys($data);
                $placeholders = array_map(fn($f) => ":$f", $fields);
                $updates = array_map(fn($f) => "$f = VALUES($f)", $fields);

                $sql = "INSERT INTO logement_equipements (" . implode(', ', $fields) . ")
                        VALUES (" . implode(', ', $placeholders) . ")
                        ON DUPLICATE KEY UPDATE " . implode(', ', $updates);

                try {
                    $stmt = $pdo->prepare($sql);
                    foreach ($data as $key => $value) {
                        $stmt->bindValue(":$key", $value);
                    }
                    $stmt->execute();
                    $message = "Equipements sauvegardes avec succes!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Erreur: " . $e->getMessage();
                    $messageType = "danger";
                }
            }
            break;

        case 'save_ville':
            $logementId = intval($_POST['logement_id'] ?? 0);
            $villeId = intval($_POST['ville_id'] ?? 0);

            if ($logementId > 0) {
                try {
                    $stmt = $pdo->prepare("UPDATE liste_logements SET ville_id = :ville_id WHERE id = :id");
                    $stmt->execute([
                        ':ville_id' => $villeId > 0 ? $villeId : null,
                        ':id' => $logementId
                    ]);
                    $message = "Ville associee avec succes!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Erreur: " . $e->getMessage();
                    $messageType = "danger";
                }
            }
            break;
    }
}

// Recuperer la liste des logements actifs avec leurs equipements
$stmt = $pdo->query("
    SELECT l.id, l.nom_du_logement, le.*
    FROM liste_logements l
    LEFT JOIN logement_equipements le ON l.id = le.logement_id
    WHERE l.actif = 1
    ORDER BY l.nom_du_logement
");
$logements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recuperer un logement specifique si demande
$selectedLogement = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("
        SELECT l.id, l.nom_du_logement, l.ville_id, le.*
        FROM liste_logements l
        LEFT JOIN logement_equipements le ON l.id = le.logement_id
        WHERE l.id = ?
    ");
    $stmt->execute([intval($_GET['id'])]);
    $selectedLogement = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Recuperer la liste des villes
$villes = [];
try {
    $stmt = $pdo->query("SELECT * FROM villes ORDER BY nom");
    $villes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table n'existe peut-etre pas encore
}

// Recuperer la ville du logement selectionne et ses recommandations
$villeLogement = null;
$recommandations = ['partenaire' => [], 'restaurant' => [], 'activite' => []];
if ($selectedLogement && !empty($selectedLogement['ville_id'])) {
    // Recuperer les infos de la ville
    try {
        $stmt = $pdo->prepare("SELECT * FROM villes WHERE id = ?");
        $stmt->execute([$selectedLogement['ville_id']]);
        $villeLogement = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    // Recuperer les recommandations de la ville
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM ville_recommandations
            WHERE ville_id = ? AND actif = 1
            ORDER BY categorie, ordre, nom
        ");
        $stmt->execute([$selectedLogement['ville_id']]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $recommandations[$row['categorie']][] = $row;
        }
    } catch (PDOException $e) {
        // Table n'existe peut-etre pas encore
    }
}

// header loaded via menu.php
?>

<!-- Header de page -->
<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="display-4">
            <i class="fas fa-couch text-primary"></i> Equipements & Informations
        </h1>
        <p class="lead text-muted">Decrivez chaque logement pour informer vos voyageurs</p>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="close" data-bs-dismiss="alert">&times;</button>
</div>
<?php endif; ?>

<div class="row">
        <!-- Liste des logements -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-home"></i> Logements</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" style="max-height: 70vh; overflow-y: auto;">
                        <?php foreach ($logements as $log): ?>
                        <?php
                            $hasData = !empty($log['code_wifi']) || $log['nombre_couchages'] > 0;
                            $isSelected = $selectedLogement && $selectedLogement['id'] == $log['id'];
                        ?>
                        <a href="?id=<?= $log['id'] ?>"
                           class="list-group-item list-group-item-action <?= $isSelected ? 'active' : '' ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($log['nom_du_logement']) ?></strong>
                                    <?php if ($log['nombre_couchages'] > 0): ?>
                                    <br><small class="<?= $isSelected ? '' : 'text-muted' ?>">
                                        <i class="fas fa-bed"></i> <?= $log['nombre_couchages'] ?> couchages
                                        <?php if ($log['code_wifi']): ?>
                                        | <i class="fas fa-wifi"></i> WiFi
                                        <?php endif; ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                                <?php if ($hasData): ?>
                                <span class="badge badge-<?= $isSelected ? 'light' : 'success' ?>">
                                    <i class="fas fa-check"></i>
                                </span>
                                <?php else: ?>
                                <span class="badge badge-<?= $isSelected ? 'light' : 'warning' ?>">
                                    <i class="fas fa-exclamation"></i>
                                </span>
                                <?php endif; ?>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulaire d'edition -->
        <div class="col-md-8">
            <?php if ($selectedLogement): ?>
            <form method="POST">
                <input type="hidden" name="action" value="save_equipements">
                <input type="hidden" name="logement_id" value="<?= $selectedLogement['id'] ?>">

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-edit"></i> <?= htmlspecialchars($selectedLogement['nom_du_logement']) ?></h5>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Sauvegarder
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Navigation par onglets -->
                        <ul class="nav nav-tabs" id="equipTabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#tabGeneral">
                                    <i class="fas fa-info-circle"></i> General
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tabAcces">
                                    <i class="fas fa-key"></i> Acces
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tabCuisine">
                                    <i class="fas fa-utensils"></i> Cuisine
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tabMenager">
                                    <i class="fas fa-broom"></i> Menager
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tabSalon">
                                    <i class="fas fa-tv"></i> Salon
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tabChambres">
                                    <i class="fas fa-bed"></i> Chambres
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tabSDB">
                                    <i class="fas fa-bath"></i> SDB
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tabExterieur">
                                    <i class="fas fa-tree"></i> Exterieur
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tabRegles">
                                    <i class="fas fa-clipboard-list"></i> Regles
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tabVille">
                                    <i class="fas fa-city"></i> Ville
                                    <?php
                                    $totalRecos = count($recommandations['partenaire']) + count($recommandations['restaurant']) + count($recommandations['activite']);
                                    if ($totalRecos > 0): ?>
                                        <span class="badge text-bg-info"><?= $totalRecos ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content mt-3">
                            <!-- Onglet General -->
                            <div class="tab-pane fade show active" id="tabGeneral">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label><i class="fas fa-bed"></i> Couchages</label>
                                            <input type="number" name="nombre_couchages" class="form-control"
                                                   value="<?= $selectedLogement['nombre_couchages'] ?? 0 ?>" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label><i class="fas fa-door-open"></i> Chambres</label>
                                            <input type="number" name="nombre_chambres" class="form-control"
                                                   value="<?= $selectedLogement['nombre_chambres'] ?? 0 ?>" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label><i class="fas fa-bath"></i> Salles de bain</label>
                                            <input type="number" name="nombre_salles_bain" class="form-control"
                                                   value="<?= $selectedLogement['nombre_salles_bain'] ?? 0 ?>" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label><i class="fas fa-ruler-combined"></i> Superficie (m2)</label>
                                            <input type="number" name="superficie_m2" class="form-control"
                                                   value="<?= $selectedLogement['superficie_m2'] ?? '' ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label><i class="fas fa-building"></i> Etage</label>
                                            <input type="text" name="etage" class="form-control"
                                                   value="<?= htmlspecialchars($selectedLogement['etage'] ?? '') ?>"
                                                   placeholder="Ex: RDC, 1er, 2eme...">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group pt-4">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" name="ascenseur" id="ascenseur"
                                                       <?= ($selectedLogement['ascenseur'] ?? 0) ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="ascenseur">
                                                    <i class="fas fa-elevator"></i> Ascenseur disponible
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <h6 class="mt-4"><i class="fas fa-thermometer-half"></i> Confort thermique</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="chauffage" id="chauffage"
                                                   <?= ($selectedLogement['chauffage'] ?? 1) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="chauffage">Chauffage</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <select name="chauffage_type" class="form-control form-control-sm">
                                                <option value="electrique" <?= ($selectedLogement['chauffage_type'] ?? '') == 'electrique' ? 'selected' : '' ?>>Electrique</option>
                                                <option value="gaz" <?= ($selectedLogement['chauffage_type'] ?? '') == 'gaz' ? 'selected' : '' ?>>Gaz</option>
                                                <option value="central" <?= ($selectedLogement['chauffage_type'] ?? '') == 'central' ? 'selected' : '' ?>>Central</option>
                                                <option value="poele" <?= ($selectedLogement['chauffage_type'] ?? '') == 'poele' ? 'selected' : '' ?>>Poele</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="climatisation" id="climatisation"
                                                   <?= ($selectedLogement['climatisation'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="climatisation">Climatisation</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="ventilateur" id="ventilateur"
                                                   <?= ($selectedLogement['ventilateur'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="ventilateur">Ventilateur</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group mt-3">
                                    <label><i class="fas fa-book"></i> Guide d'utilisation — Chauffage</label>
                                    <textarea name="guide_chauffage" class="form-control form-control-sm" rows="3"
                                              placeholder="Ex: Le thermostat est dans le couloir. Reglez la temperature souhaitee..."><?= htmlspecialchars($selectedLogement['guide_chauffage'] ?? '') ?></textarea>
                                    <small class="text-muted">1 etape par ligne. Comment regler le chauffage.</small>
                                </div>

                                <div class="form-group">
                                    <label><i class="fas fa-book"></i> Guide d'utilisation — Climatisation</label>
                                    <textarea name="guide_climatisation" class="form-control form-control-sm" rows="3"
                                              placeholder="Ex: La telecommande est sur la table, appuyez sur ON, reglez la temperature..."><?= htmlspecialchars($selectedLogement['guide_climatisation'] ?? '') ?></textarea>
                                    <small class="text-muted">1 etape par ligne. Comment utiliser la climatisation.</small>
                                </div>
                            </div>

                            <!-- Onglet Acces -->
                            <div class="tab-pane fade" id="tabAcces">
                                <h6><i class="fas fa-wifi"></i> WiFi</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Nom du reseau (SSID)</label>
                                            <input type="text" name="nom_wifi" class="form-control"
                                                   value="<?= htmlspecialchars($selectedLogement['nom_wifi'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Mot de passe WiFi</label>
                                            <input type="text" name="code_wifi" class="form-control"
                                                   value="<?= htmlspecialchars($selectedLogement['code_wifi'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <h6 class="mt-4"><i class="fas fa-door-open"></i> Acces au logement</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Code de porte / Digicode</label>
                                            <input type="text" name="code_porte" class="form-control"
                                                   value="<?= htmlspecialchars($selectedLogement['code_porte'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Code boite a cles</label>
                                            <input type="text" name="code_boite_cles" class="form-control"
                                                   value="<?= htmlspecialchars($selectedLogement['code_boite_cles'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Instructions d'arrivee</label>
                                    <textarea name="instructions_arrivee" class="form-control" rows="4"
                                              placeholder="Comment acceder au logement, ou trouver les cles..."><?= htmlspecialchars($selectedLogement['instructions_arrivee'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- Onglet Cuisine -->
                            <div class="tab-pane fade" id="tabCuisine">
                                <h6><i class="fas fa-coffee"></i> Machine a cafe</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <select name="machine_cafe_type" class="form-control">
                                                <option value="aucune" <?= ($selectedLogement['machine_cafe_type'] ?? '') == 'aucune' ? 'selected' : '' ?>>Aucune</option>
                                                <option value="nespresso" <?= ($selectedLogement['machine_cafe_type'] ?? '') == 'nespresso' ? 'selected' : '' ?>>Nespresso</option>
                                                <option value="dolce_gusto" <?= ($selectedLogement['machine_cafe_type'] ?? '') == 'dolce_gusto' ? 'selected' : '' ?>>Dolce Gusto</option>
                                                <option value="filtre" <?= ($selectedLogement['machine_cafe_type'] ?? '') == 'filtre' ? 'selected' : '' ?>>Filtre classique</option>
                                                <option value="italienne" <?= ($selectedLogement['machine_cafe_type'] ?? '') == 'italienne' ? 'selected' : '' ?>>Italienne (Moka)</option>
                                                <option value="autre" <?= ($selectedLogement['machine_cafe_type'] ?? '') == 'autre' ? 'selected' : '' ?>>Autre</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <input type="text" name="machine_cafe_autre" class="form-control"
                                                   placeholder="Precision si autre..."
                                                   value="<?= htmlspecialchars($selectedLogement['machine_cafe_autre'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <h6 class="mt-3"><i class="fas fa-blender"></i> Petit electromenager</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="bouilloire" id="bouilloire"
                                                   <?= ($selectedLogement['bouilloire'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="bouilloire">Bouilloire</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="grille_pain" id="grille_pain"
                                                   <?= ($selectedLogement['grille_pain'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="grille_pain">Grille-pain</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="micro_ondes" id="micro_ondes"
                                                   <?= ($selectedLogement['micro_ondes'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="micro_ondes">Micro-ondes</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="four" id="four"
                                                   <?= ($selectedLogement['four'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="four">Four</label>
                                        </div>
                                    </div>
                                </div>

                                <h6 class="mt-3"><i class="fas fa-fire"></i> Cuisson</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="plaque_cuisson" id="plaque_cuisson"
                                                   <?= ($selectedLogement['plaque_cuisson'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="plaque_cuisson">Plaque de cuisson</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="plaque_cuisson_type" class="form-control form-control-sm">
                                            <option value="">Type...</option>
                                            <option value="gaz" <?= ($selectedLogement['plaque_cuisson_type'] ?? '') == 'gaz' ? 'selected' : '' ?>>Gaz</option>
                                            <option value="electrique" <?= ($selectedLogement['plaque_cuisson_type'] ?? '') == 'electrique' ? 'selected' : '' ?>>Electrique</option>
                                            <option value="induction" <?= ($selectedLogement['plaque_cuisson_type'] ?? '') == 'induction' ? 'selected' : '' ?>>Induction</option>
                                            <option value="vitroceramique" <?= ($selectedLogement['plaque_cuisson_type'] ?? '') == 'vitroceramique' ? 'selected' : '' ?>>Vitroceramique</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group mt-3">
                                    <label><i class="fas fa-book"></i> Guide d'utilisation — Four</label>
                                    <textarea name="guide_four" class="form-control form-control-sm" rows="3"
                                              placeholder="Ex: Tournez le selecteur de mode... Reglez la temperature..."><?= htmlspecialchars($selectedLogement['guide_four'] ?? '') ?></textarea>
                                    <small class="text-muted">1 etape par ligne. Visible par les voyageurs sur le site.</small>
                                </div>

                                <div class="form-group">
                                    <label><i class="fas fa-book"></i> Guide d'utilisation — Plaques de cuisson</label>
                                    <textarea name="guide_plaque_cuisson" class="form-control form-control-sm" rows="3"
                                              placeholder="Ex: Appuyez sur ON/OFF, selectionnez la zone, reglez la puissance..."><?= htmlspecialchars($selectedLogement['guide_plaque_cuisson'] ?? '') ?></textarea>
                                    <small class="text-muted">1 etape par ligne.</small>
                                </div>

                                <div class="form-group">
                                    <label><i class="fas fa-book"></i> Guide d'utilisation — Micro-ondes</label>
                                    <textarea name="guide_micro_ondes" class="form-control form-control-sm" rows="2"
                                              placeholder="Ex: Placez votre plat, reglez la puissance et le temps..."><?= htmlspecialchars($selectedLogement['guide_micro_ondes'] ?? '') ?></textarea>
                                    <small class="text-muted">1 etape par ligne.</small>
                                </div>

                                <div class="form-group">
                                    <label><i class="fas fa-book"></i> Guide d'utilisation — Machine a cafe</label>
                                    <textarea name="guide_machine_cafe" class="form-control form-control-sm" rows="3"
                                              placeholder="Ex: Remplissez le reservoir, inserez une capsule, appuyez sur le bouton..."><?= htmlspecialchars($selectedLogement['guide_machine_cafe'] ?? '') ?></textarea>
                                    <small class="text-muted">1 etape par ligne.</small>
                                </div>

                                <div class="form-group">
                                    <label><i class="fas fa-book"></i> Guide d'utilisation — Lave-vaisselle</label>
                                    <textarea name="guide_lave_vaisselle" class="form-control form-control-sm" rows="3"
                                              placeholder="Ex: Chargez la vaisselle, ajoutez une pastille, selectionnez Eco..."><?= htmlspecialchars($selectedLogement['guide_lave_vaisselle'] ?? '') ?></textarea>
                                    <small class="text-muted">1 etape par ligne.</small>
                                </div>

                                <h6 class="mt-3"><i class="fas fa-snowflake"></i> Gros electromenager</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="refrigerateur" id="refrigerateur"
                                                   <?= ($selectedLogement['refrigerateur'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="refrigerateur">Refrigerateur</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="congelateur" id="congelateur"
                                                   <?= ($selectedLogement['congelateur'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="congelateur">Congelateur</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="lave_vaisselle" id="lave_vaisselle"
                                                   <?= ($selectedLogement['lave_vaisselle'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="lave_vaisselle">Lave-vaisselle</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="ustensiles_cuisine" id="ustensiles_cuisine"
                                                   <?= ($selectedLogement['ustensiles_cuisine'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="ustensiles_cuisine">Ustensiles</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Onglet Menager -->
                            <div class="tab-pane fade" id="tabMenager">
                                <h6><i class="fas fa-tshirt"></i> Linge</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="machine_laver" id="machine_laver"
                                                   <?= ($selectedLogement['machine_laver'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="machine_laver">Machine a laver</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="seche_linge" id="seche_linge"
                                                   <?= ($selectedLogement['seche_linge'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="seche_linge">Seche-linge</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="fer_repasser" id="fer_repasser"
                                                   <?= ($selectedLogement['fer_repasser'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="fer_repasser">Fer a repasser</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="table_repasser" id="table_repasser"
                                                   <?= ($selectedLogement['table_repasser'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="table_repasser">Table a repasser</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group mt-3">
                                    <label><i class="fas fa-book"></i> Guide d'utilisation — Machine a laver</label>
                                    <textarea name="guide_machine_laver" class="form-control form-control-sm" rows="3"
                                              placeholder="Ex: Chargez le linge, ajoutez la lessive dans le bac, selectionnez programme coton 40°..."><?= htmlspecialchars($selectedLogement['guide_machine_laver'] ?? '') ?></textarea>
                                    <small class="text-muted">1 etape par ligne.</small>
                                </div>

                                <div class="form-group">
                                    <label><i class="fas fa-book"></i> Guide d'utilisation — Seche-linge</label>
                                    <textarea name="guide_seche_linge" class="form-control form-control-sm" rows="2"
                                              placeholder="Ex: Videz le filtre avant chaque utilisation, selectionnez programme coton..."><?= htmlspecialchars($selectedLogement['guide_seche_linge'] ?? '') ?></textarea>
                                    <small class="text-muted">1 etape par ligne.</small>
                                </div>

                                <h6 class="mt-3"><i class="fas fa-broom"></i> Nettoyage</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="aspirateur" id="aspirateur"
                                                   <?= ($selectedLogement['aspirateur'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="aspirateur">Aspirateur</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="produits_menage" id="produits_menage"
                                                   <?= ($selectedLogement['produits_menage'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="produits_menage">Produits menagers</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Onglet Salon -->
                            <div class="tab-pane fade" id="tabSalon">
                                <h6><i class="fas fa-tv"></i> Television</h6>
                                <div class="row">
                                    <div class="col-md-2">
                                        <div class="custom-control custom-checkbox pt-2">
                                            <input type="checkbox" class="custom-control-input" name="tv" id="tv"
                                                   <?= ($selectedLogement['tv'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="tv">TV</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" name="tv_type" class="form-control form-control-sm"
                                               placeholder="Type/Marque (Samsung, LG...)"
                                               value="<?= htmlspecialchars($selectedLogement['tv_type'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="tv_pouces" class="form-control"
                                                   placeholder="Taille"
                                                   value="<?= $selectedLogement['tv_pouces'] ?? '' ?>">
                                            <div class="input-group-append"><span class="input-group-text">"</span></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group mt-2">
                                    <label><i class="fas fa-book"></i> Guide d'utilisation — TV</label>
                                    <textarea name="guide_tv" class="form-control form-control-sm" rows="3"
                                              placeholder="Ex: Prenez la telecommande, appuyez sur ON, selectionnez l'entree HDMI1..."><?= htmlspecialchars($selectedLogement['guide_tv'] ?? '') ?></textarea>
                                    <small class="text-muted">1 etape par ligne. Comment allumer, changer de source, etc.</small>
                                </div>

                                <h6 class="mt-3"><i class="fas fa-play-circle"></i> Streaming</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="netflix" id="netflix"
                                                   <?= ($selectedLogement['netflix'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="netflix">Netflix</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="amazon_prime" id="amazon_prime"
                                                   <?= ($selectedLogement['amazon_prime'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="amazon_prime">Prime Video</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="disney_plus" id="disney_plus"
                                                   <?= ($selectedLogement['disney_plus'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="disney_plus">Disney+</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group mt-2">
                                    <label>Chaines TV disponibles</label>
                                    <input type="text" name="chaines_tv" class="form-control"
                                           placeholder="Ex: TNT, Canal+, BeIN Sports..."
                                           value="<?= htmlspecialchars($selectedLogement['chaines_tv'] ?? '') ?>">
                                </div>

                                <h6 class="mt-3"><i class="fas fa-gamepad"></i> Divertissement</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="enceinte_bluetooth" id="enceinte_bluetooth"
                                                   <?= ($selectedLogement['enceinte_bluetooth'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="enceinte_bluetooth">Enceinte BT</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="console_jeux" id="console_jeux"
                                                   <?= ($selectedLogement['console_jeux'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="console_jeux">Console</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" name="console_jeux_type" class="form-control form-control-sm"
                                               placeholder="PS5, Xbox..."
                                               value="<?= htmlspecialchars($selectedLogement['console_jeux_type'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="livres" id="livres"
                                                   <?= ($selectedLogement['livres'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="livres">Livres</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="jeux_societe" id="jeux_societe"
                                                   <?= ($selectedLogement['jeux_societe'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="jeux_societe">Jeux de societe</label>
                                        </div>
                                    </div>
                                </div>

                                <h6 class="mt-3"><i class="fas fa-couch"></i> Mobilier</h6>
                                <div class="row">
                                    <div class="col-md-2">
                                        <div class="custom-control custom-checkbox pt-2">
                                            <input type="checkbox" class="custom-control-input" name="canape" id="canape"
                                                   <?= ($selectedLogement['canape'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="canape">Canape</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" name="canape_type" class="form-control form-control-sm"
                                               placeholder="Type (angle, 2 places...)"
                                               value="<?= htmlspecialchars($selectedLogement['canape_type'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox pt-2">
                                            <input type="checkbox" class="custom-control-input" name="canape_convertible" id="canape_convertible"
                                                   <?= ($selectedLogement['canape_convertible'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="canape_convertible">Convertible</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group mt-2">
                                    <label><i class="fas fa-book"></i> Guide — Canape convertible</label>
                                    <textarea name="guide_canape_convertible" class="form-control form-control-sm" rows="2"
                                              placeholder="Ex: Retirez les coussins, tirez la poignee sous l'assise, depliez le matelas..."><?= htmlspecialchars($selectedLogement['guide_canape_convertible'] ?? '') ?></textarea>
                                    <small class="text-muted">1 etape par ligne. Comment le deplier/replier.</small>
                                </div>

                                <div class="row mt-2">
                                    <div class="col-md-2">
                                        <div class="custom-control custom-checkbox pt-2">
                                            <input type="checkbox" class="custom-control-input" name="table_manger" id="table_manger"
                                                   <?= ($selectedLogement['table_manger'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="table_manger">Table</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="table_manger_places" class="form-control"
                                                   placeholder="Places"
                                                   value="<?= $selectedLogement['table_manger_places'] ?? '' ?>">
                                            <div class="input-group-append"><span class="input-group-text">pers.</span></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox pt-2">
                                            <input type="checkbox" class="custom-control-input" name="bureau" id="bureau"
                                                   <?= ($selectedLogement['bureau'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="bureau">Bureau / Espace travail</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Onglet Chambres -->
                            <div class="tab-pane fade" id="tabChambres">
                                <h6><i class="fas fa-bed"></i> Literie</h6>
                                <div class="form-group">
                                    <label>Description des lits</label>
                                    <textarea name="type_lits" class="form-control" rows="3"
                                              placeholder="Ex: 1 lit double 160x200, 2 lits simples 90x190..."><?= htmlspecialchars($selectedLogement['type_lits'] ?? '') ?></textarea>
                                </div>

                                <h6 class="mt-3"><i class="fas fa-blanket"></i> Linge fourni</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="linge_lit_fourni" id="linge_lit_fourni"
                                                   <?= ($selectedLogement['linge_lit_fourni'] ?? 1) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="linge_lit_fourni">Draps fournis</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="serviettes_fournies" id="serviettes_fournies"
                                                   <?= ($selectedLogement['serviettes_fournies'] ?? 1) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="serviettes_fournies">Serviettes</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="oreillers_supplementaires" id="oreillers_supplementaires"
                                                   <?= ($selectedLogement['oreillers_supplementaires'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="oreillers_supplementaires">Oreillers sup.</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="couvertures_supplementaires" id="couvertures_supplementaires"
                                                   <?= ($selectedLogement['couvertures_supplementaires'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="couvertures_supplementaires">Couvertures sup.</label>
                                        </div>
                                    </div>
                                </div>

                                <h6 class="mt-4"><i class="fas fa-baby"></i> Enfants / Bebes</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="lit_bebe" id="lit_bebe"
                                                   <?= ($selectedLogement['lit_bebe'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="lit_bebe">Lit bebe</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="chaise_haute" id="chaise_haute"
                                                   <?= ($selectedLogement['chaise_haute'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="chaise_haute">Chaise haute</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="barriere_securite" id="barriere_securite"
                                                   <?= ($selectedLogement['barriere_securite'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="barriere_securite">Barriere securite</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="jeux_enfants" id="jeux_enfants"
                                                   <?= ($selectedLogement['jeux_enfants'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="jeux_enfants">Jeux enfants</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Onglet Salle de bain -->
                            <div class="tab-pane fade" id="tabSDB">
                                <h6><i class="fas fa-shower"></i> Equipements</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="douche" id="douche"
                                                   <?= ($selectedLogement['douche'] ?? 1) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="douche">Douche</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="baignoire" id="baignoire"
                                                   <?= ($selectedLogement['baignoire'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="baignoire">Baignoire</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="seche_cheveux" id="seche_cheveux"
                                                   <?= ($selectedLogement['seche_cheveux'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="seche_cheveux">Seche-cheveux</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="produits_toilette" id="produits_toilette"
                                                   <?= ($selectedLogement['produits_toilette'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="produits_toilette">Produits toilette</label>
                                        </div>
                                    </div>
                                </div>

                                <h6 class="mt-4"><i class="fas fa-shield-alt"></i> Securite</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="detecteur_fumee" id="detecteur_fumee"
                                                   <?= ($selectedLogement['detecteur_fumee'] ?? 1) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="detecteur_fumee">Detecteur fumee</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="detecteur_co" id="detecteur_co"
                                                   <?= ($selectedLogement['detecteur_co'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="detecteur_co">Detecteur CO</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="extincteur" id="extincteur"
                                                   <?= ($selectedLogement['extincteur'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="extincteur">Extincteur</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="trousse_secours" id="trousse_secours"
                                                   <?= ($selectedLogement['trousse_secours'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="trousse_secours">Trousse secours</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="coffre_fort" id="coffre_fort"
                                                   <?= ($selectedLogement['coffre_fort'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="coffre_fort">Coffre-fort</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Onglet Exterieur -->
                            <div class="tab-pane fade" id="tabExterieur">
                                <h6><i class="fas fa-sun"></i> Espaces exterieurs</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="balcon" id="balcon"
                                                   <?= ($selectedLogement['balcon'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="balcon">Balcon</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="terrasse" id="terrasse"
                                                   <?= ($selectedLogement['terrasse'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="terrasse">Terrasse</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="jardin" id="jardin"
                                                   <?= ($selectedLogement['jardin'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="jardin">Jardin</label>
                                        </div>
                                    </div>
                                </div>

                                <h6 class="mt-3"><i class="fas fa-car"></i> Parking</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox pt-2">
                                            <input type="checkbox" class="custom-control-input" name="parking" id="parking"
                                                   <?= ($selectedLogement['parking'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="parking">Parking</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <select name="parking_type" class="form-control form-control-sm">
                                            <option value="">Type...</option>
                                            <option value="gratuit" <?= ($selectedLogement['parking_type'] ?? '') == 'gratuit' ? 'selected' : '' ?>>Gratuit</option>
                                            <option value="payant" <?= ($selectedLogement['parking_type'] ?? '') == 'payant' ? 'selected' : '' ?>>Payant</option>
                                            <option value="prive" <?= ($selectedLogement['parking_type'] ?? '') == 'prive' ? 'selected' : '' ?>>Prive</option>
                                            <option value="rue" <?= ($selectedLogement['parking_type'] ?? '') == 'rue' ? 'selected' : '' ?>>Dans la rue</option>
                                        </select>
                                    </div>
                                </div>

                                <h6 class="mt-3"><i class="fas fa-umbrella-beach"></i> Equipements exterieurs</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="barbecue" id="barbecue"
                                                   <?= ($selectedLogement['barbecue'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="barbecue">Barbecue</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="salon_jardin" id="salon_jardin"
                                                   <?= ($selectedLogement['salon_jardin'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="salon_jardin">Salon de jardin</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Onglet Regles -->
                            <div class="tab-pane fade" id="tabRegles">
                                <h6><i class="fas fa-clock"></i> Horaires</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Heure de check-in</label>
                                            <input type="time" name="heure_checkin" class="form-control"
                                                   value="<?= $selectedLogement['heure_checkin'] ?? '15:00' ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Heure de check-out</label>
                                            <input type="time" name="heure_checkout" class="form-control"
                                                   value="<?= $selectedLogement['heure_checkout'] ?? '11:00' ?>">
                                        </div>
                                    </div>
                                </div>

                                <h6 class="mt-3"><i class="fas fa-gavel"></i> Regles de la maison</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="fumer_autorise" id="fumer_autorise"
                                                   <?= ($selectedLogement['fumer_autorise'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="fumer_autorise">Fumer autorise</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="fetes_autorisees" id="fetes_autorisees"
                                                   <?= ($selectedLogement['fetes_autorisees'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="fetes_autorisees">Fetes autorisees</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="animaux_acceptes" id="animaux_acceptes"
                                                   <?= ($selectedLogement['animaux_acceptes'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="animaux_acceptes">Animaux acceptes</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group mt-2">
                                    <label>Conditions pour les animaux</label>
                                    <input type="text" name="animaux_conditions" class="form-control"
                                           placeholder="Ex: Petits chiens uniquement, supplement 10EUR/nuit..."
                                           value="<?= htmlspecialchars($selectedLogement['animaux_conditions'] ?? '') ?>">
                                </div>

                                <h6 class="mt-4"><i class="fas fa-door-open"></i> Instructions de depart</h6>
                                <div class="form-group">
                                    <textarea name="instructions_depart" class="form-control" rows="3"
                                              placeholder="Ex: Merci de laisser les cles sur la table, sortir les poubelles..."><?= htmlspecialchars($selectedLogement['instructions_depart'] ?? '') ?></textarea>
                                </div>

                                <h6 class="mt-4"><i class="fas fa-map-marker-alt"></i> Informations quartier</h6>
                                <div class="form-group">
                                    <textarea name="infos_quartier" class="form-control" rows="3"
                                              placeholder="Commerces a proximite, transports, restaurants..."><?= htmlspecialchars($selectedLogement['infos_quartier'] ?? '') ?></textarea>
                                </div>

                                <h6 class="mt-4"><i class="fas fa-phone"></i> Numeros d'urgence</h6>
                                <div class="form-group">
                                    <textarea name="numeros_urgence" class="form-control" rows="2"
                                              placeholder="Plombier: 06..., Electricien: 06..."><?= htmlspecialchars($selectedLogement['numeros_urgence'] ?? '') ?></textarea>
                                </div>

                                <h6 class="mt-4"><i class="fas fa-sticky-note"></i> Notes supplementaires</h6>
                                <div class="form-group">
                                    <textarea name="notes_supplementaires" class="form-control" rows="3"
                                              placeholder="Autres informations utiles..."><?= htmlspecialchars($selectedLogement['notes_supplementaires'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- Onglet Ville -->
                            <div class="tab-pane fade" id="tabVille">
                                <!-- Selection de la ville -->
                                <div class="card mb-4 border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <i class="fas fa-city"></i> Ville du logement
                                    </div>
                                    <div class="card-body">
                                        <div class="row align-items-end">
                                            <div class="col-md-6">
                                                <div class="form-group mb-0">
                                                    <label>Associer ce logement a une ville</label>
                                                    <select name="ville_id_select" id="ville_id_select" class="form-control">
                                                        <option value="">-- Aucune ville --</option>
                                                        <?php foreach ($villes as $v): ?>
                                                            <option value="<?= $v['id'] ?>" <?= ($selectedLogement['ville_id'] ?? 0) == $v['id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($v['nom']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <button type="button" class="btn btn-primary" onclick="saveVille()">
                                                    <i class="fas fa-save"></i> Enregistrer
                                                </button>
                                            </div>
                                            <div class="col-md-3 text-right">
                                                <a href="villes.php" class="btn btn-outline-secondary">
                                                    <i class="fas fa-cog"></i> Gerer les villes
                                                </a>
                                            </div>
                                        </div>
                                        <?php if (empty($villes)): ?>
                                            <div class="alert alert-warning mt-3 mb-0">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Aucune ville configuree. <a href="villes.php">Creez d'abord une ville</a> pour y ajouter des recommandations.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($villeLogement): ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-map-marker-alt"></i>
                                        Recommandations pour <strong><?= htmlspecialchars($villeLogement['nom']) ?></strong>
                                        <a href="villes.php?id=<?= $villeLogement['id'] ?>" class="btn btn-sm btn-success float-right">
                                            <i class="fas fa-edit"></i> Modifier les recommandations
                                        </a>
                                    </div>

                                    <!-- Navigation par categorie -->
                                    <ul class="nav nav-pills mb-4" id="villeSubTabs">
                                        <li class="nav-item">
                                            <a class="nav-link active" data-bs-toggle="pill" href="#villePartenaires">
                                                <i class="fas fa-handshake"></i> Partenaires
                                                <span class="badge badge-light"><?= count($recommandations['partenaire']) ?></span>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-bs-toggle="pill" href="#villeRestaurants">
                                                <i class="fas fa-utensils"></i> Restaurants
                                                <span class="badge badge-light"><?= count($recommandations['restaurant']) ?></span>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-bs-toggle="pill" href="#villeActivites">
                                                <i class="fas fa-hiking"></i> Activites
                                                <span class="badge badge-light"><?= count($recommandations['activite']) ?></span>
                                            </a>
                                        </li>
                                    </ul>

                                    <div class="tab-content">
                                        <!-- Partenaires -->
                                        <div class="tab-pane fade show active" id="villePartenaires">
                                            <?php if (!empty($recommandations['partenaire'])): ?>
                                                <div class="row">
                                                    <?php foreach ($recommandations['partenaire'] as $reco): ?>
                                                        <div class="col-md-6 mb-3">
                                                            <div class="card">
                                                                <div class="card-body">
                                                                    <h6 class="card-title mb-1">
                                                                        <i class="fas fa-handshake text-primary"></i>
                                                                        <?= htmlspecialchars($reco['nom']) ?>
                                                                    </h6>
                                                                    <?php if ($reco['description']): ?>
                                                                        <p class="card-text small text-muted mb-1"><?= htmlspecialchars($reco['description']) ?></p>
                                                                    <?php endif; ?>
                                                                    <?php if ($reco['adresse']): ?>
                                                                        <small><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($reco['adresse']) ?></small><br>
                                                                    <?php endif; ?>
                                                                    <?php if ($reco['telephone']): ?>
                                                                        <small><i class="fas fa-phone"></i> <?= htmlspecialchars($reco['telephone']) ?></small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-muted"><i class="fas fa-info-circle"></i> Aucun partenaire pour cette ville</p>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Restaurants -->
                                        <div class="tab-pane fade" id="villeRestaurants">
                                            <?php if (!empty($recommandations['restaurant'])): ?>
                                                <div class="row">
                                                    <?php foreach ($recommandations['restaurant'] as $reco): ?>
                                                        <div class="col-md-6 mb-3">
                                                            <div class="card">
                                                                <div class="card-body">
                                                                    <h6 class="card-title mb-1">
                                                                        <i class="fas fa-utensils text-danger"></i>
                                                                        <?= htmlspecialchars($reco['nom']) ?>
                                                                        <?php if ($reco['prix_indicatif']): ?>
                                                                            <span class="badge text-bg-success"><?= htmlspecialchars($reco['prix_indicatif']) ?></span>
                                                                        <?php endif; ?>
                                                                    </h6>
                                                                    <?php if ($reco['description']): ?>
                                                                        <p class="card-text small text-muted mb-1"><?= htmlspecialchars($reco['description']) ?></p>
                                                                    <?php endif; ?>
                                                                    <?php if ($reco['adresse']): ?>
                                                                        <small><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($reco['adresse']) ?></small><br>
                                                                    <?php endif; ?>
                                                                    <?php if ($reco['telephone']): ?>
                                                                        <small><i class="fas fa-phone"></i> <?= htmlspecialchars($reco['telephone']) ?></small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-muted"><i class="fas fa-info-circle"></i> Aucun restaurant pour cette ville</p>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Activites -->
                                        <div class="tab-pane fade" id="villeActivites">
                                            <?php if (!empty($recommandations['activite'])): ?>
                                                <div class="row">
                                                    <?php foreach ($recommandations['activite'] as $reco): ?>
                                                        <div class="col-md-6 mb-3">
                                                            <div class="card">
                                                                <div class="card-body">
                                                                    <h6 class="card-title mb-1">
                                                                        <i class="fas fa-hiking text-success"></i>
                                                                        <?= htmlspecialchars($reco['nom']) ?>
                                                                        <?php if ($reco['prix_indicatif']): ?>
                                                                            <span class="badge text-bg-info"><?= htmlspecialchars($reco['prix_indicatif']) ?></span>
                                                                        <?php endif; ?>
                                                                    </h6>
                                                                    <?php if ($reco['description']): ?>
                                                                        <p class="card-text small text-muted mb-1"><?= htmlspecialchars($reco['description']) ?></p>
                                                                    <?php endif; ?>
                                                                    <?php if ($reco['adresse']): ?>
                                                                        <small><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($reco['adresse']) ?></small><br>
                                                                    <?php endif; ?>
                                                                    <?php if ($reco['site_web']): ?>
                                                                        <small><i class="fas fa-globe"></i> <a href="<?= htmlspecialchars($reco['site_web']) ?>" target="_blank">Site web</a></small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-muted"><i class="fas fa-info-circle"></i> Aucune activite pour cette ville</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5 text-muted">
                                        <i class="fas fa-city fa-4x mb-3"></i>
                                        <h5>Aucune ville associee</h5>
                                        <p>Selectionnez une ville ci-dessus pour afficher les recommandations locales.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Sauvegarder les equipements
                        </button>
                    </div>
                </div>
            </form>
            <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-hand-pointer fa-3x text-muted mb-3"></i>
                    <h5>Selectionnez un logement</h5>
                    <p class="text-muted">Cliquez sur un logement dans la liste pour modifier ses equipements et informations.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

<!-- Formulaire pour sauvegarder la ville -->
<?php if ($selectedLogement): ?>
<form method="POST" id="formSaveVille" style="display:none;">
    <input type="hidden" name="action" value="save_ville">
    <input type="hidden" name="logement_id" value="<?= $selectedLogement['id'] ?>">
    <input type="hidden" name="ville_id" id="ville_id_hidden" value="">
</form>

<script>
function saveVille() {
    const villeId = document.getElementById('ville_id_select').value;
    document.getElementById('ville_id_hidden').value = villeId;
    document.getElementById('formSaveVille').submit();
}
</script>
<?php endif; ?>

<?php // footer inline ?>
