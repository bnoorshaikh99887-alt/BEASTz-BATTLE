<?php
// Cashfree PG Order Status Return Handler (Atomic, Double-Credit Proof via cashfree_orders)
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
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 12);
$response = curl_exec($ch);
curl_close($ch);

$order = json_decode($response, true);
$order_status = $order['order_status'] ?? 'UNKNOWN';

if ($order_status === 'PAID') {
    $paid_amount = (float)($order['order_amount'] ?? 0.00);
    $cf_payment_id = (string)($order['cf_order_id'] ?? $order_id);

    // 1. ATOMIC WALLET CREDIT USING DATABASE TRANSACTION & ROW LOCKING
    $conn->begin_transaction();
    try {
        // Ensure cashfree_orders table exists
        @$conn->query("CREATE TABLE IF NOT EXISTS `cashfree_orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` VARCHAR(100) NOT NULL UNIQUE,
            `user_id` INT NOT NULL,
            `amount` DECIMAL(10, 2) NOT NULL,
            `status` ENUM('PENDING', 'PAID', 'FAILED', 'USER_DROPPED') NOT NULL DEFAULT 'PENDING',
            `payment_session_id` VARCHAR(255) NULL,
            `cf_payment_id` VARCHAR(100) NULL,
            `raw_response` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_user` (`user_id`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Lock order row to prevent duplicate credits or race conditions
        $chk_order = $conn->prepare("SELECT id, status, user_id, amount FROM cashfree_orders WHERE order_id = ? FOR UPDATE");
        $chk_order->bind_param("s", $order_id);
        $chk_order->execute();
        $cf_order_res = $chk_order->get_result()->fetch_assoc();
        $chk_order->close();

        // If order already marked as PAID, exit cleanly
        if ($cf_order_res && $cf_order_res['status'] === 'PAID') {
            $conn->commit();
            safe_redirect("wallet.php?success=" . urlencode("Payment already processed and credited to your wallet!"));
            exit;
        }

        // Secondary check: deposits table
        $chk_dep = $conn->prepare("SELECT id FROM deposits WHERE order_id = ? AND status = 'SUCCESS' LIMIT 1");
        $chk_dep->bind_param("s", $order_id);
        $chk_dep->execute();
        if ($chk_dep->get_result()->num_rows > 0) {
            $chk_dep->close();
            $conn->commit();
            safe_redirect("wallet.php?success=" . urlencode("Payment already processed and credited!"));
            exit;
        }
        $chk_dep->close();

        // Update or insert order row with status = PAID
        $raw_json = json_encode($order);
        if ($cf_order_res) {
            $upd_order = $conn->prepare("UPDATE cashfree_orders SET status = 'PAID', cf_payment_id = ?, raw_response = ? WHERE order_id = ?");
            $upd_order->bind_param("sss", $cf_payment_id, $raw_json, $order_id);
            $upd_order->execute();
            $upd_order->close();
        } else {
            $ins_order = $conn->prepare("INSERT INTO cashfree_orders (order_id, user_id, amount, status, cf_payment_id, raw_response) VALUES (?, ?, ?, 'PAID', ?, ?)");
            $ins_order->bind_param("sidss", $order_id, $user_id, $paid_amount, $cf_payment_id, $raw_json);
            $ins_order->execute();
            $ins_order->close();
        }

        // Increment user balance atomically
        $upd_bal = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $upd_bal->bind_param("di", $paid_amount, $user_id);
        $upd_bal->execute();
        $upd_bal->close();

        // Transaction passbook ledger entry
        $desc = "Cashfree Deposit (" . $order_id . ")";
        $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
        $tx->bind_param("ids", $user_id, $paid_amount, $desc);
        $tx->execute();
        $tx->close();

        // Deposits record entry
        $dep = $conn->prepare("INSERT INTO deposits (user_id, order_id, transaction_id, amount, status, method) VALUES (?, ?, ?, ?, 'SUCCESS', 'Cashfree') ON DUPLICATE KEY UPDATE status = 'SUCCESS'");
        if ($dep) {
            $dep->bind_param("issd", $user_id, $order_id, $cf_payment_id, $paid_amount);
            $dep->execute();
            $dep->close();
        }

        $conn->commit();
        safe_redirect("wallet.php?success=" . urlencode("🎉 " . format_inr($paid_amount) . " successfully added to your BEASTzBATTLE wallet!"));
        exit;
    } catch (Throwable $e) {
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
