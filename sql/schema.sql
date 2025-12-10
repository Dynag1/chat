-- sql/schema.sql
-- Schéma complet de la base de données Atypi Chat
-- Version: 2.0

CREATE DATABASE IF NOT EXISTS random_chat;
USE random_chat;

-- =============================================
-- Table: users
-- Stocke les informations des utilisateurs
-- =============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,                                    -- Chiffré
    email VARCHAR(255) NOT NULL,                                       -- Chiffré
    email_hash VARCHAR(64) NOT NULL UNIQUE,                            -- Hash SHA256 pour recherche/unicité
    password_hash VARCHAR(255) NOT NULL,
    is_blocked TINYINT(1) DEFAULT 0,                                   -- Compte bloqué par admin
    is_admin TINYINT(1) DEFAULT 0,                                     -- Est administrateur
    status ENUM('offline', 'online', 'searching', 'in_chat') DEFAULT 'offline',
    intent ENUM('discuter', 'aider', 'besoin_aide') DEFAULT 'discuter', -- Intention de l'utilisateur
    email_verified TINYINT(1) DEFAULT 0,                               -- Email vérifié
    verification_token VARCHAR(64) DEFAULT NULL,                        -- Token de vérification
    verification_expires TIMESTAMP NULL DEFAULT NULL,                   -- Expiration du token
    registration_ip VARCHAR(45) DEFAULT NULL,                           -- IP lors de l'inscription
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- Table: chats
-- Stocke les sessions de chat entre utilisateurs
-- =============================================
CREATE TABLE IF NOT EXISTS chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user1_id INT NOT NULL,
    user2_id INT NOT NULL,
    status ENUM('active', 'ended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =============================================
-- Table: messages
-- Stocke les messages des conversations
-- =============================================
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    sender_id INT NOT NULL,
    content TEXT NOT NULL,                                              -- Chiffré
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =============================================
-- Table: reports
-- Stocke les signalements d'utilisateurs
-- =============================================
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    reported_id INT NOT NULL,
    reason TEXT NOT NULL,                                               -- Chiffré
    conversation_snapshot TEXT DEFAULT NULL,                            -- Chiffré - JSON de la conversation
    chat_id INT DEFAULT NULL,                                           -- Référence au chat concerné
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =============================================
-- Table: blocked_users
-- Stocke les blocages entre utilisateurs
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
-- Index pour améliorer les performances
-- =============================================
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_users_intent ON users(intent);
CREATE INDEX idx_users_email_hash ON users(email_hash);
CREATE INDEX idx_chats_status ON chats(status);
CREATE INDEX idx_messages_chat_id ON messages(chat_id);
CREATE INDEX idx_reports_reported_id ON reports(reported_id);
