// public/assets/js/chat.js

// Get CSRF token from meta tag or cookie
let csrfToken = '';
function getCSRFToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag) {
        csrfToken = metaTag.getAttribute('content');
    }
    return csrfToken;
}

// Initialize CSRF token
getCSRFToken();

// XSS Protection: Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Maximum message length
const MAX_MESSAGE_LENGTH = 1000;

let currentChatId = null;
let lastMessageId = 0;
let partnerId = null;
let pollInterval = null;
let isSearching = false;

const messagesDiv = document.getElementById('messages');
const inputArea = document.getElementById('input-area');
const messageInput = document.getElementById('message-input');
const sendBtn = document.getElementById('send-btn');
const nextBtn = document.getElementById('next-btn');
const reportBtn = document.getElementById('report-btn');
const leaveBtn = document.getElementById('leave-btn');
const notifyBtn = document.getElementById('notify-btn');

// Initial check
checkStatus();
checkNotificationPermission();

function checkNotificationPermission() {
    if ('Notification' in window) {
        if (Notification.permission === 'default') {
            notifyBtn.style.display = 'inline-block';
        } else if (Notification.permission === 'granted') {
            notifyBtn.style.display = 'none';
        }
    }
}

notifyBtn.addEventListener('click', () => {
    Notification.requestPermission().then(permission => {
        if (permission === 'granted') {
            notifyBtn.style.display = 'none';
            new Notification("Notifications activées", {
                body: "Vous serez maintenant notifié des nouveaux messages !",
                icon: 'assets/icons/icon-192x192.png'
            });
        }
    });
});

function checkStatus() {
    fetch('api/chat.php?action=check_status')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'in_chat') {
                startChat(data.chat_id);
            }
        });
}

// Request notification permission
function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission !== 'granted') {
        Notification.requestPermission();
    }
}

nextBtn.addEventListener('click', () => {
    requestNotificationPermission();
    if (isSearching) return;

    // UI Updates
    isSearching = true;
    nextBtn.disabled = true;
    nextBtn.textContent = 'Recherche...';
    messagesDiv.innerHTML = '<div class="system-message">Recherche d\'un inconnu...</div>';
    inputArea.style.display = 'none';
    reportBtn.style.display = 'none';
    blockBtn.style.display = 'none';
    leaveBtn.style.display = 'none';

    // Stop previous polling
    if (pollInterval) clearInterval(pollInterval);
    currentChatId = null;
    lastMessageId = 0;

    // Call API
    fetch('api/chat.php?action=find_match', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ force_new: true, csrf_token: csrfToken })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                startChat(data.chat_id, data.partner_id);
                sendNotification("Nouveau partenaire trouvé !", "Vous discutez maintenant avec un inconnu.");
            } else {
                if (data.message === 'Waiting for partner...') {
                    messagesDiv.innerHTML = '<div class="system-message">En attente d\'un partenaire...</div>';
                    setTimeout(() => {
                        retryMatch();
                    }, 2000);
                } else if (data.message) {
                    messagesDiv.innerHTML = `<div class="system-message" style="color:red">Erreur : ${data.message}</div>`;
                    isSearching = false;
                    nextBtn.disabled = false;
                    nextBtn.textContent = 'Chercher';
                } else {
                    setTimeout(() => {
                        retryMatch();
                    }, 2000);
                }
            }
        })
        .catch(err => {
            console.error(err);
            isSearching = false;
            nextBtn.disabled = false;
            nextBtn.textContent = 'Chercher';
            messagesDiv.innerHTML += '<div class="system-message" style="color:red">Erreur de connexion. Réessayez.</div>';
        });
});

function retryMatch() {
    if (!isSearching) return;

    fetch('api/chat.php?action=find_match', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ force_new: false, csrf_token: csrfToken })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                startChat(data.chat_id, data.partner_id);
                sendNotification("Nouveau partenaire trouvé !", "Vous discutez maintenant avec un inconnu.");
            } else {
                setTimeout(retryMatch, 2000);
            }
        });
}

function startChat(chatId, pid) {
    isSearching = false;
    currentChatId = chatId;
    partnerId = pid;

    nextBtn.style.display = 'none';
    nextBtn.disabled = false;
    nextBtn.textContent = 'Chercher';
    messagesDiv.innerHTML = '<div class="system-message">Vous discutez maintenant avec un inconnu ! Dites bonjour !</div>';
    inputArea.style.display = 'flex';
    reportBtn.style.display = 'inline-block';
    blockBtn.style.display = 'inline-block';
    leaveBtn.style.display = 'inline-block';

    pollInterval = setInterval(pollMessages, 2000);
}

