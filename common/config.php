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
        ['BEASTZ_GAMING_AVATARS'] = [
    [
        'label' => 'Ninja',
        'svg' => "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g0' x1='0' y1='0' x2='1' y2='1'><stop offset='0%' stop-color='%230f172a'/><stop offset='100%' stop-color='%231e293b'/></linearGradient></defs><circle cx='50' cy='50' r='50' fill='url(%23g0)'/><path d='M20 50 C20 30 35 22 50 22 C65 22 80 30 80 50 C80 72 68 82 50 82 C32 82 20 72 20 50 Z' fill='%231e293b' stroke='%23facc15' stroke-width='3'/><path d='M24 38 Q50 32 76 38 L74 46 Q50 42 26 46 Z' fill='%23facc15'/><polygon points='50,33 54,41 46,41' fill='%230f172a'/><ellipse cx='38' cy='52' rx='6' ry='2.5' fill='%23facc15'/><ellipse cx='62' cy='52' rx='6' ry='2.5' fill='%23facc15'/><path d='M30 62 L50 74 L70 62 L66 80 L34 80 Z' fill='%23090d16' stroke='%23facc15' stroke-width='2'/></svg>"
    ],
    [
        'label' => 'Samurai',
        'svg' => "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g1' x1='0' y1='0' x2='0' y2='1'><stop offset='0%' stop-color='%23881337'/><stop offset='100%' stop-color='%230f172a'/></linearGradient></defs><circle cx='50' cy='50' r='50' fill='url(%23g1)'/><path d='M18 25 Q38 42 50 28 Q62 42 82 25 Q70 52 50 44 Q30 52 18 25 Z' fill='%23facc15'/><circle cx='50' cy='36' r='5' fill='%23e11d48' stroke='%23facc15' stroke-width='1.5'/><path d='M28 44 C28 35 72 35 72 44 L70 68 C70 76 50 84 50 84 C50 84 30 76 30 68 Z' fill='%231e293b' stroke='%23facc15' stroke-width='2.5'/><polygon points='34,54 44,54 41,58 36,58' fill='%23facc15'/><polygon points='56,54 66,54 64,58 59,58' fill='%23facc15'/><path d='M36 66 Q50 72 64 66 L60 76 Q50 80 40 76 Z' fill='%23e11d48'/></svg>"
    ],
    [
        'label' => 'Headhunter',
        'svg' => "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g2' x1='0' y1='0' x2='1' y2='1'><stop offset='0%' stop-color='%230369a1'/><stop offset='100%' stop-color='%230f172a'/></linearGradient></defs><circle cx='50' cy='50' r='50' fill='url(%23g2)'/><path d='M28 48 C28 32 72 32 72 48 L70 72 C70 80 50 82 50 82 C50 82 30 80 30 72 Z' fill='%231e293b' stroke='%2338bdf8' stroke-width='2.5'/><path d='M32 50 Q50 46 68 50 L66 62 Q50 58 34 62 Z' fill='%23facc15'/><path d='M22 36 C22 20 78 20 78 36' fill='none' stroke='%23facc15' stroke-width='5' stroke-linecap='round'/><rect x='16' y='42' width='10' height='22' rx='4' fill='%23facc15'/><rect x='74' y='42' width='10' height='22' rx='4' fill='%23facc15'/><path d='M76 60 Q70 78 54 78' fill='none' stroke='%23facc15' stroke-width='2.5'/><circle cx='52' cy='78' r='3' fill='%2338bdf8'/></svg>"
    ],
    [
        'label' => 'Ghost',
        'svg' => "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g3' x1='0' y1='0' x2='0' y2='1'><stop offset='0%' stop-color='%23581c87'/><stop offset='100%' stop-color='%23090d16'/></linearGradient></defs><circle cx='50' cy='50' r='50' fill='url(%23g3)'/><path d='M50 18 C30 18 22 34 22 56 C22 74 34 84 50 84 C66 84 78 74 78 56 C78 34 70 18 50 18 Z' fill='%231e1b4b' stroke='%23a855f7' stroke-width='3'/><path d='M30 40 C34 58 42 66 50 66 C58 66 66 58 70 40 C64 36 56 34 50 34 C44 34 36 36 30 40 Z' fill='%23090d16'/><ellipse cx='40' cy='48' rx='5' ry='3' fill='%23c084fc'/><ellipse cx='60' cy='48' rx='5' ry='3' fill='%23c084fc'/><path d='M42 62 Q50 68 58 62' fill='none' stroke='%23c084fc' stroke-width='2'/></svg>"
    ],
    [
        'label' => 'Predator',
        'svg' => "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g4' x1='0' y1='0' x2='1' y2='1'><stop offset='0%' stop-color='%2314532d'/><stop offset='100%' stop-color='%23052e16'/></linearGradient></defs><circle cx='50' cy='50' r='50' fill='url(%23g4)'/><polygon points='20,28 32,46 22,50' fill='%2322c55e'/><polygon points='80,28 68,46 78,50' fill='%2322c55e'/><path d='M24 45 C24 35 76 35 76 45 L70 76 L50 85 L30 76 Z' fill='%2314532d' stroke='%234ade80' stroke-width='3'/><polygon points='32,54 44,52 38,60' fill='%23facc15'/><polygon points='68,54 56,52 62,60' fill='%23facc15'/><polygon points='50,60 44,68 56,68' fill='%23052e16'/><polygon points='40,74 44,80 47,74' fill='%23facc15'/><polygon points='60,74 56,80 53,74' fill='%23facc15'/></svg>"
    ],
    [
        'label' => 'Cyber Skull',
        'svg' => "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g5' x1='0' y1='0' x2='0' y2='1'><stop offset='0%' stop-color='%237c2d12'/><stop offset='100%' stop-color='%2318181b'/></linearGradient></defs><circle cx='50' cy='50' r='50' fill='url(%23g5)'/><path d='M26 44 C26 26 74 26 74 44 C74 56 68 64 64 68 L64 78 L36 78 L36 68 C32 64 26 56 26 44 Z' fill='%2327272a' stroke='%23fb923c' stroke-width='3'/><ellipse cx='38' cy='46' rx='6' ry='8' fill='%23fb923c'/><ellipse cx='62' cy='46' rx='6' ry='8' fill='%23fb923c'/><polygon points='50,56 46,64 54,64' fill='%23fb923c'/><line x1='42' y1='72' x2='42' y2='78' stroke='%23fb923c' stroke-width='2'/><line x1='50' y1='72' x2='50' y2='78' stroke='%23fb923c' stroke-width='2'/><line x1='58' y1='72' x2='58' y2='78' stroke='%23fb923c' stroke-width='2'/></svg>"
    ],
    [
        'label' => 'Champion',
        'svg' => "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g6' x1='0' y1='0' x2='1' y2='1'><stop offset='0%' stop-color='%23713f12'/><stop offset='100%' stop-color='%230f172a'/></linearGradient></defs><circle cx='50' cy='50' r='50' fill='url(%23g6)'/><path d='M20 54 L30 32 L42 46 L50 24 L58 46 L70 32 L80 54 L76 74 Q50 82 24 74 Z' fill='%23eab308' stroke='%23fef08a' stroke-width='2.5'/><circle cx='50' cy='24' r='4' fill='%23fef08a'/><circle cx='30' cy='32' r='3.5' fill='%23fef08a'/><circle cx='70' cy='32' r='3.5' fill='%23fef08a'/><polygon points='50,52 54,60 63,60 56,65 59,73 50,68 41,73 44,65 37,60 46,60' fill='%230f172a'/></svg>"
    ],
    [
        'label' => 'Valkyrie',
        'svg' => "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g7' x1='0' y1='0' x2='0' y2='1'><stop offset='0%' stop-color='%23831843'/><stop offset='100%' stop-color='%230f172a'/></linearGradient></defs><circle cx='50' cy='50' r='50' fill='url(%23g7)'/><polygon points='14,40 32,46 24,56' fill='%23ec4899'/><polygon points='86,40 68,46 76,56' fill='%23ec4899'/><path d='M28 42 C28 30 72 30 72 42 L68 70 L50 82 L32 70 Z' fill='%231e293b' stroke='%23f472b6' stroke-width='2.5'/><polygon points='34,48 50,42 66,48 64,56 50,52 36,56' fill='%23facc15'/><polygon points='44,62 50,58 56,62 50,72' fill='%23f472b6'/></svg>"
    ]
];

