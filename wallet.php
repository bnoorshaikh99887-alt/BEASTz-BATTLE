<?php
require_once __DIR__ . '/common/config.php';
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

// 1. Manual UPI Deposit Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_deposit') {
    $amount = (float)($_POST['amount'] ?? 0);
    $txn_id = sanitize($_POST['transaction_id'] ?? '');

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
            } else {
                $alert_msg = "Deposit submission failed: " . $conn->error;
                $alert_type = "error";
            }
            $dep->close();
        }
        $chk->close();
    }
}

// 2. Withdrawal Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_withdrawal') {
    $amount = (float)($_POST['amount'] ?? 0);

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
        $pend = $conn->prepare("SELECT id FROM withdrawals WHERE user_id = ? AND LOWER(status) = 'pending'");
        $pend->bind_param("i", $user_id);
        $pend->execute();
        if ($pend->get_result()->num_rows > 0) {
            $alert_msg = "Aapki ek withdrawal request pehle se pending hai.";
            $alert_type = "error";
        } else {
            $conn->begin_transaction();
            try {
                // Deduct balance upfront
                $ded = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
                $ded->bind_param("di", $amount, $user_id);
                $ded->execute();
                $ded->close();

                // Add withdrawal record
                $w_ins = $conn->prepare("INSERT INTO withdrawals (user_id, amount, upi_id, status) VALUES (?, ?, ?, 'Pending')");
                $w_ins->bind_param("ids", $user_id, $amount, $saved_upi_id);
                $w_ins->execute();
                $w_ins->close();

                // Passbook entry
                $t_desc = "Withdrawal Requested (Payout to " . $saved_upi_id . ")";
                $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
                $tx->bind_param("ids", $user_id, $amount, $t_desc);
                $tx->execute();
                $tx->close();

                $conn->commit();
                $current_balance -= $amount;
                $alert_msg = "🎉 Withdrawal request for " . format_inr($amount) . " submitted! Payout will be sent to " . htmlspecialchars($saved_upi_id);
                $alert_type = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $alert_msg = "Withdrawal submission error: " . $e->getMessage();
                $alert_type = "error";
            }
        }
        $pend->close();
    }
}

require_once __DIR__ . '/common/header.php';

// Fetch Transactions
$tx_stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 20");
$tx_stmt->bind_param("i", $user_id);
$tx_stmt->execute();
$transactions = $tx_stmt->get_result();
?>

<!-- Alerts -->
<?php if (!empty($alert_msg)): ?>
    <div class="mb-4 p-3.5 rounded-xl text-xs font-semibold flex items-center gap-2 <?= ($alert_type === 'success') ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : (($alert_type === 'info') ? 'bg-sky-500/20 text-sky-300 border border-sky-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30') ?>">
        <i class="fa-solid <?= ($alert_type === 'success') ? 'fa-circle-check text-emerald-400' : (($alert_type === 'info') ? 'fa-circle-info text-sky-400' : 'fa-circle-exclamation text-rose-400') ?>"></i>
        <span><?= htmlspecialchars($alert_msg); ?></span>
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
                <i class="fa-solid fa-rotate-left text-2xl text-slate-500 mb-2 block"></i>
                No transaction records found yet.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal 1: Choose Deposit Option -->
<div id="depositChoiceModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeDepositChoiceModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <h3 class="text-sm font-bold text-white mb-1">Add Money to Wallet</h3>
        <p class="text-[11px] text-slate-400 mb-4">Choose your preferred deposit method</p>

        <div class="space-y-3">
            <!-- Option 1: Instant Cashfree Gateway -->
            <button type="button" onclick="openCashfreeModal()" class="w-full bg-slate-800 hover:bg-slate-750 border border-violet-500/40 rounded-2xl p-3.5 flex items-center gap-3.5 transition text-left group">
                <div class="w-10 h-10 rounded-xl bg-violet-600/20 text-violet-400 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div>
                    <span class="text-xs font-bold text-white group-hover:text-violet-300 block">Instant Gateway</span>
                    <span class="text-[10px] text-slate-400">Google Pay, PhonePe, Cards, NetBanking</span>
                </div>
            </button>

            <!-- Option 2: Direct UPI App -->
            <button type="button" onclick="openDirectUPIModal()" class="w-full bg-slate-800 hover:bg-slate-750 border border-slate-700 rounded-2xl p-3.5 flex items-center gap-3.5 transition text-left group">
                <div class="w-10 h-10 rounded-xl bg-emerald-600/20 text-emerald-400 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-mobile-screen"></i>
                </div>
                <div>
                    <span class="text-xs font-bold text-white group-hover:text-emerald-300 block">Instant UPI App (Paytm/GPay)</span>
                    <span class="text-[10px] text-slate-400">Pay directly to Admin UPI ID</span>
                </div>
            </button>

            <!-- Option 3: Scan QR Code -->
            <button type="button" onclick="openManualQRModal()" class="w-full bg-slate-800 hover:bg-slate-750 border border-slate-700 rounded-2xl p-3.5 flex items-center gap-3.5 transition text-left group">
                <div class="w-10 h-10 rounded-xl bg-amber-600/20 text-amber-400 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
                <div>
                    <span class="text-xs font-bold text-white group-hover:text-amber-300 block">Scan QR Code & UTR</span>
                    <span class="text-[10px] text-slate-400">Manual verification via 12-digit UTR</span>
                </div>
            </button>
        </div>
    </div>
