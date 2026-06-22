<?php
/**
 * =============================================
 * Quiz Results & Analytics - EduHack AI Teacher Panel
 * =============================================
 * 
 * This page displays quiz performance analytics,
 * student results, and learning insights.
 */

// Require authentication and teacher role
require_once '../includes/auth.php';
requireTeacher();

// Get teacher info
$teacher_id = getCurrentUserId();
$teacher_name = getCurrentUserFullName();

// Include database connection
require_once '../includes/db.php';

// =============================================
// GET FILTERS
// =============================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$subject_filter = isset($_GET['subject']) ? mysqli_real_escape_string($conn, trim($_GET['subject'])) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'latest';

// =============================================
// FETCH OVERVIEW STATISTICS
// =============================================
$stats_sql = "SELECT 
                COUNT(DISTINCT q.id) as total_quizzes,
                COUNT(qa.id) as total_attempts,
                COALESCE(AVG(qa.percentage), 0) as avg_score,
                COALESCE(SUM(CASE WHEN qa.percentage >= q.passing_score THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(qa.id), 0), 0) as pass_rate
              FROM quizzes q
              LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.is_completed = 1
              WHERE q.teacher_id = $teacher_id";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// =============================================
// FETCH QUIZZES WITH PERFORMANCE DATA
// =============================================
$where_conditions = ["q.teacher_id = $teacher_id"];

if (!empty($search)) {
    $where_conditions[] = "(q.title LIKE '%$search%' OR n.subject LIKE '%$search%')";
}
if (!empty($subject_filter)) {
    $where_conditions[] = "n.subject = '$subject_filter'";
}

$where_clause = implode(' AND ', $where_conditions);

$order_by = match($sort_by) {
    'attempts' => 'total_attempts DESC',
    'highest' => 'avg_score DESC',
    'lowest' => 'avg_score ASC',
    default => 'q.created_at DESC'
};

