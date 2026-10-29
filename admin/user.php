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

<div class="mb-4">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-users-gear text-violet-400"></i> User Management
            </h2>
            <p class="text-[11px] text-slate-400">Registered platform gamers and balances</p>
        </div>
        <span class="px-2.5 py-1 rounded-xl bg-slate-800 text-slate-300 text-[11px] font-bold border border-slate-700">
            Total: <?= ($users ? $users->num_rows : 0); ?>
        </span>
    </div>
</div>

<?php if (!empty($msg)): ?>
    <div class="mb-4 p-3 rounded-xl text-xs font-semibold flex items-center gap-2 <?= $msg_type === 'success' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30'; ?>">
        <i class="fa-solid <?= $msg_type === 'success' ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400'; ?>"></i>
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
            <div class="bg-slate-800/80 border <?= $is_admin_user ? 'border-rose-500/40 bg-gradient-to-r from-rose-950/20 to-slate-800/80' : ($is_blocked ? 'border-rose-500/30 bg-rose-950/10' : 'border-slate-700') ?> rounded-2xl p-3.5 shadow-md space-y-2">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-xs font-bold text-white flex items-center gap-1.5">
                            <?= htmlspecialchars($u['username']); ?>
                            <?php if ($is_admin_user): ?>
                                <span class="text-[9px] bg-rose-500/20 text-rose-300 px-1.5 py-0.5 rounded font-extrabold border border-rose-500/40 flex items-center gap-1">
                                    <i class="fa-solid fa-crown text-[8px] text-amber-300"></i> ADMIN (IMMUNE)
                                </span>
                            <?php elseif ($is_blocked): ?>
                                <span class="text-[9px] bg-rose-500/20 text-rose-400 px-1.5 py-0.5 rounded font-bold border border-rose-500/30">
                                    BLOCKED
                                </span>
                            <?php else: ?>
                                <span class="text-[9px] bg-emerald-500/20 text-emerald-400 px-1.5 py-0.5 rounded font-bold border border-emerald-500/30">
                                    ACTIVE
                                </span>
                            <?php endif; ?>
                        </h3>
                        <p class="text-[10px] text-slate-400">
                            <?= !empty($u['email']) ? htmlspecialchars($u['email']) : 'No email'; ?> &bull; 
                            <?= !empty($u['phone']) ? htmlspecialchars($u['phone']) : 'No phone'; ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] text-slate-400 block">Balance</span>
                        <span class="text-xs font-mono font-bold text-emerald-400"><?= format_inr($u['wallet_balance']); ?></span>
                    </div>
                </div>

                <div class="bg-slate-900/60 p-2 rounded-xl border border-slate-800 text-[10px] flex justify-between">
                    <div>
                        <span class="text-slate-400 block">Registered UPI:</span>
                        <span class="font-mono text-violet-300"><?= !empty($u['upi_id']) ? htmlspecialchars($u['upi_id']) : 'Not configured'; ?></span>
                    </div>
                    <div class="text-right">
                        <span class="text-slate-400 block">Matches:</span>
                        <span class="font-bold text-white"><?= $u['total_tourneys']; ?></span>
                    </div>
                </div>

                <!-- Block / Unblock Action -->
                <?php if ($is_admin_user): ?>
                    <div class="pt-1 text-center">
                        <span class="text-[10px] text-slate-500 italic">
                            <i class="fa-solid fa-shield mr-1 text-rose-400"></i> Super Admin account is permanently active and immune to bans.
                        </span>
                    </div>
                <?php else: ?>
                    <form method="POST" action="user.php" class="pt-1" onsubmit="return confirm('Kya aap is user ko <?= $is_blocked ? 'unblock' : 'suspend / block' ?> karna chahte hain?');">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="user_id" value="<?= $u['id']; ?>">
                        <input type="hidden" name="new_status" value="<?= $is_blocked ? 'active' : 'blocked'; ?>">
                        
                        <button type="submit" class="w-full py-1.5 text-xs font-bold rounded-lg transition <?= $is_blocked ? 'bg-emerald-600/30 text-emerald-300 hover:bg-emerald-600/40 border border-emerald-500/40' : 'bg-rose-600/20 text-rose-300 hover:bg-rose-600/30 border border-rose-500/30' ?>">
                            <i class="fa-solid <?= $is_blocked ? 'fa-unlock' : 'fa-ban' ?> mr-1"></i>
                            <?= $is_blocked ? 'Unblock User' : 'Suspend / Block User' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="text-xs text-slate-500 text-center py-4">No users registered yet.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
