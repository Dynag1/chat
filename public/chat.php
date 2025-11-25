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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Atypi Chat</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#6c5ce7">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">
    <link rel="icon" type="image/png" href="favicon.png">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(\Security::generateCSRFToken()); ?>">
</head>
<body>
    <div class="container">
        <header style="justify-content: center;">
            <div style="display: flex; flex-direction: column; align-items: center;">
                <img src="assets/icons/icon-192x192.png" alt="Atypi Chat Logo" style="width: 60px; height: 60px; margin-bottom: 10px;">
                <span style="font-size: 1.8rem; margin-bottom: 10px; font-weight: bold; letter-spacing: 1px;">
                    Atypi Chat
                </span>
                <div>
                    <button id="install-btn" class="btn-sm" style="display:none; background: #0984e3; margin-right: 5px;">Installer</button>
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
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
