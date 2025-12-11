<?php
require_once __DIR__ . '/../src/session_config.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/Security.php';

if (isset($_SESSION['user_id'])) {
    header("Location: chat.php");
    exit;
}

$token = $_GET['token'] ?? '';
$user = new User();
$validToken = false;

if ($token) {
    $validToken = $user->verifyResetToken($token);
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Session invalide. Veuillez rafraîchir la page.';
        $messageType = 'error';
    } elseif (!$validToken) {
        $message = 'Session expirée ou lien invalide.';
        $messageType = 'error';
    } else {
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if ($password !== $confirmPassword) {
            $message = "Les mots de passe ne correspondent pas.";
            $messageType = 'error';
        } else {
            $validation = Security::validatePassword($password);
            if (!$validation['valid']) {
                $message = $validation['message'];
                $messageType = 'error';
            } else {
                $result = $user->resetPasswordWithToken($token, $password);
                if ($result['success']) {
                    $_SESSION['flash_message'] = $result['message'];
                    $_SESSION['flash_type'] = 'success';
                    header("Location: login.php");
                    exit;
                } else {
                    $message = $result['message'];
                    $messageType = 'error';
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
    <title>Réinitialisation - Atypi Chat</title>
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
            <h2>Nouveau mot de passe</h2>
            
            <?php if ($message): ?>
                <div class="error"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($validToken): ?>
                <input type="password" name="password" placeholder="Nouveau mot de passe (min. 8 caractères)" required minlength="8">
                <input type="password" name="confirm_password" placeholder="Confirmer le mot de passe" required>
                <?php echo Security::getCSRFInput(); ?>
                <button type="submit">Changer le mot de passe</button>
            <?php else: ?>
                <div class="error" style="text-align: center; margin-bottom: 20px;">
                    Ce lien de réinitialisation est invalide ou a expiré.
                </div>
                <p style="text-align: center;">
                    <a href="forgot_password.php" class="button" style="display:inline-block;width:auto;">Demander un nouveau lien</a>
                </p>
            <?php endif; ?>
            
            <p style="text-align: center; margin-top: 20px;">
                <a href="login.php">Retour à la connexion</a>
            </p>
        </form>
    </div>
</body>
</html>
