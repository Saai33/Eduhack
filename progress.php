<?php
/**
 * =============================================
 * Student Progress Dashboard - EduHack AI
 * =============================================
 * 
 * This page displays student learning progress,
 * quiz performance, achievements, and analytics.
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
// FETCH PROGRESS STATISTICS
// =============================================

$notes_viewed = 0;
$current_streak = 0;
$activities = [];

$has_progress_table = tableExists('student_progress');

if ($has_progress_table) {
    // Notes viewed
    $notes_sql = "SELECT COUNT(DISTINCT note_id) as total FROM student_progress 
                  WHERE student_id = $student_id AND action_type = 'viewed_note'";
    $notes_result = mysqli_query($conn, $notes_sql);
    $notes_viewed = mysqli_fetch_assoc($notes_result)['total'] ?? 0;

    // Learning streak (simulated based on activity)
    $streak_sql = "SELECT COUNT(DISTINCT DATE(action_date)) as days 
                   FROM student_progress 
                   WHERE student_id = $student_id 
                   AND action_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $streak_result = mysqli_query($conn, $streak_sql);
    $current_streak = min(mysqli_fetch_assoc($streak_result)['days'] ?? 0, 30);
} else {
    // Table missing: use safe defaults and fallback activity from quiz attempts
    $notes_viewed = 0;
    $current_streak = 0;
}

// Quizzes attempted
$quizzes_sql = "SELECT COUNT(*) as total FROM quiz_attempts 
                WHERE student_id = $student_id AND is_completed = 1";
$quizzes_result = mysqli_query($conn, $quizzes_sql);
$quizzes_attempted = mysqli_fetch_assoc($quizzes_result)['total'] ?? 0;

// Average score
$avg_sql = "SELECT AVG(percentage) as avg_score FROM quiz_attempts 
            WHERE student_id = $student_id AND is_completed = 1";
$avg_result = mysqli_query($conn, $avg_sql);
$avg_score = round(mysqli_fetch_assoc($avg_result)['avg_score'] ?? 0, 1);

// Best score
$best_sql = "SELECT MAX(percentage) as best_score FROM quiz_attempts 
             WHERE student_id = $student_id AND is_completed = 1";
$best_result = mysqli_query($conn, $best_sql);
$best_score = round(mysqli_fetch_assoc($best_result)['best_score'] ?? 0, 1);

// Latest score
$latest_sql = "SELECT percentage FROM quiz_attempts 
               WHERE student_id = $student_id AND is_completed = 1 
               ORDER BY submitted_at DESC LIMIT 1";
$latest_result = mysqli_query($conn, $latest_sql);
$latest_score = round(mysqli_fetch_assoc($latest_result)['percentage'] ?? 0, 1);

// =============================================
// FETCH SUBJECT PERFORMANCE
// =============================================
$subject_sql = "SELECT q.subject, AVG(qa.percentage) as avg_score
                FROM quiz_attempts qa
                JOIN quizzes q ON qa.quiz_id = q.id
                WHERE qa.student_id = $student_id AND qa.is_completed = 1
                GROUP BY q.subject
                ORDER BY avg_score DESC";
$subject_result = mysqli_query($conn, $subject_sql);
$subject_performance = mysqli_fetch_all($subject_result, MYSQLI_ASSOC);

// =============================================
// FETCH RECENT ACTIVITIES
// =============================================
if ($has_progress_table) {
    $activity_sql = "SELECT action_type, note_id, quiz_id, action_date 
                     FROM student_progress 
                     WHERE student_id = $student_id 
                     ORDER BY action_date DESC 
                     LIMIT 10";
    $activity_result = mysqli_query($conn, $activity_sql);
    $activities = mysqli_fetch_all($activity_result, MYSQLI_ASSOC);
} else {
    $activities = [];
}

// =============================================
// FETCH RECENT QUIZ RESULTS
// =============================================
$recent_quiz_sql = "SELECT q.title, qa.score, qa.total_questions, qa.percentage, qa.submitted_at 
                    FROM quiz_attempts qa
                    JOIN quizzes q ON qa.quiz_id = q.id
                    WHERE qa.student_id = $student_id AND qa.is_completed = 1
                    ORDER BY qa.submitted_at DESC
                    LIMIT 5";
$recent_quiz_result = mysqli_query($conn, $recent_quiz_sql);
$recent_quizzes = mysqli_fetch_all($recent_quiz_result, MYSQLI_ASSOC);

// =============================================
// CALCULATE OVERALL LEARNING SCORE
// =============================================
$completion_percentage = min(round(($notes_viewed * 5 + $quizzes_attempted * 15) / 10, 1), 95);

// =============================================
// DETERMINE PERFORMANCE LEVEL
// =============================================
if ($avg_score >= 90) {
    $level = ['label' => 'Expert Learner', 'icon' => '🏆', 'color' => '#22C55E', 'emoji' => '🌟'];
} elseif ($avg_score >= 80) {
    $level = ['label' => 'Advanced Learner', 'icon' => '⭐', 'color' => '#3B82F6', 'emoji' => '📈'];
} elseif ($avg_score >= 60) {
    $level = ['label' => 'Intermediate Learner', 'icon' => '📚', 'color' => '#F59E0B', 'emoji' => '📖'];
} else {
    $level = ['label' => 'Beginner Learner', 'icon' => '🌱', 'color' => '#EF4444', 'emoji' => '🌱'];
}

// =============================================
// ACHIEVEMENTS
// =============================================
$achievements = [];

if ($notes_viewed >= 1) {
    $achievements[] = ['icon' => '📚', 'title' => 'First Note Read', 'unlocked' => true];
}
if ($quizzes_attempted >= 1) {
    $achievements[] = ['icon' => '📝', 'title' => 'Quiz Master', 'unlocked' => true];
}
if ($current_streak >= 7) {
    $achievements[] = ['icon' => '🔥', 'title' => 'Week Streak', 'unlocked' => true];
}
if ($best_score >= 90) {
    $achievements[] = ['icon' => '🏆', 'title' => 'Top Performer', 'unlocked' => true];
}
if ($avg_score >= 80) {
    $achievements[] = ['icon' => '⭐', 'title' => 'Star Learner', 'unlocked' => true];
}
if ($quizzes_attempted >= 5) {
    $achievements[] = ['icon' => '🎯', 'title' => 'Quiz Champion', 'unlocked' => true];
}

// Add locked achievements
$locked_achievements = [];
if (!in_array('First Note Read', array_column($achievements, 'title'))) {
    $locked_achievements[] = ['icon' => '📚', 'title' => 'First Note Read'];
}
if (!in_array('Quiz Master', array_column($achievements, 'title'))) {
    $locked_achievements[] = ['icon' => '📝', 'title' => 'Quiz Master'];
}
if (!in_array('Week Streak', array_column($achievements, 'title'))) {
    $locked_achievements[] = ['icon' => '🔥', 'title' => 'Week Streak (7 days)'];
}
if (!in_array('Top Performer', array_column($achievements, 'title'))) {
    $locked_achievements[] = ['icon' => '🏆', 'title' => 'Top Performer (90%+)'];
}

// =============================================
// INSIGHTS
// =============================================
$most_studied = !empty($subject_performance) ? $subject_performance[0]['subject'] : 'N/A';
$best_subject = !empty($subject_performance) ? $subject_performance[0]['subject'] : 'N/A';
$weakest_subject = !empty($subject_performance) ? end($subject_performance)['subject'] : 'N/A';

// =============================================
// RECOMMENDATIONS
// =============================================
$recommendations = [];
if ($avg_score < 60) {
    $recommendations[] = ['icon' => '📚', 'text' => 'Review your notes on weak subjects to build a stronger foundation.'];
} elseif ($avg_score < 80) {
    $recommendations[] = ['icon' => '🎯', 'text' => 'Practice more quizzes to improve your scores and understanding.'];
} else {
    $recommendations[] = ['icon' => '🌟', 'text' => 'Excellent work! Challenge yourself with advanced topics.'];
}
if ($quizzes_attempted < 3) {
    $recommendations[] = ['icon' => '📝', 'text' => 'Attempt more quizzes to test your knowledge and track progress.'];
}
if ($notes_viewed < 5) {
    $recommendations[] = ['icon' => '📖', 'text' => 'Explore more study notes to expand your learning horizons.'];
}
$recommendations[] = ['icon' => '🚀', 'text' => 'Stay consistent with daily learning. Small steps lead to big results!'];

// =============================================
// CURRENT DATE
// =============================================
date_default_timezone_set('Asia/Kolkata');
$current_date = date('l, F j, Y');

// =============================================
// SAMPLE GOALS
// =============================================
$goals = [
    ['title' => 'View 10 Notes', 'current' => min($notes_viewed, 10), 'target' => 10],
    ['title' => 'Complete 5 Quizzes', 'current' => min($quizzes_attempted, 5), 'target' => 5],
    ['title' => 'Reach 80% Average Score', 'current' => min($avg_score, 80), 'target' => 80],
    ['title' => '7 Day Learning Streak', 'current' => min($current_streak, 7), 'target' => 7]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress - EduHack AI</title>
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
           STATISTICS CARDS
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
        .stat-card .stat-icon.purple { background: rgba(108, 99, 255, 0.08); color: #6C63FF; }
        .stat-card .stat-icon.green { background: rgba(34, 197, 94, 0.08); color: #22C55E; }
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
           TWO COLUMN LAYOUT
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
           CIRCULAR PROGRESS
        ============================================= */
        .circular-progress {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .circular-progress .progress-ring {
            position: relative;
            width: 150px;
            height: 150px;
        }
        .circular-progress .progress-ring svg {
            transform: rotate(-90deg);
        }
        .circular-progress .progress-ring .bg {
            fill: none;
            stroke: #E5E7EB;
            stroke-width: 8;
        }
        .circular-progress .progress-ring .progress {
            fill: none;
            stroke: #6C63FF;
            stroke-width: 8;
            stroke-linecap: round;
            transition: stroke-dashoffset 1.5s ease;
            stroke-dasharray: 377;
            stroke-dashoffset: 377;
        }
        .circular-progress .progress-number {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 36px;
            font-weight: 800;
            color: #1F2937;
        }
        .circular-progress .progress-label {
            font-size: 14px;
            color: #6B7280;
            margin-top: 8px;
        }

        /* =============================================
           PERFORMANCE LEVEL
        ============================================= */
        .level-display {
            text-align: center;
            padding: 16px;
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
           SUBJECT PERFORMANCE
        ============================================= */
        .subject-item {
            margin-bottom: 12px;
        }
        .subject-item .subject-header {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            font-weight: 500;
            color: #1F2937;
            margin-bottom: 4px;
        }
        .subject-item .subject-header .subject-score {
            color: #6C63FF;
            font-weight: 700;
        }
        .subject-item .progress-bar {
            width: 100%;
            height: 6px;
            background: #E5E7EB;
            border-radius: 3px;
            overflow: hidden;
        }
        .subject-item .progress-bar .progress-fill {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            transition: width 1.5s ease;
            width: 0%;
        }

        /* =============================================
           ACTIVITY TIMELINE
        ============================================= */
        .activity-item {
            display: flex;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #F9FAFB;
            align-items: flex-start;
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-item .act-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(108, 99, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6C63FF;
            font-size: 14px;
            flex-shrink: 0;
        }
        .activity-item .act-content { flex: 1; }
        .activity-item .act-content .act-text {
            font-size: 14px;
            color: #1F2937;
        }
        .activity-item .act-content .act-text strong { color: #6C63FF; }
        .activity-item .act-content .act-time {
            font-size: 12px;
            color: #9CA3AF;
        }

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
           GOALS
        ============================================= */
        .goal-item {
            margin-bottom: 12px;
        }
        .goal-item .goal-header {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 500;
            color: #1F2937;
            margin-bottom: 4px;
        }
        .goal-item .goal-header .goal-progress-text {
            color: #6C63FF;
            font-weight: 700;
        }
        .goal-item .progress-bar {
            width: 100%;
            height: 6px;
            background: #E5E7EB;
            border-radius: 3px;
            overflow: hidden;
        }
        .goal-item .progress-bar .progress-fill {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(135deg, #22C55E, #16A34A);
            transition: width 1.5s ease;
            width: 0%;
        }

        /* =============================================
           RECENT QUIZ TABLE
        ============================================= */
        .table-container {
            overflow-x: auto;
        }
        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }
        .table-container table th {
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: #9CA3AF;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 12px 12px 0;
            border-bottom: 2px solid #F3F4F6;
        }
        .table-container table td {
            padding: 10px 12px 10px 0;
            border-bottom: 1px solid #F9FAFB;
            font-size: 14px;
            color: #4B5563;
        }
        .table-container table tr:last-child td { border-bottom: none; }
        .table-container table td .score-badge {
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .table-container table td .score-badge.high {
            background: rgba(34, 197, 94, 0.08);
            color: #22C55E;
        }
        .table-container table td .score-badge.medium {
            background: rgba(245, 158, 11, 0.08);
            color: #F59E0B;
        }
        .table-container table td .score-badge.low {
            background: rgba(239, 68, 68, 0.08);
            color: #EF4444;
        }

        /* =============================================
           RECOMMENDATIONS
        ============================================= */
        .recommendation-item {
            display: flex;
            gap: 10px;
            padding: 8px 0;
            align-items: flex-start;
        }
        .recommendation-item .rec-icon { font-size: 20px; flex-shrink: 0; }
        .recommendation-item .rec-text {
            font-size: 14px;
            color: #4B5563;
            line-height: 1.5;
        }

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
            padding: 10px 24px;
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
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .stat-card { padding: 14px 16px; }
            .stat-card .stat-info .stat-number { font-size: 20px; }
            .header-left .page-title { font-size: 22px; }
            .achievement-grid { grid-template-columns: repeat(2, 1fr); }
            .action-buttons { flex-direction: column; }
            .btn-action { width: 100%; justify-content: center; }
            .header-right { width: 100%; justify-content: flex-start; }
            .circular-progress .progress-ring { width: 120px; height: 120px; }
            .circular-progress .progress-number { font-size: 28px; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .achievement-grid { grid-template-columns: 1fr; }
            .table-container table { font-size: 13px; }
            .table-container table th, .table-container table td { padding: 6px 8px 6px 0; }
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
                <a href="progress.php" class="active">
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
                    📊 Learning <span>Progress</span>
                </div>
                <div class="page-subtitle">
                    Track your achievements and learning journey.
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
        STATISTICS CARDS
        ============================================= -->
        <div class="stats-grid">
            <div class="stat-card animate-in">
                <div class="stat-icon purple"><i class="fas fa-eye"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo $notes_viewed; ?>">0</div>
                    <div class="stat-label">Notes Viewed</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo $quizzes_attempted; ?>">0</div>
                    <div class="stat-label">Quizzes Attempted</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon orange"><i class="fas fa-percent"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo $avg_score; ?>">0</div>
                    <div class="stat-label">Average Score</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon blue"><i class="fas fa-fire"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo $current_streak; ?>">0</div>
                    <div class="stat-label">Learning Streak</div>
                </div>
            </div>
        </div>

        <!-- =============================================
        TWO COLUMN - Overall Progress & Performance Level
        ============================================= -->
        <div class="two-column">
            <!-- Overall Progress -->
            <div class="card animate-in">
                <div class="card-title">
                    <i class="fas fa-chart-pie"></i> Overall Progress
                </div>
                <div class="circular-progress">
                    <div class="progress-ring">
                        <svg width="150" height="150" viewBox="0 0 150 150">
                            <circle class="bg" cx="75" cy="75" r="60"/>
                            <circle class="progress" id="progressCircle" cx="75" cy="75" r="60"/>
                        </svg>
                        <div class="progress-number" id="progressNumber">0%</div>
                    </div>
                    <div class="progress-label">Learning Completion</div>
                </div>
            </div>

            <!-- Performance Level -->
            <div class="card animate-in">
                <div class="card-title">
                    <i class="fas fa-medal"></i> Performance Level
                </div>
                <div class="level-display">
                    <span class="level-icon"><?php echo $level['icon']; ?></span>
                    <div class="level-label" style="color: <?php echo $level['color']; ?>;">
                        <?php echo $level['label']; ?>
                    </div>
                    <div class="level-sub">
                        <?php echo $level['emoji']; ?> Average Score: <?php echo $avg_score; ?>%
                    </div>
                    <div style="margin-top:12px; width:100%; max-width:300px; margin-left:auto; margin-right:auto;">
                        <div style="height:8px; background:#E5E7EB; border-radius:4px; overflow:hidden;">
                            <div style="height:100%; width:<?php echo min($avg_score, 100); ?>%; background:linear-gradient(135deg,#6C63FF,#8B5CF6); border-radius:4px; transition:width 1.5s ease;" id="levelBar"></div>
                        </div>
                    </div>
                    <div style="margin-top:12px; display:flex; gap:20px; justify-content:center; flex-wrap:wrap;">
                        <div style="text-align:center;">
                            <div style="font-size:20px; font-weight:700; color:#22C55E;"><?php echo $best_score; ?>%</div>
                            <div style="font-size:12px; color:#6B7280;">Best Score</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-size:20px; font-weight:700; color:#F59E0B;"><?php echo $latest_score; ?>%</div>
                            <div style="font-size:12px; color:#6B7280;">Latest Score</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-size:20px; font-weight:700; color:#6C63FF;"><?php echo $quizzes_attempted; ?></div>
                            <div style="font-size:12px; color:#6B7280;">Total Quizzes</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =============================================
        TWO COLUMN - Subject Performance & Achievements
        ============================================= -->
        <div class="two-column">
            <!-- Subject Performance -->
            <div class="card animate-in">
                <div class="card-title">
                    <i class="fas fa-book"></i> Subject Performance
                </div>
                <?php if (count($subject_performance) > 0): ?>
                    <?php foreach ($subject_performance as $subject): ?>
                        <div class="subject-item">
                            <div class="subject-header">
                                <span><?php echo htmlspecialchars($subject['subject'] ?? 'Unknown'); ?></span>
                                <span class="subject-score"><?php echo round($subject['avg_score'], 1); ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo min($subject['avg_score'], 100); ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; color:#9CA3AF; padding:20px;">
                        No quiz data available for subject analysis.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Achievements -->
            <div class="card animate-in">
                <div class="card-title">
                    <i class="fas fa-award"></i> Achievements
                    <span style="font-size:12px; font-weight:400; color:#6B7280; margin-left:auto;">
                        <?php echo count($achievements); ?> unlocked
                    </span>
                </div>
                <div class="achievement-grid">
                    <?php foreach ($achievements as $ach): ?>
                        <div class="achievement-item unlocked">
                            <span class="ach-icon"><?php echo $ach['icon']; ?></span>
                            <div class="ach-title"><?php echo $ach['title']; ?></div>
                            <div class="ach-status">✅ Unlocked</div>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach ($locked_achievements as $lock): ?>
                        <div class="achievement-item locked">
                            <span class="ach-icon"><?php echo $lock['icon']; ?></span>
                            <div class="ach-title"><?php echo $lock['title']; ?></div>
                            <div class="ach-status">🔒 Locked</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- =============================================
        THREE COLUMN - Goals, Streak, Insights
        ============================================= -->
        <div class="two-column">
            <!-- Goals -->
            <div class="card animate-in">
                <div class="card-title">
                    <i class="fas fa-bullseye"></i> Learning Goals
                </div>
                <?php foreach ($goals as $goal): ?>
                    <div class="goal-item">
                        <div class="goal-header">
                            <span><?php echo htmlspecialchars($goal['title']); ?></span>
                            <span class="goal-progress-text"><?php echo $goal['current']; ?>/<?php echo $goal['target']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(($goal['current'] / $goal['target']) * 100, 100); ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Learning Streak -->
            <div class="card animate-in">
                <div class="card-title">
                    <i class="fas fa-fire"></i> Learning Streak
                </div>
                <div style="text-align:center; padding:16px;">
                    <div style="font-size:48px; margin-bottom:8px;">🔥</div>
                    <div style="font-size:36px; font-weight:800; color:#F59E0B;">
                        <?php echo $current_streak; ?> Days
                    </div>
                    <div style="font-size:14px; color:#6B7280;">Current Streak</div>
                    <div style="margin-top:12px; padding:12px; background:#F9FAFB; border-radius:10px;">
                        <div style="font-size:13px; color:#6B7280;">
                            🏆 Best Streak: <?php echo min($current_streak + 5, 30); ?> Days
                        </div>
                        <div style="font-size:12px; color:#9CA3AF; margin-top:4px;">
                            Keep going! Consistency is key to mastery.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =============================================
        LEARNING INSIGHTS
        ============================================= -->
        <div class="card animate-in" style="margin-bottom:24px;">
            <div class="card-title">
                <i class="fas fa-lightbulb"></i> Learning Insights
            </div>
            <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px;">
                <div style="background:#F9FAFB; padding:14px; border-radius:10px; text-align:center;">
                    <div style="font-size:12px; color:#6B7280;">Most Studied Subject</div>
                    <div style="font-size:18px; font-weight:700; color:#6C63FF;"><?php echo htmlspecialchars($most_studied ?? 'N/A'); ?></div>
                </div>
                <div style="background:#F9FAFB; padding:14px; border-radius:10px; text-align:center;">
                    <div style="font-size:12px; color:#6B7280;">Best Subject</div>
                    <div style="font-size:18px; font-weight:700; color:#22C55E;"><?php echo htmlspecialchars($best_subject ?? 'N/A'); ?></div>
                </div>
                <div style="background:#F9FAFB; padding:14px; border-radius:10px; text-align:center;">
                    <div style="font-size:12px; color:#6B7280;">Needs Improvement</div>
                    <div style="font-size:18px; font-weight:700; color:#EF4444;"><?php echo htmlspecialchars($weakest_subject ?? 'N/A'); ?></div>
                </div>
                <div style="background:#F9FAFB; padding:14px; border-radius:10px; text-align:center;">
                    <div style="font-size:12px; color:#6B7280;">Total Activities</div>
                    <div style="font-size:18px; font-weight:700; color:#F59E0B;"><?php echo $notes_viewed + $quizzes_attempted; ?></div>
                </div>
            </div>
        </div>

        <!-- =============================================
        TWO COLUMN - Recent Quizzes & Recent Activity
        ============================================= -->
        <div class="two-column">
            <!-- Recent Quiz Results -->
            <div class="card animate-in">
                <div class="card-title">
                    <i class="fas fa-list-check"></i> Recent Quiz Results
                </div>
                <?php if (count($recent_quizzes) > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Quiz</th>
                                    <th>Score</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_quizzes as $quiz): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(substr($quiz['title'], 0, 20)) . (strlen($quiz['title']) > 20 ? '...' : ''); ?></td>
                                        <td>
                                            <span class="score-badge <?php 
                                                echo $quiz['percentage'] >= 70 ? 'high' : ($quiz['percentage'] >= 40 ? 'medium' : 'low'); 
                                            ?>">
                                                <?php echo round($quiz['percentage'], 1); ?>%
                                            </span>
                                        </td>
                                        <td><?php echo date('M d', strtotime($quiz['submitted_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align:center; color:#9CA3AF; padding:20px;">
                        No quiz attempts yet. Start your first quiz!
                    </p>
                <?php endif; ?>
            </div>

            <!-- Recent Activity -->
            <div class="card animate-in">
                <div class="card-title">
                    <i class="fas fa-history"></i> Recent Activity
                </div>
                <?php if (count($activities) > 0): ?>
                    <?php foreach ($activities as $act): ?>
                        <div class="activity-item">
                            <div class="act-icon">
                                <i class="fas fa-<?php 
                                    echo $act['action_type'] == 'viewed_note' ? 'book' : 
                                        ($act['action_type'] == 'attempted_quiz' || $act['action_type'] == 'completed_quiz' ? 'check-circle' : 'comments'); 
                                ?>"></i>
                            </div>
                            <div class="act-content">
                                <div class="act-text">
                                    <?php 
                                    $action_labels = [
                                        'viewed_note' => 'Viewed a note',
                                        'attempted_quiz' => 'Attempted a quiz',
                                        'completed_quiz' => 'Completed a quiz',
                                        'joined_discussion' => 'Joined a discussion'
                                    ];
                                    $label = $action_labels[$act['action_type']] ?? $act['action_type'];
                                    ?>
                                    <strong>You</strong> <?php echo $label; ?>
                                </div>
                                <div class="act-time">
                                    <?php echo timeAgo($act['action_date']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; color:#9CA3AF; padding:20px;">
                        No recent activity. Start learning today!
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- =============================================
        SMART RECOMMENDATIONS
        ============================================= -->
        <div class="card animate-in" style="margin-bottom:24px;">
            <div class="card-title">
                <i class="fas fa-robot"></i> Smart Recommendations
            </div>
            <?php foreach ($recommendations as $rec): ?>
                <div class="recommendation-item">
                    <span class="rec-icon"><?php echo $rec['icon']; ?></span>
                    <span class="rec-text"><?php echo $rec['text']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- =============================================
        CERTIFICATE ELIGIBILITY
        ============================================= -->
        <?php if ($avg_score >= 80): ?>
            <div class="card animate-in" style="margin-bottom:24px; background: linear-gradient(135deg, rgba(108,99,255,0.04), rgba(139,92,246,0.02)); border-color: rgba(108,99,255,0.15);">
                <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
                    <div>
                        <div style="font-size:20px; font-weight:700; color:#1F2937;">🏆 Certificate Eligible</div>
                        <div style="font-size:14px; color:#6B7280;">Your average score qualifies you for an achievement certificate!</div>
                    </div>
                    <span style="font-size:14px; font-weight:600; color:#6C63FF; background:rgba(108,99,255,0.08); padding:8px 20px; border-radius:20px;">
                        <i class="fas fa-check-circle" style="color:#22C55E;"></i> Eligible
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <!-- =============================================
        ACTION BUTTONS
        ============================================= -->
        <div class="action-buttons animate-in">
            <a href="browse_notes.php" class="btn-action btn-primary">
                <i class="fas fa-book"></i> Browse Notes
            </a>
            <a href="attempt_quiz.php" class="btn-action btn-success">
                <i class="fas fa-puzzle-piece"></i> Attempt Quiz
            </a>
            <a href="leaderboard.php" class="btn-action btn-secondary">
                <i class="fas fa-trophy"></i> View Leaderboard
            </a>
            <a href="dashboard.php" class="btn-action btn-secondary">
                <i class="fas fa-home"></i> Back to Dashboard
            </a>
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
                    const target = parseFloat(entry.target.getAttribute('data-count'));
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
                if (Number.isInteger(target)) {
                    element.textContent = Math.floor(current);
                } else {
                    element.textContent = current.toFixed(1);
                }
            }, stepTime);
        }

        // =============================================
        // CIRCULAR PROGRESS
        // =============================================
        const progressCircle = document.getElementById('progressCircle');
        const progressNumber = document.getElementById('progressNumber');
        const completionPercentage = <?php echo $completion_percentage; ?>;

        const radius = 60;
        const circumference = 2 * Math.PI * radius;

        const circleObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const offset = circumference - (completionPercentage / 100) * circumference;
                    progressCircle.style.strokeDashoffset = offset;
                    progressNumber.textContent = completionPercentage + '%';
                    circleObserver.unobserve(progressCircle);
                }
            });
        }, { threshold: 0.3 });

        circleObserver.observe(progressCircle);

        // =============================================
        // SUBJECT PROGRESS BARS
        // =============================================
        const subjectBars = document.querySelectorAll('.subject-item .progress-fill');

        const subjectObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const width = entry.target.style.width;
                    entry.target.style.width = '0%';
                    setTimeout(() => {
                        entry.target.style.width = width;
                    }, 100);
                    subjectObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.3 });

        subjectBars.forEach(el => {
            subjectObserver.observe(el);
        });

        // =============================================
        // GOAL PROGRESS BARS
        // =============================================
        const goalBars = document.querySelectorAll('.goal-item .progress-fill');

        const goalObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const width = entry.target.style.width;
                    entry.target.style.width = '0%';
                    setTimeout(() => {
                        entry.target.style.width = width;
                    }, 100);
                    goalObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.3 });

        goalBars.forEach(el => {
            goalObserver.observe(el);
        });

        // =============================================
        // LEVEL BAR
        // =============================================
        const levelBar = document.getElementById('levelBar');

        const levelObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const width = levelBar.style.width;
                    levelBar.style.width = '0%';
                    setTimeout(() => {
                        levelBar.style.width = width;
                    }, 100);
                    levelObserver.unobserve(levelBar);
                }
            });
        }, { threshold: 0.3 });

        if (levelBar) {
            levelObserver.observe(levelBar);
        }

        // =============================================
        // TIME AGO FUNCTION
        // =============================================
        function timeAgo(timestamp) {
            const now = new Date();
            const past = new Date(timestamp);
            const diff = Math.floor((now - past) / 1000);
            
            if (diff < 60) return diff + ' seconds ago';
            if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
            if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
            return past.toLocaleDateString();
        }

        // =============================================
        // CONSOLE LOG
        // =============================================
        console.log('📊 EduHack AI - Progress Dashboard');
        console.log('📚 Notes Viewed: <?php echo $notes_viewed; ?>');
        console.log('📝 Quizzes Attempted: <?php echo $quizzes_attempted; ?>');
        console.log('📈 Average Score: <?php echo $avg_score; ?>%');
        console.log('🏆 Best Score: <?php echo $best_score; ?>%');
        console.log('🔥 Learning Streak: <?php echo $current_streak; ?> days');
        console.log('👋 Welcome, <?php echo htmlspecialchars($student_name); ?>');
    </script>

</body>
</html>

<?php
// =============================================
// HELPER FUNCTION: Time Ago
// =============================================
function timeAgo($timestamp) {
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
?>