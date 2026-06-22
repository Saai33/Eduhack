<?php
/**
 * =============================================
 * Create Quiz - EduHack AI Teacher Panel
 * =============================================
 * 
 * This page allows teachers to create interactive
 * quizzes with multiple-choice questions.
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
// FETCH TEACHER'S NOTES FOR DROPDOWN
// =============================================
$notes_sql = "SELECT id, title, subject FROM notes 
              WHERE teacher_id = $teacher_id AND is_published = 1 
              ORDER BY created_at DESC";
$notes_result = mysqli_query($conn, $notes_sql);
$teacher_notes = mysqli_fetch_all($notes_result, MYSQLI_ASSOC);

// =============================================
// PROCESS FORM SUBMISSION
// =============================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quiz'])) {
    // Get quiz data
    $title = mysqli_real_escape_string($conn, trim($_POST['title']));
    $subject = mysqli_real_escape_string($conn, trim($_POST['subject']));
    $note_id = isset($_POST['note_id']) ? (int)$_POST['note_id'] : null;
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $difficulty = mysqli_real_escape_string($conn, trim($_POST['difficulty']));
    $time_limit = (int)$_POST['time_limit'];
    $passing_score = (int)$_POST['passing_score'];
    $action = $_POST['action'] ?? 'draft';
    
    // Get questions
    $questions = [];
    if (!empty($_POST['questions'])) {
        $postedQuestions = json_decode($_POST['questions'], true);
        if (is_array($postedQuestions)) {
            foreach ($postedQuestions as $q) {
                if (!empty($q['question']) && !empty($q['option_a']) && !empty($q['option_b']) && 
                    !empty($q['option_c']) && !empty($q['option_d']) && !empty($q['correct_answer'])) {
                    $questions[] = [
                        'question' => mysqli_real_escape_string($conn, trim($q['question'])),
                        'option_a' => mysqli_real_escape_string($conn, trim($q['option_a'])),
                        'option_b' => mysqli_real_escape_string($conn, trim($q['option_b'])),
                        'option_c' => mysqli_real_escape_string($conn, trim($q['option_c'])),
                        'option_d' => mysqli_real_escape_string($conn, trim($q['option_d'])),
                        'correct_answer' => mysqli_real_escape_string($conn, trim($q['correct_answer'])),
                        'marks' => isset($q['marks']) ? (int)$q['marks'] : 1
                    ];
                }
            }
        }
    }
    
    // Validation
    if (empty($title)) {
        $error = 'Please enter a quiz title.';
    } elseif (empty($subject)) {
        $error = 'Please select a subject.';
    } elseif (empty($description)) {
        $error = 'Please enter a quiz description.';
    } elseif (count($questions) === 0) {
        $error = 'Please add at least one question.';
    } else {
        // Calculate total marks
        $total_marks = array_sum(array_column($questions, 'marks'));
        
        // Insert quiz
        $is_published = ($action === 'publish') ? 1 : 0;
        $note_id_value = $note_id ? $note_id : 'NULL';
        
        $quiz_sql = "INSERT INTO quizzes (
                        teacher_id, note_id, title, subject, description,
                        difficulty, time_limit, passing_score, total_marks, is_published
                    ) VALUES (
                        $teacher_id, $note_id_value, '$title', '$subject', '$description',
                        '$difficulty', $time_limit, $passing_score, $total_marks, $is_published
                    )";
        
        if (mysqli_query($conn, $quiz_sql)) {
            $quiz_id = mysqli_insert_id($conn);
            
            // Insert questions
            $question_count = 0;
            foreach ($questions as $q) {
                $question_sql = "INSERT INTO quiz_questions (
                                    quiz_id, question, option_a, option_b, option_c, option_d,
                                    correct_answer, marks
                                ) VALUES (
                                    $quiz_id, '{$q['question']}', '{$q['option_a']}', '{$q['option_b']}',
                                    '{$q['option_c']}', '{$q['option_d']}', '{$q['correct_answer']}', {$q['marks']}
                                )";
                if (mysqli_query($conn, $question_sql)) {
                    $question_count++;
                }
            }
            
            $success = $is_published ? 
                "✅ Quiz published successfully! $question_count questions added." :
                "📝 Quiz saved as draft. You can publish it later.";
                
            // Reset form via JavaScript redirect after success
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'create_quiz.php?success=1';
                }, 2000);
            </script>";
        } else {
            $error = 'Failed to create quiz: ' . mysqli_error($conn);
        }
    }
}

// =============================================
// CURRENT DATE
// =============================================
date_default_timezone_set('Asia/Kolkata');
$current_date = date('l, F j, Y');

// =============================================
// SAMPLE QUESTIONS (for Smart Assistant)
// =============================================
$sample_questions = [
    [
        'question' => 'What is the fundamental concept of this topic?',
        'options' => ['Concept A', 'Concept B', 'Concept C', 'Concept D'],
        'answer' => 'A'
    ],
    [
        'question' => 'Which of the following is the correct approach?',
        'options' => ['Approach 1', 'Approach 2', 'Approach 3', 'Approach 4'],
        'answer' => 'B'
    ],
    [
        'question' => 'What is the main application of this principle?',
        'options' => ['Application 1', 'Application 2', 'Application 3', 'Application 4'],
        'answer' => 'C'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quiz - EduHack AI</title>
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
            padding: 28px;
            transition: all 0.3s ease;
        }
        .form-card:hover {
            box-shadow: 0 8px 30px rgba(108, 99, 255, 0.03);
        }
        .form-card .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-card .card-title i { color: #6C63FF; }

        /* =============================================
           FORM ELEMENTS
        ============================================= */
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 4px;
        }
        .form-group label .required {
            color: #EF4444;
            margin-left: 2px;
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
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
            box-shadow: 0 0 0 4px rgba(108, 99, 255, 0.04);
        }
        .form-control::placeholder { color: #9CA3AF; }
        textarea.form-control { resize: vertical; min-height: 60px; }
        select.form-control { appearance: none; cursor: pointer; }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* =============================================
           QUESTION BUILDER
        ============================================= */
        .question-card {
            background: #F9FAFB;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            position: relative;
        }
        .question-card:hover {
            border-color: rgba(108, 99, 255, 0.1);
        }
        .question-card .q-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .question-card .q-number {
            font-weight: 700;
            color: #6C63FF;
            font-size: 14px;
        }
        .question-card .q-actions {
            display: flex;
            gap: 6px;
        }
        .question-card .q-actions button {
            padding: 4px 10px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .question-card .q-actions .btn-remove {
            background: rgba(239, 68, 68, 0.08);
            color: #EF4444;
        }
        .question-card .q-actions .btn-remove:hover {
            background: #EF4444;
            color: white;
        }
        .question-card .q-actions .btn-duplicate {
            background: rgba(59, 130, 246, 0.08);
            color: #3B82F6;
        }
        .question-card .q-actions .btn-duplicate:hover {
            background: #3B82F6;
            color: white;
        }
        .question-card .q-actions .btn-move {
            background: rgba(108, 99, 255, 0.08);
            color: #6C63FF;
        }
        .question-card .q-actions .btn-move:hover {
            background: #6C63FF;
            color: white;
        }

        .question-card .q-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .question-card .q-row .full-width {
            grid-column: 1 / -1;
        }
        .question-card .q-row .form-group {
            margin-bottom: 0;
        }

        .btn-add-question {
            width: 100%;
            padding: 14px;
            border: 2px dashed #E5E7EB;
            border-radius: 12px;
            background: transparent;
            color: #6B7280;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-add-question:hover {
            border-color: #6C63FF;
            color: #6C63FF;
            background: rgba(108, 99, 255, 0.02);
        }

        /* =============================================
           SMART ASSISTANT
        ============================================= */
        .assistant-card {
            background: linear-gradient(135deg, rgba(108, 99, 255, 0.04), rgba(139, 92, 246, 0.02));
            border: 1px solid rgba(108, 99, 255, 0.08);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
        }
        .assistant-card .assistant-title {
            font-size: 16px;
            font-weight: 700;
            color: #1F2937;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 4px;
        }
        .assistant-card .assistant-title i { color: #6C63FF; font-size: 22px; }
        .assistant-card .assistant-subtitle {
            font-size: 13px;
            color: #6B7280;
            margin-bottom: 12px;
        }
        .assistant-buttons {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .btn-assistant {
            padding: 10px 16px;
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
        .btn-assistant i { font-size: 16px; }

        /* =============================================
           PREVIEW & STATS
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
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .preview-card .preview-title i { color: #6C63FF; }
        .preview-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }
        .preview-stat {
            background: #F9FAFB;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
        }
        .preview-stat .stat-number {
            font-size: 24px;
            font-weight: 800;
            color: #6C63FF;
        }
        .preview-stat .stat-label {
            font-size: 12px;
            color: #6B7280;
        }

        .preview-questions {
            max-height: 300px;
            overflow-y: auto;
        }
        .preview-questions::-webkit-scrollbar { width: 4px; }
        .preview-questions::-webkit-scrollbar-thumb { background: #6C63FF; border-radius: 2px; }
        .preview-q-item {
            padding: 8px 0;
            border-bottom: 1px solid #F9FAFB;
            font-size: 13px;
            color: #4B5563;
        }
        .preview-q-item .q-text { font-weight: 500; color: #1F2937; }
        .preview-q-item .q-options {
            font-size: 12px;
            color: #6B7280;
            margin-top: 2px;
        }
        .preview-q-item .q-options .correct {
            color: #22C55E;
            font-weight: 600;
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
        .btn-primary:hover::before { transform: rotate(45deg) translateX(100%); }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(108, 99, 255, 0.3);
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

        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
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
        @media (max-width: 1200px) {
            .form-container { grid-template-columns: 1fr; }
            .preview-card { position: static; }
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
            .form-row { grid-template-columns: 1fr; }
            .question-card .q-row { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column; }
            .form-actions .btn-primary,
            .form-actions .btn-success,
            .form-actions .btn-secondary {
                width: 100%;
                justify-content: center;
            }
            .header-left .page-title { font-size: 22px; }
            .form-card { padding: 20px; }
            .preview-stats { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 480px) {
            .form-card { padding: 16px; }
            .question-card { padding: 16px; }
            .assistant-buttons .btn-assistant { width: 100%; justify-content: center; }
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
                <a href="create_quiz.php" class="active">
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
                    🧪 Create <span>Interactive Quiz</span>
                </div>
                <div class="page-subtitle">
                    Build engaging assessments for students.
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
            LEFT - MAIN FORM
            ============================================= -->
            <div>

                <!-- Quiz Information -->
                <div class="form-card animate-in">
                    <div class="card-title">
                        <i class="fas fa-info-circle"></i> Quiz Information
                    </div>

                    <form id="quizForm" method="POST">
                        <div class="form-group">
                            <label>Quiz Title <span class="required">*</span></label>
                            <input type="text" name="title" class="form-control" 
                                   placeholder="Enter quiz title" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Subject <span class="required">*</span></label>
                                <select name="subject" class="form-control" required>
                                    <option value="">Select Subject</option>
                                    <option value="Mathematics">Mathematics</option>
                                    <option value="Physics">Physics</option>
                                    <option value="Chemistry">Chemistry</option>
                                    <option value="Biology">Biology</option>
                                    <option value="Computer Science">Computer Science</option>
                                    <option value="English">English</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Related Note</label>
                                <select name="note_id" class="form-control">
                                    <option value="">None</option>
                                    <?php foreach ($teacher_notes as $note): ?>
                                        <option value="<?php echo $note['id']; ?>">
                                            <?php echo htmlspecialchars($note['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Description <span class="required">*</span></label>
                            <textarea name="description" class="form-control" 
                                      placeholder="Describe what this quiz covers..." rows="2" required></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Difficulty Level</label>
                                <select name="difficulty" class="form-control">
                                    <option value="Beginner">Beginner</option>
                                    <option value="Intermediate" selected>Intermediate</option>
                                    <option value="Advanced">Advanced</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Time Limit (minutes)</label>
                                <select name="time_limit" class="form-control">
                                    <option value="5">5 minutes</option>
                                    <option value="10">10 minutes</option>
                                    <option value="15" selected>15 minutes</option>
                                    <option value="20">20 minutes</option>
                                    <option value="30">30 minutes</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Passing Score (%)</label>
                            <input type="number" name="passing_score" class="form-control" 
                                   value="50" min="0" max="100">
                        </div>
                </div>

                <!-- Question Builder -->
                <div class="form-card animate-in" style="margin-top:20px;">
                    <div class="card-title">
                        <i class="fas fa-list-ol"></i> Questions
                        <span style="font-size:13px; font-weight:400; color:#6B7280; margin-left:8px;" id="questionCount">(0 questions)</span>
                    </div>

                    <div id="questionsContainer">
                        <!-- Questions will be added here dynamically -->
                    </div>

                    <button type="button" class="btn-add-question" onclick="addQuestion()">
                        <i class="fas fa-plus-circle"></i> Add Question
                    </button>

                    <input type="hidden" name="questions" id="questionsData">
                    <input type="hidden" name="action" id="quizAction" value="draft">

                    <div class="form-actions" style="margin-top:20px; padding-top:20px; border-top:2px solid #F3F4F6;">
                        <button type="submit" name="create_quiz" value="1" onclick="return prepareSubmit('draft')" class="btn-secondary">
                            <i class="fas fa-save"></i> Save Draft
                        </button>
                        <button type="submit" name="create_quiz" value="1" onclick="return prepareSubmit('publish')" class="btn-success">
                            <i class="fas fa-globe"></i> Publish Quiz
                        </button>
                        <button type="reset" class="btn-secondary" onclick="confirmReset()">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                    </form>
                </div>

            </div>

            <!-- =============================================
            RIGHT - SMART ASSISTANT & PREVIEW
            ============================================= -->
            <div>

                <!-- Smart Quiz Assistant -->
                <div class="assistant-card animate-in">
                    <div class="assistant-title">
                        <i class="fas fa-robot"></i> Smart Quiz Assistant
                    </div>
                    <div class="assistant-subtitle">
                        AI-powered tools to create better quizzes
                    </div>
                    <div class="assistant-buttons">
                        <button class="btn-assistant" onclick="generateSampleQuestions()">
                            <i class="fas fa-lightbulb"></i> Generate Sample Questions
                        </button>
                        <button class="btn-assistant" onclick="generateMCQs()">
                            <i class="fas fa-puzzle-piece"></i> Generate MCQs
                        </button>
                        <button class="btn-assistant" onclick="generateImportantQuestions()">
                            <i class="fas fa-star"></i> Generate Important Questions
                        </button>
                    </div>
                </div>

                <!-- Preview & Stats -->
                <div class="preview-card animate-in">
                    <div class="preview-title">
                        <i class="fas fa-eye"></i> Quiz Preview
                    </div>

                    <div class="preview-stats">
                        <div class="preview-stat">
                            <div class="stat-number" id="previewQuestions">0</div>
                            <div class="stat-label">Questions</div>
                        </div>
                        <div class="preview-stat">
                            <div class="stat-number" id="previewMarks">0</div>
                            <div class="stat-label">Total Marks</div>
                        </div>
                        <div class="preview-stat">
                            <div class="stat-number" id="previewTime">15</div>
                            <div class="stat-label">Minutes</div>
                        </div>
                        <div class="preview-stat">
                            <div class="stat-number" id="previewPassing">50%</div>
                            <div class="stat-label">Passing Score</div>
                        </div>
                    </div>

                    <div style="font-size:13px; font-weight:600; color:#1F2937; margin-bottom:8px;">
                        Question Preview
                    </div>
                    <div class="preview-questions" id="previewQuestionsList">
                        <div style="text-align:center; color:#9CA3AF; padding:20px;">
                            <i class="fas fa-plus-circle" style="font-size:24px; display:block; margin-bottom:8px;"></i>
                            Add questions to see preview
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
        // QUESTION MANAGEMENT
        // =============================================
        let questionCounter = 0;

        function addQuestion(questionData = null) {
            questionCounter++;
            const container = document.getElementById('questionsContainer');
            const qId = `q_${questionCounter}`;
            
            const data = questionData || {
                question: '',
                option_a: '',
                option_b: '',
                option_c: '',
                option_d: '',
                correct_answer: 'A',
                marks: 1
            };
            
            const html = `
                <div class="question-card" id="${qId}">
                    <div class="q-header">
                        <span class="q-number">Question ${questionCounter}</span>
                        <div class="q-actions">
                            <button class="btn-move" onclick="moveQuestion(this, -1)" title="Move Up">
                                <i class="fas fa-arrow-up"></i>
                            </button>
                            <button class="btn-move" onclick="moveQuestion(this, 1)" title="Move Down">
                                <i class="fas fa-arrow-down"></i>
                            </button>
                            <button class="btn-duplicate" onclick="duplicateQuestion('${qId}')" title="Duplicate">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button class="btn-remove" onclick="removeQuestion('${qId}')" title="Remove">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Question Text <span class="required">*</span></label>
                        <input type="text" class="form-control q-text" 
                               placeholder="Enter question..." value="${data.question}">
                    </div>
                    
                    <div class="q-row">
                        <div class="form-group">
                            <label>Option A <span class="required">*</span></label>
                            <input type="text" class="form-control q-option-a" 
                                   placeholder="Option A" value="${data.option_a}">
                        </div>
                        <div class="form-group">
                            <label>Option B <span class="required">*</span></label>
                            <input type="text" class="form-control q-option-b" 
                                   placeholder="Option B" value="${data.option_b}">
                        </div>
                        <div class="form-group">
                            <label>Option C <span class="required">*</span></label>
                            <input type="text" class="form-control q-option-c" 
                                   placeholder="Option C" value="${data.option_c}">
                        </div>
                        <div class="form-group">
                            <label>Option D <span class="required">*</span></label>
                            <input type="text" class="form-control q-option-d" 
                                   placeholder="Option D" value="${data.option_d}">
                        </div>
                    </div>
                    
                    <div class="q-row">
                        <div class="form-group">
                            <label>Correct Answer <span class="required">*</span></label>
                            <select class="form-control q-correct">
                                <option value="A" ${data.correct_answer === 'A' ? 'selected' : ''}>A</option>
                                <option value="B" ${data.correct_answer === 'B' ? 'selected' : ''}>B</option>
                                <option value="C" ${data.correct_answer === 'C' ? 'selected' : ''}>C</option>
                                <option value="D" ${data.correct_answer === 'D' ? 'selected' : ''}>D</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Marks</label>
                            <input type="number" class="form-control q-marks" 
                                   value="${data.marks}" min="1" max="10">
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', html);
            updatePreview();
            updateQuestionCount();
            
            // Auto-scroll to new question
            const newQuestion = document.getElementById(qId);
            if (newQuestion) {
                newQuestion.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        function removeQuestion(qId) {
            if (confirm('Are you sure you want to remove this question?')) {
                const element = document.getElementById(qId);
                if (element) {
                    element.remove();
                    renumberQuestions();
                    updatePreview();
                    updateQuestionCount();
                }
            }
        }

        function duplicateQuestion(qId) {
            const element = document.getElementById(qId);
            if (!element) return;
            
            const data = {
                question: element.querySelector('.q-text').value,
                option_a: element.querySelector('.q-option-a').value,
                option_b: element.querySelector('.q-option-b').value,
                option_c: element.querySelector('.q-option-c').value,
                option_d: element.querySelector('.q-option-d').value,
                correct_answer: element.querySelector('.q-correct').value,
                marks: parseInt(element.querySelector('.q-marks').value) || 1
            };
            
            addQuestion(data);
        }

        function moveQuestion(btn, direction) {
            const card = btn.closest('.question-card');
            const container = document.getElementById('questionsContainer');
            const cards = container.querySelectorAll('.question-card');
            const index = Array.from(cards).indexOf(card);
            const newIndex = index + direction;
            
            if (newIndex < 0 || newIndex >= cards.length) return;
            
            if (direction < 0) {
                container.insertBefore(card, cards[newIndex]);
            } else {
                container.insertBefore(card, cards[newIndex + 1]);
            }
            
            renumberQuestions();
            updatePreview();
        }

        function renumberQuestions() {
            const cards = document.querySelectorAll('.question-card');
            cards.forEach((card, index) => {
                const numberSpan = card.querySelector('.q-number');
                if (numberSpan) {
                    numberSpan.textContent = `Question ${index + 1}`;
                }
            });
            questionCounter = cards.length;
        }

        function updateQuestionCount() {
            const count = document.querySelectorAll('.question-card').length;
            document.getElementById('questionCount').textContent = `(${count} questions)`;
        }

        // =============================================
        // PREVIEW UPDATE
        // =============================================
        function updatePreview() {
            const cards = document.querySelectorAll('.question-card');
            const previewList = document.getElementById('previewQuestionsList');
            let totalMarks = 0;
            
            if (cards.length === 0) {
                previewList.innerHTML = `
                    <div style="text-align:center; color:#9CA3AF; padding:20px;">
                        <i class="fas fa-plus-circle" style="font-size:24px; display:block; margin-bottom:8px;"></i>
                        Add questions to see preview
                    </div>
                `;
                document.getElementById('previewQuestions').textContent = '0';
                document.getElementById('previewMarks').textContent = '0';
                return;
            }
            
            let html = '';
            cards.forEach((card, index) => {
                const question = card.querySelector('.q-text').value || 'Question';
                const optionA = card.querySelector('.q-option-a').value || 'Option A';
                const optionB = card.querySelector('.q-option-b').value || 'Option B';
                const optionC = card.querySelector('.q-option-c').value || 'Option C';
                const optionD = card.querySelector('.q-option-d').value || 'Option D';
                const correct = card.querySelector('.q-correct').value;
                const marks = parseInt(card.querySelector('.q-marks').value) || 1;
                totalMarks += marks;
                
                const options = [optionA, optionB, optionC, optionD];
                const correctIndex = ['A', 'B', 'C', 'D'].indexOf(correct);
                
                html += `
                    <div class="preview-q-item">
                        <div class="q-text">${index + 1}. ${escapeHtml(question)} <span style="color:#6B7280; font-weight:400;">(${marks} mark${marks > 1 ? 's' : ''})</span></div>
                        <div class="q-options">
                            ${options.map((opt, i) => {
                                const letter = ['A', 'B', 'C', 'D'][i];
                                return `${letter}: ${escapeHtml(opt)}${i === correctIndex ? ' ✅' : ''}`;
                            }).join(' • ')}
                        </div>
                    </div>
                `;
            });
            
            previewList.innerHTML = html;
            document.getElementById('previewQuestions').textContent = cards.length;
            document.getElementById('previewMarks').textContent = totalMarks;
            
            // Update time and passing from form
            const timeSelect = document.querySelector('select[name="time_limit"]');
            const passingInput = document.querySelector('input[name="passing_score"]');
            if (timeSelect) {
                document.getElementById('previewTime').textContent = timeSelect.value;
            }
            if (passingInput) {
                document.getElementById('previewPassing').textContent = passingInput.value + '%';
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // =============================================
        // SMART ASSISTANT FUNCTIONS
        // =============================================
        function generateSampleQuestions() {
            const btn = event.target;
            btn.innerHTML = '<span class="spinner"></span> Generating...';
            btn.disabled = true;
            
            setTimeout(() => {
                const samples = [
                    {
                        question: 'What is the fundamental concept discussed in this topic?',
                        options: ['Concept A', 'Concept B', 'Concept C', 'Concept D'],
                        answer: 'A'
                    },
                    {
                        question: 'Which approach is most commonly used for solving this problem?',
                        options: ['Method 1', 'Method 2', 'Method 3', 'Method 4'],
                        answer: 'B'
                    },
                    {
                        question: 'What are the key principles behind this theory?',
                        options: ['Principle A', 'Principle B', 'Principle C', 'Principle D'],
                        answer: 'C'
                    }
                ];
                
                samples.forEach(sample => {
                    addQuestion({
                        question: sample.question,
                        option_a: sample.options[0],
                        option_b: sample.options[1],
                        option_c: sample.options[2],
                        option_d: sample.options[3],
                        correct_answer: sample.answer,
                        marks: 1
                    });
                });
                
                btn.innerHTML = '<i class="fas fa-lightbulb"></i> Generate Sample Questions';
                btn.disabled = false;
            }, 1500);
        }

        function generateMCQs() {
            const btn = event.target;
            btn.innerHTML = '<span class="spinner"></span> Generating...';
            btn.disabled = true;
            
            setTimeout(() => {
                const mcqs = [
                    {
                        question: 'Which of the following is correct?',
                        options: ['Option X', 'Option Y', 'Option Z', 'Option W'],
                        answer: 'A'
                    },
                    {
                        question: 'What is the primary purpose of this concept?',
                        options: ['Purpose 1', 'Purpose 2', 'Purpose 3', 'Purpose 4'],
                        answer: 'B'
                    }
                ];
                
                mcqs.forEach(mcq => {
                    addQuestion({
                        question: mcq.question,
                        option_a: mcq.options[0],
                        option_b: mcq.options[1],
                        option_c: mcq.options[2],
                        option_d: mcq.options[3],
                        correct_answer: mcq.answer,
                        marks: 1
                    });
                });
                
                btn.innerHTML = '<i class="fas fa-puzzle-piece"></i> Generate MCQs';
                btn.disabled = false;
            }, 1500);
        }

        function generateImportantQuestions() {
            const btn = event.target;
            btn.innerHTML = '<span class="spinner"></span> Generating...';
            btn.disabled = true;
            
            setTimeout(() => {
                const important = [
                    {
                        question: 'Explain the core concept and its significance.',
                        options: ['Option 1', 'Option 2', 'Option 3', 'Option 4'],
                        answer: 'A'
                    },
                    {
                        question: 'How does this topic apply to real-world scenarios?',
                        options: ['Application A', 'Application B', 'Application C', 'Application D'],
                        answer: 'B'
                    },
                    {
                        question: 'What are the common challenges and solutions?',
                        options: ['Challenge 1', 'Challenge 2', 'Challenge 3', 'Challenge 4'],
                        answer: 'C'
                    },
                    {
                        question: 'Compare and contrast different approaches.',
                        options: ['Approach A', 'Approach B', 'Approach C', 'Approach D'],
                        answer: 'A'
                    }
                ];
                
                important.forEach(q => {
                    addQuestion({
                        question: q.question,
                        option_a: q.options[0],
                        option_b: q.options[1],
                        option_c: q.options[2],
                        option_d: q.options[3],
                        correct_answer: q.answer,
                        marks: 2
                    });
                });
                
                btn.innerHTML = '<i class="fas fa-star"></i> Generate Important Questions';
                btn.disabled = false;
            }, 1800);
        }

        // =============================================
        // FORM SUBMISSION
        // =============================================
        function prepareSubmit(action) {
            const questions = [];
            const cards = document.querySelectorAll('.question-card');
            
            cards.forEach(card => {
                const question = card.querySelector('.q-text').value.trim();
                const option_a = card.querySelector('.q-option-a').value.trim();
                const option_b = card.querySelector('.q-option-b').value.trim();
                const option_c = card.querySelector('.q-option-c').value.trim();
                const option_d = card.querySelector('.q-option-d').value.trim();
                const correct_answer = card.querySelector('.q-correct').value;
                const marks = parseInt(card.querySelector('.q-marks').value) || 1;
                
                if (question && option_a && option_b && option_c && option_d) {
                    questions.push({
                        question, option_a, option_b, option_c, option_d,
                        correct_answer, marks
                    });
                }
            });
            
            document.getElementById('questionsData').value = JSON.stringify(questions);
            document.getElementById('quizAction').value = action;
            
            // Validate
            if (questions.length === 0) {
                alert('Please add at least one question.');
                return false;
            }
            
            return true;
        }

        // =============================================
        // RESET CONFIRMATION
        // =============================================
        function confirmReset() {
            if (confirm('Are you sure you want to reset the form? All unsaved data will be lost.')) {
                document.getElementById('questionsContainer').innerHTML = '';
                questionCounter = 0;
                updatePreview();
                updateQuestionCount();
            }
        }

        // =============================================
        // REAL-TIME PREVIEW UPDATES
        // =============================================
        document.addEventListener('input', function(e) {
            if (e.target.closest('.question-card') || 
                e.target.matches('input[name="passing_score"]') ||
                e.target.matches('select[name="time_limit"]')) {
                updatePreview();
            }
        });

        // =============================================
        // INITIALIZE WITH ONE EMPTY QUESTION
        // =============================================
        document.addEventListener('DOMContentLoaded', function() {
            // Check if we have any questions from a previous submission attempt
            const existingQuestions = document.querySelectorAll('.question-card');
            if (existingQuestions.length === 0) {
                addQuestion();
            }
            
            // Update preview on load
            updatePreview();
            updateQuestionCount();
            
            // Auto-dismiss alerts
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
        });

        // =============================================
        // CONSOLE LOG
        // =============================================
        console.log('🧪 EduHack AI - Create Quiz Page Loaded');
        console.log('👋 Welcome, <?php echo htmlspecialchars($teacher_name); ?>');
        console.log('💡 Smart Quiz Assistant Ready');
    </script>

</body>
</html>