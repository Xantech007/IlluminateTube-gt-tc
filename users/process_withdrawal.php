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
        ob_end_flush();
        exit;
    }

    $username = htmlspecialchars($user['name']);
    $balance = $user['balance'];
    $verification_status = $user['verification_status'];
    $upgrade_status = $user['upgrade_status'] ?? 'not_upgraded';
    $user_country = htmlspecialchars($user['country']);

    // Generate account status badge markup
    if ($verification_status === 'verified') {
        $account_status_badge = '<span class="status-tag status-verified"><i class="fa-solid fa-circle-check"></i> Account Verified</span>';
    } elseif ($upgrade_status === 'upgraded') {
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
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&family=Inter:wght@400;500;600;700&family=Libre+Barcode+128&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-color: #0b0f19;
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
            padding: 90px 20px 100px 20px;
            background: radial-gradient(circle at center, #111827 0%, #000000 100%);
        }

        /* Paper Receipt Styling */
        .receipt-paper-wrapper {
            width: 100%;
            max-width: 420px;
            filter: drop-shadow(0px 15px 25px rgba(0, 0, 0, 0.6));
            margin: 0 auto;
        }

        .receipt-paper {
            background: #fdfbf7;
            color: #1a1a1a;
            font-family: 'Courier Prime', monospace;
            padding: 30px 24px 25px 24px;
            position: relative;
            background-image: linear-gradient(rgba(0,0,0,0.01) 1px, transparent 0);
            background-size: 100% 2em;
        }

        /* Jagged Sawtooth Edges */
        .receipt-paper::before,
        .receipt-paper::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            height: 10px;
            background-size: 16px 10px;
        }

        .receipt-paper::before {
            top: -10px;
            background-image: linear-gradient(135deg, #fdfbf7 50%, transparent 50%), linear-gradient(225deg, #fdfbf7 50%, transparent 50%);
        }

        .receipt-paper::after {
            bottom: -10px;
            background-image: linear-gradient(45deg, #fdfbf7 50%, transparent 50%), linear-gradient(315deg, #fdfbf7 50%, transparent 50%);
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .receipt-header img {
            width: 45px;
            height: 45px;
            filter: grayscale(100%) contrast(150%);
            margin-bottom: 8px;
        }

        .receipt-header h1 {
            font-family: 'Inter', sans-serif;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #111;
        }

        .receipt-header p {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .receipt-divider {
            border-top: 1px dashed #333;
            margin: 14px 0;
        }

        .receipt-title {
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 14px;
            margin: 10px 0;
            letter-spacing: 1px;
        }

        .receipt-amount-box {
            text-align: center;
            margin: 16px 0;
            padding: 10px 0;
            background: rgba(0, 0, 0, 0.03);
            border: 1px dashed #999;
        }

        .receipt-amount-box .label {
            font-size: 10px;
            text-transform: uppercase;
            color: #555;
            letter-spacing: 0.5px;
        }

        .receipt-amount-box .value {
            font-size: 28px;
            font-weight: 700;
            color: #000;
            margin-top: 2px;
        }

        .receipt-details {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .receipt-details tr td {
            padding: 5px 0;
            vertical-align: top;
        }

        .receipt-details tr td:first-child {
            color: #555;
            text-transform: uppercase;
            white-space: nowrap;
            padding-right: 12px;
        }

        .receipt-details tr td:last-child {
            text-align: right;
            font-weight: 700;
            color: #111;
            word-break: break-all;
        }

        .receipt-status-badge {
            display: inline-block;
            padding: 2px 6px;
            border: 1px solid #d97706;
            color: #d97706;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-radius: 2px;
        }

        .receipt-notes {
            font-size: 10px;
            color: #555;
            margin-top: 15px;
            line-height: 1.4;
        }

        .receipt-notes h4 {
            font-size: 10px;
            text-transform: uppercase;
            color: #111;
            margin-bottom: 4px;
        }

        .receipt-notes ul {
            padding-left: 14px;
        }

        .receipt-notes li {
            margin-bottom: 3px;
        }

        .receipt-barcode {
            text-align: center;
            margin-top: 20px;
            padding-top: 10px;
        }

        .receipt-barcode .barcode-font {
            font-family: 'Libre Barcode 128', cursive;
            font-size: 42px;
            line-height: 1;
            letter-spacing: 2px;
        }

        .receipt-barcode p {
            font-size: 10px;
            letter-spacing: 2px;
            color: #444;
        }

        .receipt-footer-msg {
            text-align: center;
            font-size: 11px;
            margin-top: 15px;
            color: #444;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Action Buttons */
        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .back-btn, .print-btn {
            flex: 1;
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
            font-family: 'Inter', sans-serif;
        }

        .back-btn {
            background: #374151;
            color: #fff;
        }

        .back-btn:hover {
            background: #4b5563;
        }

        .print-btn {
            background: var(--accent-color);
            color: #fff;
        }

        .print-btn:hover {
            background: var(--accent-hover);
        }

        .back-btn:active, .print-btn:active {
            transform: scale(0.97);
        }

        .error {
            text-align: center;
            color: #ef4444;
            margin-bottom: 20px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
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

        /* Print Media Queries */
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
            }
            .top-header, .bottom-menu, .button-group, #notificationContainer {
                display: none !important;
            }
            .page-wrapper {
                padding: 0 !important;
                background: none !important;
                min-height: auto !important;
            }
            .receipt-paper-wrapper {
                filter: none !important;
                max-width: 100% !important;
            }
            .receipt-paper {
                background: #ffffff !important;
                padding: 10px !important;
            }
            .receipt-paper::before, .receipt-paper::after {
                display: none !important;
            }
        }

        @media (max-width: 480px) {
            .button-group {
                flex-direction: column;
            }
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
        <div class="receipt-paper-wrapper">
            <?php if ($error): ?>
                <div style="background: rgba(255,255,255,0.05); padding: 24px; border-radius: 16px; border: 1px solid var(--border-color);">
                    <p class="error"><?php echo htmlspecialchars($error); ?></p>
                    <button class="back-btn" style="width:100%;" onclick="window.location.href='home.php'"><i class="fa-solid fa-house"></i> Back to Home</button>
                </div>
            <?php else: ?>
                <div class="receipt-paper">
                    <div class="receipt-header">
                        <img src="img/top.png" alt="Illuminate Tube Logo">
                        <h1>Illuminate Tube</h1>
                        <p>Official Transaction Receipt</p>
                    </div>

                    <div class="receipt-divider"></div>

                    <div class="receipt-title">Withdrawal Successful</div>

                    <div class="receipt-amount-box">
                        <div class="label">Amount to Receive</div>
                        <div class="value"><?php echo htmlspecialchars($currency_symbol) . number_format($converted_amount, 2); ?></div>
                    </div>

                    <div class="receipt-divider"></div>

                    <table class="receipt-details">
                        <tr>
                            <td>Ref Number:</td>
                            <td><?php echo htmlspecialchars($ref_number); ?></td>
                        </tr>
                        <tr>
                            <td>Date / Time:</td>
                            <td><?php echo gmdate('Y-m-d H:i'); ?> UTC</td>
                        </tr>
                        <tr>
                            <td>Status:</td>
                            <td><span class="receipt-status-badge">Pending</span></td>
                        </tr>
                        <tr>
                            <td>Account Name:</td>
                            <td><?php echo $username; ?></td>
                        </tr>
                        <tr>
                            <td><?php echo htmlspecialchars($channel_label); ?>:</td>
                            <td><?php echo htmlspecialchars($channel); ?></td>
                        </tr>
                        <tr>
                            <td><?php echo htmlspecialchars($ch_name); ?>:</td>
                            <td><?php echo htmlspecialchars($bank_name); ?></td>
                        </tr>
                        <tr>
                            <td><?php echo htmlspecialchars($ch_value); ?>:</td>
                            <td><?php echo htmlspecialchars($bank_account); ?></td>
                        </tr>
                    </table>

                    <div class="receipt-divider"></div>

                    <table class="receipt-details">
                        <tr>
                            <td>Withdrawn (USD):</td>
                            <td>$<?php echo number_format($amount, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Original Balance:</td>
                            <td>$<?php echo number_format($balance, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Remaining Balance:</td>
                            <td>$<?php echo number_format($new_balance, 2); ?></td>
                        </tr>
                    </table>

                    <div class="receipt-divider"></div>

                    <div class="receipt-notes">
                        <h4>Important Notice:</h4>
                        <ul>
                            <li>Your withdrawal request is pending approval and will be processed within 2 hours.</li>
                            <li>Ensure bank details are correct to prevent payout delays.</li>
                            <li>For help, visit our <a href="support.php" style="color:#000; font-weight: bold;">support page</a>.</li>
                        </ul>
                    </div>

                    <div class="receipt-barcode">
                        <div class="barcode-font">*<?php echo htmlspecialchars($ref_number); ?>*</div>
                        <p><?php echo htmlspecialchars($ref_number); ?></p>
                    </div>

                    <div class="receipt-footer-msg">
                        Thank you for using Illuminate Tube!
                    </div>
                </div>

                <div class="button-group">
                    <button class="back-btn" onclick="window.location.href='home.php'"><i class="fa-solid fa-house"></i> Home</button>
                    <button class="print-btn" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Receipt</button>
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
