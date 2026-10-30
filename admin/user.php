<?php
require_once __DIR__ . '/common/header.php';

$msg = "";
$msg_type = "";

// Handle Block / Unblock User Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $uid = (int)($_POST['user_id'] ?? 0);
    $new_status = ($_POST['new_status'] === 'blocked') ? 'blocked' : 'active';

    if ($uid <= 0) {
        $msg = "Invalid user specified.";
        $msg_type = "error";
    } else {
        // Safety check: Never suspend an admin account!
        $chk_adm = $conn->prepare("SELECT role, username FROM users WHERE id = ? LIMIT 1");
        if ($chk_adm) {
            $chk_adm->bind_param("i", $uid);
            $chk_adm->execute();
            $target_user = $chk_adm->get_result()->fetch_assoc();
            $chk_adm->close();

            if (!$target_user) {
                $msg = "User record not found.";
                $msg_type = "error";
            } elseif (($target_user['role'] ?? '') === 'admin') {
                $msg = "Security Alert: Admin accounts cannot be suspended or blocked.";
                $msg_type = "error";
            } else {
                $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND (role IS NULL OR role != 'admin')");
                if ($stmt) {
                    $stmt->bind_param("si", $new_status, $uid);
                    if ($stmt->execute()) {
                        $msg = "User @" . htmlspecialchars($target_user['username']) . " status updated to " . ucfirst($new_status) . ".";
                        $msg_type = "success";
                    } else {
                        $msg = "Failed to update user status: " . $stmt->error;
                        $msg_type = "error";
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Fetch all Users
$users = $conn->query("
    SELECT u.id, u.username, u.email, u.phone, u.wallet_balance, u.upi_id, u.role, u.status,
    (SELECT COUNT(*) FROM participants WHERE user_id = u.id) as total_tourneys
    FROM users u 
    ORDER BY CASE WHEN u.role = 'admin' THEN 1 ELSE 2 END, u.id DESC
");
?>

<div class="mb-5 flex items-center justify-between">
    <div>
        <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
            <i class="fa-solid fa-users-gear text-yellow-500"></i> User Management
        </h2>
        <p class="text-xs text-slate-500">Registered platform gamers, balances, and access control</p>
    </div>
    <span class="text-xs font-bold px-3 py-1 bg-yellow-100 text-yellow-900 border border-yellow-300 rounded-full">
        Total: <?= ($users ? $users->num_rows : 0); ?>
    </span>
</div>

<?php if (!empty($msg)): ?>
    <div class="mb-5 p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2.5 <?= $msg_type === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'; ?>">
        <i class="fa-solid <?= $msg_type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600'; ?> text-sm"></i>
        <span><?= htmlspecialchars($msg); ?></span>
    </div>
<?php endif; ?>

<div class="space-y-3">
    <?php if ($users && $users->num_rows > 0): ?>
        <?php while ($u = $users->fetch_assoc()): ?>
            <?php 
                $is_admin_user = (($u['role'] ?? '') === 'admin');
                $is_blocked = (($u['status'] ?? 'active') === 'blocked');
            ?>
            <div class="bg-white border <?= $is_admin_user ? 'border-yellow-400 bg-yellow-50/20' : ($is_blocked ? 'border-rose-300 bg-rose-50/20' : 'border-slate-200') ?> rounded-2xl p-4 shadow-xs space-y-3 transition">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-extrabold text-slate-900">@<?= htmlspecialchars($u['username']); ?></span>
                            <?php if ($is_admin_user): ?>
                                <span class="text-[10px] bg-yellow-400 text-black px-2 py-0.5 rounded-full font-black shadow-xs flex items-center gap-1">
                                    <i class="fa-solid fa-crown text-[9px]"></i> SUPER ADMIN (IMMUNE)
                                </span>
                            <?php elseif ($is_blocked): ?>
                                <span class="text-[10px] bg-rose-100 text-rose-800 border border-rose-200 px-2 py-0.5 rounded-full font-bold">
                                    SUSPENDED
                                </span>
                            <?php else: ?>
                                <span class="text-[10px] bg-emerald-100 text-emerald-800 border border-emerald-200 px-2 py-0.5 rounded-full font-bold">
                                    ACTIVE
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">
                            <?= !empty($u['email']) ? htmlspecialchars($u['email']) : 'No email registered'; ?> &bull; 
                            <?= !empty($u['phone']) ? htmlspecialchars($u['phone']) : 'No phone'; ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] text-slate-400 block font-semibold uppercase">Wallet</span>
                        <span class="text-base font-mono font-black text-slate-900"><?= format_inr($u['wallet_balance']); ?></span>
                    </div>
                </div>

                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-xs flex justify-between items-center">
                    <div>
                        <span class="text-[11px] text-slate-500 font-semibold block mb-0.5">Payout UPI ID:</span>
                        <span class="font-mono text-slate-800 font-bold"><?= !empty($u['upi_id']) ? htmlspecialchars($u['upi_id']) : 'Not configured'; ?></span>
                    </div>
                    <div class="text-right">
                        <span class="text-[11px] text-slate-500 font-semibold block mb-0.5">Tournaments:</span>
                        <span class="font-black text-slate-900"><?= $u['total_tourneys']; ?></span>
                    </div>
                </div>

                <!-- Block / Unblock Action -->
                <?php if ($is_admin_user): ?>
                    <div class="pt-1 text-center">
                        <span class="text-[11px] text-slate-500 font-medium">
                            <i class="fa-solid fa-shield text-yellow-600 mr-1"></i> Super Admin account is permanently active and cannot be suspended.
                        </span>
                    </div>
                <?php else: ?>
                    <form method="POST" action="user.php" class="pt-1" onsubmit="return confirm('Are you sure you want to <?= $is_blocked ? 'unblock' : 'suspend / block' ?> this user?');">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="user_id" value="<?= $u['id']; ?>">
                        <input type="hidden" name="new_status" value="<?= $is_blocked ? 'active' : 'blocked'; ?>">
                        
                        <button type="submit" class="w-full py-2 text-xs font-bold rounded-xl transition flex items-center justify-center gap-1.5 active:scale-95 <?= $is_blocked ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs' : 'bg-slate-100 hover:bg-rose-50 text-rose-600 hover:text-rose-700 border border-slate-200 hover:border-rose-200' ?>">
                            <i class="fa-solid <?= $is_blocked ? 'fa-unlock' : 'fa-ban' ?>"></i>
                            <?= $is_blocked ? 'Unblock User Account' : 'Suspend / Block User' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-8 text-center text-slate-500 shadow-xs">
            <i class="fa-solid fa-users text-3xl text-slate-300 mb-2 block"></i>
            <p class="text-xs font-bold">No users registered yet.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
