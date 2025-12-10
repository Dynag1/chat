<?php
require_once __DIR__ . '/../src/session_config.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/Security.php';

if (isset($_SESSION['user_id'])) {
    header("Location: chat.php");
    exit;
}

$error = '';
$showResendLink = false;
$resendEmail = '';
$resendSuccess = false;

// Handle resend verification email request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend') {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Requête invalide. Veuillez réessayer.';
    } else {
        $email = Security::sanitizeInput($_POST['resend_email']);
        if (Security::validateEmail($email)) {
            $user = new User();
            $result = $user->resendVerificationEmail($email);
            if ($result['success']) {
                $resendSuccess = true;
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Format d\'email invalide.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Requête invalide. Veuillez réessayer.';
    } else {
        // Rate limiting
        $ip = Security::getClientIP();
        $rateLimitKey = 'login_' . $ip;
        
        if (!Security::checkRateLimit($rateLimitKey, 5, 900)) {
            $lockoutTime = Security::getRateLimitLockoutTime($rateLimitKey);
            $minutes = ceil($lockoutTime / 60);
            $error = "Trop de tentatives de connexion. Veuillez réessayer dans $minutes minute(s).";
        } else {
            // Sanitize inputs
            $email = Security::sanitizeInput($_POST['email']);
            $password = $_POST['password']; // Don't sanitize password
            
            // Validate email format
            if (!Security::validateEmail($email)) {
                $error = 'Format d\'email invalide.';
            } else {
                $user = new User();
                $result = $user->login($email, $password);
                
                if ($result['success']) {
                    // Regenerate session ID to prevent session fixation
                    Security::regenerateSession();
                    
                    $_SESSION['user_id'] = $result['user']['id'];
                    $_SESSION['username'] = $result['user']['username'];
                    $_SESSION['is_admin'] = $result['user']['is_admin'];
                    
                    header("Location: chat.php");
                    exit;
                } else {
                    $error = $result['message'];
                    // Check if it's an email verification issue
                    if (isset($result['email_not_verified']) && $result['email_not_verified']) {
                        $showResendLink = true;
                        $resendEmail = $email;
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
    <title>Connexion - Atypi Chat</title>
    
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
            <h2>Connexion</h2>
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="<?php echo $_SESSION['flash_type'] === 'warning' ? 'error' : 'success'; ?>" style="<?php echo $_SESSION['flash_type'] === 'success' ? 'background-color: #d4edda; color: #155724; border-color: #c3e6cb;' : ''; ?>">
                    <?php 
                        echo htmlspecialchars($_SESSION['flash_message']); 
                        unset($_SESSION['flash_message']);
                        unset($_SESSION['flash_type']);
                    ?>
                </div>
            <?php endif; ?>
            <?php if ($resendSuccess): ?>
                <div class="success" style="background-color: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 15px;">
                    ✓ Un nouvel email de vérification a été envoyé. Vérifiez votre boîte de réception et vos spams.
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php if ($showResendLink): ?>
                    <div style="margin-top: 15px; padding: 15px; background: #fff3cd; border-radius: 6px; border: 1px solid #ffc107;">
                        <p style="margin: 0 0 10px 0; color: #856404; font-size: 0.9em;">
                            📧 Vous n'avez pas reçu l'email ? Vérifiez vos spams ou renvoyez-le.
                        </p>
                        <form method="POST" action="" style="margin: 0;">
                            <input type="hidden" name="action" value="resend">
                            <input type="hidden" name="resend_email" value="<?php echo htmlspecialchars($resendEmail); ?>">
                            <?php echo Security::getCSRFInput(); ?>
                            <button type="submit" style="background: #ffc107; color: #212529; font-size: 0.9em; padding: 8px 15px;">
                                Renvoyer l'email de vérification
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <?php echo Security::getCSRFInput(); ?>
            <button type="submit">Se connecter</button>
            <p style="text-align: center; margin-top: 10px;">
                <a href="forgot_password.php" style="font-size: 0.9em;">Mot de passe oublié ?</a>
            </p>
            <p style="text-align: center; margin-top: 10px;">
                Pas encore de compte ? <a href="register.php">S'inscrire</a>
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
