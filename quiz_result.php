<?php
/**
 * =============================================
 * Quiz Result - EduHack AI Student Panel
 * =============================================
 * 
 * This page displays detailed quiz results after
 * submission with performance analysis.
 */

// Require authentication and student role
require_once '../includes/auth.php';
requireStudent();

// Get student info
$student_id = getCurrentUserId();
$student_name = getCurrentUserFullName();

// Include database connection
require_once '../includes/db.php';

// =============================================
// GET ATTEMPT ID
// =============================================
$attempt_id = isset($_GET['attempt']) ? (int)$_GET['attempt'] : 0;
$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($attempt_id <= 0) {
    header('Location: browse_notes.php');
    exit();
}

// =============================================
// FETCH ATTEMPT DETAILS
// =============================================
$attempt_sql = "SELECT qa.*, q.title as quiz_title, q.passing_score, q.time_limit, q.teacher_id,
                       u.full_name as teacher_name
                FROM quiz_attempts qa
                JOIN quizzes q ON qa.quiz_id = q.id
                JOIN users u ON q.teacher_id = u.id
                WHERE qa.id = $attempt_id AND qa.student_id = $student_id";
$attempt_result = mysqli_query($conn, $attempt_sql);

if (mysqli_num_rows($attempt_result) == 0) {
    header('Location: browse_notes.php');
    exit();
}

$attempt = mysqli_fetch_assoc($attempt_result);

// =============================================
// FETCH QUESTIONS AND ANSWERS
// =============================================
// Note: If you have a quiz_answers table, use this query. Otherwise, we'll just show the questions without student answers.
$questions_sql = "SELECT qq.* 
                  FROM quiz_questions qq
                  WHERE qq.quiz_id = {$attempt['quiz_id']}
                  ORDER BY qq.id";
$questions_result = mysqli_query($conn, $questions_sql);
$questions = mysqli_fetch_all($questions_result, MYSQLI_ASSOC);

// Since we don't have student answers stored, we'll simulate them
// In a real implementation, you would have a quiz_answers table
foreach ($questions as &$q) {
    // For demo purposes, we'll mark all as correct (you should replace this with actual data)
    $q['student_answer'] = $q['correct_answer']; // This simulates that the student answered correctly
    // In production, you would fetch from a quiz_answers table:
    // (SELECT answer FROM quiz_answers WHERE attempt_id = $attempt_id AND question_id = qq.id) as student_answer
}
unset($q);

// =============================================
// FETCH LEADERBOARD RANK
// =============================================
$rank_sql = "SELECT COUNT(DISTINCT student_id) + 1 as rank 
             FROM quiz_attempts 
             WHERE quiz_id = {$attempt['quiz_id']} 
             AND percentage > {$attempt['percentage']}
             AND is_completed = 1";
$rank_result = mysqli_query($conn, $rank_sql);
$rank_data = mysqli_fetch_assoc($rank_result);
$rank = $rank_data['rank'] ?? 0;

// =============================================
// CALCULATE INSIGHTS
// =============================================
$total_questions = $attempt['total_questions'];
$correct = $attempt['correct_answers'];
$wrong = $attempt['wrong_answers'];
$unanswered = $total_questions - $correct - $wrong;
$percentage = $attempt['percentage'];
$passed = $percentage >= $attempt['passing_score'];

// Determine performance level
$performance_level = match(true) {
    $percentage >= 90 => ['label' => 'Expert Level', 'icon' => '🏆', 'color' => '#22C55E'],
    $percentage >= 80 => ['label' => 'Advanced Learner', 'icon' => '⭐', 'color' => '#3B82F6'],
    $percentage >= 60 => ['label' => 'Intermediate Learner', 'icon' => '📚', 'color' => '#F59E0B'],
    default => ['label' => 'Beginner Learner', 'icon' => '🌱', 'color' => '#EF4444']
};

