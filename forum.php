<?php
/**
 * =============================================
 * Teacher Forum - EduHack AI
 * =============================================
 * 
 * This page allows teachers to manage discussions,
 * answer questions, and publish announcements.
 */

// Require authentication and teacher role
require_once '../includes/auth.php';
requireTeacher();

// Get teacher info
$teacher_id = getCurrentUserId();
$teacher_name = getCurrentUserFullName();

// Include database connection
require_once '../includes/db.php';
$has_likes = columnExists('forum_posts', 'likes');

// =============================================
// GET FILTERS
// =============================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$category_filter = isset($_GET['category']) ? mysqli_real_escape_string($conn, trim($_GET['category'])) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'latest';
$show_unanswered = isset($_GET['unanswered']) ? true : false;

// =============================================
// HANDLE ANNOUNCEMENT
// =============================================
$announcement_error = '';
$announcement_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title = mysqli_real_escape_string($conn, trim($_POST['announcement_title']));
    $category = mysqli_real_escape_string($conn, trim($_POST['announcement_category']));
    $content = mysqli_real_escape_string($conn, trim($_POST['announcement_content']));
    $priority = mysqli_real_escape_string($conn, trim($_POST['priority']));
    // Determine status from the submit button value: publish button sets create_announcement=1
    $status = (isset($_POST['create_announcement']) && $_POST['create_announcement'] === '1') ? 'published' : 'draft';
    
    if (empty($title) || empty($content)) {
        $announcement_error = 'Please fill in all required fields.';
    } else {
        if (!tableExists('forum_announcements')) {
            $announcement_error = 'Announcements feature is not available because the forum_announcements table is missing.';
        } else {
            $insert_sql = "INSERT INTO forum_announcements (teacher_id, title, category, content, priority, status) 
                       VALUES ($teacher_id, '$title', '$category', '$content', '$priority', '$status')";
            if (mysqli_query($conn, $insert_sql)) {
                $announcement_success = $status == 'published' ? 
                    '✅ Announcement published successfully!' : 
                    '📝 Announcement saved as draft.';
            } else {
                $announcement_error = 'Failed to create announcement.';
            }
        }
    }
}

// =============================================
// HANDLE PIN/UNPIN
// =============================================
if (isset($_GET['pin']) && isset($_GET['id'])) {
    $post_id = (int)$_GET['id'];
    $pin_sql = "UPDATE forum_posts SET is_pinned = 1 WHERE id = $post_id";
    mysqli_query($conn, $pin_sql);
    header("Location: forum.php");
    exit();
}

if (isset($_GET['unpin']) && isset($_GET['id'])) {
    $post_id = (int)$_GET['id'];
    $pin_sql = "UPDATE forum_posts SET is_pinned = 0 WHERE id = $post_id";
    mysqli_query($conn, $pin_sql);
    header("Location: forum.php");
    exit();
}

// =============================================
// HANDLE DELETE POST
// =============================================
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $post_id = (int)$_GET['id'];
    $delete_sql = "DELETE FROM forum_posts WHERE id = $post_id";
    if (mysqli_query($conn, $delete_sql)) {
        // Also delete replies
        mysqli_query($conn, "DELETE FROM forum_replies WHERE post_id = $post_id");
        header("Location: forum.php?deleted=1");
        exit();
    }
}

// =============================================
// HANDLE DELETE REPLY
// =============================================
if (isset($_GET['delete_reply']) && isset($_GET['id'])) {
    $reply_id = (int)$_GET['id'];
    $delete_sql = "DELETE FROM forum_replies WHERE id = $reply_id";
    mysqli_query($conn, $delete_sql);
    header("Location: forum.php");
    exit();
}

// =============================================
// HANDLE REPLY
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reply'])) {
    $post_id = (int)$_POST['post_id'];
    $reply = mysqli_real_escape_string($conn, trim($_POST['reply']));
    
    if (!empty($reply)) {
        $insert_reply = "INSERT INTO forum_replies (post_id, user_id, reply_text) 
                         VALUES ($post_id, $teacher_id, '$reply')";
        mysqli_query($conn, $insert_reply);
        header("Location: forum.php");
        exit();
    }
}

// =============================================
// FETCH STATISTICS
// =============================================
$stats_sql = "SELECT 
                COUNT(*) as total_posts,
                SUM(CASE WHEN (SELECT COUNT(*) FROM forum_replies WHERE post_id = forum_posts.id) = 0 THEN 1 ELSE 0 END) as unanswered,
                COUNT(DISTINCT user_id) as active_students
              FROM forum_posts";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Teacher replies count
