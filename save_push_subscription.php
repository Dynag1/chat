<?php
session_start();
require 'vendor/autoload.php'; // Adapter selon ton arborescence

// Connexion à la base de données (reprends la config de ton code principal)
$db_host = '4685j.myd.infomaniak.com';
$db_name = '4685j_pirates';
$db_user = '4685j_pirates';
$db_pass = 'Uj4jP4j&R-73wAijsd7-dgs';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur BDD']);
    exit;
}

// Récupérer l'abonnement envoyé par le JS
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Enregistre l'abonnement (table à créer si besoin)
$stmt = $pdo->prepare("REPLACE INTO push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)");
$stmt->execute([
    $user_id,
    $data['endpoint'],
    $data['keys']['p256dh'],
    $data['keys']['auth']
]);

echo json_encode(['success' => true]);