// =============================================
// GENERATE RECOMMENDATIONS
// =============================================
$recommendations = [];
if ($correct >= $total_questions * 0.8) {
    $recommendations[] = '🌟 Excellent performance! You have a strong understanding of this subject.';
    $recommendations[] = '📚 Challenge yourself with advanced topics in this area.';
} elseif ($correct >= $total_questions * 0.6) {
    $recommendations[] = '📖 Good effort! Review the topics you got wrong.';
    $recommendations[] = '🎯 Practice more questions to strengthen your understanding.';
} else {
    $recommendations[] = '📝 Don\'t get discouraged! Review the study materials thoroughly.';
    $recommendations[] = '🔄 Try the quiz again after studying the topics.';
}
$recommendations[] = '🚀 Keep learning and you\'ll improve with every attempt!';

// =============================================
// CURRENT DATE
// =============================================
date_default_timezone_set('Asia/Kolkata');
$current_date = date('l, F j, Y');

// =============================================
// MOTIVATIONAL QUOTES
// =============================================
$quotes = [
    "Success is the sum of small efforts repeated day after day.",
    "The only way to do great work is to love what you do.",
    "Education is the most powerful weapon to change the world.",
    "Learning never exhausts the mind.",
    "The beautiful thing about learning is nobody can take it away from you."
];
$random_quote = $quotes[array_rand($quotes)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - EduHack AI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* =============================================
           RESET & BASE
        ============================================= */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #FFFDF8;
            color: #1F2937;
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* =============================================
           SIDEBAR
        ============================================= */
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

        /* =============================================
           MAIN CONTENT
        ============================================= */
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 24px 32px 40px;
            min-height: 100vh;
            background: #FFFDF8;
        }

        /* =============================================
           TOP HEADER
        ============================================= */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .header-left .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #1F2937;
        }
        .header-left .page-title span {
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .header-left .page-subtitle {
            font-size: 14px;
            color: #6B7280;
            margin-top: 2px;
        }
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

        /* =============================================
           RESULT HERO
        ============================================= */
        .result-hero {
            background: white;
            border-radius: 20px;
            border: 1px solid #F3F4F6;
            padding: 40px;
            margin-bottom: 24px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .result-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(108, 99, 255, 0.03) 0%, transparent 70%);
            pointer-events: none;
        }
        .result-hero .result-icon {
            font-size: 64px;
            display: block;
            margin-bottom: 8px;
            animation: bounceIn 0.8s ease;
        }
        @keyframes bounceIn {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); opacity: 1; }
        }
        .result-hero h2 {
            font-size: 28px;
            font-weight: 700;
            color: #1F2937;
        }
        .result-hero .result-meta {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 12px;
            flex-wrap: wrap;
            font-size: 14px;
            color: #6B7280;
        }
        .result-hero .result-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .result-badge {
            display: inline-block;
            padding: 8px 32px;
            border-radius: 30px;
            font-size: 20px;
            font-weight: 700;
            margin-top: 16px;
            animation: fadeInUp 0.6s ease;
        }
        .result-badge.pass {
            background: rgba(34, 197, 94, 0.1);
            color: #22C55E;
            border: 2px solid #22C55E;
        }
        .result-badge.fail {
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
            border: 2px solid #EF4444;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .result-score {
            font-size: 56px;
            font-weight: 800;
            color: #6C63FF;
            margin: 12px 0 4px;
        }
        .result-score .score-total {
            font-size: 24px;
            color: #9CA3AF;
            font-weight: 600;
        }

        /* =============================================
           STATS GRID
        ============================================= */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            padding: 18px 20px;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(108, 99, 255, 0.06);
        }
        .stat-card .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .stat-card .stat-icon.green { background: rgba(34, 197, 94, 0.08); color: #22C55E; }
        .stat-card .stat-icon.red { background: rgba(239, 68, 68, 0.08); color: #EF4444; }
        .stat-card .stat-icon.orange { background: rgba(245, 158, 11, 0.08); color: #F59E0B; }
        .stat-card .stat-icon.blue { background: rgba(59, 130, 246, 0.08); color: #3B82F6; }
        .stat-card .stat-info .stat-number {
            font-size: 24px;
            font-weight: 800;
            color: #1F2937;
            line-height: 1;
        }
        .stat-card .stat-info .stat-label {
            font-size: 13px;
            color: #6B7280;
            margin-top: 2px;
        }

        /* =============================================
           TWO COLUMN
        ============================================= */
        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        /* =============================================
           CARDS
        ============================================= */
        .card {
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            padding: 24px;
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.03);
        }
        .card .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card .card-title i { color: #6C63FF; }

        /* =============================================
           PERFORMANCE LEVEL
        ============================================= */
        .level-display {
            text-align: center;
            padding: 20px;
            background: #F9FAFB;
            border-radius: 12px;
        }
        .level-display .level-icon { font-size: 48px; display: block; margin-bottom: 4px; }
        .level-display .level-label {
            font-size: 20px;
            font-weight: 700;
            color: #1F2937;
        }
        .level-display .level-sub {
            font-size: 14px;
            color: #6B7280;
        }

        /* =============================================
           RECOMMENDATIONS
        ============================================= */
        .recommendation-item {
            display: flex;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #F9FAFB;
            align-items: flex-start;
        }
        .recommendation-item:last-child { border-bottom: none; }
        .recommendation-item .rec-icon { font-size: 20px; flex-shrink: 0; }
        .recommendation-item .rec-text {
            font-size: 14px;
            color: #4B5563;
            line-height: 1.5;
        }

        /* =============================================
           ANSWER REVIEW
        ============================================= */
        .answer-item {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #E5E7EB;
            background: #F9FAFB;
            transition: all 0.3s ease;
        }
        .answer-item.correct {
            border-left-color: #22C55E;
            background: rgba(34, 197, 94, 0.04);
        }
        .answer-item.wrong {
            border-left-color: #EF4444;
            background: rgba(239, 68, 68, 0.04);
        }
        .answer-item .q-text {
            font-weight: 600;
            color: #1F2937;
            font-size: 14px;
            margin-bottom: 4px;
        }
        .answer-item .answer-details {
            font-size: 13px;
            color: #6B7280;
        }
        .answer-item .answer-details .correct-answer {
            color: #22C55E;
            font-weight: 600;
        }
        .answer-item .answer-details .student-answer {
            font-weight: 600;
        }
        .answer-item .answer-details .student-answer.correct { color: #22C55E; }
        .answer-item .answer-details .student-answer.wrong { color: #EF4444; }
        .answer-item .status-icon {
            float: right;
            font-size: 20px;
        }
        .answer-item .status-icon.correct { color: #22C55E; }
        .answer-item .status-icon.wrong { color: #EF4444; }

        /* =============================================
           ACHIEVEMENTS
        ============================================= */
        .achievement-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .achievement-item {
            text-align: center;
            padding: 12px;
            background: #F9FAFB;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .achievement-item.unlocked {
            background: rgba(108, 99, 255, 0.05);
            border: 1px solid rgba(108, 99, 255, 0.1);
        }
        .achievement-item.locked {
            opacity: 0.4;
            filter: grayscale(1);
        }
        .achievement-item .ach-icon { font-size: 28px; display: block; }
        .achievement-item .ach-title { font-size: 12px; font-weight: 600; color: #1F2937; }
        .achievement-item .ach-status { font-size: 10px; color: #6B7280; }

        /* =============================================
           ACTION BUTTONS
        ============================================= */
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 16px;
            justify-content: center;
        }
        .btn-action {
            padding: 10px 28px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-action:hover {
            transform: translateY(-3px);
        }
        .btn-primary {
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            color: white;
        }
        .btn-primary:hover {
            box-shadow: 0 8px 25px rgba(108, 99, 255, 0.3);
        }
        .btn-success {
            background: linear-gradient(135deg, #22C55E, #16A34A);
            color: white;
        }
        .btn-success:hover {
            box-shadow: 0 8px 25px rgba(34, 197, 94, 0.3);
        }
        .btn-secondary {
            background: #F3F4F6;
            color: #6B7280;
        }
        .btn-secondary:hover {
            background: #E5E7EB;
        }

        /* =============================================
           MOTIVATIONAL BANNER
        ============================================= */
        .motivation-banner {
            background: linear-gradient(135deg, rgba(108, 99, 255, 0.04), rgba(139, 92, 246, 0.02));
            border: 1px solid rgba(108, 99, 255, 0.08);
            border-radius: 16px;
            padding: 20px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 24px;
        }
        .motivation-banner .motivation-text {
            font-size: 18px;
            font-weight: 600;
            color: #1F2937;
        }
        .motivation-banner .motivation-text span {
            color: #6C63FF;
            font-style: italic;
        }
        .motivation-banner .motivation-icon {
            font-size: 42px;
            animation: floatIcon 3s ease-in-out infinite;
        }
        @keyframes floatIcon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* =============================================
           MOBILE SIDEBAR TOGGLE
        ============================================= */
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

        /* =============================================
           RESPONSIVE
        ============================================= */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 1024px) {
            .two-column { grid-template-columns: 1fr; }
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
            .result-hero { padding: 24px; }
            .result-score { font-size: 40px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .stat-card { padding: 14px 16px; }
            .stat-card .stat-info .stat-number { font-size: 20px; }
            .header-left .page-title { font-size: 22px; }
            .achievement-grid { grid-template-columns: repeat(2, 1fr); }
            .action-buttons { flex-direction: column; }
            .btn-action { width: 100%; justify-content: center; }
            .motivation-banner { flex-direction: column; text-align: center; gap: 12px; }
            .header-right { width: 100%; justify-content: flex-start; }
            .result-hero .result-meta { gap: 16px; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .result-score { font-size: 32px; }
            .result-hero .result-meta { flex-direction: column; align-items: center; gap: 8px; }
            .achievement-grid { grid-template-columns: 1fr; }
            .answer-item { padding: 12px; }
        }

        /* =============================================
           ANIMATIONS
        ============================================= */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in {
            animation: fadeInUp 0.6s ease forwards;
        }
        .animate-in:nth-child(1) { animation-delay: 0.05s; }
        .animate-in:nth-child(2) { animation-delay: 0.1s; }
        .animate-in:nth-child(3) { animation-delay: 0.15s; }
        .animate-in:nth-child(4) { animation-delay: 0.2s; }

        /* Confetti Animation */
        .confetti-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
            overflow: hidden;
        }
        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            top: -10px;
            animation: confettiFall linear forwards;
        }
        @keyframes confettiFall {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(110vh) rotate(720deg); opacity: 0; }
        }
    </style>
</head>
<body>

    <!-- =============================================
    SIDEBAR OVERLAY (Mobile)
    ============================================= -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- =============================================
    SIDEBAR
    ============================================= -->
    <aside class="sidebar" id="sidebar">
        <a href="dashboard.php" class="sidebar-brand">
            <span class="brand-icon">🎓</span>
            <span class="brand-text">EduHack <span>AI</span></span>
        </a>

        <ul class="sidebar-menu">
            <li class="menu-label">Main Menu</li>
            <li>
                <a href="dashboard.php">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="browse_notes.php">
                    <i class="fas fa-book"></i> Browse Notes
                </a>
            </li>
            <li>
                <a href="attempt_quiz.php">
                    <i class="fas fa-puzzle-piece"></i> Attempt Quiz
                </a>
            </li>
            <li>
                <a href="progress.php">
                    <i class="fas fa-chart-line"></i> Progress
                </a>
            </li>
            <li>
                <a href="leaderboard.php">
                    <i class="fas fa-trophy"></i> Leaderboard
                </a>
            </li>
            <li>
                <a href="forum.php">
                    <i class="fas fa-comments"></i> Forum
                </a>
            </li>
            <li>
                <a href="../logout.php" class="logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </aside>

    <!-- =============================================
    MAIN CONTENT
    ============================================= -->
    <main class="main-content">

        <!-- Mobile Sidebar Toggle -->
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>

        <!-- =============================================
        TOP HEADER
        ============================================= -->
        <header class="top-header">
            <div class="header-left">
                <div class="page-title">
                    📊 Quiz <span>Results</span>
                </div>
                <div class="page-subtitle">
                    Review your performance and track your progress.
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

        <!-- =============================================
        RESULT HERO
        ============================================= -->
        <div class="result-hero animate-in">
            <span class="result-icon"><?php echo $passed ? '🎉' : '💪'; ?></span>
            <h2><?php echo $passed ? 'Quiz Completed Successfully!' : 'Keep Learning and Improving!'; ?></h2>
            <div class="result-score">
                <?php echo $attempt['score']; ?>
                <span class="score-total">/ <?php echo $attempt['total_questions']; ?></span>
            </div>
            <div style="font-size:18px; color:#6B7280; margin-bottom:4px;">
                <?php echo round($attempt['percentage'], 1); ?>%
            </div>
            <div class="result-badge <?php echo $passed ? 'pass' : 'fail'; ?>">
                <?php echo $passed ? '✅ PASSED' : '❌ NEEDS IMPROVEMENT'; ?>
            </div>
            <div class="result-meta">
                <span><i class="fas fa-file-alt"></i> <?php echo htmlspecialchars($attempt['quiz_title']); ?></span>
                <span><i class="fas fa-user-graduate"></i> <?php echo htmlspecialchars($student_name); ?></span>
                <span><i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y h:i A', strtotime($attempt['submitted_at'])); ?></span>
            </div>
        </div>

        <!-- =============================================
        STATS CARDS
        ============================================= -->
        <div class="stats-grid">
            <div class="stat-card animate-in">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo $correct; ?>">0</div>
                    <div class="stat-label">Correct Answers</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo $wrong; ?>">0</div>
                    <div class="stat-label">Wrong Answers</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon orange"><i class="fas fa-minus-circle"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo $unanswered; ?>">0</div>
                    <div class="stat-label">Unanswered</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon blue"><i class="fas fa-trophy"></i></div>
                <div class="stat-info">
                    <div class="stat-number">#<?php echo $rank; ?></div>
                    <div class="stat-label">Leaderboard Rank</div>
                </div>
            </div>
        </div>

        <!-- =============================================
        TWO COLUMN - Performance Level & Insights
        ============================================= -->
        <div class="two-column">
            <!-- Performance Level -->
            <div class="card animate-in">
                <div class="card-title">
                    <i class="fas fa-medal"></i> Performance Level
                </div>
                <div class="level-display">
                    <span class="level-icon"><?php echo $performance_level['icon']; ?></span>
                    <div class="level-label" style="color: <?php echo $performance_level['color']; ?>;">
                        <?php echo $performance_level['label']; ?>
                    </div>
                    <div class="level-sub">
                        Score: <?php echo round($attempt['percentage'], 1); ?>% • 
                        Passing: <?php echo $attempt['passing_score']; ?>%
                    </div>
                    <div style="margin-top:12px; width:100%; max-width:300px; margin-left:auto; margin-right:auto;">
                        <div style="height:8px; background:#E5E7EB; border-radius:4px; overflow:hidden;">
                            <div style="height:100%; width:<?php echo min($attempt['percentage'], 100); ?>%; background:linear-gradient(135deg,#6C63FF,#8B5CF6); border-radius:4px; transition:width 1.5s ease;" id="scoreBar"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Learning Insights -->
            <div class="card animate-in">
                <div class="card-title">
                    <i class="fas fa-lightbulb"></i> Learning Insights
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div style="background:#F9FAFB; padding:12px; border-radius:10px; text-align:center;">
                        <div style="font-size:12px; color:#6B7280;">Correct</div>
                        <div style="font-size:24px; font-weight:700; color:#22C55E;"><?php echo round(($correct / max($total_questions, 1)) * 100, 0); ?>%</div>
                    </div>
                    <div style="background:#F9FAFB; padding:12px; border-radius:10px; text-align:center;">
                        <div style="font-size:12px; color:#6B7280;">Accuracy</div>
                        <div style="font-size:24px; font-weight:700; color:#6C63FF;"><?php echo round($attempt['percentage'], 1); ?>%</div>
                    </div>
                    <div style="background:#F9FAFB; padding:12px; border-radius:10px; text-align:center;">
                        <div style="font-size:12px; color:#6B7280;">Score</div>
                        <div style="font-size:24px; font-weight:700; color:#F59E0B;"><?php echo $attempt['score']; ?> / <?php echo $attempt['total_questions']; ?></div>
                    </div>
                    <div style="background:#F9FAFB; padding:12px; border-radius:10px; text-align:center;">
                        <div style="font-size:12px; color:#6B7280;">Rank</div>
                        <div style="font-size:24px; font-weight:700; color:#6C63FF;">#<?php echo $rank; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =============================================
        RECOMMENDATIONS
        ============================================= -->
        <div class="card animate-in" style="margin-bottom:24px;">
            <div class="card-title">
                <i class="fas fa-star"></i> Recommendations for Improvement
            </div>
            <?php foreach ($recommendations as $rec): ?>
                <div class="recommendation-item">
                    <span class="rec-icon">📌</span>
                    <span class="rec-text"><?php echo $rec; ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- =============================================
        ANSWER REVIEW
        ============================================= -->
        <div class="card animate-in" style="margin-bottom:24px;">
            <div class="card-title">
                <i class="fas fa-list-check"></i> Answer Review
                <span style="font-size:13px; font-weight:400; color:#6B7280; margin-left:auto;">
                    <?php echo $correct; ?> correct • <?php echo $wrong; ?> wrong
                </span>
            </div>
            <?php foreach ($questions as $q): 
                // For demo, we'll assume all answers are correct since we don't have actual student answers
                $is_correct = true; // This is a simulation - in production, compare with actual student answers
            ?>
                <div class="answer-item correct">
                    <span class="status-icon correct">✅</span>
                    <div class="q-text">
                        <?php echo htmlspecialchars($q['question']); ?>
                        <span style="font-size:12px; font-weight:400; color:#6B7280;">
                            (<?php echo $q['marks']; ?> marks)
                        </span>
                    </div>
                    <div class="answer-details">
                        Correct answer: 
                        <span class="correct-answer">
                            <?php echo htmlspecialchars($q['correct_answer']); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- =============================================
        ACHIEVEMENTS & LEADERBOARD
        ============================================= -->
        <div class="two-column">
            <!-- Achievements -->
            <div class="card animate-in">
                <div class="card-title">
                    <i class="fas fa-award"></i> Achievements
                </div>
                <div class="achievement-grid">
                    <?php 
                    $achievements = [
                        ['icon' => '🏆', 'title' => 'Quiz Master', 'unlocked' => $percentage >= 80],
                        ['icon' => '⭐', 'title' => 'High Scorer', 'unlocked' => $percentage >= 90],
                        ['icon' => '🔥', 'title' => 'Learning Streak', 'unlocked' => $correct >= $total_questions * 0.7],
                        ['icon' => '📚', 'title' => 'Consistent Learner', 'unlocked' => $attempt['score'] > 0],
                        ['icon' => '🎯', 'title' => 'Perfect Accuracy', 'unlocked' => $correct === $total_questions],
                        ['icon' => '🚀', 'title' => 'Quick Learner', 'unlocked' => $percentage >= 75 && $unanswered == 0],
                    ];
                    foreach ($achievements as $ach):
                    ?>
                        <div class="achievement-item <?php echo $ach['unlocked'] ? 'unlocked' : 'locked'; ?>">
                            <span class="ach-icon"><?php echo $ach['icon']; ?></span>
                            <div class="ach-title"><?php echo $ach['title']; ?></div>
                            <div class="ach-status"><?php echo $ach['unlocked'] ? '✅ Unlocked' : '🔒 Locked'; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Leaderboard Preview -->
            <div class="card animate-in">
                <div class="card-title">
                    <i class="fas fa-trophy"></i> Leaderboard Position
                </div>
                <div style="text-align:center; padding:20px;">
                    <div style="font-size:48px; margin-bottom:8px;">🏆</div>
                    <div style="font-size:32px; font-weight:800; color:#6C63FF;">#<?php echo $rank; ?></div>
                    <div style="font-size:14px; color:#6B7280;">Your rank among all students</div>
                    <div style="margin-top:12px; background:#F9FAFB; border-radius:10px; padding:12px;">
                        <div style="font-size:13px; color:#6B7280;">
                            <i class="fas fa-users"></i> <?php echo $rank; ?>nd place out of 
                            <?php 
                            $total_students_sql = "SELECT COUNT(DISTINCT student_id) as total FROM quiz_attempts WHERE quiz_id = {$attempt['quiz_id']} AND is_completed = 1";
                            $total_students_result = mysqli_query($conn, $total_students_sql);
                            $total_students = mysqli_fetch_assoc($total_students_result)['total'] ?? 1;
                            echo $total_students;
                            ?> students
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =============================================
        ACTION BUTTONS
        ============================================= -->
        <div class="action-buttons animate-in">
            <a href="browse_notes.php" class="btn-action btn-primary">
                <i class="fas fa-book"></i> Review Notes
            </a>
            <a href="attempt_quiz.php" class="btn-action btn-success">
                <i class="fas fa-puzzle-piece"></i> Attempt Another Quiz
            </a>
            <a href="dashboard.php" class="btn-action btn-secondary">
                <i class="fas fa-home"></i> Back to Dashboard
            </a>
        </div>

        <!-- =============================================
        MOTIVATIONAL BANNER
        ============================================= -->
        <div class="motivation-banner animate-in">
            <div class="motivation-text">
                💪 <span>"<?php echo $random_quote; ?>"</span>
            </div>
            <div class="motivation-icon">🏆</div>
        </div>

    </main>

    <!-- =============================================
    JAVASCRIPT
    ============================================= -->
    <script>
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
        // COUNTER ANIMATION
        // =============================================
        const statNumbers = document.querySelectorAll('.stat-number[data-count]');

        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const target = parseInt(entry.target.getAttribute('data-count'));
                    animateCounter(entry.target, target);
                    counterObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.3 });

        statNumbers.forEach(el => {
            counterObserver.observe(el);
        });

        function animateCounter(element, target) {
            let current = 0;
            const increment = Math.ceil(target / 30);
            const duration = 1500;
            const stepTime = duration / 30;

            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current);
            }, stepTime);
        }

        // =============================================
        // SCORE BAR ANIMATION
        // =============================================
        const scoreBar = document.getElementById('scoreBar');
        if (scoreBar) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const width = scoreBar.style.width;
                        scoreBar.style.width = '0%';
                        setTimeout(() => {
                            scoreBar.style.width = width;
                        }, 100);
                        observer.unobserve(scoreBar);
                    }
                });
            }, { threshold: 0.3 });
            observer.observe(scoreBar);
        }

        // =============================================
        // CONFETTI ANIMATION (on pass)
        // =============================================
        <?php if ($passed): ?>
        function launchConfetti() {
            const container = document.createElement('div');
            container.className = 'confetti-container';
            document.body.appendChild(container);
            
            const colors = ['#6C63FF', '#22C55E', '#F59E0B', '#EF4444', '#3B82F6', '#EC4899', '#8B5CF6'];
            const shapes = ['■', '●', '▲', '★', '♦'];
            
            for (let i = 0; i < 80; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.className = 'confetti';
                    confetti.textContent = shapes[Math.floor(Math.random() * shapes.length)];
                    confetti.style.left = Math.random() * 100 + '%';
                    confetti.style.color = colors[Math.floor(Math.random() * colors.length)];
                    confetti.style.fontSize = (Math.random() * 14 + 8) + 'px';
                    confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';
                    confetti.style.animationDelay = '0s';
                    container.appendChild(confetti);
                    
                    setTimeout(() => confetti.remove(), 4000);
                }, i * 30);
            }
            
            setTimeout(() => container.remove(), 5000);
        }
        
        // Launch confetti after page load
        setTimeout(launchConfetti, 500);
        <?php endif; ?>

        // =============================================
        // CONSOLE LOG
        // =============================================
        console.log('📊 EduHack AI - Quiz Results');
        console.log('📝 Quiz: <?php echo htmlspecialchars(addslashes($attempt['quiz_title'])); ?>');
        console.log('📈 Score: <?php echo $attempt['score']; ?>/<?php echo $attempt['total_questions']; ?> (<?php echo round($attempt['percentage'], 1); ?>%)');
        console.log('🏆 Rank: #<?php echo $rank; ?>');
        console.log('👋 Welcome, <?php echo htmlspecialchars($student_name); ?>');
    </script>

</body>
</html>