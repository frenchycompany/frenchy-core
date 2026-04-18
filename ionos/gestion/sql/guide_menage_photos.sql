CREATE TABLE IF NOT EXISTS guide_menage_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_zone INT NOT NULL,
    chemin VARCHAR(500) NOT NULL,
    legende VARCHAR(255) DEFAULT '',
    ordre INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_zone) REFERENCES guide_menage_zones(id) ON DELETE CASCADE
);
