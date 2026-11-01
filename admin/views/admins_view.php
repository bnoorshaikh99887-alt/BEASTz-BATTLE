<?php
// admin/views/admins_view.php - Multi-Admin & Host Management
$admin_msg = "";
$admin_msg_type = "";
$is_super = ($logged_username === 'anonymous.349' || ($_SESSION['admin_role'] ?? '') === 'super_admin');

// 1. ACTION: Create Sub-Admin / Host
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_host_admin') {
    if (!$is_super) {
        $admin_msg = "Only Master Admin (anonymous.349) can create new sub-admins.";
        $admin_msg_type = "error";
    } else {
        $new_uname = preg_replace('/[^a-zA-Z0-9_.-]/', '', sanitize($_POST['new_admin_username'] ?? ''));
        $new_pass = trim($_POST['new_admin_password'] ?? '');

        if (empty($new_uname) || strlen($new_uname) < 3) {
            $admin_msg = "Username must be at least 3 characters long.";
            $admin_msg_type = "error";
        } elseif (empty($new_pass) || strlen($new_pass) < 6) {
            $admin_msg = "Password must be at least 6 characters long.";
            $admin_msg_type = "error";
        } else {
            // Check uniqueness in admin table
            $chk = $conn->prepare("SELECT id FROM admin WHERE username = ? LIMIT 1");
            $chk->bind_param("s", $new_uname);
            $chk->execute();
            $exists = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($exists) {
                $admin_msg = "Admin username already exists. Choose another.";
                $admin_msg_type = "error";
            } else {
                $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
                $ins = $conn->prepare("INSERT INTO admin (username, password, role, status, wallet_balance, rooms_hosted) VALUES (?, ?, 'host_admin', 'active', 0.00, 0)");
                $ins->bind_param("ss", $new_uname, $hashed);
                if ($ins->execute()) {
                    $ins->close();
                    $admin_msg = "Host Admin @" . htmlspecialchars($new_uname) . " created successfully! They can now log in.";
                    $admin_msg_type = "success";
                } else {
                    $admin_msg = "Database Error: " . $conn->error;
                    $admin_msg_type = "error";
                }
            }
        }
    }
}

// 2. ACTION: Toggle Status (Suspend / Activate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_admin_status') {
    if (!$is_super) {
        $admin_msg = "Unauthorized.";
        $admin_msg_type = "error";
    } else {
        $aid = (int)($_POST['admin_id'] ?? 0);
        $new_st = ($_POST['new_status'] === 'suspended') ? 'suspended' : 'active';
        
        // Cannot suspend Master Admin
        $chk_master = $conn->prepare("SELECT username FROM admin WHERE id = ?");
        $chk_master->bind_param("i", $aid);
        $chk_master->execute();
        $adm_row = $chk_master->get_result()->fetch_assoc();
        $chk_master->close();

        if ($adm_row && $adm_row['username'] === 'anonymous.349') {
            $admin_msg = "Master Admin cannot be suspended.";
            $admin_msg_type = "error";
        } else {
            $upd = $conn->prepare("UPDATE admin SET status = ? WHERE id = ?");
            $upd->bind_param("si", $new_st, $aid);
            $upd->execute();
            $upd->close();
            $admin_msg = "Admin status updated to " . strtoupper($new_st) . "!";
            $admin_msg_type = "success";
        }
    }
}

// 3. ACTION: Delete Sub-Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_admin') {
    if (!$is_super) {
        $admin_msg = "Unauthorized.";
        $admin_msg_type = "error";
    } else {
        $aid = (int)($_POST['admin_id'] ?? 0);
        
        $chk_master = $conn->prepare("SELECT username FROM admin WHERE id = ?");
        $chk_master->bind_param("i", $aid);
        $chk_master->execute();
        $adm_row = $chk_master->get_result()->fetch_assoc();
        $chk_master->close();

        if ($adm_row && $adm_row['username'] === 'anonymous.349') {
            $admin_msg = "Master Admin cannot be deleted.";
            $admin_msg_type = "error";
        } else {
            $conn->query("DELETE FROM admin WHERE id = {$aid}");
            $admin_msg = "Sub-admin removed permanently.";
            $admin_msg_type = "success";
        }
    }
}

