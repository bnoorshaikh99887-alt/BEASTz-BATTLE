<?php
session_start();
require_once __DIR__ . '/common/config.php'; // Tumhara DB connection

// JS se aaya hua Google Token receive karo
$data = json_decode(file_get_contents('php://input'), true);
$token = $data['token'] ?? '';

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Token not received']);
    exit;
}

// Google ke API se token verify karo
$verify_url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $token;
$response = @file_get_contents($verify_url);
$google_info = json_decode($response, true);

if (isset($google_info['email'])) {
    $email = $google_info['email'];
    $name = $google_info['name'];
    $avatar = $google_info['picture'] ?? 'https://api.dicebear.com/7.x/bottts/svg?seed=' . urlencode($name);
    
    // Check karo ki ye email TiDB database mein pehle se hai ya nahi
    $stmt = $conn->prepare("SELECT id, username, email, role, status FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        // Puraana user hai -> Blocked to nahi hai check karo
        if (($user['status'] ?? 'active') === 'blocked') {
            echo json_encode(['success' => false, 'message' => 'Your account is suspended.']);
            exit;
        }
        
        // Login karao (Session set karo)
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = 'user';
        $_SESSION['user_logged_in'] = true;
        
        echo json_encode(['success' => true, 'redirect' => 'index.php']);
    } else {
        // Naya user hai -> Database mein account create karo
        $base_username = preg_replace('/[^a-zA-Z0-9]/', '', $name);
        $username = $base_username . rand(1000, 9999); 
        $phone = 'G_' . rand(100000000, 999999999); // Google phone nahi deta, isliye temporary save kiya
        $hashed_password = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT); 
        $wallet_balance = 0.00;
        $my_ref_code = 'BEAST' . strtoupper(substr(md5($username . time()), 0, 5));
        $referral = '';

        $ins = $conn->prepare("INSERT INTO users (username, name, email, phone, password, role, status, wallet_balance, avatar, referral_code, referred_by) VALUES (?, ?, ?, ?, ?, 'user', 'active', ?, ?, ?, ?)");
        $ins->bind_param("sssssdsss", $username, $name, $email, $phone, $hashed_password, $wallet_balance, $avatar, $my_ref_code, $referral);
        
        if ($ins->execute()) {
            $new_id = $ins->insert_id;
            $ins->close();
            
            // Naye account ka session set karo
            $_SESSION['user_id'] = $new_id;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = 'user';
            $_SESSION['user_logged_in'] = true;
            
            echo json_encode(['success' => true, 'redirect' => 'index.php']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Google Token']);
}
?>
