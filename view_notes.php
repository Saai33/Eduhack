<?php
/**
 * =============================================
 * View Notes - EduHack AI Teacher Panel
 * =============================================
 * 
 * This page allows teachers to view, search, filter,
 * edit, and delete their uploaded notes.
 */

// Require authentication and teacher role
require_once '../includes/auth.php';
requireTeacher();

// Get teacher info
$teacher_id = getCurrentUserId();
$teacher_name = getCurrentUserFullName();

// Include database connection
require_once '../includes/db.php';

$error = '';
$success = '';

// =============================================
// HANDLE DELETE REQUEST
// =============================================
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $note_id = (int)$_GET['id'];
    
    // Verify note belongs to this teacher
    $check_sql = "SELECT file_path FROM notes WHERE id = $note_id AND teacher_id = $teacher_id";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $note = mysqli_fetch_assoc($check_result);
        
        // Delete file from server
        $file_path = '../' . $note['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete from database
        $delete_sql = "DELETE FROM notes WHERE id = $note_id AND teacher_id = $teacher_id";
        if (mysqli_query($conn, $delete_sql)) {
            $success = 'Note deleted successfully!';
        } else {
            $error = 'Failed to delete note.';
        }
    } else {
        $error = 'Note not found or unauthorized.';
    }
}

// =============================================
// HANDLE EDIT REQUEST
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_note'])) {
    $note_id = (int)$_POST['note_id'];
    $title = mysqli_real_escape_string($conn, trim($_POST['title']));
    $subject = mysqli_real_escape_string($conn, trim($_POST['subject']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $summary = mysqli_real_escape_string($conn, trim($_POST['summary']));
    $topics = mysqli_real_escape_string($conn, trim($_POST['topics']));
    
    if (empty($title) || empty($subject) || empty($description)) {
        $error = 'Please fill in all required fields.';
    } else {
        $update_sql = "UPDATE notes SET 
                        title = '$title',
                        subject = '$subject',
                        description = '$description',
                        summary = '$summary',
                        topics = '$topics'
                      WHERE id = $note_id AND teacher_id = $teacher_id";
        
        if (mysqli_query($conn, $update_sql)) {
            $success = 'Note updated successfully!';
        } else {
            $error = 'Failed to update note: ' . mysqli_error($conn);
        }
    }
}

// =============================================
// FETCH STATISTICS
// =============================================
$stats_sql = "SELECT 
                COUNT(*) as total_notes,
                SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) as published_notes,
                SUM(CASE WHEN is_published = 0 THEN 1 ELSE 0 END) as draft_notes,
                SUM(downloads) as total_downloads
              FROM notes 
              WHERE teacher_id = $teacher_id";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// =============================================
// FETCH NOTES WITH FILTERS
// =============================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$subject_filter = isset($_GET['subject']) ? mysqli_real_escape_string($conn, trim($_GET['subject'])) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'latest';
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'table';

// Build WHERE clause
$where_conditions = ["teacher_id = $teacher_id"];

if (!empty($search)) {
    $where_conditions[] = "(title LIKE '%$search%' OR subject LIKE '%$search%' OR description LIKE '%$search%')";
}

if (!empty($subject_filter)) {
    $where_conditions[] = "subject = '$subject_filter'";
}

$where_clause = implode(' AND ', $where_conditions);

