<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE pseudo = ?");
    $stmt->execute([$_POST['pseudo']]);
    $user = $stmt->fetch();

    if ($user && password_verify($_POST['password'], $user['password'])) {
        if (!empty($user['blocked']) && $user['blocked'] == 1) {
            $error = "Votre compte a été bloqué. Veuillez contacter l'administrateur.";
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_pseudo'] = $user['pseudo'];
            header('Location: index.php');
            exit();
        }
    } else {
        $error = "Identifiants incorrects";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#ffd700">
    <title>Connexion - Chat Pirate</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pirata+One&display=swap" rel="stylesheet">
    <style>
        body.pirate-theme {
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(120deg, #1e202c 0%, #232946 100%);
            position: relative;
            overflow-x: hidden;
        }
        /* Subtle animated background */
        body.pirate-theme::before {
            content: "";
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: url('https://www.transparenttextures.com/patterns/pirate.png');
            opacity: 0.06;
            z-index: 0;
            pointer-events: none;
        }
        .auth-card {
            max-width: 400px;
            margin: 3rem auto;
            padding: 2rem 1.5rem;
            border-radius: 18px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.25);
            background: rgba(34, 34, 51, 0.97);
            position: relative;
            z-index: 1;
        }
        .pirate-logo {
            width: 64px;
            display: block;
            margin: 0 auto 1rem auto;
            filter: drop-shadow(0 2px 8px #0009);
        }
        h2.pirate-title {
            font-family: 'Pirata One', cursive, sans-serif;
            letter-spacing: 1px;
            font-size: 2rem;
            color: #ffd700;
            text-shadow: 0 2px 8px #0004;
        }
        .form-label {
            color: #ffd700;
            font-weight: 500;
        }
        .form-control {
            background: #232946;
            border: 2px solid #393e46;
            color: #fff;
            border-radius: 8px;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            border-color: #ffd700;
            box-shadow: 0 0 0 0.15rem #ffd70060;
            background: #232946;
            color: #fff;
        }
        .btn-pirate {
            background: linear-gradient(90deg,#e94560 60%, #ffd700 100%);
            border: none;
            padding: 14px 0;
            font-weight: bold;
            color: #232946;
            border-radius: 8px;
            font-size: 1.1rem;
            box-shadow: 0 2px 8px #0002;
            transition: background 0.2s, transform 0.2s;
        }
        .btn-pirate:hover, .btn-pirate:focus {
            background: linear-gradient(90deg,#ffd700 60%, #e94560 100%);
            color: #232946;
            transform: translateY(-2px) scale(1.03);
        }
        .text-warning {
            color: #ffd700 !important;
        }
        .alert-danger {
            background: #e94560cc;
            color: #fff;
            border: none;
        }
        @media (max-width: 600px) {
            .auth-card {
                margin: 1.2rem 0.5rem;
                padding: 1.2rem 0.5rem;
            }
            h2.pirate-title {
                font-size: 1.4rem;
            }
            .pirate-logo {
                width: 48px;
            }
        }
    </style>
</head>
<body class="pirate-theme">
    <div class="container">
        <div class="auth-card">
            <img src="https://images.icon-icons.com/1061/PNG/512/pirate_icon-icons.com_76799.png" alt="Drapeau Pirate" class="pirate-logo" />
            <h2 class="pirate-title text-center mb-4">🚀 Rejoindre le bateau</h2>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="on">
                <div class="mb-3">
                    <label for="pseudo" class="form-label">Nom de Pirate</label>
                    <input 
                        type="text" 
                        class="form-control form-control-lg" 
                        id="pseudo" 
                        name="pseudo" 
                        required
                        minlength="3"
                        maxlength="32"
                        placeholder="Ex: Capitaine_Crochet"
                        autofocus
                        autocomplete="username">
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Mot de passe secret</label>
                    <input 
                        type="password" 
                        class="form-control form-control-lg" 
                        id="password" 
                        name="password" 
                        required
                        placeholder="••••••••"
                        autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-pirate btn-lg w-100">
                    🏴‍☠️ Rejoindre l'équipage
                </button>
                <div class="text-center mt-4">
                    <p class="mb-0">Pas encore inscrit ? 
                        <a href="register.php" class="text-decoration-none text-warning fw-bold">
                            Pas encore inscrit ? Rejoindre le bateau
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
