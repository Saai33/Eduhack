<?php
/**
 * =============================================
 * Landing Page - EduHack AI
 * =============================================
 * 
 * World-class SaaS landing page for EduHack AI
 * An intelligent learning management platform
 */

// Start session to check login status
session_start();
require_once 'includes/db.php';

$isLoggedIn = false;
$userRole = null;

if (isset($_SESSION['user_id'])) {
    // Verify user still exists in database
    $stmt = mysqli_prepare($conn, "SELECT role FROM users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $isLoggedIn = true;
        $userRole = $user['role'];
    } else {
        // User no longer exists, clear session
        session_destroy();
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduHack AI - Learn Smarter, Grow Faster</title>
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

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
            background: #FFFDF8;
            color: #1F2937;
            overflow-x: hidden;
            line-height: 1.6;
        }

        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #F3F4F6;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #6C63FF;
        }

        /* =============================================
           UTILITY CLASSES
        ============================================= */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .section-padding {
            padding: 100px 0;
        }

        .text-center {
            text-align: center;
        }

        .section-tag {
            display: inline-block;
            padding: 6px 18px;
            background: rgba(108, 99, 255, 0.1);
            color: #6C63FF;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .section-title {
            font-size: 42px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 16px;
            line-height: 1.2;
        }

        .section-title span {
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-subtitle {
            font-size: 18px;
            color: #6B7280;
            max-width: 600px;
            margin: 0 auto 50px;
            line-height: 1.8;
        }

        /* =============================================
           NAVIGATION
        ============================================= */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            padding: 18px 0;
            transition: all 0.4s ease;
        }

        .navbar.scrolled {
            background: rgba(255, 253, 248, 0.92);
            backdrop-filter: blur(20px);
            box-shadow: 0 2px 30px rgba(0, 0, 0, 0.06);
            padding: 12px 0;
        }

        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-size: 24px;
            font-weight: 700;
            color: #1F2937;
        }

        .nav-logo .logo-icon {
            font-size: 32px;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            padding: 8px;
            border-radius: 12px;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .nav-logo span {
            color: #6C63FF;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 32px;
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: #4B5563;
            font-weight: 500;
            font-size: 15px;
            transition: color 0.3s ease;
            position: relative;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            transition: width 0.3s ease;
        }

        .nav-links a:hover {
            color: #6C63FF;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-nav {
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-nav-login {
            color: #6C63FF;
            background: transparent;
        }

        .btn-nav-login:hover {
            background: rgba(108, 99, 255, 0.08);
        }

        .btn-nav-register {
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            color: white;
            box-shadow: 0 4px 15px rgba(108, 99, 255, 0.25);
        }

        .btn-nav-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(108, 99, 255, 0.35);
        }

        .btn-nav-logout {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.25);
        }

        .btn-nav-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(239, 68, 68, 0.35);
        }

        .btn-nav-dashboard {
            background: linear-gradient(135deg, #22C55E, #16A34A);
            color: white;
        }

        .btn-nav-dashboard:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(34, 197, 94, 0.35);
        }

        /* Mobile Menu */
        .hamburger {
            display: none;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            padding: 5px;
            background: none;
            border: none;
        }

        .hamburger span {
            display: block;
            width: 28px;
            height: 3px;
            background: #1F2937;
            border-radius: 3px;
            transition: all 0.3s ease;
        }

        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(5px, 6px);
        }

        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(5px, -6px);
        }

        /* =============================================
           HERO SECTION
        ============================================= */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            padding: 120px 0 80px;
            background: #FFFDF8;
        }

        .hero-bg-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.15;
            pointer-events: none;
        }

        .hero-bg-blob-1 {
            width: 600px;
            height: 600px;
            background: #6C63FF;
            top: -200px;
            right: -100px;
            animation: floatBlob 20s ease-in-out infinite;
        }

        .hero-bg-blob-2 {
            width: 400px;
            height: 400px;
            background: #8B5CF6;
            bottom: -100px;
            left: -50px;
            animation: floatBlob 25s ease-in-out infinite reverse;
        }

        .hero-bg-blob-3 {
            width: 300px;
            height: 300px;
            background: #22C55E;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: floatBlob 30s ease-in-out infinite;
        }

        @keyframes floatBlob {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(30px, -40px) scale(1.1); }
            50% { transform: translate(-30px, 30px) scale(0.9); }
            75% { transform: translate(40px, -20px) scale(1.05); }
        }

        .hero-particles {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .hero-particle {
            position: absolute;
            width: 6px;
            height: 6px;
            background: rgba(108, 99, 255, 0.2);
            border-radius: 50%;
            animation: floatParticle 15s infinite linear;
        }

        .hero-particle:nth-child(1) { left: 10%; animation-delay: 0s; animation-duration: 12s; }
        .hero-particle:nth-child(2) { left: 20%; animation-delay: -2s; animation-duration: 18s; width: 8px; height: 8px; }
        .hero-particle:nth-child(3) { left: 30%; animation-delay: -4s; animation-duration: 14s; }
        .hero-particle:nth-child(4) { left: 40%; animation-delay: -6s; animation-duration: 20s; width: 10px; height: 10px; }
        .hero-particle:nth-child(5) { left: 50%; animation-delay: -8s; animation-duration: 16s; }
        .hero-particle:nth-child(6) { left: 60%; animation-delay: -10s; animation-duration: 22s; width: 7px; height: 7px; }
        .hero-particle:nth-child(7) { left: 70%; animation-delay: -12s; animation-duration: 13s; }
        .hero-particle:nth-child(8) { left: 80%; animation-delay: -14s; animation-duration: 19s; width: 9px; height: 9px; }
        .hero-particle:nth-child(9) { left: 90%; animation-delay: -16s; animation-duration: 15s; }
        .hero-particle:nth-child(10) { left: 95%; animation-delay: -18s; animation-duration: 17s; width: 12px; height: 12px; }

        @keyframes floatParticle {
            0% { transform: translateY(100vh) rotate(0deg) scale(0); opacity: 0; }
            10% { opacity: 1; transform: scale(1); }
            90% { opacity: 1; }
            100% { transform: translateY(-100vh) rotate(720deg) scale(0); opacity: 0; }
        }

        .hero-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .hero-text {
            animation: fadeInUp 1s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-badge {
            display: inline-block;
            padding: 6px 18px;
            background: rgba(108, 99, 255, 0.1);
            color: #6C63FF;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            letter-spacing: 0.5px;
        }

        .hero-title {
            font-size: 52px;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 20px;
            color: #1F2937;
        }

        .hero-title span {
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: 18px;
            color: #6B7280;
            line-height: 1.8;
            margin-bottom: 30px;
            max-width: 500px;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 40px;
        }

        .btn-primary {
            padding: 14px 36px;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(108, 99, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.15) 50%, transparent 70%);
            transform: rotate(45deg) translateX(-100%);
            transition: transform 0.6s ease;
        }

        .btn-primary:hover::before {
            transform: rotate(45deg) translateX(100%);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 35px rgba(108, 99, 255, 0.4);
        }

        .btn-secondary {
            padding: 14px 36px;
            background: white;
            color: #6C63FF;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            border-color: #6C63FF;
            transform: translateY(-3px);
            box-shadow: 0 4px 20px rgba(108, 99, 255, 0.1);
        }

        .hero-trust {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .trust-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #4B5563;
            font-weight: 500;
        }

        .trust-item i {
            color: #22C55E;
            font-size: 18px;
        }

        /* Hero Visual */
        .hero-visual {
            position: relative;
            animation: fadeInUp 1.2s ease;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .hero-dashboard-mockup {
            width: 100%;
            max-width: 550px;
            background: white;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(108, 99, 255, 0.12);
            border: 1px solid rgba(108, 99, 255, 0.06);
            position: relative;
            animation: floatMockup 6s ease-in-out infinite;
        }

        @keyframes floatMockup {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }

        .mockup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .mockup-dots {
            display: flex;
            gap: 6px;
        }

        .mockup-dots span {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: block;
        }

        .mockup-dots span:nth-child(1) { background: #EF4444; }
        .mockup-dots span:nth-child(2) { background: #F59E0B; }
        .mockup-dots span:nth-child(3) { background: #22C55E; }

        .mockup-title {
            font-size: 14px;
            font-weight: 600;
            color: #1F2937;
        }

        .mockup-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .mockup-stat {
            background: #F9FAFB;
            padding: 14px;
            border-radius: 12px;
            text-align: center;
        }

        .mockup-stat .number {
            font-size: 22px;
            font-weight: 700;
            color: #6C63FF;
        }

        .mockup-stat .label {
            font-size: 11px;
            color: #6B7280;
        }

        .mockup-chart {
            height: 80px;
            background: linear-gradient(135deg, rgba(108, 99, 255, 0.1), rgba(139, 92, 246, 0.05));
            border-radius: 12px;
            display: flex;
            align-items: flex-end;
            padding: 10px;
            gap: 8px;
        }

        .mockup-bar {
            flex: 1;
            background: linear-gradient(180deg, #6C63FF, #8B5CF6);
            border-radius: 4px 4px 0 0;
            height: 40%;
            animation: barGrow 2s ease infinite;
        }

        .mockup-bar:nth-child(2) { height: 70%; animation-delay: 0.3s; }
        .mockup-bar:nth-child(3) { height: 50%; animation-delay: 0.6s; }
        .mockup-bar:nth-child(4) { height: 90%; animation-delay: 0.9s; }
        .mockup-bar:nth-child(5) { height: 60%; animation-delay: 1.2s; }
        .mockup-bar:nth-child(6) { height: 75%; animation-delay: 1.5s; }

        @keyframes barGrow {
            0%, 100% { transform: scaleY(1); }
            50% { transform: scaleY(1.1); }
        }

        .floating-icon-hero {
            position: absolute;
            font-size: 30px;
            animation: floatIcon 4s ease-in-out infinite;
            background: white;
            padding: 12px;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }

        .floating-icon-hero:nth-child(2) { top: -20px; right: -10px; animation-delay: 0s; }
        .floating-icon-hero:nth-child(3) { bottom: -10px; left: -20px; animation-delay: -1s; }
        .floating-icon-hero:nth-child(4) { top: 30%; right: -30px; animation-delay: -2s; }
        .floating-icon-hero:nth-child(5) { bottom: 20%; left: -30px; animation-delay: -3s; }

        @keyframes floatIcon {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-15px) rotate(5deg); }
        }

        /* =============================================
           STATISTICS SECTION
        ============================================= */
        .stats {
            background: white;
            border-top: 1px solid #F3F4F6;
            border-bottom: 1px solid #F3F4F6;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
        }

        .stat-number {
            font-size: 48px;
            font-weight: 800;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
        }

        .stat-label {
            font-size: 16px;
            color: #6B7280;
            margin-top: 8px;
            font-weight: 500;
        }

        /* =============================================
           FEATURES SECTION
        ============================================= */
        .features {
            background: #FFFDF8;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .feature-card {
            background: white;
            padding: 40px 30px;
            border-radius: 20px;
            border: 1px solid #F3F4F6;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(108, 99, 255, 0.08);
            border-color: rgba(108, 99, 255, 0.15);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: rgba(108, 99, 255, 0.08);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #6C63FF;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            background: rgba(108, 99, 255, 0.15);
            transform: scale(1.05) rotate(-5deg);
        }

        .feature-card h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #1F2937;
        }

        .feature-card p {
            color: #6B7280;
            font-size: 15px;
            line-height: 1.7;
        }

        /* =============================================
           HOW IT WORKS
        ============================================= */
        .how-it-works {
            background: white;
        }

        .timeline {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            position: relative;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 10%;
            right: 10%;
            height: 2px;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            transform: translateY(-50%);
            opacity: 0.2;
        }

        .timeline-step {
            text-align: center;
            padding: 20px;
            position: relative;
        }

        .step-number {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            margin: 0 auto 16px;
            position: relative;
            z-index: 2;
        }

        .timeline-step h4 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1F2937;
        }

        .timeline-step p {
            color: #6B7280;
            font-size: 14px;
        }

        /* =============================================
           TESTIMONIALS
        ============================================= */
        .testimonials {
            background: #FFFDF8;
        }

        .testimonials-slider {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .testimonial-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            border: 1px solid #F3F4F6;
            transition: all 0.3s ease;
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.05);
        }

        .testimonial-stars {
            color: #F59E0B;
            font-size: 16px;
            margin-bottom: 12px;
        }

        .testimonial-text {
            font-size: 15px;
            color: #4B5563;
            line-height: 1.8;
            margin-bottom: 16px;
            font-style: italic;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .testimonial-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
        }

        .testimonial-name {
            font-weight: 600;
            color: #1F2937;
            font-size: 14px;
        }

        .testimonial-role {
            font-size: 13px;
            color: #6B7280;
        }

        /* =============================================
           CTA SECTION
        ============================================= */
        .cta {
            background: linear-gradient(135deg, #6C63FF, #8B5CF6);
            position: relative;
            overflow: hidden;
            padding: 80px 0;
        }

        .cta::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            top: -100px;
            right: -100px;
            animation: floatBlob 15s ease-in-out infinite;
        }

        .cta::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            bottom: -50px;
            left: -50px;
            animation: floatBlob 20s ease-in-out infinite reverse;
        }

        .cta-content {
            position: relative;
            z-index: 2;
            text-align: center;
            color: white;
        }

        .cta-content h2 {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .cta-content p {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 30px;
        }

        .cta-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-cta-primary {
            padding: 14px 36px;
            background: white;
            color: #6C63FF;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .btn-cta-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }

        .btn-cta-secondary {
            padding: 14px 36px;
            background: transparent;
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .btn-cta-secondary:hover {
            background: rgba(255,255,255,0.1);
            border-color: white;
            transform: translateY(-3px);
        }

        /* =============================================
           FOOTER
        ============================================= */
        .footer {
            background: #1F2937;
            color: #9CA3AF;
            padding: 60px 0 30px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-brand h3 {
            color: white;
            font-size: 24px;
            margin-bottom: 12px;
        }

        .footer-brand h3 span {
            color: #6C63FF;
        }

        .footer-brand p {
            font-size: 14px;
            line-height: 1.8;
            max-width: 300px;
        }

        .footer-col h4 {
            color: white;
            font-size: 16px;
            margin-bottom: 16px;
        }

        .footer-col ul {
            list-style: none;
        }

        .footer-col ul li {
            margin-bottom: 10px;
        }

        .footer-col ul a {
            color: #9CA3AF;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .footer-col ul a:hover {
            color: white;
        }

        .footer-social {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }

        .footer-social a {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9CA3AF;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .footer-social a:hover {
            background: #6C63FF;
            color: white;
            transform: translateY(-3px);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.05);
            padding-top: 20px;
            text-align: center;
            font-size: 14px;
        }

        /* =============================================
           RESPONSIVE
        ============================================= */
        @media (max-width: 1024px) {
            .hero-title {
                font-size: 40px;
            }

            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .timeline {
                grid-template-columns: repeat(2, 1fr);
            }

            .timeline::before {
                display: none;
            }

            .testimonials-slider {
                grid-template-columns: repeat(2, 1fr);
            }

            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: rgba(255, 253, 248, 0.98);
                backdrop-filter: blur(20px);
                padding: 20px;
                flex-direction: column;
                gap: 16px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.05);
            }

            .nav-links.active {
                display: flex;
            }

            .hamburger {
                display: flex;
            }

            .hero-content {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .hero-title {
                font-size: 34px;
            }

            .hero-subtitle {
                margin: 0 auto 30px;
            }

            .hero-buttons {
                justify-content: center;
            }

            .hero-trust {
                justify-content: center;
            }

            .hero-visual {
                order: -1;
            }

            .hero-dashboard-mockup {
                max-width: 100%;
            }

            .floating-icon-hero {
                display: none;
            }

            .section-title {
                font-size: 30px;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }

            .stat-number {
                font-size: 32px;
            }

            .timeline {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .testimonials-slider {
                grid-template-columns: 1fr;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .cta-content h2 {
                font-size: 30px;
            }

            .hero-dashboard-mockup {
                padding: 20px;
            }

            .mockup-stats {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }

            .mockup-stat .number {
                font-size: 18px;
            }

            .nav-actions .btn-nav {
                padding: 8px 16px;
                font-size: 13px;
            }

            .section-padding {
                padding: 60px 0;
            }
        }

        @media (max-width: 480px) {
            .hero-title {
                font-size: 28px;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn-primary, .btn-secondary {
                width: 100%;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .stat-item {
                padding: 12px;
            }

            .stat-number {
                font-size: 28px;
            }

            .stat-label {
                font-size: 13px;
            }

            .section-title {
                font-size: 26px;
            }

            .cta-content h2 {
                font-size: 24px;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn-cta-primary, .btn-cta-secondary {
                width: 100%;
                justify-content: center;
            }
        }

        /* Scroll reveal animation */
        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: all 0.8s ease;
        }

        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>

    <!-- =============================================
    NAVIGATION
    ============================================= -->
    <nav class="navbar" id="navbar">
        <div class="container">
            <a href="#home" class="nav-logo">
                <span class="logo-icon">🎓</span>
                EduHack <span>AI</span>
            </a>

            <ul class="nav-links" id="navLinks">
                <li><a href="#features">Features</a></li>
                <li><a href="#how-it-works">How It Works</a></li>
                <li><a href="#testimonials">Testimonials</a></li>
                <li><a href="#cta">Contact</a></li>
            </ul>

            <div class="nav-actions">
                <a href="login.php" class="btn-nav btn-nav-login">Login</a>
                <a href="register.php" class="btn-nav btn-nav-register">Register</a>
                <?php if ($isLoggedIn): ?>
                    <a href="logout.php" class="btn-nav btn-nav-logout">Logout</a>
                <?php endif; ?>
                <button class="hamburger" id="hamburger" aria-label="Toggle menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </nav>

    <!-- =============================================
    HERO SECTION
    ============================================= -->
    <section class="hero" id="home">
        <!-- Background Elements -->
        <div class="hero-bg-blob hero-bg-blob-1"></div>
        <div class="hero-bg-blob hero-bg-blob-2"></div>
        <div class="hero-bg-blob hero-bg-blob-3"></div>

        <div class="hero-particles">
            <div class="hero-particle"></div>
            <div class="hero-particle"></div>
            <div class="hero-particle"></div>
            <div class="hero-particle"></div>
            <div class="hero-particle"></div>
            <div class="hero-particle"></div>
            <div class="hero-particle"></div>
            <div class="hero-particle"></div>
            <div class="hero-particle"></div>
            <div class="hero-particle"></div>
        </div>

        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <span class="hero-badge">🚀 AI-Powered Learning</span>
                    <h1 class="hero-title">
                        Transform Learning with<br>
                        <span>EduHack AI</span>
                    </h1>
                    <p class="hero-subtitle">
                        An intelligent learning ecosystem that helps students learn effectively 
                        and empowers teachers to manage educational content with ease.
                    </p>
                    <div class="hero-buttons">
                        <?php if ($isLoggedIn): ?>
                            <a href="<?php echo $userRole; ?>/dashboard.php" class="btn-primary">
                                <i class="fas fa-rocket"></i> Go to Dashboard
                            </a>
                        <?php else: ?>
                            <a href="register.php" class="btn-primary">
                                <i class="fas fa-rocket"></i> Get Started
                            </a>
                            <a href="#features" class="btn-secondary">
                                <i class="fas fa-info-circle"></i> Explore Features
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="hero-trust">
                        <span class="trust-item"><i class="fas fa-check-circle"></i> Smart Learning</span>
                        <span class="trust-item"><i class="fas fa-check-circle"></i> Interactive Quizzes</span>
                        <span class="trust-item"><i class="fas fa-check-circle"></i> Progress Tracking</span>
                    </div>
                </div>

                <div class="hero-visual">
                    <div class="hero-dashboard-mockup">
                        <div class="mockup-header">
                            <div class="mockup-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                            <span class="mockup-title">📊 Learning Dashboard</span>
                            <span style="font-size:14px; color:#9CA3AF;">Live</span>
                        </div>
                        <div class="mockup-stats">
                            <div class="mockup-stat">
                                <div class="number">89%</div>
                                <div class="label">Completion</div>
                            </div>
                            <div class="mockup-stat">
                                <div class="number">12</div>
                                <div class="label">Quizzes</div>
                            </div>
                            <div class="mockup-stat">
                                <div class="number">45</div>
                                <div class="label">Notes</div>
                            </div>
                        </div>
                        <div class="mockup-chart">
                            <div class="mockup-bar"></div>
                            <div class="mockup-bar"></div>
                            <div class="mockup-bar"></div>
                            <div class="mockup-bar"></div>
                            <div class="mockup-bar"></div>
                            <div class="mockup-bar"></div>
                        </div>
                    </div>

                    <!-- Floating Icons -->
                    <div class="floating-icon-hero" style="top:-20px; right:-10px;">📚</div>
                    <div class="floating-icon-hero" style="bottom:-10px; left:-20px;">🎓</div>
                    <div class="floating-icon-hero" style="top:30%; right:-30px;">🧠</div>
                    <div class="floating-icon-hero" style="bottom:20%; left:-30px;">🏆</div>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================
    STATISTICS SECTION
    ============================================= -->
    <section class="stats section-padding">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item reveal">
                    <div class="stat-number" data-count="500">0</div>
                    <div class="stat-label">Students</div>
                </div>
                <div class="stat-item reveal">
                    <div class="stat-number" data-count="100">0</div>
                    <div class="stat-label">Study Notes</div>
                </div>
                <div class="stat-item reveal">
                    <div class="stat-number" data-count="50">0</div>
                    <div class="stat-label">Interactive Quizzes</div>
                </div>
                <div class="stat-item reveal">
                    <div class="stat-number" data-count="1000">0</div>
                    <div class="stat-label">Learning Hours</div>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================
    FEATURES SECTION
    ============================================= -->
    <section class="features section-padding" id="features">
        <div class="container">
            <div class="text-center">
                <span class="section-tag">✨ Features</span>
                <h2 class="section-title">Everything You Need for <span>Smart Learning</span></h2>
                <p class="section-subtitle">
                    Powerful tools designed to enhance the learning experience for both students and teachers.
                </p>
            </div>

            <div class="features-grid">
                <div class="feature-card reveal">
                    <div class="feature-icon">📝</div>
                    <h3>Smart Notes Management</h3>
                    <p>Teachers can upload, organize, and manage study materials. Students access notes anytime, anywhere.</p>
                </div>
                <div class="feature-card reveal">
                    <div class="feature-icon">🧪</div>
                    <h3>Interactive Quiz System</h3>
                    <p>Create engaging quizzes with multiple-choice questions. Students get instant feedback and results.</p>
                </div>
                <div class="feature-card reveal">
                    <div class="feature-icon">🤖</div>
                    <h3>Smart Study Assistant</h3>
                    <p>AI-powered study assistant helps students understand concepts, generate questions, and improve learning.</p>
                </div>
                <div class="feature-card reveal">
                    <div class="feature-icon">📊</div>
                    <h3>Progress Tracking</h3>
                    <p>Monitor learning progress with detailed analytics. Identify strengths and areas for improvement.</p>
                </div>
                <div class="feature-card reveal">
                    <div class="feature-icon">🏆</div>
                    <h3>Student Leaderboard</h3>
                    <p>Gamified learning experience with leaderboards. Motivate students through healthy competition.</p>
                </div>
                <div class="feature-card reveal">
                    <div class="feature-icon">💬</div>
                    <h3>Discussion Forum</h3>
                    <p>Collaborative learning environment where students and teachers interact, share knowledge, and grow together.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================
    HOW IT WORKS
    ============================================= -->
    <section class="how-it-works section-padding" id="how-it-works">
        <div class="container">
            <div class="text-center">
                <span class="section-tag">📋 How It Works</span>
                <h2 class="section-title">Simple Steps to <span>Start Learning</span></h2>
                <p class="section-subtitle">
                    Get started with EduHack AI in just a few easy steps.
                </p>
            </div>

            <div class="timeline">
                <div class="timeline-step reveal">
                    <div class="step-number">1</div>
                    <h4>📚 Teacher Uploads Notes</h4>
                    <p>Teachers upload study materials and organize content for students.</p>
                </div>
                <div class="timeline-step reveal">
                    <div class="step-number">2</div>
                    <h4>📝 Teacher Creates Quizzes</h4>
                    <p>Create interactive assessments to test student understanding.</p>
                </div>
                <div class="timeline-step reveal">
                    <div class="step-number">3</div>
                    <h4>🎯 Students Learn &amp; Practice</h4>
                    <p>Students access notes, attempt quizzes, and track their progress.</p>
                </div>
                <div class="timeline-step reveal">
                    <div class="step-number">4</div>
                    <h4>💬 Students Participate</h4>
                    <p>Engage in discussions, ask questions, and share knowledge.</p>
                </div>
                <div class="timeline-step reveal">
                    <div class="step-number">5</div>
                    <h4>📈 Track Performance</h4>
                    <p>Monitor progress, identify strengths, and improve continuously.</p>
                </div>
                <div class="timeline-step reveal">
                    <div class="step-number">6</div>
                    <h4>🏆 Achieve Success</h4>
                    <p>Master subjects, earn achievements, and excel in learning.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================
    TESTIMONIALS
    ============================================= -->
    <section class="testimonials section-padding" id="testimonials">
        <div class="container">
            <div class="text-center">
                <span class="section-tag">💬 Testimonials</span>
                <h2 class="section-title">What Our Users <span>Say</span></h2>
                <p class="section-subtitle">
                    Real feedback from students and teachers using EduHack AI.
                </p>
            </div>

            <div class="testimonials-slider">
                <div class="testimonial-card reveal">
                    <div class="testimonial-stars">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">
                        "EduHack AI has completely transformed how I learn. The AI study assistant helps me understand complex topics easily. I've improved my grades significantly!"
                    </p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">JS</div>
                        <div>
                            <div class="testimonial-name">Jane Smith</div>
                            <div class="testimonial-role">Student</div>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card reveal">
                    <div class="testimonial-stars">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">
                        "As a teacher, EduHack AI makes content management effortless. I can upload notes, create quizzes, and track student progress all in one place. Highly recommended!"
                    </p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">JD</div>
                        <div>
                            <div class="testimonial-name">John Doe</div>
                            <div class="testimonial-role">Teacher</div>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card reveal">
                    <div class="testimonial-stars">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">
                        "The leaderboard feature motivates me to study more. I love competing with my peers and tracking my progress. EduHack AI makes learning fun and engaging!"
                    </p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">AR</div>
                        <div>
                            <div class="testimonial-name">Alex Rivera</div>
                            <div class="testimonial-role">Student</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================
    CTA SECTION
    ============================================= -->
    <section class="cta" id="cta">
        <div class="container">
            <div class="cta-content">
                <h2>Start Your Learning Journey Today 🚀</h2>
                <p>Join thousands of students and teachers revolutionizing education with AI.</p>
                <div class="cta-buttons">
                    <?php if ($isLoggedIn): ?>
                        <a href="<?php echo $userRole; ?>/dashboard.php" class="btn-cta-primary">
                            <i class="fas fa-rocket"></i> Go to Dashboard
                        </a>
                    <?php else: ?>
                        <a href="register.php" class="btn-cta-primary">
                            <i class="fas fa-user-plus"></i> Register Now
                        </a>
                        <a href="login.php" class="btn-cta-secondary">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================
    FOOTER
    ============================================= -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <h3>EduHack <span>AI</span></h3>
                    <p>
                        An intelligent learning ecosystem that helps students learn effectively 
                        and empowers teachers to manage educational content with ease.
                    </p>
                    <div class="footer-social">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="footer-col">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="#testimonials">Testimonials</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Platform</h4>
                    <ul>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                        <li><a href="#">Notes</a></li>
                        <li><a href="#">Quizzes</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Contact</h4>
                    <ul>
                        <li><a href="mailto:info@eduhack.com"><i class="fas fa-envelope"></i> info@eduhack.com</a></li>
                        <li><a href="#"><i class="fas fa-map-marker-alt"></i> India</a></li>
                        <li><a href="#"><i class="fas fa-phone"></i> +91 98765 43210</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 EduHack AI. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <!-- =============================================
    JAVASCRIPT
    ============================================= -->
    <script>
        // =============================================
        // NAVBAR SCROLL EFFECT
        // =============================================
        const navbar = document.getElementById('navbar');
        let lastScroll = 0;

        window.addEventListener('scroll', function() {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
            
            lastScroll = currentScroll;
        });

        // =============================================
        // MOBILE MENU
        // =============================================
        const hamburger = document.getElementById('hamburger');
        const navLinks = document.getElementById('navLinks');

        hamburger.addEventListener('click', function() {
            this.classList.toggle('active');
            navLinks.classList.toggle('active');
        });

        // Close menu on link click
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                hamburger.classList.remove('active');
                navLinks.classList.remove('active');
            });
        });

        // =============================================
        // SCROLL REVEAL ANIMATIONS
        // =============================================
        const revealElements = document.querySelectorAll('.reveal');

        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        revealElements.forEach(el => {
            revealObserver.observe(el);
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
        }, {
            threshold: 0.3
        });

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
                // Add + for numbers over 1000
                if (target >= 1000) {
                    element.textContent = current + '+';
                } else {
                    element.textContent = current;
                }
            }, stepTime);
        }

        // =============================================
        // SMOOTH SCROLL FOR NAV LINKS
        // =============================================
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    e.preventDefault();
                    const offsetTop = targetElement.offsetTop - 80;
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // =============================================
        // PARALLAX EFFECT ON HERO
        // =============================================
        document.addEventListener('mousemove', function(e) {
            const hero = document.querySelector('.hero-visual');
            if (!hero) return;
            
            const x = (e.clientX / window.innerWidth - 0.5) * 20;
            const y = (e.clientY / window.innerHeight - 0.5) * 20;
            
            hero.style.transform = `translate(${x}px, ${y}px)`;
        });

        // =============================================
        // CONSOLE LOG
        // =============================================
        console.log('🎓 EduHack AI - Premium Learning Platform');
        console.log('✨ Design: Modern SaaS UI');
        console.log('🚀 Built for College Internship & Hackathon');
        console.log('📧 Contact: info@eduhack.com');

        // =============================================
        // LOADING ANIMATION (optional)
        // =============================================
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ EduHack AI loaded successfully!');
        });
    </script>

</body>
</html>