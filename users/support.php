<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
session_start([
    'cookie_path' => '/',
    'cookie_lifetime' => 86400,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
]);
error_log('Session ID in support.php: ' . session_id() . ', User ID: ' . ($_SESSION['user_id'] ?? 'not set'), 3, '../debug.log');

// Set time zone to UTC
date_default_timezone_set('UTC');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log('No user_id in session, redirecting to signin', 3, '../debug.log');
    header('Location: ../signin.php');
    ob_end_flush();
    exit;
}

// Include database connection
try {
    require_once '../database/conn.php';
} catch (Exception $e) {
    error_log('Failed to include conn.php: ' . $e->getMessage(), 3, '../debug.log');
    echo 'Failed to connect to database. Check logs for details.';
    ob_end_flush();
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
        ob_end_flush();
        exit;
    }
    $username = htmlspecialchars($user['name']);
    $balance = number_format($user['balance'], 2);
    $user_country = htmlspecialchars($user['country']);
} catch (PDOException $e) {
    error_log('Database error in support.php: ' . $e->getMessage(), 3, '../debug.log');
    if (file_exists('../error.php')) {
        include '../error.php';
    } else {
        echo 'Database error occurred: ' . htmlspecialchars($e->getMessage());
    }
    ob_end_flush();
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
    error_log('Region settings fetch error in support.php: ' . $e->getMessage(), 3, '../debug.log');
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
    error_log('Telegram support fetch error in support.php: ' . $e->getMessage(), 3, '../debug.log');
}

// Fallback in case table/record is not set yet
if (empty($telegram_raw)) {
    $telegram_raw = '@TaskTubeSupport';
    $telegram_clean = 'TaskTubeSupport';
}
?>

