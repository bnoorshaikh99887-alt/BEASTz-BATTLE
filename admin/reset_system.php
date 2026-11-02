<?php
/**
 * BEASTz BATTLE - Master System Reset (FIXED)
 *
 * Fixes:
 *  - Login (admin session) ab compulsory hai (pehle koi bhi bina login wipe kar sakta tha)
 *  - Hardcoded master passwords (noor009988@, admin123, 34900) hata diye
 *  - Sirf logged-in admin ka apna password verify hota hai
 *  - Sirf POST allowed + same-origin check
 *  - Brute-force limit (5 galat try = 5 min lock)
 *  - Wipe errors ab silent nahi, poora rollback hota hai
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

function rs_out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

global $conn;
if (!$conn || $conn->connect_error) {
    rs_out(["success" => false, "error" => "Database connection failed"], 500);
}

// 1. Sirf POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    rs_out(["success" => false, "error" => "Only POST allowed"], 405);
}

// 2. Admin login compulsory
$admin_id   = (int)($_SESSION['admin_id'] ?? 0);
$admin_user = (string)($_SESSION['admin_user'] ?? $_SESSION['username'] ?? '');
$is_admin   = $admin_id > 0 && (($_SESSION['role'] ?? '') === 'admin' || isset($_SESSION['admin_id']));
if (!$is_admin || $admin_user === '') {
    rs_out(["success" => false, "error" => "Unauthorized. Please login as admin."], 401);
}

// 3. Same-origin check (CSRF se bachav)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $o_host = parse_url($origin, PHP_URL_HOST);
    $h_host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    if (!$o_host || strcasecmp($o_host, $h_host) !== 0) {
        rs_out(["success" => false, "error" => "Cross-site request blocked"], 403);
    }
}

// 4. Brute-force lock
$now = time();
if (($_SESSION['reset_lock_until'] ?? 0) > $now) {
    $wait = (int)ceil(($_SESSION['reset_lock_until'] - $now) / 60);
    rs_out(["success" => false, "error" => "Too many wrong attempts. Try again in {$wait} min."], 429);
}

// Session lock jaldi chhod do taaki baaki requests na ruke
$raw_input = file_get_contents("php://input");
$json_data = json_decode($raw_input, true);
if (!is_array($json_data)) $json_data = [];
$password = trim((string)($_POST["password"] ?? $json_data["password"] ?? $_POST["admin_idp"] ?? $json_data["admin_idp"] ?? ""));

if ($password === "") {
    rs_out(["success" => false, "error" => "Admin password is required to authorize Master Reset!"], 400);
}

// 5. Sirf isi logged-in admin ka password verify karo
function rs_check_pass(string $input, string $stored): bool {
    if ($stored === '') return false;
    if (password_verify($input, $stored)) return true;
    // legacy plain-text password support (sirf exact match)
    return hash_equals($stored, $input);
}

$is_auth = false;

$st = $conn->prepare("SELECT password FROM users WHERE id = ? AND username = ? AND role = 'admin' LIMIT 1");
if ($st) {
    $st->bind_param("is", $admin_id, $admin_user);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if ($row && rs_check_pass($password, (string)$row['password'])) $is_auth = true;
}
if (!$is_auth) {
    $st = $conn->prepare("SELECT password FROM admin WHERE id = ? AND username = ? LIMIT 1");
    if ($st) {
        $st->bind_param("is", $admin_id, $admin_user);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row && rs_check_pass($password, (string)$row['password'])) $is_auth = true;
    }
}

if (!$is_auth) {
    $_SESSION['reset_fail'] = (int)($_SESSION['reset_fail'] ?? 0) + 1;
    if ($_SESSION['reset_fail'] >= 5) {
        $_SESSION['reset_lock_until'] = $now + 300;
        $_SESSION['reset_fail'] = 0;
    }
    rs_out(["success" => false, "error" => "Authentication Failed! Invalid password. Wipe aborted."], 403);
}
unset($_SESSION['reset_fail'], $_SESSION['reset_lock_until']);

// 6. Wipe (all-or-nothing)
mysqli_report(MYSQLI_REPORT_OFF);

function rs_exec(mysqli $c, string $sql): void {
    if ($c->query($sql) === false) {
        // 1146 = table exist nahi karti -> skip, baaki sab error pe rollback
        if ($c->errno === 1146) return;
        throw new Exception($c->error . " [" . $sql . "]");
    }
}

$conn->begin_transaction();
try {
    // A. Tournaments + participants + logs
    rs_exec($conn, "DELETE FROM anti_cheat_logs");
    rs_exec($conn, "DELETE FROM participants");
    rs_exec($conn, "DELETE FROM user_tournaments");
    rs_exec($conn, "DELETE FROM tournaments");

    // B. Money records
    rs_exec($conn, "DELETE FROM withdrawals");
    rs_exec($conn, "DELETE FROM deposits");
    rs_exec($conn, "DELETE FROM transactions");
    rs_exec($conn, "DELETE FROM app_notifications");

    // C. Non-admin users (role NULL wale bhi)
    rs_exec($conn, "DELETE FROM users WHERE (role IS NULL OR role != 'admin') AND id NOT IN (1, 210001, 30002)");

    // D. Balances zero
    rs_exec($conn, "UPDATE users SET wallet_balance = 0.00, winning_balance = 0.00, remember_token = NULL");
    rs_exec($conn, "UPDATE admin SET wallet_balance = 0.00, rooms_hosted = 0 WHERE id != 30002");

    // E. Sab player sessions terminate
    $now_ts = (string)time();
    $ins = $conn->prepare("INSERT INTO settings (`key`, `value`) VALUES ('system_reset_time', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
    if (!$ins) throw new Exception($conn->error);
    $ins->bind_param("s", $now_ts);
    if (!$ins->execute()) throw new Exception($ins->error);
    $ins->close();

    $conn->commit();

    rs_out([
        "success" => true,
        "message" => "MASTER RESET COMPLETED! All tournaments, wallet balances and gamer accounts have been wiped. Player sessions terminated."
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log("reset_system error: " . $e->getMessage());
    rs_out(["success" => false, "error" => "Wipe failed, nothing was changed. Check server log."], 500);
}