$teacher_replies_sql = "SELECT COUNT(*) as total FROM forum_replies WHERE user_id = $teacher_id";
$teacher_replies_result = mysqli_query($conn, $teacher_replies_sql);
$teacher_replies = mysqli_fetch_assoc($teacher_replies_result)['total'] ?? 0;

// =============================================
// FETCH ANNOUNCEMENTS
// =============================================
$announcements = [];
if (tableExists('forum_announcements')) {
    $announcements_sql = "SELECT * FROM forum_announcements 
                      WHERE status = 'published' 
                      ORDER BY priority = 'Urgent' DESC, created_at DESC 
                      LIMIT 3";
    $announcements_result = mysqli_query($conn, $announcements_sql);
    $announcements = mysqli_fetch_all($announcements_result, MYSQLI_ASSOC);
}

// =============================================
// FETCH DISCUSSIONS
// =============================================
$where_conditions = ["1=1"];
if (!empty($search)) {
    $where_conditions[] = "(fp.title LIKE '%$search%' OR fp.content LIKE '%$search%' OR u.full_name LIKE '%$search%')";
}
if (!empty($category_filter)) {
    $where_conditions[] = "fp.category = '$category_filter'";
}
if ($show_unanswered) {
    $where_conditions[] = "(SELECT COUNT(*) FROM forum_replies WHERE post_id = fp.id) = 0";
}
$where_clause = implode(' AND ', $where_conditions);

$order_by = match($sort_by) {
    'replied' => 'reply_count DESC',
    'views' => 'views DESC',
    'unanswered' => 'reply_count ASC',
    default => 'fp.created_at DESC'
};

$posts_sql = "SELECT fp.*, " . ($has_likes ? "COALESCE(fp.likes, 0) as likes, " : "0 as likes, ") . "u.full_name, u.profile_image,
                     (SELECT COUNT(*) FROM forum_replies WHERE post_id = fp.id) as reply_count,
                     (SELECT COUNT(*) FROM forum_replies WHERE post_id = fp.id AND user_id = $teacher_id) as teacher_replied
              FROM forum_posts fp
              JOIN users u ON fp.user_id = u.id
              WHERE $where_clause
              ORDER BY fp.is_pinned DESC, $order_by
              LIMIT 20";
$posts_result = mysqli_query($conn, $posts_sql);
$posts = mysqli_fetch_all($posts_result, MYSQLI_ASSOC);

// =============================================
// FETCH CATEGORIES
// =============================================
$categories = ['Mathematics', 'Physics', 'Chemistry', 'Biology', 'Computer Science', 'English', 'General Discussion'];

// =============================================
// TOP CONTRIBUTORS
// =============================================
$contributors_sql = "SELECT u.id, u.full_name, 
                     COUNT(DISTINCT fp.id) as posts,
                     COUNT(fr.id) as replies,
                     COUNT(DISTINCT fp.id) + COUNT(fr.id) as total_activity
                     FROM users u
                     LEFT JOIN forum_posts fp ON u.id = fp.user_id
                     LEFT JOIN forum_replies fr ON u.id = fr.user_id
                     WHERE u.role = 'student'
                     GROUP BY u.id
                     ORDER BY total_activity DESC
                     LIMIT 5";
$contributors_result = mysqli_query($conn, $contributors_sql);
$contributors = mysqli_fetch_all($contributors_result, MYSQLI_ASSOC);

// =============================================
// POPULAR TOPICS
// =============================================
$topics = ['HTML', 'CSS', 'PHP', 'JavaScript', 'Python', 'Database', 'Algorithms', 'AI', 'Machine Learning'];

// =============================================
// COMMUNITY INSIGHTS
// =============================================
$insights_sql = "SELECT category, COUNT(*) as count FROM forum_posts GROUP BY category ORDER BY count DESC LIMIT 1";
$insights_result = mysqli_query($conn, $insights_sql);
$most_active_category = mysqli_fetch_assoc($insights_result)['category'] ?? 'N/A';

// =============================================
// CURRENT DATE
// =============================================
date_default_timezone_set('Asia/Kolkata');
$current_date = date('l, F j, Y');

