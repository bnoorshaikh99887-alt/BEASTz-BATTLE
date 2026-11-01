<?php
$msg = "";
$msg_type = "";

// Handle Withdrawal Completion / Rejection
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
                
                // 2. FIX: Update User's Passbook (transactions table) so it no longer says 'Pending'
                $safe_upi = $conn->real_escape_string($upi_id);
                $conn->query("UPDATE transactions SET description = 'Withdrawal Processed: Sent to {$safe_upi}' WHERE user_id = {$user_id} AND description LIKE 'Withdrawal Request (Pending%' AND amount = {$amount}");

                $conn->commit();
                
                $msg = "Payout of " . format_inr($amount) . " to " . htmlspecialchars($upi_id) . " marked as Completed!";
                $msg_type = "success";

                // ========================================================
                // TARGETED PUSH NOTIFICATION (USING DATABASE PLAYER_ID)
                // ========================================================
                $notif_title = "✅ Withdrawal Successfully Completed!";
                $notif_body = "₹" . number_format($amount, 2) . " has been successfully transferred to your UPI ID (" . $upi_id . ")! Check your bank/UPI app.";

                $onesignal_app_id = '561bd59e-8355-47c8-88cc-0766688a105a';
                $onesignal_rest_key = getenv("ONESIGNAL_API_KEY") ?: base64_decode("b3NfdjJfYXBwX2t5bjVsaHVka3ZkNHJjZ21hNXRncmNxcWxrM3A0bTR0NXliZTQ1ZnpmbWJjZ3VlYWtmb2xwdHFycWlld25ybXA2N21pNHB4azV3aHllMzZiNTVzbTQ3bDdxbXF0ZGJ2MnVmZmpzYXk=");

                // Fetch player's device token from database
                $token_stmt = $conn->prepare("SELECT player_id FROM users WHERE id = ?");
                $token_stmt->bind_param("i", $user_id);
                $token_stmt->execute();
                $token_res = $token_stmt->get_result()->fetch_assoc();
                $token_stmt->close();

                $device_player_id = trim($token_res['player_id'] ?? '');
                if (empty($device_player_id) && $is_host_request && $host_admin_id > 0) {
                    $adm_t = $conn->prepare("SELECT player_id FROM admin WHERE id = ?");
                    if ($adm_t) {
                        $adm_t->bind_param("i", $host_admin_id);
                        $adm_t->execute();
                        $ares = $adm_t->get_result()->fetch_assoc();
                        $device_player_id = trim($ares['player_id'] ?? '');
                        $adm_t->close();
                    }
                }

                if (!empty($device_player_id)) {
                    $onesignal_fields = [
                        'app_id' => $onesignal_app_id,
                        'include_player_ids' => [$device_player_id], // Direct Device Token Target
                    'headings' => ['en' => $notif_title],
                    'contents' => ['en' => $notif_body],
                    'data' => [
                        'target_url' => '/?tab=wallet',
                        'badge_type' => 'PAYOUT_SUCCESS'
                    ],
                    'priority' => 10,
                    'android_visibility' => 1,
                    'content_available' => true,
                    'android_sound' => 'notification',
                    'android_channel_id' => 'beastz_push_alerts'
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json; charset=utf-8',
                    'Authorization: Key ' . $onesignal_rest_key
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($onesignal_fields));
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                curl_exec($ch);
                curl_close($ch);
                } else {
                    $msg .= " (Note: Push dispatched if player registered device)";
                }

                // Store in DB for UI reference
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
                    // Refund to host admin wallet
                    $conn->query("UPDATE admin SET wallet_balance = wallet_balance + {$amount} WHERE id = {$host_admin_id}");
                } else {
                    // Refund held amount back to user's wallet
                    $ref = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                    $ref->bind_param("di", $amount, $user_id);
                    $ref->execute();
                    $ref->close();

                    // FIX: Remove 'Pending' text from old transaction on Reject
                    $conn->query("UPDATE transactions SET description = 'Withdrawal Request (Rejected)' WHERE user_id = {$user_id} AND description LIKE 'Withdrawal Request (Pending%' AND amount = {$amount}");

                    // Record refund transaction
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

// Fetch Withdrawals
$withdrawals = $conn->query("
    SELECT w.*, COALESCE(u.username, 'Host Admin') as username, COALESCE(u.wallet_balance, 0.00) as wallet_balance 
    FROM withdrawals w 
    LEFT JOIN users u ON w.user_id = u.id 
    ORDER BY w.is_admin_request DESC, w.id DESC
");
?>

<div class="mb-5 flex items-center justify-between">
    <div>
        <h2 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2 font-display">
            <i class="fa-solid fa-hand-holding-dollar text-yellow-500"></i>
            <span>UPI Withdrawal Requests</span>
        </h2>
        <p class="text-xs text-slate-500">Send money to player UPI ID and mark payout completed with auto-push notification</p>
    </div>
    <span class="text-xs font-bold px-3 py-1 bg-yellow-100 dark:bg-yellow-400/10 text-yellow-900 dark:text-yellow-400 border border-yellow-300 dark:border-yellow-400/30 rounded-full">
        Payouts Studio
    </span>
</div>

<?php if (!empty($msg)): ?>
    <div class="mb-5 p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2.5 <?= ($msg_type === 'success') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/10 text-rose-400 border border-rose-500/30' ?>">
        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400' ?> text-sm"></i>
        <span><?= htmlspecialchars($msg); ?></span>
    </div>
<?php endif; ?>

<div id="admin-payouts-cards-container" class="space-y-3">
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
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-400/10 text-amber-800 dark:text-amber-400 border border-amber-300 dark:border-amber-400/30">
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
                    <div class="flex items-center gap-1.5">
                        <button type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($w['upi_id']); ?>'); alert('UPI ID copied!');" class="px-2 py-1 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 hover:border-yellow-400 rounded-lg text-[10px] font-bold text-slate-700 dark:text-slate-300">
                            Copy UPI
                        </button>
                        <a href="upi://pay?pa=<?= urlencode($w['upi_id']); ?>&am=<?= $w['amount']; ?>&cu=INR" class="px-2 py-1 bg-yellow-400 hover:bg-yellow-500 text-slate-950 rounded-lg text-[10px] font-black">
                            Pay UPI
                        </a>
                    </div>
                </div>

                <!-- Approval / Rejection Actions -->
                <?php if ($is_pending): ?>
                    <div class="flex items-center gap-2 pt-1">
                        <form method="POST" action="index.php?tab=payouts" class="flex-1" onsubmit="return confirm('Confirm payout sent to <?= htmlspecialchars($w['upi_id']); ?>? Targeted push notification will be delivered immediately.');">
                            <input type="hidden" name="action" value="complete">
                            <input type="hidden" name="withdrawal_id" value="<?= $w['id']; ?>">
                            <button type="submit" class="w-full py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-1.5 cursor-pointer">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Approve & Send Push Alert</span>
                            </button>
                        </form>

                        <form method="POST" action="index.php?tab=payouts" onsubmit="return confirm('Reject and refund ₹<?= $w['amount']; ?> back?');">
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
