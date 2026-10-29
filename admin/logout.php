<?php
require_once __DIR__ . '/common/config.php';

// 1. Clear database remember_token for this admin/user
$target_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
if ($target_id && $conn && !$conn->connect_error) {
    $uid = (int)$target_id;
    @$conn->query("UPDATE users SET remember_token = NULL WHERE id = {$uid}");
}

// 2. Clear remember token cookie
if (isset($_COOKIE['adept_remember'])) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    setcookie('adept_remember', '', time() - 86400, '/', '', $is_https, true);
    unset($_COOKIE['adept_remember']);
}

// 3. Clear session data and cookie
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
@session_destroy();

// 4. Safe redirect back to user login page
safe_redirect("/login.php?logged_out=1");
exit;
