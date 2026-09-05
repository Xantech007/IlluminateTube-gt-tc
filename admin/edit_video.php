<?php
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once '../database/conn.php';

// Fetch video details
$video = null;
if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT id, title, url, reward, likes FROM videos WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $video = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$video) {
            $_SESSION['error'] = 'Video not found.';
            header("Location: dashboard.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to fetch video: ' . $e->getMessage();
        error_log('Edit video fetch error: ' . $e->getMessage(), 3, '../debug.log');
        header("Location: dashboard.php");
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = filter_var($_POST['title'], FILTER_SANITIZE_STRING);
    $reward = filter_var($_POST['reward'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $likes = isset($_POST['likes']) ? intval($_POST['likes']) : 0;
    
    $upload_dir = '../users/videos/';
    $allowed_types = ['video/mp4', 'video/avi', 'video/quicktime'];
    $max_size = 100 * 1024 * 1024; // 100MB
    $url = $video['url']; // Keep existing URL unless a new file is uploaded

    if (empty($title) || !isset($_POST['reward'])) {
        $_SESSION['error'] = 'Title and reward are required.';
        header("Location: edit_video.php?id=" . $_GET['id']);
        exit;
    }

    // Handle file upload if provided
    if (!empty($_FILES['video_file']['name'])) {
        $file = $_FILES['video_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'File upload error: ' . $file['error'];
            header("Location: edit_video.php?id=" . $_GET['id']);
            exit;
        }

        if (!in_array($file['type'], $allowed_types)) {
            $_SESSION['error'] = 'Invalid file type. Only MP4, AVI, and MOV are allowed.';
            header("Location: edit_video.php?id=" . $_GET['id']);
            exit;
        }

        if ($file['size'] > $max_size) {
            $_SESSION['error'] = 'File size exceeds 100MB limit.';
            header("Location: edit_video.php?id=" . $_GET['id']);
            exit;
        }

        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('video_') . '_' . time() . '.' . $ext;
        $destination = $upload_dir . $filename;
        $url = 'users/videos/' . $filename;

        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Move uploaded file and delete old file
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            if (file_exists('../' . $video['url'])) {
                unlink('../' . $video['url']); // Delete old video file
            }
        } else {
            $_SESSION['error'] = 'Failed to upload video file.';
            header("Location: edit_video.php?id=" . $_GET['id']);
            exit;
        }
    }

    // Update database
    try {
        $stmt = $pdo->prepare("UPDATE videos SET title = ?, url = ?, reward = ?, likes = ? WHERE id = ?");
        $stmt->execute([$title, $url, $reward, $likes, $_GET['id']]);
        $_SESSION['success'] = 'Video updated successfully.';
        header("Location: dashboard.php");
        exit;
    } catch (PDOException $e) {
        if (isset($destination) && file_exists($destination)) {
            unlink($destination); // Remove new file if database update fails
        }
        $_SESSION['error'] = 'Failed to update video: ' . $e->getMessage();
        error_log('Edit video error: ' . $e->getMessage(), 3, '../debug.log');
        header("Location: edit_video.php?id=" . $_GET['id']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Video - Illuminate Tube</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background-color: #f4f4f4;
        }

        .edit-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .edit-container h2 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }

        .edit-form input[type="text"],
        .edit-form input[type="number"],
        .edit-form input[type="file"] {
            width: 100%;
            padding: 8px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .edit-form input[type="file"] {
            padding: 4px;
        }

        .edit-form button {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .edit-form button:hover {
            background-color: #0056b3;
        }

        .error, .success {
            margin-bottom: 15px;
            text-align: center;
        }

        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <div class="edit-container">
        <h2>Edit Video</h2>
        <?php if (isset($_SESSION['error'])): ?>
            <p class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <p class="success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
        <?php endif; ?>
        <form action="" method="POST" enctype="multipart/form-data" class="edit-form">
            <input type="text" name="title" value="<?php echo htmlspecialchars($video['title']); ?>" placeholder="Video Title" required>
            <input type="file" name="video_file" accept=".mp4,.avi,.mov">
            <p style="font-size: 13px; color: #666;">Current video: <?php echo htmlspecialchars($video['url']); ?> (leave file empty to keep current video)</p>
            <input type="number" name="reward" value="<?php echo htmlspecialchars($video['reward']); ?>" placeholder="Reward ($)" step="0.01" required>
            <input type="number" name="likes" value="<?php echo htmlspecialchars($video['likes'] ?? 0); ?>" placeholder="Likes" min="0" required>
            <button type="submit">Update Video</button>
        </form>
        <a href="dashboard.php" style="display: block; text-align: center; margin-top: 15px; color: #007bff; text-decoration: none;">Back to Dashboard</a>
    </div>
</body>
</html>
