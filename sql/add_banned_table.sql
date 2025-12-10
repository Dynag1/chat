-- sql/add_banned_table.sql
-- Table pour stocker les IP et emails bannis par les admins

CREATE TABLE IF NOT EXISTS banned_identifiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('ip', 'email_hash') NOT NULL,
    value VARCHAR(255) NOT NULL,
    banned_user_id INT DEFAULT NULL,          -- L'utilisateur qui a été banni
    banned_by_admin_id INT NOT NULL,          -- L'admin qui a banni
    reason TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ban (type, value),
    FOREIGN KEY (banned_by_admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Index pour recherche rapide
CREATE INDEX idx_banned_type_value ON banned_identifiers(type, value);

