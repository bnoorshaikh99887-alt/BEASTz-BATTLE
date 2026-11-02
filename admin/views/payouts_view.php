<?php
// Handle AJAX Payout Approval / Rejection request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $w_id = (int)$_POST['withdrawal_id'];
    $act = $_POST['ajax_action'];

    $w_stmt = $conn->prepare("SELECT w.*, u.username, u.wallet_balance FROM withdrawals w LEFT JOIN users u ON w.user_id = u.id WHERE w.id = ?");
    $w_stmt->bind_param("i", $w_id);
    $w_stmt->execute();
    $w = $w_stmt->get_result()->fetch_assoc();
    $w_stmt->close();

    if ($w && (strtolower($w['status']) === 'pending')) {
        $user_id = (int)$w['user_id'];
        $amount = (float)$w['amount'];
        $upi_id = $w['upi_id'] ?? '';
        $is_host_request = (int)($w['is_admin_request'] ?? 0);
        $host_admin_id = (int)($w['admin_id'] ?? 0);

        if ($act === 'complete') {
            $conn->begin_transaction();
            try {
                $conn->query("UPDATE withdrawals SET status = 'Completed' WHERE id = $w_id");
                $safe_upi = $conn->real_escape_string($upi_id);
                $conn->query("UPDATE transactions SET description = 'Withdrawal Processed: Sent to {$safe_upi}' WHERE user_id = {$user_id} AND description LIKE 'Withdrawal Request (Pending%' AND amount = {$amount}");
                $conn->commit();
                
                // Send Push Notification
                if (file_exists(__DIR__ . "/../../common/onesignal_helper.php")) { 
                    require_once __DIR__ . "/../../common/onesignal_helper.php"; 
                } elseif (file_exists(__DIR__ . "/../common/onesignal_helper.php")) { 
                    require_once __DIR__ . "/../common/onesignal_helper.php"; 
                }
                
                $notif_title = "✅ Withdrawal Successfully Completed!";
                $notif_body = "₹" . number_format($amount, 2) . " has been successfully transferred to your UPI ID (" . $upi_id . ")!";
                $target_pids = [];
                if (!empty($w['player_id']) && strlen(trim($w['player_id'])) > 15) { $target_pids[] = trim($w['player_id']); }
                if ($user_id > 0) {
                    $d_res = $conn->query("SELECT player_id FROM user_devices WHERE user_id = {$user_id} AND player_id IS NOT NULL AND player_id != ''");
                    if ($d_res) { while ($dr = $d_res->fetch_assoc()) { if (!empty($dr['player_id'])) $target_pids[] = trim($dr['player_id']); } }
                }
                $target_pids = array_values(array_unique(array_filter($target_pids)));
                if (!empty($target_pids) && function_exists("send_onesignal_push")) {
                    @send_onesignal_push($notif_title, $notif_body, "/?tab=wallet", $target_pids, [$user_id], ["badge_type" => "PAYOUT_SUCCESS"], false);
                }

                echo json_encode(['success' => true, 'message' => 'Payout of ' . format_inr($amount) . ' marked as Completed!']);
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
        } elseif ($act === 'reject') {
            $conn->begin_transaction();
            try {
                if ($is_host_request && $host_admin_id > 0) {
                    $conn->query("UPDATE admin SET wallet_balance = wallet_balance + {$amount} WHERE id = {$host_admin_id}");
                } else {
                    $ref = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                    $ref->bind_param("di", $amount, $user_id);
                    $ref->execute();
                    $ref->close();
                    $conn->query("UPDATE transactions SET description = 'Withdrawal Request (Rejected)' WHERE user_id = {$user_id} AND description LIKE 'Withdrawal Request (Pending%' AND amount = {$amount}");
                }
                $conn->query("UPDATE withdrawals SET status = 'Rejected' WHERE id = $w_id");
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Withdrawal request rejected.']);
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
        }
    }
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Fetch Withdrawals (Pending first, then history)
$withdrawals = $conn->query("
    SELECT w.*, COALESCE(u.username, 'Host Admin') as username, COALESCE(u.wallet_balance, 0.00) as wallet_balance 
    FROM withdrawals w 
    LEFT JOIN users u ON w.user_id = u.id 
    ORDER BY CASE WHEN LOWER(w.status) = 'pending' THEN 0 ELSE 1 END, w.id DESC
");

// Count initial pending withdrawals for smart notification check
$count_res = $conn->query("SELECT COUNT(*) as cnt FROM withdrawals WHERE LOWER(status) = 'pending'");
$initial_pending_count = ($count_res) ? (int)$count_res->fetch_assoc()['cnt'] : 0;
?>

<div class="mb-5 flex items-center justify-between">
    <div>
        <h2 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2 font-display">
            <i class="fa-solid fa-hand-holding-dollar text-yellow-500"></i>
            <span>UPI Withdrawal Requests</span>
        </h2>
        <p class="text-xs text-slate-500">Send money to player UPI ID and mark payout completed</p>
    </div>
    <span class="text-xs font-bold px-3 py-1 bg-yellow-100 dark:bg-yellow-400/10 text-yellow-900 dark:text-yellow-400 border border-yellow-300 dark:border-yellow-400/30 rounded-full">
        Payouts Studio
    </span>
</div>

<!-- AJAX Success / Error Banner -->
<div id="payout-alert-box" class="hidden mb-5 p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2.5 transition-all"></div>

<!-- Tap-to-Refresh Notification Banner for New Withdrawals -->
<div id="new-withdrawal-banner" onclick="location.reload();" class="hidden mb-4 p-3 bg-gradient-to-r from-yellow-500 to-amber-500 text-slate-950 rounded-2xl text-xs font-black flex items-center justify-between shadow-lg cursor-pointer animate-pulse">
    <div class="flex items-center gap-2">
        <i class="fa-solid fa-bell text-sm"></i>
        <span>New Withdrawal Request Available! Tap here to refresh.</span>
    </div>
    <i class="fa-solid fa-rotate text-sm"></i>
</div>

<div id="admin-payouts-cards-container" class="space-y-3">
    <?php if ($withdrawals && $withdrawals->num_rows > 0): ?>
        <?php while ($w = $withdrawals->fetch_assoc()): ?>
            <?php 
                $is_pending = (strtolower($w['status']) === 'pending'); 
                $is_completed = (strtolower($w['status']) === 'completed');
                $is_host_req = ((int)($w['is_admin_request'] ?? 0) === 1);
            ?>
            <div id="payout-card-<?= $w['id']; ?>" class="bg-white dark:bg-[#131b2e] border <?= $is_host_req ? 'border-yellow-400 shadow-[0_0_20px_rgba(250,204,21,0.25)] ring-2 ring-yellow-400/30' : ($is_pending ? 'border-amber-400/60 ring-1 ring-amber-400/20' : 'border-slate-200 dark:border-slate-800') ?> rounded-2xl p-4 shadow-sm space-y-3 transition">
                
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-extrabold text-slate-900 dark:text-white">@<?= htmlspecialchars($w['username']); ?></span>
                            
                            <?php if ($is_host_req): ?>
                                <span class="px-2.5 py-0.5 rounded-full bg-yellow-400 text-slate-950 font-black text-[10px] uppercase shadow-md shadow-yellow-400/40 animate-pulse flex items-center gap-1">
                                    <i class="fa-solid fa-crown text-[9px]"></i>
                                    <span>HOST ADMIN PAYOUT</span>
                                </span>
                            <?php endif; ?>

                            <div id="badge-container-<?= $w['id']; ?>">
                                <?php if ($is_pending): ?>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-400/10 text-amber-800 dark:text-amber-400 border border-amber-300 dark:border-amber-400/35">
                                        Pending Payment
                                    </span>
                                <?php elseif ($is_completed): ?>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-400/10 text-emerald-800 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-400/30">
                                        Paid & Notified
                                    </span>
                                <?php else: ?>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-400/10 text-rose-800 dark:text-rose-400 border border-rose-300 dark:border-rose-400/30">
                                        Rejected / Refunded
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="text-[11px] text-slate-500 block mt-0.5">
                            <?= date("d M Y, h:i A", strtotime($w['created_at'])); ?> &bull; Ref #<?= $w['id']; ?>
                        </span>
                    </div>

                    <div class="text-right">
                        <span class="text-lg font-black font-mono text-emerald-600 dark:text-emerald-400 block">
                            <?= format_inr($w['amount']); ?>
                        </span>
                        <span class="text-[10px] text-slate-400">Withdrawal Amount</span>
                    </div>
                </div>

                <!-- UPI Details Box -->
                <div class="bg-slate-50 dark:bg-slate-900 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 text-xs flex justify-between items-center">
                    <div>
                        <span class="text-[10px] text-slate-400 block">Payout Destination UPI:</span>
                        <span class="font-mono font-bold text-slate-900 dark:text-white select-all text-xs">
                            <?= htmlspecialchars($w['upi_id']); ?>
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($w['upi_id']); ?>'); alert('UPI ID copied!');" class="px-2 py-1 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 hover:border-yellow-400 rounded-lg text-[10px] font-bold text-slate-700 dark:text-slate-300">
                            Copy UPI
                        </button>
                        <a href="upi://pay?pa=<?= urlencode($w['upi_id']); ?>&am=<?= $w['amount']; ?>&cu=INR" class="px-2 py-1 bg-yellow-400 hover:bg-yellow-500 text-slate-950 rounded-lg text-[10px] font-black">
                            Pay UPI
                        </a>
                    </div>
                </div>

                <!-- Approval / Rejection Actions container -->
                <div id="actions-container-<?= $w['id']; ?>">
                    <?php if ($is_pending): ?>
                        <div class="flex items-center gap-2 pt-1">
                            <button type="button" onclick="processPayout(<?= $w['id']; ?>, 'complete')" class="flex-1 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-1.5 cursor-pointer">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Approve & Send Push Alert</span>
                            </button>

                            <button type="button" onclick="processPayout(<?= $w['id']; ?>, 'reject')" class="py-2.5 px-3 bg-slate-100 dark:bg-slate-800 hover:bg-rose-500/20 text-rose-500 hover:text-rose-400 font-bold rounded-xl text-xs transition active:scale-95 border border-slate-200 dark:border-slate-700 cursor-pointer">
                                Reject
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-slate-800 rounded-2xl p-8 text-center text-slate-500 shadow-sm">
            <i class="fa-solid fa-money-bill-transfer text-3xl text-slate-300 dark:text-slate-700 mb-2 block"></i>
            <p class="text-xs font-bold">No withdrawal requests recorded yet.</p>
        </div>
    <?php endif; ?>
</div>

<!-- AJAX SCRIPT FOR SEAMLESS APPROVAL AND SMART TAP-TO-REFRESH NOTIFICATION -->
<script>
function processPayout(withdrawalId, actionType) {
    var confirmMsg = actionType === 'complete' ? "Approve and send targeted Push Notification?" : "Are you sure you want to reject this request?";
    if (!confirm(confirmMsg)) return;

    var card = document.getElementById('payout-card-' + withdrawalId);
    var actionsContainer = document.getElementById('actions-container-' + withdrawalId);
    if (actionsContainer) {
        actionsContainer.innerHTML = '<div class="text-center py-2 text-xs font-bold text-yellow-500"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Processing payout...</div>';
    }

    var formData = new URLSearchParams();
    formData.append('withdrawal_id', withdrawalId);
    formData.append('ajax_action', actionType);

    fetch('index.php?tab=payouts', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(res => res.json())
    .then(data => {
        var alertBox = document.getElementById('payout-alert-box');
        alertBox.classList.remove('hidden', 'bg-emerald-500/10', 'text-emerald-400', 'border-emerald-500/30', 'bg-rose-500/10', 'text-rose-400', 'border-rose-500/30');

        if (data.success) {
            alertBox.classList.add('bg-emerald-500/10', 'text-emerald-400', 'border', 'border-emerald-500/30');
            alertBox.innerHTML = '<i class="fa-solid fa-circle-check text-sm"></i> <span>' + data.message + '</span>';
            
            var badgeContainer = document.getElementById('badge-container-' + withdrawalId);
            if (badgeContainer) {
                if (actionType === 'complete') {
                    badgeContainer.innerHTML = '<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-400/10 text-emerald-800 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-400/30">Paid & Notified</span>';
                } else {
                    badgeContainer.innerHTML = '<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-400/10 text-rose-800 dark:text-rose-400 border border-rose-300 dark:border-rose-400/30">Rejected / Refunded</span>';
                }
            }
            if (actionsContainer) actionsContainer.innerHTML = '';

            var container = document.getElementById('admin-payouts-cards-container');
            if (container && card) {
                container.appendChild(card); // Move to bottom history list smoothly
            }
        } else {
            alertBox.classList.add('bg-rose-500/10', 'text-rose-400', 'border', 'border-rose-500/30');
            alertBox.innerHTML = '<i class="fa-solid fa-circle-exclamation text-sm"></i> <span>Error: ' + data.message + '</span>';
            if (actionsContainer) location.reload();
        }
    })
    .catch(err => {
        console.error(err);
        alert("Network error occurred.");
        location.reload();
    });
}

// SMART BACKGROUND CHECKER (Notifies when new withdrawal is placed without breaking active buttons)
let initialPendingCount = <?= $initial_pending_count; ?>;

setInterval(function() {
    if (document.activeElement && document.activeElement.tagName === 'INPUT') return;

    fetch(window.location.href)
        .then(res => res.text())
        .then(html => {
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');
            
            // Count pending forms in fetched HTML
            var newPendingCount = doc.querySelectorAll('form input[name="ajax_action"][value="complete"]').length;
            
            if (newPendingCount > initialPendingCount) {
                var banner = document.getElementById('new-withdrawal-banner');
                if (banner) {
                    banner.classList.remove('hidden');
                }
            }
        })
        .catch(err => {});
}, 6000);
</script>
