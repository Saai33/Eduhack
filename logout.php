<?php
/**
 * =============================================
 * Logout Page - EduHack AI (With Confirmation)
 * =============================================
 * 
 * This page shows a logout confirmation message
 * before redirecting to login page.
 */

// Start session
session_start();

// Include database
require_once 'includes/db.php';

// Log the logout activity (optional)
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['full_name'] ?? 'User';
    
    // You can log this to a database table if you have one
    // For now, we'll just store in a variable
    $logout_message = "User '$username' (ID: $user_id) logged out at " . date('Y-m-d H:i:s');
    
    // You could insert this into a logs table:
    // $sql = "INSERT INTO activity_logs (user_id, action, timestamp) VALUES ($user_id, 'logout', NOW())";
    // mysqli_query($conn, $sql);
}

// Unset all session variables
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Store logout message for display on login page
session_start(); // Start a new session just for message
$_SESSION['logout_success'] = 'You have been successfully logged out. See you soon! 👋';
session_write_close();

// Redirect after 3 seconds (optional)
// You can use meta refresh or JavaScript
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - EduHack AI</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #6C63FF 0%, #8B5CF6 50%, #6C63FF 100%);
            background-size: 200% 200%;
            animation: gradientShift 8s ease infinite;
            padding: 20px;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .logout-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 50px 40px;
            border-radius: 24px;
            text-align: center;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.6s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logout-icon {
            font-size: 72px;
            margin-bottom: 20px;
            display: block;
            animation: bounceIcon 1s ease infinite;
        }

        @keyframes bounceIcon {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .logout-card h1 {
            font-size: 28px;
            color: #1F2937;
            margin-bottom: 12px;
        }

        .logout-card h1 span {
            color: #6C63FF;
        }

        .logout-card p {
            color: #6B7280;
            font-size: 16px;
            margin-bottom: 24px;
            line-height: 1.6;
        }

        .spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid #E5E7EB;
            border-top: 4px solid #6C63FF;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 16px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .btn-primary {
            display: inline-block;
            padding: 12px 32px;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(108, 99, 255, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(108, 99, 255, 0.4);
        }

        .btn-secondary {
            display: inline-block;
            padding: 12px 32px;
            background: transparent;
            color: #6C63FF;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-left: 12px;
        }

        .btn-secondary:hover {
            border-color: #6C63FF;
            background: rgba(108, 99, 255, 0.05);
        }

        .redirect-timer {
            font-size: 14px;
            color: #9CA3AF;
            margin-top: 16px;
        }

        .redirect-timer strong {
            color: #6C63FF;
            font-size: 18px;
        }

        @media (max-width: 480px) {
            .logout-card {
                padding: 30px 20px;
            }

            .logout-icon {
                font-size: 56px;
            }

            .logout-card h1 {
                font-size: 24px;
            }

            .btn-secondary {
                margin-left: 0;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>

    <div class="logout-card">
        <span class="logout-icon">👋</span>
        <h1>See You <span>Soon!</span></h1>
        <p>You have been successfully logged out of EduHack AI. We hope to see you again!</p>
        
        <div class="spinner"></div>
        
        <div>
            <a href="login.php" class="btn-primary">
                <i class="fas fa-sign-in-alt"></i> Login Again
            </a>
            <a href="index.php" class="btn-secondary">
                <i class="fas fa-home"></i> Home
            </a>
        </div>
        
        <div class="redirect-timer">
            Redirecting to login in <strong id="timer">3</strong> seconds...
        </div>
    </div>

    <script>
        // =============================================
        // AUTO REDIRECT COUNTDOWN
        // =============================================
        let seconds = 3;
        const timerElement = document.getElementById('timer');
        
        const countdown = setInterval(function() {
            seconds--;
            timerElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(countdown);
                window.location.href = 'login.php?logout=success';
            }
        }, 1000);

        // =============================================
        // CONSOLE LOG
        // =============================================
        console.log('👋 Logged out successfully from EduHack AI');
        console.log('🔄 Redirecting to login page in 3 seconds...');
    </script>

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</body>
</html>