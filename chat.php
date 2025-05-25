<?php
session_set_cookie_params([
    'lifetime' => 60*60*24*30,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
require 'config.php';
check_auth();
$userPseudo = htmlspecialchars($_SESSION['user_pseudo']);
$userId = $_SESSION['user_id'];
?>

<html lang="fr">
<head>
    <meta charset="UTF-8">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#ffd700">
    <title>Chat Pirate</title>
    <link rel="icon" type="image/png" href="icon-192.png">
    <meta name="viewport" content="width=device-width, initial-scale=1, interactive-widget=resizes-content">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Pirata+One&display=swap" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            min-height: 100dvh;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            background: linear-gradient(120deg, #1e202c 0%, #232946 100%);
            color: #fff;
        }
        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: url('https://www.transparenttextures.com/patterns/pirate.png');
            opacity: 0.06;
            z-index: 0;
            pointer-events: none;
        }
        .chat-container {
            width: 100%;
            max-width: 420px;
            height: 100dvh;
            min-height: 0;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            padding: 0;
            position: relative;
            z-index: 1;
        }
        .chat-header, .chat-footer {
            flex: 0 0 auto;
        }
        .pirate-title {
            font-family: 'Pirata One', cursive, sans-serif;
            color: #ffd700;
            letter-spacing: 1px;
            text-shadow: 0 2px 8px #0004;
            font-size: 1.3rem;
            margin-bottom: 0;
        }
        #status {
            font-style: italic;
            margin-bottom: 6px;
        }
        #chat-box {
            flex: 1 1 auto;
            min-height: 0;
            max-height: none;
            overflow-y: auto;
            background: #111;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 8px;
            border: 1px solid #333;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .bubble {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 18px;
            min-width: 40px;
            max-width: 100%;
            width: fit-content;
            font-size: 1rem;
            position: relative;
            margin-bottom: 2px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            opacity: 0;
            transform: translateY(10px);
            animation: pop-in 0.2s forwards;
            word-break: normal;
            overflow-wrap: break-word;
            white-space: pre-line;
        }
        @keyframes pop-in {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .msg-me { align-self: flex-end; }
        .msg-me .bubble {
            background: linear-gradient(135deg, #ffd700 60%, #fffbe0 100%);
            color: #333;
            border-bottom-right-radius: 4px;
        }
        .msg-other { align-self: flex-start; }
        .msg-other .bubble {
            background: linear-gradient(135deg, #2e8b57 60%, #7fffd4 100%);
            color: #fff;
            border-bottom-left-radius: 4px;
        }
        .btn, .btn-sm {
            font-size: 0.95rem !important;
            padding: 6px 14px !important;
            border-radius: 10px !important;
        }
        .btn-primary, .btn-danger, .btn-warning, .btn-secondary {
            min-width: 80px;
        }
        .input-group .form-control {
            font-size: 1rem;
            padding: 8px 10px;
        }
        .chat-actions {
            background: #222;
            z-index: 10;
        }
        @media (max-width: 600px) {
            .chat-container {
                max-width: 100vw;
                min-width: 0;
                padding: 0 2px;
                height: 100dvh;
            }
            #chat-box {
                padding: 6px;
                margin-bottom: 0;
            }
            .btn, .btn-sm {
                font-size: 0.9rem !important;
                padding: 5px 7px !important;
                min-width: 60px;
            }
            .chat-actions {
                position: sticky;
                bottom: 0;
                left: 0;
                width: 100vw;
                margin-bottom: 0;
                padding-bottom: env(safe-area-inset-bottom, 6px);
                box-shadow: 0 -2px 12px rgba(0,0,0,0.12);
            }
        }
        #chat-form .form-control {
            min-height: 40px;
            max-height: 80px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
<div class="chat-container">
    <div class="chat-header d-flex flex-column align-items-center mb-2 pt-3 px-2">
        <h2 class="pirate-title">Chat Pirate</h2>
        <div class="chat-actions d-flex gap-2 px-2 mb-2 w-100">
            <a href="index.php" class="btn btn-warning btn-sm flex-fill">Accueil</a>
            <button id="leave-btn" class="btn btn-danger btn-sm flex-fill" style="display:none;">Quitter</button>
            <button id="report-btn" class="btn btn-warning btn-sm flex-fill" style="display:none;">Signaler</button>
            
        </div>
    </div>
    <div id="status">Recherche d'un partenaire...</div>
    <div id="chat-box"></div>
    <form id="chat-form" class="chat-footer input-group mb-2 px-2" style="display:none;">
        <input type="text" id="message" class="form-control" placeholder="Votre message..." autocomplete="off" required>
        <button class="btn btn-primary btn-sm" type="submit">Envoyer</button>
    </form>
</div>

<!-- Modal de signalement -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="report-form" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="reportModalLabel"><font color="black">Signaler ce pirate</font></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <label for="report-reason" class="form-label"><font color="black">Expliquez la raison du signalement :</font></label>
        <textarea id="report-reason" class="form-control" required rows="4" maxlength="500"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-warning btn-sm">Envoyer</button>
      </div>
    </form>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
if ("Notification" in window && Notification.permission !== "granted") {
    Notification.requestPermission();
}
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js').then(function(reg) {
        // SW enregistré
    });
}


