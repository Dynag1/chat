// public/assets/js/pwa.js

let deferredPrompt = null;
let installPromptShown = false;

// Detect iOS
function isIOS() {
    return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
}

// Detect Android
function isAndroid() {
    return /Android/.test(navigator.userAgent);
}

// Detect if running as PWA (standalone mode)
function isInStandaloneMode() {
    return (window.matchMedia('(display-mode: standalone)').matches) || 
           (window.navigator.standalone) || 
           document.referrer.includes('android-app://');
}

// Check if install prompt was dismissed recently (within 1 day now - reduced for testing)
function wasPromptDismissedRecently() {
    const dismissedTime = localStorage.getItem('pwa_install_dismissed');
    if (!dismissedTime) return false;
    const daysSinceDismissed = (Date.now() - parseInt(dismissedTime)) / (1000 * 60 * 60 * 24);
    return daysSinceDismissed < 1;
}

// Show manual install instructions
function showManualInstallInstructions() {
    const isIOSDevice = isIOS();
    const isAndroidDevice = isAndroid();
    
    let instructions = '';
    
    if (isIOSDevice) {
        instructions = `
            <h3>Installation sur iOS</h3>
            <ol>
                <li>Appuyez sur le bouton <strong>Partager</strong> (icône carré avec flèche)</li>
                <li>Faites défiler et appuyez sur <strong>"Sur l'écran d'accueil"</strong></li>
                <li>Appuyez sur <strong>Ajouter</strong></li>
            </ol>
        `;
    } else if (isAndroidDevice) {
        instructions = `
            <h3>Installation sur Android</h3>
            <ol>
                <li>Appuyez sur le menu <strong>⋮</strong> (3 points en haut à droite)</li>
                <li>Appuyez sur <strong>"Installer l'application"</strong> ou <strong>"Ajouter à l'écran d'accueil"</strong></li>
            </ol>
        `;
    } else {
        instructions = `
            <h3>Installation sur ordinateur</h3>
            <ol>
                <li>Cliquez sur l'icône d'installation dans la barre d'adresse (à droite)</li>
                <li>Ou utilisez le menu du navigateur > "Installer..."</li>
            </ol>
        `;
    }
    
    // Create modal
    const overlay = document.createElement('div');
    overlay.id = 'pwa-install-overlay';
    overlay.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.8); display: flex; justify-content: center;
        align-items: center; z-index: 10000;
    `;
    overlay.innerHTML = `
        <div style="background: white; border-radius: 16px; padding: 25px; max-width: 320px; width: 90%; text-align: left;">
            <div style="text-align: center; margin-bottom: 15px;">
                <img src="assets/icons/icon-192x192.png" alt="Atypi Chat" style="width: 60px; height: 60px; border-radius: 12px;">
            </div>
            ${instructions}
            <button id="pwa-close" style="width: 100%; padding: 12px; background: #6c5ce7; color: white; border: none; border-radius: 8px; margin-top: 15px; cursor: pointer;">
                J'ai compris
            </button>
        </div>
    `;
    
    document.body.appendChild(overlay);
    
    document.getElementById('pwa-close').addEventListener('click', () => {
        overlay.remove();
    });
    
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) overlay.remove();
    });
}

// Handle install button click
function handleInstallClick() {
    console.log('Install button clicked, deferredPrompt:', deferredPrompt);
    
    if (deferredPrompt) {
        // Native install prompt available
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then((choiceResult) => {
            console.log('User choice:', choiceResult.outcome);
            deferredPrompt = null;
        });
    } else {
        // Show manual instructions
        showManualInstallInstructions();
    }
}

// Initialize install button
function initInstallButton() {
    const installBtn = document.getElementById('install-btn');
    if (installBtn) {
        // Don't show if already installed
        if (isInStandaloneMode()) {
            installBtn.style.display = 'none';
            return;
        }
        
        installBtn.style.display = 'block';
        installBtn.onclick = handleInstallClick;
    }
}

// Create and show install modal (auto popup)
function showInstallModal(isIOSDevice = false) {
    if (installPromptShown || isInStandaloneMode() || wasPromptDismissedRecently()) return;
    installPromptShown = true;

    const overlay = document.createElement('div');
    overlay.id = 'pwa-install-overlay';
    overlay.innerHTML = `
        <style>
            #pwa-install-overlay {
                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0, 0, 0, 0.7); display: flex; justify-content: center;
                align-items: center; z-index: 10000; backdrop-filter: blur(5px);
            }
            .pwa-modal {
                background: white; border-radius: 20px; padding: 30px;
                max-width: 340px; width: 90%; text-align: center;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            }
            .pwa-modal img { width: 80px; height: 80px; border-radius: 18px; margin-bottom: 20px; }
            .pwa-modal h2 { color: #2d3436; margin: 0 0 10px 0; font-size: 1.4rem; }
            .pwa-modal p { color: #636e72; margin: 0 0 25px 0; font-size: 0.95rem; line-height: 1.5; }
            .pwa-modal .btn-install {
                background: linear-gradient(135deg, #6c5ce7, #a29bfe); color: white;
                border: none; padding: 14px 30px; border-radius: 12px;
                font-size: 1rem; font-weight: 600; cursor: pointer; width: 100%; margin-bottom: 12px;
            }
            .pwa-modal .btn-later {
                background: transparent; color: #636e72; border: none;
                padding: 10px; font-size: 0.9rem; cursor: pointer; width: 100%;
            }
        </style>
        <div class="pwa-modal">
            <img src="assets/icons/icon-192x192.png" alt="Atypi Chat">
            <h2>Installer Atypi Chat</h2>
            <p>Ajoutez l'application sur votre écran d'accueil pour un accès rapide !</p>
            <button class="btn-install" id="pwa-modal-install">📲 Installer</button>
            <button class="btn-later" id="pwa-modal-later">Plus tard</button>
        </div>
    `;

    document.body.appendChild(overlay);

    document.getElementById('pwa-modal-install').addEventListener('click', () => {
        overlay.remove();
        installPromptShown = false;
        handleInstallClick();
    });

    document.getElementById('pwa-modal-later').addEventListener('click', () => {
        localStorage.setItem('pwa_install_dismissed', Date.now().toString());
        overlay.remove();
        installPromptShown = false;
    });

    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            localStorage.setItem('pwa_install_dismissed', Date.now().toString());
            overlay.remove();
            installPromptShown = false;
        }
    });
}

// Listen for beforeinstallprompt
window.addEventListener('beforeinstallprompt', (e) => {
    console.log('✅ beforeinstallprompt event fired!');
    e.preventDefault();
    deferredPrompt = e;
    
    // Update button to show native install is available
    const installBtn = document.getElementById('install-btn');
    if (installBtn) {
        installBtn.textContent = '📲 Installer l\'App';
        installBtn.style.background = '#27ae60';
    }

    // Show popup if not dismissed
    if (!wasPromptDismissedRecently() && !isInStandaloneMode()) {
        setTimeout(() => showInstallModal(false), 2000);
    }
});

// On page load
window.addEventListener('load', () => {
    console.log('=== PWA Debug Info ===');
    console.log('User Agent:', navigator.userAgent);
    console.log('isIOS:', isIOS());
    console.log('isAndroid:', isAndroid());
    console.log('isInStandaloneMode:', isInStandaloneMode());
    console.log('Protocol:', location.protocol);
    console.log('Service Worker support:', 'serviceWorker' in navigator);
    
    // Initialize install button
    initInstallButton();
    
    // Show popup for iOS if not dismissed
    if (isIOS() && !isInStandaloneMode() && !wasPromptDismissedRecently()) {
        setTimeout(() => showInstallModal(true), 2000);
    }
});

// Track successful install
window.addEventListener('appinstalled', () => {
    console.log('✅ PWA was installed successfully!');
    deferredPrompt = null;
    localStorage.removeItem('pwa_install_dismissed');
    
    const installBtn = document.getElementById('install-btn');
    if (installBtn) installBtn.style.display = 'none';
    
    const overlay = document.getElementById('pwa-install-overlay');
    if (overlay) overlay.remove();
});

// Register service worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js')
            .then((registration) => {
                console.log('✅ ServiceWorker registered, scope:', registration.scope);
            })
            .catch((err) => {
                console.log('❌ ServiceWorker registration failed:', err);
            });
    });
} else {
    console.log('❌ Service Worker not supported');
}

// Debug functions
window.pwaDebug = {
    reset: function() {
        localStorage.removeItem('pwa_install_dismissed');
        if ('caches' in window) {
            caches.keys().then(names => {
                names.forEach(name => caches.delete(name));
            });
        }
        navigator.serviceWorker.getRegistrations().then(regs => {
            regs.forEach(reg => reg.unregister());
        });
        console.log('PWA reset complete. Refreshing...');
        setTimeout(() => location.reload(), 500);
    },
    status: function() {
        console.log('deferredPrompt:', deferredPrompt ? 'Available' : 'Not available');
        console.log('Standalone:', isInStandaloneMode());
        console.log('Dismissed recently:', wasPromptDismissedRecently());
    },
    showPopup: function() {
        installPromptShown = false;
        showInstallModal(isIOS());
    }
};

console.log('💡 Debug: Use pwaDebug.reset() to clear all PWA data, pwaDebug.status() for info, pwaDebug.showPopup() to test popup');
