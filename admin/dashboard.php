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

// Handle Telegram Support Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_telegram'])) {
    $telegram_link = trim($_POST['telegram_link']);

    try {
        // Ensure table exists with telegram column
        $pdo->exec("CREATE TABLE IF NOT EXISTS c_support (
            id INT AUTO_INCREMENT PRIMARY KEY,
            telegram VARCHAR(255) NOT NULL
        )");

        // Check if a row already exists
        $stmt = $pdo->prepare("SELECT id FROM c_support LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Update existing row
            $updateStmt = $pdo->prepare("UPDATE c_support SET telegram = :telegram WHERE id = :id");
            $updateStmt->execute([
                ':telegram' => $telegram_link,
                ':id' => $row['id']
            ]);
        } else {
            // Insert new record if none exists
            $insertStmt = $pdo->prepare("INSERT INTO c_support (telegram) VALUES (:telegram)");
            $insertStmt->execute([':telegram' => $telegram_link]);
        }

        $_SESSION['telegram_success'] = "Telegram support link updated successfully!";
        header("Location: dashboard.php");
        exit;
    } catch (PDOException $e) {
        error_log('Telegram update error: ' . $e->getMessage(), 3, '../debug.log');
        $_SESSION['telegram_error'] = "Failed to update Telegram link: " . $e->getMessage();
        header("Location: dashboard.php");
        exit;
    }
}

// Fetch existing Telegram Link/Username
$telegram_link = '';
try {
    $stmt = $pdo->prepare("SELECT telegram FROM c_support LIMIT 1");
    $stmt->execute();
    $support_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($support_data) {
        $telegram_link = $support_data['telegram'];
    }
} catch (PDOException $e) {
    error_log('Telegram fetch error: ' . $e->getMessage(), 3, '../debug.log');
}

// Fetch all videos including likes
try {
    $stmt = $pdo->prepare("SELECT id, title, url, reward, likes FROM videos ORDER BY id");
    $stmt->execute();
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Video fetch error: ' . $e->getMessage(), 3, '../debug.log');
    $videos = [];
    $error = 'Failed to load videos: ' . $e->getMessage();
}