let pairId = null;
let partnerId = null;
let polling = null;
const myUserId = <?php echo json_encode($userId); ?>;

function scrollChat() {
    const chatBox = document.getElementById('chat-box');
    chatBox.scrollTop = chatBox.scrollHeight;
}

function findPartner() {
    console.log("[findPartner] appelée");
    document.getElementById('status').textContent = "Recherche d'un partenaire...";

    fetch('actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=find_partner',
        credentials: 'same-origin'
    })
    .then(resp => {
        console.log("[findPartner] Réponse reçue du serveur", resp);
        return resp.json();
    })
    .then(data => {
        console.log("[findPartner] Données JSON reçues", data);
        if (data.success && data.pair_id) {
            pairId = data.pair_id;
            partnerId = data.partner_id;
            document.getElementById('status').textContent = "Partenaire trouvé ! Démarrez la discussion.";
            document.getElementById('chat-form').style.display = '';
            document.getElementById('leave-btn').style.display = '';
            startPolling();
            showReportBtn();
            console.log("[findPartner] Partenaire trouvé, pairId:", pairId, "partnerId:", partnerId);
        } else if (data.message) {
            document.getElementById('status').textContent = "En attente d'un partenaire...";
            console.log("[findPartner] Toujours en attente, on relance dans 2s");
            setTimeout(findPartner, 2000);
        } else {
            document.getElementById('status').textContent = "Erreur lors de la recherche.";
            console.error("[findPartner] Erreur inattendue dans la réponse", data);
        }
    })
    .catch(err => {
        document.getElementById('status').textContent = "Erreur réseau.";
        console.error("[findPartner] Erreur réseau ou JS", err);
    });
}


let lastMsgId = 0;
let firstFetch = true;
let displayedBroadcastIds = [];

function fetchMessages() {
    if (!pairId) return;
    fetch('get_messages.php?pair_id=' + pairId + '&last_id=' + lastMsgId)
    .then(resp => resp.json())
    .then(data => {
        if (data.ended) {
            alert("L'autre pirate a quitté le chat. Vous allez être redirigé vers l'accueil.");
            window.location.href = "index.php";
            return;
        }
        const chatBox = document.getElementById('chat-box');

        // 1. Affichage des messages broadcast (ajout uniquement des nouveaux, sans suppression)
        if (data.broadcasts && data.broadcasts.length > 0) {
            // Affiche les broadcasts du plus ancien au plus récent
            data.broadcasts.slice().reverse().forEach(bc => {
                if (!displayedBroadcastIds.includes(bc.id)) {
                    let wrapper = document.createElement('div');
                    wrapper.className = 'broadcast-msg';
                    let bubble = document.createElement('div');
                    bubble.className = 'bubble';
                    bubble.style.background = 'linear-gradient(135deg, #ff9800 60%, #fffbe0 100%)';
                    bubble.style.color = '#222';
                    bubble.textContent = "[Annonce] " + bc.message;
                    wrapper.appendChild(bubble);
                    // Ajoute en haut du chat
                    chatBox.insertBefore(wrapper, chatBox.firstChild);
                    displayedBroadcastIds.push(bc.id);
                }
            });
        }

        // 2. Affichage des messages privés (inchangé)
        if (data.success && data.messages.length > 0) {
            let maxId = lastMsgId;
            data.messages.forEach(msg => {
                if (msg.id > lastMsgId) {
                    let wrapper = document.createElement('div');
                    wrapper.className = (msg.sender_id == myUserId) ? 'msg-me' : 'msg-other';
                    let bubble = document.createElement('div');
                    bubble.className = 'bubble';
                    bubble.textContent = msg.message;
                    wrapper.appendChild(bubble);
                    chatBox.appendChild(wrapper);

                    if (!firstFetch && msg.sender_id != myUserId && Notification.permission === "granted") {
                        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                            navigator.serviceWorker.controller.postMessage({
                                title: "Nouveau message",
                                options: { body: msg.message, icon: "/icon-192.png" }
                            });
                        } else {
                            new Notification("Nouveau message", { body: msg.message, icon: "/icon-192.png" });
                        }
                    }
                    if (msg.id > maxId) maxId = msg.id;
                }
            });
            lastMsgId = maxId;
            scrollChat();
        }
        if (firstFetch) firstFetch = false;
    });
}




