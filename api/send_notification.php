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

$stmt = $conn->prepare("INSERT INTO app_notifications (title, message, target_url, badge_type) VALUES (?, ?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("ssss", $title, $message, $target_url, $badge_type);
    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        $stmt->close();
        echo json_encode([
            'success' => true,
            'id' => $new_id,
            'message' => 'Push notification broadcasted successfully to all users!'
        ]);
        exit;
    }
    $stmt->close();
}

echo json_encode(['success' => false, 'error' => $conn->error ?: 'Failed to insert notification']);