function pollMessages() {
    // Double-check that we still have an active chat
    if (!currentChatId) {
        console.log("pollMessages: No current chat ID, stopping");
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
        return;
    }

    fetch(`api/chat.php?action=get_messages&chat_id=${currentChatId}&last_id=${lastMessageId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                data.messages.forEach(msg => {
                    appendMessage(msg);
                    lastMessageId = msg.id;
                    if (msg.sender_id != USER_ID) {
                        sendNotification("Nouveau Message", "Inconnu : " + msg.content);
                    }
                });
            } else if (data.status === 'ended') {
                console.log("Chat ended by partner");
                endChatUI();
            }
        })
        .catch(err => {
            console.error("Error polling messages:", err);
        });
}

function appendMessage(msg) {
    const div = document.createElement('div');
    div.className = `message ${msg.sender_id == USER_ID ? 'sent' : 'received'}`;
    // XSS Protection: escape HTML content
    div.textContent = msg.content;
    messagesDiv.appendChild(div);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function endChatUI(showDefaultMessage = true) {
    console.log("endChatUI called, stopping polling");
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
    currentChatId = null;
    lastMessageId = 0;
    partnerId = null;
    inputArea.style.display = 'none';
    reportBtn.style.display = 'none';
    blockBtn.style.display = 'none';
    leaveBtn.style.display = 'none';
    nextBtn.style.display = 'inline-block';
    if (showDefaultMessage) {
        messagesDiv.innerHTML += '<div class="system-message">L\'inconnu s\'est déconnecté. Cliquez sur Suivant pour trouver un nouveau partenaire.</div>';
    }
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
    console.log("endChatUI complete");
}

function sendNotification(title, body) {
    if ('Notification' in window && Notification.permission === 'granted') {
        // Only send if page is hidden or it's a major event like match
        if (document.hidden || title.includes("trouvé")) {
            // Use Service Worker if available (better for PWA/Android)
            if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                navigator.serviceWorker.ready.then(registration => {
                    registration.showNotification(title, {
                        body: body,
                        icon: 'assets/icons/icon-192x192.png',
                        vibrate: [200, 100, 200]
                    });
                });
            } else {
                // Fallback to standard API
                new Notification(title, {
                    body: body,
                    icon: 'assets/icons/icon-192x192.png'
                });
            }
        }
    }
}

sendBtn.addEventListener('click', sendMessage);
messageInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') sendMessage();
});

function sendMessage() {
    const content = messageInput.value.trim();
    if (!content || !currentChatId) return;

    // Validate message length
    if (content.length > MAX_MESSAGE_LENGTH) {
        alert(`Message trop long. Maximum ${MAX_MESSAGE_LENGTH} caractères.`);
        return;
    }

    messageInput.value = '';

    fetch('api/chat.php?action=send_message', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            chat_id: currentChatId,
            content: content,
            csrf_token: csrfToken
        })
    });
}

reportBtn.addEventListener('click', () => {
    if (!confirm("Êtes-vous sûr de vouloir signaler cet utilisateur ?")) return;

    const reason = prompt("Raison du signalement :");
    if (!reason) return;

    fetch('api/action.php?action=report', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            reported_id: partnerId,
            reason: reason,
            csrf_token: csrfToken
        })
    })
        .then(res => res.json())
        .then(data => {
            alert("Utilisateur signalé.");
            // Don't automatically skip, let them decide to block or leave
        });
});

const blockBtn = document.getElementById('block-btn');
blockBtn.addEventListener('click', () => {
    if (!confirm("Êtes-vous sûr de vouloir bloquer cet utilisateur ? Vous ne serez plus mis en relation avec lui.")) return;

    fetch('api/action.php?action=block', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            blocked_id: partnerId,
            csrf_token: csrfToken
        })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("Utilisateur bloqué.");
                // Reset UI and find next match
                endChatUI(false); // Don't show default message
                messagesDiv.innerHTML += '<div class="system-message">Utilisateur bloqué.</div>';
                nextBtn.click();
            } else {
                alert("Erreur : " + data.message);
            }
        });
});

leaveBtn.addEventListener('click', () => {
    console.log("Leave button clicked. Current chat ID:", currentChatId);

    if (!confirm("Êtes-vous sûr de vouloir quitter cette conversation ?")) {
        console.log("User cancelled leave");
        return;
    }

    console.log("User confirmed leave. Stopping polling...");

    // Stop polling immediately to prevent race conditions
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
        console.log("Polling stopped");
    }

    // Save the chat ID before clearing it
    const chatIdToSend = currentChatId;
    console.log("Chat ID to send to server:", chatIdToSend);

    // Reset local state immediately
    currentChatId = null;
    lastMessageId = 0;
    partnerId = null;
    console.log("Local state reset");

    // Hide UI elements
    inputArea.style.display = 'none';
    reportBtn.style.display = 'none';
    leaveBtn.style.display = 'none';
    console.log("UI elements hidden");

    // Make the API call
    console.log("Calling end_chat API...");
    fetch('api/chat.php?action=end_chat', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            chat_id: chatIdToSend,
            csrf_token: csrfToken
        })
    })
        .then(res => {
            console.log("API response received:", res.status);
            return res.json();
        })
        .then(data => {
            console.log("End chat API response:", data);
            messagesDiv.innerHTML += '<div class="system-message">Vous avez quitté la conversation.</div>';
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        })
        .catch(err => {
            console.error("Error ending chat:", err);
            messagesDiv.innerHTML += '<div class="system-message">Vous avez quitté la conversation.</div>';
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        });
});