function get_avatar_url(,  = "Gamer") {
    if (!empty() && (str_starts_with(, "http") || str_starts_with(, "data:image"))) {
        return ;
    }
    global ;
     = abs(crc32( ?: 'Gamer')) % count();
    return []['svg'];
}

    if (!function_exists('format_compact_inr')) {
        function format_compact_inr($amount) {
            $amount = (float)$amount;
            if ($amount < 1000) {
                return '₹' . number_format($amount, ($amount == floor($amount)) ? 0 : 2);
            } elseif ($amount < 1000000) {
                $k = $amount / 1000;
                $rounded = round($k, 1);
                if ($rounded == floor($rounded)) {
                    return '₹' . (int)$rounded . 'k';
                } else {
                    return '₹' . number_format($rounded, 1) . 'k';
                }
            } else {
                $m = $amount / 1000000;
                $rounded = round($m, 1);
                if ($rounded == floor($rounded)) {
                    return '₹' . (int)$rounded . 'M';
                } else {
                    return '₹' . number_format($rounded, 1) . 'M';
                }
            }
        }
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
        @$conn->query("CREATE TABLE IF NOT EXISTS app_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            target_url VARCHAR(255) DEFAULT '/?tab=home',
            badge_type VARCHAR(50) DEFAULT 'ANNOUNCEMENT',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
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
