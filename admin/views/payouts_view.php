<?php
$msg = "";
$msg_type = "";

// Handle Standard Payout Approval / Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $w_id = (int)$_POST['withdrawal_id'];
    $act = $_POST['action'];

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
                // 1. Mark withdrawals table as completed
                $conn->query("UPDATE withdrawals SET status = 'Completed' WHERE id = $w_id");
                
                // 2. Update User's Passbook (transactions table)
                $safe_upi = $conn->real_escape_string($upi_id);
                $conn->query("UPDATE transactions SET description = 'Withdrawal Processed: Sent to {$safe_upi}' WHERE user_id = {$user_id} AND description LIKE 'Withdrawal Request (Pending%' AND amount = {$amount}");

                $conn->commit();
                
                $msg = "Payout of " . format_inr($amount) . " to " . htmlspecialchars($upi_id) . " marked as Completed!";
                $msg_type = "success";

                // ========================================================
                // ONESIGNAL PUSH NOTIFICATION DISPATCHER
                // ========================================================
                $helper_paths = [
                    __DIR__ . "/../../common/onesignal_helper.php",
                    __DIR__ . "/../common/onesignal_helper.php",
                    __DIR__ . "/common/onesignal_helper.php",
                    dirname(__DIR__, 2) . "/common/onesignal_helper.php"
                ];

                $helper_loaded = false;
                foreach ($helper_paths as $path) {
                    if (file_exists($path)) {
                        require_once $path;
                        $helper_loaded = true;
                        break;
                    }
                }

                $notif_title = "✅ Withdrawal Successfully Completed!";
                $notif_body = "₹" . number_format($amount, 2) . " has been successfully transferred to your UPI ID (" . $upi_id . ")!";

                // Fetch registered player_ids directly for this user
                $target_pids = [];
                if ($user_id > 0) {
                    $d_res = $conn->query("SELECT player_id FROM user_devices WHERE user_id = {$user_id} AND player_id IS NOT NULL AND player_id != ''");
                    if ($d_res) {
                        while ($dr = $d_res->fetch_assoc()) {
                            if (!empty($dr['player_id']) && strlen(trim($dr['player_id'])) > 10) {
                                $target_pids[] = trim($dr['player_id']);
                            }
                        }
                    }
                    $u_res = $conn->query("SELECT player_id FROM users WHERE id = {$user_id} AND player_id IS NOT NULL AND player_id != ''");
                    if ($u_res && ($ur = $u_res->fetch_assoc())) {
                        if (!empty($ur['player_id']) && strlen(trim($ur['player_id'])) > 10) {
                            $target_pids[] = trim($ur['player_id']);
                        }
                    }
                }
                $target_pids = array_values(array_unique(array_filter($target_pids)));

                // Trigger Push via Helper or Direct cURL Fallback
                if ($helper_loaded && function_exists("send_onesignal_push")) {
                    send_onesignal_push($notif_title, $notif_body, "/?tab=wallet", $target_pids, [$user_id], ["badge_type" => "PAYOUT_SUCCESS", "withdrawal_id" => $w_id], false);
                } else {
                    $onesignal_app_id = defined('ONESIGNAL_APP_ID') ? ONESIGNAL_APP_ID : "561bd59e-8355-47c8-88cc-0766688a105a";
                    $onesignal_api_key = defined('ONESIGNAL_API_KEY') ? ONESIGNAL_API_KEY : base64_decode("b3NfdjJfYXBwX2t5bjVsaHVka3ZkNHJjZ21hNXRncmNxcWxrM3A0bTR0NXliZTQ1ZnpmbWJjZ3VlYWtmb2xwdHFycWlld25ybXA2N21pNHB4azV3aHllMzZiNTVzbTQ3bDdxbXF0ZGJ2MnVmZmpzYXk=");
                    
                    if (!empty($onesignal_app_id)) {
                        $fields = [
                            'app_id' => $onesignal_app_id,
                            'headings' => ['en' => $notif_title],
                            'contents' => ['en' => $notif_body],
                            'include_external_user_ids' => [strval($user_id)],
                            'data' => ["badge_type" => "PAYOUT_SUCCESS", "withdrawal_id" => $w_id, "url" => "/?tab=wallet"]
                        ];
                        if (!empty($target_pids)) {
                            $fields['include_player_ids'] = $target_pids;
                        }
                        
                        $ch = curl_init("https://onesignal.com/api/v1/notifications");
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8', 'Authorization: Basic ' . $onesignal_api_key]);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                        curl_setopt($ch, CURLOPT_POST, TRUE);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                        curl_exec($ch);
                        curl_close($ch);
                    }
                }

                // Safely ensure table supports notifications without errors
                @$conn->query("ALTER TABLE app_notifications ADD COLUMN IF NOT EXISTS user_id INT DEFAULT 0");
                $conn->query("INSERT INTO app_notifications (title, message, target_url, badge_type) VALUES ('" . $conn->real_escape_string($notif_title) . "', '" . $conn->real_escape_string($notif_body) . "', '/?tab=wallet', 'PAYOUT_SUCCESS')");

            } catch (Exception $e) {
                $conn->rollback();
                $msg = "Payout processing error: " . $e->getMessage();
                $msg_type = "error";
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

                    $desc = "Withdrawal Rejected Refund (Ref #" . $w_id . ")";
                    $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
                    $tx->bind_param("ids", $user_id, $amount, $desc);
                    $tx->execute();
                    $tx->close();
                }

                $conn->query("UPDATE withdrawals SET status = 'Rejected' WHERE id = $w_id");
                $conn->commit();
                $msg = "Withdrawal request rejected and " . format_inr($amount) . " refunded.";
                $msg_type = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $msg = "Rejection error: " . $e->getMessage();
                $msg_type = "error";
            }
        }
    }
}

