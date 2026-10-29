<?php
// Cashfree PG Order Status Return Handler
ob_start();
require_once __DIR__ . '/common/config.php';
check_user_auth();

$user_id = (int)$_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? sanitize($_GET['order_id']) : '';

if (empty($order_id)) {
    safe_redirect("wallet.php?info=" . urlencode("Returned from payment gateway."));
    exit;
}

$app_id = get_cashfree_app_id();
$secret_key = get_cashfree_secret_key();
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

$order = json_decode($response, true);
$order_status = $order['order_status'] ?? 'UNKNOWN';

if ($order_status === 'PAID') {
    $paid_amount = (float)$order['order_amount'];

    // 1. Double Credit Prevention
    $chk = $conn->prepare("SELECT id FROM transactions WHERE description LIKE ?");
    $search_desc = "%" . $order_id . "%";
    $chk->bind_param("s", $search_desc);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $chk->close();
        safe_redirect("wallet.php?success=" . urlencode("Payment already processed and credited!"));
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
        $dep = $conn->prepare("INSERT INTO deposits (user_id, order_id, transaction_id, amount, status) VALUES (?, ?, ?, ?, 'SUCCESS')");
        if ($dep) {
            $dep->bind_param("issd", $user_id, $order_id, $order_id, $paid_amount);
            $dep->execute();
            $dep->close();
        }

        $conn->commit();
        safe_redirect("wallet.php?success=" . urlencode("🎉 " . format_inr($paid_amount) . " successfully added to your BEASTzBATTLE wallet!"));
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        safe_redirect("wallet.php?error=" . urlencode("Wallet update error: " . $e->getMessage()));
        exit;
    }
} elseif ($order_status === 'ACTIVE') {
    // Order was created but payment was cancelled, abandoned, or pending in UPI app
    safe_redirect("wallet.php?info=" . urlencode("Payment session closed or pending. If money was debited from your bank, it will auto-reflect in your wallet shortly."));
    exit;
} else {
    safe_redirect("wallet.php?error=" . urlencode("Payment status: " . $order_status . ". You can retry or use Direct UPI QR."));
    exit;
}
?>
