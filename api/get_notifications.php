<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../common/config.php';

global $conn;
$since_id = (int)($_GET['since_id'] ?? 0);

if (!$conn || $conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Fetch newest notifications created
$stmt = $conn->prepare("SELECT id, title, message, target_url, badge_type, created_at FROM app_notifications WHERE id > ? ORDER BY id ASC LIMIT 10");
if ($stmt) {
    $stmt->bind_param("i", $since_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $notifications = [];
    while ($row = $res->fetch_assoc()) {
        $notifications[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'message' => $row['message'],
            'target_url' => $row['target_url'] ?: '/?tab=home',
            'badge_type' => $row['badge_type'] ?: 'ANNOUNCEMENT',
            'created_at' => $row['created_at']
        ];
    }
    $stmt->close();
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'count' => count($notifications)
    ]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}
