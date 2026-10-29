<?php
require_once __DIR__ . '/common/config.php';
check_user_auth();

$user_id = (int)$_SESSION['user_id'];
$alert_msg = isset($_GET['error']) ? sanitize($_GET['error']) : "";
$alert_type = isset($_GET['error']) ? "error" : "";

if (isset($_GET['success'])) {
    $alert_msg = sanitize($_GET['success']);
    $alert_type = "success";
}

// Fetch Current User Details
$u_stmt = $conn->prepare("SELECT username, email, wallet_balance, upi_id FROM users WHERE id = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$user_info = $u_stmt->get_result()->fetch_assoc();
$current_balance = (float)$user_info['wallet_balance'];
$saved_upi_id = $user_info['upi_id'] ?? '';
$u_stmt->close();

// Fetch Admin Settings
$admin_upi = "adeptplay@upi";
$admin_qr = "";
$set_res = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('admin_upi_id', 'admin_qr_image')");
if ($set_res) {
    while ($s = $set_res->fetch_assoc()) {
        if ($s['setting_key'] === 'admin_upi_id' && !empty($s['setting_value'])) $admin_upi = $s['setting_value'];
        if ($s['setting_key'] === 'admin_qr_image' && !empty($s['setting_value'])) $admin_qr = $s['setting_value'];
    }
}

// 1. Manual Deposit Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_deposit') {
    $amount = (float)$_POST['amount'];
    $txn_id = sanitize($_POST['transaction_id']);

    if ($amount < 10) {
        $alert_msg = "Minimum deposit amount ₹10.00 hai.";
        $alert_type = "error";
    } elseif (empty($txn_id)) {
        $alert_msg = "Kripya 12-digit UPI Transaction / UTR ID daalein.";
        $alert_type = "error";
    } else {
        $chk = $conn->prepare("SELECT id FROM deposits WHERE transaction_id = ?");
        $chk->bind_param("s", $txn_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $alert_msg = "Yeh Transaction ID pehle se submit ho chuki hai.";
            $alert_type = "error";
        } else {
            $dep = $conn->prepare("INSERT INTO deposits (user_id, amount, transaction_id, status) VALUES (?, ?, ?, 'Pending')");
            $dep->bind_param("ids", $user_id, $amount, $txn_id);
            if ($dep->execute()) {
                $alert_msg = "Deposit request for " . format_inr($amount) . " submit ho gayi! Admin verify karega.";
                $alert_type = "success";
            }
            $dep->close();
        }
        $chk->close();
    }
}

// 2. Withdrawal Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_withdrawal') {
    $amount = (float)$_POST['amount'];
    if (empty($saved_upi_id)) {
        $alert_msg = "Withdrawal se pehle Profile mein apna UPI ID link karein.";
        $alert_type = "error";
    } elseif ($amount < 50) {
        $alert_msg = "Minimum withdrawal amount ₹50.00 hai.";
        $alert_type = "error";
    } elseif ($amount > $current_balance) {
        $alert_msg = "Wallet balance kam hai.";
        $alert_type = "error";
    } else {
        $pend = $conn->prepare("SELECT id FROM withdrawals WHERE user_id = ? AND status = 'Pending'");
        $pend->bind_param("i", $user_id);
        $pend->execute();
        if ($pend->get_result()->num_rows > 0) {
            $alert_msg = "Aapki ek withdrawal request pehle se pending hai.";
            $alert_type = "error";
        } else {
            $w_ins = $conn->prepare("INSERT INTO withdrawals (user_id, amount, upi_id, status) VALUES (?, ?, ?, 'Pending')");
            $w_ins->bind_param("ids", $user_id, $amount, $saved_upi_id);
            if ($w_ins->execute()) {
                $alert_msg = "Withdrawal request for " . format_inr($amount) . " sent! Payout will reach " . htmlspecialchars($saved_upi_id);
                $alert_type = "success";
            }
            $w_ins->close();
        }
        $pend->close();
    }
}

require_once __DIR__ . '/common/header.php';

// Fetch Transactions
$tx_stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$tx_stmt->bind_param("i", $user_id);
$tx_stmt->execute();
$transactions = $tx_stmt->get_result();
?>

<!-- Alerts -->
<?php if (!empty($alert_msg)): ?>
    <div class="mb-4 p-3.5 rounded-xl text-xs font-semibold flex items-center gap-2 <?= ($alert_type === 'success') ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' ?>">
        <i class="fa-solid <?= ($alert_type === 'success') ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400' ?>"></i>
        <span><?= $alert_msg; ?></span>
    </div>
<?php endif; ?>

