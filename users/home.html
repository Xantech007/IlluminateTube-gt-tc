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
            <span id="usernameDisplay" style="font-size: 14px; font-weight: 600;">John Doe</span>
        </div>
        <div class="balance-badge">
            $<span id="balance">12.50</span>
        </div>
    </div>

    <!-- Notification Containers for feedback -->
    <div id="alertContainer"></div>

    <!-- TikTok Scroll Container -->
    <div class="tiktok-feed" id="tiktokFeed">
        
        <!-- Sample Video Card 1 -->
        <div class="video-card" data-index="0" id="video-card-101">
            <div class="reward-countdown-banner">
                <i class="fa-solid fa-stopwatch" style="color: #4ade80;"></i>
                Reward in: <span class="timer-display">--s</span>
            </div>

            <video 
                class="feed-video" 
                playsinline 
                preload="auto"
                data-video-id="101" 
                data-reward="0.50">
                <source src="https://www.w3schools.com/html/mov_bbb.mp4" type="video/mp4">
            </video>

            <div class="actions-sidebar">
                <button class="action-btn like-btn" aria-label="Like video">
                    <i class="fa-solid fa-heart"></i>
                    <span class="like-count" data-likes="1420">0</span>
                </button>

                <div class="action-btn">
                    <i class="fa-solid fa-coins" style="color: #eab308;"></i>
                    <span>+$0.50</span>
                </div>

                <button class="action-btn share-btn" aria-label="Share">
                    <i class="fa-solid fa-share"></i>
                    <span>Share</span>
                </button>
            </div>

            <div class="video-overlay-info">
                <h3>Big Buck Bunny Teaser</h3>
                <p>Watch full ad to earn <span>+$0.50</span> directly to your balance.</p>
            </div>
        </div>

        <!-- Sample Video Card 2 -->
        <div class="video-card" data-index="1" id="video-card-102">
            <div class="reward-countdown-banner">
                <i class="fa-solid fa-stopwatch" style="color: #4ade80;"></i>
                Reward in: <span class="timer-display">--s</span>
            </div>

            <video 
                class="feed-video" 
                playsinline 
                preload="metadata"
                data-video-id="102" 
                data-reward="0.75">
                <source src="https://www.w3schools.com/html/movie.mp4" type="video/mp4">
            </video>

            <div class="actions-sidebar">
                <button class="action-btn like-btn" aria-label="Like video">
                    <i class="fa-solid fa-heart"></i>
                    <span class="like-count" data-likes="850">0</span>
                </button>

                <div class="action-btn">
                    <i class="fa-solid fa-coins" style="color: #eab308;"></i>
                    <span>+$0.75</span>
                </div>

                <button class="action-btn share-btn" aria-label="Share">
                    <i class="fa-solid fa-share"></i>
                    <span>Share</span>
                </button>
            </div>

            <div class="video-overlay-info">
                <h3>Sample Cinematic Promo</h3>
                <p>Watch full ad to earn <span>+$0.75</span> directly to your balance.</p>
            </div>
        </div>

        <!-- Empty state message displayed when all videos are finished -->
        <div class="no-videos-container" id="noVideosMsg" style="display: none;">
            <i class="fa-solid fa-circle-check" style="font-size: 56px; color: #22c55e; margin-bottom: 16px;"></i>
            <h2>All Videos Watched!</h2>
            <p style="margin-top: 8px; color: #9ca3af;">You have watched all available videos for today. Please check back later for new ads!</p>
        </div>
    </div>

    <div id="notificationContainer"></div>

    <!-- Fixed Bottom Menu -->
    <div class="bottom-menu" role="navigation">
        <a href="home.html" class="active"><i class="fa-solid fa-house"></i>Home</a>
        <a href="profile.html"><i class="fa-solid fa-user"></i>Profile</a>
        <a href="history.html"><i class="fa-solid fa-clock-rotate-left"></i>History</a>
        <a href="support.html"><i class="fa-solid fa-headset"></i>Support</a>
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

            // Video completion reward dispatch (Simulated via frontend handlers)
            video.addEventListener('ended', function() {
                const rewardVal = parseFloat(video.getAttribute('data-reward'));
                
                // Update balance locally for demonstration
                const balanceEl = document.getElementById('balance');
                let currentBalance = parseFloat(balanceEl.textContent) || 0;
                currentBalance += rewardVal;
                balanceEl.textContent = currentBalance.toFixed(2);

                Swal.fire({
                    icon: 'success',
                    title: 'Reward Earned!',
                    text: `+$${rewardVal.toFixed(2)} added to your balance!`,
                    timer: 1800,
                    showConfirmButton: false
                });

                // Smoothly fade out and remove watched video card from view
                $(card).fadeOut(400, function() {
                    card.remove();
                    checkEmptyFeed();
                });

                scrollToNextVideo(card);
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
                    window.location.href = 'signin.html';
                }
            });
        });

        // Simulated notifications loop
        const notificationContainer = document.getElementById('notificationContainer');
        function showMockNotification() {
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.innerHTML = `<i class="fa-solid fa-circle-info" style="color: #4ade80;"></i><span>New bonus videos are available!</span>`;
            notificationContainer.appendChild(notification);
            setTimeout(() => notification.remove(), 3500);
        }
        setTimeout(showMockNotification, 3000);

        // Prevent Context Menu
        document.addEventListener('contextmenu', function(event) {
            event.preventDefault();
        });
    </script>
</body>
</html>
