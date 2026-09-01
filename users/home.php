<?php
session_start();
require_once '../database/conn.php';

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log('No user_id in session, redirecting to signin', 3, '../debug.log');
    header('Location: ../signin.php');
    exit;
}

// Initialize session trackers for watched videos
if (!isset($_SESSION['watched_videos'])) {
    $_SESSION['watched_videos'] = [];
}

// Handle AJAX reward collection & DB update directly
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_reward') {
    header('Content-Type: application/json');
    $v_id = intval($_POST['video_id']);
    $user_id = $_SESSION['user_id'];

    // Verify if already watched in DB or session
    try {
        $check_stmt = $pdo->prepare("SELECT id FROM activities WHERE user_id = ? AND video_id = ? AND action LIKE 'Watched%'");
        $check_stmt->execute([$user_id, $v_id]);
        
        if ($check_stmt->fetch() || in_array($v_id, $_SESSION['watched_videos'])) {
            echo json_encode(['status' => 'error', 'message' => 'Video already watched.']);
            exit;
        }

        // Fetch reward amount to prevent manipulation from front-end
        $vid_stmt = $pdo->prepare("SELECT reward FROM videos WHERE id = ?");
        $vid_stmt->execute([$v_id]);
        $video_data = $vid_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$video_data) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid video.']);
            exit;
        }

        $reward = floatval($video_data['reward']);

        $pdo->beginTransaction();

        // 1. Update user balance in Database
        $update_balance = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $update_balance->execute([$reward, $user_id]);

        // 2. Log activity to prevent double rewards on refresh
        $log_activity = $pdo->prepare("INSERT INTO activities (user_id, video_id, action, created_at) VALUES (?, ?, ?, NOW())");
        $log_activity->execute([$user_id, $v_id, 'Watched video #' . $v_id]);

        $pdo->commit();

        // 3. Update session tracking
        $_SESSION['watched_videos'][] = $v_id;

        // Fetch new updated balance
        $bal_stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $bal_stmt->execute([$user_id]);
        $new_balance = floatval($bal_stmt->fetchColumn());

        echo json_encode([
            'status' => 'success',
            'new_balance' => number_format($new_balance, 2),
            'reward' => number_format($reward, 2)
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Reward Transaction Error: ' . $e->getMessage(), 3, '../debug.log');
        echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
    }
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
        exit;
    }
    $username = htmlspecialchars($user['name']);
    $db_balance = floatval($user['balance']);
    $total_display_balance = number_format($db_balance, 2);
    $verification_status = $user['verification_status'];
    $user_country = htmlspecialchars($user['country']);
    $upgrade_status = $user['upgrade_status'] ?? 'not_upgraded';
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage(), 3, '../debug.log');
    if (file_exists('../error.php')) {
        include '../error.php';
    } else {
        echo 'Database error occurred: ' . htmlspecialchars($e->getMessage());
    }
    exit;
}

// Helper function to check URL reachability
function url_exists($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($code !== 200) {
        return ['status' => false, 'error' => "HTTP $code - $error"];
    }
    return ['status' => true];
}

// Fetch up to 10 videos excluding both DB activities and current session watched videos
$videos = [];
$video_error = null;

