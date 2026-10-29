<?php
// Prevent multiple inclusions
if (!defined('ADEPT_PLAY_CONFIG')) {
    define('ADEPT_PLAY_CONFIG', true);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // =========================================================================
    // ✅ TiDB CLOUD LIVE DATABASE CONNECTION (SSL ENABLED)
    // =========================================================================
    $db_host = "gateway01.ap-southeast-1.prod.aws.tidbcloud.com";
    $db_user = "33NZV583e1gPsMh.root";
    $db_pass = "ZOxtqv6OtRayGxb1";
    $db_name = "test";
    $db_port = 4000;

    // Connect with MySQLi SSL for TiDB Cloud
    $conn = mysqli_init();
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    @$conn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port, NULL, MYSQLI_CLIENT_SSL);

    if ($conn->connect_error) {
        $script_name = basename($_SERVER['PHP_SELF']);
        if ($script_name !== 'install.php') {
            header("Location: install.php");
            exit;
        }
    } else {
        $conn->set_charset("utf8mb4");
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

    function sanitize($data) {
        global $conn;
        return htmlspecialchars(trim($conn->real_escape_string($data)));
    }

    function format_inr($amount) {
        return '₹' . number_format((float)$amount, 2);
    }

    function check_user_auth() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit;
        }
    }

    function check_admin_auth() {
        if (!isset($_SESSION['admin_id'])) {
            header("Location: login.php");
            exit;
        }
    }
}
?>