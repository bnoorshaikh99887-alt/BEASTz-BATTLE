<?php
/**
 * payouts.php (FIXED)
 *
 * Fixes:
 *  - Pehle koi bhi logged-in PLAYER (user_id) payouts approve/reject kar sakta tha -> ab sirf admin
 *  - Double click / 2 tabs se refund 2 baar jata tha -> ab FOR UPDATE + atomic status check
 *  - Refresh par POST dobara chalta tha -> ab redirect (PRG)
 *  - SQL string se build hoti thi -> ab prepared statements
 *  - Output escape, LIMIT 200 (bada table freeze karta tha), submit lock
 */
require_once __DIR__ . '/../common/config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$is_admin = !empty($_SESSION['admin_id']) || (($_SESSION['role'] ?? '') === 'admin')
         || !empty($_SESSION['is_super_admin']) || !empty($_SESSION['admin_role']);
if (!$is_admin) {
    safe_redirect("../login.php");
    exit;
}

$msg = $_SESSION['payout_flash']['msg'] ?? "";
$msg_type = $_SESSION['payout_flash']['type'] ?? "";
unset($_SESSION['payout_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $w_id = (int)($_POST['withdrawal_id'] ?? 0);
    $act = $_POST['action'];

    if ($w_id > 0 && in_array($act, ['complete', 'reject'], true)) {
        $conn->begin_transaction();
        try {
            // Row lock: dusri request ko wait karna padega
            $st = $conn->prepare("SELECT * FROM withdrawals WHERE id = ? FOR UPDATE");
            $st->bind_param("i", $w_id);
            $st->execute();
            $w = $st->get_result()->fetch_assoc();
            $st->close();

            if (!$w) throw new Exception("Withdrawal not found.");
            if (strtolower($w['status']) !== 'pending') throw new Exception("Ye request pehle hi process ho chuki hai.");

            $user_id = (int)$w['user_id'];
            $amount = (float)$w['amount'];
            $upi_id = (string)($w['upi_id'] ?? '');
            $is_host_request = (int)($w['is_admin_request'] ?? 0);
            $host_admin_id = (int)($w['admin_id'] ?? 0);

            $new_status = ($act === 'complete') ? 'Completed' : 'Rejected';
            $up = $conn->prepare("UPDATE withdrawals SET status = ? WHERE id = ? AND LOWER(status) = 'pending'");
            $up->bind_param("si", $new_status, $w_id);
            $up->execute();
            if ($up->affected_rows !== 1) { $up->close(); throw new Exception("Status update failed (already processed)."); }
            $up->close();

            if ($act === 'complete') {
                $d = "Withdrawal Processed: Sent to " . $upi_id;
                $tx = $conn->prepare("UPDATE transactions SET description = ? WHERE user_id = ? AND description LIKE 'Withdrawal Request (Pending%' AND amount = ?");
                $tx->bind_param("sid", $d, $user_id, $amount);
                $tx->execute();
                $tx->close();
                $msg = "Payout of ₹{$amount} to {$upi_id} marked as Completed!";
            } else {
                if ($is_host_request && $host_admin_id > 0) {
                    $rf = $conn->prepare("UPDATE admin SET wallet_balance = wallet_balance + ? WHERE id = ?");
                    $rf->bind_param("di", $amount, $host_admin_id);
                    $rf->execute();
                    $rf->close();
                } else {
                    $rf = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                    $rf->bind_param("di", $amount, $user_id);
                    $rf->execute();
                    $rf->close();
                    $d = "Withdrawal Request (Rejected)";
                    $tx = $conn->prepare("UPDATE transactions SET description = ? WHERE user_id = ? AND description LIKE 'Withdrawal Request (Pending%' AND amount = ?");
                    $tx->bind_param("sid", $d, $user_id, $amount);
                    $tx->execute();
                    $tx->close();
                }
                $msg = "Withdrawal rejected and ₹{$amount} refunded.";
            }
            $conn->commit();
            $msg_type = "success";
        } catch (Throwable $e) {
            $conn->rollback();
            $msg = "Error: " . $e->getMessage();
            $msg_type = "error";
        }
    }

    $_SESSION['payout_flash'] = ['msg' => $msg, 'type' => $msg_type];
    header("Location: payouts.php");
    exit;
}

// Session lock chhod do: baaki requests (API) ab block nahi hongi
session_write_close();

$withdrawals = $conn->query("
    SELECT w.*, COALESCE(u.username, 'Host Admin') as username, COALESCE(u.wallet_balance, 0.00) as wallet_balance
    FROM withdrawals w
    LEFT JOIN users u ON w.user_id = u.id
    ORDER BY CASE WHEN LOWER(w.status) = 'pending' THEN 0 ELSE 1 END, w.id DESC
    LIMIT 200
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payouts Studio - BEASTz Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>a,button,[role=button]{touch-action:manipulation;-webkit-tap-highlight-color:transparent}</style>
</head>
<body class="bg-slate-950 text-slate-100 p-4 max-w-md mx-auto">
    <div class="flex items-center justify-between mb-4">
        <a href="index.php" class="px-3 py-1.5 bg-slate-800 text-xs font-bold rounded-xl"><i class="fa-solid fa-arrow-left mr-1"></i> Back</a>
        <h2 class="text-sm font-black text-sky-400">Payouts Studio</h2>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="mb-4 p-3 rounded-xl text-xs font-bold <?= ($msg_type === 'success') ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30' ?>">
            <?= htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="space-y-3">
        <?php if ($withdrawals && $withdrawals->num_rows > 0): ?>
            <?php while ($w = $withdrawals->fetch_assoc()): ?>
                <?php
                    $is_pending = (strtolower($w['status']) === 'pending');
                    $is_completed = (strtolower($w['status']) === 'completed');
                ?>
                <div class="bg-[#131b2e] border <?= $is_pending ? 'border-amber-400' : 'border-slate-800' ?> rounded-2xl p-4 space-y-3">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="text-sm font-extrabold text-white">@<?= htmlspecialchars($w['username']); ?></span>
                            <span class="block text-[10px] text-slate-400">Ref #<?= (int)$w['id']; ?> &bull; <?= htmlspecialchars((string)$w['created_at']); ?></span>
                        </div>
                        <span class="text-base font-mono font-black text-emerald-400">₹<?= htmlspecialchars((string)$w['amount']); ?></span>
                    </div>

                    <div class="bg-slate-900 p-2.5 rounded-xl text-xs flex justify-between items-center">
                        <span class="font-mono font-bold text-yellow-300"><?= htmlspecialchars((string)$w['upi_id']); ?></span>
                        <a href="upi://pay?pa=<?= urlencode((string)$w['upi_id']); ?>&am=<?= urlencode((string)$w['amount']); ?>&cu=INR" class="px-2.5 py-1 bg-blue-600 text-white font-black rounded-lg text-[10px]">Pay UPI</a>
                    </div>

                    <?php if ($is_pending): ?>
                        <div class="flex gap-2 pt-1">
                            <form method="POST" action="payouts.php" class="flex-1" onsubmit="return confirm('Payout approve karna hai?');">
                                <input type="hidden" name="action" value="complete">
                                <input type="hidden" name="withdrawal_id" value="<?= (int)$w['id']; ?>">
                                <button type="submit" class="w-full py-2 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-xl text-xs">Approve Payout</button>
                            </form>
                            <form method="POST" action="payouts.php" onsubmit="return confirm('Reject karke paisa wapas karna hai?');">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="withdrawal_id" value="<?= (int)$w['id']; ?>">
                                <button type="submit" class="py-2 px-3 bg-slate-800 text-rose-400 font-bold rounded-xl text-xs">Reject</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-[11px] font-bold <?= $is_completed ? 'text-emerald-400' : 'text-rose-400' ?>">
                            Status: <?= htmlspecialchars(ucfirst((string)$w['status'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-center text-slate-500 text-xs">No payouts found.</p>
        <?php endif; ?>
    </div>
    <script>
    // double click / late click par form 2 baar submit na ho
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (f.dataset.locked === '1') { e.preventDefault(); return; }
        setTimeout(function () {
            f.dataset.locked = '1';
            f.querySelectorAll('button[type=submit]').forEach(function (b) { b.disabled = true; b.style.opacity = '.5'; });
        }, 0);
    });
    </script>
</body>
</html>
