<?php
/**
 * =============================================
 * Create Notes - EduHack AI Teacher Panel
 * =============================================
 * 
 * This page allows teachers to upload and manage
 * study notes for students.
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
// PROCESS FORM SUBMISSION
// =============================================
$error = '';
$success = '';
$form_data = [
    'title' => '',
    'subject' => '',
    'description' => '',
    'difficulty' => 'Beginner',
    'category' => 'Theory',
    'summary' => '',
    'topics' => '',
    'reading_time' => '10 Minutes'
];

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = mysqli_real_escape_string($conn, trim($_POST['title'] ?? ''));
    $subject = mysqli_real_escape_string($conn, trim($_POST['subject'] ?? ''));
    $description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
    $difficulty = mysqli_real_escape_string($conn, trim($_POST['difficulty'] ?? 'Beginner'));
    $category = mysqli_real_escape_string($conn, trim($_POST['category'] ?? 'Theory'));
    $summary = mysqli_real_escape_string($conn, trim($_POST['summary'] ?? ''));
    $topics = mysqli_real_escape_string($conn, trim($_POST['topics'] ?? ''));
    $reading_time = mysqli_real_escape_string($conn, trim($_POST['reading_time'] ?? '10 Minutes'));
    $action = $_POST['action'] ?? 'draft';
    
    // Validation
    if (empty($title)) {
        $error = 'Please enter a note title.';
    } elseif (empty($subject)) {
        $error = 'Please select a subject.';
    } elseif (empty($description)) {
        $error = 'Please enter a description.';
    } elseif (!isset($_FILES['note_file']) || $_FILES['note_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Please upload a note file.';
    } else {
        // Process file upload
        $file = $_FILES['note_file'];
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $allowed_extensions = ['pdf', 'doc', 'docx'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $file_type = $file['type'];
        
        // Validate file type
        if (!in_array($file_extension, $allowed_extensions)) {
            $error = 'Invalid file format. Please upload PDF, DOC, or DOCX files.';
        } elseif ($file['size'] > $max_size) {
            $error = 'File size exceeds 5MB limit.';
        } else {
            // Create upload directory if not exists
            $upload_dir = '../uploads/notes/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $filename = uniqid() . '_' . time() . '.' . $file_extension;
            $filepath = $upload_dir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Handle thumbnail upload
                $thumbnail_path = null;
                if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                    $thumb_file = $_FILES['thumbnail'];
                    $thumb_ext = strtolower(pathinfo($thumb_file['name'], PATHINFO_EXTENSION));
                    $allowed_thumb = ['jpg', 'jpeg', 'png'];
                    
                    if (in_array($thumb_ext, $allowed_thumb)) {
                        $thumb_filename = uniqid() . '_thumb.' . $thumb_ext;
                        $thumb_filepath = $upload_dir . $thumb_filename;
                        if (move_uploaded_file($thumb_file['tmp_name'], $thumb_filepath)) {
                            $thumbnail_path = 'uploads/notes/' . $thumb_filename;
                        }
                    }
                }
                
                // Insert into database
                $is_published = ($action === 'publish') ? 1 : 0;
                $sql = "INSERT INTO notes (
                            teacher_id, title, subject, description, 
                            file_name, file_path, file_type, file_size,
                            summary, is_published
                        ) VALUES (
                            $teacher_id, '$title', '$subject', '$description',
                            '$filename', 'uploads/notes/$filename', '$file_extension', {$file['size']},
                            '$summary', $is_published
                        )";
                
                if (mysqli_query($conn, $sql)) {
                    $note_id = mysqli_insert_id($conn);
                    $success = $is_published ? 
                        '✅ Notes published successfully! Students can now access them.' :
                        '📝 Notes saved as draft. You can publish them later.';
                    
                    // Reset form
                    $form_data = [
                        'title' => '',
                        'subject' => '',
                        'description' => '',
                        'difficulty' => 'Beginner',
                        'category' => 'Theory',
                        'summary' => '',
                        'topics' => '',
                        'reading_time' => '10 Minutes'
                    ];
                } else {
                    $error = 'Database error: ' . mysqli_error($conn);
                    // Delete uploaded file if database insert fails
                    if (file_exists($filepath)) {
                        unlink($filepath);
                    }
                }
            } else {
                $error = 'Failed to upload file. Please try again.';
            }
        }
    }
}

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
    <title>Create Notes - EduHack AI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* =============================================
           RESET & BASE (Same as dashboard)
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
           SIDEBAR (Same as dashboard)
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
           FORM CONTAINER
        ============================================= */
        .form-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-top: 8px;
        }

        .form-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            padding: 32px;
            transition: all 0.3s ease;
        }
        .form-card:hover {
            box-shadow: 0 8px 30px rgba(108, 99, 255, 0.04);
        }

        .form-card .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-card .card-title i {
            color: #6C63FF;
        }

        /* =============================================
           FORM ELEMENTS
        ============================================= */
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .form-group label .required {
            color: #EF4444;
            margin-left: 2px;
        }
        .form-group .help-text {
            font-size: 12px;
            color: #9CA3AF;
            margin-top: 4px;
        }

        .form-control {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            font-size: 14px;
            color: #1F2937;
            background: #FFFFFF;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        .form-control:focus {
            outline: none;
            border-color: #6C63FF;
            box-shadow: 0 0 0 4px rgba(108, 99, 255, 0.06);
        }
        .form-control::placeholder {
            color: #9CA3AF;
        }
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        select.form-control {
            appearance: none;
            cursor: pointer;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* File Upload */
        .file-upload-wrapper {
            border: 2px dashed #E5E7EB;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        .file-upload-wrapper:hover {
            border-color: #6C63FF;
            background: rgba(108, 99, 255, 0.02);
        }
        .file-upload-wrapper.dragover {
            border-color: #6C63FF;
            background: rgba(108, 99, 255, 0.04);
        }
        .file-upload-wrapper .upload-icon {
            font-size: 48px;
            color: #9CA3AF;
            margin-bottom: 8px;
        }
        .file-upload-wrapper .upload-text {
            font-size: 14px;
            color: #6B7280;
        }
        .file-upload-wrapper .upload-text strong {
            color: #6C63FF;
        }
        .file-upload-wrapper input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .file-preview {
            display: none;
            margin-top: 16px;
            padding: 16px;
            background: #F9FAFB;
            border-radius: 12px;
            align-items: center;
            gap: 16px;
        }
        .file-preview.show {
            display: flex;
        }
        .file-preview .file-icon {
            font-size: 36px;
            color: #6C63FF;
        }
        .file-preview .file-info {
            flex: 1;
        }
        .file-preview .file-name {
            font-weight: 600;
            color: #1F2937;
            font-size: 14px;
        }
        .file-preview .file-size {
            font-size: 13px;
            color: #6B7280;
        }
        .file-preview .file-remove {
            color: #EF4444;
            cursor: pointer;
            font-size: 20px;
            transition: color 0.3s ease;
        }
        .file-preview .file-remove:hover {
            color: #DC2626;
        }
        .file-preview .file-thumbnail {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
        }

        /* =============================================
           SMART ASSISTANT CARD
        ============================================= */
        .assistant-card {
            background: linear-gradient(135deg, rgba(108, 99, 255, 0.04), rgba(139, 92, 246, 0.02));
            border: 1px solid rgba(108, 99, 255, 0.08);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .assistant-card .assistant-title {
            font-size: 16px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .assistant-card .assistant-title i {
            color: #6C63FF;
            font-size: 24px;
        }
        .assistant-card .assistant-subtitle {
            font-size: 13px;
            color: #6B7280;
            margin-bottom: 16px;
        }
        .assistant-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-assistant {
            padding: 10px 20px;
            background: white;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #1F2937;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-assistant:hover {
            border-color: #6C63FF;
            color: #6C63FF;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 99, 255, 0.08);
        }
        .btn-assistant i {
            font-size: 16px;
        }
        .btn-assistant.loading {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .assistant-output {
            margin-top: 16px;
            padding: 16px;
            background: white;
            border-radius: 12px;
            border: 1px solid #F3F4F6;
            display: none;
            animation: fadeIn 0.5s ease;
        }
        .assistant-output.show {
            display: block;
        }
        .assistant-output .output-label {
            font-size: 12px;
            font-weight: 700;
            color: #9CA3AF;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .assistant-output .output-content {
            font-size: 14px;
            color: #4B5563;
            line-height: 1.7;
        }

        /* =============================================
           PREVIEW CARD
        ============================================= */
        .preview-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #F3F4F6;
            padding: 24px;
            position: sticky;
            top: 24px;
        }
        .preview-card .preview-title {
            font-size: 16px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .preview-card .preview-title i {
            color: #6C63FF;
        }
        .preview-content {
            padding: 20px;
            background: #F9FAFB;
            border-radius: 12px;
            min-height: 200px;
        }
        .preview-content .preview-note-title {
            font-size: 20px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 4px;
        }
        .preview-content .preview-note-subject {
            font-size: 14px;
            color: #6C63FF;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .preview-content .preview-note-description {
            font-size: 14px;
            color: #6B7280;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        .preview-content .preview-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            font-size: 13px;
            color: #6B7280;
        }
        .preview-content .preview-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .preview-content .preview-meta i {
            color: #9CA3AF;
        }
        .preview-empty {
            text-align: center;
            color: #9CA3AF;
            padding: 30px 0;
        }
        .preview-empty i {
            font-size: 48px;
            display: block;
            margin-bottom: 12px;
            color: #E5E7EB;
        }

        /* =============================================
           BUTTONS
        ============================================= */
        .btn-primary {
            padding: 12px 32px;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            transform: rotate(45deg) translateX(-100%);
            transition: transform 0.6s ease;
        }
        .btn-primary:hover::before {
            transform: rotate(45deg) translateX(100%);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(108, 99, 255, 0.3);
        }

        .btn-secondary {
            padding: 12px 32px;
            background: white;
            color: #6B7280;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-secondary:hover {
            border-color: #6C63FF;
            color: #6C63FF;
            transform: translateY(-2px);
        }

        .btn-success {
            padding: 12px 32px;
            background: linear-gradient(135deg, #22C55E, #16A34A);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(34, 197, 94, 0.3);
        }

        .btn-danger {
            padding: 12px 32px;
            background: white;
            color: #EF4444;
            border: 2px solid #FEE2E2;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-danger:hover {
            background: #FEE2E2;
            border-color: #EF4444;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
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
        .alert-icon {
            font-size: 20px;
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
           ANIMATIONS
        ============================================= */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in {
            animation: fadeIn 0.6s ease forwards;
        }
        .animate-in:nth-child(1) { animation-delay: 0.05s; }
        .animate-in:nth-child(2) { animation-delay: 0.1s; }

        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 3px solid #E5E7EB;
            border-top: 3px solid #6C63FF;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* =============================================
           RESPONSIVE
        ============================================= */
        @media (max-width: 1024px) {
            .form-container {
                grid-template-columns: 1fr;
            }
            .preview-card {
                position: static;
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
            .form-row {
                grid-template-columns: 1fr;
            }
            .top-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .header-left .page-title {
                font-size: 22px;
            }
            .form-card {
                padding: 20px;
            }
            .assistant-buttons {
                flex-direction: column;
            }
            .btn-assistant {
                width: 100%;
                justify-content: center;
            }
            .form-actions {
                flex-direction: column;
            }
            .form-actions .btn-primary,
            .form-actions .btn-success,
            .form-actions .btn-secondary,
            .form-actions .btn-danger {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .file-upload-wrapper {
                padding: 20px;
            }
            .file-upload-wrapper .upload-icon {
                font-size: 32px;
            }
            .preview-content .preview-note-title {
                font-size: 17px;
            }
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
                <a href="create_notes.php" class="active">
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
                <div class="page-title">
                    📝 Create <span>Study Notes</span>
                </div>
                <div class="page-subtitle">
                    Upload learning materials and make them available to students.
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
            <div class="alert alert-error animate-in">
                <span class="alert-icon">⚠️</span>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success animate-in">
                <span class="alert-icon">✅</span>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- =============================================
        FORM CONTAINER
        ============================================= -->
        <div class="form-container">

            <!-- =============================================
            MAIN FORM
            ============================================= -->
            <div class="form-card animate-in">
                <div class="card-title">
                    <i class="fas fa-pencil-alt"></i> Note Details
                </div>

                <form id="noteForm" method="POST" enctype="multipart/form-data">
                    <!-- Title -->
                    <div class="form-group">
                        <label>Note Title <span class="required">*</span></label>
                        <input type="text" name="title" class="form-control" 
                               placeholder="Enter note title" 
                               value="<?php echo htmlspecialchars($form_data['title']); ?>" required>
                    </div>

                    <!-- Subject & Difficulty -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Subject <span class="required">*</span></label>
                            <select name="subject" class="form-control" required>
                                <option value="">Select Subject</option>
                                <option value="Mathematics" <?php echo $form_data['subject'] == 'Mathematics' ? 'selected' : ''; ?>>Mathematics</option>
                                <option value="Physics" <?php echo $form_data['subject'] == 'Physics' ? 'selected' : ''; ?>>Physics</option>
                                <option value="Chemistry" <?php echo $form_data['subject'] == 'Chemistry' ? 'selected' : ''; ?>>Chemistry</option>
                                <option value="Biology" <?php echo $form_data['subject'] == 'Biology' ? 'selected' : ''; ?>>Biology</option>
                                <option value="Computer Science" <?php echo $form_data['subject'] == 'Computer Science' ? 'selected' : ''; ?>>Computer Science</option>
                                <option value="English" <?php echo $form_data['subject'] == 'English' ? 'selected' : ''; ?>>English</option>
                                <option value="Other" <?php echo $form_data['subject'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Difficulty Level</label>
                            <select name="difficulty" class="form-control">
                                <option value="Beginner" <?php echo $form_data['difficulty'] == 'Beginner' ? 'selected' : ''; ?>>Beginner</option>
                                <option value="Intermediate" <?php echo $form_data['difficulty'] == 'Intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                <option value="Advanced" <?php echo $form_data['difficulty'] == 'Advanced' ? 'selected' : ''; ?>>Advanced</option>
                            </select>
                        </div>
                    </div>

                    <!-- Category & Reading Time -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Learning Category</label>
                            <select name="category" class="form-control">
                                <option value="Theory" <?php echo $form_data['category'] == 'Theory' ? 'selected' : ''; ?>>Theory</option>
                                <option value="Practical" <?php echo $form_data['category'] == 'Practical' ? 'selected' : ''; ?>>Practical</option>
                                <option value="Assignment" <?php echo $form_data['category'] == 'Assignment' ? 'selected' : ''; ?>>Assignment</option>
                                <option value="Reference Material" <?php echo $form_data['category'] == 'Reference Material' ? 'selected' : ''; ?>>Reference Material</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Estimated Reading Time</label>
                            <select name="reading_time" class="form-control">
                                <option value="5 Minutes" <?php echo $form_data['reading_time'] == '5 Minutes' ? 'selected' : ''; ?>>5 Minutes</option>
                                <option value="10 Minutes" <?php echo $form_data['reading_time'] == '10 Minutes' ? 'selected' : ''; ?>>10 Minutes</option>
                                <option value="15 Minutes" <?php echo $form_data['reading_time'] == '15 Minutes' ? 'selected' : ''; ?>>15 Minutes</option>
                                <option value="30 Minutes" <?php echo $form_data['reading_time'] == '30 Minutes' ? 'selected' : ''; ?>>30 Minutes</option>
                                <option value="45 Minutes" <?php echo $form_data['reading_time'] == '45 Minutes' ? 'selected' : ''; ?>>45 Minutes</option>
                                <option value="60 Minutes" <?php echo $form_data['reading_time'] == '60 Minutes' ? 'selected' : ''; ?>>60 Minutes</option>
                            </select>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label>Short Description <span class="required">*</span></label>
                        <textarea name="description" class="form-control" 
                                  placeholder="Brief description of the note content..." 
                                  rows="3" required><?php echo htmlspecialchars($form_data['description']); ?></textarea>
                    </div>

                    <!-- Smart Summary -->
                    <div class="form-group">
                        <label>Smart Summary</label>
                        <textarea name="summary" class="form-control" 
                                  placeholder="AI-generated summary (you can edit this)" 
                                  rows="2"><?php echo htmlspecialchars($form_data['summary']); ?></textarea>
                        <div class="help-text">Add a brief summary to help students understand the content quickly.</div>
                    </div>

                    <!-- Important Topics -->
                    <div class="form-group">
                        <label>Important Topics</label>
                        <textarea name="topics" class="form-control" 
                                  placeholder="List key topics covered (one per line)" 
                                  rows="3"><?php echo htmlspecialchars($form_data['topics']); ?></textarea>
                        <div class="help-text">List important topics to help students focus on key areas.</div>
                    </div>

                    <!-- File Upload -->
                    <div class="form-group">
                        <label>Upload Note File <span class="required">*</span></label>
                        <div class="file-upload-wrapper" id="fileUploadWrapper">
                            <div class="upload-icon">📄</div>
                            <div class="upload-text">
                                <strong>Click to upload</strong> or drag and drop<br>
                                <span style="font-size:12px; color:#9CA3AF;">PDF, DOC, DOCX (Max 5MB)</span>
                            </div>
                            <input type="file" name="note_file" id="noteFile" accept=".pdf,.doc,.docx" required>
                        </div>
                        <div class="file-preview" id="filePreview">
                            <span class="file-icon">📄</span>
                            <div class="file-info">
                                <div class="file-name" id="fileName">document.pdf</div>
                                <div class="file-size" id="fileSize">2.4 MB</div>
                            </div>
                            <span class="file-remove" id="fileRemove">&times;</span>
                        </div>
                        <div class="help-text">Supported formats: PDF, DOC, DOCX</div>
                    </div>

                    <!-- Thumbnail Upload -->
                    <div class="form-group">
                        <label>Note Thumbnail (Optional)</label>
                        <div class="file-upload-wrapper" id="thumbUploadWrapper">
                            <div class="upload-icon">🖼️</div>
                            <div class="upload-text">
                                <strong>Click to upload</strong> thumbnail image<br>
                                <span style="font-size:12px; color:#9CA3AF;">JPG, JPEG, PNG</span>
                            </div>
                            <input type="file" name="thumbnail" id="thumbFile" accept=".jpg,.jpeg,.png">
                        </div>
                        <div class="file-preview" id="thumbPreview">
                            <img src="" alt="Thumbnail" class="file-thumbnail" id="thumbImage">
                            <div class="file-info">
                                <div class="file-name" id="thumbName">image.jpg</div>
                                <div class="file-size" id="thumbSize">500 KB</div>
                            </div>
                            <span class="file-remove" id="thumbRemove">&times;</span>
                        </div>
                        <div class="help-text">Upload a thumbnail image for better visual representation.</div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" name="action" value="draft" class="btn-secondary">
                            <i class="fas fa-save"></i> Save Draft
                        </button>
                        <button type="submit" name="action" value="publish" class="btn-success">
                            <i class="fas fa-globe"></i> Publish Notes
                        </button>
                        <button type="reset" class="btn-danger" onclick="resetForm()">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </form>
            </div>

            <!-- =============================================
            RIGHT SIDEBAR - PREVIEW & SMART ASSISTANT
            ============================================= -->
            <div class="animate-in" style="display:flex; flex-direction:column; gap:24px;">

                <!-- Smart Assistant -->
                <div class="assistant-card">
                    <div class="assistant-title">
                        <i class="fas fa-robot"></i> Smart Study Assistant
                    </div>
                    <div class="assistant-subtitle">
                        AI-powered tools to enhance your notes
                    </div>
                    <div class="assistant-buttons">
                        <button class="btn-assistant" onclick="generateSummary()">
                            <i class="fas fa-file-alt"></i> Generate Summary
                        </button>
                        <button class="btn-assistant" onclick="generateTopics()">
                            <i class="fas fa-list"></i> Generate Key Topics
                        </button>
                        <button class="btn-assistant" onclick="generateQuestions()">
                            <i class="fas fa-question-circle"></i> Practice Questions
                        </button>
                    </div>
                    <div class="assistant-output" id="assistantOutput">
                        <div class="output-label" id="outputLabel">Generated Content</div>
                        <div class="output-content" id="outputContent">Content will appear here...</div>
                    </div>
                </div>

                <!-- Live Preview -->
                <div class="preview-card">
                    <div class="preview-title">
                        <i class="fas fa-eye"></i> Live Preview
                    </div>
                    <div class="preview-content" id="previewContent">
                        <div class="preview-empty">
                            <i class="fas fa-file-alt"></i>
                            <p>Fill in the form to see a live preview</p>
                        </div>
                    </div>
                </div>

            </div>
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
        // FILE UPLOAD HANDLING
        // =============================================
        const noteFile = document.getElementById('noteFile');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const fileRemove = document.getElementById('fileRemove');

        noteFile.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                fileName.textContent = file.name;
                fileSize.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                filePreview.classList.add('show');
                document.querySelector('#fileUploadWrapper .upload-text').style.display = 'none';
            }
        });

        fileRemove.addEventListener('click', function() {
            noteFile.value = '';
            filePreview.classList.remove('show');
            document.querySelector('#fileUploadWrapper .upload-text').style.display = 'block';
        });

        // Thumbnail upload
        const thumbFile = document.getElementById('thumbFile');
        const thumbPreview = document.getElementById('thumbPreview');
        const thumbImage = document.getElementById('thumbImage');
        const thumbName = document.getElementById('thumbName');
        const thumbSize = document.getElementById('thumbSize');
        const thumbRemove = document.getElementById('thumbRemove');

        thumbFile.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    thumbImage.src = e.target.result;
                };
                reader.readAsDataURL(file);
                thumbName.textContent = file.name;
                thumbSize.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                thumbPreview.classList.add('show');
                document.querySelector('#thumbUploadWrapper .upload-text').style.display = 'none';
            }
        });

        thumbRemove.addEventListener('click', function() {
            thumbFile.value = '';
            thumbPreview.classList.remove('show');
            document.querySelector('#thumbUploadWrapper .upload-text').style.display = 'block';
            thumbImage.src = '';
        });

        // =============================================
        // DRAG AND DROP
        // =============================================
        ['fileUploadWrapper', 'thumbUploadWrapper'].forEach(id => {
            const wrapper = document.getElementById(id);
            wrapper.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            wrapper.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });
            wrapper.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                const input = this.querySelector('input[type="file"]');
                if (input && e.dataTransfer.files.length) {
                    input.files = e.dataTransfer.files;
                    input.dispatchEvent(new Event('change'));
                }
            });
        });

        // =============================================
        // SMART ASSISTANT FUNCTIONS (Demo)
        // =============================================
        function generateSummary() {
            const output = document.getElementById('assistantOutput');
            const content = document.getElementById('outputContent');
            const label = document.getElementById('outputLabel');
            
            output.classList.add('show');
            label.textContent = '📝 Generating Summary...';
            content.innerHTML = '<div class="spinner"></div> Generating...';
            
            setTimeout(() => {
                const title = document.querySelector('input[name="title"]').value || 'This chapter';
                const subject = document.querySelector('select[name="subject"]').value || 'the subject';
                const summaries = [
                    `This comprehensive note on ${subject} covers all fundamental concepts with practical examples. The content is structured to facilitate easy understanding and retention.`,
                    `A detailed exploration of ${title} that breaks down complex ideas into simple, digestible sections. Perfect for students at the ${document.querySelector('select[name="difficulty"]').value || 'beginner'} level.`,
                    `This study material provides in-depth coverage of ${subject} with clear explanations, diagrams, and real-world applications. Ideal for exam preparation and conceptual clarity.`
                ];
                label.textContent = '✅ Summary Generated';
                content.textContent = summaries[Math.floor(Math.random() * summaries.length)];
            }, 1500);
        }

        function generateTopics() {
            const output = document.getElementById('assistantOutput');
            const content = document.getElementById('outputContent');
            const label = document.getElementById('outputLabel');
            
            output.classList.add('show');
            label.textContent = '📋 Generating Key Topics...';
            content.innerHTML = '<div class="spinner"></div> Generating...';
            
            setTimeout(() => {
                const topics = [
                    '• Core Concepts and Fundamentals\n• Key Theories and Principles\n• Practical Applications\n• Important Formulas and Equations\n• Common Mistakes to Avoid\n• Real-world Examples\n• Practice Problems and Solutions',
                    '• Introduction to Key Ideas\n• Step-by-Step Methodology\n• Critical Analysis Points\n• Advanced Concepts\n• Case Studies\n• Review Questions\n• Summary of Key Takeaways',
                    '• Basic Terminology\n• Essential Concepts\n• Important Techniques\n• Problem-Solving Strategies\n• Common Pitfalls\n• Expert Tips\n• Quick Revision Notes'
                ];
                label.textContent = '✅ Key Topics Generated';
                content.textContent = topics[Math.floor(Math.random() * topics.length)];
            }, 1500);
        }

        function generateQuestions() {
            const output = document.getElementById('assistantOutput');
            const content = document.getElementById('outputContent');
            const label = document.getElementById('outputLabel');
            
            output.classList.add('show');
            label.textContent = '❓ Generating Practice Questions...';
            content.innerHTML = '<div class="spinner"></div> Generating...';
            
            setTimeout(() => {
                const questions = [
                    '1. Explain the core concept and its significance.\n2. How would you apply this principle in a real-world scenario?\n3. What are the common challenges and how to overcome them?\n4. Compare and contrast different approaches.\n5. Describe the step-by-step process with examples.',
                    '1. Define the key terms and their relationships.\n2. What are the main advantages and disadvantages?\n3. How does this concept connect to other topics?\n4. Provide examples to illustrate each point.\n5. What questions would you ask to test understanding?',
                    '1. What is the fundamental idea behind this concept?\n2. How can you remember the key points effectively?\n3. What are the most important applications?\n4. Create a scenario that demonstrates understanding.\n5. How would you explain this to a beginner?'
                ];
                label.textContent = '✅ Practice Questions Generated';
                content.textContent = questions[Math.floor(Math.random() * questions.length)];
            }, 1500);
        }

        // =============================================
        // LIVE PREVIEW
        // =============================================
        const formInputs = document.querySelectorAll('#noteForm input, #noteForm textarea, #noteForm select');

        formInputs.forEach(input => {
            input.addEventListener('input', updatePreview);
            input.addEventListener('change', updatePreview);
        });

        function updatePreview() {
            const title = document.querySelector('input[name="title"]').value || 'Untitled Note';
            const subject = document.querySelector('select[name="subject"]').value || 'No Subject';
            const description = document.querySelector('textarea[name="description"]').value || 'No description provided.';
            const difficulty = document.querySelector('select[name="difficulty"]').value || 'Beginner';
            const readingTime = document.querySelector('select[name="reading_time"]').value || '10 Minutes';
            
            const previewContent = document.getElementById('previewContent');
            
            if (title === 'Untitled Note' && subject === 'No Subject' && description === 'No description provided.') {
                previewContent.innerHTML = `
                    <div class="preview-empty">
                        <i class="fas fa-file-alt"></i>
                        <p>Fill in the form to see a live preview</p>
                    </div>
                `;
                return;
            }
            
            previewContent.innerHTML = `
                <div class="preview-note-title">${escapeHtml(title)}</div>
                <div class="preview-note-subject">📚 ${escapeHtml(subject)}</div>
                <div class="preview-note-description">${escapeHtml(description)}</div>
                <div class="preview-meta">
                    <span><i class="fas fa-signal"></i> ${escapeHtml(difficulty)}</span>
                    <span><i class="fas fa-clock"></i> ${escapeHtml(readingTime)}</span>
                    <span><i class="fas fa-check-circle" style="color:#22C55E;"></i> Ready to Publish</span>
                </div>
            `;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // =============================================
        // RESET FORM
        // =============================================
        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All unsaved data will be lost.')) {
                document.getElementById('noteForm').reset();
                filePreview.classList.remove('show');
                thumbPreview.classList.remove('show');
                document.querySelector('#fileUploadWrapper .upload-text').style.display = 'block';
                document.querySelector('#thumbUploadWrapper .upload-text').style.display = 'block';
                updatePreview();
                document.getElementById('assistantOutput').classList.remove('show');
            }
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
        }, 6000);

        // =============================================
        // CONSOLE LOG
        // =============================================
        console.log('📝 EduHack AI - Create Notes Page Loaded');
        console.log('👋 Welcome, <?php echo htmlspecialchars($teacher_name); ?>');
    </script>

</body>
</html>