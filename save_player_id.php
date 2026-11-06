<?php
/**
 * BEASTz BATTLE - Secure OneSignal Device Token (player_id) Sync Handler
 * Updates player_id in TiDB Cloud database user_devices, users, and admin tables
 */
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if (file_exists(__DIR__ . "/common/config.php")) {
    require_once __DIR__ . "/common/config.php";
} elseif (file_exists(__DIR__ . "/config.php")) {
    require_once __DIR__ . "/config.php";
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$raw_input = file_get_contents("php://input");
$json_data = json_decode($raw_input, true) ?? [];

$player_id = trim($_POST["player_id"] ?? $json_data["player_id"] ?? $_GET["player_id"] ?? "");
// SECURE SESSION EXTRACTION: Never trust client parameters for user_id to prevent IDOR
$user_id = (int)($_SESSION["user_id"] ?? 0);
$admin_id = (int)($_SESSION["admin_id"] ?? $_SESSION["worker_id"] ?? 0);

if (empty($player_id)) {
    echo json_encode([
        "success" => false,
        "error" => "player_id parameter is required"
    ]);
    exit;
}

// Persist token in cookie for 1 year
setcookie("beastz_player_id", $player_id, time() + (365 * 24 * 3600), "/");

if (!$conn || $conn->connect_error) {
    echo json_encode([
        "success" => false,
        "error" => "Database connection failure"
    ]);
    exit;
}

// Ensure user_devices table exists
@$conn->query("CREATE TABLE IF NOT EXISTS user_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    player_id VARCHAR(191) NOT NULL,
    platform VARCHAR(50) DEFAULT 'android',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY u_p (user_id, player_id)
)");

$updated_user = false;
$updated_admin = false;

// 1. If user ID is known, link to users and user_devices
if ($user_id > 0) {
    $ins_dev = $conn->prepare("INSERT INTO user_devices (user_id, player_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE updated_at = NOW()");
    if ($ins_dev) {
        $ins_dev->bind_param("is", $user_id, $player_id);
        $ins_dev->execute();
        $ins_dev->close();
    }

    $stmt = $conn->prepare("UPDATE users SET player_id = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $player_id, $user_id);
        $stmt->execute();
        $stmt->close();
        $updated_user = true;
    }
}

// 2. If admin / worker is logged in, link to admin table
if ($admin_id > 0) {
    @$conn->query("ALTER TABLE admin ADD COLUMN IF NOT EXISTS player_id VARCHAR(191) DEFAULT NULL");
    $astmt = $conn->prepare("UPDATE admin SET player_id = ? WHERE id = ?");
    if ($astmt) {
        $astmt->bind_param("si", $player_id, $admin_id);
        $astmt->execute();
        $astmt->close();
        $updated_admin = true;
    }
}

// 3. OneSignal REST API External User ID Linking
$target_ext_id = $user_id > 0 ? (string)$user_id : ($admin_id > 0 ? "admin_" . $admin_id : "");
if (!empty($target_ext_id)) {
    $os_app_id = function_exists('env') ? env('ONESIGNAL_APP_ID', '561bd59e-8355-47c8-88cc-0766688a105a') : '561bd59e-8355-47c8-88cc-0766688a105a';
    $os_key = function_exists('get_onesignal_key') ? get_onesignal_key() : (defined('ONESIGNAL_API_KEY') ? ONESIGNAL_API_KEY : base64_decode('b3NfdjJfYXBwX2t5bjVsaHVka3ZkNHJjZ21hNXRncmNxcWxrM3A0bTR0NXliZTQ1ZnpmbWJjZ3VlYWtmb2xwdHFycWlld25ybXA2N21pNHB4azV3aHllMzZiNTVzbTQ3bDdxbXF0ZGJ2MnVmZmpzYXk='));
    
    $ch = curl_init("https://onesignal.com/api/v1/players/{$player_id}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json; charset=utf-8",
        "Authorization: Key " . $os_key
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "app_id" => $os_app_id,
        "external_user_id" => $target_ext_id
    ]));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    @curl_exec($ch);
    @curl_close($ch);
}

echo json_encode([
    "success" => true,
    "message" => "OneSignal device player_id successfully registered and linked",
    "player_id" => $player_id,
    "user_id" => $user_id,
    "admin_id" => $admin_id,
    "updated_user" => $updated_user,
    "updated_admin" => $updated_admin
]);
?>
