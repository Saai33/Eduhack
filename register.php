<?php
/**
 * =============================================
 * Registration Page - EduHack AI
 * =============================================
 * 
 * This page allows new users to create an account
 * as either a Student or Teacher.
 */

// Start session and include database
session_start();
require_once 'includes/db.php';

$error = '';
$success = '';
$full_name = '';
$email = '';
$role = 'student';

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = mysqli_real_escape_string($conn, trim($_POST['role']));
    
    // Validation
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Check if email already exists
        $check_sql = "SELECT id FROM users WHERE email = '$email'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error = 'Email already registered. Please login or use a different email.';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $insert_sql = "INSERT INTO users (full_name, email, password, role) 
                           VALUES ('$full_name', '$email', '$hashed_password', '$role')";
            
            if (mysqli_query($conn, $insert_sql)) {
                $user_id = mysqli_insert_id($conn);
                
                // If student, create progress record
                if ($role === 'student') {
                    $progress_sql = "INSERT INTO user_progress (student_id, notes_viewed, quizzes_completed, total_score) 
                                     VALUES ($user_id, 0, 0, 0)";
                    mysqli_query($conn, $progress_sql);
                }
                
                $success = 'Registration successful! Redirecting to login...';
                
                // Clear form data
                $full_name = '';
                $email = '';
                $role = 'student';
                
                // Redirect after 2 seconds
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 2000);
                </script>";
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - EduHack AI</title>
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
            box-shadow: 0 20px 60px rgba(108, 99, 255, 0.15);
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
            animation: gradientShift 8s ease infinite;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Floating Blobs */
        .blob {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            animation: float 20s infinite ease-in-out;
        }

        .blob-1 {
            width: 300px;
            height: 300px;
            top: -100px;
            right: -100px;
            animation-delay: 0s;
        }

        .blob-2 {
            width: 200px;
            height: 200px;
            bottom: -50px;
            left: -50px;
            animation-delay: -5s;
        }

        .blob-3 {
            width: 150px;
            height: 150px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation-delay: -10s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(20px, -30px) scale(1.1); }
            50% { transform: translate(-20px, 20px) scale(0.9); }
            75% { transform: translate(30px, -10px) scale(1.05); }
        }

        /* Particles */
        .particles {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 6px;
            height: 6px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            animation: particleFloat 15s infinite linear;
        }

        .particle:nth-child(1) { left: 10%; animation-delay: 0s; animation-duration: 12s; }
        .particle:nth-child(2) { left: 20%; animation-delay: -2s; animation-duration: 15s; }
        .particle:nth-child(3) { left: 30%; animation-delay: -4s; animation-duration: 10s; }
        .particle:nth-child(4) { left: 40%; animation-delay: -6s; animation-duration: 18s; }
        .particle:nth-child(5) { left: 50%; animation-delay: -8s; animation-duration: 14s; }
        .particle:nth-child(6) { left: 60%; animation-delay: -10s; animation-duration: 16s; }
        .particle:nth-child(7) { left: 70%; animation-delay: -12s; animation-duration: 13s; }
        .particle:nth-child(8) { left: 80%; animation-delay: -14s; animation-duration: 17s; }
        .particle:nth-child(9) { left: 90%; animation-delay: -16s; animation-duration: 11s; }
        .particle:nth-child(10) { left: 95%; animation-delay: -18s; animation-duration: 19s; }

        @keyframes particleFloat {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100vh) rotate(720deg); opacity: 0; }
        }

        /* Branding Content */
        .brand-content {
            position: relative;
            z-index: 2;
            color: white;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 40px;
        }

        .logo-icon {
            font-size: 42px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 12px;
            border-radius: 16px;
            display: inline-block;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .logo-text {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .logo-text span {
            font-weight: 300;
        }

        .tagline {
            font-size: 48px;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 20px;
            animation: fadeInUp 1s ease;
        }

        .tagline span {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sub-tagline {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 40px;
            font-weight: 300;
            animation: fadeInUp 1.2s ease;
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
            gap: 20px;
            margin-top: 30px;
            animation: fadeInUp 1.4s ease;
        }

        .floating-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            transition: all 0.3s ease;
            animation: floatIcon 3s ease-in-out infinite;
        }

        .floating-icon:nth-child(1) { animation-delay: 0s; }
        .floating-icon:nth-child(2) { animation-delay: -0.5s; }
        .floating-icon:nth-child(3) { animation-delay: -1s; }
        .floating-icon:nth-child(4) { animation-delay: -1.5s; }

        .floating-icon:hover {
            transform: translateY(-10px) scale(1.1);
            background: rgba(255, 255, 255, 0.25);
        }

        @keyframes floatIcon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* =============================================
           RIGHT SIDE - FORM
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
            max-width: 420px;
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
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(108, 99, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            box-shadow: 0 12px 48px rgba(108, 99, 255, 0.18);
            transform: translateY(-2px);
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 8px;
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
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border-left: 4px solid #22C55E;
        }

        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
            border-left: 4px solid #EF4444;
        }

        .alert-icon {
            font-size: 20px;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 18px;
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

        .input-wrapper input,
        .input-wrapper select {
            width: 100%;
            padding: 12px 14px 12px 44px;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            font-size: 14px;
            color: #1F2937;
            background: #FFFFFF;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .input-wrapper select {
            appearance: none;
            cursor: pointer;
            padding-right: 40px;
        }

        .input-wrapper select option {
            padding: 10px;
        }

        .input-wrapper .select-arrow {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 16px;
            pointer-events: none;
        }

        .input-wrapper input:focus,
        .input-wrapper select:focus {
            outline: none;
            border-color: #6C63FF;
            box-shadow: 0 0 0 4px rgba(108, 99, 255, 0.1);
        }

        .input-wrapper input:focus + .input-icon,
        .input-wrapper input:focus ~ .input-icon {
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

        /* Role Selection */
        .role-selection {
            display: flex;
            gap: 12px;
            margin-top: 4px;
        }

        .role-option {
            flex: 1;
            position: relative;
        }

        .role-option input[type="radio"] {
            display: none;
        }

        .role-option label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 14px;
            color: #6B7280;
            background: #FFFFFF;
            margin-bottom: 0;
        }

        .role-option input[type="radio"]:checked + label {
            border-color: #6C63FF;
            background: rgba(108, 99, 255, 0.05);
            color: #6C63FF;
            box-shadow: 0 0 0 4px rgba(108, 99, 255, 0.08);
        }

        .role-option label:hover {
            border-color: #6C63FF;
            transform: translateY(-2px);
        }

        .role-option .role-icon {
            font-size: 20px;
        }

        /* Submit Button */
        .btn-submit {
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
            margin-top: 6px;
        }

        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(108, 99, 255, 0.3);
        }

        .btn-submit:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Button Shine Effect */
        .btn-submit::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 30%,
                rgba(255, 255, 255, 0.1) 50%,
                transparent 70%
            );
            transform: rotate(45deg) translateX(-100%);
            transition: transform 0.6s ease;
        }

        .btn-submit:hover:not(:disabled)::before {
            transform: rotate(45deg) translateX(100%);
        }

        /* Footer Links */
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
            
            .tagline {
                font-size: 36px;
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
                min-height: 300px;
            }

            .right-side {
                padding: 30px 20px;
                min-height: auto;
            }

            .glass-card {
                padding: 30px 20px;
            }

            .tagline {
                font-size: 28px;
            }

            .logo-text {
                font-size: 24px;
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
                font-size: 22px;
            }

            .role-selection {
                flex-direction: column;
            }

            .role-option label {
                padding: 12px;
            }
        }

        @media (max-width: 480px) {
            .left-side {
                padding: 30px 20px;
                min-height: 200px;
            }

            .right-side {
                padding: 20px 15px;
            }

            .glass-card {
                padding: 20px 15px;
            }

            .tagline {
                font-size: 22px;
            }

            .sub-tagline {
                font-size: 14px;
            }

            .logo-text {
                font-size: 20px;
            }

            .logo-icon {
                font-size: 30px;
                padding: 8px;
            }
        }

        /* Success animation */
        .success-checkmark {
            display: inline-block;
            animation: checkmark 0.8s ease;
        }

        @keyframes checkmark {
            0% { transform: scale(0); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); }
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
            </div>

            <!-- Brand Content -->
            <div class="brand-content">
                <div class="logo">
                    <span class="logo-icon">🎓</span>
                    <span class="logo-text">EduHack <span>AI</span></span>
                </div>

                <div class="tagline">
                    Learn Smarter,<br>
                    <span>Grow Faster</span>
                </div>

                <div class="sub-tagline">
                    Join thousands of students and teachers<br>
                    revolutionizing education with AI
                </div>

                <div class="floating-icons">
                    <div class="floating-icon">📚</div>
                    <div class="floating-icon">🎓</div>
                    <div class="floating-icon">🧠</div>
                    <div class="floating-icon">🏆</div>
                </div>
            </div>
        </div>

        <!-- =============================================
        RIGHT SIDE - FORM
        ============================================= -->
        <div class="right-side">
            <div class="form-container">
                <div class="glass-card">
                    <div class="form-header">
                        <h1>Create Account <span>✨</span></h1>
                        <p>Start your learning journey today</p>
                    </div>

                    <!-- Alert Messages -->
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <span class="alert-icon">⚠️</span>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <span class="alert-icon success-checkmark">✅</span>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Registration Form -->
                    <form id="registerForm" method="POST" action="" novalidate>
                        <!-- Full Name -->
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <div class="input-wrapper">
                                <span class="input-icon">👤</span>
                                <input 
                                    type="text" 
                                    id="full_name" 
                                    name="full_name" 
                                    placeholder="Enter your full name"
                                    value="<?php echo htmlspecialchars($full_name); ?>"
                                    required
                                    autocomplete="name"
                                >
                            </div>
                            <div id="nameError" class="validation-message hidden error">❌ Please enter your full name</div>
                        </div>

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
                                >
                            </div>
                            <div id="emailError" class="validation-message hidden error">❌ Please enter a valid email address</div>
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
                                    placeholder="Min 8 characters"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                >
                                <button type="button" class="toggle-password" id="togglePassword1">👁️</button>
                            </div>
                            <div id="passwordError" class="validation-message hidden error">❌ Password must be at least 8 characters</div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="input-wrapper">
                                <span class="input-icon">🔐</span>
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    placeholder="Confirm your password"
                                    required
                                    autocomplete="new-password"
                                >
                                <button type="button" class="toggle-password" id="togglePassword2">👁️</button>
                            </div>
                            <div id="confirmError" class="validation-message hidden error">❌ Passwords do not match</div>
                        </div>

                        <!-- Role Selection -->
                        <div class="form-group">
                            <label>I want to join as</label>
                            <div class="role-selection">
                                <div class="role-option">
                                    <input type="radio" id="student" name="role" value="student" 
                                           <?php echo ($role === 'student' || empty($role)) ? 'checked' : ''; ?>>
                                    <label for="student">
                                        <span class="role-icon">🧑‍🎓</span> Student
                                    </label>
                                </div>
                                <div class="role-option">
                                    <input type="radio" id="teacher" name="role" value="teacher"
                                           <?php echo ($role === 'teacher') ? 'checked' : ''; ?>>
                                    <label for="teacher">
                                        <span class="role-icon">👨‍🏫</span> Teacher
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn-submit" id="submitBtn">
                            Create Account 🚀
                        </button>
                    </form>

                    <!-- Footer -->
                    <div class="form-footer">
                        Already have an account? <a href="login.php">Login here</a>
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
        const form = document.getElementById('registerForm');
        const fullName = document.getElementById('full_name');
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submitBtn');

        const nameError = document.getElementById('nameError');
        const emailError = document.getElementById('emailError');
        const passwordError = document.getElementById('passwordError');
        const confirmError = document.getElementById('confirmError');

        // =============================================
        // Toggle Password Visibility
        // =============================================
        document.getElementById('togglePassword1').addEventListener('click', function() {
            togglePasswordVisibility(password, this);
        });

        document.getElementById('togglePassword2').addEventListener('click', function() {
            togglePasswordVisibility(confirmPassword, this);
        });

        function togglePasswordVisibility(input, toggleBtn) {
            if (input.type === 'password') {
                input.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                input.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }

        // =============================================
        // Real-time Validation
        // =============================================

        // Name validation
        fullName.addEventListener('input', function() {
            validateName(this.value);
            updateSubmitButton();
        });

        function validateName(value) {
            if (value.trim().length < 2) {
                nameError.classList.remove('hidden');
                nameError.classList.add('error');
                fullName.classList.add('error');
                fullName.classList.remove('success');
                return false;
            } else {
                nameError.classList.add('hidden');
                fullName.classList.remove('error');
                fullName.classList.add('success');
                return true;
            }
        }

        // Email validation
        email.addEventListener('input', function() {
            validateEmail(this.value);
            updateSubmitButton();
        });

        function validateEmail(value) {
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(value)) {
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
            validateConfirmPassword();
            updateSubmitButton();
        });

        function validatePassword(value) {
            if (value.length < 8) {
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

        // Confirm password validation
        confirmPassword.addEventListener('input', function() {
            validateConfirmPassword();
            updateSubmitButton();
        });

        function validateConfirmPassword() {
            if (password.value !== confirmPassword.value || confirmPassword.value === '') {
                confirmError.classList.remove('hidden');
                confirmError.classList.add('error');
                confirmPassword.classList.add('error');
                confirmPassword.classList.remove('success');
                return false;
            } else {
                confirmError.classList.add('hidden');
                confirmPassword.classList.remove('error');
                confirmPassword.classList.add('success');
                return true;
            }
        }

        // =============================================
        // Update Submit Button
        // =============================================
        function updateSubmitButton() {
            const isNameValid = validateName(fullName.value);
            const isEmailValid = validateEmail(email.value);
            const isPasswordValid = validatePassword(password.value);
            const isConfirmValid = validateConfirmPassword();

            if (isNameValid && isEmailValid && isPasswordValid && isConfirmValid) {
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
            const isNameValid = validateName(fullName.value);
            const isEmailValid = validateEmail(email.value);
            const isPasswordValid = validatePassword(password.value);
            const isConfirmValid = validateConfirmPassword();

            if (!isNameValid || !isEmailValid || !isPasswordValid || !isConfirmValid) {
                e.preventDefault();
                // Scroll to first error
                const firstError = document.querySelector('.input-wrapper input.error');
                if (firstError) {
                    firstError.focus();
                }
            }
        });

        // =============================================
        // Initial validation state
        // =============================================
        // Don't disable initially to allow empty form
        // But after user starts typing, validation kicks in

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

        console.log('🚀 EduHack AI - Registration Page Loaded');
    </script>

</body>
</html>