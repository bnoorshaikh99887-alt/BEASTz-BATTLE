<?php
require_once __DIR__ . '/common/config.php';
check_user_auth();

$user_id = (int)$_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? sanitize($_GET['order_id']) : '';

if (empty($order_id)) {
    header("Location: wallet.php?error=" . urlencode("Invalid Cashfree order response."));
    exit;
}

$app_id = '1494190b594e98ed762a2a943320914941';
$secret_key = 'cfsk_ma_prod_979295dd86c34ea7eef3d56c1e07d25e_27097462';

$endpoint = "https://api.cashfree.com/pg/orders/" . urlencode($order_id);

// Check order status from Cashfree Production Server
$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "x-api-version: 2023-08-01",
    "x-client-id: " . $app_id,
    "x-client-secret: " . $secret_key
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
curl_close($ch);

$order = json_decode($response, true);

if (isset($order['order_status']) && $order['order_status'] === 'PAID') {
    $paid_amount = (float)$order['order_amount'];

    // 1. Double Credit Prevention
    $chk = $conn->prepare("SELECT id FROM transactions WHERE description LIKE ?");
    $search_desc = "%" . $order_id . "%";
    $chk->bind_param("s", $search_desc);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        header("Location: wallet.php?success=" . urlencode("Payment already processed and credited!"));
        exit;
    }
    $chk->close();

    // 2. Atomic Wallet Credit
    $conn->begin_transaction();
    try {
        // Update user balance
        $upd = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $upd->bind_param("di", $paid_amount, $user_id);
        $upd->execute();
        $upd->close();

        // Transaction passbook entry
        $desc = "Cashfree Deposit (" . $order_id . ")";
        $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
        $tx->bind_param("ids", $user_id, $paid_amount, $desc);
        $tx->execute();
        $tx->close();

        // Deposits record entry
        $dep = $conn->prepare("INSERT INTO deposits (user_id, amount, transaction_id, status) VALUES (?, ?, ?, 'Completed')");
        $dep->bind_param("ids", $user_id, $paid_amount, $order_id);
        $dep->execute();
        $dep->close();

        $conn->commit();
        header("Location: wallet.php?success=" . urlencode("🎉 " . format_inr($paid_amount) . " successfully added to your wallet!"));
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: wallet.php?error=" . urlencode("Wallet update error: " . $e->getMessage()));
        exit;
    }
} else {
    $status = $order['order_status'] ?? 'FAILED';
    header("Location: wallet.php?error=" . urlencode("Cashfree Payment Status: " . $status));
    exit;
}
?>