<?php
session_start();
require_once '../database/conn.php';

// Set time zone to UTC
date_default_timezone_set('UTC');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log('No user_id in session, redirecting to signin', 3, '../debug.log');
    header('Location: ../signin.php');
    exit;
}

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT name, balance, COALESCE(country, '') AS country FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        error_log('User not found for ID: ' . $_SESSION['user_id'], 3, '../debug.log');
        session_destroy();
        header('Location: ../signin.php?error=user_not_found');
        exit;
    }
    $username = htmlspecialchars($user['name']);
    $balance = number_format($user['balance'], 2);
    $user_country = htmlspecialchars($user['country']);
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage(), 3, '../debug.log');
    session_destroy();
    header('Location: ../signin.php?error=database');
    exit;
}

// Fetch region settings for labels
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(ch_name, 'Bank Name') AS ch_name, 
               COALESCE(ch_value, 'Bank Account') AS ch_value, 
               COALESCE(channel, 'Bank') AS channel_label
        FROM region_settings 
        WHERE country = ?
    ");
    $stmt->execute([$user_country]);
    $region_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($region_settings) {
        $ch_name = htmlspecialchars($region_settings['ch_name']);
        $ch_value = htmlspecialchars($region_settings['ch_value']);
        $channel_label = htmlspecialchars($region_settings['channel_label']);
    } else {
        $ch_name = 'Bank Name';
        $ch_value = 'Bank Account';
        $channel_label = 'Bank';
        error_log('No region settings found for country: ' . $user_country, 3, '../debug.log');
    }
} catch (PDOException $e) {
    error_log('Region settings fetch error for user ID: ' . $_SESSION['user_id'] . ': ' . $e->getMessage(), 3, '../debug.log');
    $ch_name = 'Bank Name';
    $ch_value = 'Bank Account';
    $channel_label = 'Bank';
}

// Fetch Telegram username/link from c_support table
$telegram_raw = '';
$telegram_clean = '';
try {
    $stmt = $pdo->prepare("SELECT telegram FROM c_support LIMIT 1");
    $stmt->execute();
    $support_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($support_data && !empty($support_data['telegram'])) {
        $telegram_raw = trim($support_data['telegram']);
        // Strip out leading @ or url parts to get clean username for t.me link
        $telegram_clean = ltrim($telegram_raw, '@');
        $telegram_clean = str_replace(['https://t.me/', 'http://t.me/'], '', $telegram_clean);
    }
} catch (PDOException $e) {
    error_log('Telegram support fetch error: ' . $e->getMessage(), 3, '../debug.log');
}

