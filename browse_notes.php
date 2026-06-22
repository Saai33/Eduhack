<?php
/**
 * =============================================
 * Browse Notes - EduHack AI Student Panel
 * =============================================
 * 
 * This page allows students to browse, search, and
 * filter all available study notes.
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
// HANDLE DOWNLOAD REQUEST
// =============================================
if (isset($_GET['download']) && isset($_GET['id'])) {
    $note_id = (int)$_GET['id'];
    
    // Update download count
    $update_sql = "UPDATE notes SET downloads = downloads + 1 WHERE id = $note_id";
    mysqli_query($conn, $update_sql);
    
    // Get file path
    $file_sql = "SELECT file_path, file_name FROM notes WHERE id = $note_id AND is_published = 1";
    $file_result = mysqli_query($conn, $file_sql);
    
    if (mysqli_num_rows($file_result) > 0) {
        $file = mysqli_fetch_assoc($file_result);
        $file_path = '../' . $file['file_path'];
        
        if (file_exists($file_path)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file['file_name'] . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit();
        }
    }
}

// =============================================
// GET FILTERS
// =============================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$subject_filter = isset($_GET['subject']) ? mysqli_real_escape_string($conn, trim($_GET['subject'])) : '';
$category_filter = isset($_GET['category']) ? mysqli_real_escape_string($conn, trim($_GET['category'])) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'latest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 9;
$offset = ($page - 1) * $limit;

// =============================================
// BUILD QUERY
// =============================================
$where_conditions = ["is_published = 1"];

if (!empty($search)) {
    $where_conditions[] = "(title LIKE '%$search%' OR subject LIKE '%$search%' OR description LIKE '%$search%')";
}

if (!empty($subject_filter)) {
    $where_conditions[] = "subject = '$subject_filter'";
}

if (!empty($category_filter)) {
    $where_conditions[] = "category = '$category_filter'";
}

$where_clause = implode(' AND ', $where_conditions);

// Sort
$order_by = match($sort_by) {
    'oldest' => 'created_at ASC',
    'views' => 'views DESC',
    default => 'created_at DESC'
};

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM notes WHERE $where_clause";
$count_result = mysqli_query($conn, $count_sql);
$total_notes = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_notes / $limit);

// Get notes
$notes_sql = "SELECT * FROM notes WHERE $where_clause ORDER BY $order_by LIMIT $limit OFFSET $offset";
$notes_result = mysqli_query($conn, $notes_sql);
$notes = mysqli_fetch_all($notes_result, MYSQLI_ASSOC);

// =============================================
// GET STATISTICS
// =============================================
$stats_sql = "SELECT 
                COUNT(*) as total_notes,
                COUNT(DISTINCT subject) as total_subjects,
                subject as popular_subject,
                COUNT(*) as new_this_week
              FROM notes 
              WHERE is_published = 1";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Most popular subject
$popular_sql = "SELECT subject, COUNT(*) as count 
                FROM notes 
                WHERE is_published = 1 
                GROUP BY subject 
                ORDER BY count DESC 
                LIMIT 1";
$popular_result = mysqli_query($conn, $popular_sql);
$popular = mysqli_fetch_assoc($popular_result);

// New this week
$new_week_sql = "SELECT COUNT(*) as total 
                 FROM notes 
                 WHERE is_published = 1 
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$new_week_result = mysqli_query($conn, $new_week_sql);
$new_week = mysqli_fetch_assoc($new_week_result);

// =============================================
// GET SUBJECTS FOR FILTER
// =============================================
$subjects_sql = "SELECT DISTINCT subject FROM notes WHERE is_published = 1 ORDER BY subject";
$subjects_result = mysqli_query($conn, $subjects_sql);
$subjects = mysqli_fetch_all($subjects_result, MYSQLI_ASSOC);

// =============================================
// GET FEATURED NOTES
// =============================================
$featured_sql = "SELECT * FROM notes 
                 WHERE is_published = 1 
                 ORDER BY views DESC, created_at DESC 
                 LIMIT 3";
$featured_result = mysqli_query($conn, $featured_sql);
$featured_notes = mysqli_fetch_all($featured_result, MYSQLI_ASSOC);

// =============================================
// CURRENT DATE
// =============================================
date_default_timezone_set('Asia/Kolkata');
$current_date = date('l, F j, Y');

// =============================================
// STUDY TIPS
// =============================================
$study_tips = [
    ['icon' => '📚', 'title' => 'Read Consistently', 'desc' => 'Dedicate at least 30 minutes daily'],
    ['icon' => '📝', 'title' => 'Take Notes', 'desc' => 'Write summaries to reinforce learning'],
    ['icon' => '🎯', 'title' => 'Practice Quizzes', 'desc' => 'Test your knowledge regularly'],
    ['icon' => '⏰', 'title' => 'Study Daily', 'desc' => 'Build a consistent study routine']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Notes - EduHack AI</title>
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
        .search-bar {
            display: flex;
            align-items: center;
            background: white;
            border: 1px solid #E5E7EB;
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
            width: 160px;
        }
        .search-bar i { color: #9CA3AF; }
        .header-btn {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid #E5E7EB;
            background: white;
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
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: white;
            padding: 16px 20px;
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
           FEATURED NOTES
        ============================================= */
        .featured-section {
            margin-bottom: 32px;
        }
        .featured-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .featured-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            overflow: hidden;
            transition: all 0.4s ease;
        }
        .featured-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 50px rgba(108, 99, 255, 0.08);
            border-color: rgba(108, 99, 255, 0.15);
        }
        .featured-card .featured-thumb {
            height: 140px;
            background: linear-gradient(135deg, rgba(108, 99, 255, 0.08), rgba(139, 92, 246, 0.04));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #6C63FF;
            position: relative;
        }
        .featured-card .featured-thumb .featured-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 4px 14px;
            background: #6C63FF;
            color: white;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .featured-card .featured-body {
            padding: 16px 20px 20px;
        }
        .featured-card .featured-body .featured-tags {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        .featured-card .featured-body .featured-tags span {
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .featured-card .featured-body .featured-tags .subject-tag {
            background: rgba(108, 99, 255, 0.08);
            color: #6C63FF;
        }
        .featured-card .featured-body .featured-tags .diff-tag {
            background: rgba(245, 158, 11, 0.08);
            color: #F59E0B;
        }
        .featured-card .featured-body h4 {
            font-size: 18px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 4px;
        }
        .featured-card .featured-body p {
            font-size: 14px;
            color: #6B7280;
            line-height: 1.5;
            margin-bottom: 12px;
        }
        .featured-card .featured-body .featured-meta {
            display: flex;
            gap: 16px;
            font-size: 13px;
            color: #6B7280;
            margin-bottom: 12px;
        }
        .featured-card .featured-body .featured-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* =============================================
           NOTES GRID
        ============================================= */
        .notes-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        .note-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            overflow: hidden;
            transition: all 0.4s ease;
        }
        .note-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 40px rgba(108, 99, 255, 0.06);
            border-color: rgba(108, 99, 255, 0.1);
        }
        .note-card .note-thumb {
            height: 120px;
            background: linear-gradient(135deg, rgba(108, 99, 255, 0.06), rgba(139, 92, 246, 0.03));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: #6C63FF;
            position: relative;
        }
        .note-card .note-thumb .note-type {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 3px 12px;
            background: white;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            color: #6B7280;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .note-card .note-body {
            padding: 14px 18px 18px;
        }
        .note-card .note-body .note-tags {
            display: flex;
            gap: 6px;
            margin-bottom: 6px;
            flex-wrap: wrap;
        }
        .note-card .note-body .note-tags span {
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
        }
        .note-card .note-body .note-tags .subject-tag {
            background: rgba(108, 99, 255, 0.08);
            color: #6C63FF;
        }
        .note-card .note-body .note-tags .diff-tag {
            background: rgba(245, 158, 11, 0.08);
            color: #F59E0B;
        }
        .note-card .note-body h5 {
            font-size: 16px;
            font-weight: 600;
            color: #1F2937;
            margin-bottom: 4px;
            line-height: 1.3;
        }
        .note-card .note-body .note-desc {
            font-size: 13px;
            color: #6B7280;
            line-height: 1.5;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .note-card .note-body .note-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #6B7280;
            padding-top: 10px;
            border-top: 1px solid #F9FAFB;
        }
        .note-card .note-body .note-meta .meta-left {
            display: flex;
            gap: 12px;
        }
        .note-card .note-body .note-meta .meta-left span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .note-card .note-body .note-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        .btn-sm {
            padding: 6px 16px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-sm-primary {
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            color: white;
        }
        .btn-sm-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(108, 99, 255, 0.25);
        }
        .btn-sm-secondary {
            background: #F3F4F6;
            color: #6B7280;
        }
        .btn-sm-secondary:hover {
            background: #E5E7EB;
            transform: translateY(-2px);
        }

        /* =============================================
           STUDY TIPS
        ============================================= */
        .tips-section {
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            padding: 24px;
            margin-top: 24px;
        }
        .tips-section .tips-title {
            font-size: 18px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .tips-section .tips-title i { color: #6C63FF; }
        .tips-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }
        .tip-item {
            text-align: center;
            padding: 16px;
            background: #F9FAFB;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        .tip-item:hover {
            background: rgba(108, 99, 255, 0.04);
            transform: translateY(-4px);
        }
        .tip-item .tip-icon { font-size: 32px; display: block; margin-bottom: 6px; }
        .tip-item .tip-title { font-size: 14px; font-weight: 600; color: #1F2937; }
        .tip-item .tip-desc { font-size: 12px; color: #6B7280; }

        /* =============================================
           PAGINATION
        ============================================= */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            align-items: center;
            padding: 12px 0;
        }
        .pagination a, .pagination span {
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            color: #6B7280;
            border: 1px solid #E5E7EB;
            background: white;
        }
        .pagination a:hover {
            border-color: #6C63FF;
            color: #6C63FF;
            transform: translateY(-2px);
        }
        .pagination .active {
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            color: white;
            border-color: #6C63FF;
        }
        .pagination .disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* =============================================
           EMPTY STATE
        ============================================= */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
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
            .featured-grid { grid-template-columns: repeat(2, 1fr); }
            .notes-grid { grid-template-columns: repeat(2, 1fr); }
            .tips-grid { grid-template-columns: repeat(2, 1fr); }
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
            .header-right { width: 100%; justify-content: space-between; }
            .search-bar { flex: 1; }
            .search-bar input { width: 100%; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .featured-grid { grid-template-columns: 1fr; }
            .notes-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .tips-grid { grid-template-columns: 1fr 1fr; }
            .stat-card { padding: 12px 16px; }
            .stat-card .stat-info .stat-number { font-size: 20px; }
            .header-left .page-title { font-size: 22px; }
            .featured-card .featured-thumb { height: 100px; }
            .note-card .note-thumb { height: 80px; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .notes-grid { grid-template-columns: 1fr; }
            .tips-grid { grid-template-columns: 1fr; }
            .filter-actions { flex-direction: column; }
            .btn-filter { width: 100%; justify-content: center; }
            .featured-card .featured-body h4 { font-size: 16px; }
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
                <a href="browse_notes.php" class="active">
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
                    📚 Study <span>Notes Library</span>
                </div>
                <div class="page-subtitle">
                    Explore, learn and improve with curated study materials.
                </div>
            </div>
            <div class="header-right">
                <div style="font-size:14px; color:#6B7280;">
                    <i class="far fa-calendar-alt"></i> <?php echo $current_date; ?>
                </div>
                <a href="#" class="header-btn">
                    <i class="far fa-bell"></i>
                    <span class="badge">3</span>
                </a>
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
                <div class="stat-icon purple"><i class="fas fa-book"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $stats['total_notes'] ?? 0; ?></div>
                    <div class="stat-label">Total Notes</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon green"><i class="fas fa-tag"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $stats['total_subjects'] ?? 0; ?></div>
                    <div class="stat-label">Subjects Covered</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon orange"><i class="fas fa-fire"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $popular['subject'] ?? 'N/A'; ?></div>
                    <div class="stat-label">Popular Subject</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon blue"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $new_week['total'] ?? 0; ?></div>
                    <div class="stat-label">New This Week</div>
                </div>
            </div>
        </div>

        <!-- =============================================
        FILTER BAR
        ============================================= -->
        <form class="filter-bar" method="GET" action="">
            <div class="filter-group">
                <label><i class="fas fa-search"></i></label>
                <input type="text" name="search" placeholder="Search notes..." 
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
                    <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                    <option value="views" <?php echo $sort_by == 'views' ? 'selected' : ''; ?>>Most Viewed</option>
                </select>
            </div>
            <div class="filter-actions">
                <label>Sort</label>
                <select name="sort">
                    <option value="latest" <?php echo $sort_by == 'latest' ? 'selected' : ''; ?>>Latest</option>
                    <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                    <option value="views" <?php echo $sort_by == 'views' ? 'selected' : ''; ?>>Most Viewed</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter btn-filter-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="browse_notes.php" class="btn-filter btn-filter-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>

        <!-- =============================================
        FEATURED NOTES
        ============================================= -->
        <?php if (count($featured_notes) > 0): ?>
        <section class="featured-section">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="font-size:20px; font-weight:700; color:#1F2937;">
                    ⭐ Featured Notes
                </h3>
                <span style="font-size:13px; color:#6B7280;">Most popular study materials</span>
            </div>
            <div class="featured-grid">
                <?php foreach ($featured_notes as $note): ?>
                    <div class="featured-card animate-in">
                        <div class="featured-thumb">
                            📚
                            <span class="featured-badge">🔥 Popular</span>
                        </div>
                        <div class="featured-body">
                            <div class="featured-tags">
                                <span class="subject-tag"><?php echo htmlspecialchars($note['subject']); ?></span>
                            </div>
                            <h4><?php echo htmlspecialchars(substr($note['title'], 0, 35)) . (strlen($note['title']) > 35 ? '...' : ''); ?></h4>
                            <p><?php echo htmlspecialchars(substr($note['description'] ?? '', 0, 60)) . (strlen($note['description'] ?? '') > 60 ? '...' : ''); ?></p>
                            <div class="featured-meta">
                                <span><i class="fas fa-eye"></i> <?php echo $note['views'] ?? 0; ?></span>
                                <span><i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($note['created_at'])); ?></span>
                            </div>
                            <a href="view_note.php?id=<?php echo $note['id']; ?>" class="btn-sm btn-sm-primary">
                                <i class="fas fa-eye"></i> View Note
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- =============================================
        NOTES GRID
        ============================================= -->
        <section>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="font-size:20px; font-weight:700; color:#1F2937;">
                    📖 All Notes
                    <span style="font-size:14px; font-weight:400; color:#6B7280; margin-left:8px;">
                        (<?php echo $total_notes; ?> available)
                    </span>
                </h3>
            </div>

            <?php if (count($notes) > 0): ?>
                <div class="notes-grid">
                    <?php foreach ($notes as $note): ?>
                        <div class="note-card animate-in">
                            <div class="note-thumb">
                                📚
                                <span class="note-type"><?php echo htmlspecialchars($note['file_type'] ?? 'pdf'); ?></span>
                            </div>
                            <div class="note-body">
                                <div class="note-tags">
                                    <span class="subject-tag"><?php echo htmlspecialchars($note['subject']); ?></span>
                                </div>
                                <h5><?php echo htmlspecialchars(substr($note['title'], 0, 30)) . (strlen($note['title']) > 30 ? '...' : ''); ?></h5>
                                <div class="note-desc">
                                    <?php echo htmlspecialchars(substr($note['description'] ?? '', 0, 60)) . (strlen($note['description'] ?? '') > 60 ? '...' : ''); ?>
                                </div>
                                <div class="note-meta">
                                    <div class="meta-left">
                                        <span><i class="fas fa-eye"></i> <?php echo $note['views'] ?? 0; ?></span>
                                    </div>
                                    <span><?php echo date('M d', strtotime($note['created_at'])); ?></span>
                                </div>
                                <div class="note-actions">
                                    <a href="view_note.php?id=<?php echo $note['id']; ?>" class="btn-sm btn-sm-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="?download=1&id=<?php echo $note['id']; ?>" class="btn-sm btn-sm-secondary">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&subject=<?php echo urlencode($subject_filter); ?>&difficulty=<?php echo urlencode($difficulty_filter); ?>&sort=<?php echo urlencode($sort_by); ?>">
                                <i class="fas fa-chevron-left"></i> Prev
                            </a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-chevron-left"></i> Prev</span>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&subject=<?php echo urlencode($subject_filter); ?>&difficulty=<?php echo urlencode($difficulty_filter); ?>&sort=<?php echo urlencode($sort_by); ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&subject=<?php echo urlencode($subject_filter); ?>&difficulty=<?php echo urlencode($difficulty_filter); ?>&sort=<?php echo urlencode($sort_by); ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <h4>No Notes Found</h4>
                    <p>There are no study notes available matching your criteria.</p>
                    <a href="browse_notes.php" class="btn-sm btn-sm-primary" style="margin-top:12px; padding:10px 28px;">
                        <i class="fas fa-redo"></i> Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </section>

        <!-- =============================================
        STUDY TIPS
        ============================================= -->
        <section class="tips-section animate-in">
            <div class="tips-title">
                <i class="fas fa-lightbulb"></i> Study Tips for Success
            </div>
            <div class="tips-grid">
                <?php foreach ($study_tips as $tip): ?>
                    <div class="tip-item">
                        <span class="tip-icon"><?php echo $tip['icon']; ?></span>
                        <div class="tip-title"><?php echo $tip['title']; ?></div>
                        <div class="tip-desc"><?php echo $tip['desc']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

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
        // LIVE SEARCH (Quick search on input)
        // =============================================
        let searchTimeout;
        const searchInput = document.querySelector('.filter-bar input[name="search"]');
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.closest('form').submit();
                }, 500);
            });
        }

        // =============================================
        // CONSOLE LOG
        // =============================================
        console.log('📚 EduHack AI - Browse Notes Page Loaded');
        console.log('📖 Total Notes Available: <?php echo $total_notes; ?>');
        console.log('👋 Welcome, <?php echo htmlspecialchars($student_name); ?>');
    </script>

</body>
</html>