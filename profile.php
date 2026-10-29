<?php
require_once __DIR__ . '/common/header.php';
check_user_auth();

$user_id = (int)$_SESSION['user_id'];
$msg = "";
$msg_type = "";

// 1. Handle Account Settings Update (IGN & Free Fire UID)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_game_id') {
    $game_name = sanitize($_POST['game_name'] ?? '');
    $game_id = sanitize($_POST['game_id'] ?? '');

    $upd = $conn->prepare("UPDATE users SET game_name = ?, game_id = ? WHERE id = ?");
    if ($upd) {
        $upd->bind_param("ssi", $game_name, $game_id, $user_id);
        if ($upd->execute()) {
            $msg = "Account details (IGN & Free Fire Game ID) saved successfully!";
            $msg_type = "success";
        } else {
            $msg = "Update failed: " . $upd->error;
            $msg_type = "error";
        }
        $upd->close();
    }
}

// 2. Handle UPI Withdrawal Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_withdrawal') {
    $amount = (float)($_POST['amount'] ?? 0.00);
    $upi_id = sanitize($_POST['upi_id'] ?? '');
    $min_wdr = (float)get_setting('min_withdrawal', '50.00');

    // Fetch user current balances
    $u_stmt = $conn->prepare("SELECT wallet_balance, winning_balance FROM users WHERE id = ?");
    $u_stmt->bind_param("i", $user_id);
    $u_stmt->execute();
    $u_bal = $u_stmt->get_result()->fetch_assoc();
    $u_stmt->close();

    $curr_wallet = (float)($u_bal['wallet_balance'] ?? 0.00);

    if ($amount < $min_wdr) {
        $msg = "Minimum withdrawal amount is " . format_inr($min_wdr) . ".";
        $msg_type = "error";
    } elseif (empty($upi_id) || !str_contains($upi_id, '@')) {
        $msg = "Please enter a valid UPI ID (e.g. yourname@upi).";
        $msg_type = "error";
    } elseif ($curr_wallet < $amount) {
        $msg = "Insufficient wallet balance for this payout.";
        $msg_type = "error";
    } else {
        $conn->begin_transaction();
        try {
            // Deduct wallet
            $ded = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
            $ded->bind_param("di", $amount, $user_id);
            $ded->execute();
            $ded->close();

            // Insert withdrawal request
            $w_ins = $conn->prepare("INSERT INTO withdrawals (user_id, amount, upi_id, status) VALUES (?, ?, ?, 'Pending')");
            $w_ins->bind_param("ids", $user_id, $amount, $upi_id);
            $w_ins->execute();
            $w_ins->close();

            // Transaction record
            $tx_desc = "Withdrawal Payout to " . $upi_id;
            $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
            $tx->bind_param("ids", $user_id, $amount, $tx_desc);
            $tx->execute();
            $tx->close();

            // Update user's upi_id preference
            @$conn->query("UPDATE users SET upi_id = '" . $conn->real_escape_string($upi_id) . "' WHERE id = {$user_id}");

            $conn->commit();
            $msg = "🎉 Withdrawal request of " . format_inr($amount) . " submitted! Payout will be sent to " . htmlspecialchars($upi_id) . " shortly.";
            $msg_type = "success";
            $user_wallet -= $amount;
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "Withdrawal Error: " . $e->getMessage();
            $msg_type = "error";
        }
    }
}

// 3. Handle Private Tournament Search / Join
$private_match_found = null;
if (isset($_GET['tourney_code']) && !empty($_GET['tourney_code'])) {
    $code = sanitize($_GET['tourney_code']);
    $p_stmt = $conn->prepare("SELECT * FROM tournaments WHERE (private_code = ? OR id = ?) LIMIT 1");
    $p_stmt->bind_param("ss", $code, $code);
    $p_stmt->execute();
    $private_match_found = $p_stmt->get_result()->fetch_assoc();
    $p_stmt->close();
}

