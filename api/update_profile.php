<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../common/config.php';
global $conn;

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please login to update your profile.']);
    exit;
}

$curr_uid = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? 'update_account_settings';

if ($action === 'update_avatar') {
    $avatar = trim($_POST['avatar'] ?? '');
    if (empty($avatar)) {
        echo json_encode(['success' => false, 'error' => 'Invalid avatar data provided.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $avatar, $curr_uid);
        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode([
                'success' => true,
                'avatar' => $avatar,
                'message' => 'Profile picture updated instantly!'
            ]);
            exit;
        }
        $stmt->close();
    }
    echo json_encode(['success' => false, 'error' => 'Database error updating avatar.']);
    exit;
}

// Standard profile settings update
$game_name = sanitize($_POST['game_name'] ?? '');
$game_id = sanitize($_POST['game_id'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');
$upi_id = sanitize($_POST['upi_id'] ?? '');

$upd = $conn->prepare("UPDATE users SET game_name = ?, game_id = ?, phone = IF(? != '', ?, phone), upi_id = IF(? != '', ?, upi_id) WHERE id = ?");
if ($upd) {
    $upd->bind_param("ssssssi", $game_name, $game_id, $phone, $phone, $upi_id, $upi_id, $curr_uid);
    if ($upd->execute()) {
        $upd->close();
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

echo json_encode(['success' => false, 'error' => 'Failed to save account settings.']);
