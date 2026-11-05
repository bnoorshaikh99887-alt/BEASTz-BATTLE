<?php
// Prevent multiple inclusions
if (!defined('ADEPT_PLAY_CONFIG')) {
    define('ADEPT_PLAY_CONFIG', true);
    require_once __DIR__ . '/env.php';
    require_once __DIR__ . '/Secrets.php';

    // Suppress deprecated warnings (PHP 8.5 compatibility)
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');

    // Start Output Buffering for safe header redirects
    if (ob_get_level() === 0) {
        ob_start();
    }
    // Prevent stale caching in Android WebView and mobile browsers
    if (!headers_sent()) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }

    // Helper functions (defined early so they are always available)
    if (!function_exists("str_contains")) {
        function str_contains($haystack, $needle) {
            return $needle !== "" && mb_strpos($haystack, $needle) !== false;
        }
    }
    if (!function_exists("str_starts_with")) {
        function str_starts_with($haystack, $needle) {
            return (string)$needle !== "" && strncmp($haystack, $needle, strlen($needle)) === 0;
        }
    }
    if (!function_exists("str_ends_with")) {
        function str_ends_with($haystack, $needle) {
            return $needle === "" || $needle === substr($haystack, -strlen($needle));
        }
    }
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

    // FIXED SYNTAX ERROR: Added global variable declaration correctly
    global $BEASTZ_GAMING_AVATARS;
    $BEASTZ_GAMING_AVATARS = [
        [
            'label' => 'Ninja',
            'svg' => "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g0' x1='0' y1='0' x2='1' y2='1'><stop offset='0%' stop-color='%230f172a'/><stop offset='100%' stop-color='%231e293b'/></linearGradient></defs><circle cx='50' cy='50' r='50' fill='url(%23g0)'/><path d='M20 50 C20 30 35 22 50 22 C65 22 80 30 80 50 C80 72 68 82 50 82 C32 82 20 72 20 50 Z' fill='%231e293b' stroke='%2338bdf8' stroke-width='3'/><path d='M24 38 Q50 32 76 38 L74 46 Q50 42 26 46 Z' fill='%2338bdf8'/><polygon points='50,33 54,41 46,41' fill='%230f172a'/><ellipse cx='38' cy='52' rx='6' ry='2.5' fill='%2338bdf8'/><ellipse cx='62' cy='52' rx='6' ry='2.5' fill='%2338bdf8'/><path d='M30 62 L50 74 L70 62 L66 80 L34 80 Z' fill='%23090d16' stroke='%2338bdf8' stroke-width='2'/></svg>"
        ],
        [
            'label' => 'Samurai',
            'svg' => "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g1' x1='0' y1='0' x2='0' y2='1'><stop offset='0%' stop-color='%23881337'/><stop offset='100%' stop-color='%230f172a'/></linearGradient></defs><circle cx='50' cy='50' r='50' fill='url(%23g1)'/><path d='M18 25 Q38 42 50 28 Q62 42 82 25 Q70 52 50 44 Q30 52 18 25 Z' fill='%2338bdf8'/><circle cx='50' cy='36' r='5' fill='%23e11d48' stroke='%2338bdf8' stroke-width='1.5'/><path d='M28 44 C28 35 72 35 72 44 L70 68 C70 76 50 84 50 84 C50 84 30 76 30 68 Z' fill='%231e293b' stroke='%2338bdf8' stroke-width='2.5'/><polygon points='34,54 44,54 41,58 36,58' fill='%2338bdf8'/><polygon points='56,54 66,54 64,58 59,58' fill='%2338bdf8'/><path d='M36 66 Q50 72 64 66 L60 76 Q50 80 40 76 Z' fill='%23e11d48'/></svg>"
        ],
        [
            'label' => 'Headhunter',
            'svg' => "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g2' x1='0' y1='0' x2='1' y2='1'><stop offset='0%' stop-color='%230369a1'/><stop offset='100%' stop-color='%230f172a'/></linearGradient></defs><circle cx='50' cy='50' r='50' fill='url(%23g2)'/><path d='M28 48 C28 32 72 32 72 48 L70 72 C70 80 50 82 50 82 C50 82 30 80 30 72 Z' fill='%231e293b' stroke='%2338bdf8' stroke-width='2.5'/><path d='M32 50 Q50 46 68 50 L66 62 Q50 58 34 62 Z' fill='%2338bdf8'/><path d='M22 36 C22 20 78 20 78 36' fill='none' stroke='%2338bdf8' stroke-width='5' stroke-linecap='round'/><rect x='16' y='42' width='10' height='22' rx='4' fill='%2338bdf8'/><rect x='74' y='42' width='10' height='22' rx='4' fill='%2338bdf8'/><path d='M76 60 Q70 78 54 78' fill='none' stroke='%2338bdf8' stroke-width='2.5'/><circle cx='52' cy='78' r='3' fill='%2338bdf8'/></svg>"
        ],
        [
            'label' => 'Ghost',
            'svg' => "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g3' x1='0' y1='0' x2='0' y2='1'><stop offset='0%' stop-color='%23581c87'/><stop offset='100%' stop-color='%23090d16'/></linearGradient></defs><circle cx='50' cy='50' r='50' fill='url(%23g3)'/><path d='M50 18 C30 18 22 34 22 56 C22 74 34 84 50 84 C66 84 78 74 78 56 C78 34 70 18 50 18 Z' fill='%231e1b4b' stroke='%23a855f7' stroke-width='3'/><path d='M30 40 C34 58 42 66 50 66 C58 66 66 58 70 40 C64 36 56 34 50 34 C44 34 36 36 30 40 Z' fill='%23090d16'/><ellipse cx='40' cy='48' rx='5' ry='3' fill='%23c084fc'/><ellipse cx='60' cy='48' rx='5' ry='3' fill='%23c084fc'/><path d='M42 62 Q50 68 58 62' fill='none' stroke='%23c084fc' stroke-width='2'/></svg>"
        ],
        [
            'label' => 'Predator',
            'svg' => "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g4' x1='0' y1='0' x2='1' y2='1'><stop offset='0%' stop-color='%2314532d'/><stop offset='100%' stop-color='%23052e16'/></linearGradient></defs><circle cx='50' cy='50' r='50' fill='url(%23g4)'/><polygon points='20,28 32,46 22,50' fill='%2322c55e'/><polygon points='80,28 68,46 78,50' fill='%2322c55e'/><path d='M24 45 C24 35 76 35 76 45 L70 76 L50 85 L30 76 Z' fill='%2314532d' stroke='%234ade80' stroke-width='3'/><polygon points='32,54 44,52 38,60' fill='%2338bdf8'/><polygon points='68,54 56,52 62,60' fill='%2338bdf8'/><polygon points='50,60 44,68 56,68' fill='%23052e16'/><polygon points='40,74 44,80 47,74' fill='%2338bdf8'/><polygon points='60,74 56,80 53,74' fill='%2338bdf8'/></svg>"
        ],
        [
            'label' => 'Cyber Skull',
            'svg' => "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g5' x1='0' y1='0' x2='0' y2='1'><stop offset='0%' stop-color='%237c2d12'/><stop offset='100%' stop-color='%2318181b'/></linearGradient></defs><circle cx='50' cy='50' r='50' fill='url(%23g5)'/><path d='M26 44 C26 26 74 26 74 44 C74 56 68 64 64 68 L64 78 L36 78 L36 68 C32 64 26 56 26 44 Z' fill='%2327272a' stroke='%23fb923c' stroke-width='3'/><ellipse cx='38' cy='46' rx='6' ry='8' fill='%23fb923c'/><ellipse cx='62' cy='46' rx='6' ry='8' fill='%23fb923c'/><polygon points='50,56 46,64 54,64' fill='%23fb923c'/><line x1='42' y1='72' x2='42' y2='78' stroke='%23fb923c' stroke-width='2'/><line x1='50' y1='72' x2='50' y2='78' stroke='%23fb923c' stroke-width='2'/><line x1='58' y1='72' x2='58' y2='78' stroke='%23fb923c' stroke-width='2'/></svg>"
        ],
        [
            'label' => 'Champion',
            'svg' => "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g6' x1='0' y1='0' x2='1' y2='1'><stop offset='0%' stop-color='%23713f12'/><stop offset='100%' stop-color='%230f172a'/></linearGradient></defs><circle cx='50' cy='50' r='50' fill='url(%23g6)'/><path d='M20 54 L30 32 L42 46 L50 24 L58 46 L70 32 L80 54 L76 74 Q50 82 24 74 Z' fill='%232563eb' stroke='%2393c5fd' stroke-width='2.5'/><circle cx='50' cy='24' r='4' fill='%2393c5fd'/><circle cx='30' cy='32' r='3.5' fill='%2393c5fd'/><circle cx='70' cy='32' r='3.5' fill='%2393c5fd'/><polygon points='50,52 54,60 63,60 56,65 59,73 50,68 41,73 44,65 37,60 46,60' fill='%230f172a'/></svg>"
        ],
        [
            'label' => 'Valkyrie',
            'svg' => "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g7' x1='0' y1='0' x2='0' y2='1'><stop offset='0%' stop-color='%23831843'/><stop offset='100%' stop-color='%230f172a'/></linearGradient></defs><circle cx='50' cy='50' r='50' fill='url(%23g7)'/><polygon points='14,40 32,46 24,56' fill='%23ec4899'/><polygon points='86,40 68,46 76,56' fill='%23ec4899'/><path d='M28 42 C28 30 72 30 72 42 L68 70 L50 82 L32 70 Z' fill='%231e293b' stroke='%23f472b6' stroke-width='2.5'/><polygon points='34,48 50,42 66,48 64,56 50,52 36,56' fill='%2338bdf8'/><polygon points='44,62 50,58 56,62 50,72' fill='%23f472b6'/></svg>"
        ]
    ];

    if (!function_exists('get_avatar_url')) {
        function get_avatar_url($avatar_str = '', $username = 'Gamer') {
            if (!empty($avatar_str) && (str_starts_with($avatar_str, 'http') || str_starts_with($avatar_str, 'data:image'))) {
                return $avatar_str;
            }
            global $BEASTZ_GAMING_AVATARS;
            if (empty($BEASTZ_GAMING_AVATARS)) {
                return 'https://api.dicebear.com/7.x/bottts/svg?seed=' . urlencode($username ?: 'Gamer');
            }
            $idx = abs(crc32($username ?: 'Gamer')) % count($BEASTZ_GAMING_AVATARS);
            return $BEASTZ_GAMING_AVATARS[$idx]['svg'];
        }
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

    if (!function_exists('format_inr')) {
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
            if (empty($_SESSION['user_id'])) {
                if (!empty($_COOKIE['adept_remember']) && function_exists('beastz_try_restore_session')) {
                    beastz_try_restore_session();
                }
            }
            if (empty($_SESSION['user_id'])) {
                $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                           (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) ||
                           (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));
                if ($is_ajax) {
                    header('Content-Type: application/json; charset=utf-8');
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Session expired. Please log in again.', 'auth_required' => true]);
                    exit;
                }
                safe_redirect("/login.php");
                exit;
            }
        }
    }
    if (!function_exists('check_user_login')) { function check_user_login() { check_user_auth(); } }
    if (!function_exists('check_auth')) { function check_auth() { check_user_auth(); } }

    if (!function_exists('check_admin_auth')) {
        function check_admin_auth() {
            $is_adm = (!empty($_SESSION['admin_id']) || ((isset($_SESSION['role']) && $_SESSION['role'] === 'admin')));
            if (!$is_adm) {
                if (!empty($_COOKIE['adept_remember']) && function_exists('beastz_try_restore_session')) {
                    beastz_try_restore_session();
                    $is_adm = (!empty($_SESSION['admin_id']) || ((isset($_SESSION['role']) && $_SESSION['role'] === 'admin')));
                }
            }
            if (!$is_adm) {
                $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                           (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
                if ($is_ajax) {
                    header('Content-Type: application/json; charset=utf-8');
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Admin session required.', 'auth_required' => true]);
                    exit;
                }
                safe_redirect("/login.php");
                exit;
            }
        }
    }

    // Set 1-Year Persistent Cookie Lifetime & Bulletproof Session
    $one_year = 365 * 24 * 3600;
    ini_set('session.cookie_lifetime', (string)$one_year);
    ini_set('session.gc_maxlifetime', (string)$one_year);
    ini_set('session.use_strict_mode', '0'); // Allow session recovery across requests

    if (session_status() === PHP_SESSION_NONE) {
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                    (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
                    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
                    (!empty($_SERVER['HTTP_CF_VISITOR']) && str_contains($_SERVER['HTTP_CF_VISITOR'], 'https'));

        session_set_cookie_params([
            'lifetime' => $one_year,
            'path' => '/',
            'domain' => '',
            'secure' => $is_https,
            'httponly' => true,
            'samesite' => $is_https ? 'None' : 'Lax'
        ]);
        @session_start();
    }

    // =========================================================================
    // ✅ TiDB CLOUD LIVE DATABASE CONNECTION (LOW LATENCY + SSL)
    // =========================================================================
    $db_cfg = Secrets::db();
    $db_host = $db_cfg['host'];
    $db_user = $db_cfg['user'];
    $db_pass = $db_cfg['pass'];
    $db_name = $db_cfg['name'];
    $db_port = $db_cfg['port'];

    $conn = mysqli_init();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    $conn->options(MYSQLI_OPT_READ_TIMEOUT, 8);
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);

    @$conn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port, NULL, MYSQLI_CLIENT_SSL);

    if ($conn->connect_error) {
        // Never bounce the app to install.php. A slow TiDB handshake used to
        // redirect forever (PHP_SELF stays api/index.php) and the APK looked frozen.
        $req_uri = $_SERVER['REQUEST_URI'] ?? '';
        $is_install = str_contains($req_uri, 'install.php');
        $is_api = (bool)preg_match('#/(api/|live_sync|get_notifications|admin_poll|request_withdrawal|update_profile|send_notification|save_player_id|payment_verify|cashfree_)#', $req_uri);
        if (!$is_install) {
            if ($is_api) {
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                    http_response_code(503);
                }
                echo json_encode(['success' => false, 'error' => 'Database connection offline']);
                exit;
            }
            if (!headers_sent()) {
                http_response_code(503);
                header('Content-Type: text/html; charset=utf-8');
                header('Retry-After: 3');
            }
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>BEASTz BATTLE</title></head><body style="margin:0;background:#080a0f;color:#fff;font-family:sans-serif;text-align:center;padding:72px 22px">';
            echo '<h2 style="color:#38bdf8;margin:0 0 8px">Arena is reconnecting</h2>';
            echo '<p style="color:#94a3b8;font-size:14px;line-height:1.5">Database did not answer in time. Your match and wallet are safe.</p>';
            echo '<button onclick="location.replace(location.pathname+location.search)" style="margin-top:18px;background:#2563eb;color:#ffffff;border:none;padding:12px 28px;border-radius:12px;font-weight:900">TRY AGAIN</button>';
            echo '<script>setTimeout(function(){location.replace(location.pathname+location.search);},4000);</script>';
            echo '</body></html>';
            exit;
        }
    } else {
        $conn->set_charset("utf8mb4");
    // Auto-ensure remember_token columns exist in TiDB schema
    @$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS remember_token VARCHAR(128) DEFAULT NULL");
    @$conn->query("ALTER TABLE admin ADD COLUMN IF NOT EXISTS remember_token VARCHAR(128) DEFAULT NULL");

    if (!function_exists('beastz_try_restore_session')) {
        function beastz_try_restore_session() {
            global $conn;
            if (!empty($_SESSION['user_id']) || !empty($_SESSION['admin_id'])) {
                return true;
            }
            if (empty($_COOKIE['adept_remember']) || !$conn || $conn->connect_error) {
                return false;
            }
            $rem_token = trim($_COOKIE['adept_remember']);
            if (!preg_match('/^[a-f0-9]{32,128}$/i', $rem_token)) {
                return false;
            }

            $pref_panel = $_COOKIE['beastz_active_panel'] ?? $_COOKIE['active_panel'] ?? '';
            $is_restored = false;

            // 1. Check admin table first
            if ($pref_panel === 'admin' || empty($pref_panel)) {
                $a_stmt = $conn->prepare("SELECT id, username, role, status FROM admin WHERE remember_token = ? LIMIT 1");
                if ($a_stmt) {
                    $a_stmt->bind_param("s", $rem_token);
                    $a_stmt->execute();
                    $a_res = $a_stmt->get_result();
                    if ($adm = $a_res->fetch_assoc()) {
                        if (($adm['status'] ?? 'active') === 'active') {
                            $aid = (int)$adm['id'];
                            $is_super = ($adm['username'] === 'anonymous.349' || $adm['username'] === 'admin' || ($adm['role'] ?? '') === 'super_admin');
                            $_SESSION['admin_id'] = $aid;
                            $_SESSION['admin_user'] = $adm['username'];
                            $_SESSION['admin_username'] = $adm['username'];
                            $_SESSION['admin_role'] = $adm['role'] ?? ($is_super ? 'super_admin' : 'worker');
                            $_SESSION['is_super_admin'] = $is_super;
                            $_SESSION['admin_logged_in'] = true;
                            if (!$is_super || $pref_panel === 'worker') {
                                $_SESSION['worker_id'] = $aid;
                                $_SESSION['is_worker'] = true;
                                $_SESSION['active_panel'] = 'worker';
                            } else {
                                $_SESSION['active_panel'] = 'admin';
                            }
                            $is_restored = true;
                        }
                    }
                    $a_stmt->close();
                }
            }

            // 2. Check users table
            if (!$is_restored) {
                $stmt = $conn->prepare("SELECT id, username, email, phone, role, status FROM users WHERE remember_token = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("s", $rem_token);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($u = $res->fetch_assoc()) {
                        if (($u['role'] ?? '') === 'admin') {
                            $aid = (int)$u['id'];
                            $_SESSION['admin_id'] = $aid;
                            $_SESSION['admin_user'] = $u['username'];
                            $_SESSION['admin_username'] = $u['username'];
                            $_SESSION['user_id'] = $aid;
                            $_SESSION['username'] = $u['username'];
                            $_SESSION['role'] = 'admin';
                            $_SESSION['is_super_admin'] = ($u['username'] === 'anonymous.349' || $u['username'] === 'admin');
                            $_SESSION['admin_logged_in'] = true;
                            $_SESSION['active_panel'] = 'admin';
                            $is_restored = true;
                        } elseif (($u['role'] ?? '') === 'worker' || ($u['role'] ?? '') === 'host') {
                            $wid = (int)$u['id'];
                            $_SESSION['worker_id'] = $wid;
                            $_SESSION['user_id'] = $wid;
                            $_SESSION['username'] = $u['username'];
                            $_SESSION['role'] = 'worker';
                            $_SESSION['is_worker'] = true;
                            $_SESSION['active_panel'] = 'worker';
                            $is_restored = true;
                        } elseif (($u['status'] ?? 'active') !== 'blocked') {
                            $_SESSION['user_id'] = (int)$u['id'];
                            $_SESSION['username'] = $u['username'];
                            $_SESSION['email'] = $u['email'];
                            $_SESSION['role'] = $u['role'] ?? 'user';
                            $_SESSION['user_logged_in'] = true;
                            $_SESSION['active_panel'] = 'user';
                            $is_restored = true;
                        }
                    }
                    $stmt->close();
                }
            }
            return $is_restored;
        }
    }

    }

    // =========================================================================
    // ✅ 1-YEAR PERSISTENT LOGIN AUTO-RESTORE FOR BOTH ADMIN & USER ROLES
    // =========================================================================
    $beastz_session_empty = empty($_SESSION['user_id']) && empty($_SESSION['admin_id']) && empty($_SESSION['worker_id']);
    if ($beastz_session_empty && !empty($_COOKIE['adept_remember']) && function_exists('beastz_try_restore_session')) {
        beastz_try_restore_session();
    }
    if (false) {
        $rem_token = trim($_COOKIE['adept_remember']);
        if (preg_match('/^[a-f0-9]{32,128}$/i', $rem_token)) {
            $pref_panel = $_COOKIE['beastz_active_panel'] ?? $_COOKIE['active_panel'] ?? '';
            $is_restored = false;

            // 1. Check admin table first if pref is admin or empty
            if ($pref_panel === 'admin' || empty($pref_panel)) {
                $a_stmt = $conn->prepare("SELECT id, username, role, status FROM admin WHERE remember_token = ? LIMIT 1");
                if ($a_stmt) {
                    $a_stmt->bind_param("s", $rem_token);
                    $a_stmt->execute();
                    $a_res = $a_stmt->get_result();
                    if ($adm = $a_res->fetch_assoc()) {
                        if (($adm['status'] ?? 'active') === 'active') {
                            $aid = (int)$adm['id'];
                            $is_super = ($adm['username'] === 'anonymous.349' || $adm['username'] === 'admin' || ($adm['role'] ?? '') === 'super_admin');
                            $_SESSION['admin_id'] = $aid;
                            $_SESSION['admin_user'] = $adm['username'];
                            $_SESSION['admin_username'] = $adm['username'];
                            $_SESSION['admin_role'] = $adm['role'] ?? ($is_super ? 'super_admin' : 'worker');
                            $_SESSION['is_super_admin'] = $is_super;
                            $_SESSION['admin_logged_in'] = true;
                            if (!$is_super || $pref_panel === 'worker') {
                                $_SESSION['worker_id'] = $aid;
                                $_SESSION['is_worker'] = true;
                                $_SESSION['active_panel'] = 'worker';
                            } else {
                                $_SESSION['active_panel'] = 'admin';
                            }
                            $is_restored = true;
                        }
                    }
                    $a_stmt->close();
                }
            }

            // 2. Check users table
            if (!$is_restored) {
                $stmt = $conn->prepare("SELECT id, username, email, phone, role, status FROM users WHERE remember_token = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("s", $rem_token);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($u = $res->fetch_assoc()) {
                        if (($u['role'] ?? '') === 'admin') {
                            $aid = (int)$u['id'];
                            $_SESSION['admin_id'] = $aid;
                            $_SESSION['admin_user'] = $u['username'];
                            $_SESSION['admin_username'] = $u['username'];
                            $_SESSION['user_id'] = $aid;
                            $_SESSION['username'] = $u['username'];
                            $_SESSION['role'] = 'admin';
                            $_SESSION['admin_role'] = ($u['username'] === 'anonymous.349') ? 'super_admin' : 'host_admin';
                            $_SESSION['is_super_admin'] = ($u['username'] === 'anonymous.349' || $u['username'] === 'admin');
                            $_SESSION['admin_logged_in'] = true;
                            if ($pref_panel === 'user' && isset($_GET['view']) && $_GET['view'] === 'player') {
                                $_SESSION['active_panel'] = 'user';
                            } else {
                                $_SESSION['active_panel'] = 'admin';
                            }
                            $is_restored = true;
                        } elseif (($u['status'] ?? 'active') !== 'blocked') {
                            $_SESSION['user_id'] = (int)$u['id'];
                            $_SESSION['username'] = $u['username'];
                            $_SESSION['email'] = $u['email'];
                            $_SESSION['role'] = $u['role'] ?? 'user';
                            $_SESSION['user_logged_in'] = true;
                            $_SESSION['active_panel'] = 'user';
                            $is_restored = true;
                        }
                    }
                    $stmt->close();
                }
            }
        }
    }
    // =========================================================================
    // ✅ AUTO-SYNC ONESIGNAL DEVICE TOKEN (player_id) & USER_DEVICES
    // =========================================================================
    $sync_pid = trim($_COOKIE["beastz_player_id"] ?? $_REQUEST["player_id"] ?? "");
    $beastz_uri_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $beastz_is_light = (bool)preg_match('#/(live_sync|get_notifications|admin_poll)(\.php)?$#', $beastz_uri_path);
    // Polling endpoints must not write the device row every 5s (that locked TiDB and froze taps).
    if (!$beastz_is_light && !empty($sync_pid) && strlen($sync_pid) > 15 && $conn && !$conn->connect_error) {
        $pid_hash = hash('sha256', $sync_pid);
        $already_synced = (string)($_COOKIE['beastz_pid_synced'] ?? '');
        if ($already_synced !== $pid_hash) {
            $logged_uid = (int)($_SESSION["user_id"] ?? 0);
            if ($logged_uid > 0) {
                @$conn->query("INSERT IGNORE INTO user_devices (user_id, player_id) VALUES ({$logged_uid}, '" . $conn->real_escape_string($sync_pid) . "')");
                @$conn->query("UPDATE users SET player_id = '" . $conn->real_escape_string($sync_pid) . "' WHERE id = {$logged_uid}");
            }
            $logged_aid = (int)($_SESSION["admin_id"] ?? $_SESSION["worker_id"] ?? 0);
            if ($logged_aid > 0) {
                @$conn->query("UPDATE admin SET player_id = '" . $conn->real_escape_string($sync_pid) . "' WHERE id = {$logged_aid}");
            }
            $is_https_pid = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            setcookie('beastz_pid_synced', $pid_hash, [
                'expires' => time() + (6 * 3600),
                'path' => '/',
                'secure' => $is_https_pid,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    }

    // =========================================================================
    // ✅ CASHFREE LIVE PRODUCTION CREDENTIALS
    // =========================================================================
    if (!defined('CASHFREE_APP_ID')) define('CASHFREE_APP_ID', env('CASHFREE_APP_ID', '1494190b594e98ed762a2a943320914941'));
    if (!defined('CASHFREE_SECRET_KEY')) define('CASHFREE_SECRET_KEY', env('CASHFREE_SECRET_KEY', 'cfsk_ma_prod_979295dd86c34ea7eef3d56c1e07d25e_27097462'));
    if (!defined('CASHFREE_ENVIRONMENT')) define('CASHFREE_ENVIRONMENT', env('CASHFREE_ENVIRONMENT', 'PROD'));
    if (!function_exists('get_cashfree_app_id')) { function get_cashfree_app_id() { return CASHFREE_APP_ID; } }
    if (!function_exists('get_cashfree_secret_key')) { function get_cashfree_secret_key() { return CASHFREE_SECRET_KEY; } }
    if (!function_exists('get_cashfree_env')) { function get_cashfree_env() { return 'PROD'; } }

    // Issue 1-Year Persistent Remember Token for User or Admin
    if (!function_exists('set_user_remember_token')) {
        function set_user_remember_token($user_id, $role = 'user') {
            global $conn;
            $user_id = (int)$user_id;
            $token = bin2hex(random_bytes(32));
            if ($conn && !$conn->connect_error) {
                if ($role === 'admin') {
                    $s1 = $conn->prepare("UPDATE admin SET remember_token = ? WHERE id = ?");
                    if ($s1) {
                        $s1->bind_param("si", $token, $user_id);
                        $s1->execute();
                        $s1->close();
                    }
                    $s2 = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ? OR (role = 'admin' AND id = ?)");
                    if ($s2) {
                        $s2->bind_param("sii", $token, $user_id, $user_id);
                        $s2->execute();
                        $s2->close();
                    }
                } else {
                    $stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("si", $token, $user_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
            $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
                        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
                        (!empty($_SERVER['HTTP_CF_VISITOR']) && str_contains($_SERVER['HTTP_CF_VISITOR'], 'https'));
            $expire = time() + (365 * 24 * 3600);
            setcookie('adept_remember', $token, [
                'expires' => $expire,
                'path' => '/',
                'domain' => '',
                'secure' => $is_https,
                'httponly' => true,
                'samesite' => $is_https ? 'None' : 'Lax'
            ]);
            $_COOKIE['adept_remember'] = $token;

            $panel = ($role === 'admin') ? 'admin' : 'user';
            setcookie('beastz_active_panel', $panel, [
                'expires' => $expire,
                'path' => '/',
                'domain' => '',
                'secure' => $is_https,
                'httponly' => false,
                'samesite' => 'Lax'
            ]);
            $_COOKIE['beastz_active_panel'] = $panel;
        }
    }

    // Clear Persistent Remember Token (On explicit Logout)
    if (!function_exists('clear_user_remember_token')) {
        function clear_user_remember_token($user_id = null) {
            global $conn;
            if ($user_id && $conn && !$conn->connect_error) {
                $uid = (int)$user_id;
                @$conn->query("UPDATE users SET remember_token = NULL WHERE id = {$uid}");
                @$conn->query("UPDATE admin SET remember_token = NULL WHERE id = {$uid}");
            }
            $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            setcookie('adept_remember', '', time() - 86400, '/', '', $is_https, true);
            setcookie('beastz_active_panel', '', time() - 86400, '/', '', $is_https, false);
            unset($_COOKIE['adept_remember']);
            unset($_COOKIE['beastz_active_panel']);
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


    // =========================================================================
    // SYSTEM GUARDS: MAINTENANCE MODE & FORCE UPDATE APK
    // =========================================================================
    if (!function_exists('beastz_render_maintenance_screen')) {
        function beastz_render_maintenance_screen() {
            if (ob_get_level() > 0) { ob_clean(); }
            header('Content-Type: text/html; charset=utf-8');
            header('HTTP/1.1 503 Service Unavailable');
            header('Status: 503 Service Unavailable');
            header('Retry-After: 300');
            ?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Under Maintenance | BEASTz BATTLE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background-color: #080a0f; color: #f8fafc; font-family: system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 selection:bg-blue-600 selection:text-white">
    <div class="max-w-md w-full bg-slate-900/90 border border-slate-800 rounded-3xl p-7 shadow-2xl backdrop-blur-xl text-center space-y-6">
        <!-- Glowing Pulse Icon -->
        <div class="relative w-24 h-24 mx-auto">
            <div class="absolute inset-0 bg-blue-600/30 rounded-3xl blur-xl animate-pulse"></div>
            <div class="relative w-full h-full bg-gradient-to-br from-blue-600 to-blue-800 rounded-3xl border border-blue-400/40 flex items-center justify-center text-4xl text-white shadow-xl">
                <i class="fa-solid fa-screwdriver-wrench animate-spin" style="animation-duration: 9s;"></i>
            </div>
        </div>

        <!-- Headings -->
        <div class="space-y-2">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-950/70 border border-blue-800 text-blue-400 text-xs font-black uppercase tracking-widest">
                <span class="w-2 h-2 rounded-full bg-blue-400 animate-ping"></span>
                BEASTz BATTLE SYSTEM
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight uppercase">
                Under Maintenance
            </h1>
            <p class="text-slate-300 text-sm font-medium leading-relaxed px-2">
                Under Maintenance. We are upgrading our systems for a better experience. Please check back shortly.
            </p>
        </div>

        <!-- Upgrade highlights -->
        <div class="bg-slate-950/60 border border-slate-800/80 rounded-2xl p-4 text-left space-y-2.5 text-xs text-slate-400">
            <div class="flex items-center gap-2.5 text-slate-300 font-bold uppercase text-[11px] tracking-wide">
                <i class="fa-solid fa-microchip text-blue-400"></i>
                <span>Current System Upgrades:</span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-[11px]">
                <div class="flex items-center gap-1.5"><i class="fa-solid fa-check text-blue-400"></i><span>TiDB Cloud Sync</span></div>
                <div class="flex items-center gap-1.5"><i class="fa-solid fa-check text-blue-400"></i><span>Anti-Cheat Engine</span></div>
                <div class="flex items-center gap-1.5"><i class="fa-solid fa-check text-blue-400"></i><span>Duo & Squad Slots</span></div>
                <div class="flex items-center gap-1.5"><i class="fa-solid fa-check text-blue-400"></i><span>Instant PG Payouts</span></div>
            </div>
        </div>

        <!-- Action / Reload -->
        <div class="space-y-3 pt-2">
            <button onclick="window.location.reload()" class="w-full py-3.5 bg-blue-600 hover:bg-blue-500 active:scale-95 text-white font-black rounded-2xl text-xs uppercase tracking-wider transition shadow-lg shadow-blue-600/30 flex items-center justify-center gap-2 cursor-pointer">
                <i class="fa-solid fa-arrows-rotate"></i>
                <span>Check System Status</span>
            </button>
            <div class="flex items-center justify-between text-[11px] text-slate-500 pt-1">
                <span>Support: WhatsApp / Discord</span>
                <a href="login.php" class="hover:text-blue-400 transition">Host / Admin Access &rarr;</a>
            </div>
        </div>
    </div>
</body>
</html>
            <?php
            exit;
        }
    }

    if (!function_exists('beastz_render_force_update_screen')) {
        function beastz_render_force_update_screen($download_url) {
            if (ob_get_level() > 0) { ob_clean(); }
            header('Content-Type: text/html; charset=utf-8');
            header('HTTP/1.1 426 Upgrade Required');
            ?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Update Required | BEASTz BATTLE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background-color: #080a0f; color: #f8fafc; font-family: system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 selection:bg-blue-600 selection:text-white">
    <div class="max-w-md w-full bg-slate-900/90 border border-slate-800 rounded-3xl p-7 shadow-2xl backdrop-blur-xl text-center space-y-6">
        <!-- App Icon -->
        <div class="relative w-24 h-24 mx-auto">
            <div class="absolute inset-0 bg-blue-600/30 rounded-3xl blur-xl animate-pulse"></div>
            <div class="relative w-full h-full bg-gradient-to-br from-blue-600 to-indigo-700 rounded-3xl border border-blue-400/40 flex items-center justify-center text-4xl text-white shadow-xl">
                <i class="fa-solid fa-cloud-arrow-down"></i>
            </div>
        </div>

        <!-- Headings -->
        <div class="space-y-2">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-950/70 border border-rose-800 text-rose-400 text-xs font-black uppercase tracking-widest">
                <i class="fa-solid fa-triangle-exclamation"></i>
                MANDATORY UPDATE
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight uppercase">
                Update Required
            </h1>
            <p class="text-slate-300 text-sm font-medium leading-relaxed px-2">
                A critical update is required to continue playing on BEASTz BATTLE. Please install the latest version to access upcoming tournaments, live room IDs, and wallet withdrawals.
            </p>
        </div>

        <!-- Download Action -->
        <div class="space-y-3 pt-2">
            <a href="<?= htmlspecialchars($download_url) ?>" target="_blank" class="w-full py-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 active:scale-95 text-white font-black rounded-2xl text-sm uppercase tracking-wider transition shadow-lg shadow-blue-600/30 flex items-center justify-center gap-2.5 cursor-pointer no-underline">
                <i class="fa-solid fa-download text-lg"></i>
                <span>Download Update</span>
            </a>
            <p class="text-[11px] text-slate-500">
                Official BEASTz BATTLE release hosted on GitHub Releases
            </p>
        </div>
    </div>
</body>
</html>
            <?php
            exit;
        }
    }

    if (!function_exists('beastz_check_system_guards')) {
        function beastz_check_system_guards() {
            global $conn;
            if (!$conn || $conn->connect_error) return;

            $req_uri = $_SERVER['REQUEST_URI'] ?? '/';
            $path = parse_url($req_uri, PHP_URL_PATH) ?: '/';
            $path_lower = strtolower($path);

            // Allowed paths that are NEVER blocked (Admin controls, worker controls, login, auth actions, assets)
            $is_admin_path = str_contains($path_lower, 'admin') || 
                             str_starts_with($path_lower, '/admin') ||
                             str_contains($path_lower, 'worker') ||
                             str_ends_with($path_lower, 'login.php') || 
                             str_ends_with($path_lower, 'auth_action.php') || 
                             str_ends_with($path_lower, 'logout.php') ||
                             str_ends_with($path_lower, 'admin_poll.php') ||
                             str_ends_with($path_lower, 'check_update.php') ||
                             str_ends_with($path_lower, 'maintenance_toggle.php') ||
                             str_ends_with($path_lower, 'apk_update_toggle.php') ||
                             str_ends_with($path_lower, 'manifest.json') ||
                             str_ends_with($path_lower, 'sw.js');

            // Static asset check
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'webp', 'woff2', 'ttf', 'apk'])) {
                return;
            }

            // Super Admin, Admin & Worker session bypass
            $admin_user = strtolower(trim((string)($_SESSION['admin_user'] ?? $_SESSION['username'] ?? '')));
            $is_admin_session = !empty($_SESSION['admin_id']) || 
                                !empty($_SESSION['admin_logged_in']) || 
                                (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
                                !empty($_SESSION['is_super_admin']) ||
                                !empty($_SESSION['is_worker']) ||
                                in_array($admin_user, ['anonymous.349', 'admin', 'noorshaikh99887@gmail.com']) ||
                                (isset($_SESSION['admin_role']) && in_array($_SESSION['admin_role'], ['super_admin', 'host_admin', 'admin']));

            // Admin panel and authenticated admins/workers are completely exempt from Maintenance Mode and Force Update
            if ($is_admin_path || $is_admin_session) {
                return;
            }

            $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                       || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'));

            // 1. CHECK MAINTENANCE MODE (Only blocks non-admin public gamers)
            $maintenance_mode = get_setting('maintenance_mode', '0');
            if ($maintenance_mode === '1') {
                if ($is_ajax) {
                    http_response_code(503);
                    header('Content-Type: application/json; charset=utf-8');
                    header('Retry-After: 300');
                    echo json_encode([
                        'success' => false,
                        'error' => 'Under Maintenance. We are upgrading our systems for a better experience. Please check back shortly.',
                        'maintenance' => true
                    ]);
                    exit;
                }

                http_response_code(503);
                header('Retry-After: 300');
                beastz_render_maintenance_screen();
                exit;
            }

            // 2. CHECK APK FORCE UPDATE
            $apk_force_update = get_setting('apk_force_update', '0');
            if ($apk_force_update === '1') {
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $is_native_apk = str_contains($user_agent, 'BEASTzNativeApp') || 
                                 (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'com.beastzbattle.app') ||
                                 (isset($_GET['apk']) && $_GET['apk'] === '1');

                if ($is_native_apk) {
                    $download_url = get_setting('apk_download_url', 'https://github.com/bnoorshaikh99887-alt/beast-battle/releases/download/v2.0/BEASTz-BATTLE-v2.0.apk');
                    
                    if ($is_ajax) {
                        http_response_code(426);
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'success' => false,
                            'error' => 'Update Required',
                            'force_update' => true,
                            'download_url' => $download_url
                        ]);
                        exit;
                    }

                    beastz_render_force_update_screen($download_url);
                    exit;
                }
            }
        }
    }


    // Run system guards
    beastz_check_system_guards();

    // Release the PHP session lock before slow read-only polls return.
    // Otherwise wallet/join/UTR requests wait behind live_sync and the APK feels frozen.
    if (!empty($beastz_is_light) && session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }
}
?>
