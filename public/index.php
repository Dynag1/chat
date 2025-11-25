<?php
require_once __DIR__ . '/../src/session_config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: chat.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atypi Chat</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#6c5ce7">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">
    <link rel="icon" type="image/png" href="favicon.png">
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js');
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="auth-form">
            <img src="assets/icons/icon-192x192.png" alt="Atypi Chat Logo" style="width: 80px; height: 80px; margin: 0 auto 20px; display: block;">
            <h2>Bienvenue sur Atypi Chat</h2>
            <p style="text-align: center; margin-bottom: 20px;">Discutez avec des inconnus neuroatypique de manière aléatoire et sécurisée.</p>
            <button id="install-btn" style="display:none; background: #0984e3; margin-bottom: 10px;">📲 Installer l'App</button>
            <button onclick="window.location.href='login.php'">Connexion</button>
            <button onclick="window.location.href='register.php'" class="btn-secondary" style="margin-top: 10px; background: #fff; color: var(--primary-color); border: 1px solid var(--primary-color);">Inscription</button>
        </div>
    </div>
    <script src="assets/js/pwa.js"></script>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
