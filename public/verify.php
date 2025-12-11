<?php
require_once __DIR__ . '/../src/session_config.php';
require_once __DIR__ . '/../src/Database.php';


$message = '';
$success = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        $pdo = Database::getInstance()->getConnection();
        
        // Find user with this token
        $stmt = $pdo->prepare(
            "SELECT id, email_verified, verification_expires 
             FROM users 
             WHERE verification_token = ?"
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $message = "Token de vérification invalide.";
        } elseif ($user['email_verified']) {
            $message = "Votre email a déjà été vérifié. Vous pouvez vous connecter.";
            $success = true;
        } elseif (strtotime($user['verification_expires']) < time()) {
            $message = "Le lien de vérification a expiré. Veuillez vous réinscrire.";
        } else {
            // Verify the email
            $stmt = $pdo->prepare(
                "UPDATE users 
                 SET email_verified = 1, 
                     verification_token = NULL, 
                     verification_expires = NULL 
                 WHERE id = ?"
            );
            $stmt->execute([$user['id']]);
            
            $message = "Votre email a été vérifié avec succès ! Vous pouvez maintenant vous connecter.";
            $success = true;
        }
    } catch (Exception $e) {
        $message = "Une erreur s'est produite. Veuillez réessayer.";
        error_log("Email verification error: " . $e->getMessage());
    }
} else {
    $message = "Token de vérification manquant.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification Email - Atypi Chat</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#6c5ce7">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">
    <link rel="icon" type="image/png" href="favicon.png">
</head>
<body>
    <div class="container">
        <div class="auth-form">
            <h2>Vérification Email</h2>
            <div style="text-align: center; padding: 20px; <?php echo $success ? 'color: #27ae60;' : 'color: #e74c3c;'; ?>">
                <?php if ($success): ?>
                    <div style="font-size: 48px; margin-bottom: 20px;">✓</div>
                <?php else: ?>
                    <div style="font-size: 48px; margin-bottom: 20px;">✗</div>
                <?php endif; ?>
                <p style="font-size: 1.1rem; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            </div>
            <?php if ($success): ?>
                <button onclick="window.location.href='login.php'">Se connecter</button>
            <?php else: ?>
                <button onclick="window.location.href='register.php'">Retour à l'inscription</button>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