// 4. ACTION: Host Request Revenue Payout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'host_request_payout') {
    $curr_adm_id = (int)($_SESSION['admin_id'] ?? 0);
    $amount = (float)($_POST['payout_amount'] ?? 0.00);
    $upi = sanitize($_POST['host_upi_id'] ?? '');

    $chk_adm = $conn->prepare("SELECT username, wallet_balance FROM admin WHERE id = ?");
    $chk_adm->bind_param("i", $curr_adm_id);
    $chk_adm->execute();
    $adm_info = $chk_adm->get_result()->fetch_assoc();
    $chk_adm->close();

    $cur_bal = (float)($adm_info['wallet_balance'] ?? 0.00);

    if ($amount < 10) {
        $admin_msg = "Minimum payout amount is ₹10.";
        $admin_msg_type = "error";
    } elseif (empty($upi) || !str_contains($upi, '@')) {
        $admin_msg = "Please enter a valid UPI ID (e.g. name@upi).";
        $admin_msg_type = "error";
    } elseif ($cur_bal < $amount) {
        $admin_msg = "Insufficient balance. Available: " . format_inr($cur_bal);
        $admin_msg_type = "error";
    } else {
        $conn->begin_transaction();
        try {
            // Deduct admin wallet
            $conn->query("UPDATE admin SET wallet_balance = wallet_balance - {$amount}, upi_id = '" . $conn->real_escape_string($upi) . "' WHERE id = {$curr_adm_id}");
            
            // Insert into withdrawals with is_admin_request = 1
            $dummy_uid = 1; // Admin user ID
            $ins_w = $conn->prepare("INSERT INTO withdrawals (user_id, amount, upi_id, status, is_admin_request, admin_id) VALUES (?, ?, ?, 'Pending', 1, ?)");
            $ins_w->bind_param("idsi", $dummy_uid, $amount, $upi, $curr_adm_id);
            $ins_w->execute();
            $ins_w->close();

            $conn->commit();
            $admin_msg = "Host Payout Request of " . format_inr($amount) . " submitted! Master Admin will approve it shortly.";
            $admin_msg_type = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $admin_msg = "Payout Request Error: " . $e->getMessage();
            $admin_msg_type = "error";
        }
    }
}

// Fetch Host Info if host_admin
$curr_host_data = null;
if (isset($_SESSION['admin_id'])) {
    $curr_adm_id = (int)$_SESSION['admin_id'];
    $h_stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
    $h_stmt->bind_param("i", $curr_adm_id);
    $h_stmt->execute();
    $curr_host_data = $h_stmt->get_result()->fetch_assoc();
    $h_stmt->close();
}

// Fetch All Admins for Super Admin
$all_admins = $conn->query("SELECT * FROM admin ORDER BY CASE WHEN username = 'anonymous.349' THEN 1 ELSE 2 END, id ASC");
?>