$quizzes_sql = "SELECT 
                  q.*,
                  n.subject as subject,
                  COUNT(DISTINCT qa.id) as total_attempts,
                  COALESCE(AVG(qa.percentage), 0) as avg_score,
                  COALESCE(SUM(CASE WHEN qa.percentage >= q.passing_score THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(qa.id), 0), 0) as pass_rate,
                  (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as total_questions
                FROM quizzes q
                LEFT JOIN notes n ON q.note_id = n.id
                LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.is_completed = 1
                WHERE $where_clause
                GROUP BY q.id
                ORDER BY $order_by";
$quizzes_result = mysqli_query($conn, $quizzes_sql);
$quizzes = mysqli_fetch_all($quizzes_result, MYSQLI_ASSOC);

// =============================================
// FETCH RECENT ATTEMPTS
// =============================================
$recent_sql = "SELECT 
                 u.full_name,
                 q.title as quiz_title,
                 qa.score,
                 qa.percentage,
                 qa.submitted_at,
                 q.passing_score,
                 qa.id as attempt_id
               FROM quiz_attempts qa
               JOIN users u ON qa.student_id = u.id
               JOIN quizzes q ON qa.quiz_id = q.id
               WHERE q.teacher_id = $teacher_id AND qa.is_completed = 1
               ORDER BY qa.submitted_at DESC
               LIMIT 10";
$recent_result = mysqli_query($conn, $recent_sql);
$recent_attempts = mysqli_fetch_all($recent_result, MYSQLI_ASSOC);

// =============================================
// FETCH TOP PERFORMERS
// =============================================
$top_sql = "SELECT 
              u.id as student_id,
              u.full_name,
              q.title as quiz_title,
              qa.score,
              qa.percentage,
              qa.submitted_at,
              qa.id as attempt_id
            FROM quiz_attempts qa
            JOIN users u ON qa.student_id = u.id
            JOIN quizzes q ON qa.quiz_id = q.id
            WHERE q.teacher_id = $teacher_id AND qa.is_completed = 1
            ORDER BY qa.percentage DESC
            LIMIT 5";
$top_result = mysqli_query($conn, $top_sql);
$top_performers = mysqli_fetch_all($top_result, MYSQLI_ASSOC);

// =============================================
// FETCH SUBJECTS FOR FILTER
// =============================================
$subjects_sql = "SELECT DISTINCT n.subject as subject FROM quizzes q JOIN notes n ON q.note_id = n.id WHERE q.teacher_id = $teacher_id ORDER BY n.subject";
$subjects_result = mysqli_query($conn, $subjects_sql);
$subjects = mysqli_fetch_all($subjects_result, MYSQLI_ASSOC);

// =============================================
// CALCULATE INSIGHTS
// =============================================
$most_attempted = !empty($quizzes) ? array_reduce($quizzes, function($carry, $item) {
    return (!$carry || $item['total_attempts'] > $carry['total_attempts']) ? $item : $carry;
}) : null;

$highest_scoring = !empty($quizzes) ? array_reduce($quizzes, function($carry, $item) {
    return (!$carry || $item['avg_score'] > $carry['avg_score']) ? $item : $carry;
}) : null;

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
            min-width: 140px;
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

        .status-badge {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .status-badge.pass {
            background: rgba(34, 197, 94, 0.08);
            color: #22C55E;
        }
        .status-badge.fail {
            background: rgba(239, 68, 68, 0.08);
            color: #EF4444;
        }

        .rank-badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }
        .rank-badge.gold { background: rgba(245, 158, 11, 0.15); color: #F59E0B; }
        .rank-badge.silver { background: rgba(156, 163, 175, 0.15); color: #6B7280; }
        .rank-badge.bronze { background: rgba(217, 119, 6, 0.15); color: #D97706; }

        .action-btn {
            padding: 5px 12px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
        }
        .action-btn:hover {
            transform: translateY(-2px);
        }
        .action-btn.view {
            background: rgba(108, 99, 255, 0.08);
            color: #6C63FF;
        }
        .action-btn.view:hover {
            background: #6C63FF;
            color: white;
        }
        .action-btn.export {
            background: rgba(34, 197, 94, 0.08);
            color: #22C55E;
        }
        .action-btn.export:hover {
            background: #22C55E;
            color: white;
        }
        .action-btn.analytics {
            background: rgba(245, 158, 11, 0.08);
            color: #F59E0B;
        }
        .action-btn.analytics:hover {
            background: #F59E0B;
            color: white;
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
            box-shadow: 0 8px 30px rgba(0,0,0,0.04);
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
           RECENT ACTIVITY
        ============================================= */
        .activity-timeline {
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            padding: 20px 24px;
        }
        .activity-timeline .timeline-title {
            font-size: 16px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 12px;
        }
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
           MODAL
        ============================================= */
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
        .modal {
            background: white;
            border-radius: 20px;
            max-width: 700px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 32px;
            animation: slideUp 0.3s ease;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        .modal::-webkit-scrollbar { width: 6px; }
        .modal::-webkit-scrollbar-thumb { background: #6C63FF; border-radius: 3px; }
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #F3F4F6;
        }
        .modal-header h3 {
            font-size: 22px;
            font-weight: 700;
            color: #1F2937;
        }
        .modal-close {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 50%;
            background: #F3F4F6;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6B7280;
        }
        .modal-close:hover {
            background: #FEE2E2;
            color: #EF4444;
            transform: rotate(90deg);
        }
        .modal-body .detail-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #F9FAFB;
        }
        .modal-body .detail-label {
            font-weight: 600;
            color: #6B7280;
            width: 140px;
            flex-shrink: 0;
        }
        .modal-body .detail-value { color: #1F2937; }

        /* =============================================
           EMPTY STATE
        ============================================= */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state .empty-icon { font-size: 72px; color: #E5E7EB; margin-bottom: 16px; }
        .empty-state h4 { font-size: 20px; color: #1F2937; margin-bottom: 8px; }
        .empty-state p { color: #6B7280; }

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
        }

        @media (max-width: 992px) {
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar .filter-group { min-width: 100%; }
            .filter-actions { justify-content: flex-end; }
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
            .insights-grid { grid-template-columns: 1fr; }
            .header-left .page-title { font-size: 22px; }
            .table-container { overflow-x: auto; }
            .table-container table { min-width: 700px; }
            .modal { padding: 20px; margin: 10px; }
            .modal-body .detail-row { flex-direction: column; gap: 2px; }
            .modal-body .detail-label { width: 100%; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .filter-actions { flex-direction: column; }
            .btn-filter { width: 100%; justify-content: center; }
            .action-btn { font-size: 11px; padding: 4px 10px; }
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
                <a href="create_notes.php">
                    <i class="fas fa-plus-circle"></i> Create Notes
                </a>
            </li>
            <li>
                <a href="view_notes.php">
                    <i class="fas fa-book"></i> View Notes
                </a>
            </li>
            <li>
                <a href="create_quiz.php">
                    <i class="fas fa-puzzle-piece"></i> Create Quiz
                </a>
            </li>
            <li>
                <a href="quiz_results.php" class="active">
                    <i class="fas fa-chart-bar"></i> Quiz Results
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
                    📊 Quiz <span>Analytics & Results</span>
                </div>
                <div class="page-subtitle">
                    Track student performance and learning outcomes.
                </div>
            </div>
            <div class="header-right">
                <div style="font-size:14px; color:#6B7280;">
                    <i class="far fa-calendar-alt"></i> <?php echo $current_date; ?>
                </div>
                <a href="#" class="profile-avatar">
                    <?php echo strtoupper(substr($teacher_name, 0, 2)); ?>
                </a>
            </div>
        </header>

        <!-- =============================================
        STATISTICS CARDS
        ============================================= -->
        <div class="stats-grid">
            <div class="stat-card animate-in">
                <div class="stat-icon purple"><i class="fas fa-puzzle-piece"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo $stats['total_quizzes'] ?? 0; ?>">0</div>
                    <div class="stat-label">Total Quizzes</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon green"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo $stats['total_attempts'] ?? 0; ?>">0</div>
                    <div class="stat-label">Total Attempts</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon orange"><i class="fas fa-percent"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo round($stats['avg_score'] ?? 0, 1); ?>">0</div>
                    <div class="stat-label">Average Score</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon blue"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo round($stats['pass_rate'] ?? 0, 1); ?>">0</div>
                    <div class="stat-label">Pass Rate %</div>
                </div>
            </div>
        </div>

        <!-- =============================================
        FILTER BAR
        ============================================= -->
        <form class="filter-bar" method="GET" action="">
            <div class="filter-group">
                <label><i class="fas fa-search"></i></label>
                <input type="text" name="search" placeholder="Search quizzes..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group">
                <label>Subject</label>
                <select name="subject">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['subject']); ?>" 
                                <?php echo $subject_filter == $s['subject'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['subject']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Sort</label>
                <select name="sort">
                    <option value="latest" <?php echo $sort_by == 'latest' ? 'selected' : ''; ?>>Latest</option>
                    <option value="attempts" <?php echo $sort_by == 'attempts' ? 'selected' : ''; ?>>Most Attempted</option>
                    <option value="highest" <?php echo $sort_by == 'highest' ? 'selected' : ''; ?>>Highest Score</option>
                    <option value="lowest" <?php echo $sort_by == 'lowest' ? 'selected' : ''; ?>>Lowest Score</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter btn-filter-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="quiz_results.php" class="btn-filter btn-filter-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>

        <!-- =============================================
        QUIZZES TABLE
        ============================================= -->
        <div class="table-container animate-in">
            <?php if (count($quizzes) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Quiz Title</th>
                            <th>Subject</th>
                            <th>Questions</th>
                            <th>Attempts</th>
                            <th>Avg Score</th>
                            <th>Pass Rate</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quizzes as $quiz): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars(substr($quiz['title'], 0, 30)) . (strlen($quiz['title']) > 30 ? '...' : ''); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($quiz['subject']); ?></td>
                                <td><?php echo $quiz['total_questions'] ?? 0; ?></td>
                                <td><?php echo $quiz['total_attempts']; ?></td>
                                <td><?php echo round($quiz['avg_score'], 1); ?>%</td>
                                <td>
                                    <span style="font-weight:600; color:<?php echo $quiz['pass_rate'] >= 70 ? '#22C55E' : ($quiz['pass_rate'] >= 40 ? '#F59E0B' : '#EF4444'); ?>">
                                        <?php echo round($quiz['pass_rate'], 1); ?>%
                                    </span>
                                </td>
                                <td>
                                    <button class="action-btn view" onclick="viewQuizDetails(<?php echo $quiz['id']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="action-btn export" onclick="exportQuiz(<?php echo $quiz['id']; ?>)">
                                        <i class="fas fa-file-export"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📊</div>
                    <h4>No Quiz Data Available</h4>
                    <p>Create quizzes and students will start taking them.</p>
                    <a href="create_quiz.php" style="display:inline-block; margin-top:12px; padding:10px 28px; background:linear-gradient(135deg,#6C63FF,#8B5CF6); color:white; border-radius:12px; text-decoration:none; font-weight:600;">
                        <i class="fas fa-plus-circle"></i> Create Quiz
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- =============================================
        TWO COLUMN - Top Performers & Recent Attempts
        ============================================= -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px;">
            
            <!-- Top Performers -->
            <div class="table-container animate-in">
                <div style="padding:16px 20px; border-bottom:1px solid #F3F4F6;">
                    <h4 style="font-size:16px; font-weight:700; color:#1F2937;">
                        <i class="fas fa-trophy" style="color:#F59E0B;"></i> Top Performers
                    </h4>
                </div>
                <?php if (count($top_performers) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Quiz</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_performers as $index => $performer): ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge <?php 
                                            echo $index == 0 ? 'gold' : ($index == 1 ? 'silver' : ($index == 2 ? 'bronze' : '')); 
                                        ?>">
                                            <?php echo $index + 1; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($performer['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($performer['quiz_title'], 0, 20)) . (strlen($performer['quiz_title']) > 20 ? '...' : ''); ?></td>
                                    <td><strong style="color:#6C63FF;"><?php echo round($performer['percentage'], 1); ?>%</strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="padding:30px; text-align:center; color:#9CA3AF;">
                        No attempts yet
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Attempts -->
            <div class="table-container animate-in">
                <div style="padding:16px 20px; border-bottom:1px solid #F3F4F6;">
                    <h4 style="font-size:16px; font-weight:700; color:#1F2937;">
                        <i class="fas fa-clock" style="color:#6C63FF;"></i> Recent Attempts
                    </h4>
                </div>
                <?php if (count($recent_attempts) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Quiz</th>
                                <th>Score</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_attempts as $attempt): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($attempt['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($attempt['quiz_title'], 0, 15)) . (strlen($attempt['quiz_title']) > 15 ? '...' : ''); ?></td>
                                    <td><?php echo round($attempt['percentage'], 1); ?>%</td>
                                    <td>
                                        <span class="status-badge <?php echo $attempt['percentage'] >= $attempt['passing_score'] ? 'pass' : 'fail'; ?>">
                                            <?php echo $attempt['percentage'] >= $attempt['passing_score'] ? 'Pass' : 'Fail'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="padding:30px; text-align:center; color:#9CA3AF;">
                        No recent attempts
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- =============================================
        INSIGHTS GRID
        ============================================= -->
        <div class="insights-grid animate-in">
            <div class="insight-card">
                <div class="insight-icon">🔥</div>
                <div class="insight-label">Most Attempted Quiz</div>
                <div class="insight-value">
                    <?php if ($most_attempted): ?>
                        <?php echo htmlspecialchars(substr($most_attempted['title'], 0, 25)) . (strlen($most_attempted['title']) > 25 ? '...' : ''); ?>
                        <span style="font-size:12px; color:#6B7280; font-weight:400;">(<?php echo $most_attempted['total_attempts']; ?> attempts)</span>
                    <?php else: ?>
                        No data
                    <?php endif; ?>
                </div>
            </div>
            <div class="insight-card">
                <div class="insight-icon">⭐</div>
                <div class="insight-label">Highest Scoring Quiz</div>
                <div class="insight-value">
                    <?php if ($highest_scoring): ?>
                        <?php echo htmlspecialchars(substr($highest_scoring['title'], 0, 25)) . (strlen($highest_scoring['title']) > 25 ? '...' : ''); ?>
                        <span style="font-size:12px; color:#6B7280; font-weight:400;">(<?php echo round($highest_scoring['avg_score'], 1); ?>%)</span>
                    <?php else: ?>
                        No data
                    <?php endif; ?>
                </div>
            </div>
            <div class="insight-card">
                <div class="insight-icon">🏆</div>
                <div class="insight-label">Best Student</div>
                <div class="insight-value">
                    <?php if (!empty($top_performers)): ?>
                        <?php echo htmlspecialchars($top_performers[0]['full_name']); ?>
                        <span style="font-size:12px; color:#6B7280; font-weight:400;">(<?php echo round($top_performers[0]['percentage'], 1); ?>%)</span>
                    <?php else: ?>
                        No data
                    <?php endif; ?>
                </div>
            </div>
            <div class="insight-card">
                <div class="insight-icon">📚</div>
                <div class="insight-label">Total Questions Created</div>
                <div class="insight-value">
                    <?php 
                    $total_q_sql = "SELECT COUNT(*) as total FROM quiz_questions qq 
                                   JOIN quizzes q ON qq.quiz_id = q.id 
                                   WHERE q.teacher_id = $teacher_id";
                    $total_q_result = mysqli_query($conn, $total_q_sql);
                    $total_q = mysqli_fetch_assoc($total_q_result)['total'] ?? 0;
                    echo $total_q;
                    ?>
                </div>
            </div>
        </div>

        <!-- =============================================
        RECENT ACTIVITY TIMELINE
        ============================================= -->
        <div class="activity-timeline animate-in">
            <div class="timeline-title">
                <i class="fas fa-history" style="color:#6C63FF;"></i> Recent Activity
            </div>
            <?php 
            $activity_sql = "SELECT 
                               'Student attempted quiz' as action,
                               u.full_name as user_name,
                               q.title as quiz_title,
                               qa.percentage,
                               qa.submitted_at
                             FROM quiz_attempts qa
                             JOIN users u ON qa.student_id = u.id
                             JOIN quizzes q ON qa.quiz_id = q.id
                             WHERE q.teacher_id = $teacher_id AND qa.is_completed = 1
                             ORDER BY qa.submitted_at DESC
                             LIMIT 5";
            $activity_result = mysqli_query($conn, $activity_sql);
            $activities = mysqli_fetch_all($activity_result, MYSQLI_ASSOC);
            
            if (count($activities) > 0):
                foreach ($activities as $act):
            ?>
                <div class="activity-item">
                    <div class="act-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="act-content">
                        <div class="act-text">
                            <strong><?php echo htmlspecialchars($act['user_name']); ?></strong>
                            <?php echo $act['action']; ?>
                            <strong>"<?php echo htmlspecialchars($act['quiz_title']); ?>"</strong>
                            <span style="color:<?php echo $act['percentage'] >= 50 ? '#22C55E' : '#EF4444'; ?>; font-weight:600;">
                                (<?php echo round($act['percentage'], 1); ?>%)
                            </span>
                        </div>
                        <div class="act-time">
                            <?php echo timeAgo($act['submitted_at']); ?>
                        </div>
                    </div>
                </div>
            <?php 
                endforeach;
            else:
            ?>
                <div style="text-align:center; color:#9CA3AF; padding:20px;">
                    No recent activity
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- =============================================
    QUIZ DETAILS MODAL
    ============================================= -->
    <div class="modal-overlay" id="quizModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-eye" style="color:#6C63FF;"></i> Quiz Details</h3>
                <button class="modal-close" onclick="closeModal('quizModal')">&times;</button>
            </div>
            <div class="modal-body" id="quizModalBody">
                <!-- Dynamic content -->
            </div>
        </div>
    </div>

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
            const increment = Math.ceil(target / 40);
            const duration = 1500;
            const stepTime = duration / 40;

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
        // MODAL FUNCTIONS
        // =============================================
        function openModal(id) {
            document.getElementById(id).classList.add('active');
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
        // VIEW QUIZ DETAILS
        // =============================================
        function viewQuizDetails(quizId) {
            fetch(`get_quiz_details.php?id=${quizId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const q = data.quiz;
                        document.getElementById('quizModalBody').innerHTML = `
                            <div class="detail-row">
                                <div class="detail-label">Title</div>
                                <div class="detail-value"><strong>${escapeHtml(q.title)}</strong></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Subject</div>
                                <div class="detail-value">📚 ${escapeHtml(q.subject)}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Description</div>
                                <div class="detail-value">${escapeHtml(q.description || 'No description')}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-row">
                                    <div class="detail-label">Time Limit</div>
                                    <div class="detail-value">⏱️ ${q.time_limit} minutes</div>
                                </div>
                            <div class="detail-row">
                                <div class="detail-label">Questions</div>
                                <div class="detail-value">${q.total_questions || 0}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Total Attempts</div>
                                <div class="detail-value">${q.total_attempts || 0}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Average Score</div>
                                <div class="detail-value" style="font-weight:700; color:#6C63FF;">${parseFloat(q.avg_score || 0).toFixed(1)}%</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Passing Score</div>
                                <div class="detail-value">${q.passing_score || 50}%</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Pass Rate</div>
                                <div class="detail-value" style="font-weight:700; color:${q.pass_rate >= 70 ? '#22C55E' : '#EF4444'};">
                                    ${parseFloat(q.pass_rate || 0).toFixed(1)}%
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Created</div>
                                <div class="detail-value">${new Date(q.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
                            </div>
                        `;
                        openModal('quizModal');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load quiz details.');
                });
        }

        // =============================================
        // EXPORT FUNCTION
        // =============================================
        function exportQuiz(quizId) {
            // Demo export functionality
            if (confirm('Export quiz results for analytics?')) {
                alert('📊 Quiz data export will be available in the full version.\n\nQuiz ID: ' + quizId);
            }
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
        // CONSOLE LOG
        // =============================================
        console.log('📊 EduHack AI - Quiz Results Page Loaded');
        console.log('📝 Total Quizzes: <?php echo $stats['total_quizzes'] ?? 0; ?>');
        console.log('👋 Welcome, <?php echo htmlspecialchars($teacher_name); ?>');
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