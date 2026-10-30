<?php


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
                $upd->close();

                // 2. Transaction entry
                $t_desc = "UPI Deposit Verified (Txn: " . $dep['transaction_id'] . ")";
                $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
                $tx->bind_param("ids", $user_id, $amount, $t_desc);
                $tx->execute();
                $tx->close();

                // 3. Mark completed
                $conn->query("UPDATE deposits SET status = 'Completed' WHERE id = $dep_id");
                $conn->commit();

                $msg = "Deposit of " . format_inr($amount) . " for @" . htmlspecialchars($dep['username']) . " approved and credited to wallet!";
                $msg_type = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $msg = "Approval failed due to a system error: " . $e->getMessage();
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
    ORDER BY d.id DESC
");
?>

<div class="mb-5 flex items-center justify-between">
    <div>
        <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
            <i class="fa-solid fa-money-bill-transfer text-yellow-500"></i> UPI Deposit Requests
        </h2>
        <p class="text-xs text-slate-500">Verify 12-digit UPI UTR numbers and credit player wallets</p>
    </div>
    <span class="text-xs font-bold px-3 py-1 bg-yellow-100 text-yellow-900 border border-yellow-300 rounded-full">
        Manual UPI
    </span>
</div>

<?php if (!empty($msg)): ?>
    <div class="mb-5 p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2.5 <?= ($msg_type === 'success') ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' ?>">
        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?> text-sm"></i>
        <span><?= htmlspecialchars($msg); ?></span>
    </div>
<?php endif; ?>

<div class="space-y-3">
    <?php if ($deposits && $deposits->num_rows > 0): ?>
        <?php while ($d = $deposits->fetch_assoc()): ?>
            <?php $is_pending = ($d['status'] === 'Pending'); ?>
            <div class="bg-white border <?= $is_pending ? 'border-yellow-400 ring-2 ring-yellow-400/20' : 'border-slate-200' ?> rounded-2xl p-4 shadow-xs space-y-3 transition">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-extrabold text-slate-900">@<?= htmlspecialchars($d['username']); ?></span>
                            <?php if ($is_pending): ?>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-300">
                                    Action Required
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="text-[11px] text-slate-500 block mt-0.5">
                            <i class="fa-regular fa-clock mr-1 text-slate-400"></i><?= date("d M Y, h:i A", strtotime($d['created_at'])); ?>
                        </span>
                    </div>
                    <div class="text-right">
                        <span class="text-base font-mono font-black text-slate-900 block"><?= format_inr($d['amount']); ?></span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block mt-0.5 <?= ($d['status'] === 'Completed') ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : (($d['status'] === 'Rejected') ? 'bg-rose-100 text-rose-800 border border-rose-200' : 'bg-amber-100 text-amber-800 border border-amber-200') ?>">
                            <?= htmlspecialchars($d['status']); ?>
                        </span>
                    </div>
                </div>

                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-xs">
                    <span class="text-[11px] text-slate-500 font-semibold block mb-0.5">UPI Reference / 12-Digit UTR:</span>
                    <span class="font-mono text-slate-900 font-black select-all tracking-wider text-sm"><?= htmlspecialchars($d['transaction_id']); ?></span>
                </div>

                <?php if ($is_pending): ?>
                    <div class="flex items-center gap-2 pt-1">
                        <form method="POST" action="deposits.php" class="flex-1" onsubmit="return confirm('Confirm approve deposit of <?= format_inr($d['amount']); ?> and credit user wallet?');">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="deposit_id" value="<?= $d['id']; ?>">
                            <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center justify-center gap-1.5 active:scale-95">
                                <i class="fa-solid fa-check"></i> Approve & Credit Wallet
                            </button>
                        </form>
                        <form method="POST" action="deposits.php" onsubmit="return confirm('Are you sure you want to reject this deposit request?');">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="deposit_id" value="<?= $d['id']; ?>">
                            <button type="submit" class="py-2 px-3.5 bg-slate-100 hover:bg-rose-50 text-rose-600 hover:text-rose-700 border border-slate-200 hover:border-rose-200 rounded-xl text-xs font-bold transition active:scale-95 flex items-center gap-1">
                                <i class="fa-solid fa-xmark"></i> Reject
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-8 text-center text-slate-500 shadow-xs">
            <i class="fa-solid fa-receipt text-3xl text-slate-300 mb-2 block"></i>
            <p class="text-xs font-bold">No UPI deposit requests found.</p>
        </div>
    <?php endif; ?>
</div>

<?php  ?>