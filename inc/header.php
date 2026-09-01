<?php
// inc/header.php
?>

<!-- PWA Manifest & Meta Tags -->
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#141414">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

<header>
    <div class="header-container">
        <div class="logo">
            <a href="index.php">
                <img src="img/palmpay.webp" alt="Illuminate Tube Logo">
            </a>
        </div>
        
        <div class="header-actions">
            <!-- PWA Install Button (Hidden by default) -->
            <button id="pwa-install-btn" class="pwa-install-btn" style="display: none;">
                <i class="fas fa-download"></i> Install App
            </button>
            <button id="hamburger-menu" data-toggle="ham-navigation" class="hamburger-menu-button">
                <span></span>
            </button>
        </div>
    </div>
</header>

<!-- Notification Popup -->
<div id="notification-container">
    <div id="notification-popup" class="notification-popup">
        <div id="notification-content" class="notification-content">
            <i class="fas fa-coins"></i>
            <p id="notification-message"></p>
        </div>
    </div>
</div>

<style>
    header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        width: 100%;
        background: #141414;
        border-bottom: 1px solid #333;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
        padding: 15px 20px;
        z-index: 1000;
    }

    .header-container {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    /* PWA Install Button Styles */
    .pwa-install-btn {
        background: linear-gradient(45deg, #d4af37, #ffd700);
        color: #000;
        border: none;
        padding: 8px 14px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 0 10px rgba(212, 175, 55, 0.3);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .pwa-install-btn:hover {
        transform: scale(1.05);
        box-shadow: 0 0 15px rgba(212, 175, 55, 0.5);
    }

    .logo img {
        height: 50px;
    }

    .logo a {
        display: inline-block;
        text-decoration: none;
    }

    .hamburger-menu-button {
        width: 40px;
        height: 40px;
        background: linear-gradient(45deg, #d4af37, #ffd700);
        border: 2px solid #000;
        border-radius: 50%;
        cursor: pointer;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 0 10px rgba(212, 175, 55, 0.3);
    }

    .hamburger-menu-button span {
        width: 20px;
        height: 2px;
        background: #000;
        position: absolute;
        transition: all 0.3s ease;
    }

    .hamburger-menu-button span::before,
    .hamburger-menu-button span::after {
        content: '';
        width: 20px;
        height: 2px;
        background: #000;
        position: absolute;
        transition: all 0.3s ease;
    }

    .hamburger-menu-button span::before {
        transform: translateY(-6px);
    }

    .hamburger-menu-button span::after {
        transform: translateY(6px);
    }

    .hamburger-menu-button-close span {
        background: transparent;
    }

    .hamburger-menu-button-close span::before {
        transform: translateY(0) rotate(45deg);
    }

    .hamburger-menu-button-close span::after {
        transform: translateY(0) rotate(-45deg);
    }

    .notification-popup {
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #141414, #1a1a1a);
        border: 1px solid #d4af37;
        border-radius: 12px;
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.8);
        padding: 15px 20px;
        max-width: 320px;
        width: 100%;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-20px);
        transition: all 0.4s ease;
        z-index: 1001;
        display: flex;
        align-items: center;
        color: #e0e0e0;
    }

    .notification-popup.notification-show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .notification-content {
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
    }

    .notification-content i {
        margin-right: 12px;
        font-size: 18px;
        color: #ffd700;
    }

    @media (max-width: 768px) {
        .notification-popup {
            right: 10px;
            max-width: 90%;
        }
    }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.0/jquery.min.js"></script>
<script>
    // Register Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch((err) => {
                console.log('Service Worker registration failed:', err);
            });
        });
    }

    // PWA Install Prompt Logic
    let deferredPrompt;
    const installBtn = document.getElementById('pwa-install-btn');

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        installBtn.style.display = 'flex';
    });

    installBtn.addEventListener('click', async () => {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        if (outcome === 'accepted') {
            installBtn.style.display = 'none';
        }
        deferredPrompt = null;
    });

    window.addEventListener('appinstalled', () => {
        installBtn.style.display = 'none';
        deferredPrompt = null;
    });

    // Hamburger Menu
    const button = document.getElementById('hamburger-menu');
    button.addEventListener('click', function() {
        const span = button.getElementsByTagName('span')[0];
        span.classList.toggle('hamburger-menu-button-close');
        document.getElementById('ham-navigation').classList.toggle('on');
    });

    $('.menu li a').on('click', function() {
        $('#hamburger-menu').click();
    });

    // Notification Logic
    const notificationQueue = [];
    let isNotificationShowing = false;
    const delay = 7000;
    const messages = [
        "@Alex unlocked vault access & earned $150.00! 19min ago",
        "@Jame completed initiation reward $50.00! 20min ago",
        "@Gloria accessed archive & earned $200.00! 53min ago",
        "@Sophie received initiate payload $75.00! 1hr ago",
        "@Mark unlocked vault tier $120.00! 2hrs ago"
    ];

    function showNotification(message) {
        notificationQueue.push(message);
        if (!isNotificationShowing) {
            showNextNotification();
        }
    }

    function showNextNotification() {
        if (notificationQueue.length === 0) {
            isNotificationShowing = false;
            return;
        }

        const message = notificationQueue.shift();
        const notificationPopup = document.getElementById("notification-popup");
        const messageElement = document.getElementById("notification-message");
        messageElement.textContent = message;

        notificationPopup.classList.add("notification-show");
        isNotificationShowing = true;

        setTimeout(() => {
            notificationPopup.classList.remove("notification-show");
            isNotificationShowing = false;
            setTimeout(showNextNotification, 500);
        }, 4000);
    }

    messages.forEach((message, i) => {
        setTimeout(() => showNotification(message), (i + 1) * delay);
    });
</script>
