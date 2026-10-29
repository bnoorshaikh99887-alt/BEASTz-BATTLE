<?php
require_once __DIR__ . '/common/header.php';

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

    if ($w && $w['status'] === 'Pending') {
        if ($act === 'complete') {
            $user_id = (int)$w['user_id'];
            $amount = (float)$w['amount'];

            if ($w['wallet_balance'] < $amount) {
                $msg = "User balance is less than requested amount!";
                $msg_type = "error";
            } else {
                $conn->begin_transaction();
                try {
                    // Deduct wallet
                    $ded = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
                    $ded->bind_param("di", $amount, $user_id);
                    $ded->execute();

                    // Record debit transaction
                    $desc = "UPI Payout to " . $w['upi_id'];
                    $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
                    $tx->bind_param("ids", $user_id, $amount, $desc);
                    $tx->execute();

                    // Mark completed
                    $conn->query("UPDATE withdrawals SET status = 'Completed' WHERE id = $w_id");

                    $conn->commit();
                    $msg = "Withdrawal marked as Completed and " . format_inr($amount) . " debited from user wallet!";
                    $msg_type = "success";
                } catch (Exception $e) {
                    $conn->rollback();
                    $msg = "Payout processing error: " . $e->getMessage();
                    $msg_type = "error";
                }
            }
        } elseif ($act === 'reject') {
            $conn->query("UPDATE withdrawals SET status = 'Rejected' WHERE id = $w_id");
            $msg = "Withdrawal request rejected.";
            $msg_type = "success";
        }
    }
}

// Fetch Withdrawals
$withdrawals = $conn->query("
    SELECT w.*, u.username, u.wallet_balance 
    FROM withdrawals w 
    INNER JOIN users u ON w.user_id = u.id 
    ORDER BY w.id DESC");
?>

<div class="mb-4">
    <h2 class="text-base font-bold text-white">UPI Withdrawal Requests</h2>
    <p class="text-[11px] text-slate-400">Send money to user's UPI ID and mark completed</p>
</div>

<?php if (!empty($msg)): ?>
    <div class="mb-4 p-3 rounded-xl text-xs font-semibold flex items-center gap-2 <?= ($msg_type === 'success') ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' ?>">
        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400' ?>"></i>
        <span><?= $msg; ?></span>
    </div>
<?php endif; ?>

<div class="space-y-3">
    <?php if ($withdrawals && $withdrawals->num_rows > 0): ?>
        <?php while ($w = $withdrawals->fetch_assoc()): ?>
            <?php $is_pending = ($w['status'] === 'Pending'); ?>
            <div class="bg-slate-800/80 border <?= $is_pending ? 'border-amber-500/40 bg-slate-800' : 'border-slate-700/60' ?> rounded-2xl p-3.5 shadow-md space-y-2">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-xs font-bold text-white block">@<?= htmlspecialchars($w['username']); ?></span>
                        <span class="text-[10px] text-slate-400"><?= date("d M Y, h:i A", strtotime($w['created_at'])); ?></span>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-mono font-bold text-amber-400 block"><?= format_inr($w['amount']); ?></span>
                        <span class="text-[9px] font-bold px-1.5 py-0.2 rounded <?= ($w['status'] === 'Completed') ? 'bg-emerald-500/20 text-emerald-400' : (($w['status'] === 'Rejected') ? 'bg-rose-500/20 text-rose-400' : 'bg-amber-500/20 text-amber-400') ?>">
                            <?= $w['status']; ?>
                        </span>
                    </div>
                </div>

                <div class="bg-slate-900/60 p-2 rounded-xl border border-slate-800 text-[10px]">
                    <span class="text-slate-400 block">Send Payment To UPI ID:</span>
                    <span class="font-mono text-emerald-400 font-bold select-all text-xs"><?= htmlspecialchars($w['upi_id']); ?></span>
                </div>

                <?php if ($is_pending): ?>
                    <div class="flex items-center gap-2 pt-1">
                        <form method="POST" action="withdrawals.php" class="flex-1" onsubmit="return confirm('Have you manually transferred <?= format_inr($w['amount']); ?> to <?= htmlspecialchars($w['upi_id']); ?>? Click OK to debit user.');">
                            <input type="hidden" name="action" value="complete">
                            <input type="hidden" name="withdrawal_id" value="<?= $w['id']; ?>">
                            <button type="submit" class="w-full py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition shadow-sm">
                                <i class="fa-solid fa-paper-plane mr-1"></i> Mark Paid & Debit
                            </button>
                        </form>

                        <form method="POST" action="withdrawals.php" onsubmit="return confirm('Reject this withdrawal request?');">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="withdrawal_id" value="<?= $w['id']; ?>">
                            <button type="submit" class="py-1.5 px-3 bg-rose-600/20 hover:bg-rose-600/30 text-rose-400 border border-rose-500/30 rounded-lg text-xs font-bold transition">
                                <i class="fa-solid fa-xmark"></i> Reject
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="text-xs text-slate-500 text-center py-4">No withdrawal requests found.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/common/bottom.php'; ?>