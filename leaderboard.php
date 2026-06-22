<?php
/**
 * =============================================
 * Student Leaderboard - EduHack AI
 * =============================================
 * 
 * This page displays student rankings, achievements,
 * and competitive learning statistics.
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
// GET FILTERS
// =============================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$time_period = isset($_GET['period']) ? $_GET['period'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'score';

// =============================================
// BUILD LEADERBOARD QUERY
// =============================================
$date_filter = '';
if ($time_period == 'month') {
    $date_filter = "AND qa.submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($time_period == 'week') {
    $date_filter = "AND qa.submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
}

$order_by = match($sort_by) {
    'average' => 'avg_score DESC',
    'quizzes' => 'quiz_count DESC',
    default => 'total_score DESC'
};

$leaderboard_sql = "SELECT 
                        u.id,
                        u.full_name,
                        u.profile_image,
                        COALESCE(SUM(qa.score), 0) as total_score,
                        COUNT(qa.id) as quiz_count,
                        COALESCE(AVG(qa.percentage), 0) as avg_score,
                        MAX(qa.percentage) as best_score,
                        COALESCE(SUM(CASE WHEN qa.percentage >= q.passing_score THEN 1 ELSE 0 END), 0) as quizzes_passed
                    FROM users u
                    LEFT JOIN quiz_attempts qa ON u.id = qa.student_id AND qa.is_completed = 1 $date_filter
                    LEFT JOIN quizzes q ON qa.quiz_id = q.id
                    WHERE u.role = 'student'
                    GROUP BY u.id
                    HAVING quiz_count > 0 OR total_score > 0
                    ORDER BY $order_by";

if (!empty($search)) {
    $leaderboard_sql = "SELECT * FROM ($leaderboard_sql) as lb WHERE full_name LIKE '%$search%'";
}

$leaderboard_result = mysqli_query($conn, $leaderboard_sql);
$leaderboard = mysqli_fetch_all($leaderboard_result, MYSQLI_ASSOC);

// =============================================
// FIND CURRENT STUDENT RANK
// =============================================
$current_rank = 0;
$current_student_data = null;
foreach ($leaderboard as $index => $student) {
    if ($student['id'] == $student_id) {
        $current_rank = $index + 1;
        $current_student_data = $student;
        break;
    }
}

// =============================================
// STATISTICS
// =============================================
$total_participants = count($leaderboard);
$highest_score = !empty($leaderboard) ? max(array_column($leaderboard, 'total_score')) : 0;
$avg_all_score = $total_participants > 0 ? array_sum(array_column($leaderboard, 'avg_score')) / $total_participants : 0;
$active_learners = count(array_filter($leaderboard, function($s) { return $s['quiz_count'] > 0; }));

// =============================================
// TOP 3 STUDENTS
// =============================================
$top_3 = array_slice($leaderboard, 0, 3);

// =============================================
// ACHIEVEMENT BADGES
// =============================================
$badges = [
    ['icon' => '🏆', 'title' => 'Top Performer', 'color' => '#FFD700'],
    ['icon' => '🔥', 'title' => 'Learning Streak Champion', 'color' => '#FF6B35'],
    ['icon' => '⭐', 'title' => 'Consistent Learner', 'color' => '#6C63FF'],
    ['icon' => '📚', 'title' => 'Knowledge Master', 'color' => '#22C55E'],
    ['icon' => '🎯', 'title' => 'Quiz Expert', 'color' => '#3B82F6']
];

// =============================================
// MOTIVATIONAL MESSAGES
// =============================================
$motivation_messages = [];
if ($current_rank > 0) {
    if ($current_rank <= 3) {
        $motivation_messages[] = "🏆 Amazing work! You're in the top 3! Keep it up!";
    } elseif ($current_rank <= 10) {
        $motivation_messages[] = "🌟 You're in the top 10! Stay consistent to climb higher!";
    } else {
        $next_rank = max(1, $current_rank - 1);
        $points_needed = 0;
        if ($next_rank <= count($leaderboard) && $next_rank > 0) {
            $next_student = $leaderboard[$next_rank - 1] ?? null;
            if ($next_student) {
                $points_needed = $next_student['total_score'] - ($current_student_data['total_score'] ?? 0);
            }
        }
        if ($points_needed > 0) {
            $motivation_messages[] = "📈 You're only $points_needed points away from Rank #$next_rank!";
        } else {
            $motivation_messages[] = "🚀 Complete one more quiz to improve your rank!";
        }
    }
    if (($current_student_data['quiz_count'] ?? 0) == 0) {
        $motivation_messages[] = "🎯 Start your first quiz to appear on the leaderboard!";
    }
}
if (empty($motivation_messages)) {
    $motivation_messages[] = "💪 Every quiz brings you closer to the top!";
}

// =============================================
// WEEKLY CHALLENGE
// =============================================
$challenges = [
    ['icon' => '📚', 'title' => 'View 5 Notes', 'current' => min($current_student_data['quiz_count'] ?? 0 * 2, 5), 'target' => 5],
    ['icon' => '📝', 'title' => 'Complete 3 Quizzes', 'current' => min($current_student_data['quiz_count'] ?? 0, 3), 'target' => 3],
    ['icon' => '🎯', 'title' => 'Score Above 80%', 'current' => min(round($current_student_data['avg_score'] ?? 0 / 10, 1) * 10, 80), 'target' => 80]
];

// =============================================
// INSIGHTS
// =============================================
$top_subject_sql = "SELECT q.subject, COUNT(qa.id) as attempts 
                    FROM quiz_attempts qa
                    JOIN quizzes q ON qa.quiz_id = q.id
                    WHERE qa.is_completed = 1
                    GROUP BY q.subject
                    ORDER BY attempts DESC
                    LIMIT 1";
$top_subject_result = mysqli_query($conn, $top_subject_sql);
$top_subject = mysqli_fetch_assoc($top_subject_result)['subject'] ?? 'N/A';

// =============================================
// CURRENT DATE
// =============================================
date_default_timezone_set('Asia/Kolkata');
$current_date = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - EduHack AI</title>
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
        .stat-card .stat-icon.gold { background: rgba(255, 215, 0, 0.15); color: #FFD700; }
        .stat-card .stat-icon.green { background: rgba(34, 197, 94, 0.08); color: #22C55E; }
        .stat-card .stat-icon.orange { background: rgba(245, 158, 11, 0.08); color: #F59E0B; }
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
           FILTER BAR
        ============================================= */
        .filter-bar {
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .filter-bar .filter-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex: 1;
            min-width: 120px;
        }
        .filter-bar .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #6B7280;
            white-space: nowrap;
        }
        .filter-bar .filter-group input,
        .filter-bar .filter-group select {
            padding: 7px 12px;
            border: 2px solid #E5E7EB;
            border-radius: 10px;
            font-size: 13px;
            color: #1F2937;
            background: white;
            transition: all 0.3s ease;
            width: 100%;
        }
        .filter-bar .filter-group input:focus,
        .filter-bar .filter-group select:focus {
            outline: none;
            border-color: #6C63FF;
            box-shadow: 0 0 0 4px rgba(108, 99, 255, 0.04);
        }
        .filter-actions {
            display: flex;
            gap: 8px;
        }
        .btn-filter {
            padding: 7px 18px;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-filter-primary {
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            color: white;
        }
        .btn-filter-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(108, 99, 255, 0.3);
        }
        .btn-filter-secondary {
            background: #F3F4F6;
            color: #6B7280;
        }
        .btn-filter-secondary:hover {
            background: #E5E7EB;
        }

        /* =============================================
           PODIUM SECTION
        ============================================= */
        .podium-section {
            margin-bottom: 24px;
            animation: fadeInUp 0.6s ease;
        }
        .podium {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 20px;
            padding: 20px 0;
            background: white;
            border-radius: 20px;
            border: 1px solid #F3F4F6;
            min-height: 280px;
        }
        .podium-item {
            text-align: center;
            padding: 16px 20px;
            border-radius: 16px;
            transition: all 0.4s ease;
            width: 160px;
        }
        .podium-item:hover {
            transform: translateY(-8px) scale(1.02);
        }
        .podium-item .podium-rank {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .podium-item .podium-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            margin: 0 auto 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .podium-item .podium-avatar.gold { background: linear-gradient(135deg, #FFD700, #F59E0B); }
        .podium-item .podium-avatar.silver { background: linear-gradient(135deg, #C0C0C0, #9CA3AF); }
        .podium-item .podium-avatar.bronze { background: linear-gradient(135deg, #CD7F32, #D97706); }
        .podium-item .podium-name {
            font-size: 16px;
            font-weight: 700;
            color: #1F2937;
        }
        .podium-item .podium-score {
            font-size: 14px;
            color: #6B7280;
        }
        .podium-item .podium-badge {
            font-size: 24px;
            display: block;
            margin-top: 4px;
            animation: floatPodium 3s ease-in-out infinite;
        }
        .podium-item.rank-1 { padding-bottom: 30px; }
        .podium-item.rank-1 .podium-avatar { width: 80px; height: 80px; font-size: 32px; }
        .podium-item.rank-1 .podium-badge { font-size: 32px; }
        .podium-item.rank-2 { padding-bottom: 20px; }
        .podium-item.rank-3 { padding-bottom: 10px; }

        @keyframes floatPodium {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-8px) rotate(5deg); }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* =============================================
           CURRENT STUDENT CARD
        ============================================= */
        .current-student-card {
            background: linear-gradient(135deg, rgba(108, 99, 255, 0.04), rgba(139, 92, 246, 0.02));
            border: 1px solid rgba(108, 99, 255, 0.12);
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .current-student-card .rank-display {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .current-student-card .rank-display .rank-number {
            font-size: 36px;
            font-weight: 800;
            color: #6C63FF;
        }
        .current-student-card .rank-display .rank-label {
            font-size: 14px;
            color: #6B7280;
        }
        .current-student-card .rank-details {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }
        .current-student-card .rank-details .detail-item {
            text-align: center;
        }
        .current-student-card .rank-details .detail-item .detail-value {
            font-size: 20px;
            font-weight: 700;
            color: #1F2937;
        }
        .current-student-card .rank-details .detail-item .detail-label {
            font-size: 12px;
            color: #6B7280;
        }

        /* =============================================
           TABLE CONTAINER
        ============================================= */
        .table-container {
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            overflow: hidden;
            margin-bottom: 24px;
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
            padding: 14px 18px;
            border-bottom: 2px solid #F3F4F6;
            background: #F9FAFB;
        }
        .table-container table td {
            padding: 14px 18px;
            border-bottom: 1px solid #F9FAFB;
            font-size: 14px;
            color: #4B5563;
            vertical-align: middle;
        }
        .table-container table tr:hover td {
            background: #F9FAFB;
        }
        .table-container table tr:last-child td {
            border-bottom: none;
        }
        .table-container table tr.current-user {
            background: rgba(108, 99, 255, 0.04);
        }
        .table-container table tr.current-user td {
            border-bottom-color: rgba(108, 99, 255, 0.15);
        }
        .table-container table tr.current-user td:first-child {
            border-left: 4px solid #6C63FF;
        }

        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-weight: 700;
            font-size: 14px;
        }
        .rank-badge.gold { background: rgba(255, 215, 0, 0.15); color: #FFD700; }
        .rank-badge.silver { background: rgba(192, 192, 192, 0.15); color: #9CA3AF; }
        .rank-badge.bronze { background: rgba(205, 127, 50, 0.15); color: #CD7F32; }
        .rank-badge.normal { background: rgba(108, 99, 255, 0.08); color: #6C63FF; }

        .avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
        }

        .badge-icon {
            font-size: 20px;
            display: inline-block;
            animation: floatBadge 2s ease-in-out infinite;
        }
        @keyframes floatBadge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        /* =============================================
           BADGES GRID
        ============================================= */
        .badges-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .badge-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #F3F4F6;
            padding: 16px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .badge-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.04);
            border-color: rgba(108, 99, 255, 0.1);
        }
        .badge-card .badge-icon {
            font-size: 32px;
            display: block;
            margin-bottom: 4px;
        }
        .badge-card .badge-title {
            font-size: 12px;
            font-weight: 600;
            color: #1F2937;
        }

        /* =============================================
           MOTIVATION PANEL
        ============================================= */
        .motivation-panel {
            background: linear-gradient(135deg, rgba(108, 99, 255, 0.04), rgba(139, 92, 246, 0.02));
            border: 1px solid rgba(108, 99, 255, 0.08);
            border-radius: 12px;
            padding: 14px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .motivation-panel .motivation-icon {
            font-size: 28px;
            flex-shrink: 0;
        }
        .motivation-panel .motivation-text {
            flex: 1;
            font-size: 15px;
            color: #4B5563;
        }
        .motivation-panel .motivation-text strong {
            color: #6C63FF;
        }

        /* =============================================
           INSIGHTS GRID
        ============================================= */
        .insights-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .insight-card {
            background: white;
            padding: 16px 20px;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            transition: all 0.3s ease;
        }
        .insight-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.03);
        }
        .insight-card .insight-icon { font-size: 24px; margin-bottom: 4px; }
        .insight-card .insight-label {
            font-size: 12px;
            color: #6B7280;
            font-weight: 500;
        }
        .insight-card .insight-value {
            font-size: 16px;
            font-weight: 700;
            color: #1F2937;
        }

        /* =============================================
           WEEKLY CHALLENGE
        ============================================= */
        .challenge-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #F9FAFB;
        }
        .challenge-item:last-child { border-bottom: none; }
        .challenge-item .challenge-icon { font-size: 24px; flex-shrink: 0; }
        .challenge-item .challenge-info { flex: 1; }
        .challenge-item .challenge-info .challenge-title {
            font-size: 14px;
            font-weight: 600;
            color: #1F2937;
        }
        .challenge-item .challenge-info .challenge-progress {
            font-size: 12px;
            color: #6B7280;
        }
        .challenge-item .challenge-bar {
            width: 80px;
            height: 6px;
            background: #E5E7EB;
            border-radius: 3px;
            overflow: hidden;
        }
        .challenge-item .challenge-bar .challenge-fill {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            transition: width 1.5s ease;
            width: 0%;
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
            .insights-grid { grid-template-columns: repeat(2, 1fr); }
            .badges-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 1024px) {
            .podium { flex-wrap: wrap; gap: 12px; padding: 16px; }
            .podium-item { width: 120px; padding: 12px 16px; }
            .podium-item .podium-avatar { width: 48px; height: 48px; font-size: 20px; }
            .podium-item.rank-1 .podium-avatar { width: 60px; height: 60px; font-size: 24px; }
            .podium-item.rank-1 { padding-bottom: 20px; }
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
            .header-left .page-title { font-size: 22px; }
            .header-right { width: 100%; justify-content: flex-start; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .stat-card { padding: 14px 16px; }
            .stat-card .stat-info .stat-number { font-size: 20px; }
            .podium-item { width: 100px; padding: 10px 12px; }
            .podium-item .podium-avatar { width: 40px; height: 40px; font-size: 16px; }
            .podium-item.rank-1 .podium-avatar { width: 50px; height: 50px; font-size: 20px; }
            .podium-item .podium-name { font-size: 13px; }
            .podium-item .podium-score { font-size: 12px; }
            .badges-grid { grid-template-columns: repeat(2, 1fr); }
            .insights-grid { grid-template-columns: 1fr; }
            .current-student-card { flex-direction: column; align-items: stretch; text-align: center; }
            .current-student-card .rank-details { justify-content: center; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar .filter-group { min-width: 100%; }
            .filter-actions { justify-content: flex-end; }
            .table-container { overflow-x: auto; }
            .table-container table { min-width: 600px; }
            .action-buttons { flex-direction: column; }
            .btn-action { width: 100%; justify-content: center; }
            .motivation-panel { flex-direction: column; text-align: center; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .podium { flex-direction: column; align-items: center; }
            .podium-item { width: 100%; max-width: 200px; }
            .podium-item.rank-1 { padding-bottom: 10px; }
            .podium-item .podium-avatar { width: 48px; height: 48px; font-size: 20px; }
            .podium-item.rank-1 .podium-avatar { width: 60px; height: 60px; font-size: 24px; }
            .badges-grid { grid-template-columns: repeat(2, 1fr); }
            .current-student-card .rank-display { flex-direction: column; }
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
                <a href="leaderboard.php" class="active">
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
                    🏆 Student <span>Leaderboard</span>
                </div>
                <div class="page-subtitle">
                    Compete, learn, and climb to the top.
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
                <div class="stat-icon purple"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo $total_participants; ?>">0</div>
                    <div class="stat-label">Total Participants</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon gold"><i class="fas fa-star"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo $highest_score; ?>">0</div>
                    <div class="stat-label">Highest Score</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon green"><i class="fas fa-percent"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo round($avg_all_score, 1); ?>">0</div>
                    <div class="stat-label">Average Score</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon orange"><i class="fas fa-fire"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo $active_learners; ?>">0</div>
                    <div class="stat-label">Active Learners</div>
                </div>
            </div>
        </div>

        <!-- =============================================
        FILTER BAR
        ============================================= -->
        <form class="filter-bar" method="GET" action="">
            <div class="filter-group">
                <label><i class="fas fa-search"></i></label>
                <input type="text" name="search" placeholder="Search students..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group">
                <label>Period</label>
                <select name="period">
                    <option value="all" <?php echo $time_period == 'all' ? 'selected' : ''; ?>>All Time</option>
                    <option value="month" <?php echo $time_period == 'month' ? 'selected' : ''; ?>>This Month</option>
                    <option value="week" <?php echo $time_period == 'week' ? 'selected' : ''; ?>>This Week</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Sort</label>
                <select name="sort">
                    <option value="score" <?php echo $sort_by == 'score' ? 'selected' : ''; ?>>Total Score</option>
                    <option value="average" <?php echo $sort_by == 'average' ? 'selected' : ''; ?>>Average Score</option>
                    <option value="quizzes" <?php echo $sort_by == 'quizzes' ? 'selected' : ''; ?>>Quizzes Completed</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter btn-filter-primary">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <a href="leaderboard.php" class="btn-filter btn-filter-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>

        <!-- =============================================
        PODIUM SECTION
        ============================================= -->
        <?php if (count($top_3) >= 3): ?>
        <div class="podium-section">
            <div class="podium">
                <!-- Rank 2 -->
                <div class="podium-item rank-2">
                    <div class="podium-rank">🥈 Rank 2</div>
                    <div class="podium-avatar silver">
                        <?php echo strtoupper(substr($top_3[1]['full_name'], 0, 2)); ?>
                    </div>
                    <div class="podium-name"><?php echo htmlspecialchars($top_3[1]['full_name']); ?></div>
                    <div class="podium-score"><?php echo $top_3[1]['total_score']; ?> pts</div>
                    <span class="podium-badge">⭐</span>
                </div>

                <!-- Rank 1 -->
                <div class="podium-item rank-1">
                    <div class="podium-rank">🥇 Rank 1</div>
                    <div class="podium-avatar gold">
                        <?php echo strtoupper(substr($top_3[0]['full_name'], 0, 2)); ?>
                    </div>
                    <div class="podium-name"><?php echo htmlspecialchars($top_3[0]['full_name']); ?></div>
                    <div class="podium-score"><?php echo $top_3[0]['total_score']; ?> pts</div>
                    <span class="podium-badge">🏆</span>
                </div>

                <!-- Rank 3 -->
                <div class="podium-item rank-3">
                    <div class="podium-rank">🥉 Rank 3</div>
                    <div class="podium-avatar bronze">
                        <?php echo strtoupper(substr($top_3[2]['full_name'], 0, 2)); ?>
                    </div>
                    <div class="podium-name"><?php echo htmlspecialchars($top_3[2]['full_name']); ?></div>
                    <div class="podium-score"><?php echo $top_3[2]['total_score']; ?> pts</div>
                    <span class="podium-badge">🌟</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- =============================================
        CURRENT STUDENT CARD
        ============================================= -->
        <?php if ($current_rank > 0 && $current_student_data): ?>
        <div class="current-student-card">
            <div class="rank-display">
                <div>
                    <div class="rank-number">#<?php echo $current_rank; ?></div>
                    <div class="rank-label">Your Rank</div>
                </div>
                <div style="font-size:32px;">
                    <?php 
                    if ($current_rank <= 3) echo '🏆';
                    elseif ($current_rank <= 10) echo '⭐';
                    else echo '📈';
                    ?>
                </div>
            </div>
            <div class="rank-details">
                <div class="detail-item">
                    <div class="detail-value"><?php echo $current_student_data['total_score']; ?></div>
                    <div class="detail-label">Total Score</div>
                </div>
                <div class="detail-item">
                    <div class="detail-value"><?php echo $current_student_data['quiz_count']; ?></div>
                    <div class="detail-label">Quizzes Completed</div>
                </div>
                <div class="detail-item">
                    <div class="detail-value"><?php echo round($current_student_data['avg_score'], 1); ?>%</div>
                    <div class="detail-label">Average Score</div>
                </div>
                <div class="detail-item">
                    <div class="detail-value"><?php echo $current_student_data['quizzes_passed']; ?></div>
                    <div class="detail-label">Quizzes Passed</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- =============================================
        MOTIVATION PANEL
        ============================================= -->
        <div class="motivation-panel">
            <span class="motivation-icon">💡</span>
            <span class="motivation-text">
                <?php foreach ($motivation_messages as $msg): ?>
                    <?php echo $msg; ?><br>
                <?php endforeach; ?>
            </span>
        </div>

        <!-- =============================================
        LEADERBOARD TABLE
        ============================================= -->
        <div class="table-container">
            <?php if (count($leaderboard) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width:60px;">Rank</th>
                            <th>Student</th>
                            <th>Total Score</th>
                            <th>Quizzes</th>
                            <th>Avg Score</th>
                            <th>Badge</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaderboard as $index => $student): 
                            $rank = $index + 1;
                            $is_current = $student['id'] == $student_id;
                            $rank_class = $rank == 1 ? 'gold' : ($rank == 2 ? 'silver' : ($rank == 3 ? 'bronze' : 'normal'));
                            $badge_emoji = $rank == 1 ? '🏆' : ($rank == 2 ? '🥈' : ($rank == 3 ? '🥉' : '⭐'));
                        ?>
                            <tr class="<?php echo $is_current ? 'current-user' : ''; ?>">
                                <td>
                                    <span class="rank-badge <?php echo $rank_class; ?>">
                                        <?php echo $rank; ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <span class="avatar-circle">
                                            <?php echo strtoupper(substr($student['full_name'], 0, 2)); ?>
                                        </span>
                                        <span>
                                            <?php echo htmlspecialchars($student['full_name']); ?>
                                            <?php if ($is_current): ?>
                                                <span style="font-size:11px; color:#6C63FF; font-weight:600; margin-left:4px;">(You)</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </td>
                                <td><strong><?php echo $student['total_score']; ?></strong></td>
                                <td><?php echo $student['quiz_count']; ?></td>
                                <td><?php echo round($student['avg_score'], 1); ?>%</td>
                                <td>
                                    <span class="badge-icon"><?php echo $badge_emoji; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align:center; padding:40px; color:#9CA3AF;">
                    <div style="font-size:48px; margin-bottom:12px;">🏆</div>
                    <h3>No Leaderboard Data</h3>
                    <p>Start taking quizzes to appear on the leaderboard!</p>
                    <a href="attempt_quiz.php" style="display:inline-block; margin-top:12px; padding:10px 24px; background:linear-gradient(135deg,#6C63FF,#8B5CF6); color:white; border-radius:12px; text-decoration:none; font-weight:600;">
                        <i class="fas fa-play"></i> Take a Quiz
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- =============================================
        BADGES SECTION
        ============================================= -->
        <div style="margin-bottom:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h3 style="font-size:18px; font-weight:700; color:#1F2937;">
                    <i class="fas fa-award" style="color:#6C63FF;"></i> Achievement Badges
                </h3>
            </div>
            <div class="badges-grid">
                <?php foreach ($badges as $badge): ?>
                    <div class="badge-card">
                        <span class="badge-icon" style="font-size:32px; display:block; margin-bottom:4px;">
                            <?php echo $badge['icon']; ?>
                        </span>
                        <div class="badge-title"><?php echo $badge['title']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- =============================================
        INSIGHTS GRID
        ============================================= -->
        <div class="insights-grid">
            <div class="insight-card">
                <div class="insight-icon">📚</div>
                <div class="insight-label">Top Subject</div>
                <div class="insight-value"><?php echo htmlspecialchars($top_subject); ?></div>
            </div>
            <div class="insight-card">
                <div class="insight-icon">🏆</div>
                <div class="insight-label">Top Performer</div>
                <div class="insight-value">
                    <?php echo !empty($leaderboard) ? htmlspecialchars($leaderboard[0]['full_name']) : 'N/A'; ?>
                </div>
            </div>
            <div class="insight-card">
                <div class="insight-icon">📊</div>
                <div class="insight-label">Highest Avg Score</div>
                <div class="insight-value">
                    <?php echo !empty($leaderboard) ? round($leaderboard[0]['avg_score'], 1) . '%' : 'N/A'; ?>
                </div>
            </div>
            <div class="insight-card">
                <div class="insight-icon">🔥</div>
                <div class="insight-label">Most Active</div>
                <div class="insight-value">
                    <?php 
                    $most_active = !empty($leaderboard) ? array_reduce($leaderboard, function($carry, $item) {
                        return (!$carry || $item['quiz_count'] > $carry['quiz_count']) ? $item : $carry;
                    }) : null;
                    echo $most_active ? htmlspecialchars($most_active['full_name']) : 'N/A';
                    ?>
                </div>
            </div>
        </div>

        <!-- =============================================
        ACTION BUTTONS
        ============================================= -->
        <div class="action-buttons">
            <a href="attempt_quiz.php" class="btn-action btn-success">
                <i class="fas fa-puzzle-piece"></i> Attempt Quiz
            </a>
            <a href="browse_notes.php" class="btn-action btn-primary">
                <i class="fas fa-book"></i> Browse Notes
            </a>
            <a href="progress.php" class="btn-action btn-secondary">
                <i class="fas fa-chart-line"></i> View Progress
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
        // CHALLENGE PROGRESS BARS
        // =============================================
        const challengeBars = document.querySelectorAll('.challenge-fill');

        const challengeObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const width = entry.target.style.width;
                    entry.target.style.width = '0%';
                    setTimeout(() => {
                        entry.target.style.width = width;
                    }, 100);
                    challengeObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.3 });

        challengeBars.forEach(el => {
            challengeObserver.observe(el);
        });

        // =============================================
        // CONSOLE LOG
        // =============================================
        console.log('🏆 EduHack AI - Leaderboard');
        console.log('👥 Total Participants: <?php echo $total_participants; ?>');
        console.log('📊 Your Rank: #<?php echo $current_rank; ?>');
        console.log('⭐ Your Score: <?php echo $current_student_data['total_score'] ?? 0; ?>');
        console.log('👋 Welcome, <?php echo htmlspecialchars($student_name); ?>');
    </script>

</body>
</html>