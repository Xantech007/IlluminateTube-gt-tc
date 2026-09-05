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
        $account_status_badge = '<span class="status-pill status-verified"><i class="fa-solid fa-circle-check"></i> Verified Account</span>';
    } elseif ($upgrade_status === 'upgraded') {
        $account_status_badge = '<span class="status-pill status-upgraded"><i class="fa-solid fa-circle-up"></i> Upgraded</span>';
    } else {
        $account_status_badge = '<span class="status-pill status-unverified"><i class="fa-solid fa-circle-xmark"></i> Unverified</span>';
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
$new_balance = $balance;

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

            $new_balance = $balance - $amount;
            $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $stmt->execute([$new_balance, $_SESSION['user_id']]);

            $ref_number = strtoupper(substr(uniqid(), 0, 10));

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
            $new_balance = $balance;
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
    
    <!-- Modern Typography & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --bg-dark: #080b11;
            --surface-card: rgba(18, 24, 38, 0.75);
            --surface-glass: rgba(255, 255, 255, 0.03);
            --border-glow: rgba(255, 255, 255, 0.08);
            --border-accent: rgba(34, 197, 94, 0.3);
            
            --accent-emerald: #10b981;
            --accent-emerald-glow: rgba(16, 185, 129, 0.25);
            --accent-cyan: #06b6d4;
            --accent-blue: #3b82f6;
            
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            
            --font-main: 'Plus Jakarta Sans', sans-serif;
            --font-heading: 'Outfit', sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: var(--font-main);
        }

        body {
            width: 100%;
            min-height: 100vh;
            background-color: var(--bg-dark);
            color: var(--text-primary);
            background-image: 
                radial-gradient(circle at 50% 0%, rgba(16, 185, 129, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(59, 130, 246, 0.05) 0%, transparent 40%);
            background-attachment: fixed;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        /* Top Header Navigation Bar */
        .top-header {
            position: sticky;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 24px;
            background: rgba(8, 11, 17, 0.8);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-glow);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar-wrapper {
            position: relative;
        }

        .avatar-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-emerald);
            box-shadow: 0 0 10px var(--accent-emerald-glow);
        }

        .user-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .user-name {
            font-family: var(--font-heading);
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            letter-spacing: -0.2px;
        }

        .status-pill {
            font-size: 10px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .status-verified {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-upgraded {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .status-unverified {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .balance-chip {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(6, 182, 212, 0.05));
            border: 1px solid var(--border-accent);
            padding: 8px 16px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .balance-label {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
        }

        .balance-amount {
            font-family: var(--font-heading);
            font-size: 15px;
            font-weight: 700;
            color: #10b981;
        }

        /* Page Layout Center */
        .page-wrapper {
            flex: 1;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px 110px 20px;
        }

        /* Receipt Card Styling */
        .receipt-card {
            width: 100%;
            max-width: 520px;
            background: var(--surface-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-glow);
            border-radius: 28px;
            padding: 32px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
            position: relative;
            overflow: hidden;
            animation: cardAppear 0.4s ease-out forwards;
        }

        .receipt-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #10b981, #06b6d4, #3b82f6);
        }

        @keyframes cardAppear {
            from { opacity: 0; transform: translateY(20px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .status-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .status-icon-ring {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px auto;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-emerald);
            font-size: 28px;
            box-shadow: 0 0 20px var(--accent-emerald-glow);
        }

        .status-header h2 {
            font-family: var(--font-heading);
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.3px;
        }

        .status-header p {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        /* Main Display Amount */
        .payout-box {
            background: var(--surface-glass);
            border: 1px solid var(--border-glow);
            border-radius: 18px;
            padding: 20px;
            text-align: center;
            margin-bottom: 24px;
        }

        .payout-box .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .payout-box .value {
            font-family: var(--font-heading);
            font-size: 36px;
            font-weight: 800;
            color: #34d399;
            letter-spacing: -0.5px;
        }

        /* Receipt Breakdown Rows */
        .receipt-details {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.08);
            font-size: 14px;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-key {
            color: var(--text-secondary);
            font-weight: 400;
        }

        .detail-value {
            color: var(--text-primary);
            font-weight: 600;
            text-align: right;
        }

        .badge-pending {
            background: rgba(234, 179, 8, 0.15);
            color: #facc15;
            border: 1px solid rgba(234, 179, 8, 0.3);
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 12px;
        }

        /* Information / Notice Box */
        .notice-card {
            background: rgba(30, 41, 59, 0.4);
            border-left: 3px solid var(--accent-cyan);
            border-radius: 0 12px 12px 0;
            padding: 14px 16px;
            margin-bottom: 24px;
        }

        .notice-card h4 {
            font-size: 13px;
            color: #38bdf8;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .notice-card ul {
            list-style: none;
            padding: 0;
        }

        .notice-card li {
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 4px;
            position: relative;
            padding-left: 12px;
        }

        .notice-card li::before {
            content: "•";
            color: var(--accent-cyan);
            position: absolute;
            left: 0;
            font-weight: bold;
        }

        .notice-card a {
            color: #38bdf8;
            text-decoration: underline;
        }

        /* Action Buttons */
        .action-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .btn-action {
            height: 48px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-emerald), #059669);
            color: #ffffff;
            box-shadow: 0 4px 20px var(--accent-emerald-glow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(16, 185, 129, 0.35);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
            border: 1px solid var(--border-glow);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .btn-action:active {
            transform: scale(0.97);
        }

        /* Error Box State */
        .error-container {
            text-align: center;
            padding: 20px 0;
        }

        .error-icon {
            font-size: 48px;
            color: #ef4444;
            margin-bottom: 12px;
        }

        .error-msg {
            color: #f87171;
            font-size: 15px;
            margin-bottom: 20px;
        }

        /* Notification Toast Container */
        .toast-wrapper {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .notification {
            background: rgba(15, 23, 42, 0.95);
            color: var(--text-primary);
            padding: 14px 20px;
            border-radius: 14px;
            border: 1px solid var(--accent-emerald);
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            backdrop-filter: blur(10px);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideInRight 0.4s cubic-bezier(0.16, 1, 0.3, 1), fadeOut 0.5s ease-out 3s forwards;
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(50px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes fadeOut {
            to { opacity: 0; transform: translateY(-10px); }
        }

        /* Bottom Fixed Navigation Bar */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 70px;
            background: rgba(8, 11, 17, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-top: 1px solid var(--border-glow);
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 100;
            padding: 0 10px;
        }

        .nav-item {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 11px;
            font-weight: 500;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .nav-item i {
            font-size: 18px;
        }

        .nav-item.active,
        .nav-item:hover {
            color: var(--accent-emerald);
        }

        .nav-item.active {
            background: rgba(16, 185, 129, 0.08);
        }

        /* Print Layout Rules */
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
            }

            .top-header, .bottom-nav, .action-grid, .notice-card {
                display: none !important;
            }

            .page-wrapper {
                padding: 0 !important;
            }

            .receipt-card {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
                background: #ffffff !important;
                color: #000000 !important;
                max-width: 100% !important;
            }

            .status-header h2, .payout-box .value, .detail-value {
                color: #000000 !important;
            }

            .payout-box {
                background: #f8fafc !important;
                border: 1px solid #e2e8f0 !important;
            }

            .detail-key {
                color: #475569 !important;
            }
        }

        @media (max-width: 480px) {
            .receipt-card {
                padding: 24px 20px;
            }

            .action-grid {
                grid-template-columns: 1fr;
            }

            .payout-box .value {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>

    <!-- Header Overlay -->
    <header class="top-header">
        <div class="user-profile">
            <div class="avatar-wrapper">
                <img src="img/top.png" alt="Logo" class="avatar-img" />
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo $username; ?></span>
                <?php echo $account_status_badge; ?>
            </div>
        </div>
        <div class="balance-chip">
            <span class="balance-label">Balance</span>
            <span class="balance-amount">$<span id="balance"><?php echo number_format($balance, 2); ?></span></span>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="page-wrapper" role="main">
        <div class="receipt-card">
            <?php if ($error): ?>
                <div class="error-container">
                    <i class="fa-solid fa-circle-exclamation error-icon"></i>
                    <p class="error-msg"><?php echo htmlspecialchars($error); ?></p>
                    <button class="btn-action btn-primary" style="width: 100%;" onclick="window.location.href='home.php'">
                        <i class="fa-solid fa-house"></i> Return to Dashboard
                    </button>
                </div>
            <?php else: ?>
                <div class="status-header">
                    <div class="status-icon-ring">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <h2>Withdrawal Submitted</h2>
                    <p>Your payout request is currently processing</p>
                </div>

                <div class="payout-box">
                    <div class="label">Amount to Receive</div>
                    <div class="value"><?php echo htmlspecialchars($currency_symbol) . number_format($converted_amount, 2); ?></div>
                </div>

                <div class="receipt-details">
                    <div class="detail-row">
                        <span class="detail-key">Reference Code</span>
                        <span class="detail-value" style="font-family: monospace; letter-spacing: 0.5px;"><?php echo htmlspecialchars($ref_number); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-key">Original Amount</span>
                        <span class="detail-value">$<?php echo number_format($amount, 2); ?> USD</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-key">New USD Balance</span>
                        <span class="detail-value">$<?php echo number_format($new_balance, 2); ?> USD</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-key">Payout Method</span>
                        <span class="detail-value"><?php echo htmlspecialchars($channel); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-key"><?php echo htmlspecialchars($ch_name); ?></span>
                        <span class="detail-value"><?php echo htmlspecialchars($bank_name); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-key"><?php echo htmlspecialchars($ch_value); ?></span>
                        <span class="detail-value"><?php echo htmlspecialchars($bank_account); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-key">Submitted On</span>
                        <span class="detail-value"><?php echo gmdate('M j, Y • g:i A'); ?> UTC</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-key">Status</span>
                        <span class="detail-value"><span class="badge-pending"><i class="fa-solid fa-clock"></i> Pending Approval</span></span>
                    </div>
                </div>

                <div class="notice-card">
                    <h4><i class="fa-solid fa-circle-info"></i> Important Information</h4>
                    <ul>
                        <li>Withdrawals are typically reviewed and settled within 2 hours.</li>
                        <li>Verify your banking credentials to prevent potential delays.</li>
                        <li>Need assistance? Reach out via our <a href="support.php">Support Portal</a>.</li>
                    </ul>
                </div>

                <div class="action-grid">
                    <button class="btn-action btn-secondary" onclick="window.print()">
                        <i class="fa-solid fa-print"></i> Save Receipt
                    </button>
                    <button class="btn-action btn-primary" onclick="window.location.href='home.php'">
                        <i class="fa-solid fa-house"></i> Home
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div id="notificationContainer" class="toast-wrapper"></div>

    <!-- Bottom Navigation Bar -->
    <nav class="bottom-nav" role="navigation">
        <a href="home.php" class="nav-item">
            <i class="fa-solid fa-house"></i>
            <span>Home</span>
        </a>
        <a href="profile.php" class="nav-item">
            <i class="fa-solid fa-wallet"></i>
            <span>Withdraw</span>
        </a>
        <a href="history.php" class="nav-item active">
            <i class="fa-solid fa-receipt"></i>
            <span>History</span>
        </a>
        <a href="support.php" class="nav-item">
            <i class="fa-solid fa-headset"></i>
            <span>Support</span>
        </a>
        <button id="logoutBtn" class="nav-item" aria-label="Log out">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </button>
    </nav>

    <script>
        // Logout confirmation dialog
        document.getElementById('logoutBtn').addEventListener('click', () => {
            Swal.fire({
                title: 'Sign out of account?',
                text: 'You will need to log back in to access your dashboard.',
                icon: 'warning',
                background: '#121826',
                color: '#f8fafc',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#334155',
                confirmButtonText: 'Yes, Sign Out'
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
                                    title: 'Action Failed',
                                    text: 'Failed to log out. Please try again.',
                                    background: '#121826',
                                    color: '#f8fafc'
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Server Unreachable',
                                text: 'An error occurred while terminating your session.',
                                background: '#121826',
                                color: '#f8fafc'
                            });
                        }
                    });
                }
            });
        });

        // Dynamic Toast Notifications
        const notificationContainer = document.getElementById('notificationContainer');
        function fetchNotifications() {
            $.ajax({
                url: 'fetch_notifications.php',
                type: 'GET',
                dataType: 'json',
                success: function(notifications) {
                    notificationContainer.innerHTML = '';
                    notifications.forEach((message) => {
                        const notification = document.createElement('div');
                        notification.className = 'notification';
                        notification.setAttribute('role', 'alert');
                        notification.innerHTML = `<i class="fa-solid fa-bell" style="color: #10b981;"></i> <span>${message.text}</span>`;
                        notificationContainer.appendChild(notification);
                        setTimeout(() => notification.remove(), 3500);
                    });
                },
                error: function() {
                    console.error('Failed to retrieve notifications');
                }
            });
        }

        fetchNotifications();
        setInterval(fetchNotifications, 20000);

        // Context Menu Safeguard
        document.addEventListener('contextmenu', function(event) {
            event.preventDefault();
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
