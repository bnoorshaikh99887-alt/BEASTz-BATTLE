<?php
ob_start();
require_once __DIR__ . '/common/config.php';
check_user_auth();

$user_id = (int)$_SESSION['user_id'];
$message = "";
$message_type = "";

// 1. Handle Profile / Phone / UPI Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $new_username = preg_replace('/[^a-zA-Z0-9_]/', '', trim($_POST['username'] ?? ''));
    $new_email = sanitize($_POST['email'] ?? '');
    $new_phone = preg_replace('/[^0-9]/', '', trim($_POST['phone'] ?? ''));
    $new_upi = sanitize($_POST['upi_id'] ?? '');

    if (empty($new_username) || empty($new_email)) {
        $message = "Username and email cannot be empty.";
        $message_type = "error";
    } elseif (!empty($new_phone) && strlen($new_phone) !== 10) {
        $message = "Mobile number must be exactly 10 digits.";
        $message_type = "error";
    } else {
        // Check for duplicates
        $chk = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $chk->bind_param("ssi", $new_username, $new_email, $user_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $message = "Username or Email is already taken by another player.";
            $message_type = "error";
        } else {
            $upd = $conn->prepare("UPDATE users SET username = ?, email = ?, phone = ?, upi_id = ? WHERE id = ?");
            $upd->bind_param("ssssi", $new_username, $new_email, $new_phone, $new_upi, $user_id);
            if ($upd->execute()) {
                $_SESSION['username'] = $new_username;
                $message = "Profile and details updated successfully!";
                $message_type = "success";
            } else {
                $message = "Update failed. Try again.";
                $message_type = "error";
            }
            $upd->close();
        }
        $chk->close();
    }
}

// 2. Handle Password Change (Optional)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $new_pass = $_POST['new_password'] ?? '';
    if (strlen($new_pass) < 6) {
        $message = "New password must be at least 6 characters.";
        $message_type = "error";
    } else {
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $pw_upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $pw_upd->bind_param("si", $new_hash, $user_id);
        if ($pw_upd->execute()) {
            $message = "Password updated successfully!";
            $message_type = "success";
        }
        $pw_upd->close();
    }
}

// 3. Handle Logout (Redirects to central logout handler)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout') {
    safe_redirect("logout.php");
    exit;
}

