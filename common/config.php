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

    // Helper functions (defined early so they are always available)
    if (!function_exists('safe_redirect')) {
        function safe_redirect($url) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_write_close();
            }
            if (!headers_sent()) {
                header("Location: " . $url);
                exit;
            }
            echo '<script>window.location.replace(' . json_encode($url) . ');</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '"></noscript>';
            exit;
        }
    }

    if (!function_exists('sanitize')) {
        function sanitize($data) {
            global $conn;
            if ($data === null) return '';
            if ($conn && is_object($conn) && method_exists($conn, 'real_escape_string')) {
                return htmlspecialchars(trim($conn->real_escape_string((string)$data)));
            }
            return htmlspecialchars(trim((string)$data));
        }
    }
    if (!function_exists('clean')) { function clean($data) { return sanitize($data); } }
    if (!function_exists('clean_input')) { function clean_input($data) { return sanitize($data); } }

    if (!function_exists('format_inr')) {
        function get_avatar_url($avatar_str, $username = "Gamer") {
        if (!empty($avatar_str) && (str_starts_with($avatar_str, "http") || str_starts_with($avatar_str, "data:image"))) {
            return $avatar_str;
        }
        $name = trim($username ?: "Gamer");
        $initial = strtoupper(substr($name, 0, 1));
        if (empty($initial)) $initial = "B";
        $colors = ["%23facc15", "%2338bdf8", "%234ade80", "%23f43f5e", "%23a855f7", "%23fb923c"];
        $idx = abs(crc32($name)) % count($colors);
        $bg = $colors[$idx];
        return "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='{$bg}'/><text x='50%' y='54%' font-family='sans-serif' font-weight='900' font-size='42' fill='%230f172a' text-anchor='middle' dominant-baseline='middle'>{$initial}</text></svg>";
    }
    function format_inr($amount) {
            return '₹' . number_format((float)$amount, 2);
        }
    }

    if (!function_exists('is_logged_in')) {
        function is_logged_in() {
            return isset($_SESSION['user_id']);
        }
    }

    if (!function_exists('check_user_auth')) {
        function check_user_auth() {
            if (!isset($_SESSION['user_id'])) {
                safe_redirect("login.php");
                exit;
            }
        }
    }
    if (!function_exists('check_user_login')) { function check_user_login() { check_user_auth(); } }
    if (!function_exists('check_auth')) { function check_auth() { check_user_auth(); } }

    if (!function_exists('check_admin_auth')) {
        function check_admin_auth() {
            $is_adm = (isset($_SESSION['admin_id']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'));
            if (!$is_adm) {
                safe_redirect("/admin/login.php");
                exit;
            }
        }
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
    // ✅ TiDB CLOUD LIVE DATABASE CONNECTION (LOW LATENCY + SSL)
    // =========================================================================
    $db_host = "gateway01.ap-southeast-1.prod.aws.tidbcloud.com";
    $db_user = "33NZV583e1gPsMh.root";
    $db_pass = "mcj5pocb1FlVZD68";
    $db_name = "test";
    $db_port = 4000;

    $conn = mysqli_init();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    $conn->options(MYSQLI_OPT_READ_TIMEOUT, 5);
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);

    @$conn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port, NULL, MYSQLI_CLIENT_SSL);

    if ($conn->connect_error) {
        $script_name = basename($_SERVER['PHP_SELF'] ?? '');
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
    if (!isset($_SESSION['user_id']) && !empty($_COOKIE['adept_remember']) && $conn && !$conn->connect_error) {
        $rem_token = trim($_COOKIE['adept_remember']);
        if (preg_match('/^[a-f0-9]{32,128}$/i', $rem_token)) {
            $stmt = $conn->prepare("SELECT id, username, email, phone, role, status FROM users WHERE remember_token = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $rem_token);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($u = $res->fetch_assoc()) {
                    if (($u['role'] ?? '') === 'admin' || ($u['status'] ?? 'active') !== 'blocked') {
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

    // Auto-heal admin user status if logged in
    if (!empty($_SESSION['admin_id']) || (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin')) {
        $adm_uid = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);
        if ($adm_uid > 0 && $conn && !$conn->connect_error) {
            @$conn->query("UPDATE users SET status = 'active' WHERE id = {$adm_uid} AND role = 'admin'");
        }
    }

    // =========================================================================
    // ✅ CASHFREE LIVE PRODUCTION CREDENTIALS
    // =========================================================================
    if (!defined('CASHFREE_APP_ID')) define('CASHFREE_APP_ID', '1494190b594e98ed762a2a943320914941');
    if (!defined('CASHFREE_SECRET_KEY')) define('CASHFREE_SECRET_KEY', 'cfsk_ma_prod_979295dd86c34ea7eef3d56c1e07d25e_27097462');
    if (!defined('CASHFREE_ENVIRONMENT')) define('CASHFREE_ENVIRONMENT', 'PROD');

    if (!function_exists('get_cashfree_app_id')) { function get_cashfree_app_id() { return CASHFREE_APP_ID; } }
    if (!function_exists('get_cashfree_secret_key')) { function get_cashfree_secret_key() { return CASHFREE_SECRET_KEY; } }
    if (!function_exists('get_cashfree_env')) { function get_cashfree_env() { return 'PROD'; } }

    // Issue 1-Year Persistent Remember Token
    if (!function_exists('set_user_remember_token')) {
        function set_user_remember_token($user_id) {
            global $conn;
            $user_id = (int)$user_id;
            $token = bin2hex(random_bytes(32));
            if ($conn && !$conn->connect_error) {
                $stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $token, $user_id);
                    $stmt->execute();
                    $stmt->close();
                }
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
    }

    // Clear Persistent Remember Token (On explicit Logout)
    if (!function_exists('clear_user_remember_token')) {
        function clear_user_remember_token($user_id = null) {
            global $conn;
            if ($user_id && $conn && !$conn->connect_error) {
                $uid = (int)$user_id;
                @$conn->query("UPDATE users SET remember_token = NULL WHERE id = {$uid}");
            }
            setcookie('adept_remember', '', time() - 86400, '/');
            unset($_COOKIE['adept_remember']);
        }
    }

    if (!function_exists('get_setting')) {
        function get_setting($key, $default = '') {
            global $conn;
            if (!$conn || $conn->connect_error) return $default;
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
}
?>
