<?php
require 'vendor/autoload.php'; // Charge toutes les classes via Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$db_host = 'xxx';
$db_name = 'xxx';
$db_user = 'xxx';
$db_pass = 'xxx';

$mailConfig = [
    'host' => 'xxx',
    'username' => 'xxx',
    'password' => 'xxx',
    'port' => 587,
    'encryption' => PHPMailer::ENCRYPTION_STARTTLS,
    'from_email' => 'xxx',
    'from_name' => 'xxx',
    'secret_key' => 'xxx' // clé secrète pour token blocage
];

define('CHAT_ENCRYPTION_KEY', 'xxx');



try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Création automatique des tables si elles n'existent pas
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            pseudo VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_activity DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS waiting_users (
            user_id INT PRIMARY KEY,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS active_pairs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user1_id INT NOT NULL,
            user2_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS messages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            pair_id INT NOT NULL,
            sender_id INT NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (pair_id) REFERENCES active_pairs(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS reports (
            id INT PRIMARY KEY AUTO_INCREMENT,
            reporter_id INT NOT NULL,
            reported_id INT NOT NULL,
            reason TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reporter_id) REFERENCES users(id),
            FOREIGN KEY (reported_id) REFERENCES users(id)
        );
        CREATE TABLE IF NOT EXISTS broadcasts (
          id INT AUTO_INCREMENT PRIMARY KEY,
          message TEXT NOT NULL,
          created_at DATETIME NOT NULL
        );

    ");

} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

function check_auth() {
    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Non connecté', 'redirect' => 'login.php']);
        exit;
    }
}
$VAPID = [
    'subject' => 'mxxx',
    'publicKey' => 'Bxxx',
    'privateKey' => 'xxx',
];
?>
