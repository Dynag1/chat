
<?php
require_once __DIR__ . '/../src/session_config.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/Security.php';

if (isset($_SESSION['user_id'])) {
    header("Location: chat.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Requête invalide. Veuillez réessayer.';
    } else {
        // Rate limiting
        $ip = Security::getClientIP();
        $rateLimitKey = 'register_' . $ip;
        
        if (!Security::checkRateLimit($rateLimitKey, 3, 3600)) {
            $lockoutTime = Security::getRateLimitLockoutTime($rateLimitKey);
            $minutes = ceil($lockoutTime / 60);
            $error = "Trop de tentatives d'inscription. Veuillez réessayer dans $minutes minute(s).";
        } else {
            // Sanitize inputs
            $email = Security::sanitizeInput($_POST['email']);
            $password = $_POST['password'];
            $confirmPassword = $_POST['confirm_password'];
            
            // Validate email format
            if (!Security::validateEmail($email)) {
                $error = 'Format d\'email invalide.';
            } elseif ($password !== $confirmPassword) {
                $error = "Les mots de passe ne correspondent pas.";
            } else {
                // Validate password strength
                $passwordValidation = Security::validatePassword($password);
                
                if (!$passwordValidation['valid']) {
                    $error = $passwordValidation['message'];
                } elseif (!isset($_POST['terms'])) { // Validate terms
                    $error = 'Vous devez accepter les conditions d\'utilisation.';
                } else {
                    // Use email as username since we removed the username field
                    $username = explode('@', $email)[0];
                    
                    $user = new User();
                    $result = $user->register($username, $email, $password, $ip);
                    
                    if ($result['success']) {
                        // Regenerate session
                        Security::regenerateSession();
                        
                        $_SESSION['flash_message'] = $result['message'];
                        $_SESSION['flash_type'] = strpos($result['message'], 'pas pu être envoyé') !== false ? 'warning' : 'success';
                        
                        header("Location: login.php");
                        exit;
                    } else {
                        $error = $result['message'];
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Inscription - Atypi Chat</title>
    
    <!-- Inline critical CSS to prevent blank screen -->
    <style>
        body { margin: 0; background-color: #dfe6e9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; min-height: 100vh; display: flex; flex-direction: column; justify-content: center; }
        .loading-screen { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: #dfe6e9; display: flex; justify-content: center; align-items: center; z-index: 9999; transition: opacity 0.3s ease; }
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
        <form class="auth-form" action="" method="POST">
            <img src="assets/icons/icon-192x192.png" alt="Atypi Chat Logo" style="width: 80px; height: 80px; margin: 0 auto 20px; display: block;">
            <h2>Inscription</h2>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Mot de passe (min. 8 caractères)" required minlength="8">
            <input type="password" name="confirm_password" placeholder="Confirmer le mot de passe" required>
            
            <div style="margin: 15px 0; text-align: left; font-size: 0.9em;">
                <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="terms" required style="width: auto; margin-top: 3px;">
                    <span>J'accepte les <a href="terms.php" target="_blank">conditions d'utilisation</a></span>
                </label>
            </div>

            <?php echo Security::getCSRFInput(); ?>
            <button type="submit">S'inscrire</button>
            <p style="text-align: center; margin-top: 10px;">
                Déjà un compte ? <a href="login.php">Se connecter</a>
            </p>
        </form>
    </div>
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