<!-- Wallet Card -->
<div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-violet-600 via-indigo-700 to-slate-900 p-6 text-white shadow-2xl mb-5 border border-violet-400/20">
    <div class="flex items-center justify-between mb-4">
        <div>
            <span class="text-[11px] font-semibold tracking-wider text-violet-200 uppercase">Available Balance</span>
            <div class="text-3xl font-black font-mono tracking-tight text-white mt-1">
                <?= format_inr($current_balance); ?>
            </div>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center text-amber-300 text-xl shadow-inner">
            <i class="fa-solid fa-wallet"></i>
        </div>
    </div>

    <!-- Buttons -->
    <div class="grid grid-cols-2 gap-3 pt-2">
        <button type="button" onclick="openDepositSelectionModal()" class="py-2.5 px-4 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold rounded-xl text-xs shadow-lg shadow-emerald-500/20 transition flex items-center justify-center gap-2">
            <i class="fa-solid fa-circle-plus"></i> Add Money
        </button>
        <button type="button" onclick="openWithdrawModal()" class="py-2.5 px-4 bg-slate-900/80 hover:bg-slate-900 text-white font-extrabold rounded-xl text-xs border border-white/20 transition flex items-center justify-center gap-2">
            <i class="fa-solid fa-paper-plane"></i> Withdraw
        </button>
    </div>
</div>

<!-- Transaction History -->
<div class="mb-4">
    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 px-1 flex items-center gap-1.5">
        <i class="fa-solid fa-receipt text-violet-400"></i> Transaction Passbook
    </h3>

    <div class="space-y-2.5">
        <?php if ($transactions && $transactions->num_rows > 0): ?>
            <?php while ($tx = $transactions->fetch_assoc()): ?>
                <?php $is_credit = ($tx['type'] === 'credit'); ?>
                <div class="bg-slate-800/70 border border-slate-700/60 rounded-xl p-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg <?= $is_credit ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400' ?> flex items-center justify-center text-xs">
                            <i class="fa-solid <?= $is_credit ? 'fa-arrow-down-left' : 'fa-arrow-up-right' ?>"></i>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-white block line-clamp-1"><?= htmlspecialchars($tx['description']); ?></span>
                            <span class="text-[10px] text-slate-400"><?= date("d M Y, h:i A", strtotime($tx['created_at'])); ?></span>
                        </div>
                    </div>
                    <span class="text-xs font-mono font-bold <?= $is_credit ? 'text-emerald-400' : 'text-rose-400' ?>">
                        <?= ($is_credit ? '+' : '-') . format_inr($tx['amount']); ?>
                    </span>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="bg-slate-800/40 border border-slate-800 rounded-xl p-6 text-center text-xs text-slate-400">
                <i class="fa-solid fa-clock-rotate-left text-2xl text-slate-600 mb-2"></i>
                <p>No transaction records found yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- POPUP 1: Deposit Method Selection -->
<div id="depositChoiceModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-sm rounded-2xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeDepositChoiceModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="text-center mb-4">
            <div class="w-10 h-10 mx-auto rounded-xl bg-violet-600/20 text-violet-400 flex items-center justify-center text-base mb-2">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <h3 class="text-base font-bold text-white">Deposit Options</h3>
            <p class="text-[11px] text-slate-400">Select payment method to add money</p>
        </div>

        <div class="space-y-3">
            <!-- Cashfree Instant Gateway -->
            <button type="button" onclick="openCashfreeModal()" class="w-full p-3.5 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white rounded-xl text-left border border-violet-400/30 shadow-lg flex items-center justify-between group transition">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center text-lg text-emerald-400">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    <div>
                        <div class="text-xs font-bold flex items-center gap-1.5">
                            Cashfree Gateway
                            <span class="text-[9px] bg-emerald-500 text-slate-950 font-black px-1.5 py-0.2 rounded">AUTO</span>
                        </div>
                        <p class="text-[10px] text-violet-200">Instant UPI, GPay, PhonePe & Cards</p>
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-xs text-violet-300"></i>
            </button>

            <!-- Direct UPI App (PhonePe / GPay Intent) -->
            <button type="button" onclick="openDirectUPIModal()" class="w-full p-3.5 bg-emerald-600/20 hover:bg-emerald-600/30 text-white rounded-xl text-left border border-emerald-500/30 shadow-md flex items-center justify-between group transition">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-emerald-500/20 flex items-center justify-center text-lg text-emerald-400">
                        <i class="fa-brands fa-google-pay"></i>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-emerald-300">Direct UPI App (PhonePe / GPay)</div>
                        <p class="text-[10px] text-slate-300">Opens app directly on your phone</p>
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-xs text-emerald-400"></i>
            </button>

            <!-- Manual UPI QR -->
            <button type="button" onclick="openManualQRModal()" class="w-full p-3.5 bg-slate-800 hover:bg-slate-700/80 text-white rounded-xl text-left border border-slate-700 shadow-md flex items-center justify-between group transition">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-slate-900 flex items-center justify-center text-lg text-amber-400">
                        <i class="fa-solid fa-qrcode"></i>
                    </div>
                    <div>
                        <div class="text-xs font-bold">Scan Admin QR & Submit UTR</div>
                        <p class="text-[10px] text-slate-400">Manual review by admin</p>
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-xs text-slate-500"></i>
            </button>
        </div>
    </div>