<div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h2 class="text-base font-extrabold text-slate-900 dark:text-white font-display flex items-center gap-2">
                <i class="fa-solid fa-users-gear text-yellow-500"></i>
                <span><?= $is_super ? "Administrator & Host Control" : "Host Revenue & Earnings Studio"; ?></span>
            </h2>
            <p class="text-[11px] text-slate-500">
                <?= $is_super ? "Manage sub-admins, permissions, and match room hosts" : "Track ₹3/room hosted earnings and request withdrawals"; ?>
            </p>
        </div>
        <span class="px-2.5 py-1 bg-yellow-400 text-slate-950 font-black rounded-full text-[10px] uppercase">
            <?= $is_super ? "SUPER ADMIN" : "HOST ADMIN"; ?>
        </span>
    </div>

    <!-- Feedback Alerts -->
    <?php if (!empty($admin_msg)): ?>
        <div class="p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2 <?= ($admin_msg_type === 'success') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/10 text-rose-400 border border-rose-500/30'; ?>">
            <i class="fa-solid <?= ($admin_msg_type === 'success') ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400'; ?>"></i>
            <span><?= htmlspecialchars($admin_msg); ?></span>
        </div>
    <?php endif; ?>

    <!-- ======================================================== -->
    <!-- SECTION 1: HOST REVENUE & EARNINGS CARD (FOR ALL HOSTS)  -->
    <!-- ======================================================== -->
    <div class="bg-gradient-to-br from-yellow-500/10 via-slate-900 to-slate-950 border border-yellow-400/30 rounded-2xl p-4 shadow-md space-y-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-yellow-400 text-slate-950 flex items-center justify-center text-sm font-black shadow-xs">
                    <i class="fa-solid fa-coins"></i>
                </div>
                <div>
                    <h3 class="text-xs font-extrabold text-white">Host Room Revenue</h3>
                    <span class="text-[10px] text-yellow-400 font-semibold">Earn ₹3 for every room hosted & completed</span>
                </div>
            </div>
            <div class="text-right">
                <span class="text-[10px] text-slate-400 block font-semibold">Available Host Balance</span>
                <span class="text-lg font-black font-mono text-yellow-400">
                    <?= format_inr($curr_host_data['wallet_balance'] ?? 0.00); ?>
                </span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2 text-xs bg-slate-900/60 p-2.5 rounded-xl border border-slate-800">
            <div>
                <span class="text-[10px] text-slate-400 block">Total Rooms Hosted</span>
                <span class="text-sm font-black text-white"><?= (int)($curr_host_data['rooms_hosted'] ?? 0); ?> Rooms</span>
            </div>
            <div class="text-right">
                <span class="text-[10px] text-slate-400 block">Payout Rate</span>
                <span class="text-sm font-black text-emerald-400">₹3.00 / Match</span>
            </div>
        </div>

        <!-- Request Payout Modal Trigger -->
        <button type="button" onclick="document.getElementById('hostPayoutModal').classList.remove('hidden')" class="w-full py-2.5 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
            <i class="fa-solid fa-money-bill-transfer"></i>
            <span>Request Host Earnings Withdrawal</span>
        </button>
    </div>

    <!-- ======================================================== -->
    <!-- SECTION 2: CREATE NEW SUB-ADMIN (SUPER ADMIN ONLY)       -->
    <!-- ======================================================== -->
    <?php if ($is_super): ?>
    <div class="bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm space-y-3">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-yellow-400/20 text-yellow-500 flex items-center justify-center text-xs">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">
                Create New Sub-Admin / Match Host
            </h3>
        </div>

        <form method="POST" action="index.php?tab=admins" class="space-y-3 text-xs">
            <input type="hidden" name="action" value="create_host_admin">
            
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                        Host Username <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="new_admin_username" required placeholder="e.g. host_shaikh" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-yellow-400 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-bold focus:outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                        Host Password <span class="text-rose-500">*</span>
                    </label>
                    <input type="password" name="new_admin_password" required minlength="6" placeholder="••••••••" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-yellow-400 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-bold focus:outline-none">
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                <i class="fa-solid fa-plus"></i>
                <span>Create & Authorize Sub-Admin</span>
            </button>
        </form>
    </div>

    <!-- ======================================================== -->
    <!-- SECTION 3: SUB-ADMINS DIRECTORY & SUSPEND/DELETE         -->
    <!-- ======================================================== -->
    <div class="bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-400">
                Active Administrators & Match Hosts
            </h3>
            <span class="text-[10px] text-slate-500">Live Credentials</span>
        </div>

        <div class="space-y-2">
            <?php if ($all_admins && $all_admins->num_rows > 0): ?>
                <?php while ($adm = $all_admins->fetch_assoc()): ?>
                    <?php 
                        $is_this_master = ($adm['username'] === 'anonymous.349');
                        $is_suspended = ($adm['status'] === 'suspended');
                    ?>
                    <div class="p-3 rounded-xl border <?= $is_this_master ? 'bg-yellow-400/5 border-yellow-400/40' : ($is_suspended ? 'bg-rose-500/5 border-rose-500/30' : 'bg-slate-50 dark:bg-slate-800/60 border-slate-200 dark:border-slate-700'); ?> flex items-center justify-between gap-3 text-xs">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-extrabold text-slate-900 dark:text-white">@<?= htmlspecialchars($adm['username']); ?></span>
                                <?php if ($is_this_master): ?>
                                    <span class="px-2 py-0.5 rounded-full bg-yellow-400 text-slate-950 font-black text-[9px] uppercase shadow-xs">
                                        MASTER ADMIN (IMMUNE)
                                    </span>
                                <?php elseif ($is_suspended): ?>
                                    <span class="px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-400 font-bold text-[9px] uppercase">
                                        SUSPENDED
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 font-bold text-[9px] uppercase">
                                        ACTIVE HOST
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-[10px] text-slate-400 mt-1 flex items-center gap-3">
                                <span>Rooms Hosted: <strong class="text-slate-200"><?= (int)$adm['rooms_hosted']; ?></strong></span>
                                <span>Earnings: <strong class="text-yellow-400 font-mono"><?= format_inr($adm['wallet_balance']); ?></strong></span>
                            </div>
                        </div>

                        <?php if (!$is_this_master): ?>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <!-- Suspend / Activate Form -->
                                <form method="POST" action="index.php?tab=admins" onsubmit="return confirm('Toggle status for this admin?');">
                                    <input type="hidden" name="action" value="toggle_admin_status">
                                    <input type="hidden" name="admin_id" value="<?= $adm['id']; ?>">
                                    <input type="hidden" name="new_status" value="<?= $is_suspended ? 'active' : 'suspended'; ?>">
                                    <button type="submit" class="px-2 py-1 rounded-lg text-[10px] font-bold border transition <?= $is_suspended ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 hover:bg-emerald-500/30' : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-rose-500/20 hover:text-rose-300'; ?>">
                                        <?= $is_suspended ? "Activate" : "Suspend"; ?>
                                    </button>
                                </form>

                                <!-- Delete Form -->
                                <form method="POST" action="index.php?tab=admins" onsubmit="return confirm('Permanently delete this sub-admin?');">
                                    <input type="hidden" name="action" value="delete_admin">
                                    <input type="hidden" name="admin_id" value="<?= $adm['id']; ?>">
                                    <button type="submit" class="px-2 py-1 rounded-lg text-[10px] font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30 hover:bg-rose-500 hover:text-white transition">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Modal: Host Request Payout -->