</div>

<!-- Modal 2: Instant Cashfree Gateway -->
<div id="cashfreeModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeCashfreeModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <h3 class="text-sm font-bold text-white mb-1">Instant Payment Gateway</h3>
        <p class="text-[11px] text-slate-400 mb-4">Enter amount to add (Min ₹10.00)</p>

        <form method="POST" action="cashfree_create_order.php" class="space-y-3.5">
            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Amount (₹)</label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-slate-400 text-xs font-bold">₹</span>
                    <input type="number" name="amount" min="10" step="1" value="50" required class="w-full bg-slate-950 border border-slate-700 focus:border-violet-500 rounded-xl pl-7 pr-3 py-2 text-xs text-white focus:outline-none font-bold">
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" onclick="this.form.amount.value = 20" class="flex-1 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] font-bold rounded-lg">+₹20</button>
                <button type="button" onclick="this.form.amount.value = 50" class="flex-1 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] font-bold rounded-lg">+₹50</button>
                <button type="button" onclick="this.form.amount.value = 100" class="flex-1 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] font-bold rounded-lg">+₹100</button>
                <button type="button" onclick="this.form.amount.value = 200" class="flex-1 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] font-bold rounded-lg">+₹200</button>
            </div>

            <button type="submit" class="w-full py-2.5 bg-violet-600 hover:bg-violet-500 text-white font-extrabold rounded-xl text-xs transition shadow-md shadow-violet-600/30">
                Proceed to Payment
            </button>
        </form>
    </div>
</div>

