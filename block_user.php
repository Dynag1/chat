<?php
session_set_cookie_params([
    'lifetime' => 60*60*24*30, // 30 jours
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
require 'config.php';

$user_id = $_GET['user_id'] ?? null;
$token = $_GET['token'] ?? '';
$secret = 'IwqEVWWE1AvOybXbX0rml92cgkBiwscW967I'; // la même que plus haut

// Vérifier que l'utilisateur est connecté et admin
if (!isset($_SESSION['user_id'])) {
    echo "Accès refusé : vous devez être connecté.";
    exit;
}

// Vérifier le statut admin dans la base
$stmt = $pdo->prepare("SELECT admin FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$isAdmin = $stmt->fetchColumn();

if ($isAdmin != 1) {
    echo "Accès refusé : vous devez être administrateur.";
    exit;
}

// Vérification du token et blocage
if ($user_id && $token === hash('sha256', $user_id . $secret)) {
    $stmt = $pdo->prepare("UPDATE users SET blocked = 1 WHERE id = ?");
    $stmt->execute([$user_id]);

    if ($stmt->rowCount() > 0) {
        echo "Utilisateur bloqué avec succès.";
    } else {
        echo "Utilisateur introuvable ou déjà bloqué.";
    }
} else {
    echo "Lien de blocage invalide.";
}
?>