// Fetch Withdrawals (Pending first, then history sorted by ID desc)
$withdrawals = $conn->query("
    SELECT w.*, COALESCE(u.username, 'Host Admin') as username, COALESCE(u.wallet_balance, 0.00) as wallet_balance 
    FROM withdrawals w 
    LEFT JOIN users u ON w.user_id = u.id 
    ORDER BY CASE WHEN LOWER(w.status) = 'pending' THEN 0 ELSE 1 END, w.id DESC
");
?>

<div class="mb-5 flex items-center justify-between">
    <div>
        <h2 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2 font-display">
            <i class="fa-solid fa-hand-holding-dollar text-yellow-500"></i>
            <span>UPI Withdrawal Requests</span>
        </h2>
        <p class="text-xs text-slate-500">Send money to player UPI ID and mark payout completed</p>
    </div>
    <div class="flex items-center gap-2">
        <button type="button" onclick="location.reload();" class="px-3 py-1.5 bg-yellow-400 hover:bg-yellow-500 text-slate-950 rounded-xl text-xs font-black flex items-center gap-1 shadow-sm cursor-pointer">
            <i class="fa-solid fa-rotate text-[10px]"></i>
            <span>Refresh</span>
        </button>
        <span class="text-xs font-bold px-3 py-1 bg-yellow-100 dark:bg-yellow-400/10 text-yellow-900 dark:text-yellow-400 border border-yellow-300 dark:border-yellow-400/30 rounded-full">
            Payouts Studio
        </span>
    </div>
</div>

<?php if (!empty($msg)): ?>
    <div class="mb-5 p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2.5 <?= ($msg_type === 'success') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/10 text-rose-400 border border-rose-500/30' ?>">
        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400' ?> text-sm"></i>
        <span><?= htmlspecialchars($msg); ?></span>
    </div>
<?php endif; ?>

<div class="space-y-3">
    <?php if ($withdrawals && $withdrawals->num_rows > 0): ?>
        <?php while ($w = $withdrawals->fetch_assoc()): ?>
            <?php 
                $is_pending = (strtolower($w['status']) === 'pending'); 
                $is_completed = (strtolower($w['status']) === 'completed');
                $is_host_req = ((int)($w['is_admin_request'] ?? 0) === 1);
            ?>
            <div class="bg-white dark:bg-[#131b2e] border <?= $is_host_req ? 'border-yellow-400 shadow-[0_0_20px_rgba(250,204,21,0.25)] ring-2 ring-yellow-400/30' : ($is_pending ? 'border-amber-400/60 ring-1 ring-amber-400/20' : 'border-slate-200 dark:border-slate-800') ?> rounded-2xl p-4 shadow-sm space-y-3 transition">
                
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
                    <div>
                        <button type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($w['upi_id']); ?>'); alert('UPI ID copied!');" class="px-2.5 py-1.5 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 hover:border-yellow-400 rounded-lg text-[10px] font-bold text-slate-700 dark:text-slate-300 cursor-pointer">
                            <i class="fa-solid fa-copy mr-1"></i> Copy UPI
                        </button>
                    </div>
                </div>

                <!-- Approval / Rejection Form Actions -->
                <?php if ($is_pending): ?>
                    <div class="flex items-center gap-2 pt-1">
                        <form method="POST" action="index.php?tab=payouts" class="flex-1" onsubmit="return confirm('Approve and send targeted Push Notification?');">
                            <input type="hidden" name="action" value="complete">
                            <input type="hidden" name="withdrawal_id" value="<?= $w['id']; ?>">
                            <button type="submit" class="w-full py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-1.5 cursor-pointer">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Approve & Send Push Alert</span>
                            </button>
                        </form>

                        <form method="POST" action="index.php?tab=payouts" onsubmit="return confirm('Are you sure you want to reject this request?');">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="withdrawal_id" value="<?= $w['id']; ?>">
                            <button type="submit" class="py-2.5 px-3 bg-slate-100 dark:bg-slate-800 hover:bg-rose-500/20 text-rose-500 hover:text-rose-400 font-bold rounded-xl text-xs transition active:scale-95 border border-slate-200 dark:border-slate-700 cursor-pointer">
                                Reject
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-slate-800 rounded-2xl p-8 text-center text-slate-500 shadow-sm">
            <i class="fa-solid fa-money-bill-transfer text-3xl text-slate-300 dark:text-slate-700 mb-2 block"></i>
            <p class="text-xs font-bold">No withdrawal requests recorded yet.</p>
        </div>
    <?php endif; ?>
</div>
