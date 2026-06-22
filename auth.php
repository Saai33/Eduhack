<?php
/**
 * =============================================
 * Authentication File - EduHack AI
 * =============================================
 * 
 * This file handles user authentication, session management,
 * and role-based access control for the entire application.
 * 
 * @package EduHack AI
 * @version 1.0
 * @author EduHack Team
 */

// =============================================
// 1. SESSION MANAGEMENT
// =============================================

/**
 * Start a new session or resume the existing session.
 * This must be called before any output is sent to the browser.
 * 
 * The session is used to maintain user login state across pages.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =============================================
// 2. INCLUDE DATABASE CONNECTION
// =============================================

/**
 * Include the database connection file to perform
 * database operations when needed.
 */
require_once 'db.php';

// =============================================
// 3. SESSION VARIABLE DEFINITIONS
// =============================================

/**
 * Define the expected session keys for better
 * code readability and maintainability.
 */
define('SESSION_USER_ID', 'user_id');
define('SESSION_FULL_NAME', 'full_name');
define('SESSION_EMAIL', 'email');
define('SESSION_ROLE', 'role');
define('SESSION_LAST_ACTIVITY', 'last_activity');

// =============================================
// 4. LOGIN FUNCTION
// =============================================

/**
 * Authenticate a user with email/username and password
 * 
 * @param string $email User's email or username
 * @param string $password User's plain text password
 * @return array ['success' => bool, 'message' => string, 'user' => array|null]
 */
