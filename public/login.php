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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Atypi Chat</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#6c5ce7">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">
    <link rel="icon" type="image/png" href="favicon.png">
</head>
<body>
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
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
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
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
