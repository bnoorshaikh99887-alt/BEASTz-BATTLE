<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../common/config.php';

global $conn;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please login to update your profile.']);
    exit;
}

$curr_uid = (int)$_SESSION['user_id'];
$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true) ?? [];

$action = $_POST['action'] ?? $json_data['action'] ?? 'update_account_settings';

if ($action === 'update_avatar') {
    $avatar = trim($_POST['avatar'] ?? $json_data['avatar'] ?? '');
    if (empty($avatar)) {
        echo json_encode(['success' => false, 'error' => 'Invalid avatar data provided.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $avatar, $curr_uid);
        if ($stmt->execute()) {
            $stmt->close();
            $_SESSION['user_avatar'] = $avatar;
            echo json_encode([
                'success' => true,
                'avatar' => $avatar,
                'message' => 'Profile picture updated instantly!'
            ]);
            exit;
        }
        $stmt->close();
    }
    echo json_encode(['success' => false, 'error' => 'Database error updating avatar: ' . $conn->error]);
    exit;
}

// Standard profile settings update
$game_name = sanitize($_POST['game_name'] ?? $json_data['game_name'] ?? '');
$game_id = sanitize($_POST['game_id'] ?? $json_data['game_id'] ?? '');
$phone = sanitize($_POST['phone'] ?? $json_data['phone'] ?? '');
$upi_id = sanitize($_POST['upi_id'] ?? $json_data['upi_id'] ?? '');

$upd = $conn->prepare("UPDATE users SET game_name = ?, game_id = ?, phone = IF(? != '', ?, phone), upi_id = IF(? != '', ?, upi_id) WHERE id = ?");
if ($upd) {
    $upd->bind_param("ssssssi", $game_name, $game_id, $phone, $phone, $upi_id, $upi_id, $curr_uid);
    if ($upd->execute()) {
        $upd->close();
        if (!empty($game_name)) $_SESSION['user_game_name'] = $game_name;
        echo json_encode([
            'success' => true,
            'message' => 'Account settings saved successfully!',
            'game_name' => $game_name,
            'game_id' => $game_id,
            'phone' => $phone,
            'upi_id' => $upi_id
        ]);
        exit;
    }
    $upd->close();
}

echo json_encode(['success' => false, 'error' => 'Failed to save account settings: ' . $conn->error]);
