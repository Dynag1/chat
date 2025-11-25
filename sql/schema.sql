-- sql/schema.sql

CREATE DATABASE IF NOT EXISTS random_chat;
USE random_chat;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL, -- Encrypted
    email VARCHAR(255) NOT NULL UNIQUE, -- Encrypted (Note: Unique might be tricky with encryption if IV is random, usually deterministic encryption is needed for search, or we just don't enforce unique DB constraint on encrypted field and handle in app. For now, let's remove UNIQUE constraint on email if it's fully randomized encryption, or keep it if we use deterministic. Plan said "Encrypted". Let's assume standard encryption, so we can't easily index/unique it without deterministic mode. Removing UNIQUE for now to avoid issues, or we will handle uniqueness check in PHP by decrypting? No, that's slow. 
    -- Better approach for "Encrypted" email with uniqueness: Store a hashed version (SHA256) for lookups/uniqueness, and the encrypted version for retrieval.
    -- Let's add email_hash for uniqueness.
    email_hash VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_blocked TINYINT(1) DEFAULT 0,
    status ENUM('offline', 'online', 'searching', 'in_chat') DEFAULT 'offline',
    email_verified TINYINT(1) DEFAULT 0,
    verification_token VARCHAR(64) DEFAULT NULL,
    verification_expires TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Chats table
CREATE TABLE IF NOT EXISTS chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user1_id INT NOT NULL,
    user2_id INT NOT NULL,
    status ENUM('active', 'ended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user1_id) REFERENCES users(id),
    FOREIGN KEY (user2_id) REFERENCES users(id)
);

-- Messages table
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    sender_id INT NOT NULL,
    content TEXT NOT NULL, -- Encrypted
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES chats(id),
    FOREIGN KEY (sender_id) REFERENCES users(id)
);

-- Reports table
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    reported_id INT NOT NULL,
    reason TEXT NOT NULL, -- Encrypted
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id),
    FOREIGN KEY (reported_id) REFERENCES users(id)
);
