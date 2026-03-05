-- ══════════════════════════════════════════════
-- Schéma de base (template)
-- Les noms vf_ sont remplacés par le db_prefix
-- du config/property.php à l'installation.
-- ══════════════════════════════════════════════

-- Paramètres clé/valeur (identité, couleurs, typo, intégrations)
CREATE TABLE IF NOT EXISTS vf_settings (
    setting_key   VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    setting_group VARCHAR(50) NOT NULL DEFAULT 'general',
    label         VARCHAR(200) DEFAULT NULL,
    field_type    VARCHAR(20)  DEFAULT 'text',
    sort_order    INT          DEFAULT 0,
    updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Textes des sections (titre, sous-titre, contenu)
CREATE TABLE IF NOT EXISTS vf_texts (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    section_key   VARCHAR(50)  NOT NULL,
    field_key     VARCHAR(50)  NOT NULL,
    field_value   TEXT,
    label         VARCHAR(200) DEFAULT NULL,
    field_type    VARCHAR(20)  DEFAULT 'text',
    sort_order    INT          DEFAULT 0,
    updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_section_field (section_key, field_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos (hero, galerie, expérience, logo)
CREATE TABLE IF NOT EXISTS vf_photos (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    photo_group   VARCHAR(50)  NOT NULL,
    photo_key     VARCHAR(100) DEFAULT NULL,
    file_path     VARCHAR(500) NOT NULL,
    srcset_json   TEXT         DEFAULT NULL,
    alt_text      VARCHAR(300) DEFAULT '',
    is_wide       TINYINT(1)   DEFAULT 0,
    sort_order    INT          DEFAULT 0,
    updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_group (photo_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guides dynamiques (modes d'emploi QR codes)
CREATE TABLE IF NOT EXISTS vf_guides (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    slug          VARCHAR(50)  NOT NULL UNIQUE,
    title         VARCHAR(200) NOT NULL,
    subtitle      VARCHAR(300) DEFAULT '',
    icon_svg      TEXT         DEFAULT NULL,
    is_active     TINYINT(1)   DEFAULT 1,
    sort_order    INT          DEFAULT 0,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blocs de contenu des guides
CREATE TABLE IF NOT EXISTS vf_guide_blocks (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    guide_slug    VARCHAR(50)  NOT NULL,
    block_type    VARCHAR(20)  NOT NULL DEFAULT 'text',
    block_title   VARCHAR(200) DEFAULT '',
    block_content TEXT,
    sort_order    INT          DEFAULT 0,
    updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_guide (guide_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ══════════════════════════════════════════════
-- Données par défaut
-- ══════════════════════════════════════════════

-- ── Identité du logement ──
INSERT INTO vf_settings (setting_key, setting_value, setting_group, label, field_type, sort_order) VALUES
('site_name',    'Mon Logement',                        'identity', 'Nom du logement',   'text',  1),
('site_tagline', 'Bienvenue chez vous',                  'identity', 'Tagline',            'text',  2),
('site_location','Ville · Région',                       'identity', 'Localisation',       'text',  3),
('address',      'Ville, Région, France',                'identity', 'Adresse complète',   'text',  4)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ── Contact conciergerie (par défaut = Frenchy Conciergerie) ──
INSERT INTO vf_settings (setting_key, setting_value, setting_group, label, field_type, sort_order) VALUES
('phone',        '+33 6 47 55 46 78',                    'contact', 'Téléphone',          'text',  1),
('phone_raw',    '+33647554678',                         'contact', 'Téléphone (format brut)', 'text', 2),
('email',        'contact@frenchyconciergerie.fr',       'contact', 'Email',              'text',  3)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ── Intégrations ──
INSERT INTO vf_settings (setting_key, setting_value, setting_group, label, field_type, sort_order) VALUES
('airbnb_id',    '',   'integrations', 'ID annonce Airbnb',         'text', 1),
('airbnb_url',   '',   'integrations', 'Lien Airbnb complet',       'text', 2),
('ics_url',      '',   'integrations', 'Lien calendrier iCal (.ics)','text', 3),
('matterport_id','',   'integrations', 'ID Matterport',             'text', 4)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ── Couleurs ──
INSERT INTO vf_settings (setting_key, setting_value, setting_group, label, field_type, sort_order) VALUES
('color_green',    '#1D5345', 'colors', 'Vert forêt (principal)',    'color', 1),
('color_green_dk', '#153d33', 'colors', 'Vert forêt (foncé)',       'color', 2),
('color_beige',    '#CFCDB0', 'colors', 'Beige (fond)',              'color', 3),
('color_grey',     '#B2ACA9', 'colors', 'Gris neutre',              'color', 4),
('color_brown',    '#6C5C4F', 'colors', 'Noisette',                 'color', 5),
('color_offwhite', '#E8E4D0', 'colors', 'Blanc cassé (mur)',        'color', 6),
('color_dark',     '#2B2924', 'colors', 'Texte sombre',             'color', 7)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ── Typographie ──
INSERT INTO vf_settings (setting_key, setting_value, setting_group, label, field_type, sort_order) VALUES
('font_display', 'Playfair Display', 'typography', 'Police titres',  'text', 1),
('font_body',    'Inter',            'typography', 'Police corps',   'text', 2)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ── Textes des sections ──
INSERT INTO vf_texts (section_key, field_key, field_value, label, field_type, sort_order) VALUES
-- Hero
('hero', 'kicker',   'Confort · Charme · Détente',                                              'Accroche',       'text',     1),
('hero', 'title',    'Votre séjour<br>commence ici',                                            'Titre',          'text',     2),
('hero', 'desc',     'Un lieu pensé pour se retrouver, se détendre et profiter. Idéal pour des vacances en famille, entre amis ou en amoureux.', 'Description', 'textarea', 3),
('hero', 'cta1',     'Voir les disponibilités',                                                 'Bouton principal','text',    4),
('hero', 'cta2',     'Explorer en 360°',                                                        'Bouton secondaire','text',   5),

-- Band
('band', 'stat1_number', '—',            'Chiffre 1',       'text', 1),
('band', 'stat1_label',  'voyageurs',    'Légende 1',       'text', 2),
('band', 'stat2_number', '—',            'Chiffre 2',       'text', 3),
('band', 'stat2_label',  'chambres',     'Légende 2',       'text', 4),
('band', 'stat3_number', '—',            'Chiffre 3',       'text', 5),
('band', 'stat3_label',  'm²',           'Légende 3',       'text', 6),
('band', 'stat4_number', '—',            'Chiffre 4',       'text', 7),
('band', 'stat4_label',  'étoiles',      'Légende 4',       'text', 8),

-- Histoire
('histoire', 'title',  'Notre histoire',  'Titre',      'text',     1),
('histoire', 'para1',  'Ce lieu a été pensé pour offrir une expérience unique à chaque voyageur. Son ambiance, ses espaces et ses détails créent un cadre idéal pour se ressourcer.',  'Paragraphe 1', 'textarea', 2),
('histoire', 'para2',  'Chaque espace a été aménagé avec soin pour allier confort et authenticité : literie de qualité, équipements modernes et décoration soignée.',  'Paragraphe 2', 'textarea', 3),
('histoire', 'quote',  '« Un lieu où l''on se sent chez soi, avec le charme en plus. »',  'Citation', 'textarea', 4),

-- Expérience
('experience', 'title',    'L''expérience',  'Titre',     'text',     1),
('experience', 'subtitle', 'Un séjour pensé pour les moments qui comptent : repos, célébrations, retrouvailles.', 'Sous-titre', 'textarea', 2),
('experience', 'card1_title', 'Confort',                   'Carte 1 — Titre', 'text', 3),
('experience', 'card1_text',  'Des espaces généreux, une literie de qualité et tout le nécessaire pour un séjour sans compromis.', 'Carte 1 — Texte', 'textarea', 4),
('experience', 'card2_title', 'Charme',                    'Carte 2 — Titre', 'text', 5),
('experience', 'card2_text',  'Une décoration soignée, une ambiance chaleureuse et des détails qui font toute la différence.', 'Carte 2 — Texte', 'textarea', 6),
('experience', 'card3_title', 'Accueil',                   'Carte 3 — Titre', 'text', 7),
('experience', 'card3_text',  'Un accueil attentif et des recommandations locales pour profiter pleinement de votre séjour.', 'Carte 3 — Texte', 'textarea', 8),

-- Galerie
('galerie', 'title',    'Galerie',                                 'Titre',     'text', 1),
('galerie', 'subtitle', 'Découvrez le lieu en images.',             'Sous-titre','text', 2),

-- Visite
('visite', 'title',    'Visite virtuelle 360°',                    'Titre',     'text', 1),
('visite', 'subtitle', 'Parcourez le logement comme si vous y étiez.','Sous-titre','text', 2),

-- Réservation
('reservation', 'title',    'Réserver votre séjour',                               'Titre',     'text', 1),
('reservation', 'subtitle', 'Consultez les disponibilités et réservez directement.','Sous-titre','text', 2),
('reservation', 'cta',      'Demande spéciale ou événement',                       'Bouton CTA','text', 3),

-- Contact
('contact', 'title', 'Contact', 'Titre', 'text', 1),

-- ── Guides / Modes d'emploi (QR codes) ──

-- WiFi
('guide_wifi', 'title',        'Connexion WiFi',                          'Titre',              'text',     1),
('guide_wifi', 'subtitle',     'Accédez à Internet pendant votre séjour', 'Sous-titre',         'text',     2),
('guide_wifi', 'network_name', 'MonWiFi',                                  'Nom du réseau',      'text',     3),
('guide_wifi', 'password',     'motdepasse',                               'Mot de passe WiFi',  'text',     4),
('guide_wifi', 'step1',        'Ouvrez les réglages WiFi de votre téléphone, tablette ou ordinateur.', 'Étape 1', 'textarea', 5),
('guide_wifi', 'step2',        'Recherchez le nom du réseau (ci-dessus) dans la liste des réseaux disponibles.', 'Étape 2', 'textarea', 6),
('guide_wifi', 'step3',        'Saisissez le mot de passe ci-dessus et validez. La connexion s''établit en quelques secondes.', 'Étape 3', 'textarea', 7),

-- Piscine
('guide_piscine', 'title',       'La Piscine',                             'Titre',              'text',     1),
('guide_piscine', 'subtitle',    'Profitez de notre piscine privée',       'Sous-titre',         'text',     2),
('guide_piscine', 'temperature', '28°C — chauffée',                        'Température',        'text',     3),
('guide_piscine', 'avant',       'Prenez une douche avant d''entrer dans la piscine\nLes serviettes de piscine sont à votre disposition sur les transats\nAppliquez votre crème solaire au moins 15 minutes avant la baignade', 'Avant la baignade (1 par ligne)', 'textarea', 4),
('guide_piscine', 'pendant',     'Les enfants doivent être accompagnés d''un adulte en permanence\nIl est interdit de plonger\nPas de nourriture ni de boissons en verre aux abords de la piscine', 'Pendant la baignade (1 par ligne)', 'textarea', 5),
('guide_piscine', 'apres',       'Veuillez replier les transats et ranger les serviettes\nRefermez la couverture de piscine si vous êtes les derniers à l''utiliser', 'Après la baignade (1 par ligne)', 'textarea', 6),
('guide_piscine', 'securite',    'La piscine n''est pas surveillée. La baignade est sous votre entière responsabilité. En cas d''urgence, appelez le 15 (SAMU) ou le 112.', 'Avertissement sécurité', 'textarea', 7),

-- Sauna
('guide_sauna', 'title',         'Le Sauna',                               'Titre',              'text',     1),
('guide_sauna', 'subtitle',      'Un moment de détente absolue',           'Sous-titre',         'text',     2),
('guide_sauna', 'type',          'Sauna traditionnel',                     'Type',               'text',     3),
('guide_sauna', 'temperature',   '80°C — 90°C',                           'Température',        'text',     4),
('guide_sauna', 'capacite',      '4 personnes',                            'Capacité',           'text',     5),
('guide_sauna', 'mise_en_route', 'Appuyez sur le bouton <strong>ON</strong> du panneau de commande\nRéglez la température souhaitée (80°C recommandé)\nPatientez environ <strong>20 à 30 minutes</strong> le temps que le sauna atteigne la température', 'Mise en route (1 par ligne)', 'textarea', 6),
('guide_sauna', 'seance',        'Prenez une douche tiède avant d''entrer\nPlacez votre serviette sur la banquette (ne pas s''asseoir directement sur le bois)\nDurée recommandée : <strong>10 à 15 minutes</strong> par séance\nSortez et prenez une douche fraîche entre chaque séance\nHydratez-vous régulièrement', 'Votre séance (1 par ligne)', 'textarea', 7),
('guide_sauna', 'apres',         'Appuyez sur le bouton <strong>OFF</strong> du panneau de commande\nLaissez la porte entrouverte pour aérer\nEssuyez les banquettes avec votre serviette', 'Après la séance (1 par ligne)', 'textarea', 8),
('guide_sauna', 'precautions',   'Déconseillé aux femmes enceintes, aux enfants de moins de 12 ans et aux personnes souffrant de problèmes cardiaques. Ne consommez pas d''alcool avant ou pendant la séance.', 'Précautions', 'textarea', 9),
('guide_sauna', 'danger',        'Ne versez jamais d''huiles essentielles directement sur les pierres. Cela peut endommager le poêle et créer des vapeurs irritantes.', 'Avertissement danger', 'textarea', 10),

-- Sport
('guide_sport', 'title',       'Salle de Sport',                          'Titre',              'text',     1),
('guide_sport', 'subtitle',    'Restez actif pendant votre séjour',       'Sous-titre',         'text',     2),
('guide_sport', 'equipements', 'Tapis de course\nVélo elliptique\nBanc de musculation\nHaltères (paire de 2 kg à 20 kg)\nTapis de yoga et élastiques de résistance\nBallon de gym', 'Équipements (1 par ligne)', 'textarea', 3),
('guide_sport', 'tapis',       'Branchez le tapis à la prise (interrupteur à l''arrière)\nMontez sur les rails latéraux <strong>avant</strong> de démarrer\nAppuyez sur <strong>START</strong> — la bande démarre lentement\nUtilisez les touches <strong>+</strong> et <strong>-</strong> pour régler vitesse et inclinaison\nAppuyez sur <strong>STOP</strong> pour terminer, attendez l''arrêt complet', 'Tapis de course (1 par ligne)', 'textarea', 4),
('guide_sport', 'velo',        'Montez sur les pédales en vous tenant aux poignées fixes\nCommencez à pédaler — l''écran s''allume automatiquement\nRéglez la résistance avec les touches <strong>+</strong> / <strong>-</strong>', 'Vélo elliptique (1 par ligne)', 'textarea', 5),
('guide_sport', 'apres',       'Essuyez les équipements avec les lingettes mises à disposition\nRangez les haltères sur leur support\nÉteignez le tapis de course (interrupteur arrière)', 'Après votre séance (1 par ligne)', 'textarea', 6),

-- Cinéma
('guide_cinema', 'title',     'Salle Cinéma',                             'Titre',              'text',     1),
('guide_cinema', 'subtitle',  'Votre cinéma privé',                       'Sous-titre',         'text',     2),
('guide_cinema', 'allumer',   'Prenez la télécommande <strong>principale</strong> (marquée "HOME CINEMA")\nAppuyez sur le bouton <strong>ON</strong> — le vidéoprojecteur et le système audio s''allument automatiquement\nPatientez environ <strong>30 secondes</strong> le temps du démarrage\nL''écran descend automatiquement', 'Allumer le système (1 par ligne)', 'textarea', 3),
('guide_cinema', 'contenu',   '<strong>Netflix / Disney+ / Prime Video</strong> — Utilisez la télécommande pour naviguer dans les applications. Les comptes sont déjà connectés.\n<strong>YouTube</strong> — Diffusez depuis votre téléphone via Chromecast.\n<strong>HDMI</strong> — Branchez votre appareil sur le câble HDMI disponible à côté du canapé.', 'Choisir votre contenu (1 par ligne)', 'textarea', 4),
('guide_cinema', 'son_label', 'Touches <strong>VOL +/-</strong> de la télécommande principale', 'Réglage son — Volume', 'textarea', 5),
('guide_cinema', 'son_niveau','Entre 25 et 40',                           'Réglage son — Niveau conseillé', 'text', 6),
('guide_cinema', 'eteindre',  'Appuyez sur le bouton <strong>OFF</strong> de la télécommande principale\nLe vidéoprojecteur s''éteint et l''écran remonte automatiquement\nVeuillez ne pas débrancher les appareils', 'Éteindre le système (1 par ligne)', 'textarea', 7),
('guide_cinema', 'avertissement', 'Ne touchez jamais l''objectif du vidéoprojecteur. Éteignez le système après utilisation pour préserver la durée de vie de la lampe.', 'Avertissement', 'textarea', 8),

-- Cuisine
('guide_cuisine', 'title',          'La Cuisine',                              'Titre',              'text',     1),
('guide_cuisine', 'subtitle',       'Tout pour préparer vos repas en toute autonomie', 'Sous-titre', 'text',     2),
('guide_cuisine', 'equipements',    'Four encastrable\nPlaques à induction\nMicro-ondes\nLave-vaisselle\nRéfrigérateur / congélateur\nCafetière Nespresso\nBouilloire\nGrille-pain\nRobot mixeur', 'Équipements (1 par ligne)', 'textarea', 3),
('guide_cuisine', 'four',           'Tournez le sélecteur de mode sur le pictogramme souhaité (chaleur tournante recommandée)\nRéglez la température avec le second sélecteur\nLe voyant s''éteint lorsque la température est atteinte\nAprès utilisation, éteignez le four en remettant les deux sélecteurs sur 0', 'Utiliser le four (1 par ligne)', 'textarea', 4),
('guide_cuisine', 'induction',      'Appuyez sur le bouton <strong>ON/OFF</strong> du panneau tactile\nSélectionnez la zone de cuisson en appuyant sur le <strong>+</strong> ou <strong>-</strong> correspondant\nRéglez la puissance de 1 à 9\nUn signal sonore retentit à l''extinction', 'Plaques à induction (1 par ligne)', 'textarea', 5),
('guide_cuisine', 'nespresso',      'Remplissez le réservoir d''eau à l''arrière de la machine\nAllumez la machine — elle chauffe en 25 secondes\nInsérez une capsule et placez votre tasse\nAppuyez sur le bouton petit ou grand café\nLes capsules usagées vont dans le bac intégré', 'Machine Nespresso (1 par ligne)', 'textarea', 6),
('guide_cuisine', 'lave_vaisselle', 'Chargez la vaisselle (assiettes en bas, verres en haut)\nAjoutez une pastille dans le compartiment de la porte\nSélectionnez le programme <strong>Eco</strong> (bouton 2) pour un usage quotidien\nAppuyez sur <strong>Start</strong>', 'Lave-vaisselle (1 par ligne)', 'textarea', 7),
('guide_cuisine', 'tri',            'Poubelle grise : déchets ménagers\nPoubelle jaune : emballages, plastiques, cartons\nBac vert : verre\nCompost (jardin) : épluchures, marc de café', 'Tri des déchets (1 par ligne)', 'textarea', 8),
('guide_cuisine', 'consignes',      'Merci de laisser la cuisine propre et rangée en fin de séjour. Le lave-vaisselle doit être vidé. Les poubelles pleines peuvent être déposées dans le local poubelles à l''extérieur (côté garage).', 'Consignes de fin de séjour', 'textarea', 9),
('guide_cuisine', 'micro_ondes',    '', 'Micro-ondes (1 par ligne)', 'textarea', 10),

-- Chauffage / Climatisation
('guide_chauffage',     'title',        'Chauffage',                                   'Titre',              'text',     1),
('guide_chauffage',     'subtitle',     'Comment régler le chauffage',                 'Sous-titre',         'text',     2),
('guide_chauffage',     'instructions', '', 'Instructions (1 par ligne)', 'textarea', 3),

('guide_climatisation', 'title',        'Climatisation',                               'Titre',              'text',     1),
('guide_climatisation', 'subtitle',     'Comment utiliser la climatisation',            'Sous-titre',         'text',     2),
('guide_climatisation', 'instructions', '', 'Instructions (1 par ligne)', 'textarea', 3),

-- Ménager (machine à laver, sèche-linge)
('guide_menager', 'title',          'Buanderie',                                       'Titre',              'text',     1),
('guide_menager', 'subtitle',       'Machine à laver et sèche-linge',                  'Sous-titre',         'text',     2),
('guide_menager', 'machine_laver',  '', 'Machine à laver (1 par ligne)', 'textarea', 3),
('guide_menager', 'seche_linge',    '', 'Sèche-linge (1 par ligne)', 'textarea', 4),

-- Canapé convertible
('guide_canape', 'title',           'Canapé convertible',                              'Titre',              'text',     1),
('guide_canape', 'subtitle',        'Comment déplier le canapé-lit',                   'Sous-titre',         'text',     2),
('guide_canape', 'instructions',    '', 'Instructions (1 par ligne)', 'textarea', 3)

ON DUPLICATE KEY UPDATE field_value = VALUES(field_value);
