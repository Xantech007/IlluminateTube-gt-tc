<?php
session_start();
require_once '../database/conn.php';
date_default_timezone_set('Africa/Lagos');
if (!isset($_SESSION['user_id'])) {
    header('Location: ../signin.php');
    exit;
}
try {
    $stmt = $pdo->prepare("SELECT name, email, upgrade_status, country FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        session_destroy();
        header('Location: ../signin.php');
        exit;
    }
    $username = htmlspecialchars($user['name']);
    $email = htmlspecialchars($user['email']);
    $upgrade_status = $user['upgrade_status'] ?? 'not_upgraded';
    $user_country = htmlspecialchars($user['country']);
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage(), 3, '../debug.log');
    header('Location: ../signin.php?error=database');
    exit;
}
// === FETCH SETTINGS + IMAGE ===
$region_image = '';
try {
    $stmt = $pdo->prepare("
        SELECT crypto, account_upgrade, verify_ch, vc_value, verify_ch_name, verify_ch_value,
               COALESCE(verify_medium, 'Payment Method') AS verify_medium,
               vcn_value, vcv_value, verify_currency, verify_amount,
               images
        FROM region_settings
        WHERE country = ?
    ");
    $stmt->execute([$user_country]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
  
    if ($settings && !empty($settings['images'])) {
        $region_image = htmlspecialchars(trim($settings['images']));
    }
    if (!$settings || empty($settings['account_upgrade'])) {
        $error = 'Account upgrade settings not found for your country. Please contact support.';
        $crypto = 0;
        $account_upgrade = 'Payment Method';
        $verify_ch = 'Payment Method';
        $vc_value = 'Obi Mikel';
        $verify_ch_name = 'Account Name';
        $verify_ch_value = 'Account Number';
        $verify_medium = 'Payment Method';
        $vcn_value = 'First Bank';
        $vcv_value = '8012345678';
        $verify_currency = 'NGN';
        $verify_amount = 0.00;
        error_log('No account upgrade settings found for country: ' . $user_country, 3, '../debug.log');
    } else {
        $crypto = $settings['crypto'] ?? 0;
        $account_upgrade = htmlspecialchars($settings['account_upgrade'] ?: 'Payment Method');
        $verify_ch = htmlspecialchars($settings['verify_ch'] ?: 'Payment Method');
        $vc_value = htmlspecialchars($settings['vc_value'] ?: 'Obi Mikel');
        $verify_ch_name = htmlspecialchars($settings['verify_ch_name'] ?: 'Account Name');
        $verify_ch_value = htmlspecialchars($settings['verify_ch_value'] ?: 'Account Number');
        $verify_medium = htmlspecialchars($settings['verify_medium'] ?: 'Payment Method');
        $vcn_value = htmlspecialchars($settings['vcn_value'] ?: 'First Bank');
        $vcv_value = htmlspecialchars($settings['vcv_value'] ?: '8012345678');
        $verify_currency = htmlspecialchars($settings['verify_currency'] ?: 'NGN');
        $verify_amount = floatval($settings['verify_amount'] ?: 0.00);
    }
} catch (PDOException $e) {
    error_log('Settings fetch error: ' . $e->getMessage(), 3, '../debug.log');
    $error = 'Failed to load upgrade settings. Please try again later.';
    $crypto = 0;
    $account_upgrade = 'Payment Method';
    $verify_ch = 'Payment Method';
    $vc_value = 'Obi Mikel';
    $verify_ch_name = 'Account Name';
    $verify_ch_value = 'Account Number';
    $verify_medium = 'Payment Method';
    $vcn_value = 'First Bank';
    $vcv_value = '8012345678';
    $verify_currency = 'NGN';
    $verify_amount = 0.00;
}
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proof_file = $_FILES['proof_file'] ?? null;
    if (!$proof_file || $proof_file['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Please upload a payment receipt.';
    } else {
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 5 * 1024 * 1024;
        if (!in_array($proof_file['type'], $allowed_types) || $proof_file['size'] > $max_size) {
            $error = 'Invalid file type or size. Please upload a JPG or PNG file (max 5MB).';
        } else {
            $upload_dir = '../users/proofs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_ext = pathinfo($proof_file['name'], PATHINFO_EXTENSION);
            $file_name = 'upgrade_proof_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $file_name;
            if (move_uploaded_file($proof_file['tmp_name'], $upload_path)) {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("UPDATE users SET upgrade_status = 'pending' WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $stmt = $pdo->prepare("
                        INSERT INTO upgrade_requests
                        (user_id, payment_amount, name, email, upload_path, file_name, status, payment_method, currency)
                        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'], $verify_amount, $username, $email,
                        $upload_path, $file_name, $account_upgrade, $verify_currency
                    ]);
                    $pdo->commit();
                    header('Location: home.php?success=Upgrade+request+submitted+successfully');
                    exit;
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log('Upgrade error: ' . $e->getMessage(), 3, '../debug.log');
                    $error = 'An error occurred while submitting your upgrade request. Please try again.';
                    if (file_exists($upload_path)) unlink($upload_path);
                }
            } else {
                $error = 'Failed to upload payment receipt. Please try again.';
            }
        }
    }
}
?>

