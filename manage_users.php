<?php
/**
 * =============================================
 * User Management - EduHack AI Admin Panel
 * =============================================
 * 
 * This page allows administrators to manage
 * all users on the platform.
 */

// Require authentication and admin role
require_once '../includes/auth.php';
requireAdmin();

// Get admin info
$admin_id = getCurrentUserId();
$admin_name = getCurrentUserFullName();

// Include database connection
require_once '../includes/db.php';

// =============================================
// GET FILTERS
// =============================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$role_filter = isset($_GET['role']) ? mysqli_real_escape_string($conn, trim($_GET['role'])) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, trim($_GET['status'])) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'latest';

// =============================================
// HANDLE ADD USER
// =============================================
$add_error = '';
$add_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    $role = mysqli_real_escape_string($conn, trim($_POST['role']));
    $status = mysqli_real_escape_string($conn, trim($_POST['status']));
    
    if (empty($full_name) || empty($email) || empty($password)) {
        $add_error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $add_error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $add_error = 'Password must be at least 8 characters.';
    } else {
        // Check if email exists
        $check_sql = "SELECT id FROM users WHERE email = '$email'";
        $check_result = mysqli_query($conn, $check_sql);
        if (mysqli_num_rows($check_result) > 0) {
            $add_error = 'Email already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $insert_sql = "INSERT INTO users (full_name, email, password, role, is_active) 
                           VALUES ('$full_name', '$email', '$hashed', '$role', " . ($status == 'active' ? 1 : 0) . ")";
            if (mysqli_query($conn, $insert_sql)) {
                $add_success = '✅ User created successfully!';
            } else {
                $add_error = 'Failed to create user.';
            }
        }
    }
}

// =============================================
// HANDLE EDIT USER
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = (int)$_POST['user_id'];
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $role = mysqli_real_escape_string($conn, trim($_POST['role']));
    $status = mysqli_real_escape_string($conn, trim($_POST['status']));
    
    if (empty($full_name) || empty($email)) {
        $edit_error = 'Please fill in all required fields.';
    } else {
        $update_sql = "UPDATE users SET 
                       full_name = '$full_name',
                       email = '$email',
                       role = '$role',
                       is_active = " . ($status == 'active' ? 1 : 0) . "
                       WHERE id = $user_id";
        if (mysqli_query($conn, $update_sql)) {
            $edit_success = '✅ User updated successfully!';
        } else {
            $edit_error = 'Failed to update user.';
        }
    }
}

// =============================================
// HANDLE DELETE USER
// =============================================
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    if ($user_id != $admin_id) { // Prevent admin from deleting themselves
        $delete_sql = "DELETE FROM users WHERE id = $user_id";
        if (mysqli_query($conn, $delete_sql)) {
            $delete_success = '✅ User deleted successfully!';
        }
    } else {
        $delete_error = '❌ You cannot delete your own account.';
    }
}

// =============================================
// HANDLE TOGGLE STATUS
// =============================================
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    if ($user_id != $admin_id) {
        $toggle_sql = "UPDATE users SET is_active = NOT is_active WHERE id = $user_id";
        mysqli_query($conn, $toggle_sql);
    }
    header("Location: manage_users.php");
    exit();
}

// =============================================
// FETCH USER STATISTICS
// =============================================
$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students,
                SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as teachers,
                SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
              FROM users";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// =============================================
// FETCH USERS
// =============================================
$where_conditions = ["1=1"];
if (!empty($search)) {
    $where_conditions[] = "(full_name LIKE '%$search%' OR email LIKE '%$search%')";
}
if (!empty($role_filter)) {
    $where_conditions[] = "role = '$role_filter'";
}
if (!empty($status_filter)) {
    $where_conditions[] = "is_active = " . ($status_filter == 'active' ? 1 : 0);
}
$where_clause = implode(' AND ', $where_conditions);

$order_by = match($sort_by) {
    'oldest' => 'created_at ASC',
    'name_asc' => 'full_name ASC',
    'name_desc' => 'full_name DESC',
    default => 'created_at DESC'
};