<div id="hostPayoutModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-slate-950 border border-yellow-400/40 rounded-3xl p-5 shadow-2xl relative text-left">
        <button type="button" onclick="document.getElementById('hostPayoutModal').classList.add('hidden')" class="absolute top-4 right-4 text-slate-400 hover:text-white text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="text-center mb-4">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-yellow-400 text-slate-950 flex items-center justify-center text-xl font-black mb-2 shadow-md">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <h3 class="text-sm font-extrabold text-white">Withdraw Host Earnings</h3>
            <p class="text-[11px] text-slate-400">Request payout directly to your personal UPI ID</p>
        </div>

        <div class="p-2.5 rounded-xl bg-slate-900 border border-slate-800 flex justify-between text-xs mb-3">
            <span class="text-slate-400">Available Host Balance:</span>
            <strong class="text-yellow-400 font-mono font-black"><?= format_inr($curr_host_data['wallet_balance'] ?? 0.00); ?></strong>
        </div>

        <form method="POST" action="index.php?tab=admins" class="space-y-3 text-xs">
            <input type="hidden" name="action" value="host_request_payout">
            
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                    Withdrawal Amount (₹) <span class="text-slate-500">(Min ₹10)</span>
                </label>
                <input type="number" name="payout_amount" min="10" max="<?= max(10, (float)($curr_host_data['wallet_balance'] ?? 0)); ?>" required placeholder="50" class="w-full bg-slate-900 border border-slate-700 focus:border-yellow-400 rounded-xl px-3 py-2 text-white font-mono font-bold focus:outline-none">
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                    Your Personal UPI ID <span class="text-rose-400">*</span>
                </label>
                <input type="text" name="host_upi_id" required value="<?= htmlspecialchars($curr_host_data['upi_id'] ?? ''); ?>" placeholder="e.g. 9878385810@upi" class="w-full bg-slate-900 border border-slate-700 focus:border-yellow-400 rounded-xl px-3 py-2 text-white font-mono font-bold focus:outline-none">
            </div>

            <p class="text-[10px] text-slate-500">
                ⚡ Your request will appear in Master Admin's Payouts with a special yellow glow so it gets approved quickly!
            </p>

            <button type="submit" class="w-full py-3 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs transition shadow-md active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                <i class="fa-solid fa-paper-plane"></i>
                <span>Submit Payout Request</span>
            </button>
        </form>
    </div>
</div>
