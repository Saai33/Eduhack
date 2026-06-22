<?php
/**
 * =============================================
 * Login Page - EduHack AI
 * =============================================
 * 
 * This page handles user authentication and redirects
 * users to their respective dashboards based on role.
 */

// Start session
session_start();

// Include database connection
require_once 'includes/db.php';

$error = '';
$email = '';
$remember = false;

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validation
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Prepare statement to prevent SQL injection
        $stmt = mysqli_prepare($conn, "SELECT id, full_name, email, password, role, is_active FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) === 0) {
            $error = 'Invalid email or password.';
        } else {
            $user = mysqli_fetch_assoc($result);
            
            // Check if account is active
            if ($user['is_active'] == 0) {
                $error = 'Your account has been deactivated. Please contact admin.';
            } elseif (password_verify($password, $user['password'])) {
                // Login successful - set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // Update last login
                $update_sql = "UPDATE users SET last_login = NOW() WHERE id = " . $user['id'];
                mysqli_query($conn, $update_sql);
                
                // Remember Me - set cookie for 30 days
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (86400 * 30), '/');
                    // In production, store token in database
                }
                
                // Redirect based on role
                $role = $user['role'];
                if ($role === 'admin') {
                    header("Location: admin/dashboard.php");
                } elseif ($role === 'teacher') {
                    header("Location: teacher/dashboard.php");
                } else {
                    header("Location: student/dashboard.php");
                }
                exit();
            } else {
                $error = 'Invalid email or password.';
            }
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EduHack AI</title>
    <style>
        /* =============================================
           RESET & BASE
        ============================================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #FFFDF8;
            min-height: 100vh;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* =============================================
           CONTAINER
        ============================================= */
        .container {
            display: flex;
            width: 100%;
            max-width: 1200px;
            height: 100vh;
            max-height: 800px;
            background: #FFFDF8;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(108, 99, 255, 0.12);
            position: relative;
        }

        /* =============================================
           LEFT SIDE - BRANDING
        ============================================= */
        .left-side {
            flex: 1;
            background: linear-gradient(135deg, #6C63FF 0%, #8B5CF6 50%, #6C63FF 100%);
            background-size: 200% 200%;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
            animation: gradientShift 10s ease infinite;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* Floating Blobs */
        .blob {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            animation: floatBlob 20s infinite ease-in-out;
        }

        .blob-1 {
            width: 350px;
            height: 350px;
            top: -150px;
            right: -100px;
            animation-delay: 0s;
        }

        .blob-2 {
            width: 250px;
            height: 250px;
            bottom: -80px;
            left: -80px;
            animation-delay: -5s;
        }

        .blob-3 {
            width: 180px;
            height: 180px;
            top: 50%;
            left: 30%;
            transform: translate(-50%, -50%);
            animation-delay: -10s;
        }

        @keyframes floatBlob {
            0%, 100% { transform: translate(0, 0) scale(1) rotate(0deg); }
            25% { transform: translate(30px, -40px) scale(1.1) rotate(5deg); }
            50% { transform: translate(-30px, 30px) scale(0.9) rotate(-5deg); }
            75% { transform: translate(40px, -20px) scale(1.05) rotate(3deg); }
        }

        /* Particles */
        .particles {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 8px;
            height: 8px;
            background: rgba(255, 255, 255, 0.25);
            border-radius: 50%;
            animation: floatParticle 20s infinite linear;
        }

        .particle:nth-child(1) { left: 5%; animation-delay: 0s; animation-duration: 14s; width: 6px; height: 6px; }
        .particle:nth-child(2) { left: 15%; animation-delay: -2s; animation-duration: 18s; width: 10px; height: 10px; }
        .particle:nth-child(3) { left: 25%; animation-delay: -4s; animation-duration: 12s; width: 5px; height: 5px; }
        .particle:nth-child(4) { left: 35%; animation-delay: -6s; animation-duration: 22s; width: 12px; height: 12px; }
        .particle:nth-child(5) { left: 45%; animation-delay: -8s; animation-duration: 16s; width: 7px; height: 7px; }
        .particle:nth-child(6) { left: 55%; animation-delay: -10s; animation-duration: 20s; width: 9px; height: 9px; }
        .particle:nth-child(7) { left: 65%; animation-delay: -12s; animation-duration: 15s; width: 6px; height: 6px; }
        .particle:nth-child(8) { left: 75%; animation-delay: -14s; animation-duration: 19s; width: 11px; height: 11px; }
        .particle:nth-child(9) { left: 85%; animation-delay: -16s; animation-duration: 13s; width: 5px; height: 5px; }
        .particle:nth-child(10) { left: 95%; animation-delay: -18s; animation-duration: 21s; width: 8px; height: 8px; }
        .particle:nth-child(11) { left: 10%; animation-delay: -3s; animation-duration: 17s; width: 7px; height: 7px; }
        .particle:nth-child(12) { left: 50%; animation-delay: -7s; animation-duration: 23s; width: 10px; height: 10px; }

        @keyframes floatParticle {
            0% { transform: translateY(100vh) rotate(0deg) scale(0); opacity: 0; }
            10% { opacity: 1; transform: scale(1); }
            90% { opacity: 1; }
            100% { transform: translateY(-100vh) rotate(720deg) scale(0); opacity: 0; }
        }

        /* Brand Content */
        .brand-content {
            position: relative;
            z-index: 2;
            color: white;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 40px;
            animation: fadeInUp 0.8s ease;
        }

        .logo-icon {
            font-size: 44px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 12px;
            border-radius: 16px;
            display: inline-block;
            animation: pulseLogo 2s ease-in-out infinite;
        }

        @keyframes pulseLogo {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.05) rotate(-3deg); }
        }

        .logo-text {
            font-size: 34px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .logo-text span {
            font-weight: 300;
        }

        .main-heading {
            font-size: 52px;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 16px;
            animation: fadeInUp 1s ease;
        }

        .main-heading span {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sub-heading {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 30px;
            font-weight: 300;
            animation: fadeInUp 1.2s ease;
            line-height: 1.6;
        }

        .quote {
            font-size: 20px;
            font-style: italic;
            padding: 20px 30px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border-left: 4px solid #FFD700;
            animation: fadeInUp 1.4s ease;
            max-width: 80%;
        }

        .quote-author {
            font-style: normal;
            font-weight: 600;
            margin-top: 8px;
            font-size: 14px;
            opacity: 0.8;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Floating Icons */
        .floating-icons {
            display: flex;
            gap: 16px;
            margin-top: 30px;
            animation: fadeInUp 1.6s ease;
        }

        .floating-icon {
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            transition: all 0.3s ease;
            animation: floatIcon 3s ease-in-out infinite;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .floating-icon:nth-child(1) { animation-delay: 0s; }
        .floating-icon:nth-child(2) { animation-delay: -0.6s; }
        .floating-icon:nth-child(3) { animation-delay: -1.2s; }
        .floating-icon:nth-child(4) { animation-delay: -1.8s; }

        .floating-icon:hover {
            transform: translateY(-12px) scale(1.1);
            background: rgba(255, 255, 255, 0.2);
        }

        @keyframes floatIcon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }

        /* =============================================
           RIGHT SIDE - LOGIN FORM
        ============================================= */
        .right-side {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: #FFFDF8;
            position: relative;
        }

        .form-container {
            width: 100%;
            max-width: 400px;
            animation: slideUp 0.8s ease;
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

        /* Glassmorphism Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(108, 99, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.5);
            transition: all 0.4s ease;
        }

        .glass-card:hover {
            box-shadow: 0 12px 48px rgba(108, 99, 255, 0.15);
            transform: translateY(-3px);
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header h1 {
            font-size: 30px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 6px;
        }

        .form-header h1 span {
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-header p {
            color: #6B7280;
            font-size: 14px;
        }

        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
            border-left: 4px solid #EF4444;
        }

        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border-left: 4px solid #22C55E;
        }

        .alert-icon {
            font-size: 20px;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 18px;
            transition: color 0.3s ease;
        }

        .input-wrapper input {
            width: 100%;
            padding: 13px 14px 13px 46px;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            font-size: 14px;
            color: #1F2937;
            background: #FFFFFF;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .input-wrapper input:focus {
            outline: none;
            border-color: #6C63FF;
            box-shadow: 0 0 0 4px rgba(108, 99, 255, 0.08);
        }

        .input-wrapper input:focus + .input-icon {
            color: #6C63FF;
        }

        .input-wrapper input.error {
            border-color: #EF4444;
        }

        .input-wrapper input.success {
            border-color: #22C55E;
        }

        .input-wrapper .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #9CA3AF;
            font-size: 18px;
            transition: color 0.3s ease;
            background: none;
            border: none;
            padding: 0;
        }

        .input-wrapper .toggle-password:hover {
            color: #6C63FF;
        }

        /* Validation Messages */
        .validation-message {
            font-size: 12px;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .validation-message.error {
            color: #EF4444;
        }

        .validation-message.success {
            color: #22C55E;
        }

        .validation-message.hidden {
            display: none;
        }

        /* Remember Me & Forgot Password */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 13px;
            color: #6B7280;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #6C63FF;
            cursor: pointer;
        }

        .forgot-link {
            font-size: 13px;
            color: #6C63FF;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .forgot-link:hover {
            color: #8B5CF6;
            text-decoration: underline;
        }

        /* Login Button */
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(108, 99, 255, 0.3);
        }

        .btn-login:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Button Shine Effect */
        .btn-login::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 30%,
                rgba(255, 255, 255, 0.15) 50%,
                transparent 70%
            );
            transform: rotate(45deg) translateX(-100%);
            transition: transform 0.6s ease;
        }

        .btn-login:hover:not(:disabled)::before {
            transform: rotate(45deg) translateX(100%);
        }

        /* Footer */
        .form-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #6B7280;
        }

        .form-footer a {
            color: #6C63FF;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .form-footer a:hover {
            color: #8B5CF6;
            text-decoration: underline;
        }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
            color: #9CA3AF;
            font-size: 12px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #E5E7EB;
        }

        .divider::before {
            margin-right: 16px;
        }

        .divider::after {
            margin-left: 16px;
        }

        /* =============================================
           RESPONSIVE DESIGN
        ============================================= */
        @media (max-width: 1024px) {
            .container {
                max-height: none;
                border-radius: 0;
            }
            
            .left-side {
                padding: 40px 30px;
            }
            
            .main-heading {
                font-size: 38px;
            }
            
            .quote {
                max-width: 90%;
                font-size: 17px;
                padding: 16px 20px;
            }
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                height: auto;
                max-height: none;
                border-radius: 0;
            }

            .left-side {
                padding: 40px 30px;
                min-height: 280px;
            }

            .right-side {
                padding: 30px 20px;
                min-height: auto;
            }

            .glass-card {
                padding: 30px 20px;
            }

            .main-heading {
                font-size: 30px;
            }

            .logo-text {
                font-size: 26px;
            }

            .logo-icon {
                font-size: 34px;
                padding: 10px;
            }

            .floating-icons {
                display: none;
            }

            .blob-1,
            .blob-2,
            .blob-3 {
                display: none;
            }

            .form-header h1 {
                font-size: 24px;
            }

            .quote {
                max-width: 100%;
                font-size: 15px;
                padding: 14px 16px;
            }
        }

        @media (max-width: 480px) {
            .left-side {
                padding: 25px 20px;
                min-height: 200px;
            }

            .right-side {
                padding: 20px 15px;
            }

            .glass-card {
                padding: 20px 15px;
                border-radius: 16px;
            }

            .main-heading {
                font-size: 24px;
            }

            .sub-heading {
                font-size: 14px;
            }

            .logo-text {
                font-size: 20px;
            }

            .logo-icon {
                font-size: 28px;
                padding: 8px;
            }

            .form-header h1 {
                font-size: 20px;
            }

            .quote {
                font-size: 13px;
                padding: 12px 14px;
            }

            .form-options {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .input-wrapper input {
                padding: 11px 12px 11px 40px;
                font-size: 13px;
            }

            .btn-login {
                padding: 12px;
                font-size: 14px;
            }
        }

        @media (max-width: 360px) {
            .left-side {
                min-height: 160px;
                padding: 20px 15px;
            }

            .main-heading {
                font-size: 20px;
            }

            .logo {
                margin-bottom: 20px;
            }

            .logo-text {
                font-size: 18px;
            }

            .logo-icon {
                font-size: 24px;
                padding: 6px;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- =============================================
        LEFT SIDE - BRANDING
        ============================================= -->
        <div class="left-side">
            <!-- Floating Blobs -->
            <div class="blob blob-1"></div>
            <div class="blob blob-2"></div>
            <div class="blob blob-3"></div>

            <!-- Particles -->
            <div class="particles">
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
            </div>

            <!-- Brand Content -->
            <div class="brand-content">
                <div class="logo">
                    <span class="logo-icon">🎓</span>
                    <span class="logo-text">EduHack <span>AI</span></span>
                </div>

                <div class="main-heading">
                    Welcome<br>
                    <span>Back!</span>
                </div>

                <div class="sub-heading">
                    Continue your learning journey with<br>
                    AI-powered education
                </div>

                <div class="quote">
                    "Education is the most powerful weapon<br>
                    which you can use to change the world."
                    <div class="quote-author">— Nelson Mandela</div>
                </div>

                <div class="floating-icons">
                    <div class="floating-icon">📚</div>
                    <div class="floating-icon">🧠</div>
                    <div class="floating-icon">🏆</div>
                    <div class="floating-icon">🎓</div>
                </div>
            </div>
        </div>

        <!-- =============================================
        RIGHT SIDE - LOGIN FORM
        ============================================= -->
        <div class="right-side">
            <div class="form-container">
                <div class="glass-card">
                    <div class="form-header">
                        <h1>Welcome <span>Back</span></h1>
                        <p>Login to continue your learning journey</p>
                    </div>

                    <!-- Error Alert -->
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <span class="alert-icon">⚠️</span>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Login Form -->
                    <form id="loginForm" method="POST" action="" novalidate>
                        <!-- Email -->
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <div class="input-wrapper">
                                <span class="input-icon">📧</span>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    placeholder="Enter your email"
                                    value="<?php echo htmlspecialchars($email); ?>"
                                    required
                                    autocomplete="email"
                                    autofocus
                                >
                            </div>
                            <div id="emailError" class="validation-message hidden error">❌ Please enter a valid email</div>
                        </div>

                        <!-- Password -->
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-wrapper">
                                <span class="input-icon">🔒</span>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    placeholder="Enter your password"
                                    required
                                    autocomplete="current-password"
                                >
                                <button type="button" class="toggle-password" id="togglePassword">👁️</button>
                            </div>
                            <div id="passwordError" class="validation-message hidden error">❌ Please enter your password</div>
                        </div>

                        <!-- Form Options -->
                        <div class="form-options">
                            <label class="checkbox-wrapper">
                                <input type="checkbox" name="remember" id="remember" 
                                       <?php echo $remember ? 'checked' : ''; ?>>
                                <span>Remember me</span>
                            </label>
                            <a href="#" class="forgot-link">Forgot password?</a>
                        </div>

                        <!-- Login Button -->
                        <button type="submit" class="btn-login" id="submitBtn">
                            Login 🚀
                        </button>
                    </form>

                    <!-- Divider -->
                    <div class="divider">or</div>

                    <!-- Footer -->
                    <div class="form-footer">
                        Don't have an account? <a href="register.php">Create one now</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================================
    JAVASCRIPT
    ============================================= -->
    <script>
        // =============================================
        // DOM Elements
        // =============================================
        const form = document.getElementById('loginForm');
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        const submitBtn = document.getElementById('submitBtn');
        const emailError = document.getElementById('emailError');
        const passwordError = document.getElementById('passwordError');

        // =============================================
        // Toggle Password Visibility
        // =============================================
        document.getElementById('togglePassword').addEventListener('click', function() {
            if (password.type === 'password') {
                password.type = 'text';
                this.textContent = '🙈';
            } else {
                password.type = 'password';
                this.textContent = '👁️';
            }
        });

        // =============================================
        // Real-time Validation
        // =============================================

        // Email validation
        email.addEventListener('input', function() {
            validateEmail(this.value);
            updateSubmitButton();
        });

        email.addEventListener('blur', function() {
            validateEmail(this.value);
            updateSubmitButton();
        });

        function validateEmail(value) {
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(value) && value.length > 0) {
                emailError.classList.remove('hidden');
                emailError.classList.add('error');
                email.classList.add('error');
                email.classList.remove('success');
                return false;
            } else if (value.length === 0) {
                emailError.classList.remove('hidden');
                emailError.classList.add('error');
                email.classList.add('error');
                email.classList.remove('success');
                return false;
            } else {
                emailError.classList.add('hidden');
                email.classList.remove('error');
                email.classList.add('success');
                return true;
            }
        }

        // Password validation
        password.addEventListener('input', function() {
            validatePassword(this.value);
            updateSubmitButton();
        });

        password.addEventListener('blur', function() {
            validatePassword(this.value);
            updateSubmitButton();
        });

        function validatePassword(value) {
            if (value.length === 0) {
                passwordError.classList.remove('hidden');
                passwordError.classList.add('error');
                password.classList.add('error');
                password.classList.remove('success');
                return false;
            } else {
                passwordError.classList.add('hidden');
                password.classList.remove('error');
                password.classList.add('success');
                return true;
            }
        }

        // =============================================
        // Update Submit Button
        // =============================================
        function updateSubmitButton() {
            const isEmailValid = validateEmail(email.value);
            const isPasswordValid = validatePassword(password.value);

            if (isEmailValid && isPasswordValid && email.value.length > 0 && password.value.length > 0) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }

        // =============================================
        // Form Submit Validation
        // =============================================
        form.addEventListener('submit', function(e) {
            // Run all validations
            const isEmailValid = validateEmail(email.value);
            const isPasswordValid = validatePassword(password.value);

            if (!isEmailValid || !isPasswordValid) {
                e.preventDefault();
                // Focus on first error
                if (!isEmailValid) {
                    email.focus();
                } else if (!isPasswordValid) {
                    password.focus();
                }
            }
        });

        // =============================================
        // Enter Key Support (already handled by form)
        // =============================================

        // =============================================
        // Auto-dismiss alerts after 5 seconds
        // =============================================
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);

        // =============================================
        // Initial Validation State
        // =============================================
        // Set initial state - if fields have values, validate them
        if (email.value.length > 0) {
            validateEmail(email.value);
        }
        if (password.value.length > 0) {
            validatePassword(password.value);
        }
        updateSubmitButton();

        // =============================================
        // Console Log for Development
        // =============================================
        console.log('🎓 EduHack AI - Login Page Loaded');
        console.log('📧 Email field: ' + (email.value || 'empty'));
        console.log('🔒 Password field: ' + (password.value ? 'filled' : 'empty'));
    </script>

</body>
</html>