$users_sql = "SELECT * FROM users WHERE $where_clause ORDER BY $order_by";
$users_result = mysqli_query($conn, $users_sql);
$users = mysqli_fetch_all($users_result, MYSQLI_ASSOC);

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
    <title>User Management - EduHack AI</title>
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
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid #F3F4F6;
            transition: all 0.4s ease;
            text-align: center;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(108, 99, 255, 0.06);
        }
        .stat-card .stat-number {
            font-size: 24px;
            font-weight: 800;
            color: #1F2937;
            line-height: 1;
        }
        .stat-card .stat-label {
            font-size: 12px;
            color: #6B7280;
            margin-top: 2px;
        }
        .stat-card .stat-icon {
            font-size: 18px;
            display: block;
            margin-bottom: 4px;
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
        .btn-add {
            background: linear-gradient(135deg, #22C55E, #16A34A);
            color: white;
            padding: 7px 20px;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
        }

        /* =============================================
           TABLE CONTAINER
        ============================================= */
        .table-container {
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            overflow: hidden;
        }
        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }
        .table-container table th {
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #9CA3AF;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 16px;
            border-bottom: 2px solid #F3F4F6;
            background: #F9FAFB;
        }
        .table-container table td {
            padding: 12px 16px;
            border-bottom: 1px solid #F9FAFB;
            font-size: 13px;
            color: #4B5563;
            vertical-align: middle;
        }
        .table-container table tr:hover td {
            background: #F9FAFB;
        }
        .table-container table tr:last-child td {
            border-bottom: none;
        }

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

        .role-badge {
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        .role-badge.admin { background: rgba(108, 99, 255, 0.12); color: #6C63FF; }
        .role-badge.teacher { background: rgba(34, 197, 94, 0.12); color: #22C55E; }
        .role-badge.student { background: rgba(59, 130, 246, 0.12); color: #3B82F6; }

        .status-badge {
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        .status-badge.active { background: rgba(34, 197, 94, 0.12); color: #22C55E; }
        .status-badge.inactive { background: rgba(239, 68, 68, 0.12); color: #EF4444; }

        .action-btns {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        .action-btn {
            padding: 4px 10px;
            border: none;
            border-radius: 6px;
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
        .action-btn.view { background: rgba(108, 99, 255, 0.08); color: #6C63FF; }
        .action-btn.view:hover { background: #6C63FF; color: white; }
        .action-btn.edit { background: rgba(59, 130, 246, 0.08); color: #3B82F6; }
        .action-btn.edit:hover { background: #3B82F6; color: white; }
        .action-btn.delete { background: rgba(239, 68, 68, 0.08); color: #EF4444; }
        .action-btn.delete:hover { background: #EF4444; color: white; }
        .action-btn.activate { background: rgba(34, 197, 94, 0.08); color: #22C55E; }
        .action-btn.activate:hover { background: #22C55E; color: white; }
        .action-btn.deactivate { background: rgba(245, 158, 11, 0.08); color: #F59E0B; }
        .action-btn.deactivate:hover { background: #F59E0B; color: white; }

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
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .modal {
            background: white;
            border-radius: 20px;
            max-width: 600px;
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
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #F3F4F6;
        }
        .modal-header h3 {
            font-size: 22px;
            font-weight: 700;
            color: #1F2937;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-header h3 i { color: #6C63FF; }
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
        .modal-body .form-group {
            margin-bottom: 16px;
        }
        .modal-body .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 4px;
        }
        .modal-body .form-group label .required { color: #EF4444; }
        .modal-body .form-group .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #E5E7EB;
            border-radius: 10px;
            font-size: 14px;
            color: #1F2937;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        .modal-body .form-group .form-control:focus {
            outline: none;
            border-color: #6C63FF;
            box-shadow: 0 0 0 4px rgba(108, 99, 255, 0.04);
        }
        .modal-body .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .modal-footer {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 2px solid #F3F4F6;
            justify-content: flex-end;
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
        .btn-modal-primary {
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            color: white;
        }
        .btn-modal-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(108, 99, 255, 0.3);
        }
        .btn-modal-secondary {
            background: #F3F4F6;
            color: #6B7280;
        }
        .btn-modal-secondary:hover {
            background: #E5E7EB;
        }
        .btn-modal-danger {
            background: #EF4444;
            color: white;
        }
        .btn-modal-danger:hover {
            background: #DC2626;
            transform: translateY(-2px);
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
           DELETE MODAL
        ============================================= */
        .delete-modal .modal { max-width: 450px; text-align: center; }
        .delete-modal .delete-icon { font-size: 64px; color: #EF4444; margin-bottom: 16px; }
        .delete-modal h3 { font-size: 22px; color: #1F2937; margin-bottom: 8px; }
        .delete-modal p { color: #6B7280; margin-bottom: 20px; }

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
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
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
            .header-right { width: 100%; justify-content: flex-start; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .stat-card { padding: 10px 12px; }
            .stat-card .stat-number { font-size: 18px; }
            .table-container { overflow-x: auto; }
            .table-container table { min-width: 700px; }
            .modal { padding: 20px; margin: 10px; }
            .modal-body .form-row { grid-template-columns: 1fr; }
            .modal-footer { flex-wrap: wrap; }
            .modal-footer .btn-modal { flex: 1; }
            .action-btns { flex-wrap: wrap; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .filter-actions { flex-direction: column; }
            .btn-filter, .btn-add { width: 100%; justify-content: center; }
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
            <li class="menu-label">Admin Panel</li>
            <li>
                <a href="dashboard.php">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="manage_users.php" class="active">
                    <i class="fas fa-users"></i> Manage Users
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
                    👥 User <span>Management</span>
                </div>
                <div class="page-subtitle">
                    Manage students, teachers, and platform administrators.
                </div>
            </div>
            <div class="header-right">
                <div style="font-size:13px; color:#6B7280;">
                    <i class="far fa-calendar-alt"></i> <?php echo $current_date; ?>
                </div>
                <a href="#" class="profile-avatar">
                    <?php echo strtoupper(substr($admin_name, 0, 2)); ?>
                </a>
            </div>
        </header>

        <!-- =============================================
        STATISTICS CARDS
        ============================================= -->
        <div class="stats-grid">
            <div class="stat-card animate-in">
                <span class="stat-icon">👥</span>
                <div class="stat-number" data-count="<?php echo $stats['total'] ?? 0; ?>">0</div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card animate-in">
                <span class="stat-icon">👨‍🏫</span>
                <div class="stat-number" data-count="<?php echo $stats['teachers'] ?? 0; ?>">0</div>
                <div class="stat-label">Teachers</div>
            </div>
            <div class="stat-card animate-in">
                <span class="stat-icon">🧑‍🎓</span>
                <div class="stat-number" data-count="<?php echo $stats['students'] ?? 0; ?>">0</div>
                <div class="stat-label">Students</div>
            </div>
            <div class="stat-card animate-in">
                <span class="stat-icon">🛡️</span>
                <div class="stat-number" data-count="<?php echo $stats['admins'] ?? 0; ?>">0</div>
                <div class="stat-label">Admins</div>
            </div>
            <div class="stat-card animate-in">
                <span class="stat-icon">🟢</span>
                <div class="stat-number" data-count="<?php echo $stats['active'] ?? 0; ?>">0</div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card animate-in">
                <span class="stat-icon">🔴</span>
                <div class="stat-number" data-count="<?php echo $stats['inactive'] ?? 0; ?>">0</div>
                <div class="stat-label">Inactive</div>
            </div>
        </div>

        <!-- =============================================
        ALERT MESSAGES
        ============================================= -->
        <?php if (isset($add_error) && $add_error): ?>
            <div class="alert alert-error">
                <span class="alert-icon">⚠️</span>
                <?php echo htmlspecialchars($add_error); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($add_success) && $add_success): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✅</span>
                <?php echo htmlspecialchars($add_success); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($delete_success) && $delete_success): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✅</span>
                <?php echo htmlspecialchars($delete_success); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($delete_error) && $delete_error): ?>
            <div class="alert alert-error">
                <span class="alert-icon">⚠️</span>
                <?php echo htmlspecialchars($delete_error); ?>
            </div>
        <?php endif; ?>

        <!-- =============================================
        FILTER BAR
        ============================================= -->
        <form class="filter-bar" method="GET" action="">
            <div class="filter-group">
                <label><i class="fas fa-search"></i></label>
                <input type="text" name="search" placeholder="Search by name or email..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group">
                <label>Role</label>
                <select name="role">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="teacher" <?php echo $role_filter == 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                    <option value="student" <?php echo $role_filter == 'student' ? 'selected' : ''; ?>>Student</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Sort</label>
                <select name="sort">
                    <option value="latest" <?php echo $sort_by == 'latest' ? 'selected' : ''; ?>>Latest Registered</option>
                    <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest Registered</option>
                    <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                    <option value="name_desc" <?php echo $sort_by == 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter btn-filter-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="manage_users.php" class="btn-filter btn-filter-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
                <button type="button" class="btn-add" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add User
                </button>
            </div>
        </form>

        <!-- =============================================
        USERS TABLE
        ============================================= -->
        <div class="table-container">
            <?php if (count($users) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <span class="avatar-circle">
                                            <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                                        </span>
                                        <span>
                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                            <?php if ($user['id'] == $admin_id): ?>
                                                <span style="font-size:10px; color:#6C63FF; font-weight:600; margin-left:4px;">(You)</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="role-badge <?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-btns">
                                        <button class="action-btn view" onclick="viewUser(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn edit" onclick="editUser(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['id'] != $admin_id): ?>
                                            <?php if ($user['is_active']): ?>
                                                <a href="?toggle_status=1&id=<?php echo $user['id']; ?>" class="action-btn deactivate">
                                                    <i class="fas fa-ban"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="?toggle_status=1&id=<?php echo $user['id']; ?>" class="action-btn activate">
                                                    <i class="fas fa-check-circle"></i>
                                                </a>
                                            <?php endif; ?>
                                            <button class="action-btn delete" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <h4>No Users Found</h4>
                    <p>Try adjusting your search or filter criteria.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- =============================================
    ADD USER MODAL
    ============================================= -->
    <div class="modal-overlay" id="addModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New User</h3>
                <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" class="form-control" placeholder="Enter full name" required>
                    </div>
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="Enter email" required>
                    </div>
                    <div class="form-group">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Minimum 8 characters" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Role <span class="required">*</span></label>
                            <select name="role" class="form-control" required>
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status <span class="required">*</span></label>
                            <select name="status" class="form-control" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal btn-modal-secondary" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" name="add_user" class="btn-modal btn-modal-primary">
                        <i class="fas fa-save"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =============================================
    EDIT USER MODAL
    ============================================= -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Edit User</h3>
                <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" id="editFullName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" id="editEmail" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Role <span class="required">*</span></label>
                            <select name="role" id="editRole" class="form-control" required>
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status <span class="required">*</span></label>
                            <select name="status" id="editStatus" class="form-control" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal btn-modal-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" name="edit_user" class="btn-modal btn-modal-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =============================================
    VIEW USER MODAL
    ============================================= -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-user-circle"></i> User Details</h3>
                <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Dynamic content -->
            </div>
            <div class="modal-footer">
                <button class="btn-modal btn-modal-secondary" onclick="closeModal('viewModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- =============================================
    DELETE CONFIRMATION MODAL
    ============================================= -->
    <div class="modal-overlay delete-modal" id="deleteModal">
        <div class="modal">
            <div class="delete-icon">🗑️</div>
            <h3>Delete User?</h3>
            <p id="deleteMessage">Are you sure you want to delete this user? This action cannot be undone.</p>
            <div style="display:flex; gap:12px; justify-content:center;">
                <button class="btn-modal btn-modal-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <a href="#" id="deleteLink" class="btn-modal btn-modal-danger" style="text-decoration:none;">
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
        // ADD USER
        // =============================================
        function openAddModal() {
            openModal('addModal');
        }

        // =============================================
        // EDIT USER
        // =============================================
        function editUser(userId) {
            fetch(`get_user.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const user = data.user;
                        document.getElementById('editUserId').value = user.id;
                        document.getElementById('editFullName').value = user.full_name;
                        document.getElementById('editEmail').value = user.email;
                        document.getElementById('editRole').value = user.role;
                        document.getElementById('editStatus').value = user.is_active ? 'active' : 'inactive';
                        openModal('editModal');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load user data.');
                });
        }

        // =============================================
        // VIEW USER
        // =============================================
        function viewUser(userId) {
            fetch(`get_user.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const user = data.user;
                        document.getElementById('viewModalBody').innerHTML = `
                            <div style="display:flex; align-items:center; gap:16px; margin-bottom:20px; padding:16px; background:#F9FAFB; border-radius:12px;">
                                <span class="avatar-circle" style="width:64px; height:64px; font-size:24px;">
                                    ${user.full_name.substring(0, 2).toUpperCase()}
                                </span>
                                <div>
                                    <div style="font-size:18px; font-weight:700; color:#1F2937;">${escapeHtml(user.full_name)}</div>
                                    <div style="font-size:14px; color:#6B7280;">${escapeHtml(user.email)}</div>
                                </div>
                            </div>
                            <div class="detail-row" style="display:flex; padding:8px 0; border-bottom:1px solid #F9FAFB;">
                                <span style="font-weight:600; color:#6B7280; width:140px;">User ID</span>
                                <span style="color:#1F2937;">#${user.id}</span>
                            </div>
                            <div class="detail-row" style="display:flex; padding:8px 0; border-bottom:1px solid #F9FAFB;">
                                <span style="font-weight:600; color:#6B7280; width:140px;">Role</span>
                                <span style="color:#1F2937; text-transform:capitalize;">${user.role}</span>
                            </div>
                            <div class="detail-row" style="display:flex; padding:8px 0; border-bottom:1px solid #F9FAFB;">
                                <span style="font-weight:600; color:#6B7280; width:140px;">Status</span>
                                <span style="color:${user.is_active ? '#22C55E' : '#EF4444'}; font-weight:600;">
                                    ${user.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </div>
                            <div class="detail-row" style="display:flex; padding:8px 0; border-bottom:1px solid #F9FAFB;">
                                <span style="font-weight:600; color:#6B7280; width:140px;">Registered</span>
                                <span style="color:#1F2937;">${new Date(user.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</span>
                            </div>
                            <div class="detail-row" style="display:flex; padding:8px 0;">
                                <span style="font-weight:600; color:#6B7280; width:140px;">Last Login</span>
                                <span style="color:#1F2937;">${user.last_login ? new Date(user.last_login).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'Never'}</span>
                            </div>
                        `;
                        openModal('viewModal');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load user details.');
                });
        }

        // =============================================
        // DELETE USER
        // =============================================
        function confirmDelete(userId, userName) {
            document.getElementById('deleteMessage').textContent = `Are you sure you want to delete "${userName}"? This action cannot be undone.`;
            document.getElementById('deleteLink').href = `?delete=1&id=${userId}`;
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
        console.log('👥 EduHack AI - User Management');
        console.log('👤 Total Users: <?php echo $stats['total'] ?? 0; ?>');
        console.log('👨‍🏫 Teachers: <?php echo $stats['teachers'] ?? 0; ?>');
        console.log('🧑‍🎓 Students: <?php echo $stats['students'] ?? 0; ?>');
        console.log('👋 Welcome, <?php echo htmlspecialchars($admin_name); ?>');
    </script>

</body>
</html>