document.getElementById('chat-form').addEventListener('submit', function(e) {
    e.preventDefault();
    let msgInput = document.getElementById('message');
    let msg = msgInput.value.trim();
    if (!msg || !pairId) return;
    msgInput.value = ''; // Vider le champ immédiatement
    fetch('actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=send_message&pair_id=' + encodeURIComponent(pairId) + '&message=' + encodeURIComponent(msg),
        credentials: 'same-origin'
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.success) {
            fetchMessages();
        }
    });
});

document.getElementById('leave-btn').addEventListener('click', function() {
    if (!pairId) return;
    fetch('actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=leave_chat&pair_id=' + encodeURIComponent(pairId),
        credentials: 'same-origin'
    })
    .then(resp => resp.json())
    .then(data => {
        location.reload();
    });
});

function startPolling() {
    if (polling) clearInterval(polling);
    fetchMessages();
    polling = setInterval(fetchMessages, 1500);
}

function showReportBtn() {
    if (partnerId) {
        document.getElementById('report-btn').style.display = '';
    }
}

document.getElementById('report-btn').addEventListener('click', function() {
    let modal = new bootstrap.Modal(document.getElementById('reportModal'));
    document.getElementById('report-reason').value = '';
    modal.show();
});

document.getElementById('report-form').addEventListener('submit', function(e) {
    e.preventDefault();
    let reason = document.getElementById('report-reason').value.trim();
    if (!reason) return;
    fetch('actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=report_user&pair_id=' + encodeURIComponent(pairId) + '&reason=' + encodeURIComponent(reason),
        credentials: 'same-origin'
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.success) {
            alert("Merci, votre signalement a bien été transmis à la modération.");
            let modal = bootstrap.Modal.getInstance(document.getElementById('reportModal'));
            modal.hide();
            document.getElementById('report-btn').disabled = true;
        } else {
            alert("Erreur lors du signalement.");
        }
    });
});
window.addEventListener('beforeunload', function() {
    navigator.sendBeacon('actions.php', 'action=cancel_search');
});

findPartner();
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

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/service-worker.js').then(function(reg) {
    if (navigator.serviceWorker.controller) return;
    navigator.serviceWorker.addEventListener("controllerchange", function() {
      // La page est maintenant contrôlée par le SW
    });
  });
}
</script>
<script>
const VAPID_PUBLIC_KEY = 'BP5KW6op6Y8M_j-C1iFY-oO1MMdttVNNhwGKVWeDWa85q_BuePfqEV5NIB25_ZhDSeynWlHGUdC3ysuwlvsUJMA';

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

<?php if (isset($_SESSION['user_id'])): ?>
if ('serviceWorker' in navigator && 'PushManager' in window) {
    navigator.serviceWorker.ready.then(function(registration) {
        registration.pushManager.getSubscription().then(function(subscription) {
            if (subscription) {
                // Déjà abonné
                return;
            }
            Notification.requestPermission().then(function(permission) {
                if (permission !== 'granted') return;
                registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
                }).then(function(sub) {
                    fetch('save_push_subscription.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(sub),
                        credentials: 'same-origin'
                    });
                });
            });
        });
    });
}
<?php endif; ?>
const pushBtn = document.getElementById('push-btn');
const VAPID_PUBLIC_KEY = 'BP5KW6op6Y8M_j-C1iFY-oO1MMdttVNNhwGKVWeDWa85q_BuePfqEV5NIB25_ZhDSeynWlHGUdC3ysuwlvsUJMA';

// Conversion de la clé VAPID
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// Vérifier l'état du bouton au chargement
document.addEventListener('DOMContentLoaded', async () => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        pushBtn.style.display = 'none';
        return;
    }
    const reg = await navigator.serviceWorker.ready;
    const sub = await reg.pushManager.getSubscription();
    if (Notification.permission === "granted" && sub) {
        pushBtn.textContent = "Notifications activées";
        pushBtn.disabled = true;
        pushBtn.classList.remove("btn-primary");
        pushBtn.classList.add("btn-success");
    }
});

// Gestion du clic sur le bouton
pushBtn.addEventListener('click', async () => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        alert("Notifications push non supportées sur ce navigateur.");
        return;
    }
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        alert("Vous devez autoriser les notifications pour les recevoir.");
        return;
    }
    const reg = await navigator.serviceWorker.ready;
    let sub = await reg.pushManager.getSubscription();
    if (!sub) {
        sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
        });
        // Envoi de l'abonnement au serveur
        await fetch('save_push_subscription.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(sub),
            credentials: 'same-origin'
        });
    }
    pushBtn.textContent = "Notifications activées";
    pushBtn.disabled = true;
    pushBtn.classList.remove("btn-primary");
    pushBtn.classList.add("btn-success");
});

</script>

</body>
</html>
