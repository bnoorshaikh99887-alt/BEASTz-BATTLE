<?php
require_once __DIR__ . '/common/config.php';
check_user_auth();

$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // -------------------------------------------------------------
    // 1. Handle Manual Direct UPI Deposit (12-Digit UTR Submission)
    // -------------------------------------------------------------
    if (isset($_POST['transaction_id'])) {
        $utr = sanitize($_POST['transaction_id'] ?? '');
        $deposit_amount = (float)($_POST['amount'] ?? 0.00);

        if (empty($utr) || strlen($utr) < 6) {
            safe_redirect("wallet.php?error=" . urlencode("Please enter a valid 12-digit UPI reference / UTR number."));
            exit;
        }

        if ($deposit_amount < 10.0) {
            safe_redirect("wallet.php?error=" . urlencode("Minimum deposit amount is ₹10.00."));
            exit;
        }

        // Check if this UTR has already been submitted
        $chk = $conn->prepare("SELECT id, status FROM deposits WHERE transaction_id = ? LIMIT 1");
        if ($chk) {
            $chk->bind_param("s", $utr);
            $chk->execute();
            $existing_dep = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($existing_dep) {
                safe_redirect("wallet.php?error=" . urlencode("This UPI Reference / UTR has already been submitted (" . $existing_dep['status'] . ")."));
                exit;
            }
        }

        // Insert new deposit with status 'Pending' for Admin Panel verification
        $ins = $conn->prepare("INSERT INTO deposits (user_id, amount, transaction_id, status) VALUES (?, ?, ?, 'Pending')");
        if ($ins) {
            $ins->bind_param("ids", $user_id, $deposit_amount, $utr);
            if ($ins->execute()) {
                $ins->close();
                safe_redirect("wallet.php?success=" . urlencode("Deposit request of " . format_inr($deposit_amount) . " (UTR: " . $utr . ") submitted! Admin will verify and credit your wallet shortly."));
                exit;
            } else {
                $err = $ins->error;
                $ins->close();
                safe_redirect("wallet.php?error=" . urlencode("Database error: " . $err));
                exit;
            }
        }
    }

    // -------------------------------------------------------------
    // 2. Handle Razorpay Gateway Callback
    // -------------------------------------------------------------
    $razorpay_payment_id = isset($_POST['razorpay_payment_id']) ? sanitize($_POST['razorpay_payment_id']) : '';
    $deposit_amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.00;

    if (empty($razorpay_payment_id) || $deposit_amount <= 0) {
        safe_redirect("wallet.php?error=" . urlencode("Payment was cancelled or invalid response received."));
        exit;
    }

    // Double Spending Prevention
    $chk = $conn->prepare("SELECT id FROM transactions WHERE description LIKE ?");
    $search_pattern = "%" . $razorpay_payment_id . "%";
    $chk->bind_param("s", $search_pattern);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $chk->close();
        safe_redirect("wallet.php?error=" . urlencode("This payment has already been credited to your wallet."));
        exit;
    }
    $chk->close();

    // Credit User Wallet
    $conn->begin_transaction();
    try {
        $upd = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $upd->bind_param("di", $deposit_amount, $user_id);
        $upd->execute();
        $upd->close();

        $tx_desc = "Instant Gateway Deposit (ID: " . $razorpay_payment_id . ")";
        $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
        $tx->bind_param("ids", $user_id, $deposit_amount, $tx_desc);
        $tx->execute();
        $tx->close();

        $dep = $conn->prepare("INSERT INTO deposits (user_id, amount, transaction_id, status) VALUES (?, ?, ?, 'Completed')");
        $dep->bind_param("ids", $user_id, $deposit_amount, $razorpay_payment_id);
        $dep->execute();
        $dep->close();

        $conn->commit();
        safe_redirect("wallet.php?success=" . urlencode("Payment of " . format_inr($deposit_amount) . " successfully added to your wallet!"));
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        safe_redirect("wallet.php?error=" . urlencode("Database error processing payment: " . $e->getMessage()));
        exit;
    }
} else {
    safe_redirect("wallet.php");
    exit;
}
