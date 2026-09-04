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
error_log('Session ID in profile.php: ' . session_id() . ', User ID: ' . ($_SESSION['user_id'] ?? 'not set'), 3, '../debug.log');

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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

// Include countries list
try {
    require_once '../inc/countries.php';
    if (!isset($countries) || !is_array($countries)) {
        throw new Exception('Countries list not defined or invalid in countries.php');
    }
} catch (Exception $e) {
    error_log('Failed to include countries.php: ' . $e->getMessage(), 3, '../debug.log');
    $countries = ['United States', 'Nigeria', 'United Kingdom']; // Fallback
}

// Fetch user data
try {
    $stmt = $pdo->prepare("
        SELECT 
            name, 
            email, 
            balance,
            COALESCE(country, '') AS country,
            verification_status,
            upgrade_status
        FROM users 
        WHERE id = ?
    ");
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
    $email = htmlspecialchars($user['email']);
    $balance = number_format($user['balance'], 2);
    $country = htmlspecialchars($user['country']);
    $verification_status = $user['verification_status'];
    $upgrade_status = $user['upgrade_status'] ?? 'not_upgraded';

    if ($country && !in_array($country, $countries)) {
        $country = '';
    }
} catch (PDOException $e) {
    error_log('Database error in profile.php: ' . $e->getMessage(), 3, '../debug.log');
    if (file_exists('../error.php')) {
        include '../error.php';
    } else {
        echo 'Database error occurred: ' . htmlspecialchars($e->getMessage());
    }
    ob_end_flush();
    exit;
}

// Fetch region settings based on user's country
try {
    $stmt = $pdo->prepare("
        SELECT section_header, ch_name, ch_value, COALESCE(channel, 'Mobile Money') AS channel, account_upgrade
        FROM region_settings 
        WHERE country = ?
    ");
    $stmt->execute([$country]);
    $region_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($region_settings) {
        $section_header = htmlspecialchars($region_settings['section_header']);
        $ch_name = htmlspecialchars($region_settings['ch_name']);
        $ch_value = htmlspecialchars($region_settings['ch_value']);
        $channel = htmlspecialchars($region_settings['channel']);
        $account_upgrade = $region_settings['account_upgrade'] ?? 0;
    } else {
        $section_header = 'Withdraw with MoMo';
        $ch_name = 'Network / Provider';
        $ch_value = 'MoMo Number / Account';
        $channel = 'Mobile Money';
        $account_upgrade = 0;
    }
} catch (PDOException $e) {
    error_log('Region settings fetch error in profile.php: ' . $e->getMessage(), 3, '../debug.log');
    $section_header = 'Withdraw with MoMo';
    $ch_name = 'Network / Provider';
    $ch_value = 'MoMo Number / Account';
    $channel = 'Mobile Money';
    $account_upgrade = 0;
}

$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : null;
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : null;
?>

<?php include('inc/translate.php'); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Withdraw | Illuminate Tube</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-color: #000000;
            --text-color: #ffffff;
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
            height: 100%;
            overflow: hidden;
            background-color: var(--bg-color);
            color: var(--text-color);
        }

        /* Fixed Header Overlay */
        .top-header {
            position: fixed;
            top: 62;
            left: 0;
            width: 100%;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            background: linear-gradient(180deg, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0) 100%);
            pointer-events: none;
        }

        .top-header * {
            pointer-events: auto;
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

        /* TikTok Style Fullscreen Feed Wrapper */
        .tiktok-feed {
            width: 100%;
            height: 100vh;
            overflow-y: scroll;
            scroll-snap-type: y mandatory;
            -webkit-overflow-scrolling: touch;
        }

        .tiktok-feed::-webkit-scrollbar {
            display: none;
        }

        /* Individual Snap Slide Card */
        .profile-card-slide {
            width: 100%;
            height: 100vh;
            scroll-snap-align: start;
            scroll-snap-stop: always;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 80px 20px 210px 20px; /* Increased bottom padding to push cards up */
            background: radial-gradient(circle at center, #111827 0%, #000000 100%);
        }

        /* Card Container styling */
        .card-inner {
            width: 100%;
            max-width: 440px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .card-inner h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            text-align: center;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .input-container {
            position: relative;
            margin-bottom: 20px;
        }

        .input-container input,
        .input-container select {
            width: 100%;
            padding: 14px 12px;
            font-size: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.4);
            color: #ffffff;
            outline: none;
            transition: all 0.3s ease;
        }

        .input-container select option {
            background: #111827;
            color: #ffffff;
        }

        .input-container input:focus,
        .input-container select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 10px rgba(34, 197, 94, 0.3);
        }

        .input-container label {
            position: absolute;
            top: -10px;
            left: 10px;
            font-size: 11px;
            background: #000;
            padding: 0 6px;
            color: var(--accent-color);
            border-radius: 4px;
        }

        .submit-btn, .verify-btn, .change-passcode-btn {
            width: 100%;
            padding: 14px;
            background: var(--accent-color);
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: transform 0.2s ease;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .submit-btn:active, .verify-btn:active, .change-passcode-btn:active {
            transform: scale(0.96);
        }

        .verify-btn {
            background: #3b82f6;
        }

        .change-passcode-btn {
            background: #10b981;
        }

        .submit-btn:disabled {
            background: #4b5563;
            cursor: not-allowed;
        }

        .error-msg {
            color: #ef4444;
            font-size: 13px;
            text-align: center;
            margin-top: 10px;
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

    <?php if ($success_message): ?>
        <div class="notification" role="alert">
            <i class="fa-solid fa-circle-check" style="color: #4ade80;"></i>
            <span><?php echo $success_message; ?></span>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="notification" role="alert">
            <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i>
            <span><?php echo $error_message; ?></span>
        </div>
    <?php endif; ?>

    <!-- Scrollable TikTok Snap Feed -->
    <div class="tiktok-feed" id="tiktokFeed">

        <!-- Slide 1: Withdrawal Options -->
        <div class="profile-card-slide">
            <div class="card-inner">
                <h2><i class="fa-solid fa-wallet"></i> <?php echo $section_header; ?></h2>
                <form id="momoFundForm" action="process_withdrawal.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="input-container">
                        <label for="channel"><?php echo htmlspecialchars($channel); ?></label>
                        <input type="text" id="channel" name="channel" required>
                    </div>
                    <div class="input-container">
                        <label for="bankName"><?php echo htmlspecialchars($ch_name); ?></label>
                        <input type="text" id="bankName" name="bank_name" required>
                    </div>
                    <div class="input-container">
                        <label for="bankAccount"><?php echo htmlspecialchars($ch_value); ?></label>
                        <input type="text" id="bankAccount" name="bank_account" required>
                    </div>
                    <div class="input-container">
                        <label for="amount">Amount ($)</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0.01" max="<?php echo $user['balance']; ?>" required>
                    </div>
                    <button type="submit" class="submit-btn" <?php echo ($verification_status !== 'verified' && $upgrade_status !== 'upgraded') ? 'disabled' : ''; ?>>
                        <i class="fa-solid fa-money-bill-transfer"></i> Withdraw
                    </button>
                </form>

                <?php if ($account_upgrade == 1 && $verification_status !== 'verified' && $upgrade_status !== 'upgraded'): ?>
                    <p class="error-msg">Please upgrade your account to enable withdrawals.</p>
                    <button class="verify-btn" onclick="window.location.href='upgrade_account.php'">
                        <i class="fa-solid fa-rocket"></i> Upgrade Account
                    </button>
                <?php endif; ?>
                <?php if ($account_upgrade != 1 && $upgrade_status !== 'upgraded' && $verification_status !== 'verified'): ?>
                    <p class="error-msg">Please verify your account to enable withdrawals.</p>
                    <button class="verify-btn" onclick="window.location.href='verify_account.php'">
                        <i class="fa-solid fa-shield-check"></i> Verify Account
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Slide 2: Profile Settings -->
        <div class="profile-card-slide">
            <div class="card-inner">
                <h2><i class="fa-solid fa-user-gear"></i> Profile Settings</h2>
                <form id="profileForm" action="process_profile_update.php" method="POST">
                    <div class="input-container">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" value="<?php echo $username; ?>" required>
                    </div>
                    <div class="input-container">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo $email; ?>" required>
                    </div>
                    <div class="input-container">
                        <label for="country">Country</label>
                        <select id="country" name="country" required>
                            <option value="" <?php echo empty($country) ? 'selected' : ''; ?>>Select Country</option>
                            <?php foreach ($countries as $name): ?>
                                <option value="<?php echo htmlspecialchars($name); ?>" <?php echo $country === $name ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="submit-btn"><i class="fa-solid fa-floppy-disk"></i> Update Profile</button>
                    <?php if ($verification_status !== 'verified'): ?>
                        <button type="button" class="verify-btn" onclick="window.location.href='verify_account.php'"><i class="fa-solid fa-shield-halved"></i> Verify Account</button>
                    <?php endif; ?>
                    <button type="button" class="change-passcode-btn" onclick="window.location.href='change_passcode.php'"><i class="fa-solid fa-key"></i> Change Passcode</button>
                </form>
            </div>
        </div>

    </div>

    <div id="notificationContainer"></div>

    <!-- Fixed Bottom Menu -->
    <div class="bottom-menu" role="navigation">
        <a href="home.php"><i class="fa-solid fa-house"></i>Home</a>
        <a href="profile.php" class="active"><i class="fa-solid fa-money-bill"></i>Withdraw</a>
        <a href="history.php"><i class="fa-solid fa-clock-rotate-left"></i>History</a>
        <a href="support.php"><i class="fa-solid fa-headset"></i>Support</a>
        <button id="logoutBtn" aria-label="Log out"><i class="fa-solid fa-right-from-bracket"></i>Logout</button>
    </div>

    <script>
        // Profile Form AJAX Handler
        document.getElementById('profileForm').addEventListener('submit', function(event) {
            event.preventDefault();
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const country = document.getElementById('country').value;

            if (!name || !email || !country) {
                Swal.fire({
                    icon: 'error',
                    title: 'Missing Fields',
                    text: 'Please fill in all profile fields.'
                });
                return;
            }

            $.ajax({
                url: 'process_profile_update.php',
                type: 'POST',
                data: { name, email, country },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Profile Updated',
                            text: 'Your profile has been updated successfully!',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = 'profile.php?success=Profile updated successfully';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.error || 'Failed to update profile.'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Server Error',
                        text: 'An error occurred. Please try again later.'
                    });
                }
            });
        });

        // Withdrawal Validation
        const momoForm = document.getElementById('momoFundForm');
        if (momoForm) {
            momoForm.addEventListener('submit', function(e) {
                const amountInput = document.getElementById('amount');
                const maxAmount = parseFloat(<?php echo json_encode($user['balance']); ?>);
                const amount = parseFloat(amountInput.value);

                if (amount <= 0 || amount > maxAmount) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Amount',
                        text: 'Please enter a valid amount within your current balance.'
                    });
                }
            });
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
            event.preventDefault();
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
