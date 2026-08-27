<?php
// contact.php
require_once 'database/conn.php';

// Fetch Telegram username from c_support table
$telegram_raw = '';
$telegram_clean = '';
try {
    $stmt = $pdo->prepare("SELECT telegram FROM c_support LIMIT 1");
    $stmt->execute();
    $support_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($support_data && !empty($support_data['telegram'])) {
        $telegram_raw = trim($support_data['telegram']);
        // Strip out '@' or URL protocol prefixes if present for valid t.me link
        $telegram_clean = ltrim($telegram_raw, '@');
        $telegram_clean = str_replace(['https://t.me/', 'http://t.me/'], '', $telegram_clean);
    }
} catch (PDOException $e) {
    error_log('Telegram support username fetch error in contact.php: ' . $e->getMessage(), 3, 'debug.log');
}

// Fallback in case table/record is not set yet
if (empty($telegram_raw)) {
    $telegram_raw = '@TaskTubeSupport';
    $telegram_clean = 'TaskTubeSupport';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Contact Task Tube's support team 24/7 via Telegram for assistance with your account, login, or general inquiries.">
    <meta name="keywords" content="Task Tube, contact support, earn money online, watch ads, customer service">
    <meta name="author" content="Task Tube">
    <title>Task Tube - Contact Us</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: #f5f7fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: #333;
            padding-top: 80px; /* Matches header height */
            padding-bottom: 100px; /* Matches footer height */
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #6e44ff, #b5179e);
            color: #fff;
            text-align: center;
            padding: 100px 20px;
            position: relative;
            overflow: hidden;
            z-index: 10;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('https://source.unsplash.com/random/1920x1080/?technology') no-repeat center center/cover;
            opacity: 0.1;
            z-index: 0;
        }

        .hero-section h1 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .hero-section p {
            font-size: 18px;
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto 30px;
            position: relative;
            z-index: 1;
        }

        /* Main Container */
        .index-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .section-title {
            font-size: 36px;
            font-weight: 600;
            color: #333;
            text-align: center;
            margin-bottom: 40px;
        }

        .contact-content {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .contact-content h2 {
            font-size: 24px;
            font-weight: 600;
            color: #6e44ff;
            margin: 30px 0 15px;
            text-align: left;
        }

        .contact-content p {
            font-size: 16px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
            text-align: left;
        }

        .contact-content ul {
            list-style: none;
            padding: 0;
            margin-bottom: 20px;
            text-align: left;
        }

        .contact-content ul li {
            font-size: 16px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 10px;
            position: relative;
            padding-left: 30px;
        }

        .contact-content ul li::before {
            content: '\f058';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: #6e44ff;
            position: absolute;
            left: 0;
            top: 2px;
        }

        .contact-info p strong {
            color: #333;
            font-weight: 600;
        }

        .contact-info p a {
            color: #6e44ff;
            text-decoration: none;
        }

        .contact-info p a:hover {
            text-decoration: underline;
        }

        /* CTA Banner */
        .cta-banner {
            background: linear-gradient(135deg, #6e44ff, #b5179e);
            color: #fff;
            text-align: center;
            padding: 60px 20px;
            border-radius: 15px;
            margin: 40px 20px;
        }

        .cta-banner h2 {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .cta-banner .btn {
            background-color: #fff;
            color: #6e44ff;
            padding: 15px 40px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 50px;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        .cta-banner .btn:hover {
            background-color: #f0f0f0;
        }

        /* Button Styles */
        .signup-link .btn {
            background-color: #6e44ff;
            color: #fff;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 500;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .signup-link .btn:hover {
            background-color: #5a00b5;
        }

        /* Notice Popup */
        .notice {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            padding: 30px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            display: none;
            z-index: 1002;
        }

        .notice h2 {
            font-size: 24px;
            color: #6e44ff;
            margin-bottom: 15px;
        }

        .notice p {
            font-size: 16px;
            color: #666;
            margin-bottom: 20px;
            text-align: center;
        }

        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            transition: color 0.3s ease;
        }

        .close-btn:hover {
            color: #333;
        }

        .notice .btn {
            background-color: #6e44ff;
            color: #fff;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 500;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .notice .btn:hover {
            background-color: #5a00b5;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .hero-section h1 {
                font-size: 36px;
            }

            .hero-section p {
                font-size: 16px;
            }

            .section-title {
                font-size: 30px;
            }

            .contact-content {
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 70px;
                padding-bottom: 80px;
            }

            .hero-section {
                padding: 80px 20px;
            }

            .hero-section h1 {
                font-size: 32px;
            }

            .hero-section p {
                font-size: 15px;
            }

            .section-title {
                font-size: 28px;
            }

            .cta-banner h2 {
                font-size: 28px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding-top: 60px;
                padding-bottom: 60px;
            }

            .hero-section {
                padding: 60px 15px;
            }

            .hero-section h1 {
                font-size: 28px;
            }

            .hero-section p {
                font-size: 14px;
            }

            .section-title {
                font-size: 24px;
            }

            .contact-content {
                padding: 15px;
            }

            .cta-banner {
                padding: 40px 15px;
            }

            .cta-banner h2 {
                font-size: 24px;
            }

            .cta-banner .btn {
                padding: 12px 30px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <?php include 'inc/header.php'; ?>
    <?php include 'inc/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <h1>Contact Task Tube</h1>
        <p>Reach out to our 24/7 support team via Telegram for help with your account, login, or any inquiries!</p>
    </section>

    <!-- Contact Content -->
    <div class="index-container">
        <h2 class="section-title">Get in Touch</h2>
        <div class="contact-content">
            <p>
                We're here to help with any questions or issues you may have! At Task Tube, our dedicated support team is available 24/7. Whether you need help with your account, login, or have general inquiries, feel free to reach out.
            </p>

            <div class="contact-info">
                <h2>Contact Information</h2>
                <p>
                    <i class="fab fa-telegram"></i> Telegram Contact: 
                    <strong>
                        <a href="https://t.me/<?php echo $telegram_clean; ?>" onclick="openTelegram(event, 'https://t.me/<?php echo $telegram_clean; ?>')">
                            <?php echo htmlspecialchars($telegram_raw); ?>
                        </a>
                    </strong>
                </p>
                <p><i class="far fa-clock"></i> Availability: <strong>24/7</strong></p>
                <p><i class="fas fa-hourglass-half"></i> Response Time: <strong>Usually within 24 hours</strong></p>
            </div>

            <div class="categories">
                <h2>We Can Help With:</h2>
                <ul>
                    <li>Technical Support for Login/Access Issues</li>
                    <li>Verification Requests</li>
                </ul>
            </div>

            <p class="signup-link">
                Not yet a member? <a href="register.php" class="btn">Sign Up Now</a>
            </p>
        </div>
    </div>

    <!-- CTA Banner -->
    <section class="cta-banner">
        <h2>Need Help? Contact Us Now!</h2>
        <a href="https://t.me/<?php echo $telegram_clean; ?>" class="btn" onclick="openTelegram(event, 'https://t.me/<?php echo $telegram_clean; ?>')">
            Message Us on Telegram
        </a>
    </section>

    <!-- Notice Popup -->
    <div class="notice" id="notice">
        <span class="close-btn" onclick="closeNotice()" aria-label="Close notice">×</span>
        <h2>Contact Task Tube</h2>
        <p>Need assistance? Our support team is here to help you 24/7 via Telegram. Reach out today to get started or resolve any issues!</p>
        <a href="https://t.me/<?php echo $telegram_clean; ?>" class="btn" onclick="openTelegram(event, 'https://t.me/<?php echo $telegram_clean; ?>')">
            Message Us
        </a>
    </div>

    <?php include 'inc/footer.php'; ?>

    <!-- LiveChat Script -->
    <script>
        window.__lc = window.__lc || {};
        window.__lc.license = 15808029;
        (function(n,t,c){function i(n){return e._h?e._h.apply(null,n):e._q.push(n)}var e={_q:[],_h:null,_v:"2.0",on:function(){i(["on",c.call(arguments)])},once:function(){i(["once",c.call(arguments)])},off:function(){i(["off",c.call(arguments)])},get:function(){if(!e._h)throw new Error("[LiveChatWidget] You can't use getters before load.");return i(["get",c.call(arguments)])},call:function(){i(["call",c.call(arguments)])},init:function(){var n=t.createElement("script");n.async=!0,n.type="text/javascript",n.src="https://cdn.livechatinc.com/tracking.js",t.head.appendChild(n)}};!n.__lc.asyncInit&&e.init(),n.LiveChatWidget=n.LiveChatWidget||e}(window,document,[].slice))
    </script>
    <noscript><a href="https://www.livechat.com/chat-with/15808029/" rel="nofollow">Chat with us</a>, powered by <a href="https://www.livechat.com/?welcome" rel="noopener nofollow" target="_blank">LiveChat</a></noscript>

    <script>
        // Telegram Deep Link Handling for WebViews
        function openTelegram(event, url) {
            event.preventDefault();
            
            // Check if loaded inside a WebView environment
            const isWebView = /wv|AndroidWebView|iPhone.*Mobile/i.test(navigator.userAgent) || window.Android || (window.webkit && window.webkit.messageHandlers);
            
            if (isWebView) {
                // Try opening using external browser intent target
                var windowRef = window.open(url, '_system');
                if (!windowRef || windowRef.closed || typeof windowRef.closed == 'undefined') {
                    // Fallback to directly changing browser location
                    window.location.href = url;
                }
            } else {
                // Standard browser behavior
                window.open(url, '_blank', 'noopener,noreferrer');
            }
        }

        // Set Active Navbar Link
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname.split('/').pop();
            const links = document.querySelectorAll('.ham-menu ul li a');
            links.forEach(link => {
                if (link.getAttribute('href') === currentPath || (currentPath === '' && link.getAttribute('href') === 'index.php')) {
                    link.parentElement.classList.add('active');
                }
            });
        });

        // Notice Popup
        function isNoticeShown() {
            return localStorage.getItem('noticeShownContact');
        }

        function setNoticeShown() {
            localStorage.setItem('noticeShownContact', true);
        }

        function showNotice() {
            if (!isNoticeShown()) {
                const notice = document.getElementById('notice');
                setTimeout(() => {
                    notice.style.display = 'block';
                    setNoticeShown();
                }, 2000); // Match index.php timing
            }
        }

        function closeNotice() {
            document.getElementById('notice').style.display = 'none';
            setNoticeShown();
        }

        window.addEventListener('load', showNotice);

        // Prevent right-click only on non-link elements
        document.addEventListener('contextmenu', e => {
            if (!e.target.closest('a')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
