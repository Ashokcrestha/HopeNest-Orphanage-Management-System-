<?php
/**
 * Authentication Middleware
 * Orphanage Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Require user login - redirect to login page if not authenticated
 */
function requireUserLogin() {
    if (!isLoggedIn()) {
        setFlash('error', 'Please login to access this page.');
        redirect('/orphanage-management-system/login.php');
    }
}

/**
 * Require admin login - redirect to login page if not authenticated
 */
function requireAdminLogin() {
    if (!isAdmin()) {
        setFlash('error', 'Admin access required.');
        redirect('/orphanage-management-system/login.php');
    }
}
?>
