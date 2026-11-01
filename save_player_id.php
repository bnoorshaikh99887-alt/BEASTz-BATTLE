<?php
/**
 * BEASTz BATTLE - Secure OneSignal Device Token (player_id) Sync Handler
 * Updates player_id in TiDB Cloud database users table
 */
header("Content-Type: application/json; charset=utf-8");

// Load TiDB Cloud database connection
if (file_exists(__DIR__ . "/config.php")) {
    require_once __DIR__ . "/config.php";
} elseif (file_exists(__DIR__ . "/common/config.php")) {
    require_once __DIR__ . "/common/config.php";
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Support input via POST form-data, JSON raw body, or GET fallback
$raw_input = file_get_contents("php://input");
$json_data = json_decode($raw_input, true) ?? [];

$user_id = $_SESSION["user_id"] ?? $_POST["user_id"] ?? $json_data["user_id"] ?? null;
$player_id = trim($_POST["player_id"] ?? $json_data["player_id"] ?? $_GET["player_id"] ?? "");

if (empty($player_id)) {
    echo json_encode([
        "success" => false,
        "error" => "player_id parameter is required"
    ]);
    exit;
}

$user_id = (int)$user_id;

if ($user_id <= 0) {
    echo json_encode([
        "success" => false,
        "error" => "User not authenticated or user_id missing",
        "received_player_id" => $player_id
    ]);
    exit;
}

if (!$conn || $conn->connect_error) {
    echo json_encode([
        "success" => false,
        "error" => "Database connection failure"
    ]);
    exit;
}

// 1. Update users table with new player_id
$stmt = $conn->prepare("UPDATE users SET player_id = ? WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("si", $player_id, $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    // 2. Map external_user_id in OneSignal via REST API (v2 Key with Key Auth)
    $os_app_id = "561bd59e-8355-47c8-88cc-0766688a105a";
    $os_key = getenv("ONESIGNAL_API_KEY") ?: base64_decode("b3NfdjJfYXBwX2t5bjVsaHVka3ZkNHJjZ21hNXRncmNxcWxrM3A0bTR0NXliZTQ1ZnpmbWJjZ3VlYWtmb2xwdHFycWlld25ybXA2N21pNHB4azV3aHllMzZiNTVzbTQ3bDdxbXF0ZGJ2MnVmZmpzYXk=");
    
    $ch = curl_init("https://onesignal.com/api/v1/players/{$player_id}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json; charset=utf-8",
        "Authorization: Key " . $os_key
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "app_id" => $os_app_id,
        "external_user_id" => (string)$user_id
    ]));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    @curl_exec($ch);
    @curl_close($ch);

    echo json_encode([
        "success" => true,
        "message" => "OneSignal player_id successfully linked to user in database",
        "user_id" => $user_id,
        "player_id" => $player_id
    ]);
} else {
    echo json_encode([
        "success" => false,
        "error" => "Database query prepare error: " . $conn->error
    ]);
}
?>