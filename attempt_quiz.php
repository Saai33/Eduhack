<?php
/**
 * =============================================
 * Attempt Quiz - EduHack AI Student Panel
 * =============================================
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Require authentication and student role
require_once '../includes/auth.php';
requireStudent();

// Get student info
$student_id = getCurrentUserId();
$student_name = getCurrentUserFullName();

// Include database connection
require_once '../includes/db.php';

// =============================================
// DEBUG - Check what we have
// =============================================
$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
echo "<!-- Debug: Quiz ID from URL = " . $quiz_id . " -->";

// =============================================
// If no quiz ID, show the lobby
// =============================================
if ($quiz_id <= 0) {
    // Fetch all published quizzes
    $published_quizzes_sql = "SELECT id, title, total_marks, time_limit, passing_score, created_at 
                              FROM quizzes 
                              WHERE is_published = 1 
                              ORDER BY created_at DESC";
    $quiz_list_result = mysqli_query($conn, $published_quizzes_sql);
    $available_quizzes = mysqli_fetch_all($quiz_list_result, MYSQLI_ASSOC);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Select Quiz - EduHack AI</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #F8FAFF; color: #1F2937; }
            .container { max-width: 1000px; margin: 40px auto; padding: 0 24px; }
            .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
            .header h1 { font-size: 28px; margin: 0; }
            .quiz-list { display: grid; gap: 16px; }
            .quiz-card { background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 18px; padding: 20px; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
            .quiz-card h2 { margin: 0 0 8px; font-size: 18px; }
            .quiz-meta { color: #6B7280; font-size: 14px; }
            .btn-primary { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #FFFFFF; background: #6C63FF; border-radius: 999px; padding: 10px 18px; font-weight: 600; border: none; cursor: pointer; }
            .btn-primary:hover { background: #5a52d9; }
            .btn-secondary { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #4B5563; background: #F3F4F6; border-radius: 999px; padding: 10px 18px; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div>
                    <h1>Select a Quiz</h1>
                    <p style="color:#6B7280; margin:8px 0 0;">Choose a published quiz to begin your assessment.</p>
                </div>
                <a href="dashboard.php" class="btn-secondary"><i class="fas fa-home"></i> Back to Dashboard</a>
            </div>

            <?php if (count($available_quizzes) > 0): ?>
                <div class="quiz-list">
                    <?php foreach ($available_quizzes as $quiz_option): ?>
                        <div class="quiz-card">
                            <div>
                                <h2><?php echo htmlspecialchars($quiz_option['title']); ?></h2>
                                <div class="quiz-meta">
                                    ❓ <?php echo $quiz_option['total_marks']; ?> questions • ⏱️ <?php echo $quiz_option['time_limit']; ?> min • Pass: <?php echo $quiz_option['passing_score']; ?>%
                                </div>
                            </div>
                            <a href="attempt_quiz.php?id=<?php echo $quiz_option['id']; ?>" class="btn-primary">
                                <i class="fas fa-play"></i> Start Quiz
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h2>No quizzes available</h2>
                    <p>There are currently no published quizzes. Please check back later.</p>
                </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// =============================================
// DEBUG - Check if quiz exists
// =============================================
$check_quiz_sql = "SELECT id, title FROM quizzes WHERE id = $quiz_id";
$check_quiz_result = mysqli_query($conn, $check_quiz_sql);
echo "<!-- Debug: Quiz Check Query = " . $check_quiz_sql . " -->";
echo "<!-- Debug: Quiz Check Result rows = " . mysqli_num_rows($check_quiz_result) . " -->";

if (mysqli_num_rows($check_quiz_result) == 0) {
    // Quiz not found - try to find first available quiz
    $fallback_sql = "SELECT id FROM quizzes WHERE is_published = 1 LIMIT 1";
    $fallback_result = mysqli_query($conn, $fallback_sql);
    if (mysqli_num_rows($fallback_result) > 0) {
        $fallback = mysqli_fetch_assoc($fallback_result);
        header("Location: attempt_quiz.php?id=" . $fallback['id']);
        exit();
    } else {
        header('Location: attempt_quiz.php');
        exit();
    }
}

// =============================================
// CHECK IF PUBLISHED
// =============================================
$check_published = "SELECT id FROM quizzes WHERE id = $quiz_id AND is_published = 1";
$published_result = mysqli_query($conn, $check_published);

if (mysqli_num_rows($published_result) == 0) {
    // Not published - redirect to lobby
    header('Location: attempt_quiz.php');
    exit();
}

// =============================================
// CHECK IF ALREADY ATTEMPTED
// =============================================
$check_sql = "SELECT id FROM quiz_attempts 
              WHERE student_id = $student_id AND quiz_id = $quiz_id AND is_completed = 1
              ORDER BY id DESC LIMIT 1";
$check_result = mysqli_query($conn, $check_sql);

if (mysqli_num_rows($check_result) > 0) {
    $attempt_data = mysqli_fetch_assoc($check_result);
    header("Location: quiz_result.php?id=$quiz_id&attempt=" . $attempt_data['id']);
    exit();
}

// =============================================
// FETCH QUIZ DETAILS
// =============================================
$quiz_sql = "SELECT q.*, u.full_name as teacher_name 
             FROM quizzes q
             JOIN users u ON q.teacher_id = u.id
             WHERE q.id = $quiz_id";
$quiz_result = mysqli_query($conn, $quiz_sql);
$quiz = mysqli_fetch_assoc($quiz_result);

// =============================================
// FETCH QUESTIONS
// =============================================
$questions_sql = "SELECT * FROM quiz_questions WHERE quiz_id = $quiz_id ORDER BY id";
$questions_result = mysqli_query($conn, $questions_sql);
$questions = mysqli_fetch_all($questions_result, MYSQLI_ASSOC);

if (count($questions) == 0) {
    // No questions - redirect to lobby
    header('Location: attempt_quiz.php');
    exit();
}

// =============================================
// HANDLE QUIZ SUBMISSION
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    $answers = isset($_POST['answers']) ? $_POST['answers'] : [];
    $score = 0;
    $correct = 0;
    $wrong = 0;
    $total = count($questions);
    
    foreach ($questions as $q) {
        $q_id = $q['id'];
        $user_answer = isset($answers[$q_id]) ? $answers[$q_id] : '';
        
        if ($user_answer === $q['correct_answer']) {
            $score += $q['marks'];
            $correct++;
        } else {
            $wrong++;
        }
    }
    
    $percentage = ($total > 0) ? ($correct / $total) * 100 : 0;
    
    $insert_sql = "INSERT INTO quiz_attempts (
                        student_id, quiz_id, score, total_questions, 
                        percentage, correct_answers, wrong_answers, is_completed
                    ) VALUES (
                        $student_id, $quiz_id, $score, $total,
                        $percentage, $correct, $wrong, 1
                    )";
    
    if (mysqli_query($conn, $insert_sql)) {
        $attempt_id = mysqli_insert_id($conn);
        
        foreach ($questions as $q) {
            $q_id = $q['id'];
            $user_answer = isset($answers[$q_id]) ? $answers[$q_id] : '';
            if (!empty($user_answer)) {
                $answer_sql = "INSERT INTO quiz_answers (attempt_id, question_id, answer) 
                               VALUES ($attempt_id, $q_id, '$user_answer')";
                mysqli_query($conn, $answer_sql);
            }
        }
        
        header("Location: quiz_result.php?id=$quiz_id&attempt=$attempt_id");
        exit();
    }
}

// =============================================
// STUDY TIPS
// =============================================
$tips = [
    '💡 Read all options carefully before selecting.',
    '💡 Manage your time wisely.',
    '💡 Review marked questions before submitting.',
    '💡 Trust your first instinct unless you\'re sure it\'s wrong.'
];
$random_tip = $tips[array_rand($tips)];

date_default_timezone_set('Asia/Kolkata');
$current_date = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($quiz['title']); ?> - EduHack AI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #FFFDF8;
            color: #1F2937;
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .sidebar {
            width: 260px;
            height: 100vh;
            background: white;
            border-right: 1px solid #F3F4F6;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            transition: all 0.4s ease;
            z-index: 1000;
            padding: 20px 0;
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: #6C63FF; border-radius: 2px; }
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 24px;
            margin-bottom: 30px;
            text-decoration: none;
        }
        .sidebar-brand .brand-icon {
            font-size: 32px;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            padding: 8px;
            border-radius: 12px;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar-brand .brand-text {
            font-size: 20px;
            font-weight: 700;
            color: #1F2937;
        }
        .sidebar-brand .brand-text span { color: #6C63FF; }
        .sidebar-menu {
            list-style: none;
            padding: 0 12px;
        }
        .sidebar-menu li { margin-bottom: 4px; }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 12px;
            color: #6B7280;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            position: relative;
        }
        .sidebar-menu a i { width: 20px; font-size: 18px; }
        .sidebar-menu a:hover {
            background: rgba(108, 99, 255, 0.06);
            color: #6C63FF;
            transform: translateX(4px);
        }
        .sidebar-menu a.active {
            background: linear-gradient(135deg, rgba(108, 99, 255, 0.1), rgba(139, 92, 246, 0.05));
            color: #6C63FF;
            font-weight: 600;
        }
        .sidebar-menu a.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 30px;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            border-radius: 0 4px 4px 0;
        }
        .sidebar-menu a.logout {
            margin-top: 20px;
            border-top: 1px solid #F3F4F6;
            padding-top: 20px;
            color: #EF4444;
        }
        .sidebar-menu a.logout:hover {
            background: rgba(239, 68, 68, 0.06);
            color: #EF4444;
        }
        .sidebar-menu .menu-label {
            font-size: 11px;
            font-weight: 700;
            color: #9CA3AF;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 16px 16px 8px;
        }
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 24px 32px 40px;
            min-height: 100vh;
            background: #FFFDF8;
        }
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .header-left .page-breadcrumb {
            font-size: 13px;
            color: #6B7280;
        }
        .header-left .page-breadcrumb a {
            color: #6C63FF;
            text-decoration: none;
        }
        .header-left .page-breadcrumb a:hover { text-decoration: underline; }
        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .profile-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .profile-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(108, 99, 255, 0.3);
        }
        .quiz-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 24px;
        }
        .quiz-info-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            padding: 20px 24px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
            justify-content: space-between;
        }
        .quiz-info-card .quiz-title {
            font-size: 20px;
            font-weight: 700;
            color: #1F2937;
        }
        .quiz-info-card .quiz-title span {
            color: #6C63FF;
        }
        .quiz-info-card .quiz-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .quiz-info-card .quiz-meta .meta-item {
            font-size: 13px;
            color: #6B7280;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .quiz-info-card .quiz-meta .meta-item i {
            color: #6C63FF;
        }
        .timer-container {
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .timer-container .timer-icon {
            font-size: 28px;
            color: #6C63FF;
        }
        .timer-container .timer-display {
            font-size: 28px;
            font-weight: 800;
            color: #1F2937;
            font-variant-numeric: tabular-nums;
            transition: color 0.3s ease;
        }
        .timer-container .timer-display.warning {
            color: #EF4444;
            animation: pulse 1s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .timer-container .timer-label {
            font-size: 13px;
            color: #6B7280;
            margin-left: auto;
        }
        .question-area {
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            padding: 28px;
            min-height: 400px;
            position: relative;
        }
        .question-area .q-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #F3F4F6;
        }
        .question-area .q-number {
            font-size: 14px;
            font-weight: 700;
            color: #6C63FF;
        }
        .question-area .q-mark {
            font-size: 13px;
            color: #6B7280;
        }
        .question-area .q-text {
            font-size: 18px;
            font-weight: 600;
            color: #1F2937;
            margin-bottom: 24px;
            line-height: 1.6;
        }
        .options-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .option-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        .option-item:hover {
            border-color: #6C63FF;
            background: rgba(108, 99, 255, 0.02);
        }
        .option-item.selected {
            border-color: #6C63FF;
            background: rgba(108, 99, 255, 0.05);
        }
        .option-item input[type="radio"] {
            display: none;
        }
        .option-item .option-letter {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #F3F4F6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            color: #6B7280;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        .option-item.selected .option-letter {
            background: #6C63FF;
            color: white;
        }
        .option-item .option-text {
            font-size: 15px;
            color: #1F2937;
        }
        .nav-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 2px solid #F3F4F6;
            flex-wrap: wrap;
        }
        .btn-nav {
            padding: 10px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-nav:hover {
            transform: translateY(-2px);
        }
        .btn-nav-prev {
            background: #F3F4F6;
            color: #6B7280;
        }
        .btn-nav-prev:hover {
            background: #E5E7EB;
        }
        .btn-nav-next {
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            color: white;
        }
        .btn-nav-next:hover {
            box-shadow: 0 4px 15px rgba(108, 99, 255, 0.3);
        }
        .btn-nav-review {
            background: rgba(245, 158, 11, 0.08);
            color: #F59E0B;
        }
        .btn-nav-review:hover {
            background: #F59E0B;
            color: white;
        }
        .btn-nav-submit {
            background: linear-gradient(135deg, #22C55E, #16A34A);
            color: white;
            margin-left: auto;
        }
        .btn-nav-submit:hover {
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
        }
        .palette-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            padding: 20px;
            position: sticky;
            top: 24px;
        }
        .palette-card .palette-title {
            font-size: 14px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .palette-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }
        .palette-btn {
            padding: 8px;
            border: 2px solid #E5E7EB;
            border-radius: 8px;
            background: white;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            color: #6B7280;
        }
        .palette-btn:hover {
            transform: scale(1.05);
        }
        .palette-btn.current {
            border-color: #6C63FF;
            background: rgba(108, 99, 255, 0.05);
            color: #6C63FF;
        }
        .palette-btn.answered {
            background: rgba(34, 197, 94, 0.08);
            border-color: #22C55E;
            color: #22C55E;
        }
        .palette-btn.review {
            background: rgba(245, 158, 11, 0.08);
            border-color: #F59E0B;
            color: #F59E0B;
        }
        .palette-btn.visited {
            background: rgba(108, 99, 255, 0.04);
            border-color: #6C63FF;
            color: #6C63FF;
        }
        .palette-legend {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            font-size: 11px;
            color: #6B7280;
        }
        .palette-legend .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .palette-legend .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            border: 1px solid #E5E7EB;
        }
        .palette-legend .legend-color.answered { background: rgba(34, 197, 94, 0.2); border-color: #22C55E; }
        .palette-legend .legend-color.review { background: rgba(245, 158, 11, 0.2); border-color: #F59E0B; }
        .palette-legend .legend-color.visited { background: rgba(108, 99, 255, 0.1); border-color: #6C63FF; }
        .palette-legend .legend-color.unvisited { background: white; border-color: #E5E7EB; }
        .progress-container {
            margin-top: 12px;
        }
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #E5E7EB;
            border-radius: 3px;
            overflow: hidden;
        }
        .progress-bar .progress-fill {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            transition: width 0.5s ease;
            width: 0%;
        }
        .progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #6B7280;
            margin-top: 4px;
        }
        .tip-card {
            background: linear-gradient(135deg, rgba(108, 99, 255, 0.04), rgba(139, 92, 246, 0.02));
            border: 1px solid rgba(108, 99, 255, 0.08);
            border-radius: 12px;
            padding: 14px 18px;
            margin-top: 16px;
            font-size: 14px;
            color: #4B5563;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeTip 0.5s ease;
        }
        @keyframes fadeTip {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .tip-card .tip-icon { font-size: 20px; }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .modal {
            background: white;
            border-radius: 20px;
            max-width: 500px;
            width: 100%;
            padding: 32px;
            animation: slideUp 0.3s ease;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .modal-header .modal-icon {
            font-size: 48px;
            display: block;
            margin-bottom: 8px;
        }
        .modal-header h3 {
            font-size: 22px;
            font-weight: 700;
            color: #1F2937;
        }
        .modal-header p {
            color: #6B7280;
            font-size: 14px;
            margin-top: 4px;
        }
        .modal-body .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #F9FAFB;
        }
        .modal-body .summary-row:last-child { border-bottom: none; }
        .modal-body .summary-label { color: #6B7280; }
        .modal-body .summary-value { font-weight: 600; color: #1F2937; }
        .modal-footer {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            justify-content: center;
        }
        .btn-modal {
            padding: 10px 28px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-modal-cancel {
            background: #F3F4F6;
            color: #6B7280;
        }
        .btn-modal-cancel:hover {
            background: #E5E7EB;
        }
        .btn-modal-submit {
            background: linear-gradient(135deg, #22C55E, #16A34A);
            color: white;
        }
        .btn-modal-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
        }
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 1001;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid #E5E7EB;
            background: white;
            color: #1F2937;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .sidebar-toggle:hover {
            border-color: #6C63FF;
            color: #6C63FF;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.3);
            z-index: 999;
        }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 1024px) {
            .quiz-container { grid-template-columns: 1fr; }
            .palette-card { position: static; }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.open { transform: translateX(0); }
            .sidebar-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .main-content {
                margin-left: 0;
                padding: 20px 16px 32px;
                padding-top: 72px;
            }
            .top-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .quiz-info-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .quiz-info-card .quiz-meta {
                flex-wrap: wrap;
            }
            .timer-container .timer-display {
                font-size: 22px;
            }
            .question-area {
                padding: 20px;
            }
            .question-area .q-text {
                font-size: 16px;
            }
            .palette-grid {
                grid-template-columns: repeat(5, 1fr);
            }
            .nav-buttons {
                flex-direction: column;
            }
            .btn-nav {
                width: 100%;
                justify-content: center;
            }
            .btn-nav-submit {
                margin-left: 0;
            }
            .header-right {
                width: 100%;
                justify-content: flex-start;
            }
        }

        @media (max-width: 480px) {
            .question-area { padding: 16px; }
            .option-item { padding: 12px 14px; }
            .option-item .option-text { font-size: 14px; }
            .palette-grid { grid-template-columns: repeat(4, 1fr); }
            .quiz-info-card .quiz-title { font-size: 17px; }
            .timer-container { flex-wrap: wrap; }
            .modal { padding: 24px; margin: 10px; }
        }
    </style>
</head>
<body>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar" id="sidebar">
        <a href="dashboard.php" class="sidebar-brand">
            <span class="brand-icon">🎓</span>
            <span class="brand-text">EduHack <span>AI</span></span>
        </a>
        <ul class="sidebar-menu">
            <li class="menu-label">Main Menu</li>
            <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
            <li><a href="browse_notes.php"><i class="fas fa-book"></i> Browse Notes</a></li>
            <li><a href="attempt_quiz.php" class="active"><i class="fas fa-puzzle-piece"></i> Attempt Quiz</a></li>
            <li><a href="progress.php"><i class="fas fa-chart-line"></i> Progress</a></li>
            <li><a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a></li>
            <li><a href="forum.php"><i class="fas fa-comments"></i> Forum</a></li>
            <li><a href="../logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>

        <header class="top-header">
            <div class="header-left">
                <div class="page-breadcrumb">
                    <a href="browse_notes.php"><i class="fas fa-book"></i> Notes Library</a>
                    <span> / </span>
                    <a href="attempt_quiz.php">Quizzes</a>
                    <span> / </span>
                    <span><?php echo htmlspecialchars(substr($quiz['title'], 0, 30)) . (strlen($quiz['title']) > 30 ? '...' : ''); ?></span>
                </div>
            </div>
            <div class="header-right">
                <div style="font-size:13px; color:#6B7280;">
                    <i class="far fa-calendar-alt"></i> <?php echo $current_date; ?>
                </div>
                <a href="#" class="profile-avatar">
                    <?php echo strtoupper(substr($student_name, 0, 2)); ?>
                </a>
            </div>
        </header>

        <div class="quiz-info-card animate-in">
            <div>
                <div class="quiz-title">
                    🧪 <span><?php echo htmlspecialchars($quiz['title']); ?></span>
                </div>
                <div style="font-size:13px; color:#6B7280; margin-top:4px;">
                    by <?php echo htmlspecialchars($quiz['teacher_name']); ?>
                </div>
            </div>
            <div class="quiz-meta">
                <span class="meta-item"><i class="fas fa-list-ol"></i> <?php echo count($questions); ?> Questions</span>
                <span class="meta-item"><i class="fas fa-clock"></i> <?php echo $quiz['time_limit']; ?> min</span>
                <span class="meta-item"><i class="fas fa-flag-checkered"></i> Pass: <?php echo $quiz['passing_score']; ?>%</span>
            </div>
        </div>

        <div class="timer-container animate-in">
            <span class="timer-icon">⏱️</span>
            <span class="timer-display" id="timerDisplay"><?php echo sprintf('%02d:%02d', $quiz['time_limit'], 0); ?></span>
            <span class="timer-label">Time Remaining</span>
        </div>

        <form method="POST" id="quizForm">
            <div class="quiz-container">
                <div>
                    <div class="question-area animate-in" id="questionArea"></div>
                    <div class="tip-card animate-in" id="tipCard">
                        <span class="tip-icon">💡</span>
                        <span id="tipText"><?php echo $random_tip; ?></span>
                    </div>
                </div>
                <div>
                    <div class="palette-card animate-in">
                        <div class="palette-title"><i class="fas fa-th"></i> Question Palette</div>
                        <div class="palette-grid" id="paletteGrid"></div>
                        <div class="progress-container">
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressFill" style="width:0%;"></div>
                            </div>
                            <div class="progress-text">
                                <span id="answeredCount">0</span>
                                <span>Answered</span>
                                <span id="progressPercent">0%</span>
                            </div>
                        </div>
                        <div class="palette-legend" style="margin-top:12px; padding-top:12px; border-top:1px solid #F3F4F6;">
                            <span class="legend-item"><span class="legend-color answered"></span> Answered</span>
                            <span class="legend-item"><span class="legend-color review"></span> Marked</span>
                            <span class="legend-item"><span class="legend-color visited"></span> Visited</span>
                            <span class="legend-item"><span class="legend-color unvisited"></span> Unvisited</span>
                        </div>
                    </div>
                </div>
            </div>
            <input type="hidden" name="submit_quiz" value="1">
        </form>
    </main>

    <div class="modal-overlay" id="submitModal">
        <div class="modal">
            <div class="modal-header">
                <span class="modal-icon">📝</span>
                <h3>Submit Quiz?</h3>
                <p>Are you sure you want to submit your answers?</p>
            </div>
            <div class="modal-body">
                <div class="summary-row">
                    <span class="summary-label">Total Questions</span>
                    <span class="summary-value" id="modalTotal">0</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Answered</span>
                    <span class="summary-value" id="modalAnswered">0</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Unanswered</span>
                    <span class="summary-value" id="modalUnanswered">0</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Marked for Review</span>
                    <span class="summary-value" id="modalReview">0</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal btn-modal-cancel" onclick="closeModal('submitModal')">Cancel</button>
                <button class="btn-modal btn-modal-submit" onclick="submitQuiz()">
                    <i class="fas fa-check"></i> Submit Quiz
                </button>
            </div>
        </div>
    </div>

    <script>
        // =============================================
        // DATA
        // =============================================
        const questions = <?php echo json_encode($questions); ?>;
        const totalQuestions = questions.length;
        const timeLimit = <?php echo $quiz['time_limit']; ?> * 60;
        
        let currentQuestion = 0;
        let answers = {};
        let reviewStatus = {};
        let visitedQuestions = new Set();
        let timerInterval;
        let timeRemaining = timeLimit;
        let isSubmitted = false;

        // =============================================
        // SIDEBAR TOGGLE
        // =============================================
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            sidebarOverlay.classList.toggle('active');
            this.innerHTML = sidebar.classList.contains('open') ? 
                '<i class="fas fa-times"></i>' : 
                '<i class="fas fa-bars"></i>';
        });

        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('active');
            sidebarToggle.innerHTML = '<i class="fas fa-bars"></i>';
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('open');
                sidebarOverlay.classList.remove('active');
                sidebarToggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
        });

        // =============================================
        // TIMER
        // =============================================
        function startTimer() {
            timerInterval = setInterval(() => {
                timeRemaining--;
                updateTimerDisplay();
                
                if (timeRemaining <= 0) {
                    clearInterval(timerInterval);
                    alert('⏰ Time is up! Your quiz will be submitted automatically.');
                    submitQuiz();
                }
            }, 1000);
        }

        function updateTimerDisplay() {
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            const display = document.getElementById('timerDisplay');
            display.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            
            if (timeRemaining < 120) {
                display.classList.add('warning');
            }
        }

        // =============================================
        // RENDER QUESTION
        // =============================================
        function renderQuestion(index) {
            const q = questions[index];
            const area = document.getElementById('questionArea');
            const selected = answers[q.id] || '';
            const isReview = reviewStatus[q.id] || false;
            
            visitedQuestions.add(index);
            
            area.innerHTML = `
                <div class="q-header">
                    <span class="q-number">Question ${index + 1} of ${totalQuestions}</span>
                    <span class="q-mark">${q.marks} mark${q.marks > 1 ? 's' : ''}</span>
                </div>
                <div class="q-text">${escapeHtml(q.question)}</div>
                <div class="options-container">
                    ${['A', 'B', 'C', 'D'].map(letter => `
                        <label class="option-item ${selected === letter ? 'selected' : ''}" onclick="selectOption(${q.id}, '${letter}')">
                            <input type="radio" name="q_${q.id}" value="${letter}" ${selected === letter ? 'checked' : ''}>
                            <span class="option-letter">${letter}</span>
                            <span class="option-text">${escapeHtml(q['option_' + letter.toLowerCase()])}</span>
                        </label>
                    `).join('')}
                </div>
                <div class="nav-buttons">
                    <button class="btn-nav btn-nav-prev" onclick="prevQuestion()" ${index === 0 ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : ''}>
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                    <button class="btn-nav btn-nav-review" onclick="toggleReview(${q.id})">
                        <i class="fas fa-flag"></i> ${isReview ? 'Unmark' : 'Mark for Review'}
                    </button>
                    <button class="btn-nav btn-nav-next" onclick="nextQuestion()" ${index === totalQuestions - 1 ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : ''}>
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                    <button class="btn-nav btn-nav-submit" onclick="openSubmitModal()">
                        <i class="fas fa-check-circle"></i> Submit Quiz
                    </button>
                </div>
            `;
            
            updatePalette();
            updateProgress();
            updateTip();
        }

        // =============================================
        // NAVIGATION
        // =============================================
        function nextQuestion() {
            if (currentQuestion < totalQuestions - 1) {
                currentQuestion++;
                renderQuestion(currentQuestion);
                scrollToTop();
            }
        }

        function prevQuestion() {
            if (currentQuestion > 0) {
                currentQuestion--;
                renderQuestion(currentQuestion);
                scrollToTop();
            }
        }

        function goToQuestion(index) {
            if (index >= 0 && index < totalQuestions) {
                currentQuestion = index;
                renderQuestion(currentQuestion);
                scrollToTop();
            }
        }

        function scrollToTop() {
            document.getElementById('questionArea').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // =============================================
        // SELECT OPTION
        // =============================================
        function selectOption(qId, letter) {
            answers[qId] = letter;
            
            document.querySelectorAll('.option-item').forEach(el => {
                el.classList.remove('selected');
                const radio = el.querySelector('input[type="radio"]');
                if (radio && radio.value === letter) {
                    el.classList.add('selected');
                    radio.checked = true;
                }
            });
            
            updatePalette();
            updateProgress();
        }

        // =============================================
        // TOGGLE REVIEW
        // =============================================
        function toggleReview(qId) {
            reviewStatus[qId] = !reviewStatus[qId];
            updatePalette();
            renderQuestion(currentQuestion);
        }

        // =============================================
        // UPDATE PALETTE
        // =============================================
        function updatePalette() {
            const grid = document.getElementById('paletteGrid');
            grid.innerHTML = '';
            
            questions.forEach((q, index) => {
                const btn = document.createElement('button');
                btn.className = 'palette-btn';
                btn.textContent = index + 1;
                
                const isAnswered = answers[q.id] !== undefined;
                const isReview = reviewStatus[q.id] || false;
                const isVisited = visitedQuestions.has(index);
                
                if (index === currentQuestion) {
                    btn.classList.add('current');
                }
                if (isAnswered) {
                    btn.classList.add('answered');
                }
                if (isReview) {
                    btn.classList.add('review');
                }
                if (isVisited && !isAnswered && !isReview) {
                    btn.classList.add('visited');
                }
                
                btn.onclick = () => goToQuestion(index);
                grid.appendChild(btn);
            });
        }

        // =============================================
        // UPDATE PROGRESS
        // =============================================
        function updateProgress() {
            const answered = Object.keys(answers).length;
            const percentage = totalQuestions > 0 ? Math.round((answered / totalQuestions) * 100) : 0;
            
            document.getElementById('answeredCount').textContent = answered;
            document.getElementById('progressFill').style.width = percentage + '%';
            document.getElementById('progressPercent').textContent = percentage + '%';
        }

        // =============================================
        // UPDATE TIP
        // =============================================
        function updateTip() {
            const tips = [
                '💡 Read all options carefully before selecting.',
                '💡 Manage your time wisely. Don\'t spend too long on one question.',
                '💡 Review marked questions before submitting.',
                '💡 Trust your first instinct unless you\'re sure it\'s wrong.',
                '💡 Eliminate obviously wrong answers to improve your chances.',
                '💡 Stay calm and focused. Take deep breaths if you feel stressed.'
            ];
            const tipText = document.getElementById('tipText');
            tipText.textContent = tips[Math.floor(Math.random() * tips.length)];
            
            const tipCard = document.getElementById('tipCard');
            tipCard.style.animation = 'none';
            setTimeout(() => {
                tipCard.style.animation = 'fadeTip 0.5s ease';
            }, 10);
        }

        // =============================================
        // MODAL FUNCTIONS
        // =============================================
        function openSubmitModal() {
            const answered = Object.keys(answers).length;
            const unanswered = totalQuestions - answered;
            const review = Object.values(reviewStatus).filter(v => v).length;
            
            document.getElementById('modalTotal').textContent = totalQuestions;
            document.getElementById('modalAnswered').textContent = answered;
            document.getElementById('modalUnanswered').textContent = unanswered;
            document.getElementById('modalReview').textContent = review;
            
            document.getElementById('submitModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            document.body.style.overflow = '';
        }

        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // =============================================
        // SUBMIT QUIZ
        // =============================================
        function submitQuiz() {
            if (isSubmitted) return;
            isSubmitted = true;
            
            const form = document.getElementById('quizForm');
            form.querySelectorAll('input[name^="answers"]').forEach(el => el.remove());
            
            Object.keys(answers).forEach(qId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `answers[${qId}]`;
                input.value = answers[qId];
                form.appendChild(input);
            });
            
            const submitInput = document.createElement('input');
            submitInput.type = 'hidden';
            submitInput.name = 'submit_quiz';
            submitInput.value = '1';
            form.appendChild(submitInput);
            
            closeModal('submitModal');
            form.submit();
        }

        // =============================================
        // HELPER FUNCTIONS
        // =============================================
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // =============================================
        // WARNING ON PAGE LEAVE
        // =============================================
        window.addEventListener('beforeunload', function(e) {
            if (!isSubmitted && Object.keys(answers).length > 0) {
                e.preventDefault();
                e.returnValue = 'You have unsaved answers. Are you sure you want to leave?';
                return e.returnValue;
            }
        });

        // =============================================
        // INITIALIZE
        // =============================================
        document.addEventListener('DOMContentLoaded', function() {
            renderQuestion(0);
            startTimer();
            updateProgress();
            console.log('🧪 EduHack AI - Quiz Started');
            console.log('📝 Total Questions:', totalQuestions);
            console.log('⏱️ Time Limit:', timeLimit, 'seconds');
        });
    </script>
</body>
</html>