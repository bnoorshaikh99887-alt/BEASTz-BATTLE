<?php
require_once __DIR__ . '/common/header.php';

$msg = "";

// Handle Block / Unblock User Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $uid = (int)$_POST['user_id'];
    $new_status = sanitize($_POST['new_status']);

    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $uid);
    if ($stmt->execute()) {
        $msg = "User status updated to " . ucfirst($new_status) . ".";
    }
    $stmt->close();
}

// Fetch all Users
$users = $conn->query("
    SELECT u.*, 
    (SELECT COUNT(*) FROM participants WHERE user_id = u.id) as total_tourneys
    FROM users u 
    ORDER BY u.id DESC");
?>

<div class="mb-4">
    <h2 class="text-base font-bold text-white">User Management</h2>
    <p class="text-[11px] text-slate-400">Registered platform gamers and balances</p>
</div>

<?php if (!empty($msg)): ?>
    <div class="mb-4 p-3 rounded-xl text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
        <?= $msg; ?>
    </div>
<?php endif; ?>

<div class="space-y-3">
    <?php if ($users && $users->num_rows > 0): ?>
        <?php while ($u = $users->fetch_assoc()): ?>
            <?php $is_blocked = ($u['status'] === 'blocked'); ?>
            <div class="bg-slate-800/80 border border-slate-700 rounded-2xl p-3.5 shadow-md space-y-2">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-xs font-bold text-white flex items-center gap-1.5">
                            <?= htmlspecialchars($u['username']); ?>
                            <?php if ($is_blocked): ?>
                                <span class="text-[9px] bg-rose-500/20 text-rose-400 px-1.5 py-0.2 rounded border border-rose-500/30">BLOCKED</span>
                            <?php else: ?>
                                <span class="text-[9px] bg-emerald-500/20 text-emerald-400 px-1.5 py-0.2 rounded border border-emerald-500/30">ACTIVE</span>
                            <?php endif; ?>
                        </h3>
                        <p class="text-[10px] text-slate-400"><?= htmlspecialchars($u['email']); ?></p>
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

                <!-- Block / Unblock Form -->
                <form method="POST" action="user.php" class="pt-1">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="user_id" value="<?= $u['id']; ?>">
                    <input type="hidden" name="new_status" value="<?= $is_blocked ? 'active' : 'blocked'; ?>">
                    
                    <button type="submit" class="w-full py-1.5 text-xs font-bold rounded-lg transition <?= $is_blocked ? 'bg-emerald-600/30 text-emerald-300 hover:bg-emerald-600/40 border border-emerald-500/40' : 'bg-rose-600/20 text-rose-300 hover:bg-rose-600/30 border border-rose-500/30' ?>">
                        <i class="fa-solid <?= $is_blocked ? 'fa-unlock' : 'fa-ban' ?> mr-1"></i>
                        <?= $is_blocked ? 'Unblock User' : 'Suspend / Block User' ?>
                    </button>
                </form>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="text-xs text-slate-500 text-center py-4">No users registered yet.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/common/bottom.php'; ?>