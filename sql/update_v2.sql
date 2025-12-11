-- sql/update_v2.sql
-- Script de mise à jour de la base de données vers la version 2.0
-- Exécuter ce script pour mettre à jour une base existante

USE random_chat;

-- =============================================
-- Mise à jour de la table users
-- =============================================

-- Ajouter la colonne is_admin si elle n'existe pas
ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0;

-- Ajouter la colonne intent si elle n'existe pas
ALTER TABLE users ADD COLUMN intent ENUM('discuter', 'aider', 'besoin_aide') DEFAULT 'discuter';

-- Ajouter les colonnes de vérification email si elles n'existent pas
ALTER TABLE users ADD COLUMN email_verified TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN verification_token VARCHAR(64) DEFAULT NULL;
ALTER TABLE users ADD COLUMN verification_expires TIMESTAMP NULL DEFAULT NULL;

-- Ajouter la colonne registration_ip si elle n'existe pas
ALTER TABLE users ADD COLUMN registration_ip VARCHAR(45) DEFAULT NULL;

-- =============================================
-- Mise à jour de la table reports
-- =============================================

-- Ajouter la colonne conversation_snapshot si elle n'existe pas
ALTER TABLE reports ADD COLUMN conversation_snapshot TEXT DEFAULT NULL;

-- Ajouter la colonne chat_id si elle n'existe pas
ALTER TABLE reports ADD COLUMN chat_id INT DEFAULT NULL;

-- =============================================
-- Créer la table blocked_users si elle n'existe pas
-- =============================================
CREATE TABLE IF NOT EXISTS blocked_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    blocked_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blocked_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_block (user_id, blocked_user_id)
);

-- =============================================
-- Créer la table banned_identifiers si elle n'existe pas
-- Pour bannir les IP et emails des utilisateurs bloqués
-- =============================================
CREATE TABLE IF NOT EXISTS banned_identifiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('ip', 'email_hash') NOT NULL,
    value VARCHAR(255) NOT NULL,
    banned_user_id INT DEFAULT NULL,
    banned_by_admin_id INT NOT NULL,
    reason TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ban (type, value),
    FOREIGN KEY (banned_by_admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Index pour recherche rapide des bans
CREATE INDEX idx_banned_type_value ON banned_identifiers(type, value);

-- =============================================
-- Note: Les erreurs "Duplicate column" ou "Table exists" 
-- sont normales si déjà appliqué
-- =============================================