// Fallback in case table/record is not set yet
if (empty($telegram_raw)) {
    $telegram_raw = '@TaskTubeSupport';
    $telegram_clean = 'TaskTubeSupport';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Contact Task Tube's support team 24/7 via Telegram for assistance with your account, login, or general inquiries." />
    <meta name="keywords" content="Task Tube, contact support, earn money online, watch ads, customer service, telegram support" />
    <meta name="author" content="Task Tube" />
    <title>Contact Support | Task Tube</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-color: #f7f9fc;
            --gradient-bg: linear-gradient(135deg, #f7f9fc, #e5e7eb);
            --card-bg: #ffffff;
            --text-color: #1a1a1a;
            --subtext-color: #6b7280;
            --border-color: #d1d5db;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --accent-color: #22c55e;
            --accent-hover: #16a34a;
            --menu-bg: #1a1a1a;
            --menu-text: #ffffff;
            --status-completed: #22c55e;
            --status-pending: #eab308;
            --status-rejected: #ef4444;
        }

        [data-theme="dark"] {
            --bg-color: #1f2937;
            --gradient-bg: linear-gradient(135deg, #1f2937, #374151);
            --card-bg: #2d3748;
            --text-color: #e5e7eb;
            --subtext-color: #9ca3af;
            --border-color: #4b5563;
            --shadow-color: rgba(0, 0, 0, 0.3);
            --accent-color: #34d399;
            --accent-hover: #22c55e;
            --menu-bg: #111827;
            --menu-text: #e5e7eb;
            --status-completed: #34d399;
            --status-pending: #facc15;
            --status-rejected: #f87171;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            padding-bottom: 100px;
            transition: all 0.3s ease;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
            position: relative;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 0;
            animation: slideIn 0.5s ease-out;
        }

        .header img {
            width: 64px;
            height: 64px;
            margin-right: 16px;
            border-radius: 8px;
        }

        .header-text h1 {
            font-size: 26px;
            font-weight: 700;
        }

        .header-text p {
            font-size: 16px;
            color: var(--subtext-color);
            margin-top: 4px;
        }

        .theme-toggle {
            background: var(--accent-color);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .theme-toggle:hover {
            background: var(--accent-hover);
            transform: scale(1.02);
        }

        .contact-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 6px 16px var(--shadow-color);
            margin: 24px 0;
            animation: slideIn 0.5s ease-out 0.6s backwards;
        }

        .contact-section h2 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--accent-color);
        }

        .contact-section p {
            font-size: 16px;
            color: var(--subtext-color);
            line-height: 1.6;
            margin-bottom: 20px;
            text-align: left;
        }

        .contact-info p strong {
            color: var(--text-color);
            font-weight: 600;
        }

        .contact-info p a {
            color: var(--accent-color);
            text-decoration: none;
        }

        .contact-info p a:hover {
            text-decoration: underline;
        }

        .contact-section ul {
            list-style: none;
            padding: 0;
            margin-bottom: 20px;
            text-align: left;
        }

        .contact-section ul li {
            font-size: 16px;
            color: var(--subtext-color);
            line-height: 1.6;
            margin-bottom: 10px;
            position: relative;
            padding-left: 30px;
        }

        .contact-section ul li::before {
            content: '\f058';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: var(--accent-color);
            position: absolute;
            left: 0;
            top: 2px;
        }

        .cta-banner {
            background: var(--gradient-bg);
            color: var(--text-color);
            text-align: center;
            padding: 60px 20px;
            border-radius: 16px;
            margin: 40px 0;
            box-shadow: 0 6px 16px var(--shadow-color);
        }

        .cta-banner h2 {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .cta-banner .btn {
            background-color: var(--accent-color);
            color: #fff;
            padding: 15px 40px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 50px;
            text-decoration: none;
            transition: background-color 0.3s ease;
            display: inline-block;
        }

        .cta-banner .btn:hover {
            background-color: var(--accent-hover);
        }

        .signup-link .btn {
            background-color: var(--accent-color);
            color: #fff;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 500;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .signup-link .btn:hover {
            background-color: var(--accent-hover);
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--card-bg);
            color: var(--text-color);
            padding: 16px 24px;
            border-radius: 12px;
            border: 2px solid var(--accent-color);
            box-shadow: 0 4px 12px var(--shadow-color), 0 0 8px var(--accent-color);
            z-index: 1000;
            display: flex;
            align-items: center;
            animation: slideInRight 0.5s ease-out, fadeOut 0.5s ease-out 3s forwards;
            max-width: 300px;
            transition: transform 0.2s ease;
        }

        .notification:hover {
            transform: scale(1.05);
        }

        .notification::before {
            content: '🔒';
            font-size: 1.2rem;
            margin-right: 12px;
            color: var(--accent-color);
        }

        .notification.error::before {
            content: '⚠️';
        }

        .notification span {
            font-size: 14px;
            font-weight: 500;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }

        .bottom-menu {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: var(--menu-bg);
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 14px 0;
            box-shadow: 0 -2px 8px var(--shadow-color);
        }

        .bottom-menu a,
        .bottom-menu button {
            color: var(--menu-text);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 10px 18px;
            transition: color 0.3s ease;
            background: none;
            border: none;
            cursor: pointer;
        }

        .bottom-menu a.active,
        .bottom-menu a:hover,
        .bottom-menu button:hover {
            color: var(--accent-color);
        }

        #gradient {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: var(--gradient-bg);
            transition: all 0.3s ease;
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .header-text h1 {
                font-size: 22px;
            }

            .contact-section {
                padding: 20px;
            }

            .cta-banner h2 {
                font-size: 28px;
            }

            .cta-banner .btn {
                padding: 12px 30px;
                font-size: 16px;
            }

            .notification {
                max-width: 250px;
                right: 10px;
                top: 10px;
            }
        }

        @media (max-width: 480px) {
            .header-text h1 {
                font-size: 20px;
            }

            .contact-section {
                padding: 15px;
            }

            .cta-banner {
                padding: 40px 15px;
            }

            .cta-banner h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div id="gradient"></div>
    <div class="container" role="main">
        <div class="header">
            <div style="display: flex; align-items: center;">
                <img src="img/top.png" alt="Task Tube Logo" aria-label="Task Tube Logo">
                <div class="header-text">
                    <h1>Contact Support, <?php echo $username; ?>!</h1>
                    <p>Reach out to our 24/7 support team.</p>
                </div>
            </div>
            <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">Toggle Dark Mode</button>
        </div>

        <div class="contact-section">
            <h2>Get in Touch</h2>
            <p>
                We're here to help with any questions or issues you may have! At Task Tube, our dedicated support team is available 24/7. Whether you need help with your account, login, or have general inquiries, feel free to reach out.
            </p>

            <div class="contact-info">
                <h2>Contact Information</h2>
                <p>
                    <i class="fab fa-telegram"></i> Telegram Contact: 
                    <strong>
                        <a href="https://t.me/<?php echo $telegram_clean; ?>" onclick="openTelegram(event, 'https://t.me/<?php echo $telegram_clean; ?>')">
                            <?php echo htmlspecialchars($telegram_raw); ?>
                        </a>
                    </strong>
                </p>
                <p><i class="far fa-clock"></i> Availability: <strong>24/7</strong></p>
                <p><i class="fas fa-hourglass-half"></i> Response Time: <strong>Usually within 24 hours</strong></p>
            </div>

            <div class="categories">
                <h2>We Can Help With:</h2>
                <ul>
                    <li>Technical Support for Login/Access Issues</li>
                    <li>Verification Requests</li>
                </ul>
            </div>

            <p class="signup-link">
                Not yet a member? <a href="register.php" class="btn">Sign Up Now</a>
            </p>
        </div>

        <div class="cta-banner">
            <h2>Need Help? Contact Us Now!</h2>
            <a href="https://t.me/<?php echo $telegram_clean; ?>" onclick="openTelegram(event, 'https://t.me/<?php echo $telegram_clean; ?>')" class="btn">
                Message Us on Telegram
            </a>
        </div>

        <div id="notificationContainer"></div>
    </div>

    <div class="bottom-menu" role="navigation">
        <a href="home.php">Home</a>
        <a href="profile.php">Profile</a>
        <a href="history.php">History</a>
        <a href="support.php" class="active">Support</a>
        <button id="logoutBtn" aria-label="Log out">Logout</button>
    </div>

    <script>
        // Telegram Deep Link Handling for WebViews
        function openTelegram(event, url) {
            event.preventDefault();
            
            // Check if loaded inside a WebView environment
            const isWebView = /wv|AndroidWebView|iPhone.*Mobile/i.test(navigator.userAgent) || window.Android || (window.webkit && window.webkit.messageHandlers);
            
            if (isWebView) {
                // Try opening using external browser intent target
                var windowRef = window.open(url, '_system');
                if (!windowRef || windowRef.closed || typeof windowRef.closed == 'undefined') {
                    // Fallback to directly changing browser location
                    window.location.href = url;
                }
            } else {
                // Standard browser behavior
                window.open(url, '_blank', 'noopener,noreferrer');
            }
        }

        window.__lc = window.__lc || {};
        window.__lc.license = 15808029;
        (function(n, t, c) {
            function i(n) { return e._h ? e._h.apply(null, n) : e._q.push(n) }
            var e = {
                _q: [], _h: null, _v: "2.0",
                on: function() { i(["on", c.call(arguments)]) },
                once: function() { i(["once", c.call(arguments)]) },
                off: function() { i(["off", c.call(arguments)]) },
                get: function() { if (!e._h) throw new Error("[LiveChatWidget] You can't use getters before load."); return i(["get", c.call(arguments)]) },
                call: function() { i(["call", c.call(arguments)]) },
                init: function() {
                    var n = t.createElement("script");
                    n.async = true;
                    n.type = "text/javascript";
                    n.src = "https://cdn.livechatinc.com/tracking.js";
                    t.head.appendChild(n);
                }
            };
            !n.__lc.asyncInit && e.init();
            n.LiveChatWidget = n.LiveChatWidget || e;
        })(window, document, [].slice);

        // Dark Mode Toggle
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const currentTheme = localStorage.getItem('theme') || 'light';
        if (currentTheme === 'dark') {
            body.setAttribute('data-theme', 'dark');
            themeToggle.textContent = 'Toggle Light Mode';
        }

        themeToggle.addEventListener('click', () => {
            const isDark = body.getAttribute('data-theme') === 'dark';
            body.setAttribute('data-theme', isDark ? 'light' : 'dark');
            themeToggle.textContent = isDark ? 'Toggle Dark Mode' : 'Toggle Light Mode';
            localStorage.setItem('theme', isDark ? 'light' : 'dark');
        });

        // Menu Interactions
        const menuItems = document.querySelectorAll('.bottom-menu a');
        menuItems.forEach((item) => {
            item.addEventListener('click', () => {
                menuItems.forEach((menuItem) => menuItem.classList.remove('active'));
                item.classList.add('active');
            });
        });

        // Logout Button
        document.getElementById('logoutBtn').addEventListener('click', () => {
            Swal.fire({
                title: 'Log out?',
                text: 'Are you sure you want to log out?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#22c55e',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, log out'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'logout.php',
                        type: 'POST',
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                window.location.href = '../signin.php';
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: 'Failed to log out. Please try again.'
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Server Error',
                                text: 'An error occurred while logging out.'
                            });
                        }
                    });
                }
            });
        });

        // Notification Handling
        const notificationContainer = document.getElementById('notificationContainer');
        function fetchNotifications() {
            $.ajax({
                url: 'fetch_notifications.php',
                type: 'GET',
                dataType: 'json',
                success: function(notifications) {
                    notificationContainer.innerHTML = '';
                    notifications.forEach((message, index) => {
                        const notification = document.createElement('div');
                        notification.className = `notification ${message.type || 'success'}`;
                        notification.setAttribute('role', 'alert');
                        notification.innerHTML = `<span>${message.text}</span>`;
                        notificationContainer.appendChild(notification);
                        notification.style.top = `${20 + index * 80}px`;
                        setTimeout(() => notification.remove(), 3500);
                    });
                },
                error: function() {
                    console.error('Failed to fetch notifications');
                }
            });
        }

        fetchNotifications();
        setInterval(fetchNotifications, 20000);

        // Gradient Animation
        var colors = [
            [62, 35, 255],
            [60, 255, 60],
            [255, 35, 98],
            [45, 175, 230],
            [255, 0, 255],
            [255, 128, 0]
        ];
        var step = 0;
        var colorIndices = [0, 1, 2, 3];
        var gradientSpeed = 0.002;
        const gradientElement = document.getElementById('gradient');

        function updateGradient() {
            var c0_0 = colors[colorIndices[0]];
            var c0_1 = colors[colorIndices[1]];
            var c1_0 = colors[colorIndices[2]];
            var c1_1 = colors[colorIndices[3]];
            var istep = 1 - step;
            var r1 = Math.round(istep * c0_0[0] + step * c0_1[0]);
            var g1 = Math.round(istep * c0_0[1] + step * c0_1[1]);
            var b1 = Math.round(istep * c0_0[2] + step * c0_1[2]);
            var color1 = `rgb(${r1},${g1},${b1})`;
            var r2 = Math.round(istep * c1_0[0] + step * c1_1[0]);
            var g2 = Math.round(istep * c1_0[1] + step * c1_1[1]);
            var b2 = Math.round(istep * c1_0[2] + step * c1_1[2]);
            var color2 = `rgb(${r2},${g2},${b2})`;
            gradientElement.style.background = `linear-gradient(135deg, ${color1}, ${color2})`;
            step += gradientSpeed;
            if (step >= 1) {
                step %= 1;
                colorIndices[0] = colorIndices[1];
                colorIndices[2] = colorIndices[3];
                colorIndices[1] = (colorIndices[1] + Math.floor(1 + Math.random() * (colors.length - 1))) % colors.length;
                colorIndices[3] = (colorIndices[3] + Math.floor(1 + Math.random() * (colors.length - 1))) % colors.length;
            }
            requestAnimationFrame(updateGradient);
        }

        requestAnimationFrame(updateGradient);

        // Context Menu Disable
        document.addEventListener('contextmenu', function(event) {
            if (!event.target.closest('a')) {
                event.preventDefault();
            }
        });
    </script>
</body>
</html>
