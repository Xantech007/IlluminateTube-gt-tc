<?php
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once '../database/conn.php';

// Set time zone to WAT
date_default_timezone_set('Africa/Lagos');

// Handle user actions (suspend/unsuspend, delete, update password)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['action'])) {
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action'];
    try {
        $pdo->beginTransaction();
        
        if ($action === 'suspend') {
            $stmt = $pdo->prepare("UPDATE users SET is_suspended = 1 WHERE id = ?");
            $stmt->execute([$user_id]);
            $_SESSION['success'] = "User suspended successfully.";
        } elseif ($action === 'unsuspend') {
            $stmt = $pdo->prepare("UPDATE users SET is_suspended = 0 WHERE id = ?");
            $stmt->execute([$user_id]);
            $_SESSION['success'] = "User unsuspended successfully.";
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $_SESSION['success'] = "User deleted successfully.";
        } elseif ($action === 'update_password') {
            $password = trim($_POST['password']);
            // Validate password: minimum 8 characters
            if (strlen($password) < 8) {
                $_SESSION['error'] = "Password must be at least 8 characters.";
            } else {
                // Hash the new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET passcode = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                $_SESSION['success'] = "Password updated successfully.";
            }
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('User action error: ' . $e->getMessage(), 3, '../debug.log');
        $_SESSION['error'] = "Failed to process user action: " . $e->getMessage();
    }
    header("Location: manage_users.php");
    exit;
}

// Fetch all users
try {
    $stmt = $pdo->prepare("
        SELECT id, name, email, balance, verification_status, is_suspended, passcode, country, created_at
        FROM users
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('User fetch error: ' . $e->getMessage(), 3, '../debug.log');
    $users = [];
    $error = 'Failed to load users: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Illuminate Tube - Manage Users</title>
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

        .action-btn.suspend {
            background-color: #ffc107;
        }

        .action-btn.suspend:hover {
            background-color: #e0a800;
        }

        .action-btn.unsuspend {
            background-color: #28a745;
        }

        .action-btn.unsuspend:hover {
            background-color: #218838;
        }

        .action-btn.delete {
            background-color: #dc3545;
        }

        .action-btn.delete:hover {
            background-color: #c82333;
        }

        .action-btn.update-password {
            background-color: #17a2b8;
        }

        .action-btn.update-password:hover {
            background-color: #138496;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .password-input {
            width: 120px;
            padding: 5px;
            font-size: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .users-table th,
        .users-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 14px;
        }

        .users-table th {
            background-color: #f8f9fa;
            color: #333;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .users-table td {
            color: #555;
        }

        .error, .success {
            color: red;
            margin-bottom: 15px;
        }

        .success {
            color: green;
        }

        .table-container {
            max-width: 100%;
            overflow-x: auto;
            overflow-y: auto;
            max-height: 400px;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                margin: 20px;
                padding: 15px;
            }

            .users-table {
                min-width: 100%;
            }

            .action-btn, .back-link {
                width: 100%;
                max-width: 200px;
                margin: 6px 0;
                padding: 6px 12px;
                font-size: 11px;
            }

            .password-input {
                width: 100%;
                max-width: 150px;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h2>Manage Users</h2>
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

        <!-- Users List -->
        <?php if (empty($users)): ?>
            <p>No users available.</p>
        <?php else: ?>
            <div class="table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Balance ($)</th>
                            <th>Verification Status</th>
                            <th>Suspended</th>
                            <th>Password</th>
                            <th>Country</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo number_format($user['balance'], 2); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($user['verification_status'])); ?></td>
                                <td><?php echo $user['is_suspended'] ? 'Yes' : 'No'; ?></td>
                                <td>********</td> <!-- Hide hashed password -->
                                <td><?php echo htmlspecialchars($user['country'] ?? 'None'); ?></td>
                                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                <td class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="<?php echo $user['is_suspended'] ? 'unsuspend' : 'suspend'; ?>">
                                        <button type="submit" class="action-btn <?php echo $user['is_suspended'] ? 'unsuspend' : 'suspend'; ?>">
                                            <?php echo $user['is_suspended'] ? 'Unsuspend' : 'Suspend'; ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this user?');">Delete</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="update_password">
                                        <input type="password" name="password" class="password-input" placeholder="New password" minlength="8" required>
                                        <button type="submit" class="action-btn update-password">Update Password</button>
                                    </form>
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
