<?php
require_once __DIR__ . '/../src/session_config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: chat.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Atypi Chat</title>
    
    <!-- Inline critical CSS to prevent blank screen -->
    <style>
        body { 
            margin: 0; 
            background-color: #dfe6e9; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #dfe6e9;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.3s ease;
        }
        .loading-screen.hidden {
            opacity: 0;
            pointer-events: none;
        }
        .loading-content {
            text-align: center;
        }
        .loading-content img {
            width: 80px;
            height: 80px;
            animation: pulse 1.5s infinite;
        }
        .loading-content p {
            color: #6c5ce7;
            margin-top: 15px;
            font-weight: 500;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
        }
    </style>
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#6c5ce7">
    
    <!-- iOS PWA Support -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Atypi Chat">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="152x152" href="assets/icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="144x144" href="assets/icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="120x120" href="assets/icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="114x114" href="assets/icons/icon-192x192.png">
    <link rel="apple-touch-startup-image" href="assets/icons/icon-512x512.png">
    
    <link rel="icon" type="image/png" href="favicon.png">
</head>
<body>
    <!-- Loading screen -->
    <div class="loading-screen" id="loading-screen">
        <div class="loading-content">
            <img src="assets/icons/icon-192x192.png" alt="Atypi Chat">
            <p>Chargement...</p>
        </div>
    </div>

    <div class="container">
        <div class="auth-form">
            <img src="assets/icons/icon-192x192.png" alt="Atypi Chat Logo" style="width: 80px; height: 80px; margin: 0 auto 20px; display: block;">
            <h2>Bienvenue sur Atypi Chat</h2>
            <p style="text-align: center; margin-bottom: 20px;">Discutez avec des inconnus neuroatypique de manière aléatoire et sécurisée.</p>
            <button id="install-btn" style="background: #0984e3; margin-bottom: 10px;">📲 Installer l'App</button>
            <button onclick="window.location.href='login.php'">Connexion</button>
            <button onclick="window.location.href='register.php'" class="btn-secondary" style="margin-top: 10px; background: #fff; color: var(--primary-color); border: 1px solid var(--primary-color);">Inscription</button>
        </div>
    </div>
    <script src="assets/js/pwa.js"></script>
    <script>
        // Hide loading screen when page is ready
        window.addEventListener('load', function() {
            const loadingScreen = document.getElementById('loading-screen');
            if (loadingScreen) {
                loadingScreen.classList.add('hidden');
                setTimeout(() => loadingScreen.remove(), 300);
            }
        });
        // Fallback: hide after 3 seconds max
        setTimeout(function() {
            const loadingScreen = document.getElementById('loading-screen');
            if (loadingScreen) {
                loadingScreen.classList.add('hidden');
                setTimeout(() => loadingScreen.remove(), 300);
            }
        }, 3000);
    </script>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
