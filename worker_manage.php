<?php
/**
 * BEASTz BATTLE - Dedicated Worker Match & IDP Studio
 * Completely independent from Admin Panel - Keeps worker session and active_panel = worker
 */

require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/onesignal_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_authenticated = false;
$logged_worker_id = 0;
$logged_worker_name = "Host Worker";

if (isset($_SESSION['worker_id']) || isset($_SESSION['admin_id'])) {
    $logged_worker_id = (int)($_SESSION['worker_id'] ?? $_SESSION['admin_id']);
    $logged_worker_name = $_SESSION['admin_user'] ?? $_SESSION['username'] ?? "Host Worker";
    $is_authenticated = true;
}

if (!$is_authenticated) {
    $_SESSION['active_panel'] = 'worker';
    setcookie('beastz_active_panel', 'worker', time() + (365 * 24 * 3600), '/');
    safe_redirect('login.php');
    exit;
}

$_SESSION['active_panel'] = 'worker';
setcookie('beastz_active_panel', 'worker', time() + (365 * 24 * 3600), '/');
setcookie('active_panel', 'worker', time() + (365 * 24 * 3600), '/');

$tournament_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = "";
$msg_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_room') {
    $room_id = sanitize($_POST['room_id'] ?? '');
    $room_password = sanitize($_POST['room_password'] ?? '');
    $status = sanitize($_POST['status'] ?? 'upcoming');
    $send_push = !empty($_POST['send_idp_push']);

    $u_stmt = $conn->prepare("UPDATE tournaments SET room_id = ?, room_password = ?, status = ? WHERE id = ?");
    if ($u_stmt) {
        $u_stmt->bind_param("sssi", $room_id, $room_password, $status, $tournament_id);
        if ($u_stmt->execute()) {
            $msg = "Room ID & Password saved successfully!";
            $msg_type = "success";

            if ($send_push && !empty($room_id) && $room_id !== "MATCH UNDER REVIEW") {
                $pids = [];
                $uids = [];
                $qp = $conn->query("
                    SELECT DISTINCT u.player_id, u.id as user_id 
                    FROM participants p 
                    JOIN users u ON p.user_id = u.id 
                    WHERE p.tournament_id = {$tournament_id}
                ");
                if ($qp) {
                    while ($pr = $qp->fetch_assoc()) {
                        if (!empty($pr['player_id']) && strlen(trim($pr['player_id'])) > 15) {
                            $pids[] = trim($pr['player_id']);
                        }
                        if (!empty($pr['user_id'])) {
                            $uids[] = (string)$pr['user_id'];
                        }
                    }
                }
                if (!empty($uids)) {
                    $uid_list = implode(',', array_unique(array_map('intval', $uids)));
                    $q_dev = $conn->query("SELECT player_id FROM user_devices WHERE user_id IN ({$uid_list}) AND player_id IS NOT NULL AND player_id != ''");
                    if ($q_dev) {
                        while ($dr = $q_dev->fetch_assoc()) {
                            if (!empty($dr['player_id']) && strlen(trim($dr['player_id'])) > 15) {
                                $pids[] = trim($dr['player_id']);
                            }
                        }
                    }
                }
                $pids = array_values(array_unique(array_filter($pids)));

                $idp_title = "WAR ZONE TAYAR HAI JOIN KARLO ROOM 5 MIN MEIN START HONE WALA HAI";
                $idp_body = "WAR ZONE TAYAR HAI JOIN KARLO ROOM 5 MIN MEIN START HONE WALA HAI! Room ID: " . $room_id . " | Pass: " . $room_password;

                if (!empty($pids) || !empty($uids)) {
                    send_onesignal_push($idp_title, $idp_body, "/?tab=matches", $pids, $uids, ["badge_type" => "ROOM_IDP", "tournament_id" => $tournament_id], false);
                    @$conn->query("INSERT INTO app_notifications (title, message, target_url, badge_type) VALUES ('" . $conn->real_escape_string($idp_title) . "', '" . $conn->real_escape_string($idp_body) . "', '/?tab=matches', 'ROOM_IDP')");
                    $msg = "Room IDP saved and instant push alert dispatched to joined players! (" . count($pids) . " devices notified)";
                } else {
                    $msg = "Room ID & Password saved! (Push alert was NOT dispatched because 0 joined players have registered device tokens).";
                }
            }
        } else {
            $msg = "Failed to update room: " . $u_stmt->error;
            $msg_type = "error";
        }
        $u_stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'distribute_winnings') {
    $kills_arr = $_POST['kills'] ?? [];
    $bonus_arr = $_POST['bonus'] ?? [];
    $rank_arr = $_POST['rank_no'] ?? [];

    $t_curr = $conn->query("SELECT * FROM tournaments WHERE id = {$tournament_id}")->fetch_assoc();
    $per_kill_rate = (float)($t_curr['per_kill'] ?? 0.00);

    $conn->begin_transaction();
    try {
        $total_distributed = 0.00;
        $winner_u_id = null;
        $participating_uids = [];
        $participating_pids = [];

        $p_query = $conn->query("SELECT p.id, p.user_id, u.player_id FROM participants p JOIN users u ON p.user_id = u.id WHERE p.tournament_id = {$tournament_id}");
        if ($p_query) {
            while ($p_item = $p_query->fetch_assoc()) {
                $pid = (int)$p_item['id'];
                $p_uid = (int)$p_item['user_id'];
                $participating_uids[] = (string)$p_uid;
                if (!empty($p_item['player_id'])) $participating_pids[] = $p_item['player_id'];

                $kills = isset($kills_arr[$pid]) ? max(0, (int)$kills_arr[$pid]) : 0;
                $bonus = isset($bonus_arr[$pid]) ? max(0.0, (float)$bonus_arr[$pid]) : 0.00;
                $rank_no = isset($rank_arr[$pid]) ? max(0, (int)$rank_arr[$pid]) : 0;

                if ($rank_no === 1) {
                    $winner_u_id = $p_uid;
                }

                $earned = ($kills * $per_kill_rate) + $bonus;

                $conn->query("UPDATE participants SET kills = {$kills}, rank_no = {$rank_no}, prize_won = {$earned} WHERE id = {$pid}");

                if ($earned > 0) {
                    $conn->query("UPDATE users SET wallet_balance = wallet_balance + {$earned}, winning_balance = winning_balance + {$earned} WHERE id = {$p_uid}");
                    $desc = "Match Winnings: " . $conn->real_escape_string($t_curr['title']) . " ({$kills} Kills @ ₹{$per_kill_rate}" . ($rank_no > 0 ? " + Rank #{$rank_no} Bonus ₹{$bonus}" : "") . ")";
                    $conn->query("INSERT INTO transactions (user_id, amount, type, description) VALUES ({$p_uid}, {$earned}, 'credit', '{$desc}')");
                    $total_distributed += $earned;
                }
            }
        }

        if ($winner_u_id) {
            $conn->query("UPDATE tournaments SET status = 'completed', winner_user_id = {$winner_u_id} WHERE id = {$tournament_id}");
        } else {
            $conn->query("UPDATE tournaments SET status = 'completed' WHERE id = {$tournament_id}");
        }

        $conn->commit();
        $msg = "Results declared successfully! Total ₹" . number_format($total_distributed, 2) . " disbursed into gamer wallets.";
        $msg_type = "success";

        $qp_res = $conn->query("
            SELECT DISTINCT u.player_id, u.id as user_id 
            FROM participants p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.tournament_id = {$tournament_id}
        ");
        if ($qp_res) {
            while ($pr = $qp_res->fetch_assoc()) {
                if (!empty($pr['player_id']) && strlen(trim($pr['player_id'])) > 15) {
                    $participating_pids[] = trim($pr['player_id']);
                }
                if (!empty($pr['user_id'])) {
                    $participating_uids[] = (int)$pr['user_id'];
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

        $results_title = "Badhayi ho aap jeet gaye hain 🎉 par kitne? Khud aake dekh lo 🏆";
        $results_body = "Badhayi ho aap jeet gaye hain 🎉 par kitne? Khud aake dekh lo 🏆";

        if (!empty($participating_pids) || !empty($participating_uids)) {
            send_onesignal_push($results_title, $results_body, "/?tab=matches", $participating_pids, $participating_uids, ["badge_type" => "RESULTS_ANNOUNCED", "tournament_id" => $tournament_id], false);
            @$conn->query("INSERT INTO app_notifications (title, message, target_url, badge_type) VALUES ('" . $conn->real_escape_string($results_title) . "', '" . $conn->real_escape_string($results_body) . "', '/?tab=matches', 'RESULTS_ANNOUNCED')");
        }

    } catch (Exception $e) {
        $conn->rollback();
        $msg = "Results error: " . $e->getMessage();
        $msg_type = "error";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rollback_hacker_prize') {
    $participant_id = (int)($_POST['participant_id'] ?? 0);
    $admin_reason = sanitize($_POST['admin_reason'] ?? 'Cheating / Hacker Detected');

    $p_stmt = $conn->prepare("
        SELECT p.*, u.username, u.wallet_balance, u.winning_balance 
        FROM participants p 
        INNER JOIN users u ON p.user_id = u.id 
        WHERE p.id = ? AND p.tournament_id = ?");
    $p_stmt->bind_param("ii", $participant_id, $tournament_id);
    $p_stmt->execute();
    $p_row = $p_stmt->get_result()->fetch_assoc();
    $p_stmt->close();

    if ($p_row) {
        $p_uid = (int)$p_row['user_id'];
        $prize_revoked = (float)$p_row['prize_won'];

        $conn->begin_transaction();
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
            $msg = "Anti-Cheat Action Applied: Revoked " . number_format($prize_revoked, 2) . " prize from @" . htmlspecialchars($p_row['username']) . "! Match moved to Live with support notice, kill data preserved.";
            $msg_type = "success";
            $t['status'] = 'live';
            $t['room_id'] = $notice_room_id;
            $t['room_password'] = $notice_pass;
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "Hacker rollback error: " . $e->getMessage();
            $msg_type = "error";
        }
    }
}

$t_res = $conn->query("
    SELECT t.*, 
    COALESCE(t.game_name, t.game, 'Free Fire') as display_game,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
    FROM tournaments t 
    WHERE t.id = {$tournament_id}
");
$t = $t_res ? $t_res->fetch_assoc() : null;

if (!$t) {
    echo "<div class='p-8 text-center text-slate-400'>Tournament not found.<br><br><a href='worker.php' class='px-4 py-2 bg-blue-600 text-white font-bold rounded-xl text-xs'>&larr; Back to Worker Studio</a></div>";
    exit;
}

$participants = [];
$p_res = $conn->query("
    SELECT p.*, u.username, u.wallet_balance, u.player_id, u.game_id as user_game_id
    FROM participants p
    JOIN users u ON p.user_id = u.id
    WHERE p.tournament_id = {$tournament_id}
    ORDER BY p.slot_no ASC, p.id ASC
");
if ($p_res) {
    while ($row = $p_res->fetch_assoc()) $participants[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Match #<?= $t['id']; ?> Studio - Worker Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: "class" };</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/theme.css">
</head>
<body class="bg-slate-900 text-slate-100 font-sans antialiased min-h-screen pb-16">
    <div class="max-w-xl mx-auto px-4 py-4 space-y-4">
        
        <header class="flex items-center justify-between bg-slate-800/80 backdrop-blur-md p-3.5 rounded-2xl border border-slate-700">
            <a href="worker.php" class="flex items-center gap-2 text-xs font-bold text-sky-400 hover:text-yellow-300">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Worker Studio</span>
            </a>
            <div class="flex items-center gap-2">
                <span class="text-[10px] uppercase font-black px-2 py-0.5 rounded bg-blue-600 text-white">Worker Match Studio</span>
                <span class="text-xs font-mono font-bold text-slate-400">#<?= $t['id']; ?></span>
            </div>
        </header>

        <?php if (!empty($msg)): ?>
            <div class="p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2.5 <?= ($msg_type === 'success') ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border border-rose-500/40' ?>">
                <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400' ?> text-sm"></i>
                <span><?= htmlspecialchars($msg); ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-[#111827] border border-slate-800 rounded-2xl p-4 space-y-3">
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-1.5 mb-1">
                        <span class="text-[9px] font-black px-2 py-0.5 rounded uppercase <?= ($t['status'] === 'completed') ? 'bg-slate-800 text-slate-300' : (($t['status'] === 'ongoing' || $t['status'] === 'live') ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40' : 'bg-blue-600/20 text-sky-400 border border-blue-500/30') ?>">
                            <?= strtoupper($t['status']); ?>
                        </span>
                        <span class="text-[9px] text-slate-400 font-bold uppercase bg-slate-800 px-1.5 py-0.5 rounded">
                            <?= htmlspecialchars($t['match_type']); ?>
                        </span>
                        <span class="text-[9px] text-slate-400 font-bold uppercase bg-slate-800 px-1.5 py-0.5 rounded">
                            <?= htmlspecialchars($t['display_game']); ?>
                        </span>
                    </div>
                    <h2 class="text-sm font-black text-slate-900 dark:text-white"><?= htmlspecialchars($t['title']); ?></h2>
                    <p class="text-[10px] text-slate-400 font-mono mt-0.5">
                        <i class="fa-solid fa-clock mr-1 text-blue-400"></i><?= date("d M Y, h:i A", strtotime($t['match_time'])); ?> &bull; Map: <?= htmlspecialchars($t['map']); ?>
                    </p>
                </div>
                <div class="text-right">
                    <span class="text-[10px] text-slate-400 block font-bold">Prize Pool</span>
                    <span class="text-sm font-black text-sky-400 font-mono">₹<?= number_format($t['prize_pool'], 0); ?></span>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-2 bg-slate-900/80 p-2.5 rounded-xl border border-slate-800 text-xs">
                <div>
                    <span class="text-slate-400 text-[10px] block">Entry Fee</span>
                    <span class="font-extrabold text-white">₹<?= number_format($t['entry_fee'], 0); ?></span>
                </div>
                <div>
                    <span class="text-slate-400 text-[10px] block">Per Kill</span>
                    <span class="font-extrabold text-emerald-400">₹<?= number_format($t['per_kill'], 0); ?></span>
                </div>
                <div class="text-right">
                    <span class="text-slate-400 text-[10px] block">Slots Joined</span>
                    <span class="font-bold text-sky-400"><?= count($participants); ?> / <?= $t['total_slots']; ?></span>
                </div>
            </div>
        </div>

        <!-- 1. ROOM IDP CREDENTIALS STUDIO -->
        <div class="bg-[#111827] border border-slate-800 rounded-2xl p-4 space-y-3.5">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                <h3 class="text-xs font-black uppercase tracking-wider text-white flex items-center gap-2">
                    <i class="fa-solid fa-key text-blue-400"></i> Room ID & Password (IDP) Studio
                </h3>
                <span class="text-[10px] font-bold text-sky-400 bg-blue-600/10 px-2 py-0.5 rounded border border-blue-500/20">
                    Live Credentials
                </span>
            </div>

            <form method="POST" action="worker_manage.php?id=<?= $t['id']; ?>" class="space-y-3 text-xs">
                <input type="hidden" name="action" value="update_room">
                
                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-300 mb-1">Custom Room ID *</label>
                        <input type="text" name="room_id" required value="<?= htmlspecialchars($t['room_id'] ?? ''); ?>" placeholder="e.g. 7864321" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs font-mono font-bold text-white focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-300 mb-1">Room Password *</label>
                        <input type="text" name="room_password" required value="<?= htmlspecialchars($t['room_password'] ?? ''); ?>" placeholder="e.g. 1234" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs font-mono font-bold text-white focus:border-blue-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-300 mb-1">Match Status</label>
                    <select name="status" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-white focus:border-blue-500 focus:outline-none">
                        <option value="upcoming" <?= ($t['status'] === 'upcoming' ? 'selected' : ''); ?>>Upcoming (Registration Open)</option>
                        <option value="ongoing" <?= ($t['status'] === 'ongoing' || $t['status'] === 'live' ? 'selected' : ''); ?>>Ongoing / Live (Room Open)</option>
                        <option value="completed" <?= ($t['status'] === 'completed' ? 'selected' : ''); ?>>Completed (Results Published)</option>
                    </select>
                </div>

                <div class="p-3 bg-blue-600/10 border border-blue-500/30 rounded-xl space-y-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="send_idp_push" value="1" checked class="w-4 h-4 accent-yellow-400 rounded">
                        <span class="text-[11px] font-black text-sky-400">Broadcast Instant IDP Push Alert to Joined Gamers</span>
                    </label>
                    <div class="text-[10px] font-mono text-slate-300 bg-slate-900 p-2 rounded-lg border border-blue-500/20">
                        WAR ZONE TAYAR HAI JOIN KARLO ROOM 5 MIN MEIN START HONE WALA HAI!
                    </div>
                    <p class="text-[9px] text-slate-400">&bull; Targeted directly to <?= count($participants); ?> gamers who booked slots in this match.</p>
                </div>

                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Save Credentials & Dispatch IDP Push</span>
                </button>
            </form>
        </div>

        <!-- SECTION 1.5: COMPACT ENROLLED SLOT LIST (NEW) -->
        <div class="bg-[#111827] border border-slate-800 rounded-2xl p-4 space-y-3">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                <h3 class="text-xs font-black uppercase tracking-wider text-white flex items-center gap-2">
                    <i class="fa-solid fa-users-viewfinder text-blue-400"></i> Enrolled Slot List (<?= count($participants); ?>)
                </h3>
                <span class="text-[10px] text-slate-400 font-bold">Slot, Name & UID</span>
            </div>

            <?php if (!empty($participants)): ?>
                <div class="grid grid-cols-2 gap-2 max-h-60 overflow-y-auto pr-1">
                    <?php foreach ($participants as $ps): ?>
                        <div class="p-2 bg-slate-900 border border-slate-800 rounded-xl text-[11px] flex flex-col justify-between">
                            <div class="flex items-center justify-between mb-0.5">
                                <span class="px-1.5 py-0.2 bg-blue-600 text-white font-black rounded text-[9px]">
                                    Slot #<?= $ps['slot_no'] ?: 'A'; ?>
                                </span>
                                <span class="font-extrabold text-white truncate max-w-[90px]" title="@<?= htmlspecialchars($ps['username']); ?>">
                                    @<?= htmlspecialchars($ps['username']); ?>
                                </span>
                            </div>
                            <div class="text-[10px] text-slate-300 truncate">
                                IGN: <strong class="text-slate-100"><?= htmlspecialchars($ps['in_game_name'] ?: 'N/A'); ?></strong>
                            </div>
                            <div class="text-[9px] text-slate-400 font-mono truncate">
                                UID: <?= htmlspecialchars($ps['user_game_id'] ?: 'N/A'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-4 text-center text-slate-400 text-xs">
                    No slots booked yet.
                </div>
            <?php endif; ?>
        </div>

        <!-- 2. DECLARE RESULTS & DISTRIBUTE WINNINGS -->
        <div class="bg-[#111827] border border-slate-800 rounded-2xl p-4 space-y-3.5">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                <h3 class="text-xs font-black uppercase tracking-wider text-white flex items-center gap-2">
                    <i class="fa-solid fa-trophy text-blue-400"></i> Declare Results & Distribute Prizes
                </h3>
                <span class="text-[10px] font-bold text-emerald-400">
                    Auto Wallet Credit
                </span>
            </div>

            <?php if (!empty($participants)): ?>
                <form method="POST" action="worker_manage.php?id=<?= $t['id']; ?>" class="space-y-3 text-xs">
                    <input type="hidden" name="action" value="distribute_winnings">

                    <div class="space-y-3 my-2">
                        <?php foreach ($participants as $p): ?>
                            <?php 
                                $pid = $p['id'];
                                $has_won = ($p['prize_won'] > 0);
                            ?>
                            <div class="p-3 rounded-xl border <?= $has_won ? 'border-amber-400/50 bg-blue-600/5' : 'border-slate-800 bg-slate-900' ?> text-xs space-y-2">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="px-2 py-0.5 rounded bg-blue-600 text-white font-black text-[10px]">
                                                Slot #<?= $p['slot_no'] ?: 'Auto'; ?>
                                            </span>
                                            <span class="font-extrabold text-white text-xs">
                                                @<?= htmlspecialchars($p['username']); ?>
                                            </span>
                                        </div>
                                        <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                            IGN: <strong class="text-slate-300"><?= htmlspecialchars($p['in_game_name'] ?: 'No IGN'); ?></strong>
                                        </div>
                                    </div>

                                    <div class="text-right">
                                        <?php if ($has_won): ?>
                                            <span class="text-xs font-black text-sky-400 font-mono block">
                                                ₹<?= number_format($p['prize_won'], 2); ?> Won
                                            </span>
                                            <span class="text-[9px] font-bold text-emerald-400 bg-emerald-500/20 px-1.5 py-0.5 rounded">
                                                Rank #<?= $p['rank_no']; ?> &bull; <?= $p['kills']; ?> Kills
                                            </span>
                                        <?php else: ?>
                                            <span class="text-[10px] text-slate-400 font-bold">Pending Result</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-2 pt-1 border-t border-slate-800">
                                    <div>
                                        <label class="block text-[9px] font-bold text-slate-400 mb-0.5 uppercase">Kills</label>
                                        <input type="number" name="kills[<?= $pid; ?>]" min="0" value="<?= (int)$p['kills']; ?>" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1 text-xs text-white font-mono font-bold focus:border-blue-500 focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-[9px] font-bold text-slate-400 mb-0.5 uppercase">Rank</label>
                                        <input type="number" name="rank_no[<?= $pid; ?>]" min="0" value="<?= (int)$p['rank_no']; ?>" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1 text-xs text-white font-mono font-bold focus:border-blue-500 focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-[9px] font-bold text-slate-400 mb-0.5 uppercase">Bonus ₹</label>
                                        <input type="number" step="0.5" name="bonus[<?= $pid; ?>]" min="0" value="<?= (float)$p['prize_won']; ?>" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1 text-xs text-emerald-400 font-mono font-bold focus:border-blue-500 focus:outline-none">
                                    </div>
                                </div>

                                <?php if ($has_won || $p['kills'] > 0 || $p['rank_no'] > 0): ?>
                                    <div class="pt-1.5 border-t border-amber-500/30 flex items-center justify-between">
                                        <span class="text-[10px] text-rose-400 font-bold flex items-center gap-1">
                                            <i class="fa-solid fa-shield-virus"></i> Suspected Cheating?
                                        </span>
                                        <button type="button" onclick="triggerHackerRevoke(<?= $pid; ?>, '<?= addslashes($p['username']); ?>', <?= (float)$p['prize_won']; ?>)" class="px-2.5 py-1 bg-rose-500/20 hover:bg-rose-500/30 text-rose-400 border border-rose-500/40 rounded-lg text-[10px] font-bold transition flex items-center gap-1 active:scale-95 cursor-pointer">
                                            <i class="fa-solid fa-rotate-left"></i> Reclaim Prize (Hacker)
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" onclick="return confirm('Calculate and credit prize winnings to each player wallet based on kills and rank?');" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-award text-sm"></i>
                        <span>Publish Results & Credit Wallets</span>
                    </button>
                </form>
            <?php else: ?>
                <div class="p-6 text-center text-slate-400 text-xs">
                    No participants have joined this match yet.
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Modal: Hacker Prize Reclaim Confirmation -->
    <div id="hackerModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs hidden flex items-center justify-center p-4">
        <div class="w-full max-w-sm bg-[#111827] border border-rose-500/40 rounded-3xl p-5 shadow-2xl relative text-left">
            <button type="button" onclick="closeHackerModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-base cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div class="text-center mb-3">
                <div class="w-12 h-12 mx-auto rounded-2xl bg-rose-500/20 text-rose-400 flex items-center justify-center text-xl font-black mb-2 border border-rose-500/40">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Anti-Cheat Prize Reclaim</h3>
                <p class="text-[11px] text-slate-400">Revoke prize from hacker and move match back to Live</p>
            </div>

            <form method="POST" action="worker_manage.php?id=<?= $tournament_id; ?>" class="space-y-3 text-xs">
                <input type="hidden" name="action" value="rollback_hacker_prize">
                <input type="hidden" name="participant_id" id="hackerParticipantId" value="">

                <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl space-y-1">
                    <div class="flex justify-between">
                        <span class="text-slate-400 font-semibold">Suspected Player:</span>
                        <strong class="text-white" id="hackerUsernameText">@player</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400 font-semibold">Prize to Revoke:</span>
                        <strong class="text-rose-400 font-mono font-bold" id="hackerPrizeText">₹0.00</strong>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-300 mb-1">Reason for Reclaim</label>
                    <input type="text" name="admin_reason" value="Cheating / Third-Party Tools Detected" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-bold focus:border-blue-500 focus:outline-none">
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
    </script>
</body>
</html>
