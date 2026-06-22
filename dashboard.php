<?php
/**
 * =============================================
 * Teacher Dashboard - EduHack AI
 * =============================================
 * 
 * This page displays the teacher dashboard with
 * statistics, recent activities, and quick actions.
 */

// Require authentication and teacher role
require_once '../includes/auth.php';
requireTeacher();

// Get teacher ID and name from session
$teacher_id = getCurrentUserId();
$teacher_name = getCurrentUserFullName();

// Include database connection
require_once '../includes/db.php';

// =============================================
// FETCH DASHBOARD STATISTICS
// =============================================

// Total Notes
$notes_query = "SELECT COUNT(*) as total FROM notes WHERE teacher_id = $teacher_id";
$notes_result = mysqli_query($conn, $notes_query);
$total_notes = mysqli_fetch_assoc($notes_result)['total'] ?? 0;

// Total Quizzes
$quizzes_query = "SELECT COUNT(*) as total FROM quizzes WHERE teacher_id = $teacher_id";
$quizzes_result = mysqli_query($conn, $quizzes_query);
$total_quizzes = mysqli_fetch_assoc($quizzes_result)['total'] ?? 0;

// Total Students (unique students who interacted with teacher's content)
$students_query = "SELECT COUNT(DISTINCT student_id) as total 
                   FROM quiz_attempts qa 
                   JOIN quizzes q ON qa.quiz_id = q.id 
                   WHERE q.teacher_id = $teacher_id";
$students_result = mysqli_query($conn, $students_query);
$total_students = mysqli_fetch_assoc($students_result)['total'] ?? 0;

// Forum Posts
$forum_query = "SELECT COUNT(*) as total FROM forum_posts WHERE user_id = $teacher_id";
$forum_result = mysqli_query($conn, $forum_query);
$total_forum_posts = mysqli_fetch_assoc($forum_result)['total'] ?? 0;

// =============================================
// FETCH RECENT NOTES
// =============================================
$recent_notes_query = "SELECT id, title, subject, file_type, views, downloads, created_at 
                       FROM notes 
                       WHERE teacher_id = $teacher_id 
                       ORDER BY created_at DESC 
                       LIMIT 5";
$recent_notes_result = mysqli_query($conn, $recent_notes_query);
$recent_notes = mysqli_fetch_all($recent_notes_result, MYSQLI_ASSOC);

// =============================================
// FETCH RECENT QUIZZES
// =============================================
$recent_quizzes_query = "SELECT id, title, total_marks, is_published, created_at 
                         FROM quizzes 
                         WHERE teacher_id = $teacher_id 
                         ORDER BY created_at DESC 
                         LIMIT 5";
$recent_quizzes_result = mysqli_query($conn, $recent_quizzes_query);
$recent_quizzes = mysqli_fetch_all($recent_quizzes_result, MYSQLI_ASSOC);

// =============================================
// FETCH RECENT ACTIVITIES
// =============================================
$activities_query = "SELECT 
                        'Student attempted quiz' as action,
                        u.full_name as user_name,
                        qa.submitted_at as activity_date,
                        q.title as reference
                     FROM quiz_attempts qa
                     JOIN users u ON qa.student_id = u.id
                     JOIN quizzes q ON qa.quiz_id = q.id
                     WHERE q.teacher_id = $teacher_id
                     ORDER BY qa.submitted_at DESC
                     LIMIT 5";
$activities_result = mysqli_query($conn, $activities_query);
$activities = mysqli_fetch_all($activities_result, MYSQLI_ASSOC);

// =============================================
// FETCH FORUM POSTS
// =============================================
$forum_posts_query = "SELECT fp.id, fp.title, fp.created_at, 
                             COUNT(fr.id) as replies,
                             u.full_name as author
                      FROM forum_posts fp
                      JOIN users u ON fp.user_id = u.id
                      LEFT JOIN forum_replies fr ON fp.id = fr.post_id
                      WHERE fp.user_id = $teacher_id OR fp.user_id IN (
                          SELECT id FROM users WHERE role = 'student'
                      )
                      GROUP BY fp.id
                      ORDER BY fp.created_at DESC
                      LIMIT 3";
$forum_posts_result = mysqli_query($conn, $forum_posts_query);
$forum_posts = mysqli_fetch_all($forum_posts_result, MYSQLI_ASSOC);

