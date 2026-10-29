<?php
// Prevent multiple inclusions
if (!defined('ADEPT_PLAY_CONFIG')) {
    define('ADEPT_PLAY_CONFIG', true);

    // Suppress deprecated warnings (PHP 8.5 compatibility)
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');

    // Start Output Buffering for safe header redirects
    if (ob_get_level() === 0) {
        ob_start();
    }

    // Set 1-Year Persistent Cookie Lifetime
    $one_year = 365 * 24 * 3600;
    ini_set('session.cookie_lifetime', $one_year);
    ini_set('session.gc_maxlifetime', $one_year);

    if (session_status() === PHP_SESSION_NONE) {
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        session_set_cookie_params([
            'lifetime' => $one_year,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? '',
            'secure' => $is_https,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }

    // =========================================================================
    // ✅ TiDB CLOUD LIVE DATABASE CONNECTION (SSL ENABLED)
    // =========================================================================
    $db_host = "gateway01.ap-southeast-1.prod.aws.tidbcloud.com";
    $db_user = "33NZV583e1gPsMh.root";
    $db_pass = "mcj5pocb1FlVZD68";
    $db_name = "test";
    $db_port = 4000;

    $conn = mysqli_init();
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    @$conn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port, NULL, MYSQLI_CLIENT_SSL);

    if ($conn->connect_error) {
        $script_name = basename($_SERVER['PHP_SELF']);
        if ($script_name !== 'install.php') {
            safe_redirect("install.php");
            exit;
        }
    } else {
        $conn->set_charset("utf8mb4");
    }

    // =========================================================================
    // ✅ PERSISTENT LOGIN AUTO-RESTORE (UNTIL LOGOUT)
    // =========================================================================
    if (!isset($_SESSION['user_id']) && !empty($_COOKIE['adept_remember'])) {
        $rem_token = trim($_COOKIE['adept_remember']);
        if (preg_match('/^[a-f0-9]{32,128}$/i', $rem_token)) {
            $stmt = $conn->prepare("SELECT id, username, email, phone, role, status FROM users WHERE remember_token = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $rem_token);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($u = $res->fetch_assoc()) {
                    if (($u['status'] ?? 'active') !== 'blocked') {
                        $_SESSION['user_id'] = (int)$u['id'];
                        $_SESSION['username'] = $u['username'];
                        $_SESSION['email'] = $u['email'];
                        $_SESSION['role'] = $u['role'] ?? 'user';
                        if ($u['role'] === 'admin') {
                            $_SESSION['admin_id'] = (int)$u['id'];
                            $_SESSION['admin_user'] = $u['username'];
                        }
                    }
                }
                $stmt->close();
            }
        }
    }

    // =========================================================================
    // ✅ CASHFREE LIVE PRODUCTION CREDENTIALS
    // =========================================================================
    define('CASHFREE_APP_ID', '1494190b594e98ed762a2a943320914941');
    define('CASHFREE_SECRET_KEY', 'cfsk_ma_prod_979295dd86c34ea7eef3d56c1e07d25e_27097462');
    define('CASHFREE_ENVIRONMENT', 'PROD');

    function get_cashfree_app_id() { return CASHFREE_APP_ID; }
    function get_cashfree_secret_key() { return CASHFREE_SECRET_KEY; }
    function get_cashfree_env() { return 'PROD'; }

    // Bulletproof Redirect (Works even if headers already sent)
    function safe_redirect($url) {
        if (!headers_sent()) {
            header("Location: " . $url);
            exit;
        }
        echo '<script>window.location.replace(' . json_encode($url) . ');</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '"></noscript>';
        exit;
    }

    function sanitize($data) {
        global $conn;
        if ($data === null) return '';
        return htmlspecialchars(trim($conn->real_escape_string((string)$data)));
    }

    function clean($data) { return sanitize($data); }
    function clean_input($data) { return sanitize($data); }

    function format_inr($amount) {
        return '₹' . number_format((float)$amount, 2);
    }

    function is_logged_in() {
        return isset($_SESSION['user_id']);
    }

    function check_user_auth() {
        if (!isset($_SESSION['user_id'])) {
            safe_redirect("login.php");
            exit;
        }
    }

    function check_user_login() {
        check_user_auth();
    }

    function check_auth() {
        check_user_auth();
    }

    function check_admin_auth() {
        if (!isset($_SESSION['admin_id']) && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
            safe_redirect("login.php");
            exit;
        }
    }

    // Issue 1-Year Persistent Remember Token
    function set_user_remember_token($user_id) {
        global $conn;
        $user_id = (int)$user_id;
        $token = bin2hex(random_bytes(32));
        $stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $token, $user_id);
            $stmt->execute();
            $stmt->close();
        }
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        $expire = time() + (365 * 24 * 3600);
        setcookie('adept_remember', $token, [
            'expires' => $expire,
            'path' => '/',
            'secure' => $is_https,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        $_COOKIE['adept_remember'] = $token;
    }

    // Clear Persistent Remember Token (On explicit Logout)
    function clear_user_remember_token($user_id = null) {
        global $conn;
        if ($user_id) {
            $uid = (int)$user_id;
            @$conn->query("UPDATE users SET remember_token = NULL WHERE id = {$uid}");
        }
        setcookie('adept_remember', '', time() - 86400, '/');
        unset($_COOKIE['adept_remember']);
    }

    function get_setting($key, $default = '') {
        global $conn;
        $s = $conn->prepare("SELECT value, setting_value FROM settings WHERE `key` = ? OR `setting_key` = ? LIMIT 1");
        if ($s) {
            $s->bind_param("ss", $key, $key);
            $s->execute();
            $res = $s->get_result();
            if ($row = $res->fetch_assoc()) {
                $val = !empty($row['value']) ? $row['value'] : ($row['setting_value'] ?? '');
                $s->close();
                return $val ?: $default;
            }
            $s->close();
        }
        return $default;
    }
}
?>
