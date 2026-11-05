<?php
/**
 * BEASTz BATTLE - Force Update APK Security Controller
 * Requires Super Admin authorization (username: anonymous.349 + verified admin password)
 */
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

if (file_exists(__DIR__ . "/common/config.php")) {
    require_once __DIR__ . "/common/config.php";
} elseif (file_exists(__DIR__ . "/../common/config.php")) {
    require_once __DIR__ . "/../common/config.php";
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function apk_out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

global $conn;
if (!$conn || $conn->connect_error) {
    apk_out(["success" => false, "error" => "Database connection unavailable"], 500);
}

// 1. POST only
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apk_out(["success" => false, "error" => "Only POST method is permitted"], 405);
}

// 2. Super Admin Authorization Check
$admin_id   = (int)($_SESSION['admin_id'] ?? 0);
$admin_user = (string)($_SESSION['admin_user'] ?? $_SESSION['username'] ?? '');
$is_super   = !empty($_SESSION['is_super_admin']) || 
              ($admin_user === 'anonymous.349') || 
              (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin');

if (!$is_super || $admin_id <= 0) {
    apk_out(["success" => false, "error" => "Access Denied: Only Master / Super Administrator can configure APK Force Update."], 403);
}

// 3. Same-origin CSRF validation
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $o_host = parse_url($origin, PHP_URL_HOST);
    $h_host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    if (!$o_host || strcasecmp($o_host, $h_host) !== 0) {
        apk_out(["success" => false, "error" => "Cross-origin request rejected"], 403);
    }
}

// 4. Parse payload
$raw_input = file_get_contents("php://input");
$json_data = json_decode($raw_input, true);
if (!is_array($json_data)) $json_data = [];

$req_user = trim((string)($_POST['username'] ?? $json_data['username'] ?? $admin_user));
$password = trim((string)($_POST['password'] ?? $json_data['password'] ?? ''));
$target_mode = trim((string)($_POST['apk_force_update'] ?? $json_data['apk_force_update'] ?? ''));
$download_url = trim((string)($_POST['download_url'] ?? $json_data['download_url'] ?? ''));

if ($req_user !== 'anonymous.349' && $admin_user !== 'anonymous.349') {
    apk_out(["success" => false, "error" => "Invalid master username. Username 'anonymous.349' is required."], 403);
}

if ($password === '') {
    apk_out(["success" => false, "error" => "Admin password is required to change APK Force Update status!"], 400);
}

// 5. Password verification
function apk_check_pass(string $input, string $stored): bool {
    if ($stored === '') return false;
    if (password_verify($input, $stored)) return true;
    return hash_equals($stored, $input);
}

$is_auth = false;
$st = $conn->prepare("SELECT password FROM admin WHERE username = ? OR id = ? LIMIT 1");
if ($st) {
    $st->bind_param("si", $req_user, $admin_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if ($row && apk_check_pass($password, (string)$row['password'])) {
        $is_auth = true;
    }
}

if (!$is_auth) {
    $st = $conn->prepare("SELECT password FROM users WHERE (username = ? OR id = ?) AND role = 'admin' LIMIT 1");
    if ($st) {
        $st->bind_param("si", $req_user, $admin_id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row && apk_check_pass($password, (string)$row['password'])) {
            $is_auth = true;
        }
    }
}

if (!$is_auth) {
    apk_out(["success" => false, "error" => "Authentication Failed! Invalid admin password."], 403);
}

// 6. Determine new force update state
$current_mode = get_setting('apk_force_update', '0');
if ($target_mode !== '0' && $target_mode !== '1') {
    $new_mode = ($current_mode === '1') ? '0' : '1';
} else {
    $new_mode = $target_mode;
}

if (empty($download_url)) {
    $download_url = get_setting('apk_download_url', 'https://github.com/bnoorshaikh99887-alt/beast-battle/releases/latest');
}

// 7. Atomic update in settings table
$stmt = $conn->prepare("INSERT INTO settings (`key`, `value`) VALUES ('apk_force_update', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
if ($stmt) {
    $stmt->bind_param("s", $new_mode);
    $stmt->execute();
    $stmt->close();
}

$stmt2 = $conn->prepare("INSERT INTO settings (`key`, `value`) VALUES ('apk_download_url', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
if ($stmt2) {
    $stmt2->bind_param("s", $download_url);
    $stmt2->execute();
    $stmt2->close();
}

@$conn->query("UPDATE settings SET setting_value = '{$new_mode}' WHERE setting_key = 'apk_force_update'");
@$conn->query("UPDATE settings SET setting_value = '" . $conn->real_escape_string($download_url) . "' WHERE setting_key = 'apk_download_url'");

$status_text = ($new_mode === '1') ? 'ENABLED (Old APKs Locked)' : 'DISABLED (Normal Play)';
apk_out([
    "success" => true,
    "apk_force_update" => $new_mode,
    "download_url" => $download_url,
    "status_text" => $status_text,
    "message" => "Force Update APK successfully updated to {$status_text}."
]);
