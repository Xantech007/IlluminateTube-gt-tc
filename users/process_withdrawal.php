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

// Set time zone to UTC for request time
date_default_timezone_set('UTC');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log('No user_id in session, redirecting to signin', 3, '../debug.log');
    header('Location: ../signin.php');
    ob_end_flush();
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    error_log('CSRF validation failed for user ID: ' . $_SESSION['user_id'], 3, '../debug.log');
    header('Location: home.php?error=Invalid+CSRF+token');
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
    $stmt = $pdo->prepare("SELECT name, balance, COALESCE(country, '') AS country, verification_status, upgrade_status FROM users WHERE id = ?");
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

    // Check verification and upgrade statuses
    $account_status_badge = '';
    if (strtolower($user['verification_status'] ?? '') === 'verified') {
        $account_status_badge = '<span class="status-tag status-verified"><i class="fa-solid fa-circle-check"></i> Account Verified</span>';
    } elseif (strtolower($user['upgrade_status'] ?? '') === 'upgraded') {
        $account_status_badge = '<span class="status-tag status-upgraded"><i class="fa-solid fa-circle-up"></i> Account Upgraded</span>';
    } else {
        $account_status_badge = '<span class="status-tag status-unverified"><i class="fa-solid fa-circle-xmark"></i> Not Verified or Upgraded</span>';
    }
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage(), 3, '../debug.log');
    header('Location: home.php?error=Database+error');
    ob_end_flush();
    exit;
}

// Check verification or upgrade status
if ($verification_status !== 'verified' && $upgrade_status !== 'upgraded') {
    error_log('User ID: ' . $_SESSION['user_id'] . ' neither verified nor upgraded for withdrawal', 3, '../debug.log');
    header('Location: home.php?error=Please+verify+or+upgrade+your+account+before+withdrawing+funds');
    ob_end_flush();
    exit;
}