// Fetch Full Profile Info
$stmt = $conn->prepare("SELECT username, email, phone, wallet_balance, upi_id, avatar, game_name, game_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

$my_avatar = !empty($me['avatar']) ? $me['avatar'] : 'https://api.dicebear.com/7.x/bottts/svg?seed=' . urlencode($me['username']);
$my_ign = !empty($me['game_name']) ? $me['game_name'] : '';
$my_game_id = !empty($me['game_id']) ? $me['game_id'] : '';
$my_phone = !empty($me['phone']) ? $me['phone'] : '';

// Fetch Recent Transactions
$tx_res = $conn->query("SELECT * FROM transactions WHERE user_id = {$user_id} ORDER BY id DESC LIMIT 10");

// Official Support Number (Customer Support requirement)
$support_phone = get_setting('whatsapp_support', '9878385810');
if (empty($support_phone)) $support_phone = '9878385810';
?>

<!-- Alert Feedback -->
<?php if (!empty($msg)): ?>
    <div class="mb-4 p-3 rounded-xl text-xs font-semibold flex items-center gap-2 <?= ($msg_type === 'success') ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'; ?>">
        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-500' : 'fa-circle-exclamation text-rose-500'; ?>"></i>
        <span><?= htmlspecialchars($msg); ?></span>
    </div>
<?php endif; ?>

<!-- Top Profile Header (Matching Video Frame 0:12) -->
<div class="text-center pt-2 pb-6">
    <div class="relative inline-block mb-3">
        <img src="<?= htmlspecialchars($my_avatar); ?>" alt="Avatar" class="w-20 h-20 rounded-full object-cover border-4 border-yellow-400 shadow-md bg-slate-100 mx-auto">
        <button type="button" onclick="openAccountModal()" class="absolute bottom-0 right-0 w-7 h-7 bg-white text-slate-800 border border-slate-300 rounded-full flex items-center justify-center text-xs shadow hover:bg-slate-50 transition">
            <i class="fa-solid fa-camera text-[10px]"></i>
        </button>
    </div>
    
    <h2 class="text-lg font-black text-slate-900 tracking-tight font-display">
        <?= htmlspecialchars($me['username']); ?>
    </h2>
    <span class="text-xs text-slate-500 block">
        <?= !empty($me['email']) ? htmlspecialchars($me['email']) : '+91 ' . htmlspecialchars($my_phone); ?>
    </span>

    <div class="mt-2 inline-flex items-center gap-1.5 px-3 py-1 bg-yellow-50 border border-yellow-200 rounded-full text-xs font-bold text-yellow-800">
        <i class="fa-solid fa-wallet text-yellow-500"></i>
        <span>Wallet: <?= format_inr($me['wallet_balance']); ?></span>
    </div>
</div>

<!-- Profile Navigation Menu Cards (Exact Match to Video Frame 0:12) -->
<div class="space-y-2.5 mb-6">
    
    <!-- 1. Account Settings -->
    <button type="button" onclick="openAccountModal()" class="w-full bg-white hover:bg-slate-50 border border-slate-200 rounded-2xl p-4 flex items-center justify-between shadow-xs transition group">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-sm group-hover:bg-yellow-100 group-hover:text-yellow-700 transition">
                <i class="fa-regular fa-user"></i>
            </div>
            <div class="text-left">
                <span class="text-xs font-bold text-slate-900 block">Account Settings</span>
                <span class="text-[10px] text-slate-400">Manage IGN & Free Fire Game UID</span>
            </div>
        </div>
        <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-slate-600 transition"></i>
    </button>

    <!-- 2. Join Private Tournament -->
    <button type="button" onclick="openPrivateModal()" class="w-full bg-white hover:bg-slate-50 border border-slate-200 rounded-2xl p-4 flex items-center justify-between shadow-xs transition group">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-sm group-hover:bg-yellow-100 group-hover:text-yellow-700 transition">
                <i class="fa-solid fa-trophy"></i>
            </div>
            <div class="text-left">
                <span class="text-xs font-bold text-slate-900 block">Join Private Tournament</span>
                <span class="text-[10px] text-slate-400">Enter custom tournament access code</span>
            </div>
        </div>
        <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-slate-600 transition"></i>
    </button>

    <!-- 3. Withdrawals -->
    <button type="button" onclick="openWithdrawalModal()" class="w-full bg-white hover:bg-slate-50 border border-slate-200 rounded-2xl p-4 flex items-center justify-between shadow-xs transition group">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-sm group-hover:bg-yellow-100 group-hover:text-yellow-700 transition">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <div class="text-left">
                <span class="text-xs font-bold text-slate-900 block">Withdrawals</span>
                <span class="text-[10px] text-slate-400">Payout winnings via instant UPI</span>
            </div>
        </div>
        <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-slate-600 transition"></i>
    </button>

    <!-- 4. Transactions -->
    <button type="button" onclick="openTransactionsModal()" class="w-full bg-white hover:bg-slate-50 border border-slate-200 rounded-2xl p-4 flex items-center justify-between shadow-xs transition group">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-sm group-hover:bg-yellow-100 group-hover:text-yellow-700 transition">
                <i class="fa-regular fa-credit-card"></i>
            </div>
            <div class="text-left">
                <span class="text-xs font-bold text-slate-900 block">Transactions</span>
                <span class="text-[10px] text-slate-400">View deposits and match fee passbook</span>
            </div>
        </div>
        <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-slate-600 transition"></i>
    </button>

    <!-- 5. Customer Support (Official WhatsApp & Call Support) -->
    <button type="button" onclick="openSupportModal()" class="w-full bg-white hover:bg-slate-50 border border-slate-200 rounded-2xl p-4 flex items-center justify-between shadow-xs transition group">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-yellow-100 text-yellow-700 flex items-center justify-center text-sm group-hover:bg-yellow-400 group-hover:text-slate-950 transition">
                <i class="fa-regular fa-circle-question"></i>
            </div>
            <div class="text-left">
                <span class="text-xs font-bold text-slate-900 block flex items-center gap-1.5">
                    Customer Support
                    <span class="text-[9px] bg-emerald-100 text-emerald-800 font-bold px-1.5 py-0.2 rounded">ONLINE</span>
                </span>
                <span class="text-[10px] text-slate-400">Direct WhatsApp / Call: +91 <?= htmlspecialchars($support_phone); ?></span>
            </div>
        </div>
        <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-slate-600 transition"></i>
    </button>
</div>

<!-- Version Footer -->
<div class="text-center mb-6">
    <p class="text-[11px] text-slate-400 font-medium">Version 1.0.3</p>
    <a href="logout.php" class="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-rose-500 hover:text-rose-600 transition px-4 py-2 rounded-xl hover:bg-rose-50">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
        <span>Logout</span>
    </a>
</div>

<!-- ============================================================= -->
<!-- MODAL 1: ACCOUNT SETTINGS (IGN & Free Fire Game ID) -->
<!-- ============================================================= -->
<div id="accountModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeAccountModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="text-center mb-4">
            <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-yellow-400 mx-auto mb-2 shadow-xs bg-slate-100">
                <img src="<?= htmlspecialchars($my_avatar); ?>" alt="Avatar" class="w-full h-full object-cover">
            </div>
            <h3 class="text-sm font-extrabold text-slate-900">Account Settings</h3>
            <p class="text-[11px] text-slate-400">Save your in-game identity for tournaments</p>
        </div>

        <form method="POST" action="profile.php" class="space-y-3.5">
            <input type="hidden" name="action" value="update_game_id">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Game Name (IGN)</label>
                <input type="text" name="game_name" value="<?= htmlspecialchars($my_ign); ?>" required placeholder="e.g. ꧁☆BEAST☆꧂" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Free Fire Game ID (UID)</label>
                <input type="text" name="game_id" value="<?= htmlspecialchars($my_game_id); ?>" required placeholder="e.g. 2839221886" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
            </div>

            <!-- Yellow Action Button Matching Video -->
            <button type="submit" class="w-full py-2.5 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs transition shadow-sm active:scale-95">
                Save
            </button>
        </form>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL 2: JOIN PRIVATE TOURNAMENT (Matching Video Frame 0:16) -->
<!-- ============================================================= -->
<div id="privateModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative">
        <button type="button" onclick="closePrivateModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <h3 class="text-sm font-extrabold text-slate-900 mb-3">Private Contest</h3>

        <form method="GET" action="profile.php" class="space-y-3">
            <label class="block text-xs font-bold text-slate-700">Enter Tournament Code</label>
            <div class="flex gap-2">
                <input type="text" name="tourney_code" required placeholder="e.g. BEASTZ99" class="flex-1 bg-slate-50 border border-slate-300 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-slate-900 uppercase font-mono font-bold focus:outline-none">
                <button type="submit" class="px-4 py-2 bg-yellow-400 hover:bg-yellow-500 text-slate-950 rounded-xl text-xs font-bold transition">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </div>
        </form>

        <div class="mt-6 text-center py-6 px-4">
            <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-xl mb-3">
                <i class="fa-solid fa-trophy"></i>
            </div>
            <p class="text-xs font-bold text-slate-700">Enter a tournament code to join a private contest</p>
            <p class="text-[11px] text-slate-400 mt-1">Get the code from tournament organizer.</p>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL 3: WITHDRAWALS (Matching Video Frame 0:19) -->
<!-- ============================================================= -->
<div id="withdrawalModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeWithdrawalModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-extrabold text-slate-900">Withdrawal</h3>
            <div class="text-right">
                <span class="text-[10px] text-slate-400 block">Winning Balance</span>
                <span class="text-sm font-extrabold text-emerald-600 font-mono"><?= format_inr($me['wallet_balance']); ?></span>
            </div>
        </div>

        <!-- Mode Tab: UPI Withdrawal -->
        <div class="flex gap-2 mb-4">
            <button type="button" class="flex-1 py-1.5 bg-yellow-400 text-slate-950 text-xs font-black rounded-lg shadow-xs">
                UPI Withdrawal
            </button>
            <button type="button" onclick="alert('Redeem code payout will be available shortly. Please use instant UPI withdrawal.')" class="flex-1 py-1.5 bg-slate-100 text-slate-500 text-xs font-bold rounded-lg">
                Redeem Code
            </button>
        </div>

        <form method="POST" action="profile.php" class="space-y-3">
            <input type="hidden" name="action" value="request_withdrawal">

            <div>
                <label class="block text-[11px] font-medium text-slate-600 mb-1">Enter amount to withdrawal</label>
                <input type="number" name="amount" min="50" step="1" value="50" required class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:outline-none">
            </div>

            <div>
                <label class="block text-[11px] font-medium text-slate-600 mb-1">Enter UPI Id</label>
                <input type="text" name="upi_id" value="<?= htmlspecialchars($me['upi_id'] ?? ''); ?>" required placeholder="e.g. 9878385810@upi" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-slate-900 font-mono focus:outline-none">
            </div>

            <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold rounded-xl text-xs transition shadow-sm active:scale-95">
                Withdrawal <?= format_inr(50); ?>
            </button>
            <p class="text-[10px] text-slate-400 text-center">Only winnings can be withdrawn.</p>
        </form>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL 4: TRANSACTIONS PASSBOOK -->
<!-- ============================================================= -->
<div id="transactionsModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative max-h-[85vh] flex flex-col">
        <button type="button" onclick="closeTransactionsModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <h3 class="text-sm font-extrabold text-slate-900 mb-1">Transaction History</h3>
        <p class="text-[11px] text-slate-400 mb-3">All your deposits, winnings, and match fees</p>

        <div class="flex-1 overflow-y-auto space-y-2 pr-1 divide-y divide-slate-100">
            <?php if ($tx_res && $tx_res->num_rows > 0): ?>
                <?php while ($tx = $tx_res->fetch_assoc()): ?>
                    <?php $is_cr = ($tx['type'] === 'credit'); ?>
                    <div class="pt-2 flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold text-slate-800 block truncate max-w-[180px]">
                                <?= htmlspecialchars($tx['description']); ?>
                            </span>
                            <span class="text-[10px] text-slate-400 font-mono">
                                <?= date('d M Y, h:i A', strtotime($tx['created_at'])); ?>
                            </span>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-mono font-black <?= $is_cr ? 'text-emerald-600' : 'text-slate-700'; ?>">
                                <?= $is_cr ? '+' : '-' ?><?= format_inr($tx['amount']); ?>
                            </span>
                            <span class="block text-[9px] font-bold text-emerald-600">COMPLETED</span>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-xs text-slate-400 text-center py-6">No transaction records found yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL 5: CUSTOMER SUPPORT (Direct Phone & WhatsApp Support) -->
<!-- ============================================================= -->
<div id="supportModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative text-center">
        <button type="button" onclick="closeSupportModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl mb-3 shadow-xs">
            <i class="fa-brands fa-whatsapp"></i>
        </div>

        <h3 class="text-base font-extrabold text-slate-900">Official Customer Support</h3>
        <p class="text-xs text-slate-500 mt-0.5 mb-4">Instant assistance for Room IDs, Passwords, Deposits & Withdrawals</p>

        <!-- Direct Contact Card with User's Number -->
        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 mb-4 text-center">
            <span class="text-[10px] font-bold uppercase text-slate-400 block tracking-wider">Helpline Number</span>
            <span class="text-lg font-black text-slate-900 font-mono mt-0.5 block tracking-wide">
                +91 <?= htmlspecialchars($support_phone); ?>
            </span>
            <span class="inline-flex items-center gap-1 text-[10px] text-emerald-600 font-bold mt-1">
                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                Official Support Agent Online (24x7)
            </span>
        </div>

        <!-- WhatsApp Direct Chat Button -->
        <a href="https://wa.me/91<?= urlencode($support_phone); ?>?text=Hello%20BEASTzBATTLE%20Support,%20I%20need%20help%20with%20my%20account%20@<?= urlencode($me['username']); ?>" 
           target="_blank" 
           class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold rounded-xl text-xs shadow-md transition flex items-center justify-center gap-2 mb-2 active:scale-95">
            <i class="fa-brands fa-whatsapp text-sm"></i>
            <span>Chat on WhatsApp</span>
        </a>

        <!-- Direct Call Helpline Button -->
        <a href="tel:<?= urlencode($support_phone); ?>" 
           class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold rounded-xl text-xs transition flex items-center justify-center gap-2">
            <i class="fa-solid fa-phone text-xs text-slate-600"></i>
            <span>Direct Call (+91 <?= htmlspecialchars($support_phone); ?>)</span>
        </a>
    </div>
</div>

<script>
    function openAccountModal() { document.getElementById('accountModal').classList.remove('hidden'); }
    function closeAccountModal() { document.getElementById('accountModal').classList.add('hidden'); }

    function openPrivateModal() { document.getElementById('privateModal').classList.remove('hidden'); }
    function closePrivateModal() { document.getElementById('privateModal').classList.add('hidden'); }

    function openWithdrawalModal() { document.getElementById('withdrawalModal').classList.remove('hidden'); }
    function closeWithdrawalModal() { document.getElementById('withdrawalModal').classList.add('hidden'); }

    function openTransactionsModal() { document.getElementById('transactionsModal').classList.remove('hidden'); }
    function closeTransactionsModal() { document.getElementById('transactionsModal').classList.add('hidden'); }

    function openSupportModal() { document.getElementById('supportModal').classList.remove('hidden'); }
    function closeSupportModal() { document.getElementById('supportModal').classList.add('hidden'); }
</script>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
