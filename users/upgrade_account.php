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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../signin.php');
    ob_end_flush();
    exit;
}

// Database Connection
try {
    require_once '../database/conn.php';
} catch (Exception $e) {
    error_log('Failed to include conn.php: ' . $e->getMessage(), 3, '../debug.log');
    echo 'Failed to connect to database.';
    ob_end_flush();
    exit;
}

date_default_timezone_set('Africa/Lagos');

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT name, email, balance, upgrade_status, country FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        session_destroy();
        header('Location: ../signin.php');
        ob_end_flush();
        exit;
    }
    $username = htmlspecialchars($user['name']);
    $email = htmlspecialchars($user['email']);
    $balance = number_format($user['balance'] ?? 0, 2);
    $upgrade_status = $user['upgrade_status'] ?? 'not_upgraded';
    $user_country = htmlspecialchars($user['country'] ?? '');
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage(), 3, '../debug.log');
    header('Location: ../signin.php?error=database');
    ob_end_flush();
    exit;
}

// Fetch region settings & payment image
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

// Handle Form Submission
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
                    ob_end_flush();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Upgrade Account | Illuminate Tube</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-color: #000000;
            --text-color: #ffffff;
            --subtext-color: #9ca3af;
            --border-color: rgba(255, 255, 255, 0.15);
            --accent-color: #22c55e;
            --accent-hover: #16a34a;
            --menu-bg: rgba(17, 24, 39, 0.95);
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


        /* Normal Web Layout Page Container */
        .page-wrapper {
            width: 100%;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 90px 20px 80px 20px; /* Space for fixed header and bottom menu */
            background: radial-gradient(circle at center, #111827 0%, #000000 100%);
        }

        /* Standard Auto-Expanding Card Container */
        .card-inner {
            width: 100%;
            max-width: 480px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border-color);
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

        .card-inner h2 i {
            color: var(--accent-color);
        }

        .instructions {
            font-size: 14px;
            color: var(--subtext-color);
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .instructions h3 {
            font-size: 16px;
            font-weight: 600;
            color: #ffffff;
            margin: 14px 0 8px 0;
        }

        .instructions p {
            margin-bottom: 10px;
        }

        .instructions strong {
            color: #ffffff;
        }

        .instructions ul {
            padding-left: 20px;
            margin-bottom: 10px;
        }

        .instructions ul li {
            margin-bottom: 6px;
        }

        .copyable {
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--accent-color);
            font-weight: 600;
            transition: background-color 0.2s ease;
        }

        .copyable:hover {
            background: rgba(34, 197, 94, 0.2);
        }

        .payment-image {
            text-align: center;
            margin: 16px 0;
        }

        .payment-image img {
            max-width: 100%;
            width: 240px;
            height: auto;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .input-container {
            position: relative;
            margin: 20px 0 16px 0;
        }

        .input-container input[type="file"] {
            width: 100%;
            padding: 12px;
            font-size: 14px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.4);
            color: #ffffff;
            outline: none;
            cursor: pointer;
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

        .submit-btn, .resend-btn {
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

        .submit-btn:active, .resend-btn:active {
            transform: scale(0.96);
        }

        .error {
            text-align: center;
            color: #ef4444;
            font-size: 13px;
            margin-bottom: 14px;
        }

        .success {
            text-align: center;
            color: var(--accent-color);
            font-size: 14px;
            margin-bottom: 14px;
        }

        .action-links {
            text-align: center;
            margin-top: 16px;
        }

        .action-links a {
            color: var(--accent-color);
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            margin-top: 10px;
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

        #notificationContainer {
            position: fixed;
            top: 70px;
            right: 20px;
            z-index: 1000;
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

    <!-- Main Container -->
    <div class="page-wrapper">
        <div class="card-inner">
            <h2><i class="fas fa-lock"></i> Account Upgrade</h2>

            <?php if ($upgrade_status === 'upgraded'): ?>
                <p class="success">Your account is already upgraded!</p>
                <p style="text-align: center;"><a href="home.php" style="color: var(--accent-color); text-decoration: none;">Return to Dashboard</a></p>

            <?php elseif ($upgrade_status === 'pending' && !isset($_GET['resend'])): ?>
                <p class="success">Your upgrade request is pending review.</p>
                <p style="text-align: center; margin: 20px 0; color: var(--subtext-color); font-size: 14px;">
                    Your previous proof is under review. You can resend a clearer receipt if needed.
                </p>
                <div class="action-links">
                    <button type="button" onclick="window.location.href='upgrade_account.php?resend=1'" class="resend-btn">
                        <i class="fa-solid fa-rotate-right"></i> Resend Upgrade Request
                    </button>
                    <a href="home.php">Return to Dashboard</a>
                </div>

            <?php else: ?>
                <?php if ($upgrade_status === 'pending'): ?>
                    <div style="background: rgba(34,197,94,0.15); border: 1px solid var(--accent-color); padding: 12px; border-radius: 12px; margin-bottom: 16px; text-align: center; font-size: 13px;">
                        <strong>Resend Mode Active</strong><br>You are uploading a new payment proof.
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <p class="error"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>

                <div class="instructions">
                    <h3>Upgrade Instructions</h3>
                    <p>Make a payment of <strong><?php echo htmlspecialchars($verify_currency); ?> <?php echo number_format($verify_amount, 2); ?></strong> via <strong><?php echo htmlspecialchars($account_upgrade); ?></strong> using details below:</p>

                    <?php if (!empty($region_image) && file_exists("../images/{$region_image}")): ?>
                        <div class="payment-image">
                            <img src="../images/<?php echo $region_image; ?>" alt="Payment Instructions">
                        </div>
                    <?php endif; ?>

                    <p><strong><?php echo htmlspecialchars($verify_medium); ?>:</strong> <?php echo htmlspecialchars($vcn_value); ?></p>
                    <p><strong><?php echo htmlspecialchars($verify_ch_name); ?>:</strong> <?php echo htmlspecialchars($vc_value); ?></p>
                    <p><strong><?php echo htmlspecialchars($verify_ch_value); ?>:</strong> 
                        <span class="copyable" data-copy="<?php echo htmlspecialchars($vcv_value); ?>" title="Tap to copy">
                            <?php echo htmlspecialchars($vcv_value); ?>
                        </span>
                    </p>
                    <p style="margin-top: 10px;">Upload payment receipt below. Review completes within 48 hours.</p>
                    
                    <h3>Important Notes</h3>
                    <ul>
                        <li>Ensure payment via <strong><?php echo htmlspecialchars($account_upgrade); ?></strong> to specified <strong><?php echo htmlspecialchars($verify_ch_value); ?></strong>.</li>
                        <li>Upload clear payment receipt (JPG/PNG, Max 5MB).</li>
                    </ul>
                </div>

                <form action="upgrade_account.php?resend=1" method="POST" enctype="multipart/form-data">
                    <div class="input-container">
                        <input type="file" id="proof_file" name="proof_file" accept=".jpg,.jpeg,.png" required>
                        <label for="proof_file">Payment Receipt</label>
                    </div>
                    <button type="submit" class="submit-btn">
                        <i class="fa-solid fa-paper-plane"></i> <?php echo ($upgrade_status === 'pending') ? 'Resubmit Request' : 'Submit Request'; ?>
                    </button>
                </form>

                <p style="text-align: center; margin-top: 16px;">
                    <a href="home.php" style="color: var(--accent-color); font-size: 13px; text-decoration: none;">Return to Dashboard</a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div id="notificationContainer"></div>

    <!-- Fixed Bottom Menu -->
    <div class="bottom-menu" role="navigation">
        <a href="home.php"><i class="fa-solid fa-house"></i>Home</a>
        <a href="profile.php"><i class="fa-solid fa-money-bill"></i>Withdraw</a>
        <a href="history.php"><i class="fa-solid fa-clock-rotate-left"></i>History</a>
        <a href="support.php"><i class="fa-solid fa-headset"></i>Support</a>
        <button id="logoutBtn" aria-label="Log out"><i class="fa-solid fa-right-from-bracket"></i>Logout</button>
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

        // Copy functionality
        const copyableElements = document.querySelectorAll('.copyable');
        copyableElements.forEach(element => {
            element.addEventListener('click', (event) => {
                event.preventDefault();
                const textToCopy = element.getAttribute('data-copy');
                navigator.clipboard.writeText(textToCopy).then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Copied!',
                        text: `${textToCopy} copied to clipboard.`,
                        timer: 1500,
                        showConfirmButton: false
                    });
                });
            });
        });

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

        // Context menu restriction
        document.addEventListener('contextmenu', function(event) {
            event.preventDefault();
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
