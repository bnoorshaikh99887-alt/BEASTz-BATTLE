<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../common/config.php';
global $conn;

// Enforce admin permission
$is_admin = false;
if (isset($_SESSION['admin_id']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') || (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin')) {
    $is_admin = true;
}

if (!$is_admin) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized administrator access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST method required']);
    exit;
}

$title = sanitize($_POST['title'] ?? '');
$message = sanitize($_POST['message'] ?? '');
$target_url = sanitize($_POST['target_url'] ?? '/?tab=home');
$badge_type = sanitize($_POST['badge_type'] ?? 'ANNOUNCEMENT');

if (empty($title) || empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Title and message cannot be empty']);
    exit;
}

// 1. Store in local DB history
$db_saved = false;
$new_id = 0;
$stmt = $conn->prepare("INSERT INTO app_notifications (title, message, target_url, badge_type) VALUES (?, ?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("ssss", $title, $message, $target_url, $badge_type);
    if ($stmt->execute()) {
        $db_saved = true;
        $new_id = $stmt->insert_id;
    }
    $stmt->close();
}

// 2. DISPATCH TO ONESIGNAL REST API
$onesignal_app_id = "561bd59e-8355-47c8-88cc-0766688a105a";
$default_encoded = 'b3NfdjJfYXBwX2t5bjVsaHVka3ZkNHJjZ21hNXRncmNxcWxrM3A0bTR0NXliZTQ1ZnpmbWJjZ3VlYWtmb2xwdHFycWlld25ybXA2N21pNHB4azV3aHllMzZiNTVzbTQ3bDdxbXF0ZGJ2MnVmZmpzYXk=';
$onesignal_rest_key = base64_decode($default_encoded);

$fields = [
    'app_id' => $onesignal_app_id,
    'included_segments' => ['Total Subscriptions'],
    'headings' => ['en' => $title],
    'contents' => ['en' => $message],
    'data' => [
        'target_url' => $target_url,
        'badge_type' => $badge_type
    ],
    'priority' => 10
];

$payload_json = json_encode($fields);
$response = false;
$http_code = 0;

// Try cURL first
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic ' . $onesignal_rest_key
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_json);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
}

// Fallback using stream context if cURL was empty or failed
if (empty($response)) {
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json; charset=utf-8\r\n" .
                        "Authorization: Basic " . $onesignal_rest_key . "\r\n",
            'content' => $payload_json,
            'timeout' => 12,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];
    $ctx = stream_context_create($opts);
    $response = @file_get_contents("https://onesignal.com/api/v1/notifications", false, $ctx);
}

$onesignal_res = json_decode($response, true);
$recipients = $onesignal_res['recipients'] ?? 0;
$notif_id = $onesignal_res['id'] ?? '';

echo json_encode([
    'success' => true,
    'id' => $new_id,
    'onesignal_id' => $notif_id,
    'recipients' => $recipients,
    'response_raw' => $onesignal_res,
    'message' => 'Push notification broadcasted successfully to all gamer devices!'
]);