// =============================================
// TEACHER ACHIEVEMENTS
// =============================================
$teacher_achievements = [
    ['icon' => '🏆', 'title' => 'Mentor of the Month'],
    ['icon' => '⭐', 'title' => 'Top Contributor'],
    ['icon' => '📚', 'title' => 'Knowledge Guide'],
    ['icon' => '🔥', 'title' => 'Community Leader']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Forum - EduHack AI</title>
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
           CREATE ANNOUNCEMENT CARD
        ============================================= */
        .announcement-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            padding: 24px;
            margin-bottom: 24px;
            transition: all 0.3s ease;
        }
        .announcement-card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.03);
        }
        .announcement-card .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .announcement-card .card-title i { color: #6C63FF; }
        .announcement-card .form-group {
            margin-bottom: 14px;
        }
        .announcement-card .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 4px;
        }
        .announcement-card .form-group .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #E5E7EB;
            border-radius: 10px;
            font-size: 14px;
            color: #1F2937;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        .announcement-card .form-group .form-control:focus {
            outline: none;
            border-color: #6C63FF;
            box-shadow: 0 0 0 4px rgba(108, 99, 255, 0.04);
        }
        .announcement-card .form-group textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        .announcement-card .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
        }
        .btn-announcement {
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
        .btn-announcement-draft {
            background: #F3F4F6;
            color: #6B7280;
        }
        .btn-announcement-draft:hover {
            background: #E5E7EB;
        }
        .btn-announcement-publish {
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            color: white;
        }
        .btn-announcement-publish:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(108, 99, 255, 0.3);
        }

        /* =============================================
           ALERT MESSAGES
        ============================================= */
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.5s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-15px); }
            to { opacity: 1; transform: translateY(0); }
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
        .alert-icon { font-size: 18px; }

        /* =============================================
           ANNOUNCEMENT DISPLAY
        ============================================= */
        .announcement-display {
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            padding: 20px 24px;
            margin-bottom: 24px;
        }
        .announcement-item {
            padding: 12px 0;
            border-bottom: 1px solid #F9FAFB;
        }
        .announcement-item:last-child { border-bottom: none; }
        .announcement-item .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .announcement-item .announcement-header .announcement-title {
            font-size: 16px;
            font-weight: 600;
            color: #1F2937;
        }
        .announcement-item .announcement-header .priority-badge {
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .priority-badge.normal { background: rgba(59, 130, 246, 0.08); color: #3B82F6; }
        .priority-badge.important { background: rgba(245, 158, 11, 0.08); color: #F59E0B; }
        .priority-badge.urgent { background: rgba(239, 68, 68, 0.08); color: #EF4444; }
        .announcement-item .announcement-content {
            font-size: 14px;
            color: #4B5563;
            margin-top: 4px;
            line-height: 1.6;
        }
        .announcement-item .announcement-meta {
            font-size: 12px;
            color: #9CA3AF;
            margin-top: 4px;
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
            flex-wrap: wrap;
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
        .btn-filter-warning {
            background: rgba(245, 158, 11, 0.08);
            color: #F59E0B;
        }
        .btn-filter-warning:hover {
            background: #F59E0B;
            color: white;
        }

        /* =============================================
           DISCUSSION CARDS
        ============================================= */
        .discussion-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            padding: 20px 24px;
            margin-bottom: 16px;
            transition: all 0.4s ease;
        }
        .discussion-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(108, 99, 255, 0.06);
            border-color: rgba(108, 99, 255, 0.1);
        }
        .discussion-card.pinned {
            border-left: 4px solid #FFD700;
            background: rgba(255, 215, 0, 0.02);
        }
        .discussion-card .discussion-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 8px;
        }
        .discussion-card .discussion-header .discussion-author {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .discussion-card .discussion-header .discussion-author .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
        }
        .discussion-card .discussion-header .discussion-author .author-name {
            font-weight: 600;
            color: #1F2937;
            font-size: 14px;
        }
        .discussion-card .discussion-header .discussion-author .post-date {
            font-size: 12px;
            color: #9CA3AF;
        }
        .discussion-card .discussion-header .discussion-badges {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .discussion-card .discussion-header .discussion-badges .badge {
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .discussion-card .discussion-header .discussion-badges .badge.category {
            background: rgba(108, 99, 255, 0.08);
            color: #6C63FF;
        }
        .discussion-card .discussion-header .discussion-badges .badge.pinned {
            background: rgba(255, 215, 0, 0.15);
            color: #F59E0B;
        }
        .discussion-card .discussion-header .discussion-badges .badge.unanswered {
            background: rgba(239, 68, 68, 0.08);
            color: #EF4444;
        }
        .discussion-card .discussion-title {
            font-size: 18px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 6px;
            cursor: pointer;
        }
        .discussion-card .discussion-title:hover {
            color: #6C63FF;
        }
        .discussion-card .discussion-preview {
            font-size: 14px;
            color: #6B7280;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        .discussion-card .discussion-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding-top: 12px;
            border-top: 1px solid #F9FAFB;
        }
        .discussion-card .discussion-footer .discussion-stats {
            display: flex;
            gap: 16px;
            font-size: 13px;
            color: #6B7280;
        }
        .discussion-card .discussion-footer .discussion-stats span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .discussion-card .discussion-footer .discussion-stats span i {
            color: #9CA3AF;
        }
        .discussion-card .discussion-footer .discussion-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .btn-sm {
            padding: 5px 14px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-sm:hover {
            transform: translateY(-2px);
        }
        .btn-sm-primary {
            background: rgba(108, 99, 255, 0.08);
            color: #6C63FF;
        }
        .btn-sm-primary:hover {
            background: #6C63FF;
            color: white;
        }
        .btn-sm-success {
            background: rgba(34, 197, 94, 0.08);
            color: #22C55E;
        }
        .btn-sm-success:hover {
            background: #22C55E;
            color: white;
        }
        .btn-sm-warning {
            background: rgba(245, 158, 11, 0.08);
            color: #F59E0B;
        }
        .btn-sm-warning:hover {
            background: #F59E0B;
            color: white;
        }
        .btn-sm-danger {
            background: rgba(239, 68, 68, 0.08);
            color: #EF4444;
        }
        .btn-sm-danger:hover {
            background: #EF4444;
            color: white;
        }
        .btn-sm-secondary {
            background: #F3F4F6;
            color: #6B7280;
        }
        .btn-sm-secondary:hover {
            background: #E5E7EB;
        }
        .btn-sm-gold {
            background: rgba(255, 215, 0, 0.15);
            color: #F59E0B;
        }
        .btn-sm-gold:hover {
            background: #F59E0B;
            color: white;
        }

        /* =============================================
           TWO COLUMN
        ============================================= */
        .two-column {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-top: 24px;
        }

        /* =============================================
           TOP CONTRIBUTORS
        ============================================= */
        .contributor-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #F9FAFB;
        }
        .contributor-item:last-child { border-bottom: none; }
        .contributor-item .contributor-rank {
            font-weight: 700;
            color: #6C63FF;
            width: 24px;
        }
        .contributor-item .contributor-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 12px;
        }
        .contributor-item .contributor-info { flex: 1; }
        .contributor-item .contributor-info .contributor-name {
            font-size: 14px;
            font-weight: 600;
            color: #1F2937;
        }
        .contributor-item .contributor-info .contributor-stats {
            font-size: 12px;
            color: #6B7280;
        }

        /* =============================================
           POPULAR TOPICS
        ============================================= */
        .topic-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .topic-chip {
            padding: 6px 16px;
            background: #F9FAFB;
            border-radius: 20px;
            font-size: 13px;
            color: #4B5563;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }
        .topic-chip:hover {
            background: rgba(108, 99, 255, 0.08);
            color: #6C63FF;
            transform: translateY(-2px);
        }

        /* =============================================
           ACHIEVEMENTS
        ============================================= */
        .achievement-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #F9FAFB;
        }
        .achievement-item:last-child { border-bottom: none; }
        .achievement-item .ach-icon { font-size: 24px; }
        .achievement-item .ach-title {
            font-size: 14px;
            font-weight: 500;
            color: #1F2937;
        }

        /* =============================================
           GUIDELINES
        ============================================= */
        .guideline-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 0;
            font-size: 14px;
            color: #4B5563;
        }
        .guideline-item i { color: #22C55E; }

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
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
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
        .modal-body .post-content {
            font-size: 15px;
            color: #4B5563;
            line-height: 1.8;
            margin-bottom: 16px;
        }
        .modal-body .post-meta {
            font-size: 13px;
            color: #6B7280;
            margin-bottom: 16px;
        }
        .modal-body .replies-section .reply-item {
            padding: 12px 0;
            border-bottom: 1px solid #F9FAFB;
            display: flex;
            gap: 12px;
        }
        .modal-body .replies-section .reply-item:last-child { border-bottom: none; }
        .modal-body .replies-section .reply-item .reply-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 12px;
            flex-shrink: 0;
        }
        .modal-body .replies-section .reply-item .reply-content .reply-author {
            font-weight: 600;
            font-size: 13px;
            color: #1F2937;
        }
        .modal-body .replies-section .reply-item .reply-content .reply-text {
            font-size: 14px;
            color: #4B5563;
        }
        .modal-body .replies-section .reply-item .reply-content .reply-time {
            font-size: 12px;
            color: #9CA3AF;
        }
        .modal-footer {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px solid #F3F4F6;
            display: flex;
            gap: 12px;
        }
        .modal-footer .form-group {
            flex: 1;
        }
        .modal-footer .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #E5E7EB;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 60px;
            transition: all 0.3s ease;
        }
        .modal-footer .form-group textarea:focus {
            outline: none;
            border-color: #6C63FF;
            box-shadow: 0 0 0 4px rgba(108, 99, 255, 0.04);
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
            .announcement-card .form-row { grid-template-columns: 1fr 1fr; }
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
            .header-left .page-title { font-size: 22px; }
            .header-right { width: 100%; justify-content: flex-start; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .stat-card { padding: 14px 16px; }
            .stat-card .stat-info .stat-number { font-size: 20px; }
            .announcement-card .form-row { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar .filter-group { min-width: 100%; }
            .filter-actions { justify-content: flex-end; }
            .discussion-card { padding: 16px; }
            .discussion-card .discussion-title { font-size: 16px; }
            .modal { padding: 20px; margin: 10px; }
            .modal-footer { flex-direction: column; }
            .discussion-card .discussion-footer { flex-direction: column; align-items: flex-start; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .discussion-card .discussion-header { flex-direction: column; }
            .discussion-card .discussion-header .discussion-badges { margin-top: 4px; }
            .discussion-card .discussion-footer .discussion-stats { flex-wrap: wrap; gap: 8px; }
            .announcement-item .announcement-header { flex-direction: column; align-items: flex-start; }
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
                <a href="forum.php" class="active">
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
                    👨‍🏫 Teacher <span>Discussion Forum</span>
                </div>
                <div class="page-subtitle">
                    Support students and build a collaborative learning community.
                </div>
            </div>
            <div class="header-right">
                <div style="font-size:13px; color:#6B7280;">
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
                <div class="stat-icon purple"><i class="fas fa-comments"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo $stats['total_posts'] ?? 0; ?>">0</div>
                    <div class="stat-label">Total Discussions</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon green"><i class="fas fa-reply"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo $teacher_replies; ?>">0</div>
                    <div class="stat-label">Teacher Replies</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon orange"><i class="fas fa-question-circle"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo $stats['unanswered'] ?? 0; ?>">0</div>
                    <div class="stat-label">Unanswered Questions</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <div class="stat-number" data-count="<?php echo $stats['active_students'] ?? 0; ?>">0</div>
                    <div class="stat-label">Active Students</div>
                </div>
            </div>
        </div>

        <!-- =============================================
        CREATE ANNOUNCEMENT CARD
        ============================================= -->
        <div class="announcement-card animate-in">
            <div class="card-title">
                <i class="fas fa-bullhorn"></i> Create Announcement
            </div>

            <?php if ($announcement_error): ?>
                <div class="alert alert-error">
                    <span class="alert-icon">⚠️</span>
                    <?php echo htmlspecialchars($announcement_error); ?>
                </div>
            <?php endif; ?>

            <?php if ($announcement_success): ?>
                <div class="alert alert-success">
                    <span class="alert-icon">✅</span>
                    <?php echo htmlspecialchars($announcement_success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Announcement Title</label>
                        <input type="text" name="announcement_title" class="form-control" placeholder="Enter announcement title" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="announcement_category" class="form-control">
                            <option value="General">General</option>
                            <option value="Exam Update">Exam Update</option>
                            <option value="Assignment">Assignment</option>
                            <option value="Study Material">Study Material</option>
                            <option value="Important Notice">Important Notice</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority" class="form-control">
                            <option value="Normal">Normal</option>
                            <option value="Important">Important</option>
                            <option value="Urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Announcement Content</label>
                    <textarea name="announcement_content" class="form-control" placeholder="Write your announcement..." required></textarea>
                </div>
                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                    <button type="submit" name="create_announcement" class="btn-announcement btn-announcement-draft">
                        <i class="fas fa-save"></i> Save Draft
                    </button>
                    <button type="submit" name="create_announcement" value="1" class="btn-announcement btn-announcement-publish">
                        <i class="fas fa-globe"></i> Publish Announcement
                    </button>
                </div>
            </form>
        </div>

        <!-- =============================================
        ANNOUNCEMENTS DISPLAY
        ============================================= -->
        <?php if (count($announcements) > 0): ?>
            <div class="announcement-display animate-in">
                <h4 style="font-size:16px; font-weight:700; color:#1F2937; margin-bottom:12px;">
                    <i class="fas fa-bullhorn" style="color:#6C63FF;"></i> Latest Announcements
                </h4>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-item">
                        <div class="announcement-header">
                            <span class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></span>
                            <span class="priority-badge <?php echo strtolower($announcement['priority']); ?>">
                                <?php echo $announcement['priority']; ?>
                            </span>
                        </div>
                        <div class="announcement-content"><?php echo htmlspecialchars($announcement['content']); ?></div>
                        <div class="announcement-meta">
                            📂 <?php echo $announcement['category']; ?> • 
                            📅 <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- =============================================
        FILTER BAR
        ============================================= -->
        <form class="filter-bar" method="GET" action="">
            <div class="filter-group">
                <label><i class="fas fa-search"></i></label>
                <input type="text" name="search" placeholder="Search by title, student, or keyword..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group">
                <label>Category</label>
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat; ?>" <?php echo $category_filter == $cat ? 'selected' : ''; ?>>
                            <?php echo $cat; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Sort</label>
                <select name="sort">
                    <option value="latest" <?php echo $sort_by == 'latest' ? 'selected' : ''; ?>>Latest</option>
                    <option value="replied" <?php echo $sort_by == 'replied' ? 'selected' : ''; ?>>Most Replied</option>
                    <option value="views" <?php echo $sort_by == 'views' ? 'selected' : ''; ?>>Most Viewed</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter btn-filter-primary">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <a href="forum.php?unanswered=1" class="btn-filter btn-filter-warning">
                    <i class="fas fa-question-circle"></i> Unanswered
                </a>
                <a href="forum.php" class="btn-filter btn-filter-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>

        <!-- =============================================
        DISCUSSION LIST
        ============================================= -->
        <?php if (count($posts) > 0): ?>
            <?php foreach ($posts as $post): 
                $is_unanswered = $post['reply_count'] == 0;
            ?>
                <div class="discussion-card <?php echo $post['is_pinned'] ? 'pinned' : ''; ?>">
                    <div class="discussion-header">
                        <div class="discussion-author">
                            <div class="avatar">
                                <?php echo strtoupper(substr($post['full_name'], 0, 2)); ?>
                            </div>
                            <div>
                                <div class="author-name"><?php echo htmlspecialchars($post['full_name']); ?></div>
                                <div class="post-date"><?php echo timeAgo($post['created_at']); ?></div>
                            </div>
                        </div>
                        <div class="discussion-badges">
                            <span class="badge category"><?php echo htmlspecialchars($post['category']); ?></span>
                            <?php if ($post['is_pinned']): ?>
                                <span class="badge pinned"><i class="fas fa-thumbtack"></i> Pinned</span>
                            <?php endif; ?>
                            <?php if ($is_unanswered): ?>
                                <span class="badge unanswered">Unanswered</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="discussion-title" onclick="viewDiscussion(<?php echo $post['id']; ?>)">
                        <?php echo htmlspecialchars($post['title']); ?>
                    </div>
                    <div class="discussion-preview">
                        <?php echo htmlspecialchars(substr($post['content'], 0, 150)) . (strlen($post['content']) > 150 ? '...' : ''); ?>
                    </div>
                    <div class="discussion-footer">
                        <div class="discussion-stats">
                            <span><i class="fas fa-eye"></i> <?php echo $post['views']; ?></span>
                            <span><i class="fas fa-reply"></i> <?php echo $post['reply_count']; ?> replies</span>
                            <span><i class="fas fa-thumbs-up"></i> <?php echo $post['likes']; ?> likes</span>
                        </div>
                        <div class="discussion-actions">
                            <button class="btn-sm btn-sm-primary" onclick="viewDiscussion(<?php echo $post['id']; ?>)">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn-sm btn-sm-success" onclick="viewDiscussion(<?php echo $post['id']; ?>)">
                                <i class="fas fa-reply"></i> Reply
                            </button>
                            <?php if ($post['is_pinned']): ?>
                                <a href="?unpin=1&id=<?php echo $post['id']; ?>" class="btn-sm btn-sm-secondary">
                                    <i class="fas fa-thumbtack"></i> Unpin
                                </a>
                            <?php else: ?>
                                <a href="?pin=1&id=<?php echo $post['id']; ?>" class="btn-sm btn-sm-gold">
                                    <i class="fas fa-thumbtack"></i> Pin
                                </a>
                            <?php endif; ?>
                            <a href="?delete=1&id=<?php echo $post['id']; ?>" class="btn-sm btn-sm-danger" 
                               onclick="return confirm('Are you sure you want to delete this discussion? This action cannot be undone.')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align:center; padding:40px; background:white; border-radius:16px; border:1px solid #F3F4F6;">
                <div style="font-size:48px; margin-bottom:12px;">💬</div>
                <h3>No Discussions Found</h3>
                <p style="color:#6B7280;">Students haven't started any discussions yet.</p>
            </div>
        <?php endif; ?>

        <!-- =============================================
        TWO COLUMN - Sidebar Content
        ============================================= -->
        <div class="two-column">
            <!-- Left Column: More content or empty -->

            <!-- Right Column: Sidebar -->
            <div>
                <!-- Top Contributors -->
                <div class="card" style="background:white; border-radius:16px; border:1px solid #F3F4F6; padding:20px; margin-bottom:16px;">
                    <h4 style="font-size:16px; font-weight:700; color:#1F2937; margin-bottom:12px;">
                        <i class="fas fa-star" style="color:#6C63FF;"></i> Top Student Contributors
                    </h4>
                    <?php if (count($contributors) > 0): ?>
                        <?php foreach ($contributors as $index => $contributor): ?>
                            <div class="contributor-item">
                                <span class="contributor-rank">#<?php echo $index + 1; ?></span>
                                <div class="contributor-avatar">
                                    <?php echo strtoupper(substr($contributor['full_name'], 0, 2)); ?>
                                </div>
                                <div class="contributor-info">
                                    <div class="contributor-name"><?php echo htmlspecialchars($contributor['full_name']); ?></div>
                                    <div class="contributor-stats">
                                        📝 <?php echo $contributor['posts']; ?> posts • 💬 <?php echo $contributor['replies']; ?> replies
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#9CA3AF; text-align:center; padding:10px;">No contributors yet</p>
                    <?php endif; ?>
                </div>

                <!-- Community Insights -->
                <div class="card" style="background:white; border-radius:16px; border:1px solid #F3F4F6; padding:20px; margin-bottom:16px;">
                    <h4 style="font-size:16px; font-weight:700; color:#1F2937; margin-bottom:12px;">
                        <i class="fas fa-lightbulb" style="color:#6C63FF;"></i> Community Insights
                    </h4>
                    <div style="display:grid; gap:8px;">
                        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #F9FAFB;">
                            <span style="color:#6B7280;">Most Active Category</span>
                            <span style="font-weight:600; color:#6C63FF;"><?php echo htmlspecialchars($most_active_category); ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #F9FAFB;">
                            <span style="color:#6B7280;">Total Discussions</span>
                            <span style="font-weight:600; color:#6C63FF;"><?php echo $stats['total_posts'] ?? 0; ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:6px 0;">
                            <span style="color:#6B7280;">Unanswered Questions</span>
                            <span style="font-weight:600; color:#EF4444;"><?php echo $stats['unanswered'] ?? 0; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Popular Topics -->
                <div class="card" style="background:white; border-radius:16px; border:1px solid #F3F4F6; padding:20px; margin-bottom:16px;">
                    <h4 style="font-size:16px; font-weight:700; color:#1F2937; margin-bottom:12px;">
                        <i class="fas fa-tags" style="color:#6C63FF;"></i> Popular Topics
                    </h4>
                    <div class="topic-chips">
                        <?php foreach ($topics as $topic): ?>
                            <a href="?search=<?php echo urlencode($topic); ?>" class="topic-chip">
                                <?php echo $topic; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Teacher Achievements -->
                <div class="card" style="background:white; border-radius:16px; border:1px solid #F3F4F6; padding:20px; margin-bottom:16px;">
                    <h4 style="font-size:16px; font-weight:700; color:#1F2937; margin-bottom:12px;">
                        <i class="fas fa-award" style="color:#6C63FF;"></i> Your Achievements
                    </h4>
                    <?php foreach ($teacher_achievements as $ach): ?>
                        <div class="achievement-item">
                            <span class="ach-icon"><?php echo $ach['icon']; ?></span>
                            <span class="ach-title"><?php echo $ach['title']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Guidelines -->
                <div class="card" style="background:white; border-radius:16px; border:1px solid #F3F4F6; padding:20px;">
                    <h4 style="font-size:16px; font-weight:700; color:#1F2937; margin-bottom:12px;">
                        <i class="fas fa-list-check" style="color:#22C55E;"></i> Community Guidelines
                    </h4>
                    <div class="guideline-item"><i class="fas fa-check-circle"></i> Respect everyone</div>
                    <div class="guideline-item"><i class="fas fa-check-circle"></i> Encourage learning</div>
                    <div class="guideline-item"><i class="fas fa-check-circle"></i> Stay relevant</div>
                    <div class="guideline-item"><i class="fas fa-check-circle"></i> Avoid spam</div>
                    <div class="guideline-item"><i class="fas fa-check-circle"></i> Support peers</div>
                </div>
            </div>
        </div>

    </main>

    <!-- =============================================
    DISCUSSION DETAILS MODAL
    ============================================= -->
    <div class="modal-overlay" id="discussionModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modalTitle">Discussion</h3>
                <button class="modal-close" onclick="closeModal('discussionModal')">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Dynamic content -->
            </div>
            <div class="modal-footer" id="modalFooter">
                <form method="POST" style="display:flex; gap:12px; width:100%; flex-wrap:wrap;">
                    <input type="hidden" name="post_id" id="replyPostId">
                    <div class="form-group" style="flex:1; min-width:200px;">
                        <textarea name="reply" placeholder="Write your reply as a teacher..." required></textarea>
                    </div>
                    <button type="submit" name="submit_reply" class="btn-post" style="align-self:flex-end; padding:10px 28px; background:linear-gradient(135deg,#6C63FF,#8B5CF6); color:white; border:none; border-radius:10px; font-weight:600; cursor:pointer;">
                        <i class="fas fa-paper-plane"></i> Reply
                    </button>
                </form>
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
        // VIEW DISCUSSION
        // =============================================
        function viewDiscussion(postId) {
            fetch(`get_post.php?id=${postId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const post = data.post;
                        const replies = data.replies || [];
                        
                        document.getElementById('modalTitle').textContent = post.title;
                        document.getElementById('replyPostId').value = post.id;
                        
                        let repliesHtml = '';
                        if (replies.length > 0) {
                            repliesHtml = replies.map(r => `
                                <div class="reply-item">
                                    <div class="reply-avatar">${r.avatar || 'U'}</div>
                                    <div class="reply-content">
                                        <div class="reply-author">
                                            ${r.full_name}
                                            ${r.is_teacher ? ' <span style="font-size:11px; color:#6C63FF; font-weight:600;">👨‍🏫 Teacher</span>' : ''}
                                        </div>
                                        <div class="reply-text">${r.reply_text}</div>
                                        <div class="reply-time">${r.time_ago || 'Just now'}</div>
                                        ${r.is_teacher ? '<a href="?delete_reply=1&id=' + r.id + '" class="btn-sm btn-sm-danger" style="margin-top:4px;" onclick="return confirm(\'Delete this reply?\')"><i class="fas fa-trash"></i> Delete</a>' : ''}
                                    </div>
                                </div>
                            `).join('');
                        } else {
                            repliesHtml = '<p style="color:#9CA3AF; text-align:center; padding:10px;">No replies yet. Be the first teacher to reply!</p>';
                        }
                        
                        document.getElementById('modalBody').innerHTML = `
                            <div class="post-content">${post.content}</div>
                            <div class="post-meta">
                                <span><i class="fas fa-user"></i> ${post.full_name}</span>
                                <span style="margin-left:12px;"><i class="fas fa-calendar-alt"></i> ${post.created_at}</span>
                                <span style="margin-left:12px;"><i class="fas fa-tag"></i> ${post.category}</span>
                                <span style="margin-left:12px;"><i class="fas fa-eye"></i> ${post.views} views</span>
                                <span style="margin-left:12px;"><i class="fas fa-thumbs-up"></i> ${post.likes} likes</span>
                            </div>
                            <hr style="margin:16px 0; border:none; border-top:1px solid #F3F4F6;">
                            <h4 style="font-size:16px; font-weight:700; color:#1F2937; margin-bottom:12px;">
                                💬 Replies (${replies.length})
                            </h4>
                            <div class="replies-section">
                                ${repliesHtml}
                            </div>
                        `;
                        
                        openModal('discussionModal');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load discussion details.');
                });
        }

        // =============================================
        // AUTO-DISMISS ALERTS
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

        // =============================================
        // CONSOLE LOG
        // =============================================
        console.log('👨‍🏫 EduHack AI - Teacher Forum');
        console.log('📝 Total Discussions: <?php echo $stats['total_posts'] ?? 0; ?>');
        console.log('💬 Teacher Replies: <?php echo $teacher_replies; ?>');
        console.log('❓ Unanswered: <?php echo $stats['unanswered'] ?? 0; ?>');
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