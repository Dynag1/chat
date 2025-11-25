
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
                    $result = $user->register($username, $email, $password);
                    
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Atypi Chat</title>
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
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
