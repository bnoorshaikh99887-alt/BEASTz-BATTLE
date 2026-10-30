<?php
require_once __DIR__ . '/common/header.php';
check_user_auth();

$user_id = (int)$_SESSION['user_id'];
$alert_msg = "";
$alert_type = "";

// -------------------------------------------------------------
// 1. Handle Withdrawal Submission Directly on Wallet Page
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_withdrawal') {
    $amount = (float)($_POST['amount'] ?? 0.00);
    $upi_id = sanitize($_POST['upi_id'] ?? '');
    $min_wdr = (float)get_setting('min_withdrawal', '50.00');

    // Fetch fresh user balance
    $chk_stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $chk_stmt->bind_param("i", $user_id);
    $chk_stmt->execute();
    $fresh_u = $chk_stmt->get_result()->fetch_assoc();
    $chk_stmt->close();
    $avail_bal = (float)($fresh_u['wallet_balance'] ?? 0.00);

    if ($amount < $min_wdr) {
        safe_redirect("wallet.php?error=" . urlencode("Minimum withdrawal amount is " . format_inr($min_wdr) . "."));
        exit;
    }
    if (empty($upi_id) || !str_contains($upi_id, '@')) {
        safe_redirect("wallet.php?error=" . urlencode("Please enter a valid UPI ID (e.g. yourname@upi)."));
        exit;
    }
    if ($avail_bal < $amount) {
        safe_redirect("wallet.php?error=" . urlencode("Insufficient balance. You have " . format_inr($avail_bal) . " available."));
        exit;
    }

    $conn->begin_transaction();
    try {
        // Escrow: Deduct from wallet immediately
        $ded = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
        $ded->bind_param("di", $amount, $user_id);
        $ded->execute();
        $ded->close();

        // Record into withdrawals
        $w_stmt = $conn->prepare("INSERT INTO withdrawals (user_id, amount, upi_id, status) VALUES (?, ?, ?, 'Pending')");
        $w_stmt->bind_param("ids", $user_id, $amount, $upi_id);
        $w_stmt->execute();
        $w_stmt->close();

        // Record in transactions ledger
        $tx_desc = "Withdrawal Request (Pending to " . $upi_id . ")";
        $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
        $tx->bind_param("ids", $user_id, $amount, $tx_desc);
        $tx->execute();
        $tx->close();

        // Remember UPI in profile if not already set
        $conn->query("UPDATE users SET upi_id = '" . $conn->real_escape_string($upi_id) . "' WHERE id = {$user_id}");

        $conn->commit();
        safe_redirect("wallet.php?success=" . urlencode("Withdrawal request of " . format_inr($amount) . " submitted! Payout will be sent to " . $upi_id . " within 15-30 minutes."));
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        safe_redirect("wallet.php?error=" . urlencode("Withdrawal processing error: " . $e->getMessage()));
        exit;
    }
}

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
$admin_upi = get_setting('upi_id', '9878385810@upi');
if (empty($admin_upi)) $admin_upi = '9878385810@upi';
$admin_name = get_setting('upi_name', 'BEASTz BATTLE');

