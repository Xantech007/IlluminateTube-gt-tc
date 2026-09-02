<?php
// signin.php
session_start();
require_once 'database/conn.php';
$response = ['success' => false, 'error' => ''];

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['password'])) {
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);
   
    if (empty($email) || empty($password)) {
        $response['error'] = "Email and passcode are required.";
        file_put_contents('debug.log', 'Missing email or passcode' . "\n", FILE_APPEND);
    }
    // Allows passcodes of 1+ character
    else {
        try {
            $stmt = $pdo->prepare("SELECT id, email, passcode FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['passcode'])) {
                $response['success'] = true;
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['email']    = $user['email'];
                $_SESSION['passcode'] = $user['passcode'];
                file_put_contents('debug.log', 'Sign-in successful: ' . $email . "\n", FILE_APPEND);
            } else {
                $response['error'] = "Invalid email or passcode.";
                file_put_contents('debug.log', 'Failed login: ' . $email . "\n", FILE_APPEND);
            }
        } catch (PDOException $e) {
            $response['error'] = "Database error occurred.";
            file_put_contents('debug.log', 'DB Error: ' . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
   
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign in to Illuminate Tube with your email and passcode to access your vault archives and start earning rewards.">
    <meta name="keywords" content="Illuminate Tube, sign in, initiate login, earn rewards, vault access, passcode">
    <meta name="author" content="Illuminate Tube">
    <title>Illuminate Tube - Sign In</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        body {
            background: #0d0d0d;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: #e0e0e0;
            padding-top: 80px;
            padding-bottom: 100px;
        }
        .hero-section {
            background: linear-gradient(135deg, #000000, #1a1a1a);
            color: #ffd700;
            text-align: center;
            padding: 100px 20px;
            position: relative;
            overflow: hidden;
            z-index: 10;
            border-bottom: 2px solid #d4af37;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('https://source.unsplash.com/random/1920x1080/?gold,dark') no-repeat center center/cover;
            opacity: 0.15;
            z-index: 0;
        }
        .hero-section h1 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
            color: #d4af37;
            text-shadow: 0 0 10px rgba(212, 175, 55, 0.3);
        }
        .hero-section p {
            font-size: 18px;
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto 30px;
            position: relative;
            z-index: 1;
            color: #cccccc;
        }
        .index-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .section-title {
            font-size: 36px;
            font-weight: 600;
            color: #d4af37;
            text-align: center;
            margin-bottom: 40px;
        }
        .signin-content {
            max-width: 500px;
            margin: 0 auto;
            background: #141414;
            border: 1px solid #333;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
            text-align: center;
        }
        .signin-content h1 {
            font-size: 28px;
            font-weight: 600;
            color: #ffd700;
            margin-bottom: 10px;
        }
        .signin-content p {
            font-size: 16px;
            color: #aaa;
            margin-bottom: 20px;
        }
        .signin-content p span {
            color: #ffd700;
            font-weight: 600;
        }
        .input-field {
            width: 100%;
            height: 50px;
            font-size: 16px;
            padding: 10px 15px;
            background: #0d0d0d;
            color: #fff;
            border: 2px solid #333;
            border-radius: 10px;
            margin-bottom: 20px;
            outline: none;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .input-field:focus {
            border-color: #d4af37;
            box-shadow: 0 0 8px rgba(212, 175, 55, 0.4);
        }
        .btn {
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 500;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-block;
        }
        .submit-btn {
            background: linear-gradient(45deg, #d4af37, #ffd700);
            color: #000;
            border: none;
            border-radius: 25px;
            padding: 15px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
        }
        .submit-btn:hover {
            background: linear-gradient(45deg, #ffd700, #b8860b);
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.4);
        }
        .signin-link {
            font-size: 14px;
            color: #888;
            margin-top: 20px;
        }
        .signin-link a {
            color: #ffd700;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .signin-link a:hover {
            color: #fff;
            text-decoration: underline;
        }
        .cta-banner {
            background: linear-gradient(135deg, #1a1a1a, #000000);
            border: 1px solid #d4af37;
            color: #fff;
            text-align: center;
            padding: 60px 20px;
            border-radius: 15px;
            margin: 40px 20px;
            box-shadow: 0 0 20px rgba(212, 175, 55, 0.2);
        }
        .cta-banner h2 {
            font-size: 32px;
            font-weight: 600;
            color: #ffd700;
            margin-bottom: 20px;
        }
        .cta-banner .btn {
            background: linear-gradient(45deg, #d4af37, #ffd700);
            color: #000;
            padding: 15px 40px;
            font-size: 18px;
            font-weight: 700;
            border-radius: 50px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }
        .cta-banner .btn:hover {
            background: linear-gradient(45deg, #ffd700, #b8860b);
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.5);
        }
        .notice {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #141414;
            border: 2px solid #d4af37;
            border-radius: 15px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.8);
            padding: 30px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            display: none;
            z-index: 1002;
        }
        .notice h2 {
            font-size: 24px;
            color: #ffd700;
            margin-bottom: 15px;
        }
        .notice p {
            font-size: 16px;
            color: #aaa;
            margin-bottom: 20px;
            text-align: center;
        }
        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #888;
            transition: color 0.3s ease;
        }
        .close-btn:hover {
            color: #ffd700;
        }
        .notice .btn {
            background: linear-gradient(45deg, #d4af37, #ffd700);
            color: #000;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 700;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }
        .notice .btn:hover {
            background: linear-gradient(45deg, #ffd700, #b8860b);
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.5);
        }
        @media (max-width: 1024px) {
            .hero-section h1 { font-size: 36px; }
            .hero-section p { font-size: 16px; }
            .section-title { font-size: 30px; }
            .signin-content { padding: 20px; }
        }
        @media (max-width: 768px) {
            body { padding-top: 70px; padding-bottom: 80px; }
            .hero-section { padding: 80px 20px; }
            .hero-section h1 { font-size: 32px; }
            .hero-section p { font-size: 15px; }
            .section-title { font-size: 28px; }
            .signin-content { padding: 20px; margin: 0 20px; }
            .input-field { height: 45px; font-size: 15px; }
            .submit-btn { padding: 12px; font-size: 16px; }
            .cta-banner h2 { font-size: 28px; }
        }
        @media (max-width: 480px) {
            body { padding-top: 60px; padding-bottom: 60px; }
            .hero-section { padding: 60px 15px; }
            .hero-section h1 { font-size: 28px; }
            .hero-section p { font-size: 14px; }
            .section-title { font-size: 24px; }
            .signin-content { padding: 15px; margin: 0 15px; }
            .input-field { height: 40px; font-size: 14px; }
            .submit-btn { padding: 12px; font-size: 16px; }
            .cta-banner { padding: 40px 15px; }
            .cta-banner h2 { font-size: 24px; }
            .cta-banner .btn { padding: 12px 30px; font-size: 16px; }
        }
    </style>
</head>
<body>
    <?php include 'inc/header.php'; ?>
    <?php include 'inc/navbar.php'; ?>
    <section class="hero-section">
        <h1>Sign In to Illuminate Tube</h1>
        <p>Enter your email and passcode to access your initiate account and start exploring vault archives.</p>
    </section>
    <div class="index-container">
        <h2 class="section-title">Initiate Sign In</h2>
        <div class="signin-content">
            <h1>Welcome to <span>Illuminate Tube</span></h1>
            <p>Enter your credentials to continue</p>
            <form id="signin-form" method="POST">
                <input type="email" id="email" name="email" class="input-field" placeholder="Enter your email" required aria-label="Email input">
                <input type="password" id="password" name="password" class="input-field" placeholder="Enter your passcode" required aria-label="Passcode input">
                <button type="submit" class="submit-btn btn">Sign In</button>
            </form>
            <p class="signin-link">Not yet an initiate? <a href="register.php">Register Now</a></p>
        </div>
    </div>
    <section class="cta-banner">
        <h2>Not Yet an Initiate?</h2>
        <a href="register.php" class="btn" onclick="console.log('CTA button clicked')">Join Illuminate Tube Now</a>
    </section>
    <div class="notice" id="notice">
        <span class="close-btn" onclick="closeNotice()" aria-label="Close notice">×</span>
        <h2>Access Illuminate Tube</h2>
        <p>Use your email and passcode to access your initiate portal. Need an account? Register today!</p>
        <a href="register.php" class="btn" onclick="console.log('Notice button clicked')">Register Now</a>
    </div>
    <?php include 'inc/footer.php'; ?>
    <script>
        window.__lc = window.__lc || {};
        window.__lc.license = 15808029;
        (function(n,t,c){function i(n){return e._h?e._h.apply(null,n):e._q.push(n)}var e={_q:[],_h:null,_v:"2.0",on:function(){i(["on",c.call(arguments)])},once:function(){i(["once",c.call(arguments)])},off:function(){i(["off",c.call(arguments)])},get:function(){if(!e._h)throw new Error("[LiveChatWidget] You can't use getters before load.");return i(["get",c.call(arguments)])},call:function(){i(["call",c.call(arguments)])},init:function(){var n=t.createElement("script");n.async=!0;n.type="text/javascript";n.src="https://cdn.livechatinc.com/tracking.js";t.head.appendChild(n)}};!n.__lc.asyncInit&&e.init(),n.LiveChatWidget=n.LiveChatWidget||e}(window,document,[].slice))
    </script>
    <noscript><a href="https://www.livechat.com/chat-with/15808029/" rel="nofollow">Chat with us</a>, powered by <a href="https://www.livechat.com/?welcome" rel="noopener nofollow" target="_blank">LiveChat</a></noscript>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname.split('/').pop();
            const links = document.querySelectorAll('.ham-menu ul li a');
            links.forEach(link => {
                if (link.getAttribute('href') === currentPath || (currentPath === '' && link.getAttribute('href') === 'index.php')) {
                    link.parentElement.classList.add('active');
                }
            });
        });

        function isNoticeShown() {
            return localStorage.getItem('noticeShownSignIn');
        }
        function setNoticeShown() {
            localStorage.setItem('noticeShownSignIn', true);
        }
        function showNotice() {
            if (!isNoticeShown()) {
                const notice = document.getElementById('notice');
                setTimeout(() => {
                    notice.style.display = 'block';
                    setNoticeShown();
                }, 2000);
            }
        }
        function closeNotice() {
            document.getElementById('notice').style.display = 'none';
            setNoticeShown();
        }
        window.addEventListener('load', showNotice);

        document.getElementById('signin-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const email    = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();

            if (!email) {
                Swal.fire({icon: 'error', title: 'Oops...', text: 'Please enter your email.'});
                return;
            }
            if (!password) {
                Swal.fire({icon: 'error', title: 'Oops...', text: 'Please enter your passcode.'});
                return;
            }

            $.ajax({
                url: './signin.php',
                type: 'POST',
                data: { email: email, password: password },
                dataType: 'json',
                cache: false,
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Welcome back!',
                            text: 'Sign in successful',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = 'users/home.php';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Access Denied',
                            text: response.error || 'Invalid email or passcode',
                            footer: '<a href="register.php">Need an account? Register here</a>'
                        });
                        document.getElementById('password').value = '';
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Server Error',
                        text: 'Please try again later.'
                    });
                }
            });
        });

        // Disable right-click except on links
        document.addEventListener('contextmenu', e => {
            if (!e.target.closest('a')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
