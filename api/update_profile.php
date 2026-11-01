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

// ==============================================================
// 1. AVATAR / PROFILE PHOTO UPLOAD HANDLER
// ==============================================================
if ($action === 'update_avatar' || isset($_FILES['avatar_file'])) {
    $final_avatar = '';
    $upload_dir = __DIR__ . '/../uploads/avatars/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }

    $avatar_input = trim($_POST['avatar'] ?? $json_data['avatar'] ?? '');
    $is_preset_svg = (!empty($avatar_input) && (str_starts_with($avatar_input, 'data:image/svg+xml') || str_starts_with($avatar_input, '<svg')));

    // A. If user picked preset gaming character vector, use immediately
    if ($is_preset_svg) {
        $final_avatar = $avatar_input;
    }
    // B. Check for direct file upload via $_FILES
    else if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['avatar_file']['tmp_name'];
        $orig_name = $_FILES['avatar_file']['name'];
        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION)) ?: 'jpg';
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $ext = 'jpg';
        }
        $filename = 'avatar_' . $curr_uid . '_' . time() . '.' . $ext;
        $target_path = $upload_dir . $filename;
        if (@move_uploaded_file($tmp_name, $target_path)) {
            $final_avatar = 'uploads/avatars/' . $filename;
        }
    }

    // B. Check for Base64 Data URI or SVG / preset string
    if (empty($final_avatar)) {
        $avatar_input = trim($_POST['avatar'] ?? $json_data['avatar'] ?? '');
        if (!empty($avatar_input)) {
            if (str_starts_with($avatar_input, 'data:image')) {
                // Try writing base64 image to file for fast CDN delivery
                $parts = explode(',', $avatar_input, 2);
                if (count($parts) === 2) {
                    $decoded = base64_decode($parts[1]);
                    if ($decoded !== false) {
                        $filename = 'avatar_' . $curr_uid . '_' . time() . '.jpg';
                        $target_path = $upload_dir . $filename;
                        if (@file_put_contents($target_path, $decoded)) {
                            $final_avatar = 'uploads/avatars/' . $filename;
                        }
                    }
                }
            }
            // Fallback: save data URI or preset avatar string directly
            if (empty($final_avatar)) {
                $final_avatar = $avatar_input;
            }
        }
    }

    if (empty($final_avatar)) {
        echo json_encode(['success' => false, 'error' => 'No image data was provided. Please choose a photo.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $final_avatar, $curr_uid);
        if ($stmt->execute()) {
            $stmt->close();
            $_SESSION['user_avatar'] = $final_avatar;
            echo json_encode([
                'success' => true,
                'avatar' => $final_avatar,
                'message' => 'Profile picture updated successfully!'
            ]);
            exit;
        }
        $stmt->close();
    }

    echo json_encode(['success' => false, 'error' => 'Database error updating avatar: ' . $conn->error]);
    exit;
}

// ==============================================================
// 2. STANDARD IN-GAME PROFILE & UPI SETTINGS
// ==============================================================
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
