<?php
// inc/header.php
?>

<header>
    <div class="header-container">
        <div class="logo">
            <a href="index.php">
                <img src="img/palmpay.webp" alt="Task Tube Logo">
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
            <i class="fas fa-dollar-sign"></i>
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
        background: #fff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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
        background: #6e44ff;
        border: 3px solid #fff;
        border-radius: 50%;
        cursor: pointer;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .hamburger-menu-button span {
        width: 20px;
        height: 2px;
        background: #fff;
        position: absolute;
        transition: all 0.3s ease;
    }

    .hamburger-menu-button span::before,
    .hamburger-menu-button span::after {
        content: '';
        width: 20px;
        height: 2px;
        background: #fff;
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
        background: linear-gradient(135deg, #28a745, #20c997);
        border-radius: 12px;
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
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
        color: #fff;
    }

    .notification-popup.notification-show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .notification-content {
        font-size: 15px;
        font-weight: 500;
        display: flex;
        align-items: center;
    }

    .notification-content i {
        margin-right: 10px;
        font-size: 20px;
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
        "@Alex earned $150.00 from video ads! 19min ago",
        "@Jame earned $50.00 from video ads! 20min ago",
        "@Gloria earned $200.00 from video ads! 53min ago",
        "@Sophie earned $75.00 from video ads! 1hr ago",
        "@Mark earned $120.00 from video ads! 2hrs ago"
    ];

    function showNotification(message) {
        const notificationPopup = document.getElementById("notification-popup");
        const messageElement = document.getElementById("notification-message");
        messageElement.textContent = message;

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
