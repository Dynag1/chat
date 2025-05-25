<?php
session_start();
require 'config.php';

$isLogged = isset($_SESSION['user_id']);
$isAdmin = false;

if ($isLogged) {
    $stmt = $pdo->prepare("SELECT admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $isAdmin = $stmt->fetchColumn();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#ffd700">
    <title>Chat Pirate</title>
    <link rel="icon" type="image/png" href="icon-192.png">

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
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: url('https://www.transparenttextures.com/patterns/pirate.png');
            opacity: 0.06;
            z-index: 0;
            pointer-events: none;
        }
        .pirate-logo {
            width: 64px;
            display: block;
            margin: 0 auto 1.2rem auto;
            filter: drop-shadow(0 2px 8px #0009);
        }
        .pirate-title {
            font-family: 'Pirata One', cursive, sans-serif;
            color: #ffd700;
            letter-spacing: 1px;
            text-shadow: 0 2px 8px #0004;
            font-size: 2.1rem;
        }
        .welcome-card {
            max-width: 420px;
            margin: 2.5rem auto;
            padding: 2rem 1.5rem;
            border-radius: 18px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.25);
            background: rgba(34, 34, 51, 0.97);
            position: relative;
            z-index: 1;
        }
        .alert-success {
            background: #ffd700dd;
            color: #232946;
            border: none;
            font-weight: bold;
            text-align: center;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.7rem;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .btn-pirate {
            background: linear-gradient(90deg,#e94560 60%, #ffd700 100%);
            border: none;
            color: #232946;
            font-weight: bold;
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
        .btn-admin {
            background: #232946;
            color: #ffd700;
            border: 2px solid #ffd700;
            font-weight: bold;
            border-radius: 8px;
            box-shadow: 0 2px 8px #0002;
            transition: background 0.2s, color 0.2s;
        }
        .btn-admin:hover, .btn-admin:focus {
            background: #ffd700;
            color: #232946;
        }
        .invite-section {
            margin-top: 1.5rem;
            padding: 1.3rem 1rem;
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            box-shadow: 0 2px 8px #0001;
        }
        .invite-section h4 {
            color: #ffd700;
            font-family: 'Pirata One', cursive, sans-serif;
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }
        .card.border-info {
            border-color: #ffd700 !important;
            background: #fffbe9;
        }
        .card-title {
            color: #e94560;
            font-weight: bold;
        }
        .btn-outline-secondary, .btn-success {
            border-radius: 8px;
        }
        .btn-warning {
            background: linear-gradient(90deg,#ffd700 60%, #e94560 100%);
            color: #232946;
            font-weight: bold;
            border: none;
        }
        .btn-warning:hover {
            background: linear-gradient(90deg,#e94560 60%, #ffd700 100%);
            color: #232946;
        }
        .input-group input {
            border-radius: 8px 0 0 8px;
        }
        .input-group .btn {
            border-radius: 0 8px 8px 0;
        }
        .auth-links {
            margin-top: 2.5rem;
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        @media (max-width: 600px) {
            .welcome-card {
                margin: 1.2rem 0.5rem;
                padding: 1.2rem 0.5rem;
            }
            .pirate-logo {
                width: 48px;
            }
            .pirate-title {
                font-size: 1.3rem;
            }
            .button-group {
                flex-direction: column;
            }
            .auth-links {
                flex-direction: column;
                gap: 0.7rem;
            }
        }
    </style>
</head>
<body class="pirate-theme">
    <div class="container">
        <div class="welcome-card">
            <img src="https://images.icon-icons.com/1061/PNG/512/pirate_icon-icons.com_76799.png" alt="Drapeau Pirate" class="pirate-logo" />

            <h1 class="pirate-title text-center mb-4">Chat Pirate</h1>
            <?php if($isLogged): ?>
                <div class="alert alert-success">
                    Bienvenue, Pirate <?= htmlspecialchars($_SESSION['user_pseudo']) ?> !
                </div>
                <div class="button-group mb-3">
                    <a href="chat.php" class="btn btn-pirate">💬 Accéder au Chat</a>
                    <?php if($isAdmin): ?>
                        <a href="admin.php" class="btn btn-admin">⚙️ Administration</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-danger">🚪 Déconnexion</a>
                </div>
                <div class="invite-section">
                    <h4>⚓ Inviter un équipage</h4>
                    <button id="generateInvite" class="btn btn-warning mb-2">
                        🎌 Générer un lien d'invitation
                    </button>
                    <div id="inviteResult" class="mt-3" style="display:none;">
                        <div class="card border-info">
                            <div class="card-body text-dark">
                                <h5 class="card-title">🗺️ Lien d'invitation</h5>
                                <div class="input-group mb-3">
                                    <input type="text" id="inviteLink" class="form-control" readonly onclick="this.select()">
                                    <button class="btn btn-outline-secondary" onclick="copyToClipboard()">
                                        📋 Copier
                                    </button>
                                </div>
                                <a href="#" id="mailtoLink" class="btn btn-success">
                                    📧 Envoyer par email
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center">

                    <h1 class="pirate-title mb-4">Bienvenue sur le Chat Pirate !</h1>
                    <div class="auth-links">
                        <a href="login.php" class="btn btn-pirate">Connexion</a>
                        <a href="register.php" class="btn btn-outline-secondary">Inscription</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
    // Gestion de la génération d'invitation
    const genBtn = document.getElementById('generateInvite');
    if(genBtn){
        genBtn.addEventListener('click', function() {
            fetch('actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=generate_invite_code'
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    const baseUrl = window.location.origin;
                    const inviteLink = `${baseUrl}/register.php?code=${encodeURIComponent(data.code)}`;
                    document.getElementById('inviteLink').value = inviteLink;
                    document.getElementById('inviteResult').style.display = 'block';
                    // Préparation du lien mailto
                    const mailto = `mailto:?subject=Invitation%20Chat%20Pirate&body=Rejoins-moi%20sur%20le%20Chat%20Pirate%20!%0A%0AClique%20ici%20pour%20t'inscrire%20:%20${encodeURIComponent(inviteLink)}`;
                    document.getElementById('mailtoLink').href = mailto;
                }
            });
        });
    }
    function copyToClipboard() {
        const copyText = document.getElementById('inviteLink');
        copyText.select();
        document.execCommand('copy');
        alert('Lien copié dans le presse-papier !');
    }
    </script>
    <button id="install-btn"
    style="display:none; position:fixed; bottom:20px; right:20px; z-index:9999; background:#ffd700; color:#222; border:none; border-radius:8px; padding:10px 20px; font-weight:bold; box-shadow:0 2px 8px rgba(0,0,0,0.15);">
    Installer l’application
    </button>
    <script>
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/service-worker.js');
    }
    let deferredPrompt = null;
    const installBtn = document.getElementById('install-btn');
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        installBtn.style.display = 'block';
    });
    installBtn.addEventListener('click', async () => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            if (outcome === 'accepted') {
                installBtn.style.display = 'none';
            }
            deferredPrompt = null;
        }
    });
    window.addEventListener('appinstalled', () => {
        installBtn.style.display = 'none';
    });
    </script>
    <script>
const VAPID_PUBLIC_KEY = 'BP5KW6op6Y8M_j-C1iFY-oO1MMdttVNNhwGKVWeDWa85q_BuePfqEV5NIB25_ZhDSeynWlHGUdC3ysuwlvsUJMA'; // À remplacer par ta clé publique

// Fonction d'abonnement aux notifications push
function subscribePush() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

    navigator.serviceWorker.ready.then(function(registration) {
        registration.pushManager.getSubscription().then(function(subscription) {
            if (subscription) {
                // Déjà abonné
                return;
            }
            // Demande la permission à l'utilisateur
            Notification.requestPermission().then(function(permission) {
                if (permission !== 'granted') {
                    console.log("Notifications refusées");
                    return;
                }
                // S'abonner au push
                registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
                }).then(function(sub) {
                    // Envoie l'abonnement au serveur
                    fetch('save_push_subscription.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(sub)
                    });
                });
            });
        });
    });
}

// Outil pour convertir la clé VAPID
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// Lance l'abonnement si l'utilisateur est connecté
<?php if ($isLogged): ?>
subscribePush();
<?php endif; ?>
</script>

</body>
</html>