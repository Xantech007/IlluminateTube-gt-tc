<?php
// inc/header.php
?>

<!-- PWA / Add to Home Screen Meta Tags -->
<link rel="manifest" href="manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Illuminate Tube">
<link rel="apple-touch-icon" href="img/palmpay.webp">
<meta name="theme-color" content="#141414">
<meta name="mobile-web-app-capable" content="yes">

<header>
    <div class="header-container">
        <div class="logo">
            <a href="index.php">
                <img src="img/palmpay.webp" alt="Illuminate Tube Logo">
            </a>
        </div>
        <button id="hamburger-menu" data-toggle="ham-navigation" class="hamburger-menu-button">
            <span></span>
        </button>
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

<!-- ADD TO HOME SCREEN BANNER (UNIVERSAL) -->
<div class="ath-banner" id="athBanner">
    <button class="ath-close" onclick="dismissAth()"><i class="fa-solid fa-xmark"></i></button>
    <div class="ath-header">
        <div class="ath-icon"><i class="fa-solid fa-mobile-screen"></i></div>
        <div>
            <div class="ath-title" id="athTitle">Add Illuminate Tube to Home Screen</div>
            <div class="ath-sub" id="athSub">Open like a real app — faster access</div>
        </div>
    </div>
    <div class="ath-steps" id="athSteps">
        <!-- Steps injected dynamically by JS based on device -->
    </div>
    <div class="ath-actions">
        <button class="ath-btn ath-btn-done" onclick="markAthDone()">
            <i class="fa-solid fa-check"></i> Done
        </button>
        <button class="ath-btn ath-btn-later" onclick="dismissAth()">
            Later
        </button>
    </div>
</div>

