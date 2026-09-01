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
    $balance = number_format($user['balance'], 2);
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

// Fetch up to 10 unwatched videos along with their likes count
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
        $full_url = 'https://tasktube.gt.tc/users/videos/' . basename($vid['url']);
        $url_check = url_exists($full_url);
        if ($url_check['status']) {
            $vid['url'] = $full_url;
            $videos[] = $vid;
        }
    }

    if (empty($videos)) {
        $video_error = 'No ads available at the moment, please check back later.';
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

        /* Fixed Top Floating Header (Positioned below translate bar) */
        /* Fixed Top Floating Header */
        .top-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 67px 20px 12px 20px; /* Top padding pushes content down while keeping background gradient at top */
            background: linear-gradient(180deg, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.4) 70%, rgba(0,0,0,0) 100%);
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
            bottom: 170px;
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

        /* Notification Toast (Adjusted top position) */
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

    <!-- Fixed Header (Positioned below translate bar) -->
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
        <div class="notification" role="alert">
            <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i>
            <span><?php echo $error_message; ?></span>
        </div>
    <?php endif; ?>

    <!-- TikTok Scroll Container -->
    <div class="tiktok-feed" id="tiktokFeed">
        <?php if (!empty($videos)): ?>
            <?php foreach ($videos as $index => $vid): ?>
                <div class="video-card" data-index="<?php echo $index; ?>">
                    <video 
                        class="feed-video" 
                        playsinline 
                        loop 
                        preload="<?php echo $index === 0 ? 'auto' : 'metadata'; ?>"
                        data-video-id="<?php echo $vid['id']; ?>" 
                        data-reward="<?php echo $vid['reward']; ?>">
                        <source src="<?php echo htmlspecialchars($vid['url']); ?>" type="video/mp4">
                    </video>

                    <!-- Side Action Buttons (Like, Earn, Share) -->
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

                    <!-- Video Details overlay -->
                    <div class="video-overlay-info">
                        <h3><?php echo htmlspecialchars($vid['title']); ?></h3>
                        <p>Watch full ad to earn <span>+$<?php echo number_format($vid['reward'], 2); ?></span> crypto directly to your balance.</p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-videos-container">
                <i class="fa-solid fa-video-slash" style="font-size: 48px; color: #6b7280; margin-bottom: 16px;"></i>
                <p><?php echo $video_error ?: 'No ads available at the moment, please check back later.'; ?></p>
            </div>
        <?php endif; ?>
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
        // Helper function to format numbers into compact strings (e.g. 1000 => 1k, 2400 => 2.4k)
        function formatLikes(num) {
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
            }
            if (num >= 1000) {
                return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
            }
            return num.toString();
        }

        // Initialize display of shortened likes
        document.querySelectorAll('.like-count').forEach(span => {
            const rawLikes = parseInt(span.getAttribute('data-likes')) || 0;
            span.textContent = formatLikes(rawLikes);
        });

        const initialBalance = parseFloat(document.getElementById('balance').textContent);
        const feed = document.getElementById('tiktokFeed');
        const cards = document.querySelectorAll('.video-card');
        let currentVideo = null;
        let watchTimer = null;
        let currentVideoProgress = 0;

        // Local Storage for storing earned rewards by video ID
        let storedEarnings = JSON.parse(localStorage.getItem('earned_rewards') || '{}');

        // Function to compute total stored earnings and update total balance display
        function updateDisplayBalance() {
            let totalStored = 0;
            for (let id in storedEarnings) {
                totalStored += parseFloat(storedEarnings[id] || 0);
            }
            const grandTotal = initialBalance + totalStored + currentVideoProgress;
            document.getElementById('balance').textContent = grandTotal.toFixed(2);
        }

        // Initialize balance with existing locally saved earnings
        updateDisplayBalance();

        // IntersectionObserver to auto-play active video and pause non-visible ones
        const observerOptions = {
            root: feed,
            threshold: 0.6
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const video = entry.target.querySelector('video');
                if (!video) return;

                if (entry.isIntersecting) {
                    currentVideo = video;
                    video.play().catch(err => console.log('Autoplay prevented:', err));
                    startRewardTracking(video);
                } else {
                    video.pause();
                    video.currentTime = 0;
                    stopRewardTracking();
                }
            });
        }, observerOptions);

        cards.forEach(card => observer.observe(card));

        // Click on video canvas to toggle Play / Pause
        document.querySelectorAll('.feed-video').forEach(video => {
            video.addEventListener('click', function() {
                if (video.paused) {
                    video.play();
                } else {
                    video.pause();
                }
            });

            // Handle double-tap like gesture
            let lastTap = 0;
            video.addEventListener('touchend', function(e) {
                const currentTime = new Date().getTime();
                const tapLength = currentTime - lastTap;
                if (tapLength < 300 && tapLength > 0) {
                    const card = video.closest('.video-card');
                    const likeBtn = card.querySelector('.like-btn');
                    triggerLike(likeBtn);

                    // Render animated floating heart at touch target
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

            // Video watched completion handler
            video.addEventListener('ended', function() {
                const videoId = video.getAttribute('data-video-id');
                const reward = parseFloat(video.getAttribute('data-reward'));

                $.ajax({
                    url: 'process_video_watch.php',
                    type: 'POST',
                    data: { video_id: videoId, reward: reward },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Reward Earned!',
                                text: `You earned $${response.reward.toFixed(2)}!`,
                                timer: 1500,
                                showConfirmButton: false
                            });
                            // Store reward persistently for this video ID
                            storedEarnings[videoId] = reward;
                            localStorage.setItem('earned_rewards', JSON.stringify(storedEarnings));
                            currentVideoProgress = 0;
                            updateDisplayBalance();
                        }
                    }
                });
            });
        });

        // Like Button click handler
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

        // Real-time incremental earnings tracking during watch
        function startRewardTracking(video) {
            stopRewardTracking();
            const videoId = video.getAttribute('data-video-id');
            const totalReward = parseFloat(video.getAttribute('data-reward'));

            // If already fully earned, skip temporary calculation
            if (storedEarnings[videoId]) {
                currentVideoProgress = 0;
                updateDisplayBalance();
                return;
            }

            watchTimer = setInterval(() => {
                if (!video.paused && video.duration) {
                    const rewardPerSec = totalReward / video.duration;
                    currentVideoProgress += rewardPerSec;
                    if (currentVideoProgress > totalReward) currentVideoProgress = totalReward;
                    updateDisplayBalance();
                }
            }, 1000);
        }

        function stopRewardTracking() {
            if (watchTimer) {
                clearInterval(watchTimer);
                watchTimer = null;
            }
            currentVideoProgress = 0;
            updateDisplayBalance();
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