<?php include('inc/translate.php'); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Upgrade your Cash Tube account to unlock Currency Exchange." />
    <meta name="keywords" content="Cash Tube, upgrade account, currency exchange, payment" />
    <meta name="author" content="Cash Tube" />
    <title>Upgrade Account | Cash Tube</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .form-card h2 i {
            margin-right: 8px;
            font-size: 1.2rem;
            color: var(--accent-color);
        }
        .instructions {
            margin-bottom: 24px;
            font-size: 16px;
            color: var(--subtext-color);
            line-height: 1.6;
        }
        .instructions h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 12px;
        }
        .instructions p {
            margin-bottom: 12px;
        }
        .instructions strong {
            color: var(--text-color);
        }
        .instructions ul {
            list-style-type: disc;
            padding-left: 24px;
            margin-bottom: 12px;
        }
        .instructions ul li {
            margin-bottom: 8px;
        }
        .copyable {
            cursor: pointer;
            padding: 2px 4px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }
        .copyable:hover {
            background-color: var(--border-color);
        }
        .payment-image {
            text-align: center;
            margin: 24px 0;
        }
        .payment-image img {
            max-width: 100%;
            width: 300px;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 4px 12px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: transform 0.2s ease;
        }
        .payment-image img:hover {
            transform: scale(1.02);
        }
        .input-container {
            position: relative;
            margin-bottom: 28px;
        }
        .input-container input,
        .input-container input[type="file"] {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--card-bg);
            color: var(--text-color);
            outline: none;
            transition: border-color 0.3s ease;
        }
        .input-container input[type="file"] {
            padding: 12px;
            cursor: pointer;
        }
        .input-container input:focus,
        .input-container input:valid {
            border-color: var(--accent-color);
        }
        .input-container label {
            position: absolute;
            top: -10px;
            left: 12px;
            font-size: 12px;
            color: var(--subtext-color);
            background: var(--card-bg);
            padding: 0 4px;
            pointer-events: none;
            transition: all 0.3s ease;
        }
        .input-container input:placeholder-shown ~ label {
            top: 14px;
            font-size: 16px;
            color: var(--subtext-color);
        }
        .input-container input:focus ~ label,
        .input-container input:not(:placeholder-shown) ~ label {
            top: -10px;
            font-size: 12px;
            color: var(--accent-color);
        }
        .submit-btn, .resend-btn {
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
            margin-top: 12px;
        }
        .submit-btn:hover, .resend-btn:hover {
            background: var(--accent-hover);
            transform: scale(1.02);
        }
        .error {
            text-align: center;
            color: red;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .success {
            text-align: center;
            color: var(--accent-color);
            margin-bottom: 20px;
            font-size: 14px;
        }
        .action-links {
            text-align: center;
            margin-top: 30px;
        }
        .action-links a,
        .action-links button {
            display: block;
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            font-size: 15px;
            color: var(--accent-color);
            text-decoration: none;
        }
        .action-links button {
            background: transparent;
            border: none;
            cursor: pointer;
            font-weight: 600;
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
            content: 'Lock';
            font-size: 1.2rem;
            margin-right: 12px;
            color: var(--accent-color);
        }
        .notification span {
            font-size: 14px;
            font-weight: 500;
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes fadeOut {
            to { opacity: 0; transform: translateY(-20px); }
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
            .container { padding: 16px; }
            .header-text h1 { font-size: 22px; }
            .form-card { padding: 20px; }
            .notification { max-width: 250px; right: 10px; top: 10px; }
            .instructions { font-size: 14px; }
            .instructions h3 { font-size: 16px; }
            .payment-image img { width: 100%; max-width: 280px; }
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
                    <h1>Upgrade Account</h1>
                    <p>Unlock Currency Exchange feature</p>
                </div>
            </div>
            <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">Toggle Dark Mode</button>
        </div>

        <div class="form-card">
            <h2><i class="fas fa-lock"></i> Account Upgrade</h2>

            <?php if ($upgrade_status === 'upgraded'): ?>
                <p class="success">Your account is already upgraded!</p>
                <p style="text-align: center;"><a href="home.php">Return to Dashboard</a></p>

            <?php elseif ($upgrade_status === 'pending' && !isset($_GET['resend'])): ?>
                <p class="success">Your upgrade request is pending review.</p>
                <p style="text-align: center; margin: 20px 0; color: var(--subtext-color);">
                    Your previous proof is under review. You can resend a clearer receipt if needed.
                </p>
                <div class="action-links">
                    <a href="home.php">Return to Dashboard</a>
                    <button type="button" onclick="window.location.href='upgrade_account.php?resend=1'" class="resend-btn">
                        Resend Upgrade Request
                    </button>
                </div>

            <?php else: ?>
                <!-- Show full form (first time OR resending) -->
                <?php if ($upgrade_status === 'pending'): ?>
                    <div style="background: rgba(34,197,94,0.1); padding: 16px; border-radius: 12px; margin-bottom: 20px; text-align: center;">
                        <strong>Resend Mode Active</strong><br>You are uploading a new/corrected payment proof.
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <p class="error"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>

                <div class="instructions">
                    <h3>Upgrade Instructions</h3>
                    <p>To upgrade your account and unlock Currency Exchange, please make a payment of <strong><?php echo htmlspecialchars($verify_currency); ?> <?php echo number_format($verify_amount, 2); ?></strong> via <strong><?php echo htmlspecialchars($account_upgrade); ?></strong> using the details below:</p>

                    <?php if (!empty($region_image) && file_exists("../images/{$region_image}")): ?>
                        <div class="payment-image">
                            <img src="../images/<?php echo $region_image; ?>" alt="Payment Instructions">
                        </div>
                    <?php endif; ?>

                    <p><strong><?php echo htmlspecialchars($verify_medium); ?>:</strong> <?php echo htmlspecialchars($vcn_value); ?></p>
                    <p><strong><?php echo htmlspecialchars($verify_ch_name); ?>:</strong> <?php echo htmlspecialchars($vc_value); ?></p>
                    <p><strong><?php echo htmlspecialchars($verify_ch_value); ?>:</strong> 
                        <span class="copyable" data-copy="<?php echo htmlspecialchars($vcv_value); ?>" title="Tap to copy on mobile, press and hold on desktop">
                            <?php echo htmlspecialchars($vcv_value); ?>
                        </span>
                    </p>
                    <p>After completing the payment, upload a payment receipt below. Your upgrade request will be reviewed within 48 hours.</p>
                  
                    <h3>Important Notes</h3>
                    <?php if ($crypto): ?>
                        <ul>
                            <li>Ensure the payment is made via <strong><?php echo htmlspecialchars($account_upgrade); ?></strong> to the specified <strong><?php echo htmlspecialchars($verify_ch_value); ?></strong>.</li>
                            <li>Upload a clear payment receipt.</li>
                            <li>Supported file types: JPG, PNG (max size: 5MB).</li>
                            <li>Upgrade may take up to 48 hours to process.</li>
                        </ul>
                    <?php else: ?>
                        <ul>
                            <li>Ensure the payment is made via <strong><?php echo htmlspecialchars($account_upgrade); ?></strong> to the specified <strong><?php echo htmlspecialchars($verify_ch_value); ?></strong>.</li>
                            <li>Upload a clear payment receipt.</li>
                            <li>Supported file types: JPG, PNG (max size: 5MB).</li>
                            <li>Upgrade may take up to 48 hours to process.</li>
                        </ul>
                    <?php endif; ?>
                </div>

                <form action="upgrade_account.php?resend=1" method="POST" enctype="multipart/form-data">
                    <div class="input-container">
                        <input type="file" id="proof_file" name="proof_file" accept=".jpg,.jpeg,.png" required placeholder=" ">
                        <label for="proof_file">Upload Payment Receipt</label>
                    </div>
                    <button type="submit" class="submit-btn">
                        <?php echo ($upgrade_status === 'pending') ? 'Resubmit Upgrade Request' : 'Submit Upgrade Request'; ?>
                    </button>
                </form>

                <p style="text-align: center; margin-top: 20px;"><a href="home.php">Return to Dashboard</a></p>
            <?php endif; ?>
        </div>

        <div id="notificationContainer"></div>
    </div>

    <div class="bottom-menu" role="navigation">
        <a href="home.php">Home</a>
        <a href="profile.php" class="active">Withdraw</a>
        <a href="history.php">History</a>
        <a href="support.php">Support</a>
        <button id="logoutBtn" aria-label="Log out">Logout</button>
    </div>

    <script>
        <?php if ($upgrade_status === 'pending' && isset($_GET['resend'])): ?>
        Swal.fire({
            icon: 'info',
            title: 'Resend Mode Active',
            text: 'You can now upload a new or corrected payment proof.',
            timer: 4000,
            showConfirmButton: false
        });
        <?php endif; ?>

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

        const menuItems = document.querySelectorAll('.bottom-menu a');
        menuItems.forEach((item) => {
            item.addEventListener('click', () => {
                menuItems.forEach((menuItem) => {
                    menuItem.classList.remove('active');
                });
                item.classList.add('active');
            });
        });

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
            updateLabelPosition(input);
            input.addEventListener('input', () => updateLabelPosition(input));
            input.addEventListener('focus', () => {
                const label = input.nextElementSibling;
                if (label && label.tagName === 'LABEL') {
                    label.classList.add('active');
                }
            });
            input.addEventListener('blur', () => updateLabelPosition(input));
        });

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

        const copyableElements = document.querySelectorAll('.copyable');
        let pressTimer;
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        copyableElements.forEach(element => {
            const copyText = () => {
                const textToCopy = element.getAttribute('data-copy');
                navigator.clipboard.writeText(textToCopy).then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Copied!',
                        text: `${textToCopy} copied to clipboard.`,
                        timer: 1500,
                        showConfirmButton: false
                    });
                }).catch(err => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Copy Failed',
                        text: 'Unable to copy text. Please try again.',
                        timer: 2000
                    });
                    console.error('Copy error:', err);
                });
            };
            if (isMobile) {
                element.addEventListener('click', (event) => {
                    event.preventDefault();
                    copyText();
                });
            } else {
                const startCopy = () => {
                    pressTimer = setTimeout(copyText, 500);
                };
                const cancelCopy = () => {
                    clearTimeout(pressTimer);
                };
                element.addEventListener('mousedown', startCopy);
                element.addEventListener('mouseup', cancelCopy);
                element.addEventListener('mouseleave', cancelCopy);
            }
        });

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

        document.addEventListener('contextmenu', function(event) {
            event.preventDefault();
        });
    </script>
</body>
</html>
