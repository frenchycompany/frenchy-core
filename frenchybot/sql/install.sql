-- =====================================================
-- FrenchyBot — Schema des nouvelles tables
-- A executer sur la BDD existante de frenchy-core
-- NE TOUCHE PAS aux tables existantes
-- =====================================================

-- 1. hub_tokens : lien reservation → URL publique unique
CREATE TABLE IF NOT EXISTS hub_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    logement_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME DEFAULT NULL,
    last_accessed_at DATETIME DEFAULT NULL,
    access_count INT DEFAULT 0,
    UNIQUE KEY uk_token (token),
    UNIQUE KEY uk_reservation (reservation_id),
    KEY idx_logement (logement_id),
    FOREIGN KEY (reservation_id) REFERENCES reservation(id) ON DELETE CASCADE,
    FOREIGN KEY (logement_id) REFERENCES liste_logements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. hub_interactions : tracking actions/clics sur le HUB
CREATE TABLE IF NOT EXISTS hub_interactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hub_token_id INT NOT NULL,
    reservation_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    action_data JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_token (hub_token_id),
    KEY idx_reservation (reservation_id),
    KEY idx_type_date (action_type, created_at),
    FOREIGN KEY (hub_token_id) REFERENCES hub_tokens(id) ON DELETE CASCADE,
    FOREIGN KEY (reservation_id) REFERENCES reservation(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. upsells : config des upsells par logement
CREATE TABLE IF NOT EXISTS upsells (
    id INT AUTO_INCREMENT PRIMARY KEY,
    logement_id INT DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    label VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    icon VARCHAR(50) DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_logement_active (logement_id, active),
    FOREIGN KEY (logement_id) REFERENCES liste_logements(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. upsell_orders : achats upsells + Stripe
CREATE TABLE IF NOT EXISTS upsell_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    upsell_id INT NOT NULL,
    reservation_id INT NOT NULL,
    hub_token_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    status ENUM('pending','paid','cancelled','refunded') DEFAULT 'pending',
    stripe_session_id VARCHAR(255) DEFAULT NULL,
    stripe_payment_intent VARCHAR(255) DEFAULT NULL,
    customer_email VARCHAR(255) DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_stripe (stripe_session_id),
    KEY idx_reservation (reservation_id),
    FOREIGN KEY (upsell_id) REFERENCES upsells(id) ON DELETE CASCADE,
    FOREIGN KEY (reservation_id) REFERENCES reservation(id) ON DELETE CASCADE,
    FOREIGN KEY (hub_token_id) REFERENCES hub_tokens(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. auto_messages : config messages automatiques
CREATE TABLE IF NOT EXISTS auto_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    trigger_type ENUM('before_checkin','checkin_day','during_stay','checkout_day','after_checkout') NOT NULL,
    trigger_offset_hours INT DEFAULT 0,
    channel ENUM('sms','whatsapp','auto') DEFAULT 'auto',
    template TEXT NOT NULL,
    logement_id INT DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_active_trigger (active, trigger_type),
    FOREIGN KEY (logement_id) REFERENCES liste_logements(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. auto_messages_log : historique des envois auto
CREATE TABLE IF NOT EXISTS auto_messages_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    auto_message_id INT NOT NULL,
    reservation_id INT NOT NULL,
    channel VARCHAR(20) NOT NULL,
    status ENUM('sent','failed','skipped') NOT NULL,
    error_message TEXT DEFAULT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_msg_resa (auto_message_id, reservation_id),
    FOREIGN KEY (auto_message_id) REFERENCES auto_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (reservation_id) REFERENCES reservation(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. hub_qr_scans : tracking scans QR
CREATE TABLE IF NOT EXISTS hub_qr_scans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    logement_id INT NOT NULL,
    reservation_id INT DEFAULT NULL,
    hub_token_id INT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_logement_date (logement_id, scanned_at),
    FOREIGN KEY (logement_id) REFERENCES liste_logements(id) ON DELETE CASCADE,
    FOREIGN KEY (reservation_id) REFERENCES reservation(id) ON DELETE SET NULL,
    FOREIGN KEY (hub_token_id) REFERENCES hub_tokens(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. bot_knowledge : base de connaissances pour le chatbot IA
CREATE TABLE IF NOT EXISTS bot_knowledge (
    id INT AUTO_INCREMENT PRIMARY KEY,
    logement_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_logement_active (logement_id, active),
    FOREIGN KEY (logement_id) REFERENCES liste_logements(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. bot_conversations : historique des conversations chatbot
CREATE TABLE IF NOT EXISTS bot_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hub_token_id INT NOT NULL,
    reservation_id INT NOT NULL,
    role ENUM('user','assistant') NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_token_date (hub_token_id, created_at),
    KEY idx_reservation (reservation_id),
    FOREIGN KEY (hub_token_id) REFERENCES hub_tokens(id) ON DELETE CASCADE,
    FOREIGN KEY (reservation_id) REFERENCES reservation(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. bot_settings : configuration FrenchyBot (remplace le .env pour les cles API)
CREATE TABLE IF NOT EXISTS bot_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT DEFAULT NULL,
    setting_label VARCHAR(255) DEFAULT NULL,
    setting_group VARCHAR(50) DEFAULT 'general',
    setting_type ENUM('text','password','textarea','toggle','select') DEFAULT 'text',
    sort_order INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SEED : parametres par defaut
INSERT IGNORE INTO bot_settings (setting_key, setting_value, setting_label, setting_group, setting_type, sort_order) VALUES
('openai_api_key',     '', 'Cle API OpenAI',             'ia',       'password', 1),
('openai_model',       'gpt-4o-mini', 'Modele OpenAI',   'ia',       'select',   2),
('whatsapp_token',     '', 'Token WhatsApp (Meta)',       'whatsapp', 'password', 1),
('whatsapp_phone_id',  '', 'Phone ID WhatsApp',           'whatsapp', 'text',     2),
('stripe_secret_key',  '', 'Cle secrete Stripe',          'stripe',   'password', 1),
('stripe_webhook_secret','', 'Secret Webhook Stripe',     'stripe',   'password', 2),
('admin_phone',        '', 'Telephone admin (notifications)', 'general', 'text',  1),
('app_url',            'https://gestion.frenchyconciergerie.fr', 'URL de l''application', 'general', 'text', 2),
('bot_name',           'Frenchy', 'Nom du bot',            'ia',       'text',     3),
('bot_instructions',   'Tu es un assistant amical et professionnel pour une conciergerie de locations courte duree.', 'Instructions generales du bot', 'ia', 'textarea', 4),
('auto_generate_hub',  '0', 'Generer automatiquement les HUB pour les nouvelles reservations', 'general', 'toggle', 3),
('notify_on_chat',     '1', 'Notifier l''admin quand le bot ne sait pas repondre', 'general', 'toggle', 4);

-- =====================================================
-- SEED : upsells par defaut
-- =====================================================
INSERT IGNORE INTO upsells (name, label, description, price, icon, sort_order) VALUES
('early_checkin', 'Early Check-in', 'Arrivez des 14h au lieu de 16h', 25.00, 'fa-clock', 1),
('late_checkout', 'Late Check-out', 'Depart a 12h au lieu de 10h', 25.00, 'fa-door-open', 2),
('menage_supplementaire', 'Menage supplementaire', 'Menage en cours de sejour', 40.00, 'fa-broom', 3),
('pack_linge', 'Pack linge premium', 'Draps et serviettes supplementaires', 15.00, 'fa-tshirt', 4),
('transfert_gare', 'Transfert gare', 'Navette gare ↔ logement', 20.00, 'fa-car', 5);

-- =====================================================
-- SEED : messages automatiques par defaut
-- =====================================================
INSERT IGNORE INTO auto_messages (name, trigger_type, trigger_offset_hours, channel, template) VALUES
('pre_arrival', 'before_checkin', -24, 'auto',
 'Bonjour {prenom} ! Votre sejour a {logement} commence demain. Toutes les infos ici : {hub_url} — Frenchy Conciergerie'),
('welcome', 'checkin_day', 0, 'auto',
 'Bienvenue {prenom} ! Votre logement {logement} vous attend. Acces, wifi et infos pratiques : {hub_url} — Bonne installation !'),
('checkout_reminder', 'checkout_day', -3, 'auto',
 'Bonjour {prenom}, rappel : depart prevu aujourd''hui avant {heure_checkout}. Merci de votre sejour ! Frenchy Conciergerie');
