<?php 
require 'config.php';

$error = '';

$code_from_url = isset($_GET['code']) ? htmlspecialchars($_GET['code']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $pseudo = htmlspecialchars($_POST['pseudo']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $invitation_code = htmlspecialchars($_POST['invitation_code']);
    $ip_address = $_SERVER['REMOTE_ADDR'];

    if (!$email) {
        $error = "Format d'email invalide";
    } else {
        try {
            // Vérifier le code d'invitation
            $stmt = $pdo->prepare("SELECT * FROM invitation_codes WHERE code = ? AND used = 0");
            $stmt->execute([$invitation_code]);
            if(!$stmt->fetch()) {
                $error = "Code d'invitation invalide ou déjà utilisé";
            } else {
                // Vérifier unicité email
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if($stmt->fetch()) {
                    $error = "Cet email est déjà enregistré";
                } else {
                    $pdo->beginTransaction();
                    // Insérer l'utilisateur
                    $stmt = $pdo->prepare("INSERT INTO users (pseudo, password, email, ip_address, invitation_code) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$pseudo, $password, $email, $ip_address, $invitation_code]);
                    // Marquer le code comme utilisé
                    $stmt = $pdo->prepare("UPDATE invitation_codes SET used = 1 WHERE code = ?");
                    $stmt->execute([$invitation_code]);
                    $pdo->commit();

                    $_SESSION['user_id'] = $pdo->lastInsertId();
                    header('Location: index.php');
                    exit();
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Erreur serveur : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Chat Pirate</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Pirata+One&display=swap" rel="stylesheet">
    <style>
        body.pirate-theme {
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(120deg, #1e202c 0%, #232946 100%);
            position: relative;
            overflow-x: hidden;
        }
        body.pirate-theme::before {
            content: "";
            position: fixed;
            top: 0; left: 0; 
            width: 100vw; height: 100vh;
            background: url('https://www.transparenttextures.com/patterns/pirate.png');
            opacity: 0.06;
            z-index: 0;
            pointer-events: none;
        }
        .auth-card {
            max-width: 450px;
            margin: 3rem auto;
            padding: 2rem 1.5rem;
            border-radius: 18px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.25);
            background: rgba(34, 34, 51, 0.97);
            position: relative;
            z-index: 1;
        }
        .pirate-logo {
            width: 72px;
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
            text-align: center;
        }
        .form-label {
            color: #ffd700;
            font-weight: 500;
            margin-bottom: 0.5rem;
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
        .alert-danger {
            background: #e94560cc;
            color: #fff;
            border: none;
            border-radius: 8px;
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
                width: 56px;
            }
        }
    </style>
</head>
<body class="pirate-theme">
    <div class="container">
        <div class="auth-card">
            <img src="https://images.icon-icons.com/1061/PNG/512/pirate_icon-icons.com_76799.png" alt="Drapeau Pirate" class="pirate-logo" />
            <h2 class="pirate-title mb-4">Devenir Pirate</h2>

            <?php if($error): ?>
                <div class="alert alert-danger mb-4"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="on">
                <div class="mb-3">
                    <label for="email" class="form-label">Email Pirate</label>
                    <input 
                        type="email" 
                        class="form-control form-control-lg" 
                        id="email" 
                        name="email" 
                        required
                        placeholder="exemple@pirate.com"
                        autocomplete="email"
                        value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>

                <div class="mb-3">
                    <label for="invitation_code" class="form-label">Code d'Invitation</label>
                    <input 
                        type="text" 
                        class="form-control form-control-lg" 
                        id="invitation_code" 
                        name="invitation_code" 
                        required
                        placeholder="Code secret"
                        value="<?= isset($_POST['invitation_code']) ? htmlspecialchars($_POST['invitation_code']) : $code_from_url ?>">
                </div>

                <div class="mb-3">
                    <label for="pseudo" class="form-label">Nom de Pirate</label>
                    <input 
                        type="text" 
                        class="form-control form-control-lg" 
                        id="pseudo" 
                        name="pseudo" 
                        required
                        minlength="3"
                        placeholder="Ex: Capitaine_Crochet"
                        autocomplete="username"
                        value="<?= isset($_POST['pseudo']) ? htmlspecialchars($_POST['pseudo']) : '' ?>">
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Mot de passe secret</label>
                    <input 
                        type="password" 
                        class="form-control form-control-lg" 
                        id="password" 
                        name="password" 
                        required
                        minlength="6"
                        placeholder="••••••••"
                        autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-pirate btn-lg w-100">
                    🏴‍☠️ Rejoindre l'équipage
                </button>

                <div class="text-center mt-4">
                    <p class="mb-0">Déjà inscrit ? 
                        <a href="login.php" class="text-decoration-none text-warning fw-bold">
                            Partir à l'abordage !
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>