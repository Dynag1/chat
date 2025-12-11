<?php
require_once __DIR__ . '/../src/Database.php';

$migrations = [
    // Migration 1: Add intent column
    [
        'name' => 'add_intent_column',
        'sql' => "ALTER TABLE users ADD COLUMN intent ENUM('discuter', 'aider', 'besoin_aide') DEFAULT 'discuter' AFTER status",
        'check' => "SHOW COLUMNS FROM users LIKE 'intent'"
    ],
    // Migration 2: Add registration_ip column
    [
        'name' => 'add_registration_ip',
        'sql' => "ALTER TABLE users ADD COLUMN registration_ip VARCHAR(45) DEFAULT NULL",
        'check' => "SHOW COLUMNS FROM users LIKE 'registration_ip'"
    ],
    // Migration 3: Add conversation_snapshot to reports
    [
        'name' => 'add_conversation_snapshot',
        'sql' => "ALTER TABLE reports ADD COLUMN conversation_snapshot TEXT DEFAULT NULL",
        'check' => "SHOW COLUMNS FROM reports LIKE 'conversation_snapshot'"
    ],
    // Migration 4: Add chat_id to reports
    [
        'name' => 'add_chat_id_to_reports',
        'sql' => "ALTER TABLE reports ADD COLUMN chat_id INT DEFAULT NULL",
        'check' => "SHOW COLUMNS FROM reports LIKE 'chat_id'"
    ],
    // Migration 5: Create banned_identifiers table
    [
        'name' => 'create_banned_identifiers_table',
        'sql' => "CREATE TABLE IF NOT EXISTS banned_identifiers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('ip', 'email_hash') NOT NULL,
            value VARCHAR(255) NOT NULL,
            banned_user_id INT DEFAULT NULL,
            banned_by_admin_id INT NOT NULL,
            reason TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_ban (type, value)
        )",
        'check' => "SHOW TABLES LIKE 'banned_identifiers'"
    ],
];

try {
    $pdo = Database::getInstance()->getConnection();
    
    echo "=== Database Migration Script ===\n\n";
    
    foreach ($migrations as $migration) {
        echo "Checking migration: {$migration['name']}... ";
        
        // Check if migration already applied
        $stmt = $pdo->query($migration['check']);
        $exists = $stmt->fetch();
        
        if ($exists) {
            echo "SKIPPED (already exists)\n";
        } else {
            try {
                $pdo->exec($migration['sql']);
                echo "APPLIED\n";
            } catch (PDOException $e) {
                echo "ERROR: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n=== Migration complete ===\n";
    
} catch (PDOException $e) {
    echo "Connection error: " . $e->getMessage() . "\n";
}
