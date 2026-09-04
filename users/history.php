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
error_log('Session ID in history.php: ' . session_id() . ', User ID: ' . ($_SESSION['user_id'] ?? 'not set'), 3, '../debug.log');

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
    error_log('Database error in history.php: ' . $e->getMessage(), 3, '../debug.log');
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
    error_log('Region settings fetch error in history.php: ' . $e->getMessage(), 3, '../debug.log');
    $ch_name = 'Bank Name';
    $ch_value = 'Bank Account';
    $channel_label = 'Bank';
}

// Fetch activity and withdrawal history
try {
    $stmt = $pdo->prepare("
        SELECT action, amount, created_at, NULL AS ref_number, NULL AS status, 
               NULL AS channel, NULL AS bank_name, NULL AS bank_account, 'activity' AS source, NULL AS currency
        FROM activities 
        WHERE user_id = ?
        UNION ALL
        SELECT 'Withdrawal' AS action, amount, created_at, ref_number, status, 
               channel, bank_name, bank_account, 'withdrawal' AS source, currency
        FROM withdrawals 
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log('Fetched ' . count($history) . ' history records for user ID: ' . $_SESSION['user_id'], 3, '../debug.log');
} catch (PDOException $e) {
    error_log('History fetch error in history.php: ' . $e->getMessage(), 3, '../debug.log');
    $history = [];
}
?>

<?php include('inc/translate.php'); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta name="description" content="View your activity and withdrawal history, including video watches and withdrawals." />
    <title>History | Cash Tube</title>
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
            --status-completed: #22c55e;
            --status-pending: #eab308;
            --status-rejected: #ef4444;
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
            top: 0;
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
            align-items: flex-start;
            padding: 70px 20px 85px 20px;
            background: radial-gradient(circle at center, #111827 0%, #000000 100%);
        }

        /* Card Container styling */
        .card-inner {
            width: 100%;
            max-width: 550px;
            max-height: calc(100vh - 155px);
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 24px;
            padding: 20px;
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
            flex-shrink: 0;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
            overflow-y: auto;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.3);
        }

        .table-container::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }

        .history-table {
            width: 100%;
            min-width: 480px;
            border-collapse: collapse;
            font-size: 13px;
        }

        .history-table th,
        .history-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .history-table th {
            font-weight: 600;
            color: var(--subtext-color);
            position: sticky;
            top: 0;
            background: #111827;
            z-index: 1;
        }

        .history-table td {
            font-weight: 400;
            color: #ffffff;
        }

        .history-table .amount {
            font-weight: 700;
            color: #4ade80;
        }

        .status-box {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            color: #fff;
            text-align: center;
        }

        .status-completed {
            background-color: rgba(34, 197, 94, 0.2);
            color: #4ade80;
            border: 1px solid #22c55e;
        }

        .status-pending {
            background-color: rgba(234, 179, 8, 0.2);
            color: #facc15;
            border: 1px solid #eab308;
        }

        .status-rejected {
            background-color: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid #ef4444;
        }

        .no-data {
            text-align: center;
            color: var(--subtext-color);
            padding: 30px 10px;
            font-size: 14px;
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

    <!-- Scrollable TikTok Snap Feed -->
    <div class="tiktok-feed" id="tiktokFeed">
        
        <!-- Slide 1: Activity & Withdrawal History -->
        <div class="profile-card-slide">
            <div class="card-inner">
                <h2><i class="fa-solid fa-clock-rotate-left"></i> History Log</h2>
                <?php if ($history): ?>
                    <div class="table-container">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['action']); ?></td>
                                        <td>
                                            <?php if ($item['source'] === 'withdrawal'): ?>
                                                <strong><?php echo htmlspecialchars($channel_label); ?>:</strong> <?php echo htmlspecialchars($item['channel']); ?><br>
                                                <strong><?php echo htmlspecialchars($ch_name); ?>:</strong> <?php echo htmlspecialchars($item['bank_name']); ?><br>
                                                <strong><?php echo htmlspecialchars($ch_value); ?>:</strong> <?php echo htmlspecialchars($item['bank_account']); ?><br>
                                                <small style="color: var(--subtext-color);">Ref: <?php echo htmlspecialchars($item['ref_number']); ?></small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="amount">
                                            <?php
                                            $currency = $item['source'] === 'withdrawal' && !empty($item['currency']) ? htmlspecialchars($item['currency']) : '$';
                                            echo $currency . number_format($item['amount'], 2);
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($item['source'] === 'withdrawal'): ?>
                                                <?php
                                                $status = $item['status'];
                                                $display_status = $status === 'approved' ? 'Completed' : ($status === 'rejected' ? 'Rejected' : 'Pending');
                                                $status_class = $status === 'approved' ? 'status-completed' : ($status === 'rejected' ? 'status-rejected' : 'status-pending');
                                                ?>
                                                <span class="status-box <?php echo $status_class; ?>">
                                                    <?php echo htmlspecialchars($display_status); ?>
                                                </span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?php echo gmdate('M j, Y H:i', strtotime($item['created_at'])) . ' UTC'; ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="no-data"><i class="fa-solid fa-box-open" style="font-size: 24px; margin-bottom: 8px; display: block;"></i>No activity or withdrawal history available.</p>
                <?php endif; ?>
            </div>
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
