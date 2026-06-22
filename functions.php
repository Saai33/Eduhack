<?php
/**
 * =============================================
 * Functions File - EduHack AI
 * =============================================
 * 
 * This file contains reusable helper functions
 * used across the entire EduHack AI platform.
 * 
 * @package EduHack AI
 * @version 1.0
 * @author EduHack Team
 */

// =============================================
// 1. SANITIZATION & SECURITY
// =============================================

/**
 * Sanitize user input to prevent XSS and SQL injection
 * 
 * @param mixed $data The input data to sanitize
 * @return mixed Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Escape string for safe SQL usage (fallback if not using prepared statements)
 * 
 * @param string $data The string to escape
 * @return string Escaped string
 */
function escapeString($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

/**
 * Validate email address
 * 
 * @param string $email The email to validate
 * @return bool True if valid, false otherwise
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate CSRF token
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
 * @param string $token The token to verify
 * @return bool True if valid, false otherwise
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate a random password
 * 
 * @param int $length Length of the password
 * @return string Random password
 */
function generateRandomPassword($length = 10) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

// =============================================
// 2. REDIRECTION & ALERTS
// =============================================

/**
 * Redirect user to a specific URL
 * 
 * @param string $url The URL to redirect to
 * @param string|null $message Optional flash message
 * @param string $type Message type (success, error, warning, info)
 * @return void
 */
function redirect($url, $message = null, $type = 'info') {
    if ($message) {
        setFlashMessage($message, $type);
    }
    header("Location: $url");
    exit();
}

/**
 * Set a flash message in session
 * 
 * @param string $message The message to display
 * @param string $type Message type (success, error, warning, info)
 * @return void
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Display flash message and clear it from session
 * 
 * @return string HTML formatted alert message
 */
function showAlert() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        $alertClass = [
            'success' => 'alert-success',
            'error' => 'alert-error',
            'warning' => 'alert-warning',
            'info' => 'alert-info'
        ][$type] ?? 'alert-info';
        
        $icon = [
            'success' => '✅',
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️'
        ][$type] ?? 'ℹ️';
        
        return "<div class='alert $alertClass alert-dismissible fade show' role='alert'>
                    <span class='alert-icon'>$icon</span>
                    $message
                    <button type='button' class='btn-close' data-bs-dismiss='alert' onclick=\"this.parentElement.style.display='none';\">&times;</button>
                </div>";
    }
    return '';
}

/**
 * Display alert with specific message and type
 * 
 * @param string $message The message to display
 * @param string $type Message type (success, error, warning, info)
 * @return string HTML formatted alert
 */
function showAlertMessage($message, $type = 'info') {
    $alertClass = [
        'success' => 'alert-success',
        'error' => 'alert-error',
        'warning' => 'alert-warning',
        'info' => 'alert-info'
    ][$type] ?? 'alert-info';
    
    $icon = [
        'success' => '✅',
        'error' => '❌',
        'warning' => '⚠️',
        'info' => 'ℹ️'
    ][$type] ?? 'ℹ️';
    
    return "<div class='alert $alertClass' role='alert'>
                <span class='alert-icon'>$icon</span>
                $message
            </div>";
}

// =============================================
// 3. USER FUNCTIONS
// =============================================

/**
 * Get user details by ID
 * 
 * @param int $id User ID
 * @return array|null User data or null if not found
 */
function getUserById($id) {
    global $conn;
    $id = (int)$id;
    
    $sql = "SELECT id, full_name, email, role, is_active, profile_image, created_at, last_login 
            FROM users 
            WHERE id = $id";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

/**
 * Get user role by ID
 * 
 * @param int $id User ID
 * @return string|null User role or null if not found
 */
function getUserRole($id) {
    global $conn;
    $id = (int)$id;
    
    $sql = "SELECT role FROM users WHERE id = $id";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        return $user['role'];
    }
    return null;
}

/**
 * Get user by email
 * 
 * @param string $email User email
 * @return array|null User data or null if not found
 */
