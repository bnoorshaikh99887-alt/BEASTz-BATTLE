<?php
require_once __DIR__ . '/common/config.php';

// 1. Clear database remember_token for this user/admin
$target_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
if (function_exists('clear_user_remember_token')) {
    clear_user_remember_token($target_id);
} else {
    if ($target_id && $conn && !$conn->connect_error) {
        $uid = (int)$target_id;
        @$conn->query("UPDATE users SET remember_token = NULL WHERE id = {$uid}");
        @$conn->query("UPDATE admin SET remember_token = NULL WHERE id = {$uid}");
    }
}

// 2. Clear cookies
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
setcookie('adept_remember', '', time() - 86400, '/', '', $is_https, true);
setcookie('active_panel', '', time() - 86400, '/', '', $is_https, false);
unset($_COOKIE['adept_remember'], $_COOKIE['active_panel']);

// 3. Clear session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
@session_destroy();

// 4. Safe redirect to login
$redir = (str_contains(__DIR__, '/admin')) ? '../login.php?logged_out=1' : 'login.php?logged_out=1';
safe_redirect($redir);
exit;
