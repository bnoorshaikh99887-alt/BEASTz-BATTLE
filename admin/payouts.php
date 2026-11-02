<?php
session_start();
require_once __DIR__ . '/common/config.php'; // Apne config file ka path check kar lena

// Admin Check
$current_admin_user = strtolower(trim($_SESSION['admin_user'] ?? $_SESSION['username'] ?? ''));
$current_admin_id = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);
if (empty($_SESSION['is_super_admin']) && empty($_SESSION['admin_role']) && $current_admin_id === 0) {
    header("Location: login.php");
    exit;
}

$msg = "";
$msg_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $w_id = (int)$_POST['withdrawal_id'];
    $act = $_POST['action'];

    $w_stmt = $conn->prepare("SELECT w.*, u.username, u.wallet_balance FROM withdrawals w LEFT JOIN users u ON w.user_id = u.id WHERE w.id = ?");
    $w_stmt->bind_param("i", $w_id);
    $w_stmt->execute();
    $w = $w_stmt->get_result()->fetch_assoc();
    $w_stmt->close();

    if ($w && (strtolower($w['status']) === 'pending')) {
        $user_id = (int)$w['user_id'];
        $amount = (float)$w['amount'];
        $upi_id = $w['upi_id'] ?? '';
        $is_host_request = (int)($w['is_admin_request'] ?? 0);
        $host_admin_id = (int)($w['admin_id'] ?? 0);

        if ($act === 'complete') {
            $conn->begin_transaction();
            try {
                $conn->query("UPDATE withdrawals SET status = 'Completed' WHERE id = $w_id");
                $safe_upi = $conn->real_escape_string($upi_id);
                $conn->query("UPDATE transactions SET description = 'Withdrawal Processed: Sent to {$safe_upi}' WHERE user_id = {$user_id} AND description LIKE 'Withdrawal Request (Pending%' AND amount = {$amount}");
                $conn->commit();
                
                $msg = "Payout of ₹{$amount} to {$upi_id} marked as Completed!";
                $msg_type = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $msg = "Error: " . $e->getMessage();
                $msg_type = "error";
            }
        } elseif ($act === 'reject') {
            $conn->begin_transaction();
            try {
                if ($is_host_request && $host_admin_id > 0) {
                    $conn->query("UPDATE admin SET wallet_balance = wallet_balance + {$amount} WHERE id = {$host_admin_id}");
                } else {
                    $ref = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                    $ref->bind_param("di", $amount, $user_id);
                    $ref->execute();
                    $ref->close();
                    $conn->query("UPDATE transactions SET description = 'Withdrawal Request (Rejected)' WHERE user_id = {$user_id} AND description LIKE 'Withdrawal Request (Pending%' AND amount = {$amount}");
                }
                $conn->query("UPDATE withdrawals SET status = 'Rejected' WHERE id = $w_id");
                $conn->commit();
                $msg = "Withdrawal rejected and ₹{$amount} refunded.";
                $msg_type = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $msg = "Error: " . $e->getMessage();
                $msg_type = "error";
            }
        }
    }
}

$withdrawals = $conn->query("
    SELECT w.*, COALESCE(u.username, 'Host Admin') as username, COALESCE(u.wallet_balance, 0.00) as wallet_balance 
    FROM withdrawals w 
    LEFT JOIN users u ON w.user_id = u.id 
    ORDER BY CASE WHEN LOWER(w.status) = 'pending' THEN 0 ELSE 1 END, w.id DESC
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
</head>
<body class="bg-slate-950 text-slate-100 p-4 max-w-md mx-auto">
    <div class="flex items-center justify-between mb-4">
        <a href="index.php" class="px-3 py-1.5 bg-slate-800 text-xs font-bold rounded-xl"><i class="fa-solid fa-arrow-left mr-1"></i> Back</a>
        <h2 class="text-sm font-black text-yellow-400">Payouts Studio</h2>
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
                            <span class="block text-[10px] text-slate-400">Ref #<?= $w['id']; ?> &bull; <?= $w['created_at']; ?></span>
                        </div>
                        <span class="text-base font-mono font-black text-emerald-400">₹<?= $w['amount']; ?></span>
                    </div>

                    <div class="bg-slate-900 p-2.5 rounded-xl text-xs flex justify-between items-center">
                        <span class="font-mono font-bold text-yellow-300"><?= htmlspecialchars($w['upi_id']); ?></span>
                        <a href="upi://pay?pa=<?= urlencode($w['upi_id']); ?>&am=<?= $w['amount']; ?>&cu=INR" class="px-2.5 py-1 bg-yellow-400 text-slate-950 font-black rounded-lg text-[10px]">Pay UPI</a>
                    </div>

                    <?php if ($is_pending): ?>
                        <div class="flex gap-2 pt-1">
                            <form method="POST" action="payouts.php" class="flex-1">
                                <input type="hidden" name="action" value="complete">
                                <input type="hidden" name="withdrawal_id" value="<?= $w['id']; ?>">
                                <button type="submit" class="w-full py-2 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-xl text-xs">
                                    Approve Payout
                                </button>
                            </form>
                            <form method="POST" action="payouts.php">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="withdrawal_id" value="<?= $w['id']; ?>">
                                <button type="submit" class="py-2 px-3 bg-slate-800 text-rose-400 font-bold rounded-xl text-xs">
                                    Reject
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-[11px] font-bold <?= $is_completed ? 'text-emerald-400' : 'text-rose-400' ?>">
                            Status: <?= ucfirst($w['status']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-center text-slate-500 text-xs">No payouts found.</p>
        <?php endif; ?>
    </div>
</body>
</html>