// Fetch Pending Deposits for this user
$pending_deposits = $conn->query("
    SELECT id, amount, transaction_id, status, created_at 
    FROM deposits 
    WHERE user_id = {$user_id} AND LOWER(status) = 'pending' 
    ORDER BY id DESC 
    LIMIT 5
");

// Fetch Pending Withdrawals for this user
$pending_withdrawals = $conn->query("
    SELECT id, amount, upi_id, status, created_at 
    FROM withdrawals 
    WHERE user_id = {$user_id} AND LOWER(status) = 'pending' 
    ORDER BY id DESC 
    LIMIT 5
");

// Fetch Recent Completed Transactions
$history_query = "SELECT * FROM transactions WHERE user_id = {$user_id} ORDER BY id DESC LIMIT 15";
$history_res = $conn->query($history_query);
?>

<!-- Alert Feedback -->
<?php if (!empty($alert_msg)): ?>
    <div class="mb-4 p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2 <?= ($alert_type === 'success') ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : (($alert_type === 'info') ? 'bg-blue-50 text-blue-800 border border-blue-200' : 'bg-rose-50 text-rose-800 border border-rose-200') ?>">
        <i class="fa-solid <?= ($alert_type === 'success') ? 'fa-circle-check text-emerald-600' : (($alert_type === 'info') ? 'fa-circle-info text-blue-600' : 'fa-circle-exclamation text-rose-600') ?>"></i>
        <span><?= htmlspecialchars($alert_msg); ?></span>
    </div>
<?php endif; ?>

<!-- Wallet Balance Card (Matching User Request: Compact Currency e.g. 1k, 1.2k, 10M) -->
<div class="bg-white border border-slate-200 rounded-3xl p-5 mb-5 shadow-xs relative overflow-hidden">
    <div class="flex items-center justify-between mb-4">
        <div>
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Available Balance</span>
            <div class="text-3xl font-black text-slate-900 font-mono mt-0.5 tracking-tight">
                <?= format_compact_inr($current_balance); ?>
            </div>
            <span class="text-[10px] font-bold text-slate-400 block mt-0.5">
                Full: <?= format_inr($current_balance); ?>
            </span>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-yellow-400 text-slate-950 flex items-center justify-center text-xl shadow-xs font-black">
            <i class="fa-solid fa-wallet"></i>
        </div>
    </div>

    <!-- Quick Actions (Yellow Add Money & In-Modal Withdraw) -->
    <div class="grid grid-cols-2 gap-2.5">
        <button type="button" onclick="openDepositModal()" class="py-2.5 px-3 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs shadow-xs transition active:scale-95 flex items-center justify-center gap-1.5">
            <i class="fa-solid fa-circle-plus text-xs"></i>
            <span>Add Money</span>
        </button>
        <button type="button" onclick="openWithdrawModal()" class="py-2.5 px-3 bg-slate-100 hover:bg-slate-200 text-slate-800 font-extrabold rounded-xl text-xs transition flex items-center justify-center gap-1.5 border border-slate-200 active:scale-95">
            <i class="fa-solid fa-paper-plane text-xs text-slate-600"></i>
            <span>Withdraw</span>
        </button>
    </div>
</div>

<!-- Pending Payout Requests Alert -->
<?php if ($pending_withdrawals && $pending_withdrawals->num_rows > 0): ?>
    <div class="bg-blue-50/80 border border-blue-200 rounded-2xl p-4 mb-4 shadow-xs space-y-2">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-black text-blue-900 uppercase tracking-wider flex items-center gap-1.5">
                <i class="fa-solid fa-clock-rotate-left text-blue-600"></i> Pending Withdrawals
            </h3>
            <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-blue-100 text-blue-800">Processing</span>
        </div>
        <div class="space-y-1.5 pt-1">
            <?php while ($pw = $pending_withdrawals->fetch_assoc()): ?>
                <div class="bg-white p-2.5 rounded-xl border border-blue-100 flex items-center justify-between text-xs">
                    <div>
                        <span class="font-black text-slate-900 block"><?= format_inr($pw['amount']); ?></span>
                        <span class="text-[10px] font-mono text-slate-500">UPI: <?= htmlspecialchars($pw['upi_id']); ?></span>
                    </div>
                    <span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">
                        Pending Admin Transfer
                    </span>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Pending UPI UTR Deposits (Awaiting Admin Approval) -->
<?php if ($pending_deposits && $pending_deposits->num_rows > 0): ?>
    <div class="bg-amber-50/80 border border-amber-300 rounded-2xl p-4 mb-5 shadow-xs space-y-2.5">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-black text-amber-900 uppercase tracking-wider flex items-center gap-1.5">
                <i class="fa-solid fa-clock-rotate-left text-amber-600"></i> Pending UTR Verifications
            </h3>
            <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-amber-200 text-amber-900">In Review</span>
        </div>
        <p class="text-[11px] text-amber-800 leading-tight">
            Your manual deposit is submitted. Admin is verifying the UTR on banking portal.
        </p>
        <div class="space-y-2 pt-1">
            <?php while ($pd = $pending_deposits->fetch_assoc()): ?>
                <div class="bg-white p-2.5 rounded-xl border border-amber-200 flex items-center justify-between text-xs">
                    <div>
                        <span class="font-extrabold text-slate-900 block"><?= format_inr($pd['amount']); ?></span>
                        <span class="text-[10px] font-mono text-slate-500">UTR: <?= htmlspecialchars($pd['transaction_id']); ?></span>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-300">
                        Pending Admin Credit
                    </span>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Passbook History (Recent 15 Entries Matching Video) -->
<div class="space-y-3">
    <div class="flex items-center justify-between px-1">
        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
            <i class="fa-solid fa-receipt text-yellow-500"></i> Passbook History
        </span>
        <span class="text-[10px] text-slate-400 font-semibold">Recent 15 Entries</span>
    </div>

    <div class="space-y-2">
        <?php if ($history_res && $history_res->num_rows > 0): ?>
            <?php while ($tx = $history_res->fetch_assoc()): ?>
                <?php
                    $is_cr = ($tx['type'] === 'credit');
                ?>
                <div class="bg-white p-3.5 rounded-2xl border border-slate-200 flex items-center justify-between shadow-xs">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl flex items-center justify-center text-xs <?= $is_cr ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700' ?>">
                            <i class="fa-solid <?= $is_cr ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i>
                        </div>
                        <div>
                            <span class="font-bold text-slate-800 text-xs block leading-tight">
                                <?= htmlspecialchars($tx['description'] ?? 'Transaction'); ?>
                            </span>
                            <span class="text-[10px] text-slate-400 block mt-0.5">
                                <?= date("d M Y, h:i A", strtotime($tx['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="font-mono font-black text-xs <?= $is_cr ? 'text-emerald-600' : 'text-slate-700'; ?>">
                            <?= $is_cr ? '+' : '-' ?><?= format_compact_inr($tx['amount']); ?>
                        </span>
                        <span class="block text-[9px] font-bold text-emerald-600">SUCCESS</span>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center py-10 bg-white rounded-3xl border border-slate-200 shadow-xs">
                <i class="fa-solid fa-receipt text-3xl text-slate-300 mb-2 block"></i>
                <span class="text-xs text-slate-400 font-bold">No transaction records found yet.</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL: WITHDRAW MONEY DIRECTLY (NO REDIRECT TO SETTINGS) -->
<!-- ============================================================= -->
<div id="withdrawModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeWithdrawModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="text-center mb-4">
            <div class="w-12 h-12 rounded-2xl bg-yellow-400 text-slate-950 flex items-center justify-center text-lg font-black mx-auto mb-2 shadow-xs">
                <i class="fa-solid fa-paper-plane"></i>
            </div>
            <h3 class="text-base font-extrabold text-slate-900">Withdraw Winnings</h3>
            <p class="text-[11px] text-slate-400">Direct transfer to your GPay / PhonePe / Paytm UPI</p>
        </div>

        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-3 mb-4 flex items-center justify-between text-xs">
            <span class="text-slate-500 font-semibold">Available Balance:</span>
            <span class="font-black text-slate-900 font-mono text-sm"><?= format_compact_inr($current_balance); ?></span>
        </div>

        <form method="POST" action="wallet.php" class="space-y-3.5">
            <input type="hidden" name="action" value="request_withdrawal">
            
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">
                    Withdrawal Amount (₹) <span class="text-slate-400 font-normal">(Min ₹50)</span>
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-slate-400 text-xs font-bold">₹</span>
                    <input type="number" name="amount" min="50" max="<?= max(50, (int)$current_balance); ?>" step="1" required placeholder="50" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-xl pl-7 pr-3 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">
                    Your UPI ID <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="upi_id" required value="<?= htmlspecialchars($saved_upi_id); ?>" placeholder="e.g. 9878385810@upi" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
                <p class="text-[10px] text-slate-400 mt-1">UPI payment will be transferred directly to this ID.</p>
            </div>

            <button type="submit" class="w-full py-3 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-check"></i>
                <span>Confirm & Request Payout</span>
            </button>
        </form>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL: DEPOSIT CHOICE -->
<!-- ============================================================= -->
<div id="depositChoiceModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeDepositModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <h3 class="text-sm font-extrabold text-slate-900 mb-1">Add Money to Wallet</h3>
        <p class="text-[11px] text-slate-400 mb-4">Choose your preferred deposit method</p>

        <div class="space-y-2.5">
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

            <!-- Option 2: Direct UPI QR Code & 12-Digit UTR -->
            <button type="button" onclick="openQRModal()" class="w-full bg-slate-50 hover:bg-yellow-50/60 border border-slate-200 hover:border-yellow-400 rounded-2xl p-3.5 flex items-center gap-3.5 transition text-left group">
                <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-800 flex items-center justify-center text-lg font-bold shadow-xs">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
                <div>
                    <span class="text-xs font-bold text-slate-900 block">Direct UPI QR / UTR Submission</span>
                    <span class="text-[10px] text-slate-500">Scan QR & submit 12-digit UPI reference number</span>
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
        <h3 class="text-sm font-extrabold text-slate-900 mb-1">Instant Gateway Deposit</h3>
        <p class="text-[11px] text-slate-400 mb-4">Enter amount to add (Min ₹10.00)</p>
        <form method="POST" action="cashfree_create_order.php" class="space-y-3.5" onsubmit="var b=this.querySelector('button[type=submit]'); if(b){ b.disabled=true; b.innerHTML='<i class=\'fa-solid fa-spinner fa-spin mr-1.5\'></i> Opening Cashfree...'; }">
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Amount (₹)</label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-slate-400 text-xs font-bold">₹</span>
                    <input type="number" name="amount" min="10" step="1" value="50" required class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 rounded-xl pl-7 pr-3 py-2 text-xs text-slate-900 font-extrabold focus:outline-none">
                </div>
            </div>
            <!-- Quick Amount Badges -->
            <div class="grid grid-cols-4 gap-2">
                <button type="button" onclick="document.querySelector('#cashfreeModal input[name=amount]').value=20" class="py-1.5 bg-slate-100 hover:bg-yellow-100 border border-slate-200 rounded-xl text-xs font-bold text-slate-700">₹20</button>
                <button type="button" onclick="document.querySelector('#cashfreeModal input[name=amount]').value=50" class="py-1.5 bg-slate-100 hover:bg-yellow-100 border border-slate-200 rounded-xl text-xs font-bold text-slate-700">₹50</button>
                <button type="button" onclick="document.querySelector('#cashfreeModal input[name=amount]').value=100" class="py-1.5 bg-slate-100 hover:bg-yellow-100 border border-slate-200 rounded-xl text-xs font-bold text-slate-700">₹100</button>
                <button type="button" onclick="document.querySelector('#cashfreeModal input[name=amount]').value=200" class="py-1.5 bg-slate-100 hover:bg-yellow-100 border border-slate-200 rounded-xl text-xs font-bold text-slate-700">₹200</button>
            </div>
            <button type="submit" class="w-full py-3 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs transition shadow-xs active:scale-95 flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-lock text-xs"></i>
                <span>Proceed to Cashfree Payment</span>
            </button>
        </form>
    </div>
</div>

<!-- Modal: QR Code & 12-Digit UTR Deposit -->
<div id="qrModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative text-center max-h-[90vh] overflow-y-auto">
        <button type="button" onclick="closeQRModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <h3 class="text-sm font-extrabold text-slate-900 mb-1">Scan & Pay via UPI</h3>
        <p class="text-[11px] text-slate-400 mb-3">Scan QR or copy UPI ID on GPay / PhonePe / Paytm</p>
        <div class="p-3 bg-slate-50 border border-slate-200 rounded-2xl inline-block mb-3">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode("upi://pay?pa={$admin_upi}&pn=" . urlencode($admin_name) . "&cu=INR"); ?>" alt="UPI QR" class="w-40 h-40 mx-auto rounded-lg">
        </div>
        <div class="bg-slate-100 p-2.5 rounded-xl mb-3 text-xs font-mono font-black text-slate-800 flex items-center justify-between">
            <span class="select-all">UPI: <?= htmlspecialchars($admin_upi); ?></span>
            <button type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($admin_upi); ?>'); alert('UPI ID copied to clipboard!');" class="text-[10px] bg-white border border-slate-300 px-2 py-0.5 rounded font-bold">Copy</button>
        </div>
        <form method="POST" action="payment_verify.php" class="space-y-3 text-left">
            <div>
                <label class="block text-[10px] font-bold text-slate-600 mb-1">Deposited Amount (₹)</label>
                <input type="number" name="amount" min="10" step="1" required placeholder="50" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:outline-none focus:border-yellow-400">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-600 mb-1">12-Digit UPI Reference / UTR Number</label>
                <input type="text" name="transaction_id" required minlength="6" placeholder="e.g. 429381920192" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none focus:border-yellow-400">
                <p class="text-[10px] text-slate-400 mt-1">Found in your payment app transaction details after sending money.</p>
            </div>
            <button type="submit" class="w-full py-3 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs transition shadow-xs active:scale-95 flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-paper-plane"></i>
                <span>Submit 12-Digit UTR for Verification</span>
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

    function openWithdrawModal() {
        document.getElementById('withdrawModal').classList.remove('hidden');
    }
    function closeWithdrawModal() {
        document.getElementById('withdrawModal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