</div>

<!-- POPUP 2: Cashfree Deposit Form -->
<div id="cashfreeModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-sm rounded-2xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeCashfreeModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="text-center mb-4">
            <div class="w-10 h-10 mx-auto rounded-xl bg-violet-600/20 text-violet-400 flex items-center justify-center text-base mb-2">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <h3 class="text-base font-bold text-white">Cashfree Deposit</h3>
            <p class="text-[11px] text-slate-400">Auto-credited upon payment completion</p>
        </div>

        <form method="POST" action="cashfree_create_order.php" class="space-y-4">
            <div>
                <label class="block text-[11px] font-semibold text-slate-300 mb-1">Enter Deposit Amount (₹)</label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-slate-400 font-bold text-sm">₹</span>
                    <input type="number" name="amount" id="cfAmt" min="10" step="1" value="100" class="w-full bg-slate-800 border border-slate-700 focus:border-violet-500 rounded-xl pl-8 pr-3 py-2.5 text-sm font-bold text-white focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-4 gap-2">
                <button type="button" onclick="document.getElementById('cfAmt').value = 50" class="py-1.5 bg-slate-800 hover:bg-slate-700 text-xs font-bold rounded-lg text-slate-200">₹50</button>
                <button type="button" onclick="document.getElementById('cfAmt').value = 100" class="py-1.5 bg-slate-800 hover:bg-slate-700 text-xs font-bold rounded-lg text-slate-200">₹100</button>
                <button type="button" onclick="document.getElementById('cfAmt').value = 250" class="py-1.5 bg-slate-800 hover:bg-slate-700 text-xs font-bold rounded-lg text-slate-200">₹250</button>
                <button type="button" onclick="document.getElementById('cfAmt').value = 500" class="py-1.5 bg-slate-800 hover:bg-slate-700 text-xs font-bold rounded-lg text-slate-200">₹500</button>
            </div>

            <button type="submit" class="w-full py-3 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-bold rounded-xl text-xs transition shadow-lg flex items-center justify-center gap-2">
                <i class="fa-solid fa-lock"></i> Pay via Cashfree
            </button>
        </form>
    </div>
</div>

<!-- POPUP 3: Direct PhonePe/GPay Mobile Intent -->
<div id="directUPIModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-sm rounded-2xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeDirectUPIModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="text-center mb-3">
            <div class="w-10 h-10 mx-auto rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-base mb-2">
                <i class="fa-solid fa-mobile-screen-button"></i>
            </div>
            <h3 class="text-base font-bold text-white">Direct UPI App Transfer</h3>
            <p class="text-[11px] text-slate-400">One-tap to open GPay, PhonePe, or Paytm</p>
        </div>

        <div class="space-y-3">
            <div>
                <label class="block text-[11px] font-semibold text-slate-300 mb-1">Enter Amount to Pay (₹)</label>
                <input type="number" id="directAmtInput" min="10" value="100" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>

            <button type="button" onclick="payViaUPIIntent()" class="w-full py-3 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-black rounded-xl text-xs shadow-lg transition flex items-center justify-center gap-2">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Open PhonePe / GPay / Paytm
            </button>

            <form method="POST" action="wallet.php" class="pt-2 border-t border-slate-800 space-y-2">
                <input type="hidden" name="action" value="request_deposit">
                <input type="hidden" name="amount" id="formDirectAmt" value="100">

                <label class="block text-[10px] text-slate-400">After payment in app, enter 12-digit UTR:</label>
                <input type="text" name="transaction_id" required placeholder="Enter 12-digit UTR number" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">

                <button type="submit" class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-xl text-xs transition">
                    Submit UTR for Verification
                </button>
            </form>
        </div>
    </div>
</div>

