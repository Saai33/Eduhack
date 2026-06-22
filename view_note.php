<?php
/**
 * =============================================
 * View Note - EduHack AI Student Panel
 * =============================================
 * 
 * This is the flagship page of EduHack AI.
 * Displays study notes with AI-powered learning assistance.
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
// GET NOTE ID
// =============================================
$note_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($note_id <= 0) {
    header('Location: browse_notes.php');
    exit();
}

// =============================================
// FETCH NOTE DETAILS
// =============================================
$note_sql = "SELECT n.*, u.full_name as teacher_name 
             FROM notes n
             JOIN users u ON n.teacher_id = u.id
             WHERE n.id = $note_id AND n.is_published = 1";
$note_result = mysqli_query($conn, $note_sql);

if (mysqli_num_rows($note_result) == 0) {
    header('Location: browse_notes.php');
    exit();
}

$note = mysqli_fetch_assoc($note_result);

// =============================================
// INCREMENT VIEW COUNT
// =============================================
$update_view_sql = "UPDATE notes SET views = views + 1 WHERE id = $note_id";
mysqli_query($conn, $update_view_sql);

// =============================================
// FETCH RELATED NOTES (Same Subject)
// =============================================
$related_sql = "SELECT id, title, subject, created_at 
                FROM notes 
                WHERE subject = '{$note['subject']}' 
                AND id != $note_id 
                AND is_published = 1 
                ORDER BY created_at DESC 
                LIMIT 4";
$related_result = mysqli_query($conn, $related_sql);
$related_notes = mysqli_fetch_all($related_result, MYSQLI_ASSOC);

// =============================================
// FETCH RECOMMENDED QUIZZES
// =============================================
$quiz_sql = "SELECT id, title, total_marks, time_limit 
             FROM quizzes 
             WHERE note_id = $note_id AND is_published = 1 
             ORDER BY created_at DESC 
             LIMIT 3";
$quiz_result = mysqli_query($conn, $quiz_sql);
$quizzes = mysqli_fetch_all($quiz_result, MYSQLI_ASSOC);

// =============================================
// LEARNING CHECKLIST
// =============================================
$checklist_items = [
    ['id' => 'read_summary', 'label' => 'Read Summary', 'checked' => false],
    ['id' => 'review_topics', 'label' => 'Review Important Topics', 'checked' => false],
    ['id' => 'practice_questions', 'label' => 'Practice Questions', 'checked' => false],
    ['id' => 'complete_quiz', 'label' => 'Complete Quiz', 'checked' => false]
];

// =============================================
// CURRENT DATE
// =============================================
date_default_timezone_set('Asia/Kolkata');
$current_date = date('l, F j, Y');

// =============================================
// SAMPLE QUESTIONS (for demo)
// =============================================
$sample_questions = [
    "What is the main concept discussed in this note?",
    "Explain the key principles with examples.",
    "How does this topic connect to real-world applications?",
    "What are the common challenges and solutions?",
    "Create a summary in your own words."
];

// =============================================
// CONCEPT EXPLANATIONS (Demo Knowledge Base)
// =============================================
$concepts = [
    'html' => 'HTML (HyperText Markup Language) is the standard markup language for creating web pages. It describes the structure of web pages using markup.',
    'css' => 'CSS (Cascading Style Sheets) is used to style and layout web pages. It controls colors, fonts, spacing, and responsive design.',
    'php' => 'PHP is a popular server-side scripting language designed for web development. It can generate dynamic page content and interact with databases.',
    'javascript' => 'JavaScript is a programming language that enables interactive web pages. It runs in the browser and can manipulate HTML and CSS.',
    'mysql' => 'MySQL is a relational database management system used for storing and managing data. It uses SQL for querying and managing databases.',
    'python' => 'Python is a high-level, interpreted programming language known for its simplicity and readability. It is widely used in AI, data science, and web development.',
    'java' => 'Java is a object-oriented programming language designed for cross-platform development. It is widely used for enterprise applications and Android development.',
    'ai' => 'Artificial Intelligence (AI) is the simulation of human intelligence in machines. It includes machine learning, natural language processing, and computer vision.',
    'machine learning' => 'Machine Learning is a subset of AI that enables systems to learn and improve from experience without being explicitly programmed.',
    'database' => 'A database is an organized collection of structured data stored electronically. It is managed by a Database Management System (DBMS).'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($note['title']); ?> - EduHack AI</title>
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
        .header-left .page-breadcrumb {
            font-size: 13px;
            color: #6B7280;
        }
        .header-left .page-breadcrumb a {
            color: #6C63FF;
            text-decoration: none;
        }
        .header-left .page-breadcrumb a:hover { text-decoration: underline; }
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
           TWO COLUMN LAYOUT
        ============================================= */
        .two-column {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 24px;
            margin-top: 8px;
        }

        /* =============================================
           NOTE CONTENT CARD
        ============================================= */
        .note-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #F3F4F6;
            padding: 32px;
            transition: all 0.3s ease;
        }
        .note-card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.03);
        }

        .note-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .note-badges .badge {
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-subject {
            background: rgba(108, 99, 255, 0.08);
            color: #6C63FF;
        }
        .badge-difficulty {
            background: rgba(245, 158, 11, 0.08);
            color: #F59E0B;
        }
        .badge-time {
            background: rgba(34, 197, 94, 0.08);
            color: #22C55E;
        }
        .badge-date {
            background: rgba(59, 130, 246, 0.08);
            color: #3B82F6;
        }

        .note-title {
            font-size: 32px;
            font-weight: 800;
            color: #1F2937;
            margin-bottom: 8px;
        }
        .note-teacher {
            font-size: 14px;
            color: #6B7280;
            margin-bottom: 16px;
        }
        .note-teacher i { color: #6C63FF; }

        .note-description {
            font-size: 16px;
            color: #4B5563;
            line-height: 1.8;
            margin-bottom: 24px;
        }

        .note-summary {
            background: linear-gradient(135deg, rgba(108, 99, 255, 0.04), rgba(139, 92, 246, 0.02));
            border-left: 4px solid #6C63FF;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .note-summary .summary-label {
            font-size: 12px;
            font-weight: 700;
            color: #6C63FF;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .note-summary .summary-text {
            font-size: 15px;
            color: #4B5563;
            line-height: 1.7;
        }

        .note-topics {
            margin-bottom: 24px;
        }
        .note-topics .topics-label {
            font-size: 14px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 8px;
        }
        .topic-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .topic-chip {
            padding: 6px 16px;
            background: #F3F4F6;
            border-radius: 20px;
            font-size: 13px;
            color: #4B5563;
            transition: all 0.3s ease;
        }
        .topic-chip:hover {
            background: rgba(108, 99, 255, 0.08);
            color: #6C63FF;
            transform: translateY(-2px);
        }

        .note-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            padding-top: 20px;
            border-top: 2px solid #F3F4F6;
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
        .btn-primary {
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(108, 99, 255, 0.3);
        }
        .btn-secondary {
            background: #F3F4F6;
            color: #6B7280;
        }
        .btn-secondary:hover {
            background: #E5E7EB;
            transform: translateY(-3px);
        }
        .btn-bookmark {
            background: rgba(245, 158, 11, 0.08);
            color: #F59E0B;
        }
        .btn-bookmark:hover {
            background: #F59E0B;
            color: white;
            transform: translateY(-3px);
        }
        .btn-bookmark.active {
            background: #F59E0B;
            color: white;
        }

        /* =============================================
           SMART ASSISTANT SIDEBAR
        ============================================= */
        .assistant-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .assistant-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #F3F4F6;
            padding: 24px;
            transition: all 0.3s ease;
        }
        .assistant-card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.03);
        }
        .assistant-card .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #1F2937;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 4px;
        }
        .assistant-card .card-title i { color: #6C63FF; font-size: 22px; }
        .assistant-card .card-subtitle {
            font-size: 13px;
            color: #6B7280;
            margin-bottom: 16px;
        }

        .assistant-btn {
            width: 100%;
            padding: 10px 16px;
            background: rgba(108, 99, 255, 0.06);
            border: 1px solid rgba(108, 99, 255, 0.08);
            border-radius: 10px;
            color: #6C63FF;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .assistant-btn:hover {
            background: #6C63FF;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(108, 99, 255, 0.2);
        }
        .assistant-btn:active { transform: translateY(0); }

        .assistant-output {
            margin-top: 12px;
            padding: 14px 16px;
            background: #F9FAFB;
            border-radius: 10px;
            font-size: 14px;
            color: #4B5563;
            line-height: 1.7;
            display: none;
            animation: fadeIn 0.5s ease;
        }
        .assistant-output.show { display: block; }
        .assistant-output .output-label {
            font-size: 11px;
            font-weight: 700;
            color: #9CA3AF;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .assistant-output .topic-list {
            list-style: none;
            padding: 0;
        }
        .assistant-output .topic-list li {
            padding: 4px 0;
            padding-left: 20px;
            position: relative;
        }
        .assistant-output .topic-list li::before {
            content: '•';
            position: absolute;
            left: 0;
            color: #6C63FF;
            font-weight: 700;
        }
        .assistant-output .question-list {
            list-style: none;
            padding: 0;
            counter-reset: q;
        }
        .assistant-output .question-list li {
            padding: 6px 0;
            padding-left: 28px;
            position: relative;
            counter-increment: q;
        }
        .assistant-output .question-list li::before {
            content: counter(q) '.';
            position: absolute;
            left: 0;
            color: #6C63FF;
            font-weight: 700;
        }

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
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Concept Explanation */
        .concept-input-group {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
        }
        .concept-input-group input {
            flex: 1;
            padding: 8px 14px;
            border: 2px solid #E5E7EB;
            border-radius: 10px;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        .concept-input-group input:focus {
            outline: none;
            border-color: #6C63FF;
            box-shadow: 0 0 0 4px rgba(108, 99, 255, 0.04);
        }
        .concept-input-group .btn-sm {
            padding: 8px 16px;
            border: none;
            border-radius: 10px;
            background: #6C63FF;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .concept-input-group .btn-sm:hover {
            background: #8B5CF6;
        }

        /* =============================================
           CHECKLIST
        ============================================= */
        .checklist-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #F9FAFB;
        }
        .checklist-item:last-child { border-bottom: none; }
        .checklist-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #6C63FF;
            cursor: pointer;
        }
        .checklist-item label {
            font-size: 14px;
            color: #4B5563;
            cursor: pointer;
            flex: 1;
        }
        .checklist-item.done label {
            text-decoration: line-through;
            color: #9CA3AF;
        }

        /* =============================================
           PERSONAL NOTES
        ============================================= */
        .personal-notes textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #E5E7EB;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 80px;
            transition: all 0.3s ease;
        }
        .personal-notes textarea:focus {
            outline: none;
            border-color: #6C63FF;
            box-shadow: 0 0 0 4px rgba(108, 99, 255, 0.04);
        }
        .personal-notes .btn-save {
            margin-top: 8px;
            padding: 8px 20px;
            background: #6C63FF;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .personal-notes .btn-save:hover {
            background: #8B5CF6;
            transform: translateY(-2px);
        }

        /* =============================================
           RELATED NOTES
        ============================================= */
        .related-item {
            display: flex;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #F9FAFB;
            align-items: center;
        }
        .related-item:last-child { border-bottom: none; }
        .related-item .related-icon {
            font-size: 28px;
            color: #9CA3AF;
        }
        .related-item .related-info { flex: 1; }
        .related-item .related-info h5 {
            font-size: 14px;
            font-weight: 600;
            color: #1F2937;
        }
        .related-item .related-info .related-meta {
            font-size: 12px;
            color: #6B7280;
        }

        /* =============================================
           RECOMMENDED QUIZZES
        ============================================= */
        .quiz-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #F9FAFB;
        }
        .quiz-item:last-child { border-bottom: none; }
        .quiz-item .quiz-info h5 {
            font-size: 14px;
            font-weight: 600;
            color: #1F2937;
        }
        .quiz-item .quiz-info .quiz-meta {
            font-size: 12px;
            color: #6B7280;
        }

        /* =============================================
           MOTIVATIONAL CARD
        ============================================= */
        .motivation-card {
            background: linear-gradient(135deg, rgba(108, 99, 255, 0.04), rgba(139, 92, 246, 0.02));
            border: 1px solid rgba(108, 99, 255, 0.08);
            border-radius: 16px;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .motivation-card .motivation-text {
            font-size: 16px;
            font-weight: 600;
            color: #1F2937;
        }
        .motivation-card .motivation-text span {
            color: #6C63FF;
            font-style: italic;
        }
        .motivation-card .motivation-icon {
            font-size: 36px;
            animation: floatIcon 3s ease-in-out infinite;
        }
        @keyframes floatIcon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
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
            .top-header { flex-direction: column; align-items: flex-start; }
            .note-card { padding: 20px; }
            .note-title { font-size: 24px; }
            .note-actions { flex-direction: column; }
            .btn-action { width: 100%; justify-content: center; }
            .motivation-card { flex-direction: column; text-align: center; gap: 12px; }
            .concept-input-group { flex-direction: column; }
            .header-right { width: 100%; justify-content: flex-start; }
        }

        @media (max-width: 480px) {
            .note-title { font-size: 20px; }
            .note-badges .badge { font-size: 10px; padding: 3px 12px; }
            .assistant-card { padding: 16px; }
            .topic-chips .topic-chip { font-size: 12px; padding: 4px 12px; }
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
                <div class="page-breadcrumb">
                    <a href="browse_notes.php"><i class="fas fa-book"></i> Notes Library</a>
                    <span> / </span>
                    <span><?php echo htmlspecialchars(substr($note['title'], 0, 30)) . (strlen($note['title']) > 30 ? '...' : ''); ?></span>
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
        TWO COLUMN LAYOUT
        ============================================= -->
        <div class="two-column">

            <!-- =============================================
            LEFT COLUMN - NOTE CONTENT
            ============================================= -->
            <div>

                <!-- Note Card -->
                <div class="note-card animate-in">
                    <div class="note-badges">
                        <span class="badge badge-subject">📚 <?php echo htmlspecialchars($note['subject']); ?></span>
                        <span class="badge badge-date">
                            <i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($note['created_at'])); ?>
                        </span>
                    </div>

                    <h1 class="note-title"><?php echo htmlspecialchars($note['title']); ?></h1>
                    
                    <div class="note-teacher">
                        <i class="fas fa-user-graduate"></i> 
                        Uploaded by <strong><?php echo htmlspecialchars($note['teacher_name']); ?></strong>
                        <span style="margin:0 8px;">•</span>
                        <i class="fas fa-eye"></i> <?php echo $note['views'] ?? 0; ?> views
                        <span style="margin:0 8px;">•</span>
                        <i class="fas fa-download"></i> <?php echo $note['downloads'] ?? 0; ?> downloads
                    </div>

                    <?php if (!empty($note['description'])): ?>
                        <div class="note-description">
                            <?php echo nl2br(htmlspecialchars($note['description'])); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($note['summary'])): ?>
                        <div class="note-summary">
                            <div class="summary-label">📝 Smart Summary</div>
                            <div class="summary-text"><?php echo nl2br(htmlspecialchars($note['summary'])); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($note['topics'])): 
                        $topics = array_filter(array_map('trim', explode("\n", $note['topics'])));
                    ?>
                        <div class="note-topics">
                            <div class="topics-label">📋 Key Topics Covered</div>
                            <div class="topic-chips">
                                <?php foreach ($topics as $topic): ?>
                                    <span class="topic-chip"><?php echo htmlspecialchars($topic); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="note-actions">
                        <a href="../<?php echo htmlspecialchars($note['file_path']); ?>" 
                           class="btn-action btn-primary" target="_blank" 
                           onclick="trackDownload(<?php echo $note['id']; ?>)">
                            <i class="fas fa-download"></i> Download Note
                        </a>
                        <form method="POST" style="display:inline;">
                            <button type="submit" name="save_personal_note" class="btn-action btn-bookmark active">
                                <i class="fas fa-save"></i>
                                Save Note
                            </button>
                        </form>
                        <button class="btn-action btn-secondary" onclick="shareNote()">
                            <i class="fas fa-share-alt"></i> Share
                        </button>
                    </div>
                </div>

                <!-- PDF Viewer (if PDF) -->
                <?php if (strtolower($note['file_type'] ?? '') === 'pdf'): ?>
                    <div class="note-card" style="margin-top:20px;">
                        <h4 style="font-size:16px; font-weight:700; margin-bottom:12px;">
                            <i class="fas fa-file-pdf" style="color:#EF4444;"></i> Preview Document
                        </h4>
                        <iframe src="../<?php echo htmlspecialchars($note['file_path']); ?>" 
                                style="width:100%; height:400px; border:1px solid #E5E7EB; border-radius:12px;">
                        </iframe>
                    </div>
                <?php endif; ?>

                <!-- Related Notes -->
                <?php if (count($related_notes) > 0): ?>
                    <div class="note-card" style="margin-top:20px;">
                        <h4 style="font-size:16px; font-weight:700; margin-bottom:12px;">
                            <i class="fas fa-layer-group" style="color:#6C63FF;"></i> Related Notes
                        </h4>
                        <?php foreach ($related_notes as $rel): ?>
                            <div class="related-item">
                                <div class="related-icon">📄</div>
                                <div class="related-info">
                                    <h5><?php echo htmlspecialchars(substr($rel['title'], 0, 35)) . (strlen($rel['title']) > 35 ? '...' : ''); ?></h5>
                                    <div class="related-meta">
                                        <?php echo htmlspecialchars($rel['subject']); ?> • 
                                        <?php echo date('M d, Y', strtotime($rel['created_at'])); ?>
                                    </div>
                                </div>
                                <a href="view_note.php?id=<?php echo $rel['id']; ?>" class="btn-sm" style="padding:6px 14px; background:rgba(108,99,255,0.08); color:#6C63FF; border-radius:8px; text-decoration:none; font-weight:600; font-size:12px;">
                                    Open
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Recommended Quizzes -->
                <?php if (count($quizzes) > 0): ?>
                    <div class="note-card" style="margin-top:20px;">
                        <h4 style="font-size:16px; font-weight:700; margin-bottom:12px;">
                            <i class="fas fa-puzzle-piece" style="color:#22C55E;"></i> Recommended Quizzes
                        </h4>
                        <?php foreach ($quizzes as $quiz): ?>
                            <div class="quiz-item">
                                <div class="quiz-info">
                                    <h5><?php echo htmlspecialchars($quiz['title']); ?></h5>
                                    <div class="quiz-meta">
                                        ❓ <?php echo $quiz['total_marks']; ?> questions • 
                                        ⏱️ <?php echo $quiz['time_limit']; ?> min
                                    </div>
                                </div>
                                <a href="attempt_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn-sm" style="padding:6px 16px; background:linear-gradient(135deg,#22C55E,#16A34A); color:white; border-radius:8px; text-decoration:none; font-weight:600; font-size:12px;">
                                    Start Quiz
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>

            <!-- =============================================
            RIGHT COLUMN - SMART ASSISTANT
            ============================================= -->
            <div class="assistant-sidebar">

                <!-- Smart Assistant Title Card -->
                <div class="assistant-card animate-in">
                    <div class="card-title">
                        <i class="fas fa-robot"></i> Smart Study Assistant
                    </div>
                    <div class="card-subtitle">
                        Enhance your learning with intelligent study tools.
                    </div>
                </div>

                <!-- Feature 1: Quick Summary -->
                <div class="assistant-card animate-in">
                    <div class="card-title" style="font-size:14px;">
                        <i class="fas fa-file-alt" style="color:#6C63FF;"></i> Quick Summary
                    </div>
                    <button class="assistant-btn" onclick="generateSummary()">
                        <i class="fas fa-magic"></i> Generate Summary
                    </button>
                    <div class="assistant-output" id="summaryOutput">
                        <div class="output-label">📝 Summary</div>
                        <div class="summary-text">
                            <?php echo !empty($note['summary']) ? nl2br(htmlspecialchars($note['summary'])) : 'No summary available for this note.'; ?>
                        </div>
                    </div>
                </div>

                <!-- Feature 2: Important Topics -->
                <div class="assistant-card animate-in">
                    <div class="card-title" style="font-size:14px;">
                        <i class="fas fa-list" style="color:#6C63FF;"></i> Important Topics
                    </div>
                    <button class="assistant-btn" onclick="extractTopics()">
                        <i class="fas fa-tags"></i> Extract Key Topics
                    </button>
                    <div class="assistant-output" id="topicsOutput">
                        <div class="output-label">📋 Key Topics</div>
                        <ul class="topic-list">
                            <?php 
                            $topics = !empty($note['topics']) ? array_filter(array_map('trim', explode("\n", $note['topics']))) : ['Main concepts', 'Key principles', 'Important applications', 'Core definitions'];
                            foreach ($topics as $topic): 
                            ?>
                                <li><?php echo htmlspecialchars($topic); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Feature 3: Practice Questions -->
                <div class="assistant-card animate-in">
                    <div class="card-title" style="font-size:14px;">
                        <i class="fas fa-question-circle" style="color:#6C63FF;"></i> Practice Questions
                    </div>
                    <button class="assistant-btn" onclick="generateQuestions()">
                        <i class="fas fa-brain"></i> Generate Questions
                    </button>
                    <div class="assistant-output" id="questionsOutput">
                        <div class="output-label">❓ Practice Questions</div>
                        <ol class="question-list">
                            <?php foreach ($sample_questions as $q): ?>
                                <li><?php echo $q; ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>

                <!-- Feature 4: Concept Explanation -->
                <div class="assistant-card animate-in">
                    <div class="card-title" style="font-size:14px;">
                        <i class="fas fa-lightbulb" style="color:#F59E0B;"></i> Concept Explanation
                    </div>
                    <div class="concept-input-group">
                        <input type="text" id="conceptInput" placeholder="Enter a concept (e.g., HTML, CSS, PHP)" />
                        <button class="btn-sm" onclick="explainConcept()">Explain</button>
                    </div>
                    <div class="assistant-output" id="conceptOutput">
                        <div class="output-label">💡 Explanation</div>
                        <div id="conceptExplanation">Enter a concept above to get an explanation.</div>
                    </div>
                </div>

                <!-- Feature 5: Study Checklist -->
                <div class="assistant-card animate-in">
                    <div class="card-title" style="font-size:14px;">
                        <i class="fas fa-check-double" style="color:#22C55E;"></i> Study Checklist
                    </div>
                    <div id="checklist">
                        <div class="checklist-item">
                            <input type="checkbox" id="check_read" onchange="updateChecklist()">
                            <label for="check_read">📖 Read Summary</label>
                        </div>
                        <div class="checklist-item">
                            <input type="checkbox" id="check_topics" onchange="updateChecklist()">
                            <label for="check_topics">📋 Review Important Topics</label>
                        </div>
                        <div class="checklist-item">
                            <input type="checkbox" id="check_questions" onchange="updateChecklist()">
                            <label for="check_questions">❓ Practice Questions</label>
                        </div>
                        <div class="checklist-item">
                            <input type="checkbox" id="check_quiz" onchange="updateChecklist()">
                            <label for="check_quiz">🧪 Complete Quiz</label>
                        </div>
                    </div>
                    <div style="margin-top:8px; font-size:13px; color:#6B7280;">
                        Progress: <span id="checklistProgress">0</span>% complete
                    </div>
                </div>

                <!-- Feature 6: Personal Notes -->
                <div class="assistant-card animate-in">
                    <div class="card-title" style="font-size:14px;">
                        <i class="fas fa-pen" style="color:#6C63FF;"></i> My Learning Notes
                    </div>
                    <div style="font-size:14px; color:#6B7280; line-height:1.6;">
                        Personal note saving is not available on this deployment because the required storage table is unavailable.
                    </div>
                </div>

                <!-- Motivational Card -->
                <div class="motivation-card animate-in">
                    <div class="motivation-text">
                        💪 <span>"Small progress every day leads to big results."</span>
                    </div>
                    <div class="motivation-icon">🏆</div>
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
        // SMART ASSISTANT FUNCTIONS
        // =============================================

        // 1. Generate Summary
        function generateSummary() {
            const output = document.getElementById('summaryOutput');
            const btn = event.target;
            
            btn.innerHTML = '<span class="spinner"></span> Generating...';
            btn.disabled = true;
            
            setTimeout(() => {
                output.classList.add('show');
                btn.innerHTML = '<i class="fas fa-magic"></i> Generate Summary';
                btn.disabled = false;
            }, 800);
        }

        // 2. Extract Topics
        function extractTopics() {
            const output = document.getElementById('topicsOutput');
            const btn = event.target;
            
            btn.innerHTML = '<span class="spinner"></span> Extracting...';
            btn.disabled = true;
            
            setTimeout(() => {
                output.classList.add('show');
                btn.innerHTML = '<i class="fas fa-tags"></i> Extract Key Topics';
                btn.disabled = false;
            }, 800);
        }

        // 3. Generate Questions
        function generateQuestions() {
            const output = document.getElementById('questionsOutput');
            const btn = event.target;
            
            btn.innerHTML = '<span class="spinner"></span> Generating...';
            btn.disabled = true;
            
            setTimeout(() => {
                output.classList.add('show');
                btn.innerHTML = '<i class="fas fa-brain"></i> Generate Questions';
                btn.disabled = false;
            }, 1000);
        }

        // 4. Explain Concept
        function explainConcept() {
            const input = document.getElementById('conceptInput');
            const output = document.getElementById('conceptOutput');
            const explanation = document.getElementById('conceptExplanation');
            const concept = input.value.trim().toLowerCase();
            
            if (!concept) {
                explanation.textContent = 'Please enter a concept to explain.';
                output.classList.add('show');
                return;
            }
            
            // Demo knowledge base
            const concepts = {
                'html': 'HTML (HyperText Markup Language) is the standard markup language for creating web pages. It describes the structure of web pages using markup elements like headings, paragraphs, links, and images.',
                'css': 'CSS (Cascading Style Sheets) is used to style and layout web pages. It controls colors, fonts, spacing, responsive design, and animations. CSS makes websites visually appealing.',
                'php': 'PHP is a popular server-side scripting language designed for web development. It can generate dynamic page content, interact with databases, handle sessions, and build robust web applications.',
                'javascript': 'JavaScript is a programming language that enables interactive web pages. It runs in the browser and can manipulate HTML, CSS, handle user events, make API calls, and create dynamic content.',
                'mysql': 'MySQL is a relational database management system used for storing and managing data. It uses SQL for querying, supports ACID transactions, and is widely used in web applications.',
                'python': 'Python is a high-level, interpreted programming language known for its simplicity and readability. It is widely used in AI, data science, machine learning, web development, and automation.',
                'java': 'Java is an object-oriented programming language designed for cross-platform development. It is widely used for enterprise applications, Android development, and large-scale systems.',
                'ai': 'Artificial Intelligence (AI) is the simulation of human intelligence in machines. It includes machine learning, natural language processing, computer vision, and robotics. AI is transforming industries.',
                'machine learning': 'Machine Learning is a subset of AI that enables systems to learn and improve from experience without being explicitly programmed. It uses algorithms to find patterns in data.',
                'database': 'A database is an organized collection of structured data stored electronically. It is managed by a Database Management System (DBMS) and allows efficient data storage, retrieval, and manipulation.'
            };
            
            let explanationText = concepts[concept] || `"${input.value}" is an important concept in this subject. It relates to the key topics covered in this note. Review the material above to learn more about ${input.value}.`;
            
            explanation.textContent = explanationText;
            output.classList.add('show');
        }

        // Enter key for concept explanation
        document.getElementById('conceptInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                explainConcept();
            }
        });

        // =============================================
        // CHECKLIST
        // =============================================
        function updateChecklist() {
            const checkboxes = document.querySelectorAll('#checklist input[type="checkbox"]');
            const checked = document.querySelectorAll('#checklist input[type="checkbox"]:checked');
            const progress = Math.round((checked.length / checkboxes.length) * 100);
            document.getElementById('checklistProgress').textContent = progress;
            
            // Mark items as done
            checkboxes.forEach(cb => {
                const item = cb.closest('.checklist-item');
                if (cb.checked) {
                    item.classList.add('done');
                } else {
                    item.classList.remove('done');
                }
            });
        }

        // =============================================
        // SHARE FUNCTION
        // =============================================
        function shareNote() {
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo htmlspecialchars(addslashes($note['title'])); ?>',
                    text: 'Check out this study note: <?php echo htmlspecialchars(addslashes($note['title'])); ?>',
                    url: window.location.href
                });
            } else {
                // Fallback: Copy to clipboard
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('📋 Link copied to clipboard! Share it with your friends.');
                }).catch(() => {
                    prompt('Copy this link to share:', window.location.href);
                });
            }
        }

        // =============================================
        // TRACK DOWNLOAD
        // =============================================
        function trackDownload(noteId) {
            // Download is handled by the direct link, but we track via AJAX
            fetch(`track_download.php?id=${noteId}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Download tracked:', data);
                })
                .catch(error => console.error('Error tracking download:', error));
        }

        // =============================================
        // AUTO-OPEN SUMMARY IF AVAILABLE
        // =============================================
        document.addEventListener('DOMContentLoaded', function() {
            const summary = document.getElementById('summaryOutput');
            if (summary && summary.querySelector('.summary-text').textContent.trim() !== 'No summary available for this note.') {
                summary.classList.add('show');
            }
            
            const topics = document.getElementById('topicsOutput');
            if (topics && topics.querySelector('.topic-list').children.length > 0) {
                topics.classList.add('show');
            }
            
            const questions = document.getElementById('questionsOutput');
            if (questions) {
                questions.classList.add('show');
            }
        });

        // =============================================
        // CONSOLE LOG
        // =============================================
        console.log('🤖 EduHack AI - Smart Study Assistant Active');
        console.log('📚 Note: <?php echo htmlspecialchars(addslashes($note['title'])); ?>');
        console.log('👋 Welcome, <?php echo htmlspecialchars($student_name); ?>');
        console.log('💡 Features: Summary, Topics, Questions, Concept Explainer, Checklist, Personal Notes');
    </script>

</body>
</html>