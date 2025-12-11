<?php
require_once __DIR__ . '/../src/session_config.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/Security.php';

if (isset($_SESSION['user_id'])) {
    header("Location: chat.php");
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Session invalide. Veuillez rafraîchir la page.';
        $messageType = 'error';
    } else {
        // Rate limiting
        $ip = Security::getClientIP();
        $rateLimitKey = 'forgot_password_' . $ip;
        
        if (!Security::checkRateLimit($rateLimitKey, 3, 3600)) {
            $message = "Trop de tentatives. Veuillez réessayer plus tard.";
            $messageType = 'error';
        } else {
            $email = Security::sanitizeInput($_POST['email']);
            
            if (Security::validateEmail($email)) {
                $user = new User();
                $user->initiatePasswordReset($email);
                
                // Always show success message to prevent email enumeration
                $message = "Si un compte existe avec cette adresse email, vous recevrez un lien de réinitialisation dans quelques instants.";
                $messageType = 'success';
            } else {
                $message = "Format d'email invalide.";
                $messageType = 'error';
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
    <title>Mot de passe oublié - Atypi Chat</title>
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
            <h2>Mot de passe oublié</h2>
            
            <?php if ($message): ?>
                <div class="<?php echo $messageType === 'error' ? 'error' : 'success'; ?>" style="<?php echo $messageType === 'success' ? 'background:#d4edda;color:#155724;padding:10px;border-radius:4px;margin-bottom:15px;' : ''; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <p style="text-align: center; margin-bottom: 20px; color: #636e72;">
                Entrez votre adresse email pour recevoir un lien de réinitialisation.
            </p>
            
            <input type="email" name="email" placeholder="Votre adresse email" required>
            <?php echo Security::getCSRFInput(); ?>
            
            <button type="submit">Envoyer le lien</button>
            
            <p style="text-align: center; margin-top: 20px;">
                <a href="login.php">Retour à la connexion</a>
            </p>
        </form>
    </div>
</body>
</html>
