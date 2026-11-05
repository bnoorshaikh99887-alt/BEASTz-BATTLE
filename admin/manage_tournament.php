<?php
require_once __DIR__ . '/common/header.php';
require_once __DIR__ . '/../common/onesignal_helper.php';

$tournament_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = "";
$msg_type = "";

$stmt = $conn->prepare("
    SELECT t.*, 
    COALESCE(t.game_name, t.game, 'Free Fire') as display_game,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
    FROM tournaments t 
    WHERE t.id = ?");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$t = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$t) {
    echo "<div class='p-6 text-center text-slate-600'><p class='font-bold mb-3'>Tournament not found.</p><a href='tournament.php' class='text-sky-300 underline text-xs font-bold'>&larr; Back to Tournaments</a></div>";
    require_once __DIR__ . '/common/bottom.php';
    exit;
}

$per_kill_rate = (float)($t['per_kill'] ?? 0.00);

// FIX: OneSignal notification ab page render hone ke BAAD jaati hai (pehle page freeze hota tha)
$deferred_push = [];
register_shutdown_function(function () use (&$deferred_push) {
    if (empty($deferred_push)) return;
    ignore_user_abort(true);
    @set_time_limit(90);
    while (ob_get_level() > 0) { @ob_end_flush(); }
    @flush();
    if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
    foreach ($deferred_push as $dp) {
        try { sendOneSignalNotification($dp[0], $dp[1], $dp[2], $dp[3], $dp[4]); }
        catch (Throwable $e) { error_log('push error: ' . $e->getMessage()); }
    }
});

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_room') {
    $room_id = sanitize($_POST['room_id'] ?? '');
    $room_password = sanitize($_POST['room_password'] ?? '');
    
    $status = sanitize($_POST['status'] ?? '');
    if (empty($status)) {
        $status = $t['status'];
    }

    // FIX: status whitelist + purane room values yaad rakho (push sirf tab jab room badla ho)
    if (!in_array(strtolower($status), ['upcoming', 'live', 'ongoing', 'completed'], true)) {
        $status = $t['status'];
    }
    $old_room_id = (string)($t['room_id'] ?? '');
    $old_room_pass = (string)($t['room_password'] ?? '');
    $room_changed = ($room_id !== $old_room_id || $room_password !== $old_room_pass);

    $u_stmt = $conn->prepare("UPDATE tournaments SET room_id = ?, room_password = ?, status = ? WHERE id = ?");
    if ($u_stmt) {
        $u_stmt->bind_param("sssi", $room_id, $room_password, $status, $tournament_id);
        if ($u_stmt->execute()) {
            $msg = "Room ID, Password, and match status updated successfully!";
            $msg_type = "success";
            $t['room_id'] = $room_id;
            $t['room_password'] = $room_password;
            $t['status'] = $status;

            if (!empty($_POST['send_idp_push']) && $room_changed && !empty($room_id) && $room_id !== "MATCH UNDER REVIEW") {
                $target_pids = [];
                $target_uids = [];
                
                $q_joined = $conn->query("
                    SELECT DISTINCT p.user_id, u.player_id 
                    FROM participants p 
                    JOIN users u ON p.user_id = u.id 
                    WHERE p.tournament_id = {$tournament_id}
                ");
                if ($q_joined) {
                    while ($p_r = $q_joined->fetch_assoc()) {
                        if (!empty($p_r['user_id'])) $target_uids[] = (int)$p_r['user_id'];
                        if (!empty($p_r['player_id']) && strlen(trim($p_r['player_id'])) > 15) {
                            $target_pids[] = trim($p_r['player_id']);
                        }
                    }
                }
                
                if (!empty($target_uids)) {
                    $uid_list = implode(',', array_unique($target_uids));
                    $q_dev = $conn->query("SELECT player_id FROM user_devices WHERE user_id IN ({$uid_list}) AND player_id IS NOT NULL AND player_id != ''");
                    if ($q_dev) {
                        while ($dr = $q_dev->fetch_assoc()) {
                            if (!empty($dr['player_id']) && strlen(trim($dr['player_id'])) > 15) {
                                $target_pids[] = trim($dr['player_id']);
                            }
                        }
                    }
                }
                $target_pids = array_values(array_unique(array_filter($target_pids)));
                
                if (!empty($target_pids) || !empty($target_uids)) {
                    $idp_title = "🔥 WAR ZONE TAYAR HAI!";
                    $idp_msg = "WAR ZONE TAYAR HAI JOIN KARLO ROOM 5 MIN MEIN START HONE WALA HAI! Room ID: " . $room_id . " | Pass: " . $room_password;
                    $deferred_push[] = [$idp_title, $idp_msg, "/?tab=matches", $target_pids, $target_uids];
                    @$conn->query("INSERT INTO app_notifications (title, message, target_url, badge_type) VALUES ('" . $conn->real_escape_string($idp_title) . "', '" . $conn->real_escape_string($idp_msg) . "', '/?tab=matches', 'ROOM_IDP')");
                }
            }
        } else {
            $msg = "Update failed: " . $u_stmt->error;
            $msg_type = "error";
        }
        $u_stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'distribute_winnings') {
    $kills_arr = $_POST['kills'] ?? [];
    $bonus_arr = $_POST['bonus'] ?? [];
    $rank_arr = $_POST['rank_no'] ?? [];
    $published_ok = false;

    $conn->begin_transaction();
    try {
        // FIX 1: tournament row lock -> double click / refresh par dobara credit nahi hoga
        $lk = $conn->prepare("SELECT status FROM tournaments WHERE id = ? FOR UPDATE");
        $lk->bind_param("i", $tournament_id);
        $lk->execute();
        $lk_row = $lk->get_result()->fetch_assoc();
        $lk->close();
        if (!$lk_row) throw new Exception("Tournament not found.");
        if (strtolower($lk_row['status']) === 'completed') {
            throw new Exception("Results already published for this match. Dobara credit nahi ho sakta. (Galti ho to 'Reclaim Prize' se match Live karke correct karo.)");
        }

        $total_distributed = 0.00;
        $winner_u_id = null;

        $p_query = $conn->prepare("SELECT id, user_id, prize_won FROM participants WHERE tournament_id = ? FOR UPDATE");
        $p_query->bind_param("i", $tournament_id);
        $p_query->execute();
        $participants_list = $p_query->get_result();
        $p_query->close();

        while ($p_item = $participants_list->fetch_assoc()) {
            $pid = (int)$p_item['id'];
            $p_uid = (int)$p_item['user_id'];
            $prev_prize = (float)$p_item['prize_won'];

            $kills = isset($kills_arr[$pid]) ? max(0, (int)$kills_arr[$pid]) : 0;
            $bonus = isset($bonus_arr[$pid]) ? max(0.0, (float)$bonus_arr[$pid]) : 0.00;
            $rank_no = isset($rank_arr[$pid]) ? max(0, (int)$rank_arr[$pid]) : 0;

            if ($rank_no === 1) {
                $winner_u_id = $p_uid;
            }

            $earned = round(($kills * $per_kill_rate) + $bonus, 2);
            // FIX 2: sirf farak (delta) credit/debit hota hai, pehle diya hua prize dobara nahi milega
            $delta = round($earned - $prev_prize, 2);

            $upd_p = $conn->prepare("UPDATE participants SET kills = ?, rank_no = ?, prize_won = ? WHERE id = ?");
            $upd_p->bind_param("iidi", $kills, $rank_no, $earned, $pid);
            $upd_p->execute();
            $upd_p->close();

            if ($delta > 0) {
                $upd_w = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ?, winning_balance = winning_balance + ? WHERE id = ?");
                $upd_w->bind_param("ddi", $delta, $delta, $p_uid);
                $upd_w->execute();
                $upd_w->close();

                $desc = "Match Winnings: " . $t['title'] . " (" . $kills . " Kills @ " . format_inr($per_kill_rate);
                if ($rank_no > 0) {
                    $desc .= " + Rank #" . $rank_no . " Bonus " . format_inr($bonus);
                }
                $desc .= ")";
                $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
                $tx->bind_param("ids", $p_uid, $delta, $desc);
                $tx->execute();
                $tx->close();

                $total_distributed += $delta;
            } elseif ($delta < 0) {
                $abs = abs($delta);
                $upd_w = $conn->prepare("UPDATE users SET wallet_balance = GREATEST(0, wallet_balance - ?), winning_balance = GREATEST(0, winning_balance - ?) WHERE id = ?");
                $upd_w->bind_param("ddi", $abs, $abs, $p_uid);
                $upd_w->execute();
                $upd_w->close();

                $desc = "Match Result Correction: " . $t['title'];
                $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
                $tx->bind_param("ids", $p_uid, $abs, $desc);
                $tx->execute();
                $tx->close();
            }
        }

        if ($winner_u_id) {
            $st = $conn->prepare("UPDATE tournaments SET status = 'completed', winner_user_id = ? WHERE id = ?");
            $st->bind_param("ii", $winner_u_id, $tournament_id);
        } else {
            $st = $conn->prepare("UPDATE tournaments SET status = 'completed' WHERE id = ?");
            $st->bind_param("i", $tournament_id);
        }
        $st->execute();
        $st->close();

        $conn->commit();
        $published_ok = true;
        $msg = "Match results successfully published! Total " . format_inr($total_distributed) . " credited to gamer wallets.";
        $msg_type = "success";
        $t['status'] = 'completed';
    } catch (Throwable $e) {
        $conn->rollback();
        $msg = "Distribution Error: " . $e->getMessage();
        $msg_type = "error";
    }

    // FIX 3: notification commit ke BAAD, alag try me, aur background me (freeze nahi hoga)
    if ($published_ok) {
        try {
            $results_title = "Badhayi ho aap jeet gaye hain 🎉 par kitne? Khud aake dekh lo 🏆";
            $results_msg = $results_title;
            $participating_pids = [];
            $participating_uids = [];

            $q_pids = $conn->query("
                SELECT DISTINCT u.player_id, p.user_id
                FROM participants p
                JOIN users u ON p.user_id = u.id
                WHERE p.tournament_id = {$tournament_id}
            ");
            if ($q_pids) {
                while ($pr = $q_pids->fetch_assoc()) {
                    if (!empty($pr['user_id'])) $participating_uids[] = (int)$pr['user_id'];
                    if (!empty($pr['player_id']) && strlen(trim($pr['player_id'])) > 15) {
                        $participating_pids[] = trim($pr['player_id']);
                    }
                }
            }
            if (!empty($participating_uids)) {
                $u_list = implode(',', array_unique(array_map('intval', $participating_uids)));
                $q_devs = $conn->query("SELECT player_id FROM user_devices WHERE user_id IN ({$u_list}) AND player_id IS NOT NULL AND player_id != ''");
                if ($q_devs) {
                    while ($dr = $q_devs->fetch_assoc()) {
                        if (!empty($dr['player_id']) && strlen(trim($dr['player_id'])) > 15) {
                            $participating_pids[] = trim($dr['player_id']);
                        }
                    }
                }
            }
            $participating_pids = array_values(array_unique(array_filter($participating_pids)));

            if (!empty($participating_pids) || !empty($participating_uids)) {
                $deferred_push[] = [$results_title, $results_msg, "/?tab=matches", $participating_pids, $participating_uids];
                $ins = $conn->prepare("INSERT INTO app_notifications (title, message, target_url, badge_type) VALUES (?, ?, '/?tab=matches', 'RESULTS_ANNOUNCED')");
                if ($ins) { $ins->bind_param("ss", $results_title, $results_msg); $ins->execute(); $ins->close(); }
            }
        } catch (Throwable $e) {
            error_log('results notify error: ' . $e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rollback_hacker_prize') {
    $participant_id = (int)($_POST['participant_id'] ?? 0);
    $admin_reason = sanitize($_POST['admin_reason'] ?? 'Cheating / Hacker Detected');

    // FIX: row lock transaction ke andar -> double click par prize do baar nahi kategi
    $conn->begin_transaction();
    $p_stmt = $conn->prepare("
        SELECT p.*, u.username, u.wallet_balance, u.winning_balance 
        FROM participants p 
        INNER JOIN users u ON p.user_id = u.id 
        WHERE p.id = ? AND p.tournament_id = ? FOR UPDATE");
    $p_stmt->bind_param("ii", $participant_id, $tournament_id);
    $p_stmt->execute();
    $p_row = $p_stmt->get_result()->fetch_assoc();
    $p_stmt->close();

    if ($p_row) {
        $p_uid = (int)$p_row['user_id'];
        $prize_revoked = (float)$p_row['prize_won'];

        try {
            if ($prize_revoked > 0) {
                $ded = $conn->prepare("UPDATE users SET wallet_balance = GREATEST(0, wallet_balance - ?), winning_balance = GREATEST(0, winning_balance - ?) WHERE id = ?");
                $ded->bind_param("ddi", $prize_revoked, $prize_revoked, $p_uid);
                $ded->execute();
                $ded->close();

                $desc = "Prize Reclaimed (Anti-Cheat Violation in " . $t['title'] . "): " . $admin_reason;
                $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
                $tx->bind_param("ids", $p_uid, $prize_revoked, $desc);
                $tx->execute();
                $tx->close();
            }

            $rst = $conn->prepare("UPDATE participants SET prize_won = 0.00 WHERE id = ?");
            $rst->bind_param("i", $participant_id);
            $rst->execute();
            $rst->close();

            $notice_room_id = "MATCH UNDER REVIEW";
            $notice_pass = "Contact Customer Support within 1 hr";
            $upd_t = $conn->prepare("UPDATE tournaments SET status = 'live', room_id = ?, room_password = ?, winner_user_id = NULL WHERE id = ?");
            $upd_t->bind_param("ssi", $notice_room_id, $notice_pass, $tournament_id);
            $upd_t->execute();
            $upd_t->close();

            $conn->commit();
            $msg = "Anti-Cheat Action Applied: Revoked " . format_inr($prize_revoked) . " prize from @" . $p_row['username'] . "! Match moved to Live with support notice.";
            $msg_type = "success";
            $t['status'] = 'live';
            $t['room_id'] = $notice_room_id;
            $t['room_password'] = $notice_pass;
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "Hacker rollback error: " . $e->getMessage();
            $msg_type = "error";
        }
    } else {
        $conn->rollback();
        $msg = "Participant not found.";
        $msg_type = "error";
    }
}

$participants = $conn->query("
    SELECT p.*, u.username, u.email, u.phone, u.wallet_balance, u.game_id as user_game_id
    FROM participants p
    INNER JOIN users u ON p.user_id = u.id
    WHERE p.tournament_id = {$tournament_id}
    ORDER BY p.slot_no ASC, p.id ASC
");
?>

<!-- Breadcrumb & Back -->
<div class="mb-4 flex items-center justify-between">
    <?php $back_url = (!empty($_SESSION['active_panel']) && $_SESSION['active_panel'] === 'worker' && empty($_SESSION['is_super_admin'])) ? '../worker.php' : 'tournament.php'; ?>
    <a href="<?= $back_url; ?>" class="text-xs font-bold text-slate-600 hover:text-slate-900 flex items-center gap-1.5 transition">
        <i class="fa-solid fa-arrow-left text-[10px]"></i>
        <span>Back to Tournaments</span>
    </a>
    <span class="text-xs font-black px-2.5 py-0.5 rounded-full <?= ($t['status'] === 'completed') ? 'bg-slate-200 text-slate-800' : (($t['status'] === 'ongoing' || $t['status'] === 'live') ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-blue-950/60 text-yellow-900 border border-blue-600/40') ?>">
        <?= strtoupper($t['status']); ?>
    </span>
</div>

<!-- Match Overview Card -->
<div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs mb-5 space-y-3">
    <div class="flex items-start justify-between">
        <div>
            <span class="text-[10px] font-bold text-sky-200 bg-blue-950/60 border border-blue-600/40 px-2 py-0.5 rounded uppercase">
                <?= htmlspecialchars($t['display_game']); ?> &bull; <?= htmlspecialchars($t['match_type']); ?> &bull; <?= htmlspecialchars($t['map']); ?>
            </span>
            <h2 class="text-base font-extrabold text-slate-900 mt-1.5">
                <?= htmlspecialchars($t['title']); ?>
            </h2>
            <p class="text-xs text-slate-500">
                <i class="fa-regular fa-clock mr-1 text-slate-400"></i><?= date("d M Y, h:i A", strtotime($t['match_time'])); ?>
            </p>
        </div>
        <div class="text-right">
            <span class="text-[10px] text-slate-400 block font-semibold">Total Prize Pool</span>
            <span class="text-base font-mono font-black text-blue-400"><?= format_compact_inr($t['prize_pool']); ?></span>
        </div>
    </div>

    <div class="grid grid-cols-4 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-center text-xs">
        <div>
            <span class="text-[10px] text-slate-400 block">Entry Fee</span>
            <span class="font-bold text-slate-900"><?= format_inr($t['entry_fee']); ?></span>
        </div>
        <div>
            <span class="text-[10px] text-slate-400 block">Per Kill</span>
            <span class="font-bold text-emerald-600"><?= format_inr($per_kill_rate); ?></span>
        </div>
        <div>
            <span class="text-[10px] text-slate-400 block">Total Slots</span>
            <span class="font-bold text-slate-900"><?= $t['total_slots']; ?></span>
        </div>
        <div>
            <span class="text-[10px] text-slate-400 block">Joined</span>
            <span class="font-bold text-sky-200"><?= $t['enrolled_count']; ?></span>
        </div>
    </div>
</div>

<?php if (!empty($msg)): ?>
    <div class="mb-5 p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2 <?= ($msg_type === 'success') ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' ?>">
        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?>"></i>
        <span><?= htmlspecialchars($msg); ?></span>
    </div>
<?php endif; ?>

<!-- Section 1: Room ID & Password Configuration -->
<div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs mb-5">
    <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-1 flex items-center gap-1.5">
        <i class="fa-solid fa-key text-blue-400"></i> Room ID & Match Status
    </h3>
    <p class="text-[11px] text-slate-400 mb-3">Room credentials are only visible to players who joined this match</p>

    <form method="POST" action="manage_tournament.php?id=<?= $tournament_id; ?>" class="space-y-3">
        <input type="hidden" name="action" value="update_room">
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Custom Room ID</label>
                <input type="text" name="room_id" value="<?= htmlspecialchars($t['room_id'] ?? ''); ?>" placeholder="e.g. 8492019" class="w-full bg-slate-50 border border-slate-300 focus:border-blue-500 rounded-xl px-3 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Room Password</label>
                <input type="text" name="room_password" value="<?= htmlspecialchars($t['room_password'] ?? ''); ?>" placeholder="e.g. 1234" class="w-full bg-slate-50 border border-slate-300 focus:border-blue-500 rounded-xl px-3 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
            </div>
        </div>

        <div class="p-3 bg-blue-950/40/80 border border-blue-600/40 rounded-2xl space-y-2">
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="send_idp_push" value="1" checked class="w-4 h-4 text-blue-400 rounded focus:ring-blue-500 accent-yellow-400">
                    <span class="text-xs font-black text-slate-900">Auto Push Notification (To Joined Players Only)</span>
                </label>
                <span class="px-2 py-0.5 rounded bg-blue-600 text-white font-black text-[9px] uppercase shadow-xs">
                    DEFAULT ACTIVE
                </span>
            </div>
            <div class="text-[11px] text-slate-700 bg-white p-2.5 rounded-xl border border-yellow-200 font-mono">
                <strong>WAR ZONE TAYAR HAI JOIN KARLO ROOM 5 MIN MEIN START HONE WALA HAI</strong>
            </div>
        </div>

        <div>
            <label class="block text-[11px] font-bold text-slate-700 mb-1">Match Stage / Status</label>
            <select name="status" class="w-full bg-slate-50 border border-slate-300 focus:border-blue-500 rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:outline-none">
                <option value="upcoming" <?= ($t['status'] === 'upcoming') ? 'selected' : ''; ?>>UPCOMING (Registration Open)</option>
                <option value="live" <?= ($t['status'] === 'live' || $t['status'] === 'ongoing') ? 'selected' : ''; ?>>LIVE / ONGOING (Match Started / Under Review)</option>
                <option value="completed" <?= ($t['status'] === 'completed') ? 'selected' : ''; ?>>COMPLETED (Finished / Results Declared)</option>
            </select>
        </div>

        <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-1.5 cursor-pointer">
            <i class="fa-solid fa-save"></i>
            <span>Save Room Credentials & Status</span>
        </button>
    </form>
</div>

<!-- SECTION 1.5: COMPACT ENROLLED SLOT LIST (NEW) -->
<div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs mb-5">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
            <i class="fa-solid fa-users-viewfinder text-blue-500"></i> Enrolled Slot List (<?= $t['enrolled_count']; ?>)
        </h3>
        <span class="text-[10px] text-slate-400 font-bold">Slot, Name & UID</span>
    </div>

    <?php 
        // Reset pointer for slot list preview
        $participants_slots = $conn->query("
            SELECT p.slot_no, u.username, p.in_game_name, u.game_id as user_game_id
            FROM participants p
            INNER JOIN users u ON p.user_id = u.id
            WHERE p.tournament_id = {$tournament_id}
            ORDER BY p.slot_no ASC, p.id ASC
        ");
    ?>
    <?php if ($participants_slots && $participants_slots->num_rows > 0): ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-60 overflow-y-auto pr-1">
            <?php while ($ps = $participants_slots->fetch_assoc()): ?>
                <div class="p-2 bg-slate-50 border border-slate-200 rounded-xl text-[11px] flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-0.5">
                        <span class="px-1.5 py-0.2 bg-blue-600 text-white font-black rounded text-[9px]">
                            Slot #<?= $ps['slot_no'] ?: 'A'; ?>
                        </span>
                        <span class="font-extrabold text-slate-900 truncate max-w-[85px]" title="@<?= htmlspecialchars($ps['username']); ?>">
                            @<?= htmlspecialchars($ps['username']); ?>
                        </span>
                    </div>
                    <div class="text-[10px] text-slate-600 truncate">
                        IGN: <strong class="text-slate-800"><?= htmlspecialchars($ps['in_game_name'] ?: 'N/A'); ?></strong>
                    </div>
                    <div class="text-[9px] text-slate-400 font-mono truncate">
                        UID: <?= htmlspecialchars($ps['user_game_id'] ?: 'N/A'); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-4 text-slate-400 text-xs">
            No slots booked yet.
        </div>
    <?php endif; ?>
</div>

<!-- Section 2: Participants, Kills & Prize Pool Distribution -->
<div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs mb-6">
    <div class="flex items-center justify-between mb-2">
        <div>
            <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                <i class="fa-solid fa-crosshairs text-rose-500"></i> Enter Kills & Distribute Prizes
            </h3>
            <p class="text-[11px] text-slate-400">
                Rate: <strong class="text-slate-700"><?= format_inr($per_kill_rate); ?> / kill</strong>. Type kills and rank bonus for each player.
            </p>
        </div>
        <span class="text-[10px] font-mono font-black px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200">
            <?= $participants ? $participants->num_rows : 0; ?> Players
        </span>
    </div>

    <form method="POST" action="manage_tournament.php?id=<?= $tournament_id; ?>">
        <input type="hidden" name="action" value="distribute_winnings">

        <div class="space-y-3 my-4">
            <?php if ($participants && $participants->num_rows > 0): ?>
                <?php while ($p = $participants->fetch_assoc()): ?>
                    <?php 
                        $pid = $p['id'];
                        $has_won = ($p['prize_won'] > 0);
                        // FIX: purana bonus/rank form me prefill, taaki re-publish par prize galti se kam na ho
                        $pre_bonus = $has_won ? max(0, round((float)$p['prize_won'] - ((int)$p['kills'] * $per_kill_rate), 2)) : 0;
                    ?>
                    <div class="p-3 rounded-xl border <?= $has_won ? 'border-amber-300 bg-amber-50/40' : 'border-slate-200 bg-slate-50' ?> text-xs space-y-2">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <span class="px-2 py-0.5 rounded bg-blue-600 text-white font-black text-[10px]">
                                        Slot #<?= $p['slot_no'] ?: 'Auto'; ?>
                                    </span>
                                    <span class="font-extrabold text-slate-900 text-xs">
                                        <?= htmlspecialchars($p['in_game_name'] ?: $p['username']); ?>
                                    </span>
                                </div>
                                <div class="text-[10px] text-slate-500 font-mono mt-0.5">
                                    Game UID: <strong class="text-slate-800"><?= htmlspecialchars($p['game_id'] ?: ($p['user_game_id'] ?: 'N/A')); ?></strong>
                                </div>
                            </div>

                            <div class="text-right">
                                <?php if ($has_won): ?>
                                    <span class="text-xs font-black text-amber-600 font-mono block">
                                        <?= format_inr($p['prize_won']); ?> Won
                                    </span>
                                    <span class="text-[9px] font-bold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">
                                        Rank #<?= $p['rank_no']; ?> &bull; <?= $p['kills']; ?> Kills
                                    </span>
                                <?php else: ?>
                                    <span class="text-[10px] text-slate-400 font-bold">Pending Result</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 pt-1 border-t border-slate-200/60">
                            <div>
                                <label class="block text-[9px] font-bold text-slate-500 mb-0.5 uppercase">Kills (x <?= format_inr($per_kill_rate); ?>)</label>
                                <input type="number" name="kills[<?= $pid; ?>]" min="0" value="<?= (int)$p['kills']; ?>" class="w-full bg-white border border-slate-300 focus:border-blue-500 rounded-lg px-2.5 py-1 text-xs text-slate-900 font-mono font-bold focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[9px] font-bold text-slate-500 mb-0.5 uppercase">Rank Placement Bonus (₹)</label>
                                <input type="number" step="0.5" name="bonus[<?= $pid; ?>]" min="0" value="<?= $pre_bonus; ?>" class="w-full bg-white border border-slate-300 focus:border-blue-500 rounded-lg px-2.5 py-1 text-xs text-slate-900 font-mono font-bold focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[9px] font-bold text-slate-500 mb-0.5 uppercase">Rank (1 = Winner)</label>
                                <input type="number" name="rank_no[<?= $pid; ?>]" min="0" value="<?= (int)$p['rank_no']; ?>" class="w-full bg-white border border-slate-300 focus:border-blue-500 rounded-lg px-2.5 py-1 text-xs text-slate-900 font-mono font-bold focus:outline-none">
                            </div>
                        </div>

                        <?php if ($has_won || $p['kills'] > 0 || $p['rank_no'] > 0): ?>
                            <div class="pt-1.5 border-t border-amber-200 flex items-center justify-between">
                                <span class="text-[10px] text-rose-700 font-bold flex items-center gap-1">
                                    <i class="fa-solid fa-shield-virus"></i> Suspected Cheating?
                                </span>
                                <button type="button" onclick="triggerHackerRevoke(<?= (int)$pid; ?>, <?= htmlspecialchars(json_encode($p['username']), ENT_QUOTES); ?>, <?= (float)$p['prize_won']; ?>)" class="px-2 py-0.5 bg-rose-100 hover:bg-rose-200 text-rose-700 rounded text-[10px] font-bold transition flex items-center gap-1 active:scale-95 cursor-pointer">
                                    <i class="fa-solid fa-rotate-left"></i> Reclaim Prize (Hacker)
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-6 text-slate-400 text-xs">
                    No participants have joined this match yet.
                </div>
            <?php endif; ?>
        </div>

        <?php if ($participants && $participants->num_rows > 0): ?>
            <button type="submit" onclick="return confirm('Calculate and credit prize winnings to each player wallet based on kills and rank?');" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                <i class="fa-solid fa-award text-sm"></i>
                <span>Publish Results & Credit Wallets</span>
            </button>
        <?php endif; ?>
    </form>
</div>

<!-- Modal: Hacker Prize Reclaim Confirmation -->
<div id="hackerModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-rose-200 rounded-3xl p-5 shadow-2xl relative text-left">
        <button type="button" onclick="closeHackerModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base cursor-pointer">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="text-center mb-3">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center text-xl font-black mb-2 shadow-xs">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h3 class="sm:text-sm font-extrabold text-slate-900">Anti-Cheat Prize Reclaim</h3>
            <p class="text-[11px] text-slate-500">Revoke prize from hacker and move match back to Live</p>
        </div>

        <form method="POST" action="manage_tournament.php?id=<?= $tournament_id; ?>" class="space-y-3">
            <input type="hidden" name="action" value="rollback_hacker_prize">
            <input type="hidden" name="participant_id" id="hackerParticipantId" value="">

            <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs space-y-1">
                <div class="flex justify-between">
                    <span class="text-slate-500 font-semibold">Suspected Player:</span>
                    <strong class="text-slate-900" id="hackerUsernameText">@player</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500 font-semibold">Prize to Revoke:</span>
                    <strong class="text-rose-600 font-mono font-bold" id="hackerPrizeText">₹0.00</strong>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-700 mb-1">Reason for Reclaim</label>
                <input type="text" name="admin_reason" value="Cheating / Third-Party Tools Detected" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:outline-none">
            </div>

            <button type="submit" class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-1.5 cursor-pointer">
                <i class="fa-solid fa-shield-virus"></i>
                <span>Confirm Revoke & Move to Live</span>
            </button>
        </form>
    </div>
</div>

<script>
    function triggerHackerRevoke(pid, username, prize) {
        document.getElementById('hackerParticipantId').value = pid;
        document.getElementById('hackerUsernameText').innerText = '@' + username;
        document.getElementById('hackerPrizeText').innerText = '₹' + prize.toFixed(2);
        document.getElementById('hackerModal').classList.remove('hidden');
    }
    function closeHackerModal() {
        document.getElementById('hackerModal').classList.add('hidden');
    }
    // FIX: double click / late click par form 2 baar submit nahi hoga
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (f.dataset.locked === '1') { e.preventDefault(); return; }
        f.dataset.locked = '1';
        f.querySelectorAll('button[type=submit]').forEach(function (b) { b.disabled = true; b.style.opacity = '.6'; });
        setTimeout(function () {
            f.dataset.locked = '';
            f.querySelectorAll('button[type=submit]').forEach(function (b) { b.disabled = false; b.style.opacity = ''; });
        }, 20000);
    }, true);
    // FIX: page refresh par POST dobara na jaye
    if (window.history && history.replaceState) { history.replaceState(null, '', location.href); }
</script>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