function loginUser($email, $password) {
    global $conn;
    
    // Sanitize input
    $email = mysqli_real_escape_string($conn, trim($email));
    
    // Query to find user by email or username
    $sql = "SELECT id, full_name, email, password, role, is_active 
            FROM users 
            WHERE email = '$email' OR email = '$email' 
            LIMIT 1";
    
    $result = mysqli_query($conn, $sql);
    
    // Check if user exists
    if (mysqli_num_rows($result) === 0) {
        return [
            'success' => false,
            'message' => 'Invalid email or password.'
        ];
    }
    
    // Fetch user data
    $user = mysqli_fetch_assoc($result);
    
    // Verify password using password_verify() for secure password checking
    if (!password_verify($password, $user['password'])) {
        return [
            'success' => false,
            'message' => 'Invalid email or password.'
        ];
    }
    
    // Check if account is active
    if (isset($user['is_active']) && $user['is_active'] == 0) {
        return [
            'success' => false,
            'message' => 'Your account has been deactivated. Please contact admin.'
        ];
    }
    
    // Set session variables
    $_SESSION[SESSION_USER_ID] = $user['id'];
    $_SESSION[SESSION_FULL_NAME] = $user['full_name'];
    $_SESSION[SESSION_EMAIL] = $user['email'];
    $_SESSION[SESSION_ROLE] = $user['role'];
    $_SESSION[SESSION_LAST_ACTIVITY] = time();
    
    // Update last login timestamp in database
    $updateSql = "UPDATE users SET last_login = NOW() WHERE id = " . $user['id'];
    mysqli_query($conn, $updateSql);
    
    // Return success with user data
    return [
        'success' => true,
        'message' => 'Login successful!',
        'user' => [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ];
}

// =============================================
// 5. REGISTRATION FUNCTION
// =============================================

/**
 * Register a new user in the system
 * 
 * @param string $full_name User's full name
 * @param string $email User's email address
 * @param string $password User's plain text password
 * @param string $role User's role (student, teacher, admin)
 * @return array ['success' => bool, 'message' => string]
 */
function registerUser($full_name, $email, $password, $role = 'student') {
    global $conn;
    
    // Validate inputs
    $full_name = mysqli_real_escape_string($conn, trim($full_name));
    $email = mysqli_real_escape_string($conn, trim($email));
    $role = mysqli_real_escape_string($conn, trim($role));
    
    // Check if email already exists
    $checkSql = "SELECT id FROM users WHERE email = '$email'";
    $checkResult = mysqli_query($conn, $checkSql);
    
    if (mysqli_num_rows($checkResult) > 0) {
        return [
            'success' => false,
            'message' => 'Email address already registered. Please use a different email.'
        ];
    }
    
    // Validate password strength
    if (strlen($password) < 8) {
        return [
            'success' => false,
            'message' => 'Password must be at least 8 characters long.'
        ];
    }
    
    // Hash password for security
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $sql = "INSERT INTO users (full_name, email, password, role) 
            VALUES ('$full_name', '$email', '$hashedPassword', '$role')";
    
    if (mysqli_query($conn, $sql)) {
        $newUserId = mysqli_insert_id($conn);
        
        // If user is student, create a progress record
        if ($role === 'student') {
            $progressSql = "INSERT INTO user_progress (student_id, notes_viewed, quizzes_completed, total_score) 
                           VALUES ($newUserId, 0, 0, 0)";
            mysqli_query($conn, $progressSql);
        }
        
        return [
            'success' => true,
            'message' => 'Registration successful! You can now login.'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Registration failed. Please try again. Error: ' . mysqli_error($conn)
        ];
    }
}

// =============================================
// 6. LOGOUT FUNCTION
// =============================================

/**
 * Logout user by destroying session
 * 
 * @return array ['success' => bool, 'message' => string]
 */
function logoutUser() {
    // Unset all session variables
    $_SESSION = array();
    
    // Delete session cookie if it exists
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Finally destroy the session
    session_destroy();
    
    return [
        'success' => true,
        'message' => 'Logged out successfully.'
    ];
}

// =============================================
// 7. SESSION CHECK FUNCTIONS
// =============================================

/**
 * Check if user is currently logged in
 * 
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION[SESSION_USER_ID]) && !empty($_SESSION[SESSION_USER_ID]);
}

/**
 * Get current user's ID
 * 
 * @return int|null User ID or null if not logged in
 */
function getCurrentUserId() {
    return isLoggedIn() ? $_SESSION[SESSION_USER_ID] : null;
}

/**
 * Get current user's full name
 * 
 * @return string|null User's full name or null if not logged in
 */
function getCurrentUserFullName() {
    return isLoggedIn() ? $_SESSION[SESSION_FULL_NAME] : null;
}

/**
 * Get current user's email
 * 
 * @return string|null User's email or null if not logged in
 */
function getCurrentUserEmail() {
    return isLoggedIn() ? $_SESSION[SESSION_EMAIL] : null;
}

/**
 * Get current user's role
 * 
 * @return string|null User's role or null if not logged in
 */
function getCurrentUserRole() {
    return isLoggedIn() ? $_SESSION[SESSION_ROLE] : null;
}

/**
 * Get complete current user data from database
 * 
 * @return array|null User data array or null if not logged in
 */
function getCurrentUserData() {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $conn;
    $userId = getCurrentUserId();
    
    $sql = "SELECT id, full_name, email, role, profile_image, created_at 
            FROM users 
            WHERE id = $userId AND is_active = 1";
    
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

// =============================================
// 8. ROLE CHECK FUNCTIONS
// =============================================

/**
 * Check if current user is an admin
 * 
 * @return bool True if admin, false otherwise
 */
function isAdmin() {
    return isLoggedIn() && $_SESSION[SESSION_ROLE] === 'admin';
}

/**
 * Check if current user is a teacher
 * 
 * @return bool True if teacher, false otherwise
 */
function isTeacher() {
    return isLoggedIn() && $_SESSION[SESSION_ROLE] === 'teacher';
}

/**
 * Check if current user is a student
 * 
 * @return bool True if student, false otherwise
 */
function isStudent() {
    return isLoggedIn() && $_SESSION[SESSION_ROLE] === 'student';
}

/**
 * Check if current user has a specific role
 * 
 * @param string $role Role to check ('admin', 'teacher', 'student')
 * @return bool True if user has the role, false otherwise
 */
function hasRole($role) {
    return isLoggedIn() && $_SESSION[SESSION_ROLE] === $role;
}

/**
 * Check if current user has any of the specified roles
 * 
 * @param array $roles Array of roles to check
 * @return bool True if user has any of the roles
 */
function hasAnyRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    return in_array($_SESSION[SESSION_ROLE], $roles);
}

// =============================================
// 9. ACCESS CONTROL FUNCTIONS
// =============================================

/**
 * Require user to be logged in
 * If not, redirect to login page
 * 
 * @param string $redirectUrl Optional custom redirect URL
 * @return void
 */
function requireLogin($redirectUrl = 'login.php') {
    if (!isLoggedIn()) {
        // Store the requested URL to redirect back after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: $redirectUrl");
        exit();
    }
}

/**
 * Require user to be an admin
 * If not, redirect or show access denied
 * 
 * @param string $redirectUrl URL to redirect if not admin
 * @return void
 */
function requireAdmin($redirectUrl = 'login.php') {
    requireLogin($redirectUrl);
    
    if (!isAdmin()) {
        // Log the unauthorized access attempt
        error_log("Unauthorized admin access attempt by user ID: " . getCurrentUserId());
        
        // Redirect to appropriate dashboard based on role
        $role = getCurrentUserRole();
        if ($role) {
            header("Location: ../$role/dashboard.php");
        } else {
            header("Location: $redirectUrl");
        }
        exit();
    }
}

/**
 * Require user to be a teacher
 * If not, redirect or show access denied
 * 
 * @param string $redirectUrl URL to redirect if not teacher
 * @return void
 */
function requireTeacher($redirectUrl = 'login.php') {
    requireLogin($redirectUrl);
    
    if (!isTeacher() && !isAdmin()) {
        // Allow admins to access teacher pages too (optional)
        // Remove the admin check if you want only teachers
        
        // Log the unauthorized access attempt
        error_log("Unauthorized teacher access attempt by user ID: " . getCurrentUserId());
        
        // Redirect to appropriate dashboard
        $role = getCurrentUserRole();
        if ($role === 'student') {
            header("Location: ../student/dashboard.php");
        } elseif ($role === 'admin') {
            header("Location: ../admin/dashboard.php");
        } else {
            header("Location: $redirectUrl");
        }
        exit();
    }
}

/**
 * Require user to be a student
 * If not, redirect or show access denied
 * 
 * @param string $redirectUrl URL to redirect if not student
 * @return void
 */
function requireStudent($redirectUrl = 'login.php') {
    requireLogin($redirectUrl);
    
    if (!isStudent()) {
        // Log the unauthorized access attempt
        error_log("Unauthorized student access attempt by user ID: " . getCurrentUserId());
        
        // Redirect to appropriate dashboard
        $role = getCurrentUserRole();
        if ($role === 'teacher') {
            header("Location: ../teacher/dashboard.php");
        } elseif ($role === 'admin') {
            header("Location: ../admin/dashboard.php");
        } else {
            header("Location: $redirectUrl");
        }
        exit();
    }
}

/**
 * Require user to have one of the specified roles
 * 
 * @param array $roles Array of allowed roles
 * @param string $redirectUrl URL to redirect if not authorized
 * @return void
 */
function requireAnyRole($roles, $redirectUrl = 'login.php') {
    requireLogin($redirectUrl);
    
    if (!hasAnyRole($roles)) {
        // Log the unauthorized access attempt
        error_log("Unauthorized role access attempt. User ID: " . getCurrentUserId() . 
                  ", Required roles: " . implode(', ', $roles));
        
        // Redirect to appropriate dashboard based on current role
        $role = getCurrentUserRole();
        if ($role) {
            header("Location: ../$role/dashboard.php");
        } else {
            header("Location: $redirectUrl");
        }
        exit();
    }
}

// =============================================
// 10. REDIRECT AFTER LOGIN
// =============================================

/**
 * Redirect user to their dashboard based on role
 * 
 * @param string $currentPage Current page to avoid redirect loops
 * @return void
 */
function redirectToDashboard($currentPage = '') {
    if (!isLoggedIn()) {
        return;
    }
    
    // Check if there's a redirect URL stored in session
    if (isset($_SESSION['redirect_after_login'])) {
        $redirectUrl = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        header("Location: $redirectUrl");
        exit();
    }
    
    // Default dashboard redirection based on role
    $role = getCurrentUserRole();
    $dashboardMap = [
        'admin' => 'admin/dashboard.php',
        'teacher' => 'teacher/dashboard.php',
        'student' => 'student/dashboard.php'
    ];
    
    if (isset($dashboardMap[$role])) {
        $targetPage = $dashboardMap[$role];
        // Avoid redirect loops
        if ($currentPage !== $targetPage) {
            header("Location: $targetPage");
            exit();
        }
    }
}

// =============================================
// 11. SECURITY HELPER FUNCTIONS
// =============================================

/**
 * Generate CSRF token for forms
 * 
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * 
 * @param string $token Token to verify
 * @return bool True if valid, false otherwise
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if session has expired (30 minutes timeout)
 * 
 * @param int $timeout Timeout in seconds (default: 1800 = 30 minutes)
 * @return bool True if session is valid, false if expired
 */
function checkSessionTimeout($timeout = 1800) {
    if (!isset($_SESSION[SESSION_LAST_ACTIVITY])) {
        return true;
    }
    
    if (time() - $_SESSION[SESSION_LAST_ACTIVITY] > $timeout) {
        // Session expired
        logoutUser();
        return false;
    }
    
    // Update last activity time
    $_SESSION[SESSION_LAST_ACTIVITY] = time();
    return true;
}

// =============================================
// 12. ACCESS DENIED MESSAGE
// =============================================

/**
 * Display access denied message and stop execution
 * 
 * @param string $message Optional custom message
 * @return void
 */
function accessDenied($message = 'You do not have permission to access this page.') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Access Denied</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 400px; text-align: center; }
            .icon { font-size: 60px; color: #dc3545; margin-bottom: 20px; }
            h2 { color: #dc3545; margin-bottom: 10px; }
            p { color: #666; margin-bottom: 20px; }
            .btn { display: inline-block; background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
            .btn:hover { background: #0056b3; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">🚫</div>
            <h2>Access Denied</h2>
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="../index.php" class="btn">Go Home</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// =============================================
// 13. SESSION CHECK (OPTIONAL AUTO-RUN)
// =============================================

/**
 * Uncomment the line below to automatically check
 * session timeout on every page load.
 */
// if (isLoggedIn() && !checkSessionTimeout()) {
//     header('Location: login.php?msg=Session expired');
//     exit();
// }

// =============================================
// END OF AUTHENTICATION FILE
// =============================================
?>