<!-- POPUP 4: Manual QR -->
<div id="manualQRModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-sm rounded-2xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeManualQRModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="text-center mb-3">
            <h3 class="text-base font-bold text-white">Scan Admin QR</h3>
            <p class="text-[11px] text-slate-400">Scan QR, pay, then submit transaction ID</p>
        </div>

        <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 text-center mb-3">
            <?php if (!empty($admin_qr) && file_exists(__DIR__ . '/uploads/qr/' . $admin_qr)): ?>
                <img src="uploads/qr/<?= htmlspecialchars($admin_qr); ?>" alt="Admin QR" class="w-32 h-32 mx-auto object-contain rounded-lg border border-slate-700 p-1 bg-white mb-2">
            <?php else: ?>
                <div class="w-32 h-32 mx-auto bg-slate-800 border border-slate-700 rounded-lg flex flex-col items-center justify-center text-slate-500 mb-2">
                    <i class="fa-solid fa-qrcode text-3xl mb-1 text-slate-400"></i>
                    <span class="text-[9px]">GPay / PhonePe / Paytm</span>
                </div>
            <?php endif; ?>

            <div class="bg-slate-900 px-3 py-1.5 rounded-lg border border-slate-800 flex items-center justify-between">
                <span class="text-[11px] font-mono text-violet-400 select-all font-bold"><?= htmlspecialchars($admin_upi); ?></span>
                <span class="text-[10px] text-slate-400 uppercase font-bold">Admin UPI</span>
            </div>
        </div>

        <form method="POST" action="wallet.php" class="space-y-3">
            <input type="hidden" name="action" value="request_deposit">
            <div>
                <label class="block text-[11px] font-semibold text-slate-300 mb-1">Amount Paid (₹)</label>
                <input type="number" step="1" min="10" name="amount" required placeholder="e.g. 100" class="w-full bg-slate-800 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-300 mb-1">12-Digit UPI Transaction / UTR ID</label>
                <input type="text" name="transaction_id" required placeholder="Found in your payment app" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>
            <button type="submit" class="w-full py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold rounded-xl text-xs transition">
                Submit for Verification
            </button>
        </form>
    </div>
</div>

<!-- POPUP 5: Withdraw Modal -->
<div id="withdrawModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-sm rounded-2xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeWithdrawModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="text-center mb-4">
            <h3 class="text-base font-bold text-white">Withdraw Winning Cash</h3>
            <p class="text-[11px] text-slate-400">Direct transfer to your saved UPI ID</p>
        </div>

        <?php if (empty($saved_upi_id)): ?>
            <div class="bg-rose-500/20 border border-rose-500/30 rounded-xl p-3 text-xs text-rose-300 text-center mb-4">
                You haven't linked a UPI ID yet!
                <a href="profile.php" class="block mt-2 py-1.5 bg-violet-600 text-white font-bold rounded-lg text-xs">
                    Link UPI in Profile
                </a>
            </div>
        <?php else: ?>
            <div class="bg-slate-800/80 p-3 rounded-xl border border-slate-700 text-xs mb-3">
                <span class="text-slate-400 text-[10px] block">Receiving UPI ID:</span>
                <span class="font-mono font-bold text-emerald-400"><?= htmlspecialchars($saved_upi_id); ?></span>
            </div>

            <form method="POST" action="wallet.php" class="space-y-3">
                <input type="hidden" name="action" value="request_withdrawal">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-300 mb-1">Amount to Withdraw (₹)</label>
                    <input type="number" step="1" min="50" max="<?= $current_balance; ?>" name="amount" required placeholder="Min ₹50" class="w-full bg-slate-800 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
                </div>
                <button type="submit" class="w-full py-2.5 bg-violet-600 hover:bg-violet-500 text-white font-bold rounded-xl text-xs transition">
                    Confirm & Request Payout
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    function openDepositSelectionModal() { document.getElementById('depositChoiceModal').classList.remove('hidden'); }
    function closeDepositChoiceModal() { document.getElementById('depositChoiceModal').classList.add('hidden'); }

    function openCashfreeModal() { closeDepositChoiceModal(); document.getElementById('cashfreeModal').classList.remove('hidden'); }
    function closeCashfreeModal() { document.getElementById('cashfreeModal').classList.add('hidden'); }

    function openDirectUPIModal() { closeDepositChoiceModal(); document.getElementById('directUPIModal').classList.remove('hidden'); }
    function closeDirectUPIModal() { document.getElementById('directUPIModal').classList.add('hidden'); }

    function openManualQRModal() { closeDepositChoiceModal(); document.getElementById('manualQRModal').classList.remove('hidden'); }
    function closeManualQRModal() { document.getElementById('manualQRModal').classList.add('hidden'); }

    function openWithdrawModal() { document.getElementById('withdrawModal').classList.remove('hidden'); }
    function closeWithdrawModal() { document.getElementById('withdrawModal').classList.add('hidden'); }

    function payViaUPIIntent() {
        const amt = document.getElementById('directAmtInput').value;
        document.getElementById('formDirectAmt').value = amt;
        const upiId = "<?= htmlspecialchars($admin_upi); ?>";
        const upiUrl = "upi://pay?pa=" + encodeURIComponent(upiId) + "&pn=AdeptPlay&am=" + encodeURIComponent(amt) + "&cu=INR&tn=AdeptPlayDeposit";
        window.location.href = upiUrl;
    }
</script>

<?php require_once __DIR__ . '/common/bottom.php'; ?>