try {
    $stmt = $pdo->prepare("
        SELECT v.id, v.title, v.url, v.reward, COALESCE(v.likes, 0) AS likes 
        FROM videos v 
        WHERE v.id NOT IN (
            SELECT video_id FROM activities 
            WHERE user_id = ? AND action LIKE 'Watched%'
        ) 
        ORDER BY RAND() LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $fetched_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($fetched_videos as $vid) {
        // Exclude session watched videos as well
        if (in_array($vid['id'], $_SESSION['watched_videos'])) {
            continue;
        }

        $full_url = 'https://tasktube.gt.tc/users/videos/' . basename($vid['url']);
        $url_check = url_exists($full_url);
        if ($url_check['status']) {
            $vid['url'] = $full_url;
            $videos[] = $vid;
        }
    }

    if (empty($videos)) {
        $video_error = 'All videos have been watched! Check back later for new ads.';
    }
} catch (PDOException $e) {
    error_log('Video fetch error: ' . $e->getMessage(), 3, '../debug.log');
    $video_error = 'Failed to load videos from database.';
}

$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : null;
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : null;
?>

<?php include('inc/translate.php'); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Dashboard | Cash Tube</title>
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

        /* Fixed Top Floating Header */
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
            background: linear-gradient(180deg, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0) 100%);
            pointer-events: none;
        }

        .top-header * {
            pointer-events: auto;
        }

        .user-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(0, 0, 0, 0.4);
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

        /* TikTok Style Vertical Feed Container */
        .tiktok-feed {
            width: 100%;
            height: 100vh;
            overflow-y: scroll;
            scroll-snap-type: y mandatory;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }

        .tiktok-feed::-webkit-scrollbar {
            display: none;
        }

        /* Individual Video Section Slide */
        .video-card {
            width: 100%;
            height: 100vh;
            scroll-snap-align: start;
            scroll-snap-stop: always;
            position: relative;
            background: #000;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .video-card video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Countdown overlay banner */
        .reward-countdown-banner {
            position: absolute;
            top: 65px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.75);
            border: 1px solid var(--accent-color);
            backdrop-filter: blur(8px);
            padding: 8px 18px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 600;
            color: #ffffff;
            z-index: 20;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .reward-countdown-banner span {
            color: #4ade80;
            font-weight: 700;
        }

        /* Sidebar Action Icons */
        .actions-sidebar {
            position: absolute;
            right: 16px;
            bottom: 200px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            z-index: 10;
        }

        .action-btn {
            background: none;
            border: none;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            text-shadow: 0 2px 4px rgba(0,0,0,0.6);
            transition: transform 0.2s ease;
        }

        .action-btn:active {
            transform: scale(0.9);
        }

        .action-btn i {
            font-size: 32px;
            margin-bottom: 4px;
            transition: color 0.3s ease;
        }

        .action-btn.liked i {
            color: #ef4444;
            animation: heartBounce 0.4s ease;
        }

        .action-btn span {
            font-size: 12px;
            font-weight: 600;
        }

        /* Bottom Info Overlay */
        .video-overlay-info {
            position: absolute;
            bottom: 200px;
            left: 16px;
            right: 80px;
            z-index: 10;
            color: #fff;
            text-shadow: 0 1px 3px rgba(0,0,0,0.8);
        }

        .video-overlay-info h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .video-overlay-info p {
            font-size: 13px;
            opacity: 0.9;
            line-height: 1.3;
        }

        .video-overlay-info p span {
            color: #4ade80;
            font-weight: 700;
        }

        /* Floating Double Tap Heart FX */
        .heart-animation {
            position: absolute;
            color: #ef4444;
            font-size: 80px;
            pointer-events: none;
            animation: floatHeart 0.8s ease-out forwards;
            z-index: 20;
        }

        @keyframes heartBounce {
            0% { transform: scale(1); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); }
        }

        @keyframes floatHeart {
            0% { opacity: 1; transform: translate(-50%, -50%) scale(0.5); }
            50% { opacity: 0.9; transform: translate(-50%, -60%) scale(1.2); }
            100% { opacity: 0; transform: translate(-50%, -80%) scale(1); }
        }

        /* Notification Toast */
        .notification {
            position: fixed;
            top: 125px;
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

        .no-videos-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 24px;
        }
    </style>
