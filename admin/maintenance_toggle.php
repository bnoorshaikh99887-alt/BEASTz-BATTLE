<?php
/**
 * BEASTz BATTLE - Maintenance Mode Security Toggle
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

function maint_out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

global $conn;
if (!$conn || $conn->connect_error) {
    maint_out(["success" => false, "error" => "Database connection unavailable"], 500);
}

// 1. POST only
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    maint_out(["success" => false, "error" => "Only POST method is permitted"], 405);
}

// 2. Super Admin Authorization Check
$admin_id   = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);
$admin_user = (string)($_SESSION['admin_user'] ?? $_SESSION['username'] ?? '');
$is_super   = !empty($_SESSION['is_super_admin']) || 
              !empty($_SESSION['admin_logged_in']) ||
              ((string)($_SESSION['role'] ?? '') === 'admin') ||
              ($admin_user === 'anonymous.349') || 
              (isset($_SESSION['admin_role']) && in_array($_SESSION['admin_role'], ['super_admin', 'admin']));

if (!$is_super || $admin_id <= 0) {
    maint_out(["success" => false, "error" => "Access Denied: Only Master / Super Administrator can toggle Maintenance Mode."], 403);
}

// 3. Same-origin CSRF validation
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $o_host = parse_url($origin, PHP_URL_HOST);
    $h_host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    if (!$o_host || strcasecmp($o_host, $h_host) !== 0) {
        maint_out(["success" => false, "error" => "Cross-origin request rejected"], 403);
    }
}

// 4. Parse payload
$raw_input = file_get_contents("php://input");
$json_data = json_decode($raw_input, true);
if (!is_array($json_data)) $json_data = [];

$req_user = trim((string)($_POST['username'] ?? $json_data['username'] ?? $admin_user));
$password = trim((string)($_POST['password'] ?? $json_data['password'] ?? ''));
$target_mode = trim((string)($_POST['maintenance_mode'] ?? $json_data['maintenance_mode'] ?? ''));

// Master admin check: allow anonymous.349 or authenticated admin username
if ($req_user === '') {
    $req_user = $admin_user ?: 'anonymous.349';
}

if ($password === '') {
    maint_out(["success" => false, "error" => "Admin password is required to change Maintenance Mode!"], 400);
}

// 5. Password verification
function maint_check_pass(string $input, string $stored): bool {
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
    if ($row && maint_check_pass($password, (string)$row['password'])) {
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
        if ($row && maint_check_pass($password, (string)$row['password'])) {
            $is_auth = true;
        }
    }
}

if (!$is_auth) {
    maint_out(["success" => false, "error" => "Authentication Failed! Invalid admin password."], 403);
}

// 6. Determine new maintenance state (toggle if not specified)
$current_mode = get_setting('maintenance_mode', '0');
if ($target_mode !== '0' && $target_mode !== '1') {
    $new_mode = ($current_mode === '1') ? '0' : '1';
} else {
    $new_mode = $target_mode;
}

// 7. Atomic update in settings table
$stmt = $conn->prepare("INSERT INTO settings (`key`, `value`) VALUES ('maintenance_mode', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
if ($stmt) {
    $stmt->bind_param("s", $new_mode);
    $stmt->execute();
    $stmt->close();
}

// Also update setting_key/setting_value if column structure differs
@$conn->query("UPDATE settings SET setting_value = '{$new_mode}' WHERE setting_key = 'maintenance_mode'");

$status_text = ($new_mode === '1') ? 'ENABLED (Platform & APK Locked)' : 'DISABLED (Platform Live)';
maint_out([
    "success" => true,
    "maintenance_mode" => $new_mode,
    "status_text" => $status_text,
    "message" => "Maintenance Mode successfully updated to {$status_text}."
]);
