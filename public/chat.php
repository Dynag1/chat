<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../src/session_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Chat - Atypi Chat</title>
    
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
        .loading-screen.hidden { opacity: 0; pointer-events: none; }
        .loading-content { text-align: center; }
        .loading-content img { width: 80px; height: 80px; animation: pulse 1.5s infinite; }
        .loading-content p { color: #6c5ce7; margin-top: 15px; font-weight: 500; }
        @keyframes pulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.05); opacity: 0.8; } }
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
    <meta name="csrf-token" content="<?php echo htmlspecialchars(\Security::generateCSRFToken()); ?>">
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
        <header style="justify-content: center;">
            <div style="display: flex; flex-direction: column; align-items: center;">
                <img src="assets/icons/icon-192x192.png" alt="Atypi Chat Logo" style="width: 60px; height: 60px; margin-bottom: 10px;">
                <span style="font-size: 1.8rem; margin-bottom: 10px; font-weight: bold; letter-spacing: 1px;">
                    Atypi Chat
                </span>
                <div>
                    <button id="install-btn" class="btn-sm" style="background: #0984e3; margin-right: 5px;">📲 Installer</button>
                    <button id="notify-btn" class="btn-sm" style="background: #e17055; margin-right: 5px; display:none;">🔔 Notifs</button>
                    <button id="report-btn" class="btn-sm btn-danger" style="display:none;">Signaler</button>
                    <button id="block-btn" class="btn-sm btn-danger" style="display:none; background: #2d3436;">Bloquer</button>
                    <button id="leave-btn" class="btn-sm btn-danger" style="display:none; margin-right: 5px;">Quitter</button>
                    <button id="next-btn" class="btn-sm">Chercher</button>
                    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                        <button onclick="window.location.href='admin.php'" class="btn-sm" style="background:#6c5ce7; border:1px solid white; margin-left: 5px;">Admin</button>
                    <?php endif; ?>
                    <button onclick="window.location.href='profile.php'" class="btn-sm" style="background:transparent; border:1px solid white; margin-left: 5px;">Profil</button>
                    <button onclick="window.location.href='logout.php'" class="btn-sm" style="background:transparent; border:1px solid white; margin-left: 5px;">Déconnexion</button>
                </div>
            </div>
        </header>

        <div id="chat-container">
            <div id="messages">
                <div class="system-message">Cliquez sur "Suivant" pour trouver un partenaire.</div>
            </div>
            
            <div id="input-area" style="display:none;">
                <input type="text" id="message-input" placeholder="Écrivez un message...">
                <button id="send-btn">Envoyer</button>
            </div>
        </div>
    </div>

    <script>
        const USER_ID = <?php echo $_SESSION['user_id']; ?>;
    </script>
    <script src="assets/js/chat.js"></script>
    <script src="assets/js/pwa.js"></script>
    <script>
        window.addEventListener('load', function() {
            const ls = document.getElementById('loading-screen');
            if (ls) { ls.classList.add('hidden'); setTimeout(() => ls.remove(), 300); }
        });
        setTimeout(function() {
            const ls = document.getElementById('loading-screen');
            if (ls) { ls.classList.add('hidden'); setTimeout(() => ls.remove(), 300); }
        }, 3000);
    </script>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
