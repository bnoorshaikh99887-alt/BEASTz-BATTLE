<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../common/config.php';
global $conn;

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please login to request withdrawal.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST method required.']);
    exit;
}

$curr_uid = (int)$_SESSION['user_id'];
$amount = (float)($_POST['amount'] ?? 0.00);
$upi_id = sanitize($_POST['upi_id'] ?? '');
$min_wdr = (float)get_setting('min_withdrawal', '50.00');

if ($amount < $min_wdr) {
    echo json_encode(['success' => false, 'error' => 'Minimum withdrawal amount is ' . format_inr($min_wdr) . '.']);
    exit;
}

if (empty($upi_id) || !str_contains($upi_id, '@')) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid UPI ID (e.g. yourname@upi).']);
    exit;
}

// ATOMIC TRANSACTION: Row locking + barrier balance deduction
$conn->begin_transaction();
try {
    // 1. Lock user row to prevent concurrent race condition requests
    $u_stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
    $u_stmt->bind_param("i", $curr_uid);
    $u_stmt->execute();
    $u_data = $u_stmt->get_result()->fetch_assoc();
    $u_stmt->close();

    if (!$u_data) {
        throw new Exception("User account not found.");
    }

    $avail_bal = (float)($u_data['wallet_balance'] ?? 0.00);
    if ($avail_bal < $amount) {
        throw new Exception("Insufficient balance. You have " . format_inr($avail_bal) . " available.");
    }

    // 2. Atomic barrier deduction: checks balance >= amount at the moment of update
    $ded = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?");
    $ded->bind_param("did", $amount, $curr_uid, $amount);
    $ded->execute();
    $deducted = ($ded->affected_rows === 1);
    $ded->close();

    if (!$deducted) {
        throw new Exception("Insufficient balance or balance was modified by a concurrent transaction.");
    }

    // 3. Record pending withdrawal
    $w_stmt = $conn->prepare("INSERT INTO withdrawals (user_id, amount, upi_id, status) VALUES (?, ?, ?, 'Pending')");
    $w_stmt->bind_param("ids", $curr_uid, $amount, $upi_id);
    $w_stmt->execute();
    $withdrawal_id = $w_stmt->insert_id;
    $w_stmt->close();

    // 4. Record passbook debit
    $tx_desc = "Withdrawal Request (Pending to " . $upi_id . ")";
    $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
    $tx->bind_param("ids", $curr_uid, $amount, $tx_desc);
    $tx->execute();
    $tx->close();

    // 5. Update user last-used UPI & device token
    $conn->query("UPDATE users SET upi_id = '" . $conn->real_escape_string($upi_id) . "' WHERE id = {$curr_uid}");
    $c_pid = trim($_POST["player_id"] ?? $_COOKIE["beastz_player_id"] ?? "");
    if (!empty($c_pid) && strlen($c_pid) > 15) {
        $conn->query("UPDATE withdrawals SET player_id = '" . $conn->real_escape_string($c_pid) . "' WHERE id = {$withdrawal_id}");
        $conn->query("INSERT IGNORE INTO user_devices (user_id, player_id) VALUES ({$curr_uid}, '" . $conn->real_escape_string($c_pid) . "')");
        $conn->query("UPDATE users SET player_id = '" . $conn->real_escape_string($c_pid) . "' WHERE id = {$curr_uid}");
    }

    $conn->commit();

    $new_balance = $avail_bal - $amount;
    echo json_encode([
        'success' => true,
        'message' => 'Withdrawal request of ' . format_inr($amount) . ' submitted! Payout will be sent to ' . $upi_id . ' within 15-30 minutes.',
        'amount' => $amount,
        'new_balance' => $new_balance,
        'new_balance_formatted' => format_compact_inr($new_balance),
        'upi_id' => $upi_id,
        'withdrawal_id' => $withdrawal_id,
        'created_at' => date('d M Y, h:i A')
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Withdrawal failed: ' . $e->getMessage()]);
}