<!-- Modal 3: Direct UPI App -->
<div id="directUPIModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeDirectUPIModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <h3 class="text-sm font-bold text-white mb-1">Direct UPI App Transfer</h3>
        <p class="text-[11px] text-slate-400 mb-3">Pay via any installed UPI app (Paytm, GPay, PhonePe)</p>

        <div class="bg-slate-950 p-3 rounded-2xl border border-slate-800 mb-3 space-y-1.5 text-xs">
            <div class="flex items-center justify-between text-slate-400">
                <span>Admin UPI ID:</span>
                <span class="font-mono font-bold text-white select-all"><?= htmlspecialchars($admin_upi); ?></span>
            </div>
        </div>

        <div class="space-y-3">
            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Deposit Amount (₹)</label>
                <input type="number" id="directAmtInput" min="10" step="1" value="50" class="w-full bg-slate-950 border border-slate-700 focus:border-emerald-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none font-bold">
            </div>

            <button type="button" onclick="payViaUPIIntent()" class="w-full py-2.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black rounded-xl text-xs shadow-lg transition">
                <i class="fa-solid fa-bolt mr-1"></i> Open in UPI App & Pay
            </button>

            <!-- After paying, enter UTR form -->
            <form method="POST" action="wallet.php" class="pt-2 border-t border-slate-800 space-y-2">
                <input type="hidden" name="action" value="request_deposit">
                <input type="hidden" name="amount" id="formDirectAmt" value="50">
                <div>
                    <label class="block text-[10px] font-medium text-slate-400 mb-1">Enter 12-digit UTR after payment:</label>
                    <input type="text" name="transaction_id" required placeholder="e.g. 429381748291" class="w-full bg-slate-950 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none font-mono">
                </div>
                <button type="submit" class="w-full py-2 bg-slate-800 hover:bg-slate-750 text-white font-bold rounded-xl text-xs transition">
                    Submit UTR for Verification
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Modal 4: Manual QR Code & UTR -->
<div id="manualQRModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-2xl relative max-h-[90vh] overflow-y-auto">
        <button type="button" onclick="closeManualQRModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <h3 class="text-sm font-bold text-white mb-1">Scan UPI QR Code</h3>
        <p class="text-[11px] text-slate-400 mb-3">Scan with any UPI app and submit your 12-digit UTR ID</p>

        <div class="bg-white p-3 rounded-2xl max-w-[200px] mx-auto mb-3 shadow-inner text-center">
            <?php if (!empty($admin_qr)): ?>
                <img src="<?= htmlspecialchars($admin_qr); ?>" alt="Admin QR Code" class="w-full h-auto rounded-lg">
            <?php else: ?>
                <!-- Dynamic QR via UPI URI -->
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode('upi://pay?pa=' . $admin_upi . '&pn=BEASTzBATTLE&cu=INR'); ?>" alt="UPI QR Code" class="w-full h-auto rounded-lg">
            <?php endif; ?>
            <span class="text-[9px] text-slate-700 font-bold block mt-1">Scan & Pay Any Amount</span>
        </div>

        <form method="POST" action="wallet.php" class="space-y-3">
            <input type="hidden" name="action" value="request_deposit">
            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Amount Paid (₹) *</label>
                <input type="number" name="amount" min="10" step="1" required placeholder="Amount you paid" class="w-full bg-slate-950 border border-slate-700 focus:border-amber-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none font-bold">
            </div>

            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">12-Digit UPI Ref / UTR ID *</label>
                <input type="text" name="transaction_id" required placeholder="From receipt: 12 digit number" class="w-full bg-slate-950 border border-slate-700 focus:border-amber-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none font-mono">
            </div>

            <button type="submit" class="w-full py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black rounded-xl text-xs transition shadow-md">
                Submit Deposit Request
            </button>
        </form>
    </div>
</div>

<!-- Modal 5: Withdraw Modal -->
<div id="withdrawModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeWithdrawModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <h3 class="text-sm font-bold text-white mb-1">Withdraw to Bank / UPI</h3>
        <p class="text-[11px] text-slate-400 mb-3">Available: <span class="font-bold text-emerald-400 font-mono"><?= format_inr($current_balance); ?></span></p>

        <?php if (empty($saved_upi_id)): ?>
            <div class="bg-amber-500/10 border border-amber-500/30 rounded-2xl p-4 text-center mb-3">
                <i class="fa-solid fa-triangle-exclamation text-amber-400 text-xl mb-1"></i>
                <p class="text-xs text-amber-300 font-bold">UPI ID Required</p>
                <p class="text-[10px] text-slate-400 mt-1 mb-3">Please save your receiving UPI ID in Profile before requesting payout.</p>
                <a href="profile.php" class="inline-block px-3 py-1.5 bg-amber-500 text-slate-950 rounded-lg text-xs font-bold">Go to Profile</a>
            </div>
        <?php else: ?>
            <form method="POST" action="wallet.php" class="space-y-3">
                <input type="hidden" name="action" value="request_withdrawal">
                <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 text-[11px]">
                    <span class="text-slate-400 block text-[10px]">Payout UPI ID:</span>
                    <span class="text-white font-mono font-bold"><?= htmlspecialchars($saved_upi_id); ?></span>
                </div>

                <div>
                    <label class="block text-[11px] font-medium text-slate-300 mb-1">Amount to Withdraw (₹) *</label>
                    <input type="number" name="amount" min="50" max="<?= $current_balance; ?>" step="1" value="50" required class="w-full bg-slate-950 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none font-bold">
                    <span class="text-[10px] text-slate-500 mt-1 block">Minimum withdrawal: ₹50.00</span>
                </div>

                <button type="submit" class="w-full py-2.5 bg-violet-600 hover:bg-violet-500 text-white font-bold rounded-xl text-xs transition">
                    Request Payout
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
        const upiUrl = "upi://pay?pa=" + encodeURIComponent(upiId) + "&pn=BEASTzBATTLE&am=" + encodeURIComponent(amt) + "&cu=INR&tn=BEASTzBATTLE_Deposit";
        window.location.href = upiUrl;
    }
</script>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