// =============================================
// FETCH PERFORMANCE DATA
// =============================================
$performance_query = "SELECT 
                         COUNT(DISTINCT qa.id) as total_attempts,
                         AVG(qa.percentage) as avg_score,
                         COUNT(DISTINCT qa.student_id) as total_students
                      FROM quiz_attempts qa
                      JOIN quizzes q ON qa.quiz_id = q.id
                      WHERE q.teacher_id = $teacher_id AND qa.is_completed = 1";
$performance_result = mysqli_query($conn, $performance_query);
$performance = mysqli_fetch_assoc($performance_result);

$total_attempts = $performance['total_attempts'] ?? 0;
$avg_score = round($performance['avg_score'] ?? 0, 1);
$total_students_performance = $performance['total_students'] ?? 0;

// =============================================
// CURRENT DATE
// =============================================
date_default_timezone_set('Asia/Kolkata');
$current_date = date('l, F j, Y');
$current_time = date('h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - EduHack AI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
            color: #1F2937;
            overflow-x: hidden;
            display: flex;
            min-height: 100vh;
        }

        /* =============================================
           SIDEBAR
        ============================================= */
        .sidebar {
            width: 260px;
            height: 100vh;
            background: #F5EDF8;
            border-right: 1px solid #E9D9F4;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            transition: all 0.4s ease;
            z-index: 1000;
            padding: 20px 0;
        }

        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #6C63FF;
            border-radius: 2px;
        }

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

        .sidebar-brand .brand-text span {
            color: #6C63FF;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 12px;
        }

        .sidebar-menu li {
            margin-bottom: 4px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 12px;
            color: #5B3F82;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            position: relative;
        }

        .sidebar-menu a i {
            width: 20px;
            font-size: 18px;
            transition: all 0.3s ease;
        }

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
            color: #6B5B7B;
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
            background: #FBF4FD;
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

        .header-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .header-left .greeting {
            font-size: 24px;
            font-weight: 700;
            color: #1F2937;
        }

        .header-left .greeting span {
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-left .date-time {
            font-size: 14px;
            color: #6B7280;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background: #F5E3FF;
            border: 1px solid #DCC2F3;
            border-radius: 12px;
            padding: 8px 16px;
            transition: all 0.3s ease;
        }

        .search-bar:focus-within {
            border-color: #6C63FF;
            box-shadow: 0 0 0 4px rgba(108, 99, 255, 0.06);
        }

        .search-bar input {
            border: none;
            outline: none;
            background: transparent;
            font-size: 14px;
            color: #1F2937;
            width: 180px;
        }

        .search-bar i {
            color: #9CA3AF;
            font-size: 16px;
        }

        .header-btn {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid #E9D9F4;
            background: #F7EEF9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6B7280;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            text-decoration: none;
        }

        .header-btn:hover {
            border-color: #6C63FF;
            color: #6C63FF;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 99, 255, 0.1);
        }

        .header-btn .badge {
            position: absolute;
            top: -4px;
            right: -4px;
            width: 18px;
            height: 18px;
            background: #EF4444;
            color: white;
            border-radius: 50%;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
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
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: #F5E3FF;
            padding: 24px;
            border-radius: 16px;
            border: 1px solid #DCC2F3;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 40px rgba(108, 99, 255, 0.06);
            border-color: rgba(108, 99, 255, 0.1);
        }

        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 12px;
        }

        .stat-card .stat-icon.purple {
            background: rgba(108, 99, 255, 0.08);
            color: #6C63FF;
        }

        .stat-card .stat-icon.green {
            background: rgba(34, 197, 94, 0.08);
            color: #22C55E;
        }

        .stat-card .stat-icon.orange {
            background: rgba(245, 158, 11, 0.08);
            color: #F59E0B;
        }

        .stat-card .stat-icon.blue {
            background: rgba(59, 130, 246, 0.08);
            color: #3B82F6;
        }

        .stat-card .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: #1F2937;
            line-height: 1;
        }

        .stat-card .stat-label {
            font-size: 14px;
            color: #6B7280;
            margin-top: 4px;
            font-weight: 500;
        }

        .stat-card .stat-trend {
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
        }

        .stat-card .stat-trend.up {
            background: rgba(34, 197, 94, 0.08);
            color: #22C55E;
        }

        .stat-card .stat-trend.down {
            background: rgba(239, 68, 68, 0.08);
            color: #EF4444;
        }

        /* =============================================
           QUICK ACTIONS
        ============================================= */
        .quick-actions {
            margin-bottom: 32px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .section-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: #1F2937;
        }

        .section-header a {
            color: #6C63FF;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .section-header a:hover {
            color: #8B5CF6;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .action-card {
            background: #F5E3FF;
            padding: 24px;
            border-radius: 16px;
            border: 1px solid #DCC2F3;
            text-align: center;
            transition: all 0.4s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .action-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 40px rgba(108, 99, 255, 0.08);
            border-color: #6C63FF;
        }

        .action-card .action-icon {
            font-size: 36px;
            margin-bottom: 12px;
            display: block;
        }

        .action-card h4 {
            font-size: 16px;
            font-weight: 600;
            color: #1F2937;
            margin-bottom: 4px;
        }

        .action-card p {
            font-size: 13px;
            color: #6B7280;
            line-height: 1.5;
        }

        /* =============================================
           TWO COLUMN LAYOUT
        ============================================= */
        .two-column {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }

        /* =============================================
           TABLES
        ============================================= */
        .table-card {
            background: #F5E3FF;
            border-radius: 16px;
            border: 1px solid #DCC2F3;
            padding: 24px;
            transition: all 0.3s ease;
        }

        .table-card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.03);
        }

        .table-card .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .table-card .table-header h4 {
            font-size: 18px;
            font-weight: 700;
            color: #1F2937;
        }

        .table-card table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-card table th {
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: #9CA3AF;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 12px 12px 0;
            border-bottom: 2px solid #F3F4F6;
        }

        .table-card table td {
            padding: 12px 12px 12px 0;
            border-bottom: 1px solid #F9FAFB;
            font-size: 14px;
            color: #4B5563;
        }

        .table-card table tr:last-child td {
            border-bottom: none;
        }

        .table-card table td .status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .table-card table td .status.published {
            background: rgba(34, 197, 94, 0.08);
            color: #22C55E;
        }

        .table-card table td .status.draft {
            background: rgba(245, 158, 11, 0.08);
            color: #F59E0B;
        }

        .btn-view {
            padding: 4px 14px;
            background: rgba(108, 99, 255, 0.08);
            color: #6C63FF;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-view:hover {
            background: #6C63FF;
            color: white;
            transform: translateY(-2px);
        }

        /* =============================================
           ACTIVITY TIMELINE
        ============================================= */
        .activity-list {
            list-style: none;
        }

        .activity-list li {
            display: flex;
            gap: 14px;
            padding: 12px 0;
            border-bottom: 1px solid #EBDDF3;
            align-items: flex-start;
        }

        .activity-list li:last-child {
            border-bottom: none;
        }

        .activity-list .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(108, 99, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6C63FF;
            flex-shrink: 0;
        }

        .activity-list .activity-content {
            flex: 1;
        }

        .activity-list .activity-content .action {
            font-size: 14px;
            color: #1F2937;
        }

        .activity-list .activity-content .action strong {
            color: #6C63FF;
        }

        .activity-list .activity-content .time {
            font-size: 12px;
            color: #9CA3AF;
            margin-top: 2px;
        }

        /* =============================================
           PERFORMANCE OVERVIEW
        ============================================= */
        .performance-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 12px;
        }

        .perf-item {
            background: #F5E3FF;
            padding: 16px;
            border-radius: 12px;
        }

        .perf-item .perf-label {
            font-size: 13px;
            color: #6B7280;
            margin-bottom: 4px;
        }

        .perf-item .perf-value {
            font-size: 24px;
            font-weight: 700;
            color: #1F2937;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: #E9D9F4;
            border-radius: 3px;
            margin-top: 8px;
            overflow: hidden;
        }

        .progress-bar .progress-fill {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            transition: width 1.5s ease;
            width: 0%;
        }

        /* =============================================
           FORUM PREVIEW
        ============================================= */
        .forum-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #F9FAFB;
        }

        .forum-item:last-child {
            border-bottom: none;
        }

        .forum-item .forum-info h5 {
            font-size: 14px;
            font-weight: 600;
            color: #1F2937;
        }

        .forum-item .forum-info .forum-meta {
            font-size: 12px;
            color: #9CA3AF;
        }

        .forum-item .forum-replies {
            font-size: 13px;
            color: #6B7280;
            font-weight: 600;
        }

        /* =============================================
           ANNOUNCEMENTS
        ============================================= */
        .announcement-item {
            padding: 12px 16px;
            background: #F9FAFB;
            border-radius: 12px;
            margin-bottom: 10px;
            border-left: 4px solid #6C63FF;
            transition: all 0.3s ease;
        }

        .announcement-item:hover {
            background: #F3F4F6;
            transform: translateX(4px);
        }

        .announcement-item .announce-title {
            font-size: 14px;
            font-weight: 600;
            color: #1F2937;
        }

        .announcement-item .announce-date {
            font-size: 12px;
            color: #9CA3AF;
        }

        /* =============================================
           MOTIVATIONAL BANNER
        ============================================= */
        .motivation-banner {
            background: linear-gradient(135deg, rgba(108, 99, 255, 0.04), rgba(139, 92, 246, 0.02));
            border: 1px solid rgba(108, 99, 255, 0.08);
            border-radius: 16px;
            padding: 24px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 32px;
        }

        .motivation-banner .motivation-text {
            font-size: 18px;
            font-weight: 600;
            color: #1F2937;
        }

        .motivation-banner .motivation-text span {
            color: #6C63FF;
        }

        .motivation-banner .motivation-icon {
            font-size: 48px;
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

        /* =============================================
           RESPONSIVE
        ============================================= */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 1024px) {
            .two-column {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }

            .sidebar.open {
                transform: translateX(0);
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

            .sidebar-overlay.active {
                display: block;
            }

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

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }

            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .top-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-right {
                width: 100%;
                justify-content: space-between;
            }

            .search-bar {
                flex: 1;
            }

            .search-bar input {
                width: 100%;
            }

            .stat-card .stat-number {
                font-size: 24px;
            }

            .table-card {
                padding: 16px;
                overflow-x: auto;
            }

            .table-card table {
                font-size: 13px;
                min-width: 500px;
            }

            .motivation-banner {
                flex-direction: column;
                text-align: center;
                padding: 20px;
            }

            .motivation-banner .motivation-icon {
                margin-top: 12px;
            }

            .performance-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .actions-grid {
                grid-template-columns: 1fr;
            }

            .header-left .greeting {
                font-size: 20px;
            }
        }

        /* =============================================
           ANIMATIONS
        ============================================= */
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
                <a href="dashboard.php" class="active">
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
                <a href="quiz_results.php">
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
                <div class="greeting">
                    Welcome Back, <span><?php echo htmlspecialchars($teacher_name); ?></span>
                </div>
                <div class="date-time">
                    <i class="far fa-calendar-alt"></i> <?php echo $current_date; ?> 
                    <i class="far fa-clock" style="margin-left: 12px;"></i> <?php echo $current_time; ?>
                </div>
            </div>
            <div class="header-right">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div>
                <a href="#" class="header-btn">
                    <i class="far fa-bell"></i>
                    <span class="badge">3</span>
                </a>
                <a href="#" class="profile-avatar">
                    <?php echo strtoupper(substr($teacher_name, 0, 2)); ?>
                </a>
            </div>
        </header>

        <!-- =============================================
        STATISTICS CARDS
        ============================================= -->
        <section class="stats-grid">
            <div class="stat-card animate-in">
                <div class="stat-icon purple">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $total_notes; ?>">0</div>
                <div class="stat-label">Total Notes Uploaded</div>
                <div class="stat-trend up">
                    <i class="fas fa-arrow-up"></i> 12%
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon green">
                    <i class="fas fa-puzzle-piece"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $total_quizzes; ?>">0</div>
                <div class="stat-label">Total Quizzes Created</div>
                <div class="stat-trend up">
                    <i class="fas fa-arrow-up"></i> 8%
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon orange">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $total_students; ?>">0</div>
                <div class="stat-label">Students Engaged</div>
                <div class="stat-trend up">
                    <i class="fas fa-arrow-up"></i> 15%
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon blue">
                    <i class="fas fa-comments"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $total_forum_posts; ?>">0</div>
                <div class="stat-label">Forum Discussions</div>
                <div class="stat-trend down">
                    <i class="fas fa-arrow-down"></i> 2%
                </div>
            </div>
        </section>

        <!-- =============================================
        QUICK ACTIONS
        ============================================= -->
        <section class="quick-actions">
            <div class="section-header">
                <h3>Quick Actions</h3>
                <a href="#">View All →</a>
            </div>
            <div class="actions-grid">
                <a href="create_notes.php" class="action-card animate-in">
                    <span class="action-icon">📝</span>
                    <h4>Create New Note</h4>
                    <p>Upload study materials</p>
                </a>
                <a href="create_notes.php" class="action-card animate-in">
                    <span class="action-icon">📤</span>
                    <h4>Upload Material</h4>
                    <p>Share resources with students</p>
                </a>
                <a href="create_quiz.php" class="action-card animate-in">
                    <span class="action-icon">🧪</span>
                    <h4>Create Quiz</h4>
                    <p>Assess student understanding</p>
                </a>
                <a href="forum.php" class="action-card animate-in">
                    <span class="action-icon">💬</span>
                    <h4>Open Forum</h4>
                    <p>Engage in discussions</p>
                </a>
            </div>
        </section>

        <!-- =============================================
        TWO COLUMN - Recent Notes & Activity
        ============================================= -->
        <div class="two-column">
            <!-- Recent Notes -->
            <div class="table-card animate-in">
                <div class="table-header">
                    <h4>📚 Recent Notes</h4>
                    <a href="view_notes.php" style="color:#6C63FF; text-decoration:none; font-weight:600; font-size:14px;">View All →</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_notes) > 0): ?>
                            <?php foreach ($recent_notes as $note): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(substr($note['title'], 0, 25)) . (strlen($note['title']) > 25 ? '...' : ''); ?></td>
                                    <td><?php echo htmlspecialchars($note['subject']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($note['created_at'])); ?></td>
                                    <td>
                                        <a href="view_notes.php?id=<?php echo $note['id']; ?>" class="btn-view">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center; color:#9CA3AF; padding:20px;">No notes uploaded yet</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Activity -->
            <div class="table-card animate-in">
                <div class="table-header">
                    <h4>🔄 Recent Activity</h4>
                </div>
                <?php if (count($activities) > 0): ?>
                    <ul class="activity-list">
                        <?php foreach ($activities as $activity): ?>
                            <li>
                                <div class="activity-icon">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="action">
                                        <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong>
                                        <?php echo htmlspecialchars($activity['action']); ?>
                                        <span style="color:#6C63FF;">"<?php echo htmlspecialchars($activity['reference']); ?>"</span>
                                    </div>
                                    <div class="time">
                                        <i class="far fa-clock"></i> <?php echo timeAgo($activity['activity_date']); ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="text-align:center; color:#9CA3AF; padding:20px;">No recent activities</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- =============================================
        TWO COLUMN - Recent Quizzes & Performance
        ============================================= -->
        <div class="two-column">
            <!-- Recent Quizzes -->
            <div class="table-card animate-in">
                <div class="table-header">
                    <h4>🧪 Recent Quizzes</h4>
                    <a href="quiz_results.php" style="color:#6C63FF; text-decoration:none; font-weight:600; font-size:14px;">View All →</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Quiz Name</th>
                            <th>Questions</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_quizzes) > 0): ?>
                            <?php foreach ($recent_quizzes as $quiz): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(substr($quiz['title'], 0, 25)) . (strlen($quiz['title']) > 25 ? '...' : ''); ?></td>
                                    <td><?php echo $quiz['total_marks']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($quiz['created_at'])); ?></td>
                                    <td>
                                        <span class="status <?php echo $quiz['is_published'] ? 'published' : 'draft'; ?>">
                                            <?php echo $quiz['is_published'] ? 'Published' : 'Draft'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center; color:#9CA3AF; padding:20px;">No quizzes created yet</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Performance Overview -->
            <div class="table-card animate-in">
                <div class="table-header">
                    <h4>📊 Performance Overview</h4>
                </div>
                <div class="performance-grid">
                    <div class="perf-item">
                        <div class="perf-label">Total Quiz Attempts</div>
                        <div class="perf-value"><?php echo $total_attempts; ?></div>
                    </div>
                    <div class="perf-item">
                        <div class="perf-label">Average Score</div>
                        <div class="perf-value"><?php echo $avg_score; ?>%</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $avg_score; ?>%;"></div>
                        </div>
                    </div>
                    <div class="perf-item">
                        <div class="perf-label">Students Enrolled</div>
                        <div class="perf-value"><?php echo $total_students_performance; ?></div>
                    </div>
                    <div class="perf-item">
                        <div class="perf-label">Completion Rate</div>
                        <div class="perf-value"><?php echo $total_attempts > 0 ? round(($total_attempts / max(1, $total_students_performance * 3)) * 100) : 0; ?>%</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $total_attempts > 0 ? round(($total_attempts / max(1, $total_students_performance * 3)) * 100) : 0; ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =============================================
        THREE COLUMN - Forum, Announcements, Motivation
        ============================================= -->
        <div class="two-column">
            <!-- Forum Preview -->
            <div class="table-card animate-in">
                <div class="table-header">
                    <h4>💬 Recent Forum Posts</h4>
                    <a href="forum.php" style="color:#6C63FF; text-decoration:none; font-weight:600; font-size:14px;">View All →</a>
                </div>
                <?php if (count($forum_posts) > 0): ?>
                    <?php foreach ($forum_posts as $post): ?>
                        <div class="forum-item">
                            <div class="forum-info">
                                <h5><?php echo htmlspecialchars(substr($post['title'], 0, 40)) . (strlen($post['title']) > 40 ? '...' : ''); ?></h5>
                                <div class="forum-meta">
                                    by <?php echo htmlspecialchars($post['author']); ?> • <?php echo timeAgo($post['created_at']); ?>
                                </div>
                            </div>
                            <div class="forum-replies">
                                <i class="fas fa-reply"></i> <?php echo $post['replies']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; color:#9CA3AF; padding:20px;">No forum posts yet</p>
                <?php endif; ?>
            </div>

            <!-- Announcements -->
            <div class="table-card animate-in">
                <div class="table-header">
                    <h4>📢 Announcements</h4>
                </div>
                <div class="announcement-item">
                    <div class="announce-title">📝 New Quiz: PHP Basics</div>
                    <div class="announce-date">Available for students tomorrow</div>
                </div>
                <div class="announcement-item" style="border-left-color: #22C55E;">
                    <div class="announce-title">📚 New Notes Uploaded</div>
                    <div class="announce-date">AI & ML Fundamentals added</div>
                </div>
                <div class="announcement-item" style="border-left-color: #F59E0B;">
                    <div class="announce-title">💬 Forum Discussion: Web Dev</div>
                    <div class="announce-date">Join the conversation</div>
                </div>
            </div>
        </div>

        <!-- =============================================
        MOTIVATIONAL BANNER
        ============================================= -->
        <div class="motivation-banner animate-in">
            <div>
                <div class="motivation-text">
                    🌟 Empowering students through <span>knowledge</span> and <span>interactive learning</span>
                </div>
                <div style="font-size:14px; color:#6B7280; margin-top:4px;">
                    Keep inspiring the next generation of learners!
                </div>
            </div>
            <div class="motivation-icon">🚀</div>
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

        // Close sidebar on window resize (if going to desktop)
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
        const statNumbers = document.querySelectorAll('.stat-number');

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
            const increment = Math.ceil(target / 50);
            const duration = 1500;
            const stepTime = duration / 50;

            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = current;
            }, stepTime);
        }

        // =============================================
        // PROGRESS BAR ANIMATION
        // =============================================
        const progressBars = document.querySelectorAll('.progress-fill');

        const progressObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const width = entry.target.style.width;
                    entry.target.style.width = '0%';
                    setTimeout(() => {
                        entry.target.style.width = width;
                    }, 100);
                    progressObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.3 });

        progressBars.forEach(el => {
            progressObserver.observe(el);
        });

        // =============================================
        // TIME AGO FUNCTION (for PHP fallback)
        // =============================================
        // This is just for display, PHP timeAgo function handles the actual values
        console.log('🎓 EduHack AI - Teacher Dashboard Loaded');
        console.log('👋 Welcome Back, <?php echo htmlspecialchars($teacher_name); ?>');
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