<?php include('inc/translate.php'); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta name="description" content="Contact Illuminate Tube's support team 24/7 via Telegram for assistance with your account, login, or general inquiries." />
    <title>Support | Illuminate Tube</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-color: #000000;
            --text-color: #ffffff;
            --subtext-color: #9ca3af;
            --accent-color: #22c55e;
            --menu-bg: rgba(17, 24, 39, 0.85);
            --menu-text: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        html, body {
            width: 100%;
            min-height: 100vh;
            background-color: var(--bg-color);
            color: var(--text-color);
            overflow-x: hidden;
            overflow-y: auto;
        }

        /* Fixed Header Overlay */
        .top-header {
            position: sticky;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(8px);
            padding: 6px 14px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .user-badge img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }

        .balance-badge {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid var(--accent-color);
            backdrop-filter: blur(8px);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 700;
            color: #4ade80;
        }


        /* Standard Page Content Container */
        .page-container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            padding: 90px 20px 100px 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Card Container styling */
        .card-inner {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
        }

        .card-inner h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 16px;
            text-align: center;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .support-info-box {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .support-info-box p {
            font-size: 13px;
            color: var(--subtext-color);
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .support-info-box p strong {
            color: #ffffff;
        }

        .support-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
            font-size: 13px;
            color: #ffffff;
        }

        .support-item i {
            color: var(--accent-color);
            font-size: 16px;
            width: 20px;
            text-align: center;
        }

        .support-item a {
            color: #4ade80;
            text-decoration: none;
            font-weight: 600;
        }

        .support-item a:hover {
            text-decoration: underline;
        }

        .help-list {
            list-style: none;
            padding: 0;
            margin-top: 8px;
        }

        .help-list li {
            font-size: 13px;
            color: var(--subtext-color);
            line-height: 1.5;
            margin-bottom: 8px;
            position: relative;
            padding-left: 24px;
        }

        .help-list li::before {
            content: '\f058';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: var(--accent-color);
            position: absolute;
            left: 0;
            top: 2px;
        }

        .telegram-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #ffffff;
            font-size: 15px;
            font-weight: 600;
            padding: 14px;
            border-radius: 16px;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .telegram-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4);
        }

        /* Notifications Toast */
        .notification {
            position: fixed;
            top: 70px;
            right: 20px;
            background: rgba(17, 24, 39, 0.9);
            color: #fff;
            padding: 12px 20px;
            border-radius: 12px;
            border: 1px solid var(--accent-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
            z-index: 1000;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideInRight 0.5s ease-out, fadeOut 0.5s ease-out 3s forwards;
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes fadeOut {
            to { opacity: 0; transform: translateY(-20px); }
        }

        /* Fixed Bottom Navigation */
        .bottom-menu {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: var(--menu-bg);
            backdrop-filter: blur(10px);
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 12px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 100;
        }

        .bottom-menu a,
        .bottom-menu button {
            color: var(--menu-text);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            padding: 6px 14px;
            transition: color 0.3s ease;
            background: none;
            border: none;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .bottom-menu a.active,
        .bottom-menu a:hover,
        .bottom-menu button:hover {
            color: var(--accent-color);
        }
    </style>
</head>
<body>

    <!-- Header Overlay -->
    <div class="top-header">
        <div class="user-badge">
            <img src="img/top.png" alt="Logo">
            <span style="font-size: 14px; font-weight: 600;"><?php echo $username; ?></span>
        </div>
        <div class="balance-badge">
            $<span id="balance"><?php echo $balance; ?></span>
        </div>
    </div>

    <!-- Main Scrollable Page Content -->
    <div class="page-container">
        <div class="card-inner">
            <h2><i class="fa-solid fa-headset"></i> Contact Support</h2>
            
            <div class="support-info-box">
                <p>We're here to help with any questions or issues you may have! Our dedicated support team is available 24/7. Reach out directly on Telegram for assistance with your account, login, or general inquiries.</p>
                
                <div class="support-item">
                    <i class="fab fa-telegram"></i>
                    <span>Telegram: 
                        <a href="https://t.me/<?php echo $telegram_clean; ?>" onclick="openTelegram(event, 'https://t.me/<?php echo $telegram_clean; ?>')">
                            <?php echo htmlspecialchars($telegram_raw); ?>
                        </a>
                    </span>
                </div>
                <div class="support-item">
                    <i class="far fa-clock"></i>
                    <span>Availability: <strong>24/7</strong></span>
                </div>
                <div class="support-item">
                    <i class="fas fa-hourglass-half"></i>
                    <span>Response Time: <strong>Usually within 24 hours</strong></span>
                </div>
            </div>

            <div class="support-info-box">
                <strong style="font-size: 14px; color: #ffffff; display: block; margin-bottom: 8px;">We Can Help With:</strong>
                <ul class="help-list">
                    <li>Technical Support for Login/Access Issues</li>
                    <li>Account Verification Requests</li>
                    <li>General Inquiry and Earnings Help</li>
                </ul>
            </div>

            <a href="https://t.me/<?php echo $telegram_clean; ?>" onclick="openTelegram(event, 'https://t.me/<?php echo $telegram_clean; ?>')" class="telegram-btn">
                <i class="fab fa-telegram"></i> Message Us on Telegram
            </a>
        </div>
    </div>

    <div id="notificationContainer"></div>

    <!-- Fixed Bottom Menu -->
    <div class="bottom-menu" role="navigation">
        <a href="home.php"><i class="fa-solid fa-house"></i>Home</a>
        <a href="profile.php"><i class="fa-solid fa-money-bill"></i>Withdraw</a>
        <a href="history.php"><i class="fa-solid fa-clock-rotate-left"></i>History</a>
        <a href="support.php" class="active"><i class="fa-solid fa-headset"></i>Support</a>
        <button id="logoutBtn" aria-label="Log out"><i class="fa-solid fa-right-from-bracket"></i>Logout</button>
    </div>

    <script>
        // Telegram Deep Link Handling for WebViews
        function openTelegram(event, url) {
            event.preventDefault();
            
            const isWebView = /wv|AndroidWebView|iPhone.*Mobile/i.test(navigator.userAgent) || window.Android || (window.webkit && window.webkit.messageHandlers);
            
            if (isWebView) {
                var windowRef = window.open(url, '_system');
                if (!windowRef || windowRef.closed || typeof windowRef.closed == 'undefined') {
                    window.location.href = url;
                }
            } else {
                window.open(url, '_blank', 'noopener,noreferrer');
            }
        }

        // Logout handling
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
                            }
                        }
                    });
                }
            });
        });

        // Fetch Notifications
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
                        notification.className = 'notification';
                        notification.innerHTML = `<span>${message.text}</span>`;
                        notificationContainer.appendChild(notification);
                        notification.style.top = `${70 + index * 60}px`;
                        setTimeout(() => notification.remove(), 3500);
                    });
                }
            });
        }
        fetchNotifications();
        setInterval(fetchNotifications, 20000);

        // Disable Context Menu
        document.addEventListener('contextmenu', function(event) {
            if (!event.target.closest('a')) {
                event.preventDefault();
            }
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