// Fetch total registered users
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS user_count FROM users");
    $stmt->execute();
    $user_count = $stmt->fetch(PDO::FETCH_ASSOC)['user_count'];
} catch (PDOException $e) {
    error_log('User count fetch error: ' . $e->getMessage(), 3, '../debug.log');
    $user_count = 0;
    $error = isset($error) ? $error . '<br>Failed to load user count: ' . $e->getMessage() : 'Failed to load user count: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Illuminate Tube - Admin Dashboard</title>
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

        .logout-link, .management-link {
            box-sizing: border-box;
            padding: 7px 14px;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            transition: background-color 0.3s ease;
        }

        .logout-link {
            background-color: #dc3545;
        }

        .logout-link:hover {
            background-color: #c82333;
        }

        .management-link {
            background-color: #007bff;
        }

        .management-link:hover {
            background-color: #0056b3;
        }

        /* Section Styling */
        .video-management, .support-management {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .video-management h3, .support-management h3 {
            color: #333;
            margin-bottom: 15px;
        }

        .add-video-form, .telegram-form {
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }

        .add-video-form input[type="text"],
        .add-video-form input[type="number"],
        .add-video-form input[type="file"],
        .telegram-form input[type="text"] {
            flex: 1;
            min-width: 200px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .add-video-form input[type="file"] {
            padding: 4px;
        }

        .add-video-form button, .telegram-form button {
            padding: 7px 14px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .add-video-form button:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }

        .add-video-form button:hover:not(:disabled),
        .telegram-form button:hover {
            background-color: #0056b3;
        }

        .loading {
            display: none;
            margin-top: 10px;
            color: #007bff;
            font-size: 14px;
        }

        /* Scrollable Table */
        .table-container {
            max-width: 100%;
            overflow-x: auto;
            overflow-y: auto;
            max-height: 400px;
            margin-top: 10px;
        }

        .video-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .video-table th,
        .video-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 14px;
        }

        .video-table th {
            background-color: #f8f9fa;
            color: #333;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .video-table td {
            color: #555;
        }

        .video-table a {
            margin: 0 5px;
            text-decoration: none;
            color: #007bff;
        }

        .video-table a.delete {
            color: #dc3545;
        }

        .video-table a:hover {
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

            .add-video-form, .telegram-form {
                flex-direction: column;
            }

            .add-video-form input,
            .add-video-form button,
            .telegram-form input,
            .telegram-form button {
                width: 100%;
                min-width: unset;
            }

            .video-table {
                min-width: 100%;
            }

            .button-container {
                flex-direction: column;
                align-items: center;
            }

            .management-link, .logout-link {
                width: 100%;
                max-width: 200px;
                margin: 6px 0;
                padding: 6px 12px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h2>Welcome to Illuminate Tube Admin Dashboard</h2>
        <p>Hello, <?php echo htmlspecialchars($_SESSION['admin_email']); ?>!</p>
        <p>Total Registered Users: <strong><?php echo $user_count; ?></strong></p>

        <!-- Management Buttons -->
        <div class="button-container">
            <a href="manage_verifications.php" class="management-link">Manage Verification/Upgrade Requests</a>
            <a href="manage_withdrawals.php" class="management-link">Manage Withdrawals</a>
            <a href="manage_users.php" class="management-link">Manage Users</a>
            <a href="manage_region_settings.php" class="management-link">Manage Region Settings</a>
            <a href="logout.php" class="logout-link">Logout</a>
        </div>

        <!-- Support Management Section -->
        <div class="support-management">
            <h3>Manage Support Telegram Link / Username</h3>
            <?php if (isset($_SESSION['telegram_success'])): ?>
                <p class="success"><?php echo htmlspecialchars($_SESSION['telegram_success']); unset($_SESSION['telegram_success']); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['telegram_error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_SESSION['telegram_error']); unset($_SESSION['telegram_error']); ?></p>
            <?php endif; ?>

            <form action="dashboard.php" method="POST" class="telegram-form">
                <input type="text" name="telegram_link" placeholder="Enter Telegram Username or Link (e.g. https://t.me/yourusername)" value="<?php echo htmlspecialchars($telegram_link); ?>" required>
                <button type="submit" name="update_telegram">Save Telegram Link</button>
            </form>
        </div>

        <!-- Video Management Section -->
        <div class="video-management">
            <h3>Manage Videos</h3>
            <?php if (isset($error)): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <p class="success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
            <?php endif; ?>

            <!-- Add Video Form -->
            <form action="add_video.php" method="POST" enctype="multipart/form-data" class="add-video-form" id="addVideoForm">
                <input type="text" name="title" placeholder="Video Title" required>
                <input type="file" name="video_file" accept=".mp4,.avi,.mov" required>
                <input type="number" name="reward" placeholder="Reward ($)" step="0.01" required>
                <input type="number" name="likes" placeholder="Initial Likes" min="0" value="0" required>
                <button type="submit" id="addVideoButton">Add Video</button>
                <div class="loading" id="loadingIndicator">Uploading video, please wait...</div>
            </form>

            <!-- Video List -->
            <?php if (empty($videos)): ?>
                <p>No videos available.</p>
            <?php else: ?>
                <div class="table-container">
                    <table class="video-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>URL</th>
                                <th>Reward ($)</th>
                                <th>Likes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($videos as $video): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($video['id']); ?></td>
                                    <td><?php echo htmlspecialchars($video['title']); ?></td>
                                    <td><?php echo htmlspecialchars($video['url']); ?></td>
                                    <td><?php echo number_format($video['reward'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($video['likes'] ?? 0); ?></td>
                                    <td>
                                        <a href="edit_video.php?id=<?php echo $video['id']; ?>">Edit</a>
                                        <a href="delete_video.php?id=<?php echo $video['id']; ?>" class="delete" onclick="return confirm('Are you sure you want to delete this video?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Show loading indicator when form is submitted
        document.getElementById('addVideoForm').addEventListener('submit', function() {
            const button = document.getElementById('addVideoButton');
            const loadingIndicator = document.getElementById('loadingIndicator');
            button.disabled = true;
            button.innerText = 'Uploading...';
            loadingIndicator.style.display = 'block';
        });
    </script>
</body>
</html>
