<?php
/**
 * Unified Login Redirect
 * Dedicated admin login page is permanently disabled.
 * All logins (Admin, Worker, Gamer) route through the unified login portal (/login.php).
 */
require_once __DIR__ . '/../common/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle Logout
if (isset($_GET['logout']) || isset($_GET['logged_out'])) {
    if (function_exists('clear_user_remember_token')) {
        clear_user_remember_token($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null);
    }
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_user'],
        $_SESSION['admin_username'],
        $_SESSION['admin_role'],
        $_SESSION['is_super_admin'],
        $_SESSION['admin_logged_in'],
        $_SESSION['is_worker'],
        $_SESSION['worker_id'],
        $_SESSION['user_id'],
        $_SESSION['username'],
        $_SESSION['role'],
        $_SESSION['user_logged_in']
    );
    @session_destroy();
    safe_redirect("../login.php?logged_out=1");
    exit;
}

// If already authenticated as admin, go straight to admin dashboard
if (!empty($_SESSION['admin_id']) || (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') || !empty($_SESSION['admin_logged_in'])) {
    safe_redirect("index.php");
    exit;
}

// Otherwise, redirect to unified User Panel login
safe_redirect("../login.php");
exit;
