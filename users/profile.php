<?php
session_start();
require_once '../database/conn.php';

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log('No user_id in session, redirecting to signin', 3, '../debug.log');
    header('Location: ../signin.php');
    exit;
}

// Fetch user data
try {
    $stmt = $pdo->prepare("
        SELECT name, balance, verification_status, COALESCE(country, '') AS country, upgrade_status
        FROM users 
        WHERE id = ?
    ");
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
    $verification_status = $user['verification_status'];
    $user_country = htmlspecialchars($user['country']);
    $upgrade_status = $user['upgrade_status'] ?? 'not_upgraded'; // Default to 'not_upgraded' if null
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage(), 3, '../debug.log');
    if (file_exists('../error.php')) {
        include '../error.php';
    } else {
        echo 'Database error occurred: ' . htmlspecialchars($e->getMessage());
    }
    exit;
}

// Fetch region settings based on user's country
try {
    $stmt = $pdo->prepare("
        SELECT section_header, ch_name, ch_value, COALESCE(channel, 'Bank') AS channel, account_upgrade
        FROM region_settings 
        WHERE country = ?
    ");
    $stmt->execute([$user_country]);
    $region_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($region_settings) {
        $section_header = htmlspecialchars($region_settings['section_header']);
        $ch_name = htmlspecialchars($region_settings['ch_name']);
        $ch_value = htmlspecialchars($region_settings['ch_value']);
        $channel = htmlspecialchars($region_settings['channel']);
        $account_upgrade = $region_settings['account_upgrade'] ?? 0; // Default to 0 if not set
    } else {
        // Fallback values if no region settings are found.
        $section_header = 'Withdraw Funds';
        $ch_name = 'Bank Name';
        $ch_value = 'Bank Account';
        $channel = 'Bank';
        $account_upgrade = 0; // Default to verification flow
        error_log('No region settings found for country: ' . $user_country, 3, '../debug.log');
    }
} catch (PDOException $e) {
    error_log('Region settings fetch error: ' . $e->getMessage(), 3, '../debug.log');
    // Fallback values on error
    $section_header = 'Withdraw Funds';
    $ch_name = 'Bank Name';
    $ch_value = 'Bank Account';
    $channel = 'Bank';
    $account_upgrade = 0; // Default to verification flow
}

// Check for success or error message
$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : null;
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : null;

// Improved function to check if a URL is accessible
function url_exists($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout after 10 seconds
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Connection timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification (use with caution)
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Disable host verification (use with caution)
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($code !== 200) {
        error_log("url_exists failed for $url: HTTP $code, cURL error: $error", 3, '../debug.log');
        return ['status' => false, 'error' => "HTTP $code - $error"];
    }
    return ['status' => true];
}

// Fetch a random unwatched video
try {
    $stmt = $pdo->prepare("
        SELECT v.id, v.title, v.url, v.reward 
        FROM videos v 
        WHERE v.id NOT IN (
            SELECT video_id FROM activities 
            WHERE user_id = ? AND action LIKE 'Watched%'
        ) 
        ORDER BY RAND() LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($video) {
        // Force correct InfinityFree path (htdocs is the real public root)
        $video['url'] = 'https://tasktube.gt.tc/users/videos/' . basename($video['url']);
        $url_check = url_exists($video['url']);
        if (!$url_check['status']) {
            error_log('Video file not accessible: ' . $video['url'] . ' (' . $url_check['error'] . ')', 3, '../debug.log');
            $video = null;
            $video_error = 'Video file not accessible: ' . htmlspecialchars($video['url']) . ' (' . htmlspecialchars($url_check['error']) . ')';
        } else {
            error_log('Video loaded: ' . $video['url'], 3, '../debug.log');
        }
    } else {
        error_log('No unwatched videos found for user ID: ' . $_SESSION['user_id'], 3, '../debug.log');
        $video_error = 'No ads available at the moment, please check back later.';
    }
} catch (PDOException $e) {
    error_log('Video fetch error: ' . $e->getMessage(), 3, '../debug.log');
    $video = null;
    $video_error = 'Failed to load video from database: ' . htmlspecialchars($e->getMessage());
}
?>

<?php include('inc/translate.php'); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Access your Cash Tube dashboard to earn up to $1,000 daily by watching video ads. Withdraw your crypto earnings instantly!" />
    <meta name="keywords" content="Cash Tube, dashboard, earn money online, cryptocurrency, watch ads, passive income" />
    <meta name="author" content="Cash Tube" />
    <title>Dashboard | Cash Tube</title>
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

        .balance-card {
            background: linear-gradient(135deg, var(--accent-color), var(--accent-hover));
            color: #fff;
            border-radius: 16px;
            padding: 28px;
            margin: 24px 0;
            box-shadow: 0 6px 16px var(--shadow-color);
            animation: slideIn 0.5s ease-out 0.2s backwards;
        }

        .balance-card p {
            font-size: 18px;
            font-weight: 500;
        }

        .balance-card h2 {
            font-size: 36px;
            font-weight: 700;
            margin: 8px 0;
        }

        .balance-card .status {
            font-size: 14px;
            font-weight: 500;
            margin-top: 10px;
        }

        .video-section {
            text-align: center;
            margin: 48px 0;
            animation: slideIn 0.5s ease-out 0.4s backwards;
        }

        .video-section h1 {
            font-size: 30px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-color);
        }

        .video-section video {
            border-radius: 16px;
            width: 100%;
            max-width: 640px;
            box-shadow: 0 6px 16px var(--shadow-color);
        }

        .video-section h4 {
            font-size: 16px;
            color: var(--subtext-color);
            margin-top: 20px;
        }

        .video-section span {
            color: var(--accent-color);
            font-weight: 600;
        }

        .error {
            color: red;
            margin-top: 10px;
            font-size: 14px;
            text-align: center;
        }

        .success {
            color: var(--accent-color);
            margin-top: 10px;
            font-size: 14px;
            text-align: center;
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

        .play-button {
            margin: 10px auto;
            padding: 10px 20px;
            background: var(--accent-color);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
        }

        .play-button:hover {
            background: var(--accent-hover);
        }

        .form-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 6px 16px var(--shadow-color);
            margin: 24px 0;
            animation: slideIn 0.5s ease-out 0.6s backwards;
        }

        .form-card h2 {
            font-size: 24px;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-card h2::before {
            content: '💸';
            font-size: 1.2rem;
            margin-right: 8px;
        }

        .input-container {
            position: relative;
            margin-bottom: 28px;
        }

        .input-container input {
            width: 100%;
            padding: 14px 8px;
            font-size: 16px;
            border: none;
            border-bottom: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-color);
            outline: none;
            transition: border-color 0.3s ease;
        }

        .input-container input:focus,
        .input-container input:not(:placeholder-shown) {
            border-bottom-color: var(--accent-color);
        }

        .input-container label {
            position: absolute;
            top: 14px;
            left: 8px;
            font-size: 16px;
            color: var(--subtext-color);
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .input-container input:focus ~ label,
        .input-container input:not(:placeholder-shown) ~ label,
        .input-container .active {
            top: -18px;
            left: 0;
            font-size: 12px;
            color: var(--accent-color);
        }

        .input-container input.has-value ~ label {
            top: -18px;
            left: 0;
            font-size: 12px;
            color: var(--accent-color);
        }

        .submit-btn {
            width: 100%;
            padding: 14px;
            background: var(--accent-color);
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .submit-btn:hover {
            background: var(--accent-hover);
            transform: scale(1.02);
        }

        .submit-btn:disabled {
            background: #6b7280;
            cursor: not-allowed;
        }

        .verify-btn {
            width: 100%;
            padding: 14px;
            background: #3b82f6;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
            margin-top: 20px;
        }

        .verify-btn:hover {
            background: #2563eb;
            transform: scale(1.02);
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .header-text h1 {
                font-size: 22px;
            }

            .balance-card h2 {
                font-size: 30px;
            }

            .video-section h1 {
                font-size: 26px;
            }

            .video-section video {
                width: 100%;
            }

            .form-card {
                padding: 20px;
            }

            .notification {
                max-width: 250px;
                right: 10px;
                top: 10px;
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
    </style>
</head>
<body>
    <div id="gradient"></div>
    <div class="container" role="main">
        <div class="header">
            <div style="display: flex; align-items: center;">
                <img src="img/top.png" alt="Cash Tube Logo" aria-label="Cash Tube Logo">
                <div class="header-text">
                    <h1>Hello, <?php echo $username; ?>!</h1>
                    <p>Start Earning Crypto Today!</p>
                </div>
            </div>
            <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">Toggle Dark Mode</button>
        </div>

        <?php if ($success_message): ?>
            <div class="notification success" role="alert">
                <span><?php echo $success_message; ?></span>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="notification error" role="alert">
                <span><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <div class="balance-card">
            <p>Available Crypto Balance</p>
            <h2>$<span id="balance"><?php echo $balance; ?></span></h2>
            <div class="status">
                Account Status: 
                <?php 
                if ($verification_status === 'verified' && $upgrade_status === 'upgraded') {
                    echo 'Verified & Upgraded';
                } elseif ($verification_status === 'verified') {
                    echo 'Verified';
                } elseif ($upgrade_status === 'upgraded') {
                    echo 'Upgraded';
                } else {
                    echo 'Not Verified or Upgraded';
                }
                ?>
            </div>
        </div>

        <div class="video-section">
            <h1>Watch Videos to Earn Crypto</h1>
            <?php if ($video): ?>
                <video id="videoPlayer" 
                       controls 
                       playsinline 
                       muted 
                       preload="auto" 
                       data-video-id="<?php echo $video['id']; ?>" 
                       data-reward="<?php echo $video['reward']; ?>">
                    <source src="<?php echo htmlspecialchars($video['url']); ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
                <button class="play-button" id="playButton" style="display: block;">Play Video</button>
                <h4 id="video-reward">Earn <span>$<?php echo number_format($video['reward'], 2); ?></span> by watching <span><?php echo htmlspecialchars($video['title']); ?></span>. The more videos you watch, the more your <span>crypto balance</span> increases</h4>
                <?php if (isset($video_error)): ?>
                    <p class="error"><?php echo $video_error; ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p id="no-videos-message"><?php echo $video_error; ?></p>
            <?php endif; ?>
        </div>

        <div class="form-card">
            <h2><?php echo $section_header; ?></h2>
            <form id="fundForm" action="process_withdrawal.php" method="POST" role="form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="input-container">
                    <input type="text" id="channel" name="channel" required aria-required="true">
                    <label for="channel"><?php echo htmlspecialchars($channel); ?></label>
                </div>
                <div class="input-container">
                    <input type="text" id="bankName" name="bank_name" required aria-required="true">
                    <label for="bankName"><?php echo htmlspecialchars($ch_name); ?></label>
                </div>
                <div class="input-container">
                    <input type="text" id="bankAccount" name="bank_account" required aria-required="true">
                    <label for="bankAccount"><?php echo htmlspecialchars($ch_value); ?></label>
                </div>
                <div class="input-container">
                    <input type="number" id="amount" name="amount" step="0.01" min="0.01" max="<?php echo $user['balance']; ?>" required aria-required="true">
                    <label for="amount">Amount ($)</label>
                </div>
                <button type="submit" class="submit-btn" aria-label="Withdraw funds" 
                    <?php echo ($verification_status !== 'verified' && $upgrade_status !== 'upgraded') ? 'disabled' : ''; ?>>
                    Withdraw
                </button>
            </form>
            <?php if ($account_upgrade == 1 && $verification_status !== 'verified' && $upgrade_status !== 'upgraded'): ?>
                <p class="error">Please upgrade your account to enable withdrawals.</p>
                <button class="verify-btn" onclick="window.location.href='upgrade_account.php'" aria-label="Upgrade account">
                    Upgrade Account
                </button>
            <?php endif; ?>
            <?php if ($account_upgrade != 1 && $upgrade_status !== 'upgraded' && $verification_status !== 'verified'): ?>
                <p class="error">Please verify your account to enable withdrawals.</p>
                <button class="verify-btn" onclick="window.location.href='verify_account.php'" aria-label="Verify account">
                    Verify Account
                </button>
            <?php endif; ?>
        </div>

        <div id="notificationContainer"></div>
    </div>

    <div class="bottom-menu" role="navigation">
        <a href="home.php" class="active">Home</a>
        <a href="profile.php">Profile</a>
        <a href="history.php">History</a>
        <a href="support.php">Support</a>
        <button id="logoutBtn" aria-label="Log out">Logout</button>
    </div>

    <script>
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

        // Initialize and Update Label Positions
        function updateLabelPosition(input) {
            const label = input.nextElementSibling;
            if (label && label.tagName === 'LABEL') {
                if (input.value !== '') {
                    label.classList.add('active');
                    input.classList.add('has-value');
                } else {
                    label.classList.remove('active');
                    input.classList.remove('has-value');
                }
            }
        }

        document.querySelectorAll('.input-container input').forEach((input) => {
            updateLabelPosition(input); // Initialize on load
            input.addEventListener('input', () => updateLabelPosition(input)); // Update on input
            input.addEventListener('focus', () => {
                const label = input.nextElementSibling;
                if (label && label.tagName === 'LABEL') {
                    label.classList.add('active');
                }
            });
            input.addEventListener('blur', () => updateLabelPosition(input)); // Update on blur
        });

        // Form Validation
        document.getElementById('fundForm').addEventListener('submit', function(e) {
            const amountInput = document.getElementById('amount');
            const maxAmount = parseFloat(<?php echo json_encode($user['balance']); ?>);
            const amount = parseFloat(amountInput.value);
            const channel = document.getElementById('channel').value.trim();
            const bankName = document.getElementById('bankName').value.trim();
            const bankAccount = document.getElementById('bankAccount').value.trim();

            if (amount <= 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Amount',
                    text: 'Withdrawal amount must be greater than $0.'
                });
            } else if (amount > maxAmount) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Insufficient Balance',
                    text: `Withdrawal amount cannot exceed your balance of $${maxAmount.toFixed(2)}.`
                });
            } else if (!channel || !bankName || !bankAccount) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Missing Fields',
                    text: 'Please fill in all required fields.'
                });
            }
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

        // Video Watch Tracking
        const videoPlayer = document.getElementById('videoPlayer');
        const playButton = document.getElementById('playButton');
        let interval = null;
        let accumulatedReward = 0;
        let totalReward = 0;
        let rewardPerSecond = 0;
        let initialBalance = parseFloat(document.getElementById('balance').textContent);

        if (videoPlayer) {
            // Handle video errors
            videoPlayer.addEventListener('error', function(e) {
                console.error('Video playback error:', e);
                let errorMessage = 'Failed to play video. ';
                if (e.target.error) {
                    switch (e.target.error.code) {
                        case MediaError.MEDIA_ERR_ABORTED:
                            errorMessage += 'The video playback was aborted.';
                            break;
                        case MediaError.MEDIA_ERR_NETWORK:
                            errorMessage += 'A network error occurred. Please check your connection.';
                            break;
                        case MediaError.MEDIA_ERR_DECODE:
                            errorMessage += 'The video could not be decoded. The file may be corrupted.';
                            break;
                        case MediaError.MEDIA_ERR_SRC_NOT_SUPPORTED:
                            errorMessage += 'The video format is not supported or the file is inaccessible.';
                            break;
                        default:
                            errorMessage += 'An unknown error occurred.';
                    }
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Playback Error',
                    text: errorMessage,
                });
                playButton.style.display = 'block';
            });

            // Calculate reward per second when video metadata is loaded
            videoPlayer.addEventListener('loadedmetadata', function() {
                const duration = videoPlayer.duration;
                totalReward = parseFloat(videoPlayer.getAttribute('data-reward'));
                rewardPerSecond = totalReward / duration;
            });

            // Increment displayed balance during playback
            videoPlayer.addEventListener('play', function() {
                playButton.style.display = 'none';
                if (interval === null) {
                    interval = setInterval(() => {
                        accumulatedReward += rewardPerSecond;
                        if (accumulatedReward > totalReward) {
                            accumulatedReward = totalReward;
                        }
                        updateDisplayBalance(accumulatedReward);
                    }, 1000);
                }
            });

            // Pause incrementing when video is paused
            videoPlayer.addEventListener('pause', function() {
                if (interval !== null) {
                    clearInterval(interval);
                    interval = null;
                }
            });

            // Save balance and load next video when video ends
            videoPlayer.addEventListener('ended', function() {
                if (interval !== null) {
                    clearInterval(interval);
                    interval = null;
                }
                const videoId = videoPlayer.getAttribute('data-video-id');
                $.ajax({
                    url: 'process_video_watch.php',
                    type: 'POST',
                    data: { video_id: videoId, reward: accumulatedReward },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Video Watched',
                                text: `You earned $${response.reward.toFixed(2)}!`,
                                timer: 2000,
                                showConfirmButton: false
                            });
                            initialBalance = parseFloat(document.getElementById('balance').textContent);
                            accumulatedReward = 0;
                            loadNextVideo();
                        } else {
                            document.getElementById('balance').textContent = initialBalance.toFixed(2);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.error || 'Failed to record video watch.'
                            });
                            playButton.style.display = 'block';
                        }
                    },
                    error: function() {
                        document.getElementById('balance').textContent = initialBalance.toFixed(2);
                        Swal.fire({
                            icon: 'error',
                            title: 'Server Error',
                            text: 'An error occurred while tracking video watch.'
                        });
                        playButton.style.display = 'block';
                    }
                });
            });

            // Play button to initiate playback
            playButton.addEventListener('click', function() {
                videoPlayer.play().catch(function(error) {
                    console.error('Play error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Playback Error',
                        text: 'Failed to play video: ' + error.message,
                    });
                    playButton.style.display = 'block';
                });
            });
        }

        // Function to load next random unwatched video and autoplay
        function loadNextVideo() {
            $.ajax({
                url: 'get_random_video.php',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data) {
                        const videoUrl = 'https://tasktube.gt.tc/' + data.url;
                        videoPlayer.innerHTML = `<source src="${videoUrl}" type="video/mp4">Your browser does not support the video tag.`;
                        videoPlayer.setAttribute('data-video-id', data.id);
                        videoPlayer.setAttribute('data-reward', data.reward);
                        document.getElementById('video-reward').innerHTML = `Earn <span>$${parseFloat(data.reward).toFixed(2)}</span> by watching <span>${data.title}</span>. The more videos you watch, the more your <span>crypto balance</span> increases`;
                        document.getElementById('no-videos-message')?.remove();
                        videoPlayer.load();
                        accumulatedReward = 0;
                        initialBalance = parseFloat(document.getElementById('balance').textContent);
                        if (interval !== null) {
                            clearInterval(interval);
                            interval = null;
                        }
                        // Autoplay the video
                        videoPlayer.play().catch(function(error) {
                            console.error('Autoplay error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Autoplay Error',
                                text: 'Failed to autoplay next video: ' + error.message,
                            });
                            playButton.style.display = 'block';
                        });
                    } else {
                        const videoSection = document.querySelector('.video-section');
                        videoPlayer?.remove();
                        document.getElementById('video-reward')?.remove();
                        document.getElementById('playButton')?.remove();
                        const noVideosMessage = document.createElement('p');
                        noVideosMessage.id = 'no-videos-message';
                        noVideosMessage.textContent = 'No ads available at the moment, please check back later.';
                        videoSection.appendChild(noVideosMessage);
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Server Error',
                        text: 'Failed to load next video.'
                    });
                    playButton.style.display = 'block';
                }
            });
        }

        // Update displayed balance
        function updateDisplayBalance(accumulated) {
            document.getElementById('balance').textContent = (initialBalance + accumulated).toFixed(2);
        }

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
            var g2 = Math.round(istep * c1_0[2] + step * c1_1[2]);
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
            event.preventDefault();
        });
    </script>
</body>
</html>
