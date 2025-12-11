<?php
require_once __DIR__ . '/../src/session_config.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/Security.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user = new User();
$userData = $user->getUser($_SESSION['user_id']);
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Session invalide. Veuillez rafraîchir la page.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_intent') {
            $intent = $_POST['intent'];
            $result = $user->updateIntent($_SESSION['user_id'], $intent);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            if ($result['success']) {
                $userData = $user->getUser($_SESSION['user_id']);
            }
        } elseif ($action === 'update_email') {
            $newEmail = Security::sanitizeInput($_POST['email']);
            $password = $_POST['password'];
            
            if ($user->verifyPassword($_SESSION['user_id'], $password)) {
                if (Security::validateEmail($newEmail)) {
                    $result = $user->updateEmail($_SESSION['user_id'], $newEmail);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'error';
                    if ($result['success']) {
                        // Refresh user data
                        $userData = $user->getUser($_SESSION['user_id']);
                    }
                } else {
                    $message = 'Format d\'email invalide.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Mot de passe incorrect.';
                $messageType = 'error';
            }
        } elseif ($action === 'update_password') {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            if ($user->verifyPassword($_SESSION['user_id'], $currentPassword)) {
                if ($newPassword === $confirmPassword) {
                    $validation = Security::validatePassword($newPassword);
                    if ($validation['valid']) {
                        $result = $user->updatePassword($_SESSION['user_id'], $newPassword);
                        $message = $result['message'];
                        $messageType = $result['success'] ? 'success' : 'error';
                    } else {
                        $message = $validation['message'];
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Les nouveaux mots de passe ne correspondent pas.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Mot de passe actuel incorrect.';
                $messageType = 'error';
            }
        } elseif ($action === 'delete_account') {
            $password = $_POST['password_delete'];
            if ($user->verifyPassword($_SESSION['user_id'], $password)) {
                $result = $user->deleteAccount($_SESSION['user_id']);
                if ($result['success']) {
                    session_destroy();
                    header("Location: index.php");
                    exit;
                } else {
                    $message = $result['message'];
                    $messageType = 'error';
                }
            } else {
                $message = 'Mot de passe incorrect. Suppression annulée.';
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
    <title>Mon Profil - Atypi Chat</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#6c5ce7">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">
    <link rel="icon" type="image/png" href="favicon.png">
    <style>
        .profile-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .profile-section h3 {
            margin-top: 0;
            color: #2d3436;
            border-bottom: 2px solid #f1f2f6;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .danger-zone {
            border: 1px solid #ff7675;
            background: #fff5f5;
        }
        .danger-zone h3 {
            color: #d63031;
            border-color: #ffcccc;
        }
        .btn-danger {
            background: #d63031;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #6c5ce7;
            text-decoration: none;
            font-weight: bold;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #636e72;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #dfe6e9;
            border-radius: 4px;
        }
        /* Override container style for profile page to allow scrolling */
        .container {
            overflow-y: auto !important;
            display: block !important; /* Better for scrolling content */
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="chat.php" class="back-link">&larr; Retour au Chat</a>
        
        <div class="header" style="text-align: center; margin-bottom: 30px;">
            <img src="assets/icons/icon-192x192.png" alt="Logo" style="width: 60px; height: 60px;">
            <h1>Mon Profil</h1>
            <p>Gérez vos informations personnelles</p>
        </div>

        <?php if ($message): ?>
            <div class="<?php echo $messageType; ?>" style="margin-bottom: 20px; padding: 15px; border-radius: 4px; <?php echo $messageType === 'success' ? 'background:#d4edda;color:#155724;' : 'background:#f8d7da;color:#721c24;'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Update Intent -->
        <div class="profile-section">
            <h3>Mon Intention</h3>
            <p>Que souhaitez-vous faire sur Atypi Chat ?</p>
            <form action="" method="POST">
                <input type="hidden" name="action" value="update_intent">
                <?php echo Security::getCSRFInput(); ?>
                
                <div class="form-group">
                    <label>Je suis ici pour :</label>
                    <select name="intent" style="width: 100%; padding: 10px; border: 1px solid #dfe6e9; border-radius: 4px;">
                        <option value="discuter" <?php echo ($userData['intent'] ?? 'discuter') === 'discuter' ? 'selected' : ''; ?>>Discuter simplement</option>
                        <option value="aider" <?php echo ($userData['intent'] ?? 'discuter') === 'aider' ? 'selected' : ''; ?>>Aider quelqu'un</option>
                        <option value="besoin_aide" <?php echo ($userData['intent'] ?? 'discuter') === 'besoin_aide' ? 'selected' : ''; ?>>J'ai besoin d'aide</option>
                    </select>
                </div>
                <button type="submit">Mettre à jour mon intention</button>
            </form>
        </div>

        <!-- Update Email -->
        <div class="profile-section">
            <h3>Modifier l'adresse email</h3>
            <p>Email actuel : <strong><?php echo htmlspecialchars($userData['email']); ?></strong></p>
            <form action="" method="POST">
                <input type="hidden" name="action" value="update_email">
                <?php echo Security::getCSRFInput(); ?>
                
                <div class="form-group">
                    <label>Nouvel email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Mot de passe actuel (pour confirmer)</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit">Mettre à jour l'email</button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="profile-section">
            <h3>Changer le mot de passe</h3>
            <form action="" method="POST">
                <input type="hidden" name="action" value="update_password">
                <?php echo Security::getCSRFInput(); ?>
                
                <div class="form-group">
                    <label>Mot de passe actuel</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>Nouveau mot de passe</label>
                    <input type="password" name="new_password" required minlength="8">
                </div>
                <div class="form-group">
                    <label>Confirmer le nouveau mot de passe</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit">Changer le mot de passe</button>
            </form>
        </div>

        <!-- Delete Account -->
        <div class="profile-section danger-zone">
            <h3>Zone de danger</h3>
            <p>La suppression du compte est irréversible. Toutes vos données seront effacées.</p>
            <form action="" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement votre compte ? Cette action est irréversible.');">
                <input type="hidden" name="action" value="delete_account">
                <?php echo Security::getCSRFInput(); ?>
                
                <div class="form-group">
                    <label>Mot de passe (pour confirmer la suppression)</label>
                    <input type="password" name="password_delete" required>
                </div>
                <button type="submit" class="btn-danger">Supprimer mon compte</button>
            </form>
        </div>
    </div>
</body>
</html>
