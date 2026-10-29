<?php
// common/config.php - TiDB Cloud, Cashfree & Complete Helper Functions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Disable fatal exception crash on connection failure
mysqli_report(MYSQLI_REPORT_OFF);

// TiDB Cloud Serverless MySQL Connection
$db_host = 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com';
$db_port = 4000;
$db_user = '33NZV583e1gPsMh.root';
$db_pass = 'mcj5pocb1FlVZD68';
$db_name = 'test';

$conn = mysqli_init();
if ($conn) {
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    @$conn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port, NULL, MYSQLI_CLIENT_SSL);
}

// Cashfree Live Production Keys
define('CASHFREE_APP_ID', '1494190b594e98ed762a2a943320914941');
define('CASHFREE_SECRET_KEY', 'cfsk_ma_prod_979295dd86c34ea7eef3d56c1e07d25e_27097462');
define('CASHFREE_ENV', 'production');
define('CASHFREE_API_URL', 'https://api.cashfree.com/pg');

// Official Adept Support
define('SUPPORT_PHONE_1', '+91 9878385810');
define('SUPPORT_PHONE_2', '+91 988385810');
define('APP_NAME', 'Adept Play');

// Currency Formatter
function format_inr($amount) {
    return '₹' . number_format((float)$amount, 2);
}

function format_currency($amount) {
    return format_inr($amount);
}

// Input Sanitization Functions
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

function clean_input($data) {
    return sanitize($data);
}

function clean($data) {
    return sanitize($data);
}

// User Authentication Checks
function check_user_auth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /login.php");
        exit;
    }
}

function check_user_login() {
    check_user_auth();
}

function check_auth() {
    check_user_auth();
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Admin Authentication Checks
function check_admin_auth() {
    if (!isset($_SESSION['admin_logged_in']) && !isset($_SESSION['admin_id'])) {
        header("Location: /login.php");
        exit;
    }
}

function check_admin_login() {
    check_admin_auth();
}

// Settings Helper
function get_setting($key, $default = '') {
    global $conn;
    if (!$conn) return $default;
    $k = $conn->real_escape_string($key);
    $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = '$k' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        return $res->fetch_assoc()['setting_value'];
    }
    return $default;
}
