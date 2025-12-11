<?php
require_once __DIR__ . '/../src/session_config.php';
require_once __DIR__ . '/../src/Email.php';
require_once __DIR__ . '/../src/Security.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Requête invalide. Veuillez réessayer.';
    } else {
        // Rate limiting
        $ip = Security::getClientIP();
        $rateLimitKey = 'contact_' . $ip;
        
        if (!Security::checkRateLimit($rateLimitKey, 5, 3600)) {
            $error = "Trop de messages envoyés. Veuillez réessayer plus tard.";
        } else {
            $name = Security::sanitizeInput($_POST['name']);
            $email = Security::sanitizeInput($_POST['email']);
            $subject = Security::sanitizeInput($_POST['subject']);
            $message = Security::sanitizeInput($_POST['message']);
            
            if (!Security::validateEmail($email)) {
                $error = 'Format d\'email invalide.';
            } elseif (empty($name) || empty($subject) || empty($message)) {
                $error = 'Tous les champs sont obligatoires.';
            } else {
                $mailer = new Email();
                if ($mailer->sendContactEmail($email, $name, $subject, $message)) {
                    $success = 'Votre message a été envoyé avec succès.';
                } else {
                    $error = "Une erreur est survenue lors de l'envoi du message.";
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
    <title>Contact - Atypi Chat</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#6c5ce7">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">
    <link rel="icon" type="image/png" href="favicon.png">
</head>
<body>
    <div class="container">
        <form class="auth-form" action="" method="POST" style="max-width: 600px;">
            <a href="index.php" style="display: block; text-align: center; margin-bottom: 20px;">
                <img src="assets/icons/icon-192x192.png" alt="Atypi Chat Logo" style="width: 60px; height: 60px;">
            </a>
            <h2>Contactez-nous</h2>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success" style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 15px; text-align: left;">
                <label for="name" style="display: block; margin-bottom: 5px; color: #636e72;">Nom</label>
                <input type="text" id="name" name="name" required style="width: 100%; padding: 10px; border: 1px solid #dfe6e9; border-radius: 4px;">
            </div>

            <div class="form-group" style="margin-bottom: 15px; text-align: left;">
                <label for="email" style="display: block; margin-bottom: 5px; color: #636e72;">Email</label>
                <input type="email" id="email" name="email" required style="width: 100%; padding: 10px; border: 1px solid #dfe6e9; border-radius: 4px;">
            </div>

            <div class="form-group" style="margin-bottom: 15px; text-align: left;">
                <label for="subject" style="display: block; margin-bottom: 5px; color: #636e72;">Sujet</label>
                <input type="text" id="subject" name="subject" required style="width: 100%; padding: 10px; border: 1px solid #dfe6e9; border-radius: 4px;">
            </div>

            <div class="form-group" style="margin-bottom: 20px; text-align: left;">
                <label for="message" style="display: block; margin-bottom: 5px; color: #636e72;">Message</label>
                <textarea id="message" name="message" rows="5" required style="width: 100%; padding: 10px; border: 1px solid #dfe6e9; border-radius: 4px; font-family: inherit;"></textarea>
            </div>

            <?php echo Security::getCSRFInput(); ?>
            <button type="submit">Envoyer</button>
            
            <p style="text-align: center; margin-top: 15px;">
                <a href="index.php">Retour à l'accueil</a>
            </p>
        </form>
    </div>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