// Fetch Latest User Profile Data
$stmt = $conn->prepare("SELECT username, email, phone, upi_id, wallet_balance, avatar, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();

require_once __DIR__ . '/common/header.php';
?>

<!-- Alert Feedback -->
<?php if (!empty($message)): ?>
    <div class="mb-4 p-3.5 rounded-xl text-xs font-semibold flex items-center gap-2 <?= ($message_type === 'success') ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' ?>">
        <i class="fa-solid <?= ($message_type === 'success') ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400' ?>"></i>
        <span><?= $message; ?></span>
    </div>
<?php endif; ?>

<!-- User Identity Card -->
<div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 shadow-lg flex items-center gap-4 mb-5">
    <?php if (!empty($u['avatar'])): ?>
        <img src="<?= htmlspecialchars($u['avatar']); ?>" alt="Avatar" class="w-14 h-14 rounded-2xl border border-violet-500/40 object-cover shadow-md">
    <?php else: ?>
        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-500 flex items-center justify-center text-white text-xl font-bold shadow-md">
            <?= strtoupper(substr($u['username'], 0, 2)); ?>
        </div>
    <?php endif; ?>
    <div class="flex-1 min-w-0">
        <h3 class="text-base font-bold text-white truncate"><?= htmlspecialchars($u['username']); ?></h3>
        <p class="text-xs text-slate-400 truncate"><?= htmlspecialchars($u['email']); ?></p>
        <?php if (!empty($u['phone'])): ?>
            <p class="text-[11px] text-emerald-400 font-mono mt-0.5"><i class="fa-solid fa-phone text-[9px] mr-1"></i>+91 <?= htmlspecialchars($u['phone']); ?></p>
        <?php endif; ?>
        <span class="inline-block mt-1 text-[10px] text-violet-400 font-semibold">
            Member since <?= date("M Y", strtotime($u['created_at'] ?? 'now')); ?>
        </span>
    </div>
</div>

<!-- Profile & UPI Form -->
<div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 shadow-lg mb-4">
    <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <i class="fa-solid fa-user-pen text-violet-400"></i> Account & Contact Details
    </h4>
    <form method="POST" action="profile.php" class="space-y-3">
        <input type="hidden" name="action" value="update_profile">
        <div>
            <label class="block text-[11px] font-medium text-slate-300 mb-1">Gamer Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($u['username']); ?>" required class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
        </div>
        <div>
            <label class="block text-[11px] font-medium text-slate-300 mb-1">Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars($u['email']); ?>" required class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
        </div>
        <div>
            <label class="block text-[11px] font-medium text-slate-300 mb-1">Mobile / WhatsApp Number</label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($u['phone'] ?? ''); ?>" placeholder="9876543210" maxlength="10" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white font-mono placeholder-slate-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-[11px] font-medium text-slate-300 mb-1">
                Saved UPI ID <span class="text-emerald-400 font-bold">(For Cash Withdrawals)</span>
            </label>
            <div class="relative">
                <i class="fa-solid fa-bolt absolute left-3 top-3 text-amber-400 text-xs"></i>
                <input type="text" name="upi_id" value="<?= htmlspecialchars($u['upi_id'] ?? ''); ?>" placeholder="mobile@upi or username@okhdfcbank" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl pl-8 pr-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none">
            </div>
            <p class="text-[10px] text-slate-400 mt-1">Saved UPI ID will be used automatically when requesting payout.</p>
        </div>
        <button type="submit" class="w-full py-2.5 bg-violet-600 hover:bg-violet-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-violet-600/30">
            Save Profile & UPI Changes
        </button>
    </form>
</div>

<!-- Password Change Section (Optional) -->
<div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 shadow-lg mb-4">
    <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <i class="fa-solid fa-lock text-amber-400"></i> Set / Change Password
    </h4>
    <form method="POST" action="profile.php" class="space-y-3">
        <input type="hidden" name="action" value="change_password">
        <div>
            <label class="block text-[11px] font-medium text-slate-300 mb-1">New Password</label>
            <input type="password" name="new_password" required minlength="6" placeholder="Minimum 6 characters" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
        </div>
        <button type="submit" class="w-full py-2 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-xl text-xs transition">
            Update Password
        </button>
    </form>
</div>

<!-- BEASTzBATTLE Official Support Community Card -->
<div class="bg-gradient-to-r from-emerald-950/40 via-slate-900 to-slate-900 border border-emerald-500/30 rounded-2xl p-4 shadow-lg mb-4 text-xs">
    <div class="flex items-center gap-2 mb-1.5">
        <div class="w-7 h-7 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
            <i class="fa-brands fa-whatsapp text-sm"></i>
        </div>
        <div>
            <h5 class="font-bold text-white text-xs">BEASTzBATTLE Official Support</h5>
            <p class="text-[10px] text-slate-400">Join our official channels for support & updates.</p>
        </div>
    </div>
    <div class="bg-slate-950/80 p-2 rounded-xl border border-slate-800 space-y-1 text-[11px] mt-2">
        <div class="flex justify-between">
            <span class="text-slate-400">Official WhatsApp:</span>
            <a href="https://wa.me/919878385810" target="_blank" class="font-mono text-emerald-400 font-bold hover:underline">+91 9878385810</a>
        </div>
        <div class="flex justify-between">
            <span class="text-slate-400">Helpdesk Support:</span>
            <span class="font-mono text-slate-300">988385810</span>
        </div>
        <p class="text-[9px] text-slate-500 italic pt-1">Official verified community channels. BEASTzBATTLE: Play, Compete, Win.</p>
    </div>
</div>

<!-- Logout Button -->
<a href="logout.php" class="w-full py-3 bg-rose-600/20 hover:bg-rose-600/30 border border-rose-500/30 text-rose-400 hover:text-rose-300 font-bold rounded-2xl text-xs transition flex items-center justify-center gap-2">
    <i class="fa-solid fa-power-off"></i>
    <span>Sign Out of BEASTzBATTLE</span>
</a>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
