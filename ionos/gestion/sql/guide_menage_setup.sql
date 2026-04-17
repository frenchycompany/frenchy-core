CREATE TABLE IF NOT EXISTS guide_menage_guides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_logement INT NULL,
    nom VARCHAR(255) NOT NULL,
    sous_titre VARCHAR(255) DEFAULT '',
    regles_generales TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS guide_menage_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_guide INT NOT NULL,
    nom VARCHAR(255) NOT NULL,
    icon VARCHAR(50) DEFAULT 'fa-door-open',
    ordre INT DEFAULT 0,
    photo_reference VARCHAR(500) DEFAULT NULL,
    FOREIGN KEY (id_guide) REFERENCES guide_menage_guides(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS guide_menage_taches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_zone INT NOT NULL,
    section ENUM('etat','nettoyage','mise_en_place','equipements') DEFAULT 'nettoyage',
    texte VARCHAR(500) NOT NULL,
    note VARCHAR(500) DEFAULT NULL,
    photo VARCHAR(500) DEFAULT NULL,
    ordre INT DEFAULT 0,
    FOREIGN KEY (id_zone) REFERENCES guide_menage_zones(id) ON DELETE CASCADE
);

-- Seed Vertefeuille
INSERT INTO guide_menage_guides (nom, sous_titre, regles_generales) VALUES (
    'Château de Vertefeuille',
    'Protocole de nettoyage & mise en place',
    'Toujours commencer par les étages supérieurs, finir par le RDC\nAérer chaque pièce dès l''ouverture\nVérifier chaque ampoule et signaler immédiatement si HS\nLits : draps tendus sans pli, couette symétrique, oreillers gonflés\nSalle de bain : pliage serviettes hôtelier, produits alignés\nAucune trace de doigt sur vitres, miroirs et surfaces brillantes\nPoubelles vidées, sacs neufs installés\nTempérature : vérifier thermostat selon saison\nPrendre photo de chaque pièce une fois terminée'
);
SET @gid = LAST_INSERT_ID();

INSERT INTO guide_menage_zones (id_guide, nom, icon, ordre) VALUES
(@gid, 'Entrée', 'fa-door-open', 1),
(@gid, 'Cage d''escalier', 'fa-stairs', 2),
(@gid, 'Salon', 'fa-couch', 3),
(@gid, 'Salle Billard', 'fa-circle', 4),
(@gid, 'Couloir SAM', 'fa-arrows-alt-h', 5),
(@gid, 'Salle à Manger', 'fa-utensils', 6),
(@gid, 'Cuisine', 'fa-kitchen-set', 7),
(@gid, 'Couloir Sous-sol', 'fa-arrow-down', 8),
(@gid, 'Karaoké', 'fa-microphone', 9),
(@gid, 'Sauna', 'fa-hot-tub-person', 10),
(@gid, 'Salle de Sport', 'fa-dumbbell', 11),
(@gid, 'Piscine intérieure', 'fa-water-ladder', 12),
(@gid, 'Terrasse Piscine', 'fa-umbrella-beach', 13),
(@gid, 'Étage 1 – Communs (WC, SDB, Douche)', 'fa-bath', 14),
(@gid, 'Salle Cinéma', 'fa-film', 15),
(@gid, 'Chambre 1', 'fa-bed', 16),
(@gid, 'Chambre 2', 'fa-bed', 17),
(@gid, 'Chambre 3', 'fa-bed', 18),
(@gid, 'Étage 2 – PRIVÉ', 'fa-lock', 19),
(@gid, 'Étage 3', 'fa-layer-group', 20),
(@gid, 'Extérieur', 'fa-tree', 21);
