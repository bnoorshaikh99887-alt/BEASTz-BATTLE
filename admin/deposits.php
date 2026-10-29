<?php
require_once __DIR__ . '/common/header.php';

$msg = "";
$msg_type = "";

// Handle Approve / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $dep_id = (int)$_POST['deposit_id'];
    $act = $_POST['action'];

    $d_stmt = $conn->prepare("SELECT d.*, u.username FROM deposits d INNER JOIN users u ON d.user_id = u.id WHERE d.id = ?");
    $d_stmt->bind_param("i", $dep_id);
    $d_stmt->execute();
    $dep = $d_stmt->get_result()->fetch_assoc();
    $d_stmt->close();

    if ($dep && $dep['status'] === 'Pending') {
        if ($act === 'approve') {
            $user_id = (int)$dep['user_id'];
            $amount = (float)$dep['amount'];

            $conn->begin_transaction();
            try {
                // 1. Credit wallet
                $upd = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                $upd->bind_param("di", $amount, $user_id);
                $upd->execute();

                // 2. Transaction entry
                $t_desc = "UPI Deposit Verified (Txn: " . $dep['transaction_id'] . ")";
                $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
                $tx->bind_param("ids", $user_id, $amount, $t_desc);
                $tx->execute();

                // 3. Mark completed
                $conn->query("UPDATE deposits SET status = 'Completed' WHERE id = $dep_id");

                $conn->commit();
                $msg = "Deposit of " . format_inr($amount) . " for @" . $dep['username'] . " approved & credited!";
                $msg_type = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $msg = "Approval failed: " . $e->getMessage();
                $msg_type = "error";
            }
        } elseif ($act === 'reject') {
            $conn->query("UPDATE deposits SET status = 'Rejected' WHERE id = $dep_id");
            $msg = "Deposit request rejected.";
            $msg_type = "success";
        }
    }
}

// Fetch Deposits
$deposits = $conn->query("
    SELECT d.*, u.username, u.email 
    FROM deposits d 
    INNER JOIN users u ON d.user_id = u.id 
    ORDER BY d.id DESC");
?>

<div class="mb-4">
    <h2 class="text-base font-bold text-white">UPI Deposit Requests</h2>
    <p class="text-[11px] text-slate-400">Verify 12-digit UTR and credit user wallets</p>
</div>

<?php if (!empty($msg)): ?>
    <div class="mb-4 p-3 rounded-xl text-xs font-semibold flex items-center gap-2 <?= ($msg_type === 'success') ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' ?>">
        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400' ?>"></i>
        <span><?= $msg; ?></span>
    </div>
<?php endif; ?>

<div class="space-y-3">
    <?php if ($deposits && $deposits->num_rows > 0): ?>
        <?php while ($d = $deposits->fetch_assoc()): ?>
            <?php $is_pending = ($d['status'] === 'Pending'); ?>
            <div class="bg-slate-800/80 border <?= $is_pending ? 'border-amber-500/40 bg-slate-800' : 'border-slate-700/60' ?> rounded-2xl p-3.5 shadow-md space-y-2">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-xs font-bold text-white block">@<?= htmlspecialchars($d['username']); ?></span>
                        <span class="text-[10px] text-slate-400"><?= date("d M Y, h:i A", strtotime($d['created_at'])); ?></span>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-mono font-bold text-emerald-400 block"><?= format_inr($d['amount']); ?></span>
                        <span class="text-[9px] font-bold px-1.5 py-0.2 rounded <?= ($d['status'] === 'Completed') ? 'bg-emerald-500/20 text-emerald-400' : (($d['status'] === 'Rejected') ? 'bg-rose-500/20 text-rose-400' : 'bg-amber-500/20 text-amber-400') ?>">
                            <?= $d['status']; ?>
                        </span>
                    </div>
                </div>

                <div class="bg-slate-900/60 p-2 rounded-xl border border-slate-800 text-[10px]">
                    <span class="text-slate-400 block">UPI Transaction / UTR ID:</span>
                    <span class="font-mono text-violet-300 font-bold select-all text-xs"><?= htmlspecialchars($d['transaction_id']); ?></span>
                </div>

                <?php if ($is_pending): ?>
                    <div class="flex items-center gap-2 pt-1">
                        <form method="POST" action="deposits.php" class="flex-1" onsubmit="return confirm('Confirm approve deposit and credit user balance?');">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="deposit_id" value="<?= $d['id']; ?>">
                            <button type="submit" class="w-full py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition shadow-sm">
                                <i class="fa-solid fa-check mr-1"></i> Approve & Credit
                            </button>
                        </form>

                        <form method="POST" action="deposits.php" onsubmit="return confirm('Reject this deposit request?');">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="deposit_id" value="<?= $d['id']; ?>">
                            <button type="submit" class="py-1.5 px-3 bg-rose-600/20 hover:bg-rose-600/30 text-rose-400 border border-rose-500/30 rounded-lg text-xs font-bold transition">
                                <i class="fa-solid fa-xmark"></i> Reject
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="text-xs text-slate-500 text-center py-4">No deposit requests logged.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