function getUserByEmail($email) {
    global $conn;
    $email = mysqli_real_escape_string($conn, trim($email));
    
    $sql = "SELECT id, full_name, email, role, is_active, password FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

/**
 * Check if email already exists
 * 
 * @param string $email Email to check
 * @param int $exclude_id User ID to exclude (for edit)
 * @return bool True if exists, false otherwise
 */
function emailExists($email, $exclude_id = null) {
    global $conn;
    $email = mysqli_real_escape_string($conn, trim($email));
    
    $sql = "SELECT id FROM users WHERE email = '$email'";
    if ($exclude_id) {
        $exclude_id = (int)$exclude_id;
        $sql .= " AND id != $exclude_id";
    }
    $result = mysqli_query($conn, $sql);
    return mysqli_num_rows($result) > 0;
}

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current logged-in user ID
 * 
 * @return int|null User ID or null if not logged in
 */
function getCurrentUserId() {
    return isLoggedIn() ? $_SESSION['user_id'] : null;
}

/**
 * Get current logged-in user role
 * 
 * @return string|null User role or null if not logged in
 */
function getCurrentUserRole() {
    return isLoggedIn() ? $_SESSION['role'] : null;
}

/**
 * Get current logged-in user full name
 * 
 * @return string|null User name or null if not logged in
 */
function getCurrentUserName() {
    return isLoggedIn() ? $_SESSION['full_name'] : null;
}

/**
 * Logout user - destroy session
 * 
 * @return void
 */
function logoutUser() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

// =============================================
// 4. COUNT FUNCTIONS
// =============================================

/**
 * Count total users
 * 
 * @return int Total users count
 */
function countUsers() {
    global $conn;
    $sql = "SELECT COUNT(*) as total FROM users";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['total'] ?? 0;
}

/**
 * Count students
 * 
 * @return int Students count
 */
function countStudents() {
    global $conn;
    $sql = "SELECT COUNT(*) as total FROM users WHERE role = 'student'";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['total'] ?? 0;
}

/**
 * Count teachers
 * 
 * @return int Teachers count
 */
function countTeachers() {
    global $conn;
    $sql = "SELECT COUNT(*) as total FROM users WHERE role = 'teacher'";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['total'] ?? 0;
}

/**
 * Count admins
 * 
 * @return int Admins count
 */
function countAdmins() {
    global $conn;
    $sql = "SELECT COUNT(*) as total FROM users WHERE role = 'admin'";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['total'] ?? 0;
}

/**
 * Count active users (last 7 days)
 * 
 * @return int Active users count
 */
function countActiveUsers() {
    global $conn;
    $sql = "SELECT COUNT(*) as total FROM users 
            WHERE is_active = 1 AND last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['total'] ?? 0;
}

/**
 * Count total notes
 * 
 * @param bool $published Only count published notes
 * @return int Notes count
 */
function countNotes($published = true) {
    global $conn;
    $sql = "SELECT COUNT(*) as total FROM notes";
    if ($published) {
        $sql .= " WHERE is_published = 1";
    }
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['total'] ?? 0;
}

/**
 * Count total quizzes
 * 
 * @param bool $published Only count published quizzes
 * @return int Quizzes count
 */
function countQuizzes($published = true) {
    global $conn;
    $sql = "SELECT COUNT(*) as total FROM quizzes";
    if ($published) {
        $sql .= " WHERE is_published = 1";
    }
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['total'] ?? 0;
}

/**
 * Count total forum posts
 * 
 * @return int Forum posts count
 */
function countForumPosts() {
    global $conn;
    $sql = "SELECT COUNT(*) as total FROM forum_posts";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['total'] ?? 0;
}

/**
 * Count total quiz attempts
 * 
 * @param bool $completed Only count completed attempts
 * @return int Quiz attempts count
 */
function countQuizAttempts($completed = true) {
    global $conn;
    $sql = "SELECT COUNT(*) as total FROM quiz_attempts";
    if ($completed) {
        $sql .= " WHERE is_completed = 1";
    }
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['total'] ?? 0;
}

/**
 * Count total forum replies
 * 
 * @return int Forum replies count
 */
function countForumReplies() {
    global $conn;
    $sql = "SELECT COUNT(*) as total FROM forum_replies";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['total'] ?? 0;
}

// =============================================
// 5. TEACHER FUNCTIONS
// =============================================

/**
 * Get notes uploaded by a specific teacher
 * 
 * @param int $teacher_id Teacher ID
 * @param int $limit Number of notes to return (0 for all)
 * @return array Notes data
 */
function getTeacherNotes($teacher_id, $limit = 0) {
    global $conn;
    $teacher_id = (int)$teacher_id;
    $sql = "SELECT * FROM notes WHERE teacher_id = $teacher_id ORDER BY created_at DESC";
    if ($limit > 0) {
        $sql .= " LIMIT $limit";
    }
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

/**
 * Get quizzes created by a specific teacher
 * 
 * @param int $teacher_id Teacher ID
 * @param int $limit Number of quizzes to return (0 for all)
 * @return array Quizzes data
 */
function getTeacherQuizzes($teacher_id, $limit = 0) {
    global $conn;
    $teacher_id = (int)$teacher_id;
    $sql = "SELECT * FROM quizzes WHERE teacher_id = $teacher_id ORDER BY created_at DESC";
    if ($limit > 0) {
        $sql .= " LIMIT $limit";
    }
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

/**
 * Get teacher's total notes count
 * 
 * @param int $teacher_id Teacher ID
 * @return int Notes count
 */
function getTeacherNotesCount($teacher_id) {
    global $conn;
    $teacher_id = (int)$teacher_id;
    $sql = "SELECT COUNT(*) as total FROM notes WHERE teacher_id = $teacher_id";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['total'] ?? 0;
}

/**
 * Get teacher's total quizzes count
 * 
 * @param int $teacher_id Teacher ID
 * @return int Quizzes count
 */
function getTeacherQuizzesCount($teacher_id) {
    global $conn;
    $teacher_id = (int)$teacher_id;
    $sql = "SELECT COUNT(*) as total FROM quizzes WHERE teacher_id = $teacher_id";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['total'] ?? 0;
}

// =============================================
// 6. STUDENT FUNCTIONS
// =============================================

/**
 * Get student's quiz attempts
 * 
 * @param int $student_id Student ID
 * @param int $limit Number of attempts to return (0 for all)
 * @return array Quiz attempts data
 */
function getStudentQuizAttempts($student_id, $limit = 0) {
    global $conn;
    $student_id = (int)$student_id;
    $sql = "SELECT qa.*, q.title as quiz_title, q.subject 
            FROM quiz_attempts qa
            JOIN quizzes q ON qa.quiz_id = q.id
            WHERE qa.student_id = $student_id AND qa.is_completed = 1
            ORDER BY qa.submitted_at DESC";
    if ($limit > 0) {
        $sql .= " LIMIT $limit";
    }
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

/**
 * Get student's average score across all quizzes
 * 
 * @param int $student_id Student ID
 * @return float Average score
 */
function getStudentAverageScore($student_id) {
    global $conn;
    $student_id = (int)$student_id;
    $sql = "SELECT AVG(percentage) as avg_score FROM quiz_attempts 
            WHERE student_id = $student_id AND is_completed = 1";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return round($data['avg_score'] ?? 0, 1);
}

/**
 * Get student's best score
 * 
 * @param int $student_id Student ID
 * @return float Best score
 */
function getStudentBestScore($student_id) {
    global $conn;
    $student_id = (int)$student_id;
    $sql = "SELECT MAX(percentage) as best_score FROM quiz_attempts 
            WHERE student_id = $student_id AND is_completed = 1";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return round($data['best_score'] ?? 0, 1);
}

/**
 * Get student's total quiz count
 * 
 * @param int $student_id Student ID
 * @return int Quiz count
 */
function getStudentQuizCount($student_id) {
    global $conn;
    $student_id = (int)$student_id;
    $sql = "SELECT COUNT(*) as total FROM quiz_attempts 
            WHERE student_id = $student_id AND is_completed = 1";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['total'] ?? 0;
}

/**
 * Get student's notes viewed count
 * 
 * @param int $student_id Student ID
 * @return int Notes viewed count
 */
function getStudentNotesViewed($student_id) {
    global $conn;
    $student_id = (int)$student_id;
    $sql = "SELECT COUNT(DISTINCT note_id) as total FROM student_progress 
            WHERE student_id = $student_id AND action_type = 'viewed_note'";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['total'] ?? 0;
}

/**
 * Calculate student's learning progress percentage
 * 
 * @param int $student_id Student ID
 * @return int Progress percentage
 */
function calculateProgress($student_id) {
    $notes_viewed = getStudentNotesViewed($student_id);
    $quizzes_completed = getStudentQuizCount($student_id);
    $avg_score = getStudentAverageScore($student_id);
    
    $score = ($notes_viewed * 2) + ($quizzes_completed * 5) + ($avg_score / 10);
    $max_score = 100;
    return min(round($score), 100);
}

// =============================================
// 7. LEADERBOARD FUNCTIONS
// =============================================

/**
 * Get leaderboard ranking
 * 
 * @param string $time_period 'all', 'month', 'week'
 * @param int $limit Number of users to return
 * @return array Leaderboard data
 */
function getLeaderboard($time_period = 'all', $limit = 10) {
    global $conn;
    
    $date_filter = '';
    if ($time_period == 'month') {
        $date_filter = "AND qa.submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    } elseif ($time_period == 'week') {
        $date_filter = "AND qa.submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    }
    
    $sql = "SELECT 
                u.id,
                u.full_name,
                u.profile_image,
                COALESCE(SUM(qa.score), 0) as total_score,
                COUNT(qa.id) as quiz_count,
                COALESCE(AVG(qa.percentage), 0) as avg_score,
                COALESCE(MAX(qa.percentage), 0) as best_score
            FROM users u
            LEFT JOIN quiz_attempts qa ON u.id = qa.student_id AND qa.is_completed = 1 $date_filter
            WHERE u.role = 'student'
            GROUP BY u.id
            HAVING quiz_count > 0 OR total_score > 0
            ORDER BY total_score DESC
            LIMIT $limit";
    
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

/**
 * Get student's rank on leaderboard
 * 
 * @param int $student_id Student ID
 * @param string $time_period 'all', 'month', 'week'
 * @return int Rank position
 */
function getStudentRank($student_id, $time_period = 'all') {
    $leaderboard = getLeaderboard($time_period, 1000);
    foreach ($leaderboard as $index => $user) {
        if ($user['id'] == $student_id) {
            return $index + 1;
        }
    }
    return 0;
}

// =============================================
// 8. TOP PERFORMERS
// =============================================

/**
 * Get top performing students
 * 
 * @param int $limit Number of students to return
 * @return array Top students data
 */
function getTopStudents($limit = 5) {
    global $conn;
    
    $sql = "SELECT u.id, u.full_name, 
                   COALESCE(AVG(qa.percentage), 0) as avg_score,
                   COUNT(qa.id) as quiz_count,
                   COALESCE(SUM(qa.score), 0) as total_score
            FROM users u
            LEFT JOIN quiz_attempts qa ON u.id = qa.student_id AND qa.is_completed = 1
            WHERE u.role = 'student'
            GROUP BY u.id
            HAVING quiz_count > 0
            ORDER BY avg_score DESC, total_score DESC
            LIMIT $limit";
    
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

/**
 * Get top contributing teachers
 * 
 * @param int $limit Number of teachers to return
 * @return array Top teachers data
 */
function getTopTeachers($limit = 5) {
    global $conn;
    
    $sql = "SELECT u.id, u.full_name,
                   COUNT(DISTINCT n.id) as notes_count,
                   COUNT(DISTINCT q.id) as quizzes_count,
                   (COUNT(DISTINCT n.id) + COUNT(DISTINCT q.id)) as total_contributions
            FROM users u
            LEFT JOIN notes n ON u.id = n.teacher_id
            LEFT JOIN quizzes q ON u.id = q.teacher_id
            WHERE u.role = 'teacher'
            GROUP BY u.id
            ORDER BY total_contributions DESC
            LIMIT $limit";
    
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// =============================================
// 9. RECENT ACTIVITIES
// =============================================

/**
 * Get recent platform activities
 * 
 * @param int $limit Number of activities to return
 * @return array Recent activities data
 */
function getRecentActivities($limit = 10) {
    global $conn;
    
    $sql = "SELECT 
                'user_registered' as type,
                full_name as user_name,
                created_at as activity_date
            FROM users
            UNION ALL
            SELECT 
                'quiz_attempted' as type,
                u.full_name as user_name,
                qa.submitted_at as activity_date
            FROM quiz_attempts qa
            JOIN users u ON qa.student_id = u.id
            WHERE qa.is_completed = 1
            UNION ALL
            SELECT 
                'note_uploaded' as type,
                u.full_name as user_name,
                n.created_at as activity_date
            FROM notes n
            JOIN users u ON n.teacher_id = u.id
            UNION ALL
            SELECT 
                'forum_post' as type,
                u.full_name as user_name,
                fp.created_at as activity_date
            FROM forum_posts fp
            JOIN users u ON fp.user_id = u.id
            ORDER BY activity_date DESC
            LIMIT $limit";
    
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// =============================================
// 10. FILE UPLOAD FUNCTIONS
// =============================================

/**
 * Upload a file with validation
 * 
 * @param array $file $_FILES array
 * @param string $target_dir Target directory
 * @param array $allowed_types Allowed file extensions
 * @param int $max_size Maximum file size in bytes
 * @return array Upload result with success, filename, path, error
 */
function uploadFile($file, $target_dir, $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx'], $max_size = 10485760) {
    $result = [
        'success' => false,
        'filename' => '',
        'path' => '',
        'error' => ''
    ];
    
    // Check if file was uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'No file uploaded or upload error occurred.';
        return $result;
    }
    
    // Get file info
    $filename = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // Check file size
    if ($file_size > $max_size) {
        $result['error'] = 'File size exceeds ' . formatFileSize($max_size) . ' limit.';
        return $result;
    }
    
    // Check file type
    if (!in_array($file_ext, $allowed_types)) {
        $result['error'] = 'Invalid file type. Allowed: ' . implode(', ', $allowed_types);
        return $result;
    }
    
    // Generate unique filename
    $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
    $upload_path = $target_dir . $new_filename;
    
    // Create directory if it doesn't exist
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // Move file
    if (move_uploaded_file($file_tmp, $upload_path)) {
        $result['success'] = true;
        $result['filename'] = $new_filename;
        $result['path'] = $upload_path;
        $result['size'] = $file_size;
    } else {
        $result['error'] = 'Failed to upload file. Please try again.';
    }
    
    return $result;
}

/**
 * Delete a file from the server
 * 
 * @param string $file_path Path to the file
 * @return bool True if deleted, false otherwise
 */
function deleteFile($file_path) {
    if (file_exists($file_path)) {
        return unlink($file_path);
    }
    return false;
}

/**
 * Get file extension
 * 
 * @param string $filename The filename
 * @return string File extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Format file size for display
 * 
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

// =============================================
// 11. DATE & TIME FUNCTIONS
// =============================================

/**
 * Format date for display
 * 
 * @param string $timestamp MySQL datetime
 * @param string $format Format string
 * @return string Formatted date
 */
function formatDate($timestamp, $format = 'd M Y') {
    if (empty($timestamp)) {
        return '-';
    }
    return date($format, strtotime($timestamp));
}

/**
 * Get time ago string
 * 
 * @param string $timestamp MySQL datetime
 * @return string Time ago
 */
function timeAgo($timestamp) {
    if (empty($timestamp)) {
        return 'Never';
    }
    
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M d, Y', $time);
    }
}

/**
 * Get current date in readable format
 * 
 * @return string Current date
 */
function getCurrentDate() {
    return date('l, F j, Y');
}

/**
 * Get current time in 12-hour format
 * 
 * @return string Current time
 */
function getCurrentTime() {
    return date('h:i A');
}

// =============================================
// 12. DISPLAY HELPERS
// =============================================

/**
 * Truncate text to a specific length
 * 
 * @param string $text The text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix to add
 * @return string Truncated text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Get user avatar with initials
 * 
 * @param string $name User's full name
 * @return string HTML for avatar
 */
function getUserAvatar($name) {
    $initials = strtoupper(substr($name, 0, 2));
    return "<div class='avatar-circle'>$initials</div>";
}

/**
 * Get role badge HTML
 * 
 * @param string $role User role
 * @return string HTML badge
 */
function getRoleBadge($role) {
    $colors = [
        'admin' => 'purple',
        'teacher' => 'green',
        'student' => 'blue'
    ];
    $color = $colors[$role] ?? 'gray';
    return "<span class='role-badge $color'>" . ucfirst($role) . "</span>";
}

/**
 * Get status badge HTML
 * 
 * @param bool $is_active Status
 * @return string HTML badge
 */
function getStatusBadge($is_active) {
    if ($is_active) {
        return "<span class='status-badge active'>Active</span>";
    }
    return "<span class='status-badge inactive'>Inactive</span>";
}

/**
 * Get difficulty badge HTML
 * 
 * @param string $difficulty Difficulty level
 * @return string HTML badge
 */
function getDifficultyBadge($difficulty) {
    $colors = [
        'Beginner' => '#22C55E',
        'Intermediate' => '#F59E0B',
        'Advanced' => '#EF4444'
    ];
    $color = $colors[$difficulty] ?? '#6B7280';
    return "<span class='difficulty-badge' style='background:{$color}20; color:$color; padding:2px 12px; border-radius:12px; font-size:12px; font-weight:600;'>$difficulty</span>";
}

// =============================================
// 13. NOTIFICATION FUNCTIONS
// =============================================

/**
 * Create a notification for a user
 * 
 * @param int $user_id User ID
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $type Notification type (info, success, warning, danger)
 * @param string $link Optional link
 * @return bool True if inserted, false otherwise
 */
function createNotification($user_id, $title, $message, $type = 'info', $link = null) {
    global $conn;
    $user_id = (int)$user_id;
    $title = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    $type = mysqli_real_escape_string($conn, $type);
    $link = $link ? mysqli_real_escape_string($conn, $link) : 'NULL';
    
    $sql = "INSERT INTO notifications (user_id, title, message, type, link) 
            VALUES ($user_id, '$title', '$message', '$type', $link)";
    return mysqli_query($conn, $sql);
}

/**
 * Get unread notification count for a user
 * 
 * @param int $user_id User ID
 * @return int Unread notification count
 */
function getUnreadNotificationCount($user_id) {
    global $conn;
    $user_id = (int)$user_id;
    $sql = "SELECT COUNT(*) as total FROM notifications 
            WHERE user_id = $user_id AND is_read = 0";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['total'] ?? 0;
}

// =============================================
// 14. LOGGING FUNCTIONS
// =============================================

/**
 * Log AI interaction
 * 
 * @param int $user_id User ID
 * @param int $note_id Note ID (optional)
 * @param string $action_type Action type
 * @param string $input_text Input text
 * @param string $output_text Output text
 * @return bool True if inserted, false otherwise
 */
function logAIInteraction($user_id, $note_id, $action_type, $input_text, $output_text = null) {
    global $conn;
    $user_id = (int)$user_id;
    $note_id = $note_id ? (int)$note_id : 'NULL';
    $action_type = mysqli_real_escape_string($conn, $action_type);
    $input_text = mysqli_real_escape_string($conn, $input_text);
    $output_text = $output_text ? mysqli_real_escape_string($conn, $output_text) : 'NULL';
    
    $sql = "INSERT INTO ai_interactions (user_id, note_id, action_type, input_text, output_text) 
            VALUES ($user_id, $note_id, '$action_type', '$input_text', $output_text)";
    return mysqli_query($conn, $sql);
}

// =============================================
// 15. SESSION & SECURITY HELPERS
// =============================================

/**
 * Check if session has expired (30 minutes timeout)
 * 
 * @param int $timeout Timeout in seconds (default: 1800 = 30 minutes)
 * @return bool True if session is valid, false if expired
 */
function checkSessionTimeout($timeout = 1800) {
    if (!isset($_SESSION['last_activity'])) {
        return true;
    }
    
    if (time() - $_SESSION['last_activity'] > $timeout) {
        logoutUser();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Generate a unique ID (for filename, token, etc.)
 * 
 * @param int $length Length of the ID
 * @return string Unique ID
 */
function generateUniqueId($length = 16) {
    return bin2hex(random_bytes($length));
}

// =============================================
// END OF FUNCTIONS FILE
// =============================================
?>