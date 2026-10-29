<?php
require_once __DIR__ . '/common/header.php';
check_user_auth();

$user_id = (int)$_SESSION['user_id'];
$alert_msg = "";
$alert_type = "";

if (isset($_GET['error'])) {
    $alert_msg = sanitize($_GET['error']);
    $alert_type = "error";
} elseif (isset($_GET['success'])) {
    $alert_msg = sanitize($_GET['success']);
    $alert_type = "success";
} elseif (isset($_GET['info'])) {
    $alert_msg = sanitize($_GET['info']);
    $alert_type = "info";
}

// Fetch Current User Details
$u_stmt = $conn->prepare("SELECT username, email, wallet_balance, upi_id FROM users WHERE id = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$user_info = $u_stmt->get_result()->fetch_assoc();
$current_balance = (float)($user_info['wallet_balance'] ?? 0.00);
$saved_upi_id = $user_info['upi_id'] ?? '';
$u_stmt->close();

// Fetch Admin Settings
$admin_upi = "9878385810@upi";
$admin_qr = "";
$set_res = $conn->query("SELECT setting_key, setting_value, `key`, `value` FROM settings WHERE setting_key IN ('admin_upi_id', 'admin_qr_image') OR `key` IN ('admin_upi_id', 'admin_qr_image')");
if ($set_res) {
    while ($s = $set_res->fetch_assoc()) {
        $k = $s['setting_key'] ?? $s['key'];
        $v = !empty($s['setting_value']) ? $s['setting_value'] : ($s['value'] ?? '');
        if ($k === 'admin_upi_id' && !empty($v)) $admin_upi = $v;
        if ($k === 'admin_qr_image' && !empty($v)) $admin_qr = $v;
    }
}

// Fetch Recent Transactions
$history_query = "SELECT * FROM transactions WHERE user_id = {$user_id} ORDER BY id DESC LIMIT 15";
$history_res = $conn->query($history_query);
?>

<!-- Alert Feedback -->
<?php if (!empty($alert_msg)): ?>
    <div class="mb-4 p-3.5 rounded-2xl text-xs font-semibold flex items-center gap-2 <?= ($alert_type === 'success') ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : (($alert_type === 'info') ? 'bg-blue-50 text-blue-800 border border-blue-200' : 'bg-rose-50 text-rose-800 border border-rose-200') ?>">
        <i class="fa-solid <?= ($alert_type === 'success') ? 'fa-circle-check text-emerald-500' : (($alert_type === 'info') ? 'fa-circle-info text-blue-500' : 'fa-circle-exclamation text-rose-500') ?> text-sm"></i>
        <span><?= htmlspecialchars($alert_msg); ?></span>
    </div>
<?php endif; ?>

<!-- Wallet Card (White Card with Yellow Accents) -->
<div class="bg-white border border-slate-200 rounded-3xl p-5 mb-5 shadow-sm relative overflow-hidden">
    <div class="flex items-center justify-between mb-4">
        <div>
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Available Balance</span>
            <div class="text-3xl font-black text-slate-900 font-mono mt-0.5">
                <?= format_inr($current_balance); ?>
            </div>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-yellow-400 text-slate-950 flex items-center justify-center text-xl shadow-xs font-black">
            <i class="fa-solid fa-wallet"></i>
        </div>
    </div>

    <!-- Quick Actions (Yellow Add Money & Green Withdraw) -->
    <div class="grid grid-cols-2 gap-2.5">
        <button type="button" onclick="openDepositModal()" class="py-2.5 px-3 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs shadow-xs transition active:scale-95 flex items-center justify-center gap-1.5">
            <i class="fa-solid fa-circle-plus text-xs"></i>
            <span>Add Money</span>
        </button>

        <a href="profile.php" class="py-2.5 px-3 bg-slate-100 hover:bg-slate-200 text-slate-800 font-extrabold rounded-xl text-xs transition flex items-center justify-center gap-1.5 border border-slate-200">
            <i class="fa-solid fa-paper-plane text-xs text-slate-600"></i>
            <span>Withdraw</span>
        </a>
    </div>
</div>

<!-- Transaction Passbook -->
<div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
    <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-100">
        <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
            <i class="fa-solid fa-receipt text-yellow-500"></i> Passbook History
        </h3>
        <span class="text-[10px] text-slate-400 font-medium">Recent 15 Entries</span>
    </div>

    <div class="space-y-3">
        <?php if ($history_res && $history_res->num_rows > 0): ?>
            <?php while ($tx = $history_res->fetch_assoc()): ?>
                <?php $is_cr = ($tx['type'] === 'credit'); ?>
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg <?= $is_cr ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-600'; ?> flex items-center justify-center text-xs">
                            <i class="fa-solid <?= $is_cr ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i>
                        </div>
                        <div>
                            <span class="font-bold text-slate-900 block leading-tight truncate max-w-[170px]">
                                <?= htmlspecialchars($tx['description']); ?>
                            </span>
                            <span class="text-[10px] text-slate-400 font-mono">
                                <?= date('d M, h:i A', strtotime($tx['created_at'])); ?>
                            </span>
                        </div>
                    </div>

                    <div class="text-right">
                        <span class="font-mono font-black text-xs <?= $is_cr ? 'text-emerald-600' : 'text-slate-700'; ?>">
                            <?= $is_cr ? '+' : '-' ?><?= format_inr($tx['amount']); ?>
                        </span>
                        <span class="block text-[9px] font-bold text-emerald-600">SUCCESS</span>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center py-6 text-slate-400">
                <i class="fa-solid fa-rotate-right text-xl mb-1 text-slate-300"></i>
                <p class="text-xs">No transaction records found yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Add Money / Deposit Choice -->
<div id="depositChoiceModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeDepositModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <h3 class="text-sm font-extrabold text-slate-900 mb-1">Add Money to Wallet</h3>
        <p class="text-[11px] text-slate-400 mb-4">Choose your preferred deposit method</p>

        <div class="space-y-3">
            <!-- Option 1: Instant Cashfree Payment Gateway -->
            <button type="button" onclick="openCashfreeModal()" class="w-full bg-slate-50 hover:bg-yellow-50/60 border border-slate-200 hover:border-yellow-400 rounded-2xl p-3.5 flex items-center gap-3.5 transition text-left group">
                <div class="w-10 h-10 rounded-xl bg-yellow-400 text-slate-950 flex items-center justify-center text-lg font-black shadow-xs">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div>
                    <span class="text-xs font-bold text-slate-900 block">Instant Payment Gateway</span>
                    <span class="text-[10px] text-slate-500">GPay, PhonePe, Paytm, Cards, UPI (Auto-Credit)</span>
                </div>
            </button>

            <!-- Option 2: Direct UPI QR Code -->
            <button type="button" onclick="openQRModal()" class="w-full bg-slate-50 hover:bg-yellow-50/60 border border-slate-200 hover:border-yellow-400 rounded-2xl p-3.5 flex items-center gap-3.5 transition text-left group">
                <div class="w-10 h-10 rounded-xl bg-slate-200 text-slate-800 flex items-center justify-center text-lg font-bold">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
                <div>
                    <span class="text-xs font-bold text-slate-900 block">Scan UPI QR Code</span>
                    <span class="text-[10px] text-slate-500">Pay via scanner & submit 12-digit UTR</span>
                </div>
            </button>
        </div>
    </div>
</div>

<!-- Modal: Instant Cashfree Gateway -->
<div id="cashfreeModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeCashfreeModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <h3 class="text-sm font-extrabold text-slate-900 mb-1">Instant Cashfree Gateway</h3>
        <p class="text-[11px] text-slate-400 mb-4">Enter amount to add (Min ₹10.00)</p>

        <form method="POST" action="cashfree_create_order.php" class="space-y-3.5" onsubmit="var b=this.querySelector('button[type=submit]'); if(b){ b.disabled=true; b.innerHTML='<i class=\'fa-solid fa-spinner fa-spin mr-1.5\'></i> Opening Cashfree...'; }">
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Amount (₹)</label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-slate-400 text-xs font-bold">₹</span>
                    <input type="number" name="amount" min="10" step="1" value="50" required class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 rounded-xl pl-7 pr-3 py-2 text-xs text-slate-900 font-extrabold focus:outline-none">
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" onclick="this.form.amount.value = 20" class="flex-1 py-1 bg-slate-100 hover:bg-yellow-100 text-slate-700 text-[10px] font-bold rounded-lg">+₹20</button>
                <button type="button" onclick="this.form.amount.value = 50" class="flex-1 py-1 bg-slate-100 hover:bg-yellow-100 text-slate-700 text-[10px] font-bold rounded-lg">+₹50</button>
                <button type="button" onclick="this.form.amount.value = 100" class="flex-1 py-1 bg-slate-100 hover:bg-yellow-100 text-slate-700 text-[10px] font-bold rounded-lg">+₹100</button>
                <button type="button" onclick="this.form.amount.value = 200" class="flex-1 py-1 bg-slate-100 hover:bg-yellow-100 text-slate-700 text-[10px] font-bold rounded-lg">+₹200</button>
            </div>

            <button type="submit" class="w-full py-3 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs transition shadow-md shadow-yellow-400/20 active:scale-95 flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-lock text-xs"></i>
                <span>Proceed to Cashfree Payment</span>
            </button>
        </form>
    </div>
</div>

<!-- Modal: QR Code Deposit -->
<div id="qrModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative text-center">
        <button type="button" onclick="closeQRModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <h3 class="text-sm font-extrabold text-slate-900 mb-1">Scan & Pay via UPI</h3>
        <p class="text-[11px] text-slate-400 mb-3">Scan QR on GPay / PhonePe / Paytm</p>

        <div class="p-3 bg-slate-50 border border-slate-200 rounded-2xl inline-block mb-3">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode("upi://pay?pa={$admin_upi}&pn=BEASTzBATTLE&cu=INR"); ?>" alt="UPI QR" class="w-40 h-40 mx-auto rounded-lg">
        </div>

        <div class="bg-slate-100 p-2 rounded-xl mb-3 text-xs font-mono font-bold text-slate-800">
            UPI: <?= htmlspecialchars($admin_upi); ?>
        </div>

        <form method="POST" action="payment_verify.php" class="space-y-2.5 text-left">
            <div>
                <label class="block text-[10px] font-bold text-slate-600 mb-1">Deposited Amount (₹)</label>
                <input type="number" name="amount" min="10" required placeholder="50" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-xs text-slate-900 font-bold focus:outline-none focus:border-yellow-400">
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-600 mb-1">12-Digit UPI Ref / UTR No</label>
                <input type="text" name="transaction_id" required placeholder="e.g. 429381920192" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-xs text-slate-900 font-mono focus:outline-none focus:border-yellow-400">
            </div>

            <button type="submit" class="w-full py-2.5 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs transition shadow-sm">
                Submit Deposit UTR
            </button>
        </form>
    </div>
</div>

<script>
    function openDepositModal() { document.getElementById('depositChoiceModal').classList.remove('hidden'); }
    function closeDepositModal() { document.getElementById('depositChoiceModal').classList.add('hidden'); }

    function openCashfreeModal() {
        closeDepositModal();
        document.getElementById('cashfreeModal').classList.remove('hidden');
    }
    function closeCashfreeModal() { document.getElementById('cashfreeModal').classList.add('hidden'); }

    function openQRModal() {
        closeDepositModal();
        document.getElementById('qrModal').classList.remove('hidden');
    }
    function closeQRModal() { document.getElementById('qrModal').classList.add('hidden'); }
</script>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
