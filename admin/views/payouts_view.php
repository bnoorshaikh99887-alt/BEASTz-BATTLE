<?php


$msg = "";
$msg_type = "";

// Handle Withdrawal Completion / Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $w_id = (int)$_POST['withdrawal_id'];
    $act = $_POST['action'];

    $w_stmt = $conn->prepare("SELECT w.*, u.username, u.wallet_balance FROM withdrawals w INNER JOIN users u ON w.user_id = u.id WHERE w.id = ?");
    $w_stmt->bind_param("i", $w_id);
    $w_stmt->execute();
    $w = $w_stmt->get_result()->fetch_assoc();
    $w_stmt->close();

    if ($w && (strtolower($w['status']) === 'pending')) {
        $user_id = (int)$w['user_id'];
        $amount = (float)$w['amount'];

        if ($act === 'complete') {
            $conn->begin_transaction();
            try {
                // Mark completed (wallet was already held in escrow on request)
                $conn->query("UPDATE withdrawals SET status = 'Completed' WHERE id = $w_id");
                $conn->commit();
                $msg = "Payout of " . format_inr($amount) . " to @" . htmlspecialchars($w['username']) . " marked as Completed!";
                $msg_type = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $msg = "Payout processing error: " . $e->getMessage();
                $msg_type = "error";
            }
        } elseif ($act === 'reject') {
            $conn->begin_transaction();
            try {
                // Refund held amount back to user's wallet
                $ref = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                $ref->bind_param("di", $amount, $user_id);
                $ref->execute();
                $ref->close();

                // Record refund transaction
                $desc = "Withdrawal Rejected Refund (Ref #" . $w_id . ")";
                $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
                $tx->bind_param("ids", $user_id, $amount, $desc);
                $tx->execute();
                $tx->close();

                $conn->query("UPDATE withdrawals SET status = 'Rejected' WHERE id = $w_id");
                $conn->commit();
                $msg = "Withdrawal request rejected and " . format_inr($amount) . " refunded back to @" . htmlspecialchars($w['username']) . "'s wallet.";
                $msg_type = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $msg = "Rejection error: " . $e->getMessage();
                $msg_type = "error";
            }
        }
    }
}

// Fetch Withdrawals
$withdrawals = $conn->query("
    SELECT w.*, u.username, u.wallet_balance 
    FROM withdrawals w 
    INNER JOIN users u ON w.user_id = u.id 
    ORDER BY w.id DESC
");
?>

<div class="mb-5 flex items-center justify-between">
    <div>
        <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
            <i class="fa-solid fa-hand-holding-dollar text-yellow-500"></i> UPI Withdrawal Requests
        </h2>
        <p class="text-xs text-slate-500">Send money to player UPI ID and mark payout completed</p>
    </div>
    <span class="text-xs font-bold px-3 py-1 bg-yellow-100 text-yellow-900 border border-yellow-300 rounded-full">
        Payouts
    </span>
</div>

<?php if (!empty($msg)): ?>
    <div class="mb-5 p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2.5 <?= ($msg_type === 'success') ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' ?>">
        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?> text-sm"></i>
        <span><?= htmlspecialchars($msg); ?></span>
    </div>
<?php endif; ?>

<div class="space-y-3">
    <?php if ($withdrawals && $withdrawals->num_rows > 0): ?>
        <?php while ($w = $withdrawals->fetch_assoc()): ?>
            <?php 
                $is_pending = (strtolower($w['status']) === 'pending'); 
                $is_completed = (strtolower($w['status']) === 'completed');
            ?>
            <div class="bg-white border <?= $is_pending ? 'border-yellow-400 ring-2 ring-yellow-400/20' : 'border-slate-200' ?> rounded-2xl p-4 shadow-xs space-y-3 transition">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-extrabold text-slate-900">@<?= htmlspecialchars($w['username']); ?></span>
                            <?php if ($is_pending): ?>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-300">
                                    Pending Payment
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="text-[11px] text-slate-500 block mt-0.5">
                            <i class="fa-regular fa-clock mr-1 text-slate-400"></i><?= date("d M Y, h:i A", strtotime($w['created_at'])); ?> &bull; Current Wallet: <span class="font-bold text-slate-700"><?= format_compact_inr($w['wallet_balance']); ?></span>
                        </span>
                    </div>
                    <div class="text-right">
                        <span class="text-base font-mono font-black text-slate-900 block"><?= format_compact_inr($w['amount']); ?></span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block mt-0.5 <?= $is_completed ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : (($w['status'] === 'Rejected') ? 'bg-rose-100 text-rose-800 border border-rose-200' : 'bg-amber-100 text-amber-800 border border-amber-200') ?>">
                            <?= htmlspecialchars($w['status']); ?>
                        </span>
                    </div>
                </div>

                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-xs flex items-center justify-between">
                    <div>
                        <span class="text-[11px] text-slate-500 font-semibold block mb-0.5">Transfer To UPI ID:</span>
                        <span class="font-mono text-slate-900 font-black select-all text-sm tracking-wide"><?= htmlspecialchars($w['upi_id']); ?></span>
                    </div>
                    <a href="upi://pay?pa=<?= urlencode($w['upi_id']); ?>&pn=<?= urlencode($w['username']); ?>&am=<?= $w['amount']; ?>&cu=INR" class="px-2.5 py-1 bg-white border border-slate-300 hover:border-slate-400 rounded-lg text-[11px] font-bold text-slate-700 shadow-xs flex items-center gap-1">
                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i> Open UPI
                    </a>
                </div>

                <?php if ($is_pending): ?>
                    <div class="flex items-center gap-2 pt-1">
                        <form method="POST" action="withdrawals.php" class="flex-1" onsubmit="return confirm('Have you transferred <?= format_inr($w['amount']); ?> to <?= htmlspecialchars($w['upi_id']); ?>? Click OK to mark completed.');">
                            <input type="hidden" name="action" value="complete">
                            <input type="hidden" name="withdrawal_id" value="<?= $w['id']; ?>">
                            <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center justify-center gap-1.5 active:scale-95">
                                <i class="fa-solid fa-check"></i> Mark Paid & Completed
                            </button>
                        </form>
                        <form method="POST" action="withdrawals.php" onsubmit="return confirm('Are you sure you want to reject this withdrawal request? The money will be refunded back to the user.');">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="withdrawal_id" value="<?= $w['id']; ?>">
                            <button type="submit" class="py-2 px-3.5 bg-slate-100 hover:bg-rose-50 text-rose-600 hover:text-rose-700 border border-slate-200 hover:border-rose-200 rounded-xl text-xs font-bold transition active:scale-95 flex items-center gap-1">
                                <i class="fa-solid fa-xmark"></i> Reject & Refund
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-8 text-center text-slate-500 shadow-xs">
            <i class="fa-solid fa-hand-holding-dollar text-3xl text-slate-300 mb-2 block"></i>
            <p class="text-xs font-bold">No withdrawal requests found.</p>
        </div>
    <?php endif; ?>
</div>

<?php  ?>