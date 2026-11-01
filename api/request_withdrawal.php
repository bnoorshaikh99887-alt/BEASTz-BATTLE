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

$chk_stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
$chk_stmt->bind_param("i", $curr_uid);
$chk_stmt->execute();
$fresh_u = $chk_stmt->get_result()->fetch_assoc();
$chk_stmt->close();

$avail_bal = (float)($fresh_u['wallet_balance'] ?? 0.00);

if ($amount < $min_wdr) {
    echo json_encode(['success' => false, 'error' => 'Minimum withdrawal amount is ' . format_inr($min_wdr) . '.']);
    exit;
}

if (empty($upi_id) || !str_contains($upi_id, '@')) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid UPI ID (e.g. yourname@upi).']);
    exit;
}

if ($avail_bal < $amount) {
    echo json_encode(['success' => false, 'error' => 'Insufficient balance. You have ' . format_inr($avail_bal) . ' available.']);
    exit;
}

$conn->begin_transaction();
try {
    $ded = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
    $ded->bind_param("di", $amount, $curr_uid);
    $ded->execute();
    $ded->close();

    $w_stmt = $conn->prepare("INSERT INTO withdrawals (user_id, amount, upi_id, status) VALUES (?, ?, ?, 'Pending')");
    $w_stmt->bind_param("ids", $curr_uid, $amount, $upi_id);
    $w_stmt->execute();
    $withdrawal_id = $w_stmt->insert_id;
    $w_stmt->close();

    $tx_desc = "Withdrawal Request (Pending to " . $upi_id . ")";
    $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
    $tx->bind_param("ids", $curr_uid, $amount, $tx_desc);
    $tx->execute();
    $tx->close();

    $conn->query("UPDATE users SET upi_id = '" . $conn->real_escape_string($upi_id) . "' WHERE id = {$curr_uid}");
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