// Build ORDER BY
$order_by = match($sort_by) {
    'oldest' => 'created_at ASC',
    'views' => 'views DESC',
    default => 'created_at DESC'
};

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

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
// GET SUBJECTS FOR FILTER
// =============================================
$subjects_sql = "SELECT DISTINCT subject FROM notes WHERE teacher_id = $teacher_id ORDER BY subject";
$subjects_result = mysqli_query($conn, $subjects_sql);
$subjects = mysqli_fetch_all($subjects_result, MYSQLI_ASSOC);

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
    <title>View Notes - EduHack AI</title>
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
            margin-top: 4px;
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
           ALERT MESSAGES
        ============================================= */
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
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
        .alert-icon { font-size: 20px; }

        /* =============================================
           STATISTICS CARDS
        ============================================= */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: white;
            padding: 20px 24px;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .stat-card:hover {
            transform: translateY(-4px);
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
            font-size: 22px;
            flex-shrink: 0;
        }
        .stat-card .stat-icon.purple { background: rgba(108, 99, 255, 0.08); color: #6C63FF; }
        .stat-card .stat-icon.green { background: rgba(34, 197, 94, 0.08); color: #22C55E; }
        .stat-card .stat-icon.orange { background: rgba(245, 158, 11, 0.08); color: #F59E0B; }
        .stat-card .stat-icon.blue { background: rgba(59, 130, 246, 0.08); color: #3B82F6; }
        .stat-card .stat-info .stat-number {
            font-size: 28px;
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
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
        }
        .filter-bar .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 160px;
        }
        .filter-bar .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: #6B7280;
            white-space: nowrap;
        }
        .filter-bar .filter-group input,
        .filter-bar .filter-group select {
            padding: 8px 12px;
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
            padding: 8px 20px;
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

        /* View Toggle */
        .view-toggle {
            display: flex;
            gap: 4px;
            background: #F3F4F6;
            border-radius: 10px;
            padding: 4px;
        }
        .view-toggle button {
            padding: 6px 14px;
            border: none;
            border-radius: 8px;
            background: transparent;
            color: #6B7280;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .view-toggle button.active {
            background: white;
            color: #6C63FF;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .view-toggle button:hover:not(.active) {
            color: #1F2937;
        }

        /* =============================================
           TABLE VIEW
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
            padding: 16px 20px;
            border-bottom: 2px solid #F3F4F6;
            background: #F9FAFB;
        }
        .table-container table td {
            padding: 16px 20px;
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

        .note-thumb {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
            background: #F3F4F6;
        }
        .status-badge {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .status-badge.published {
            background: rgba(34, 197, 94, 0.08);
            color: #22C55E;
        }
        .status-badge.draft {
            background: rgba(245, 158, 11, 0.08);
            color: #F59E0B;
        }

        .action-btns {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
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
        .action-btn.edit {
            background: rgba(59, 130, 246, 0.08);
            color: #3B82F6;
        }
        .action-btn.edit:hover {
            background: #3B82F6;
            color: white;
        }
        .action-btn.delete {
            background: rgba(239, 68, 68, 0.08);
            color: #EF4444;
        }
        .action-btn.delete:hover {
            background: #EF4444;
            color: white;
        }
        .action-btn.download {
            background: rgba(34, 197, 94, 0.08);
            color: #22C55E;
        }
        .action-btn.download:hover {
            background: #22C55E;
            color: white;
        }

        /* =============================================
           GRID VIEW
        ============================================= */
        .grid-view {
            display: none;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .grid-view.active {
            display: grid;
        }
        .grid-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            overflow: hidden;
            transition: all 0.4s ease;
        }
        .grid-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 40px rgba(108, 99, 255, 0.06);
            border-color: rgba(108, 99, 255, 0.1);
        }
        .grid-card .grid-thumb {
            width: 100%;
            height: 160px;
            object-fit: cover;
            background: #F3F4F6;
        }
        .grid-card .grid-body {
            padding: 16px 20px 20px;
        }
        .grid-card .grid-body h4 {
            font-size: 16px;
            font-weight: 600;
            color: #1F2937;
            margin-bottom: 4px;
        }
        .grid-card .grid-body .grid-subject {
            font-size: 13px;
            color: #6C63FF;
            font-weight: 500;
        }
        .grid-card .grid-body .grid-meta {
            display: flex;
            gap: 12px;
            margin: 10px 0 12px;
            font-size: 12px;
            color: #6B7280;
        }
        .grid-card .grid-body .grid-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .grid-card .grid-body .grid-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

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
           MODALS
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
            padding: 10px 0;
            border-bottom: 1px solid #F9FAFB;
        }
        .modal-body .detail-label {
            font-weight: 600;
            color: #6B7280;
            width: 140px;
            flex-shrink: 0;
        }
        .modal-body .detail-value {
            color: #1F2937;
        }
        .modal-body .detail-value .topics-list {
            list-style: none;
            padding: 0;
        }
        .modal-body .detail-value .topics-list li {
            padding: 4px 0;
            padding-left: 20px;
            position: relative;
        }
        .modal-body .detail-value .topics-list li::before {
            content: '•';
            position: absolute;
            left: 0;
            color: #6C63FF;
            font-weight: 700;
        }
        .modal-footer {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 2px solid #F3F4F6;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Edit Form in Modal */
        .edit-form .form-group {
            margin-bottom: 16px;
        }
        .edit-form .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 4px;
        }
        .edit-form .form-group .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #E5E7EB;
            border-radius: 10px;
            font-size: 14px;
            color: #1F2937;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        .edit-form .form-group .form-control:focus {
            outline: none;
            border-color: #6C63FF;
            box-shadow: 0 0 0 4px rgba(108, 99, 255, 0.04);
        }
        .edit-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* =============================================
           DELETE CONFIRMATION
        ============================================= */
        .delete-modal .modal {
            max-width: 450px;
            text-align: center;
        }
        .delete-modal .delete-icon {
            font-size: 64px;
            color: #EF4444;
            margin-bottom: 16px;
        }
        .delete-modal h3 {
            font-size: 24px;
            color: #1F2937;
            margin-bottom: 8px;
        }
        .delete-modal p {
            color: #6B7280;
            margin-bottom: 24px;
        }
        .delete-modal .btn-danger {
            padding: 12px 32px;
            background: #EF4444;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .delete-modal .btn-danger:hover {
            background: #DC2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        .delete-modal .btn-secondary {
            padding: 12px 32px;
            background: #F3F4F6;
            color: #6B7280;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .delete-modal .btn-secondary:hover {
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
        .sidebar-overlay.active {
            display: block;
        }

        /* =============================================
           EMPTY STATE
        ============================================= */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state .empty-icon {
            font-size: 72px;
            color: #E5E7EB;
            margin-bottom: 16px;
        }
        .empty-state h4 {
            font-size: 20px;
            color: #1F2937;
            margin-bottom: 8px;
        }
        .empty-state p {
            color: #6B7280;
            margin-bottom: 20px;
        }

        /* =============================================
           RESPONSIVE
        ============================================= */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
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
            .header-left .page-title { font-size: 22px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .stat-card { padding: 16px; }
            .stat-card .stat-info .stat-number { font-size: 22px; }
            .table-container { overflow-x: auto; }
            .table-container table { min-width: 700px; }
            .grid-view { grid-template-columns: 1fr 1fr; }
            .modal { padding: 20px; margin: 10px; }
            .edit-form .form-row { grid-template-columns: 1fr; }
            .modal-body .detail-row { flex-direction: column; gap: 4px; }
            .modal-body .detail-label { width: 100%; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .grid-view { grid-template-columns: 1fr; }
            .filter-actions { flex-direction: column; }
            .btn-filter { width: 100%; justify-content: center; }
            .view-toggle { width: 100%; justify-content: center; }
            .action-btns { flex-wrap: wrap; }
            .action-btn { font-size: 12px; padding: 4px 10px; }
            .pagination a, .pagination span { padding: 6px 12px; font-size: 13px; }
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
                <a href="view_notes.php" class="active">
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
                <div class="page-title">
                    📚 My <span>Study Notes</span>
                </div>
                <div class="page-subtitle">
                    Manage and organize all uploaded learning materials.
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
        ALERT MESSAGES
        ============================================= -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <span class="alert-icon">⚠️</span>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✅</span>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- =============================================
        STATISTICS CARDS
        ============================================= -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-book"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $stats['total_notes'] ?? 0; ?></div>
                    <div class="stat-label">Total Notes</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $stats['published_notes'] ?? 0; ?></div>
                    <div class="stat-label">Published</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-edit"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $stats['draft_notes'] ?? 0; ?></div>
                    <div class="stat-label">Drafts</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-download"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $stats['total_downloads'] ?? 0; ?></div>
                    <div class="stat-label">Total Downloads</div>
                </div>
            </div>
        </div>

        <!-- =============================================
        FILTER BAR
        ============================================= -->
        <form class="filter-bar" method="GET" action="">
            <div class="filter-group">
                <label><i class="fas fa-search"></i></label>
                <input type="text" name="search" placeholder="Search by title or subject..." 
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
            <div class="filter-group" style="flex:0.5;">
                <label>Sort</label>
                <select name="sort">
                    <option value="latest" <?php echo $sort_by == 'latest' ? 'selected' : ''; ?>>Latest First</option>
                    <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="views" <?php echo $sort_by == 'views' ? 'selected' : ''; ?>>Most Viewed</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter btn-filter-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="view_notes.php" class="btn-filter btn-filter-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
                <div class="view-toggle">
                    <button type="button" class="<?php echo $view_mode == 'table' ? 'active' : ''; ?>" 
                            onclick="setView('table')">
                        <i class="fas fa-table"></i>
                    </button>
                    <button type="button" class="<?php echo $view_mode == 'grid' ? 'active' : ''; ?>" 
                            onclick="setView('grid')">
                        <i class="fas fa-th"></i>
                    </button>
                </div>
            </div>
            <input type="hidden" name="view" id="viewMode" value="<?php echo $view_mode; ?>">
        </form>

        <!-- =============================================
        TABLE VIEW
        ============================================= -->
        <div class="table-container" id="tableView" style="<?php echo $view_mode == 'table' ? '' : 'display:none;'; ?>">
            <?php if (count($notes) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Thumb</th>
                            <th>Title</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Views</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notes as $note): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($note['thumbnail_path'])): ?>
                                        <img src="../<?php echo htmlspecialchars($note['thumbnail_path']); ?>" 
                                             class="note-thumb" alt="Thumbnail">
                                    <?php else: ?>
                                        <div class="note-thumb" style="display:flex;align-items:center;justify-content:center;font-size:20px;color:#9CA3AF;">
                                            📄
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars(substr($note['title'], 0, 30)) . (strlen($note['title']) > 30 ? '...' : ''); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($note['subject']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($note['created_at'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $note['is_published'] ? 'published' : 'draft'; ?>">
                                        <?php echo $note['is_published'] ? 'Published' : 'Draft'; ?>
                                    </span>
                                </td>
                                <td><?php echo $note['views'] ?? 0; ?></td>
                                <td>
                                    <div class="action-btns">
                                        <button class="action-btn view" onclick="viewNote(<?php echo $note['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn edit" onclick="editNote(<?php echo $note['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="../<?php echo htmlspecialchars($note['file_path']); ?>" 
                                           class="action-btn download" target="_blank">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button class="action-btn delete" onclick="confirmDelete(<?php echo $note['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <h4>No Notes Found</h4>
                    <p>You haven't uploaded any notes yet. Start creating your first note!</p>
                    <a href="create_notes.php" class="btn-filter btn-filter-primary" style="display:inline-block;padding:12px 32px;text-decoration:none;">
                        <i class="fas fa-plus-circle"></i> Create Note
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- =============================================
        GRID VIEW
        ============================================= -->
        <div class="grid-view" id="gridView" style="<?php echo $view_mode == 'grid' ? '' : 'display:none;'; ?>">
            <?php foreach ($notes as $note): ?>
                <div class="grid-card">
                    <?php if (!empty($note['thumbnail_path'])): ?>
                        <img src="../<?php echo htmlspecialchars($note['thumbnail_path']); ?>" class="grid-thumb" alt="Thumbnail">
                    <?php else: ?>
                        <div class="grid-thumb" style="display:flex;align-items:center;justify-content:center;font-size:48px;color:#9CA3AF;">
                            📄
                        </div>
                    <?php endif; ?>
                    <div class="grid-body">
                        <h4><?php echo htmlspecialchars(substr($note['title'], 0, 35)) . (strlen($note['title']) > 35 ? '...' : ''); ?></h4>
                        <div class="grid-subject"><?php echo htmlspecialchars($note['subject']); ?></div>
                        <div class="grid-meta">
                            <span><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($note['created_at'])); ?></span>
                            <span><i class="fas fa-eye"></i> <?php echo $note['views'] ?? 0; ?></span>
                        </div>
                        <div style="margin-bottom:8px;">
                            <span class="status-badge <?php echo $note['is_published'] ? 'published' : 'draft'; ?>">
                                <?php echo $note['is_published'] ? 'Published' : 'Draft'; ?>
                            </span>
                        </div>
                        <div class="grid-actions">
                            <button class="action-btn view" onclick="viewNote(<?php echo $note['id']; ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-btn edit" onclick="editNote(<?php echo $note['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="../<?php echo htmlspecialchars($note['file_path']); ?>" 
                               class="action-btn download" target="_blank">
                                <i class="fas fa-download"></i>
                            </a>
                            <button class="action-btn delete" onclick="confirmDelete(<?php echo $note['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- =============================================
        PAGINATION
        ============================================= -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&subject=<?php echo urlencode($subject_filter); ?>&sort=<?php echo urlencode($sort_by); ?>&view=<?php echo $view_mode; ?>">
                        <i class="fas fa-chevron-left"></i> Prev
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i> Prev</span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&subject=<?php echo urlencode($subject_filter); ?>&sort=<?php echo urlencode($sort_by); ?>&view=<?php echo $view_mode; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&subject=<?php echo urlencode($subject_filter); ?>&sort=<?php echo urlencode($sort_by); ?>&view=<?php echo $view_mode; ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- =============================================
    VIEW NOTE MODAL
    ============================================= -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-eye" style="color:#6C63FF;"></i> Note Details</h3>
                <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Dynamic content -->
            </div>
            <div class="modal-footer">
                <button class="btn-filter btn-filter-secondary" onclick="closeModal('viewModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- =============================================
    EDIT NOTE MODAL
    ============================================= -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-edit" style="color:#3B82F6;"></i> Edit Note</h3>
                <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" action="" class="edit-form">
                <div class="modal-body">
                    <input type="hidden" name="note_id" id="editNoteId">
                    <input type="hidden" name="edit_note" value="1">
                    
                    <div class="form-group">
                        <label>Title <span style="color:#EF4444;">*</span></label>
                        <input type="text" name="title" id="editTitle" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Subject <span style="color:#EF4444;">*</span></label>
                        <select name="subject" id="editSubject" class="form-control" required>
                            <option value="Mathematics">Mathematics</option>
                            <option value="Physics">Physics</option>
                            <option value="Chemistry">Chemistry</option>
                            <option value="Biology">Biology</option>
                            <option value="Computer Science">Computer Science</option>
                            <option value="English">English</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Description <span style="color:#EF4444;">*</span></label>
                        <textarea name="description" id="editDescription" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Summary</label>
                            <textarea name="summary" id="editSummary" class="form-control" rows="2"></textarea>
                        </div>
                                <option value="5 Minutes">5 Minutes</option>
                                <option value="10 Minutes">10 Minutes</option>
                                <option value="15 Minutes">15 Minutes</option>
                                <option value="30 Minutes">30 Minutes</option>
                                <option value="45 Minutes">45 Minutes</option>
                                <option value="60 Minutes">60 Minutes</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Smart Summary</label>
                        <textarea name="summary" id="editSummary" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Important Topics</label>
                        <textarea name="topics" id="editTopics" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-filter btn-filter-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn-filter btn-filter-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =============================================
    DELETE CONFIRMATION MODAL
    ============================================= -->
    <div class="modal-overlay delete-modal" id="deleteModal">
        <div class="modal" style="max-width:450px;">
            <div class="delete-icon">🗑️</div>
            <h3>Delete Note?</h3>
            <p>Are you sure you want to delete this note? This action cannot be undone.</p>
            <div style="display:flex;gap:12px;justify-content:center;">
                <button class="btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <a href="#" id="deleteLink" class="btn-danger" style="text-decoration:none;">
                    <i class="fas fa-trash"></i> Delete
                </a>
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
        // VIEW TOGGLE
        // =============================================
        function setView(view) {
            document.getElementById('viewMode').value = view;
            
            if (view === 'table') {
                document.getElementById('tableView').style.display = '';
                document.getElementById('gridView').style.display = 'none';
            } else {
                document.getElementById('tableView').style.display = 'none';
                document.getElementById('gridView').style.display = 'grid';
            }
            
            // Update button states
            document.querySelectorAll('.view-toggle button').forEach(btn => btn.classList.remove('active'));
            if (view === 'table') {
                document.querySelector('.view-toggle button:first-child').classList.add('active');
            } else {
                document.querySelector('.view-toggle button:last-child').classList.add('active');
            }
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

        // Close modals on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // =============================================
        // VIEW NOTE
        // =============================================
        function viewNote(noteId) {
            fetch(`get_note.php?id=${noteId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const note = data.note;
                        const topics = note.topics ? note.topics.split('\n').filter(t => t.trim()) : [];
                        
                        document.getElementById('viewModalBody').innerHTML = `
                            <div class="detail-row">
                                <div class="detail-label">Title</div>
                                <div class="detail-value"><strong>${escapeHtml(note.title)}</strong></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Subject</div>
                                <div class="detail-value">📚 ${escapeHtml(note.subject)}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Description</div>
                                <div class="detail-value">${escapeHtml(note.description)}</div>
                            </div>
                            ${note.summary ? `
                                <div class="detail-row">
                                    <div class="detail-label">Summary</div>
                                    <div class="detail-value">${escapeHtml(note.summary)}</div>
                                </div>
                            ` : ''}
                            ${topics.length > 0 ? `
                                <div class="detail-row">
                                    <div class="detail-label">Topics</div>
                                    <div class="detail-value">
                                        <ul class="topics-list">
                                            ${topics.map(t => `<li>${escapeHtml(t)}</li>`).join('')}
                                        </ul>
                                    </div>
                                </div>
                            ` : ''}
                            <div class="detail-row">
                                <div class="detail-label">Difficulty</div>
                                <div class="detail-value">${escapeHtml(note.difficulty)}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Reading Time</div>
                                <div class="detail-value">⏱️ ${escapeHtml(note.reading_time || 'Not specified')}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Status</div>
                                <div class="detail-value">
                                    <span class="status-badge ${note.is_published ? 'published' : 'draft'}">
                                        ${note.is_published ? 'Published' : 'Draft'}
                                    </span>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Uploaded</div>
                                <div class="detail-value">${new Date(note.created_at).toLocaleDateString('en-US', { 
                                    year: 'numeric', month: 'long', day: 'numeric', 
                                    hour: '2-digit', minute: '2-digit' 
                                })}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Views</div>
                                <div class="detail-value">👁️ ${note.views || 0}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Downloads</div>
                                <div class="detail-value">📥 ${note.downloads || 0}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">File</div>
                                <div class="detail-value">
                                    <a href="../${escapeHtml(note.file_path)}" target="_blank" style="color:#6C63FF;text-decoration:none;">
                                        <i class="fas fa-file"></i> ${escapeHtml(note.file_name)}
                                    </a>
                                </div>
                            </div>
                        `;
                        openModal('viewModal');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load note details.');
                });
        }

        // =============================================
        // EDIT NOTE
        // =============================================
        function editNote(noteId) {
            fetch(`get_note.php?id=${noteId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const note = data.note;
                        document.getElementById('editNoteId').value = note.id;
                        document.getElementById('editTitle').value = note.title;
                        document.getElementById('editSubject').value = note.subject;
                        document.getElementById('editDescription').value = note.description;
                        document.getElementById('editSummary').value = note.summary || '';
                        document.getElementById('editTopics').value = note.topics || '';
                        document.getElementById('editDifficulty').value = note.difficulty || 'Beginner';
                        document.getElementById('editReadingTime').value = note.reading_time || '10 Minutes';
                        openModal('editModal');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load note for editing.');
                });
        }

        // =============================================
        // DELETE NOTE
        // =============================================
        function confirmDelete(noteId) {
            document.getElementById('deleteLink').href = `?delete=1&id=${noteId}`;
            openModal('deleteModal');
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
        console.log('📚 EduHack AI - View Notes Page Loaded');
        console.log('📊 Total Notes: <?php echo $stats['total_notes'] ?? 0; ?>');
        console.log('👋 Welcome, <?php echo htmlspecialchars($teacher_name); ?>');
    </script>

</body>
</html>