// Fetch region settings for labels
try {
    $stmt = $pdo->prepare("
        SELECT section_header, ch_name, ch_value, COALESCE(channel, 'Bank') AS channel, withdraw_currency, rate
        FROM region_settings 
        WHERE country = ?
    ");
    $stmt->execute([$user_country]);
    $region_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($region_settings) {
        $section_header = htmlspecialchars($region_settings['section_header']);
        $ch_name = htmlspecialchars($region_settings['ch_name']);
        $ch_value = htmlspecialchars($region_settings['ch_value']);
        $channel_label = htmlspecialchars($region_settings['channel']);
        $currency_symbol = htmlspecialchars($region_settings['withdraw_currency']);
        $rate = (float)$region_settings['rate'];
    } else {
        // Fallback values if no region settings are found
        $section_header = 'Withdraw Funds';
        $ch_name = 'Bank Name';
        $ch_value = 'Bank Account';
        $channel_label = 'Bank';
        $currency_symbol = '$';
        $rate = 1.0;
        error_log('No region settings found for country: ' . $user_country, 3, '../debug.log');
    }
} catch (PDOException $e) {
    error_log('Region settings fetch error for user ID: ' . $_SESSION['user_id'] . ': ' . $e->getMessage(), 3, '../debug.log');
    // Fallback values on error
    $section_header = 'Withdraw Funds';
    $ch_name = 'Bank Name';
    $ch_value = 'Bank Account';
    $channel_label = 'Bank';
    $currency_symbol = '$';
    $rate = 1.0;
}

// Process withdrawal
$channel = htmlspecialchars($_POST['channel'] ?? '', ENT_QUOTES, 'UTF-8');
$bank_name = htmlspecialchars($_POST['bank_name'] ?? '', ENT_QUOTES, 'UTF-8');
$bank_account = htmlspecialchars($_POST['bank_account'] ?? '', ENT_QUOTES, 'UTF-8');
$amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
$error = null;
$new_balance = $balance; // Default to original balance in case of error

if (!empty($channel) && !empty($bank_name) && !empty($bank_account) && $amount > 0) {
    if ($amount > $balance) {
        error_log('Insufficient balance for user ID: ' . $_SESSION['user_id'] . ', requested: ' . $amount . ', available: ' . $balance, 3, '../debug.log');
        $error = 'Insufficient balance for withdrawal.';
    } elseif ($amount <= 0) {
        error_log('Invalid amount for user ID: ' . $_SESSION['user_id'] . ', amount: ' . $amount, 3, '../debug.log');
        $error = 'Invalid withdrawal amount.';
    } else {
        $converted_amount = $amount * $rate;
        try {
            $pdo->beginTransaction();

            // Deduct raw amount from balance
            $new_balance = $balance - $amount;
            $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $stmt->execute([$new_balance, $_SESSION['user_id']]);

            // Generate unique reference number
            $ref_number = strtoupper(substr(uniqid(), 0, 10));

            // Insert withdrawal record with converted amount and currency
            $stmt = $pdo->prepare("
                INSERT INTO withdrawals (user_id, amount, currency, channel, bank_name, bank_account, ref_number, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$_SESSION['user_id'], $converted_amount, $currency_symbol, $channel, $bank_name, $bank_account, $ref_number]);

            $pdo->commit();
            error_log('Withdrawal request created for user ID: ' . $_SESSION['user_id'] . ', raw amount: ' . $amount . ', converted amount: ' . $converted_amount . ', currency: ' . $currency_symbol . ', channel: ' . $channel, 3, '../debug.log');
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Withdrawal error for user ID: ' . $_SESSION['user_id'] . ': ' . $e->getMessage(), 3, '../debug.log');
            $error = 'An error occurred while processing your withdrawal.';
            $new_balance = $balance; // Reset to original if transaction fails
        }
    }
} else {
    error_log('Invalid withdrawal inputs for user ID: ' . $_SESSION['user_id'], 3, '../debug.log');
    $error = 'Please fill in all required fields.';
}
?>

<?php include('inc/translate.php'); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta name="description" content="Withdrawal receipt for your Illuminate Tube withdrawal." />
    <title>Withdrawal Receipt | Illuminate Tube</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-color: #000000;
            --text-color: #ffffff;
            --accent-color: #22c55e;
            --accent-hover: #16a34a;
            --menu-bg: rgba(17, 24, 39, 0.85);
            --menu-text: #ffffff;
            --subtext-color: #9ca3af;
            --border-color: rgba(255, 255, 255, 0.12);
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
            overflow-x: hidden;
            overflow-y: auto;
            background-color: var(--bg-color);
            color: var(--text-color);
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


        /* Center Layout Wrapper */
        .page-wrapper {
            width: 100%;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 90px 20px 90px 20px;
            background: radial-gradient(circle at center, #111827 0%, #000000 100%);
        }

        /* Card Container styling with standard fit */
        .card-inner {
            width: 100%;
            max-width: 480px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
        }

        .receipt-card h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 16px;
            text-align: center;
            color: var(--accent-color);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .receipt-card .amount {
            font-size: 32px;
            font-weight: 700;
            margin: 16px 0;
            text-align: center;
            color: #ffffff;
        }

        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .receipt-table th,
        .receipt-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .receipt-table th {
            font-weight: 600;
            color: var(--subtext-color);
            width: 45%;
        }

        .receipt-table td {
            font-weight: 500;
            color: #ffffff;
        }

        .back-btn, .print-btn {
            width: 100%;
            padding: 12px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: transform 0.2s ease, background 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .back-btn {
            background: var(--accent-color);
            color: #fff;
        }

        .back-btn:hover {
            background: var(--accent-hover);
        }

        .print-btn {
            background: #3b82f6;
            color: #fff;
        }

        .print-btn:hover {
            background: #2563eb;
        }

        .back-btn:active, .print-btn:active {
            transform: scale(0.96);
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 16px;
        }

        .error {
            text-align: center;
            color: #ef4444;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .notes-section {
            margin-top: 16px;
            font-size: 13px;
            color: var(--subtext-color);
        }

        .notes-section h3 {
            font-size: 15px;
            margin-bottom: 8px;
            color: #ffffff;
        }

        .notes-section ul {
            list-style-type: disc;
            padding-left: 20px;
        }

        .notes-section li {
            margin-bottom: 6px;
        }

        .notes-section a {
            color: var(--accent-color);
            text-decoration: none;
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

        @media (max-width: 480px) {
            .button-group {
                flex-direction: column;
            }
        }

        .status-tag {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-verified {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.4);
        }
        
        .status-upgraded {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.4);
        }
        
        .status-unverified {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.4);
        }
        
    </style>
</head>
<body>

    <!-- Header Overlay -->
    <div class="top-header">
        <div class="user-badge">
            <img src="img/top.png" alt="Logo">
            <div style="display: flex; flex-direction: column; gap: 2px;">
                <span style="font-size: 14px; font-weight: 600;"><?php echo $username; ?></span>
                <?php echo $account_status_badge; ?>
            </div>
        </div>
        <div class="balance-badge">
            $<span id="balance"><?php echo $balance; ?></span>
        </div>
    </div>

    <!-- Container Area -->
    <div class="page-wrapper" role="main">
        <div class="card-inner receipt-card">
            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
                <button class="back-btn" onclick="window.location.href='home.php'"><i class="fa-solid fa-house"></i> Back to Home</button>
            <?php else: ?>
                <h2><i class="fas fa-check-circle"></i> Withdrawal Request Submitted!</h2>
                <div class="amount"><?php echo htmlspecialchars($currency_symbol) . number_format($converted_amount, 2); ?></div>
                <table class="receipt-table">
                    <tr>
                        <th>Original Balance (USD)</th>
                        <td>$<?php echo number_format($balance, 2); ?></td>
                    </tr>
                    <tr>
                        <th>Withdrawn Amount (USD)</th>
                        <td>$<?php echo number_format($amount, 2); ?></td>
                    </tr>
                    <tr>
                        <th>Amount to Receive</th>
                        <td><?php echo htmlspecialchars($currency_symbol) . number_format($converted_amount, 2); ?></td>
                    </tr>
                    <tr>
                        <th>New Balance (USD)</th>
                        <td>$<?php echo number_format($new_balance, 2); ?></td>
                    </tr>
                    <tr>
                        <th>Ref Number</th>
                        <td><?php echo htmlspecialchars($ref_number); ?></td>
                    </tr>
                    <tr>
                        <th>Request Time</th>
                        <td><?php echo gmdate('F j, Y, g:i A'); ?> UTC</td>
                    </tr>
                    <tr>
                        <th><?php echo htmlspecialchars($channel_label); ?></th>
                        <td><?php echo htmlspecialchars($channel); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo htmlspecialchars($ch_name); ?></th>
                        <td><?php echo htmlspecialchars($bank_name); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo htmlspecialchars($ch_value); ?></th>
                        <td><?php echo htmlspecialchars($bank_account); ?></td>
                    </tr>
                    <tr>
                        <th>From</th>
                        <td>Illumnate Tube</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>Pending</td>
                    </tr>
                </table>
                <div class="notes-section">
                    <h3>Important Notes:</h3>
                    <ul>
                        <li>Your withdrawal request is pending approval and will be processed within 2 hours.</li>
                        <li>Please ensure your bank details are correct to avoid delays.</li>
                        <li>If you have any questions, contact support via our <a href="support.php">support page</a>.</li>
                        <li>Conversion rates are based on current market values and may vary slightly upon processing.</li>
                    </ul>
                </div>
                <div class="button-group">
                    <button class="back-btn" onclick="window.location.href='home.php'"><i class="fa-solid fa-house"></i> Home</button>
                    <button class="print-btn" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="notificationContainer"></div>

    <!-- Fixed Bottom Menu -->
    <div class="bottom-menu" role="navigation">
        <a href="home.php"><i class="fa-solid fa-house"></i>Home</a>
        <a href="profile.php"><i class="fa-solid fa-money-bill"></i>Withdraw</a>
        <a href="history.php" class="active"><i class="fa-solid fa-clock-rotate-left"></i>History</a>
        <a href="support.php"><i class="fa-solid fa-headset"></i>Support</a>
        <button id="logoutBtn" aria-label="Log out"><i class="fa-solid fa-right-from-bracket"></i>Logout</button>
    </div>

    <script>
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
                        notification.className = 'notification';
                        notification.setAttribute('role', 'alert');
                        notification.innerHTML = `<span>${message.text}</span>`;
                        notificationContainer.appendChild(notification);
                        notification.style.top = `${70 + index * 60}px`;
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

        // Context Menu Disable
        document.addEventListener('contextmenu', function(event) {
            event.preventDefault();
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