<!-- NOTIFICATION PERMISSION BANNER -->
<div class="notify-banner" id="notifyBanner">
    <div class="notify-header">
        <div class="notify-icon"><i class="fa-solid fa-bell"></i></div>
        <div>
            <div class="notify-title">Stay Updated</div>
            <div class="notify-sub">Get instant alerts for rewards, bonuses & new tasks</div>
        </div>
    </div>
    <div class="notify-actions">
        <button class="notify-btn notify-btn-allow" onclick="requestNotify()">
            <i class="fa-solid fa-bell"></i> Allow
        </button>
        <button class="notify-btn notify-btn-deny" onclick="dismissNotify()">
            Not Now
        </button>
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"><i class="fa-solid fa-circle-check"></i><span id="toastMsg">Done</span></div>

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

    /* Notification Popup (Default Header Alerts) */
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

    /* ========== ADD TO HOME SCREEN BANNER ========== */
    .ath-banner {
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%) translateY(120px);
        width: calc(100% - 40px);
        max-width: 400px;
        background: #1e293b;
        border: 1px solid #334155;
        border-radius: 20px;
        padding: 16px 20px;
        z-index: 2000;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        transition: all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        opacity: 0;
    }

    .ath-banner.show {
        transform: translateX(-50%) translateY(0);
        opacity: 1;
    }

    .ath-banner.hidden {
        display: none !important;
    }

    .ath-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }

    .ath-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: linear-gradient(45deg, #d4af37, #ffd700);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #000;
        font-size: 18px;
        box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
    }

    .ath-title {
        font-size: 15px;
        font-weight: 800;
        color: #f8fafc;
    }

    .ath-sub {
        font-size: 12px;
        color: #94a3b8;
        font-weight: 500;
    }

    .ath-steps {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 14px;
    }

    .ath-step {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .ath-step-num {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: rgba(212, 175, 55, 0.15);
        border: 1px solid rgba(212, 175, 55, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffd700;
        font-size: 11px;
        font-weight: 800;
        flex-shrink: 0;
    }

    .ath-step-text {
        font-size: 12px;
        color: #cbd5e1;
        font-weight: 500;
    }

    .ath-step-text strong {
        color: #f8fafc;
        font-weight: 700;
    }

    .ath-actions {
        display: flex;
        gap: 10px;
    }

    .ath-btn {
        flex: 1;
        padding: 12px;
        border-radius: 14px;
        border: none;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .ath-btn-done {
        background: linear-gradient(45deg, #d4af37, #ffd700);
        color: #000;
    }

    .ath-btn-later {
        background: #334155;
        color: #94a3b8;
    }

    .ath-close {
        position: absolute;
        top: 10px;
        right: 14px;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #334155;
        border: none;
        color: #94a3b8;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .ath-close:hover {
        background: #475569;
        color: #f8fafc;
    }

    /* ========== NOTIFICATION PERMISSION BANNER ========== */
    .notify-banner {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%) translateY(-120px);
        width: calc(100% - 40px);
        max-width: 400px;
        background: #1e293b;
        border: 1px solid #334155;
        border-radius: 20px;
        padding: 16px 20px;
        z-index: 2000;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        transition: all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        opacity: 0;
    }

    .notify-banner.show {
        transform: translateX(-50%) translateY(0);
        opacity: 1;
    }

    .notify-banner.hidden {
        display: none !important;
    }

    .notify-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }

    .notify-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: linear-gradient(135deg, #10b981, #34d399);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 16px;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .notify-title {
        font-size: 15px;
        font-weight: 800;
        color: #f8fafc;
    }

    .notify-sub {
        font-size: 12px;
        color: #94a3b8;
        font-weight: 500;
        line-height: 1.5;
    }

    .notify-actions {
        display: flex;
        gap: 10px;
        margin-top: 14px;
    }

    .notify-btn {
        flex: 1;
        padding: 12px;
        border-radius: 14px;
        border: none;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .notify-btn-allow {
        background: linear-gradient(135deg, #10b981, #34d399);
        color: #fff;
    }

    .notify-btn-deny {
        background: #334155;
        color: #94a3b8;
    }

    /* ========== TOAST ========== */
    .toast {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%) translateY(-80px);
        background: #1e293b;
        color: #fff;
        padding: 14px 24px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        font-weight: 600;
        z-index: 2001;
        transition: all 0.4s;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        border: 1px solid #334155;
    }

    .toast.show {
        transform: translateX(-50%) translateY(0);
    }

    .toast i {
        color: #10b981;
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
    // ========== TOAST ==========
    function showToast(msg) {
        const t = document.getElementById("toast");
        document.getElementById("toastMsg").textContent = msg;
        t.classList.add("show");
        setTimeout(function() { t.classList.remove("show"); }, 2500);
    }

    // ========== DEVICE DETECTION ==========
    function getDeviceInfo() {
        const ua = navigator.userAgent;
        const isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
        const isAndroid = /Android/.test(ua);
        const isSafari = /^((?!chrome|android).)*safari/i.test(ua);
        const isChrome = /Chrome/.test(ua) && !/Edg/.test(ua);
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        
        return { isIOS, isAndroid, isSafari, isChrome, isStandalone };
    }

    // ========== ADD TO HOME SCREEN BANNER ==========
    function initAthBanner() {
        if (localStorage.getItem("IlluminateTubeAthDone") === "true") return;
        if (localStorage.getItem("IlluminateTubeAthDismissed") === "true") return;
        
        const device = getDeviceInfo();
        
        // Already installed as app
        if (device.isStandalone) return;
        
        // Only show on mobile browsers
        if (!device.isIOS && !device.isAndroid) return;
        
        const stepsContainer = document.getElementById("athSteps");
        let stepsHTML = "";
        
        if (device.isIOS && device.isSafari) {
            stepsHTML = 
                '<div class="ath-step">' +
                    '<div class="ath-step-num">1</div>' +
                    '<div class="ath-step-text">Tap the <strong>Share</strong> button <i class="fa-solid fa-arrow-up-from-bracket" style="color:#ffd700;"></i> at the bottom</div>' +
                '</div>' +
                '<div class="ath-step">' +
                    '<div class="ath-step-num">2</div>' +
                    '<div class="ath-step-text">Scroll down and tap <strong>"Add to Home Screen"</strong></div>' +
                '</div>' +
                '<div class="ath-step">' +
                    '<div class="ath-step-num">3</div>' +
                    '<div class="ath-step-text">Tap <strong>"Add"</strong> — app icon appears on your home screen</div>' +
                '</div>';
        } else if (device.isAndroid && device.isChrome) {
            stepsHTML = 
                '<div class="ath-step">' +
                    '<div class="ath-step-num">1</div>' +
                    '<div class="ath-step-text">Tap the <strong>Menu</strong> button <i class="fa-solid fa-ellipsis-vertical" style="color:#ffd700;"></i> (3 dots)</div>' +
                '</div>' +
                '<div class="ath-step">' +
                    '<div class="ath-step-num">2</div>' +
                    '<div class="ath-step-text">Tap <strong>"Add to Home Screen"</strong> or <strong>"Install App"</strong></div>' +
                '</div>' +
                '<div class="ath-step">' +
                    '<div class="ath-step-num">3</div>' +
                    '<div class="ath-step-text">Tap <strong>"Add"</strong> or <strong>"Install"</strong> — app icon appears on your home screen</div>' +
                '</div>';
        } else {
            stepsHTML = 
                '<div class="ath-step">' +
                    '<div class="ath-step-num">1</div>' +
                    '<div class="ath-step-text">Open your browser <strong>Menu</strong> or <strong>Share</strong></div>' +
                '</div>' +
                '<div class="ath-step">' +
                    '<div class="ath-step-num">2</div>' +
                    '<div class="ath-step-text">Tap <strong>"Add to Home Screen"</strong> or <strong>"Install"</strong></div>' +
                '</div>' +
                '<div class="ath-step">' +
                    '<div class="ath-step-num">3</div>' +
                    '<div class="ath-step-text">Tap <strong>"Add"</strong> — app icon appears on your home screen</div>' +
                '</div>';
        }
        
        stepsContainer.innerHTML = stepsHTML;
        
        setTimeout(function() {
            const banner = document.getElementById("athBanner");
            if (banner) banner.classList.add("show");
        }, 3000);
    }

    function markAthDone() {
        localStorage.setItem("IlluminateTubeAthDone", "true");
        const banner = document.getElementById("athBanner");
        if (banner) banner.classList.remove("show");
        setTimeout(function() {
            if (banner) banner.classList.add("hidden");
            initNotifyBanner();
        }, 600);
    }

    function dismissAth() {
        localStorage.setItem("IlluminateTubeAthDismissed", "true");
        const banner = document.getElementById("athBanner");
        if (banner) banner.classList.remove("show");
        setTimeout(function() {
            if (banner) banner.classList.add("hidden");
            initNotifyBanner();
        }, 600);
    }

    // ========== NOTIFICATION PERMISSION ==========
    function initNotifyBanner() {
        if (localStorage.getItem("IlluminateTubeNotifyDecided") === "true") return;
        if (!("Notification" in window)) return;
        if (Notification.permission === "granted") {
            localStorage.setItem("IlluminateTubeNotifyDecided", "true");
            return;
        }
        
        setTimeout(function() {
            const banner = document.getElementById("notifyBanner");
            if (banner) banner.classList.add("show");
        }, 1000);
    }

    function requestNotify() {
        if (!("Notification" in window)) {
            showToast("Notifications not supported on this device");
            return;
        }
        
        Notification.requestPermission().then(function(permission) {
            localStorage.setItem("IlluminateTubeNotifyDecided", "true");
            const banner = document.getElementById("notifyBanner");
            if (banner) banner.classList.remove("show");
            setTimeout(function() {
                if (banner) banner.classList.add("hidden");
            }, 600);
            
            if (permission === "granted") {
                showToast("Notifications enabled!");
                registerServiceWorker();
            } else {
                showToast("Notifications disabled.");
            }
        });
    }

    function dismissNotify() {
        localStorage.setItem("IlluminateTubeNotifyDecided", "true");
        const banner = document.getElementById("notifyBanner");
        if (banner) banner.classList.remove("show");
        setTimeout(function() {
            if (banner) banner.classList.add("hidden");
        }, 600);
    }

    // ========== SERVICE WORKER REGISTER ==========
    function registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js')
                .then(function(registration) {
                    console.log('Service Worker registered:', registration);
                })
                .catch(function(error) {
                    console.log('Service Worker registration failed:', error);
                });
        }
    }

    // ========== HAMBURGER MENU ==========
    document.addEventListener("DOMContentLoaded", function() {
        registerServiceWorker();
        initAthBanner();

        const button = document.getElementById('hamburger-menu');
        if (button) {
            button.addEventListener('click', function() {
                const span = button.getElementsByTagName('span')[0];
                span.classList.toggle('hamburger-menu-button-close');
                const nav = document.getElementById('ham-navigation');
                if (nav) nav.classList.toggle('on');
            });
        }

        $('.menu li a').on('click', function() {
            $('#hamburger-menu').click();
        });

        // Notification Queue Logic
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
            if (messageElement && notificationPopup) {
                messageElement.textContent = message;
                notificationPopup.classList.add("notification-show");
                isNotificationShowing = true;

                setTimeout(() => {
                    notificationPopup.classList.remove("notification-show");
                    isNotificationShowing = false;
                    setTimeout(showNextNotification, 500);
                }, 4000);
            }
        }

        messages.forEach((message, i) => {
            setTimeout(() => showNotification(message), (i + 1) * delay);
        });
    });
</script>
