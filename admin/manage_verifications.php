<?php
session_start();

// Ensure session is started and admin is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header("Location: login.php");
    exit;
}

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../database/conn.php';

// Set time zone to WAT
date_default_timezone_set('Africa/Lagos');

// Handle request actions (approve/reject for verification or upgrade)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'], $_POST['csrf_token'], $_POST['request_type'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token. Please try again.";
        error_log('CSRF validation failed for admin ID: ' . $_SESSION['admin_id'], 3, '../debug.log');
    } else {
        $request_id = filter_var($_POST['request_id'], FILTER_VALIDATE_INT);
        $action = filter_var($_POST['action'], FILTER_SANITIZE_STRING);
        $request_type = filter_var($_POST['request_type'], FILTER_SANITIZE_STRING);
        if ($request_id === false || !in_array($action, ['approve', 'reject']) || !in_array($request_type, ['verification', 'upgrade'])) {
            $_SESSION['error'] = "Invalid request ID, action, or type.";
            error_log("Invalid input: request_id=$request_id, action=$action, request_type=$request_type", 3, '../debug.log');
        } else {
            try {
                $pdo->beginTransaction();
                
                // Determine table and status column based on request type
                $table = $request_type === 'verification' ? 'verification_requests' : 'upgrade_requests';
                $status_column = $request_type === 'verification' ? 'verification_status' : 'upgrade_status';
                $status_value = $action === 'approve' ? 'verified' : 'not_verified'; // For verification
                if ($request_type === 'upgrade') {
                    $status_value = $action === 'approve' ? 'upgraded' : 'not_upgraded';
                }
                
                // Fetch the request
                $stmt = $pdo->prepare("SELECT user_id FROM $table WHERE id = ? AND status = 'pending'");
                $stmt->execute([$request_id]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($request) {
                    $user_id = $request['user_id'];
                    // Update request status
                    $stmt = $pdo->prepare("UPDATE $table SET status = ? WHERE id = ?");
                    $stmt->execute([$action === 'approve' ? 'approved' : 'rejected', $request_id]);
                    // Update user status
                    $stmt = $pdo->prepare("UPDATE users SET $status_column = ? WHERE id = ?");
                    $stmt->execute([$status_value, $user_id]);
                    $_SESSION['success'] = ucfirst($request_type) . " request " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully.";
                    error_log(ucfirst($request_type) . " request ID $request_id $action for user ID $user_id by admin ID {$_SESSION['admin_id']}", 3, '../debug.log');
                    $pdo->commit();
                } else {
                    $_SESSION['error'] = "Invalid or already processed $request_type request.";
                    error_log("Invalid or processed $request_type request ID: $request_id", 3, '../debug.log');
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log(ucfirst($request_type) . " action error: " . $e->getMessage(), 3, '../debug.log');
                $_SESSION['error'] = "Failed to process $request_type request: " . htmlspecialchars($e->getMessage());
            }
        }
    }
    header("Location: manage_verifications.php");
    exit;
}

// Fetch all verification and upgrade requests
try {
    $stmt = $pdo->prepare("
        SELECT vr.id, vr.user_id, vr.payment_amount, vr.name, vr.email, vr.upload_path, vr.file_name, vr.status, 
               vr.payment_method, vr.currency, vr.created_at, u.country, rs.account_upgrade,
               'verification' AS request_type
        FROM verification_requests vr
        JOIN users u ON vr.user_id = u.id
        JOIN region_settings rs ON u.country = rs.country
        WHERE rs.account_upgrade = 0
        UNION
        SELECT ur.id, ur.user_id, ur.payment_amount, ur.name, ur.email, ur.upload_path, ur.file_name, ur.status, 
               ur.payment_method, ur.currency, ur.created_at, u.country, rs.account_upgrade,
               'upgrade' AS request_type
        FROM upgrade_requests ur
        JOIN users u ON ur.user_id = u.id
        JOIN region_settings rs ON u.country = rs.country
        WHERE rs.account_upgrade = 1
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Requests fetch error: ' . $e->getMessage(), 3, '../debug.log');
    $requests = [];
    $error = 'Failed to load requests: ' . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Illuminate Tube - Manage Verification and Upgrade Requests</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background-color: #f4f4f4;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .dashboard-container h2 {
            color: #333;
            margin-bottom: 20px;
        }

        .dashboard-container p {
            color: #555;
            font-size: 16px;
        }

        .button-container {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
        }

        .back-link, .action-btn {
            box-sizing: border-box;
            padding: 7px 14px;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            transition: background-color 0.3s ease;
        }

        .back-link {
            background-color: #6c757d;
        }

        .back-link:hover {
            background-color: #5a6268;
        }

        .action-btn.approve {
            background-color: #28a745;
        }

        .action-btn.approve:hover {
            background-color: #218838;
        }

        .action-btn.reject {
            background-color: #dc3545;
        }

        .action-btn.reject:hover {
            background-color: #c82333;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .table-container {
            max-width: 100%;
            overflow-x: auto;
            overflow-y: auto;
            max-height: 400px;
            margin-top: 10px;
        }

        .requests-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .requests-table th,
        .requests-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 14px;
        }

        .requests-table th {
            background-color: #f8f9fa;
            color: #333;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .requests-table td {
            color: #555;
        }

        .requests-table img {
            max-width: 100px;
            height: auto;
            border-radius: 4px;
        }

        .requests-table a {
            color: #007bff;
            text-decoration: none;
        }

        .requests-table a:hover {
            text-decoration: underline;
        }

        .error, .success {
            color: red;
            margin-bottom: 15px;
        }

        .success {
            color: green;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-container {
                margin: 20px;
                padding: 15px;
            }

            .requests-table {
                min-width: 100%;
            }

            .action-btn, .back-link {
                width: 100%;
                max-width: 200px;
                margin: 6px 0;
                padding: 6px 12px;
                font-size: 11px;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .requests-table img {
                max-width: 80px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h2>Manage Verification and Upgrade Requests</h2>
        <p>Hello, <?php echo htmlspecialchars($_SESSION['admin_email']); ?>!</p>

        <?php if (isset($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <p class="success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <p class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
        <?php endif; ?>

        <div class="button-container">
            <a href="dashboard.php" class="back-link">Back to Dashboard</a>
        </div>

        <!-- Combined Requests List -->
        <?php if (empty($requests)): ?>
            <p>No verification or upgrade requests available.</p>
        <?php else: ?>
            <div class="table-container">
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Payment Amount</th>
                            <th>Payment Method</th>
                            <th>Currency</th>
                            <th>Proof File</th>
                            <th>Status</th>
                            <th>Submitted At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['id']); ?></td>
                                <td><?php echo htmlspecialchars($request['name']); ?></td>
                                <td><?php echo htmlspecialchars($request['email']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($request['request_type'])); ?></td>
                                <td><?php echo htmlspecialchars($request['currency']); ?> <?php echo number_format($request['payment_amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($request['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($request['currency']); ?></td>
                                <td>
                                    <?php
                                    $file_ext = pathinfo($request['file_name'], PATHINFO_EXTENSION);
                                    if (in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png'])) {
                                        echo '<img src="../' . htmlspecialchars($request['upload_path']) . '" alt="Proof">';
                                    } else {
                                        echo '<a href="../' . htmlspecialchars($request['upload_path']) . '" target="_blank">View Proof</a>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars(ucfirst($request['status'])); ?></td>
                                <td><?php echo htmlspecialchars($request['created_at']); ?></td>
                                <td class="action-buttons">
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="request_type" value="<?php echo $request['request_type']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <button type="submit" class="action-btn approve">Approve</button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="request_type" value="<?php echo $request['request_type']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <button type="submit" class="action-btn reject">Reject</button>
                                        </form>
                                    <?php else: ?>
                                        <span>No actions available</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