</head>
<body>

    <!-- Fixed Header -->
    <div class="top-header">
        <div class="user-badge">
            <img src="img/top.png" alt="Logo">
            <span style="font-size: 14px; font-weight: 600;"><?php echo $username; ?></span>
        </div>
        <div class="balance-badge">
            $<span id="balance"><?php echo $total_display_balance; ?></span>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="notification" role="alert">
            <i class="fa-solid fa-circle-check" style="color: #4ade80;"></i>
            <span><?php echo $success_message; ?></span>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="notification" role="alert">
            <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i>
            <span><?php echo $error_message; ?></span>
        </div>
    <?php endif; ?>

    <!-- TikTok Scroll Container -->
    <div class="tiktok-feed" id="tiktokFeed">
        <?php if (!empty($videos)): ?>
            <?php foreach ($videos as $index => $vid): ?>
                <div class="video-card" data-index="<?php echo $index; ?>" id="video-card-<?php echo $vid['id']; ?>">
                    <div class="reward-countdown-banner">
                        <i class="fa-solid fa-stopwatch" style="color: #4ade80;"></i>
                        Reward in: <span class="timer-display">--s</span>
                    </div>

                    <video 
                        class="feed-video" 
                        playsinline 
                        preload="<?php echo $index === 0 ? 'auto' : 'metadata'; ?>"
                        data-video-id="<?php echo $vid['id']; ?>" 
                        data-reward="<?php echo $vid['reward']; ?>">
                        <source src="<?php echo htmlspecialchars($vid['url']); ?>" type="video/mp4">
                    </video>

                    <!-- Side Action Buttons -->
                    <div class="actions-sidebar">
                        <button class="action-btn like-btn" aria-label="Like video">
                            <i class="fa-solid fa-heart"></i>
                            <span class="like-count" data-likes="<?php echo (int)$vid['likes']; ?>">0</span>
                        </button>

                        <div class="action-btn">
                            <i class="fa-solid fa-coins" style="color: #eab308;"></i>
                            <span>+$<?php echo number_format($vid['reward'], 2); ?></span>
                        </div>

                        <button class="action-btn share-btn" aria-label="Share">
                            <i class="fa-solid fa-share"></i>
                            <span>Share</span>
                        </button>
                    </div>

                    <!-- Video Details Overlay -->
                    <div class="video-overlay-info">
                        <h3><?php echo htmlspecialchars($vid['title']); ?></h3>
                        <p>Watch full ad to earn <span>+$<?php echo number_format($vid['reward'], 2); ?></span> directly to your balance.</p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Empty state message displayed when all videos are finished -->
        <div class="no-videos-container" id="noVideosMsg" style="<?php echo empty($videos) ? 'display: flex;' : 'display: none;'; ?>">
            <i class="fa-solid fa-circle-check" style="font-size: 56px; color: #22c55e; margin-bottom: 16px;"></i>
            <h2>All Videos Watched!</h2>
            <p style="margin-top: 8px; color: #9ca3af;"><?php echo $video_error ?: 'You have watched all available videos for today. Please check back later for new ads!'; ?></p>
        </div>
    </div>

    <div id="notificationContainer"></div>

    <!-- Fixed Bottom Menu -->
    <div class="bottom-menu" role="navigation">
        <a href="home.php" class="active"><i class="fa-solid fa-house"></i>Home</a>
        <a href="profile.php"><i class="fa-solid fa-user"></i>Profile</a>
        <a href="history.php"><i class="fa-solid fa-clock-rotate-left"></i>History</a>
        <a href="support.php"><i class="fa-solid fa-headset"></i>Support</a>
        <button id="logoutBtn" aria-label="Log out"><i class="fa-solid fa-right-from-bracket"></i>Logout</button>
    </div>

    <script>
        function formatLikes(num) {
            if (num >= 1000000) return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
            if (num >= 1000) return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
            return num.toString();
        }

        document.querySelectorAll('.like-count').forEach(span => {
            const rawLikes = parseInt(span.getAttribute('data-likes')) || 0;
            span.textContent = formatLikes(rawLikes);
        });

        const feed = document.getElementById('tiktokFeed');

        function checkEmptyFeed() {
            const visibleCards = document.querySelectorAll('.video-card:not([style*="display: none"])');
            if (visibleCards.length === 0) {
                document.getElementById('noVideosMsg').style.display = 'flex';
            }
        }

        function scrollToNextVideo(card) {
            const remainingCards = Array.from(document.querySelectorAll('.video-card')).filter(c => c.style.display !== 'none' && c !== card);
            if (remainingCards.length > 0) {
                setTimeout(() => {
                    remainingCards[0].scrollIntoView({ behavior: 'smooth' });
                }, 800);
            }
        }

        // IntersectionObserver for video autoplay and timer countdown
        const observerOptions = {
            root: feed,
            threshold: 0.6
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const video = entry.target.querySelector('video');
                if (!video) return;

                if (entry.isIntersecting) {
                    video.play().catch(err => console.log('Autoplay prevented:', err));
                } else {
                    video.pause();
                }
            });
        }, observerOptions);

        document.querySelectorAll('.video-card').forEach(card => observer.observe(card));

        // Video interaction, real-time timer countdown, and completion logic
        document.querySelectorAll('.feed-video').forEach(video => {
            const card = video.closest('.video-card');
            const timerDisplay = card.querySelector('.timer-display');

            // Dynamic live time update display
            video.addEventListener('timeupdate', function() {
                if (video.duration) {
                    const remaining = Math.ceil(video.duration - video.currentTime);
                    timerDisplay.textContent = remaining > 0 ? `${remaining}s` : 'Processing...';
                }
            });

            video.addEventListener('click', function() {
                if (video.paused) {
                    video.play();
                } else {
                    video.pause();
                }
            });

            // Double tap heart gesture
            let lastTap = 0;
            video.addEventListener('touchend', function(e) {
                const currentTime = new Date().getTime();
                const tapLength = currentTime - lastTap;
                if (tapLength < 300 && tapLength > 0) {
                    const likeBtn = card.querySelector('.like-btn');
                    triggerLike(likeBtn);

                    const touch = e.changedTouches[0];
                    const heart = document.createElement('i');
                    heart.className = 'fa-solid fa-heart heart-animation';
                    heart.style.left = `${touch.clientX}px`;
                    heart.style.top = `${touch.clientY}px`;
                    card.appendChild(heart);
                    setTimeout(() => heart.remove(), 800);
                }
                lastTap = currentTime;
            });

            // Video completion reward dispatch
            video.addEventListener('ended', function() {
                const videoId = parseInt(video.getAttribute('data-video-id'));

                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: { action: 'claim_reward', video_id: videoId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            // Update total user balance UI dynamically
                            document.getElementById('balance').textContent = response.new_balance;

                            Swal.fire({
                                icon: 'success',
                                title: 'Reward Earned!',
                                text: `+$${response.reward} added to your balance!`,
                                timer: 1800,
                                showConfirmButton: false
                            });

                            // Smoothly fade out and remove watched video card from view
                            $(card).fadeOut(400, function() {
                                card.remove();
                                checkEmptyFeed();
                            });

                            scrollToNextVideo(card);
                        } else {
                            Swal.fire({
                                icon: 'info',
                                title: 'Notice',
                                text: response.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to add reward. Please check your connection.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                });
            });
        });

        // Like button toggle
        document.querySelectorAll('.like-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                triggerLike(btn);
            });
        });

        function triggerLike(btn) {
            const isLiked = btn.classList.toggle('liked');
            const countSpan = btn.querySelector('.like-count');
            let rawCount = parseInt(countSpan.getAttribute('data-likes')) || 0;
            rawCount = isLiked ? rawCount + 1 : Math.max(0, rawCount - 1);
            
            countSpan.setAttribute('data-likes', rawCount);
            countSpan.textContent = formatLikes(rawCount);
        }

        // Native share functionality
        document.querySelectorAll('.share-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (navigator.share) {
                    navigator.share({
                        title: 'Watch & Earn on Cash Tube',
                        url: window.location.href
                    }).catch(console.error);
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: 'Share',
                        text: 'Link copied to clipboard!'
                    });
                }
            });
        });

        // Logout Flow
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
                        notification.style.top = `${125 + index * 60}px`;
                        setTimeout(() => notification.remove(), 3500);
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
