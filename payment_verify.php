<?php
require_once __DIR__ . '/common/config.php';
check_user_auth();

$user_id = (int)$_SESSION['user_id'];
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
    || isset($_POST['is_ajax']);

function return_response($success, $message, $extra = []) {
    global $is_ajax;
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
        exit;
    }
    if ($success) {
        safe_redirect("wallet.php?success=" . urlencode($message));
    } else {
        safe_redirect("wallet.php?error=" . urlencode($message));
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // -------------------------------------------------------------
    // 1. Handle Manual Direct UPI Deposit (12-Digit UTR Submission)
    // -------------------------------------------------------------
    if (isset($_POST['transaction_id'])) {
        $utr = sanitize($_POST['transaction_id'] ?? '');
        $deposit_amount = (float)($_POST['amount'] ?? 0.00);

        if (empty($utr) || strlen($utr) < 6) {
            return_response(false, "Please enter a valid 12-digit UPI reference / UTR number.");
        }

        if ($deposit_amount < 10.0) {
            return_response(false, "Minimum deposit amount is ₹10.00.");
        }

        try {
            // Check if this UTR has already been submitted
            $chk = $conn->prepare("SELECT id, status FROM deposits WHERE transaction_id = ? OR utr = ? LIMIT 1");
            if ($chk) {
                $chk->bind_param("ss", $utr, $utr);
                $chk->execute();
                $existing_dep = $chk->get_result()->fetch_assoc();
                $chk->close();

                if ($existing_dep) {
                    return_response(false, "This UPI Reference / UTR has already been submitted (" . $existing_dep['status'] . ").");
                }
            }

            // Generate unique order ID
            $unique_order_id = 'ORD_' . time() . '_' . mt_rand(1000, 9999);

            // Insert new deposit with status 'Pending' for Admin Panel verification
            $ins = $conn->prepare("INSERT INTO deposits (user_id, order_id, amount, method, status, reference_id, transaction_id, utr) VALUES (?, ?, ?, 'UPI', 'Pending', '', ?, ?)");
            if ($ins) {
                $ins->bind_param("isdss", $user_id, $unique_order_id, $deposit_amount, $utr, $utr);
                if ($ins->execute()) {
                    $new_dep_id = $ins->insert_id;
                    $ins->close();
                    return_response(true, "Deposit request of " . format_inr($deposit_amount) . " (UTR: " . $utr . ") submitted! Admin will verify and credit your wallet shortly.", [
                        'deposit_id' => $new_dep_id,
                        'amount' => $deposit_amount,
                        'amount_formatted' => format_inr($deposit_amount),
                        'utr' => $utr
                    ]);
                } else {
                    $err = $ins->error;
                    $ins->close();
                    return_response(false, "Unable to record deposit: " . $err);
                }
            } else {
                return_response(false, "Database error preparing statement.");
            }
        } catch (Throwable $t) {
            return_response(false, "Deposit submission error: " . $t->getMessage());
        }
    }

    // -------------------------------------------------------------
    // 2. Handle Razorpay Gateway Callback
    // -------------------------------------------------------------
    $razorpay_payment_id = isset($_POST['razorpay_payment_id']) ? sanitize($_POST['razorpay_payment_id']) : '';
    $deposit_amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.00;

    if (empty($razorpay_payment_id) || $deposit_amount <= 0) {
        return_response(false, "Payment was cancelled or invalid response received.");
    }

    try {
        $chk = $conn->prepare("SELECT id FROM transactions WHERE description LIKE ?");
        $search_pattern = "%" . $razorpay_payment_id . "%";
        $chk->bind_param("s", $search_pattern);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $chk->close();
            return_response(false, "This payment has already been credited to your wallet.");
        }
        $chk->close();

        $conn->begin_transaction();
        $upd = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $upd->bind_param("di", $deposit_amount, $user_id);
        $upd->execute();
        $upd->close();

        $tx_desc = "Instant Gateway Deposit (ID: " . $razorpay_payment_id . ")";
        $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
        $tx->bind_param("ids", $user_id, $deposit_amount, $tx_desc);
        $tx->execute();
        $tx->close();

        $order_id = 'RZP_' . time() . '_' . mt_rand(1000, 9999);
        $dep = $conn->prepare("INSERT INTO deposits (user_id, order_id, amount, method, status, transaction_id, utr) VALUES (?, ?, ?, 'Razorpay', 'Completed', ?, ?)");
        $dep->bind_param("isdss", $user_id, $order_id, $deposit_amount, $razorpay_payment_id, $razorpay_payment_id);
        $dep->execute();
        $dep->close();
        $conn->commit();

        return_response(true, "Payment of " . format_inr($deposit_amount) . " successfully added to your wallet!");
    } catch (Throwable $e) {
        $conn->rollback();
        return_response(false, "Database error processing payment: " . $e->getMessage());
    }
} else {
    safe_redirect("wallet.php");
    exit;
}
