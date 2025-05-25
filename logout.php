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

// Suppression de l'utilisateur de la recherche de paire
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("DELETE FROM waiting_users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

// 1. Vide toutes les variables de session
$_SESSION = array();

// 2. Supprime le cookie de session côté client
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. Détruit la session côté serveur
session_destroy();

// 4. Redirige vers la page d'accueil
header("Location: /index.php?login");
exit;
?>
