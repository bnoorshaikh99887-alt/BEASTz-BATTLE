<?php
require_once __DIR__ . '/common/config.php';
check_user_auth();

$user_id = (int)$_SESSION['user_id'];

// Check POST response from Razorpay
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $razorpay_payment_id = isset($_POST['razorpay_payment_id']) ? sanitize($_POST['razorpay_payment_id']) : '';
    $deposit_amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.00;
    
    // Check if payment ID is received
    if (empty($razorpay_payment_id) || $deposit_amount <= 0) {
        header("Location: wallet.php?error=" . urlencode("Payment was cancelled or invalid response received."));
        exit;
    }

    // 1. Double Spending Prevention: Check if this payment ID was already credited
    $chk = $conn->prepare("SELECT id FROM transactions WHERE description LIKE ?");
    $search_pattern = "%" . $razorpay_payment_id . "%";
    $chk->bind_param("s", $search_pattern);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        header("Location: wallet.php?error=" . urlencode("This payment has already been credited to your wallet."));
        exit;
    }
    $chk->close();

    // 2. Start Atomic Wallet Credit
    $conn->begin_transaction();
    try {
        // A. Update User Wallet
        $upd = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $upd->bind_param("di", $deposit_amount, $user_id);
        $upd->execute();
        $upd->close();

        // B. Insert into Transactions Passbook
        $tx_desc = "Instant Gateway Deposit (ID: " . $razorpay_payment_id . ")";
        $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
        $tx->bind_param("ids", $user_id, $deposit_amount, $tx_desc);
        $tx->execute();
        $tx->close();

        // C. Record into Deposits Table as 'Completed'
        $dep = $conn->prepare("INSERT INTO deposits (user_id, amount, transaction_id, status) VALUES (?, ?, ?, 'Completed')");
        $dep->bind_param("ids", $user_id, $deposit_amount, $razorpay_payment_id);
        $dep->execute();
        $dep->close();

        $conn->commit();
        header("Location: wallet.php?success=" . urlencode("Payment of " . format_inr($deposit_amount) . " successfully added to your wallet!"));
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: wallet.php?error=" . urlencode("Database error processing payment: " . $e->getMessage()));
        exit;
    }
} else {
    header("Location: wallet.php");
    exit;
}
?>