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

error_log('Session ID in change_passcode.php: ' . session_id() . ', User ID: ' . ($_SESSION['user_id'] ?? 'not set'), 3, '../debug.log');

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

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT name, balance FROM users WHERE id = ?");
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
    $balance = number_format($user['balance'] ?? 0, 2);
} catch (PDOException $e) {
    error_log('Database error in change_passcode.php: ' . $e->getMessage(), 3, '../debug.log');
    if (file_exists('../error.php')) {
        include '../error.php';
    } else {
        echo 'Database error occurred: ' . htmlspecialchars($e->getMessage());
    }
    ob_end_flush();
    exit;
}

$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : null;
$error_message   = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : null;
?>

<?php include('inc/translate.php'); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Change Password | Illuminate Tube</title>
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
            top: 62px;
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
            padding: 80px 20px 210px 20px;
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

        .input-container input {
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

        .input-container input:focus {
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

        .submit-btn {
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

        .submit-btn:active {
            transform: scale(0.96);
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

        .notification.error {
            border-color: #ef4444;
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
        <div class="notification error" role="alert">
            <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i>
            <span><?php echo $error_message; ?></span>
        </div>
    <?php endif; ?>

    <!-- Scrollable TikTok Snap Feed -->
    <div class="tiktok-feed" id="tiktokFeed">

        <!-- Passcode Change Card Slide -->
        <div class="profile-card-slide">
            <div class="card-inner">
                <h2><i class="fa-solid fa-key"></i> Change Password</h2>
                <form id="passwordForm" action="process_change_password.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="input-container">
                        <label for="old_password">Old Password</label>
                        <input type="password" id="old_password" name="old_password" required>
                    </div>

                    <div class="input-container">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>

                    <div class="input-container">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fa-solid fa-lock"></i> Change Password
                    </button>
                </form>
            </div>
        </div>

    </div>

    <div id="notificationContainer"></div>

    <!-- Fixed Bottom Menu -->
    <div class="bottom-menu" role="navigation">
        <a href="home.php"><i class="fa-solid fa-house"></i>Home</a>
        <a href="profile.php" class="active"><i class="fa-solid fa-user"></i>Profile</a>
        <a href="history.php"><i class="fa-solid fa-clock-rotate-left"></i>History</a>
        <a href="support.php"><i class="fa-solid fa-headset"></i>Support</a>
        <button id="logoutBtn" aria-label="Log out"><i class="fa-solid fa-right-from-bracket"></i>Logout</button>
    </div>

    <script>
        // Password Change Form AJAX Handler
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const oldPassword = document.getElementById('old_password').value.trim();
            const newPassword = document.getElementById('new_password').value.trim();
            const confirmPassword = document.getElementById('confirm_password').value.trim();

            if (!oldPassword) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Please enter your current password.'
                });
                return;
            }

            if (newPassword.length < 1) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'New password must be at least 1 character long.'
                });
                return;
            }

            if (newPassword !== confirmPassword) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'New password and confirmation do not match.'
                });
                return;
            }

            $.ajax({
                url: 'process_change_password.php',
                type: 'POST',
                data: { 
                    old_password: oldPassword, 
                    new_password: newPassword, 
                    confirm_password: confirmPassword 
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Password updated successfully!',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = 'change_passcode.php?success=Password changed successfully';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: res.error || 'Something went wrong.'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Server error. Try again.'
                    });
                }
            });
        });

        // Logout Handling
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
                        },
                        error: function() {
                            window.location.href = '../signin.php';
                        }
                    });
                }
            });
        });

        // Notifications Polling
        const notificationContainer = document.getElementById('notificationContainer');
        function fetchNotifications() {
            $.ajax({
                url: 'fetch_notifications.php',
                type: 'GET',
                dataType: 'json',
                success: function(notifications) {
                    notificationContainer.innerHTML = '';
                    (notifications || []).forEach((n, index) => {
                        const notification = document.createElement('div');
                        notification.className = `notification ${n.type || 'success'}`;
                        notification.innerHTML = `<span>${n.text}</span>`;
                        notificationContainer.appendChild(notification);
                        notification.style.top = `${70 + index * 60}px`;
                        setTimeout(() => notification.remove(), 4000);
                    });
                }
            });
        }
        fetchNotifications();
        setInterval(fetchNotifications, 20000);

        // Prevent Context Menu
        document.addEventListener('contextmenu', function(event) {
            event.preventDefault();
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
