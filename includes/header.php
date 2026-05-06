<?php
/**
 * Header Include
 * Orphanage Management System
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$baseUrl = '/orphanage-management-system';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Orphanage Management System - Providing care, love, and hope for every child.">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | ' : ''; ?>Orphanage Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="mainNavbar">
        <div class="nav-container">
            <a href="<?php echo $baseUrl; ?>/index.php" class="nav-logo">
                <i class="fas fa-heart"></i>
                <span>HopeNest</span>
            </a>

            <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <ul class="nav-menu" id="navMenu">
                <?php if (isAdmin()): ?>
                    <!-- Admin Navigation -->
                    <li><a href="<?php echo $baseUrl; ?>/admin/dashboard.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-th-large"></i> Dashboard</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/admin/orphans.php" class="<?php echo $currentPage === 'orphans' ? 'active' : ''; ?>"><i class="fas fa-child"></i> Orphans</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/admin/users.php" class="<?php echo $currentPage === 'users' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Users</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/admin/verify_users.php" class="<?php echo $currentPage === 'verify_users' ? 'active' : ''; ?>"><i class="fas fa-user-check"></i> Verify</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/admin/donations.php" class="<?php echo $currentPage === 'donations' ? 'active' : ''; ?>"><i class="fas fa-hand-holding-heart"></i> Donations</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/admin/adoptions.php" class="<?php echo $currentPage === 'adoptions' ? 'active' : ''; ?>"><i class="fas fa-file-alt"></i> Adoptions</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/admin/feedback.php" class="<?php echo $currentPage === 'feedback' ? 'active' : ''; ?>"><i class="fas fa-comment-dots"></i> Feedback</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/admin/matching.php" class="<?php echo $currentPage === 'matching' ? 'active' : ''; ?>"><i class="fas fa-magic"></i> Matching</a></li>
                    <li class="nav-user">
                        <span class="nav-user-name"><i class="fas fa-user-shield"></i> <?php echo $_SESSION['admin_name'] ?? 'Admin'; ?></span>
                        <a href="<?php echo $baseUrl; ?>/logout.php" class="btn btn-sm btn-outline"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </li>
                <?php elseif (isLoggedIn()): ?>
                    <!-- User Navigation -->
                    <li><a href="<?php echo $baseUrl; ?>/user/dashboard.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-th-large"></i> Dashboard</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/user/orphans.php" class="<?php echo $currentPage === 'orphans' ? 'active' : ''; ?>"><i class="fas fa-child"></i> Children</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/user/donate.php" class="<?php echo $currentPage === 'donate' ? 'active' : ''; ?>"><i class="fas fa-hand-holding-heart"></i> Donate</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/user/adopt.php" class="<?php echo $currentPage === 'adopt' ? 'active' : ''; ?>"><i class="fas fa-heart"></i> Adopt</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/user/my_requests.php" class="<?php echo $currentPage === 'my_requests' ? 'active' : ''; ?>"><i class="fas fa-clipboard-list"></i> My Requests</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/user/feedback.php" class="<?php echo $currentPage === 'feedback' ? 'active' : ''; ?>"><i class="fas fa-comment-dots"></i> Feedback</a></li>
                    <li class="nav-user">
                        <a href="<?php echo $baseUrl; ?>/user/profile.php" class="nav-user-name"><i class="fas fa-user"></i> <?php echo $_SESSION['user_name'] ?? 'User'; ?></a>
                        <a href="<?php echo $baseUrl; ?>/logout.php" class="btn btn-sm btn-outline"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </li>
                <?php else: ?>
                    <!-- Guest Navigation -->
                    <li><a href="<?php echo $baseUrl; ?>/index.php" class="<?php echo $currentPage === 'index' ? 'active' : ''; ?>">Home</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/login.php" class="<?php echo $currentPage === 'login' ? 'active' : ''; ?>">Login</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/register.php" class="btn btn-primary btn-sm">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php $flash = getFlash(); if ($flash): ?>
    <div class="flash-message flash-<?php echo $flash['type']; ?>" id="flashMessage">
        <div class="flash-content">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
            <span><?php echo $flash['message']; ?></span>
            <button class="flash-close" onclick="this.parentElement.parentElement.remove()"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content Wrapper -->
    <main class="main-content">
