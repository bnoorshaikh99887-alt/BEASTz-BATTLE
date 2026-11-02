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

// Ensure Worker / Host Authentication
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

// Lock active panel strictly to worker
$_SESSION['active_panel'] = 'worker';
setcookie('beastz_active_panel', 'worker', time() + (365 * 24 * 3600), '/');
setcookie('active_panel', 'worker', time() + (365 * 24 * 3600), '/');

$tournament_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = "";
$msg_type = "";

// -------------------------------------------------------------
// POST ACTION: 1. SAVE ROOM IDP CREDENTIALS & BROADCAST PUSH
// -------------------------------------------------------------
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

            // Trigger Push Notification when requested (TARGETED JOINED PLAYERS ONLY)
            if ($send_push && !empty($room_id) && $room_id !== "MATCH UNDER REVIEW") {
                $pids = [];
                $uids = [];
                $qp = $conn->query("
                    SELECT DISTINCT u.player_id, u.id as user_id 
                    FROM participants p 
                    JOIN users u ON p.user_id = u.id 
                    WHERE p.tournament_id = {$tournament_id}
                    UNION
                    SELECT DISTINCT u.player_id, u.id as user_id 
                    FROM user_tournaments ut 
                    JOIN users u ON ut.user_id = u.id 
                    WHERE ut.tournament_id = {$tournament_id}
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

                if (!empty($pids)) {
                    $push_res = send_onesignal_push($idp_title, $idp_body, "/?tab=matches", $pids, $uids, ["badge_type" => "ROOM_IDP", "tournament_id" => $tournament_id], false);
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

// -------------------------------------------------------------
// POST ACTION: 2. REVOKE INDIVIDUAL PARTICIPANT WITH REFUND
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kick_participant') {
    $participant_id = (int)($_POST['participant_id'] ?? 0);
    $part_row = $conn->query("SELECT p.*, t.entry_fee, t.title FROM participants p JOIN tournaments t ON p.tournament_id = t.id WHERE p.id = {$participant_id}")->fetch_assoc();
    
    if ($part_row) {
        $p_uid = (int)$part_row['user_id'];
        $refund_fee = (float)($part_row['entry_fee'] ?? 0);
        $t_name = $part_row['title'] ?? 'Match';

        $conn->begin_transaction();
        try {
            if ($refund_fee > 0) {
                $conn->query("UPDATE users SET wallet_balance = wallet_balance + {$refund_fee} WHERE id = {$p_uid}");
                $conn->query("INSERT INTO transactions (user_id, amount, type, description) VALUES ({$p_uid}, {$refund_fee}, 'credit', 'Refund: Slot Cancelled in {$t_name}')");
            }
            $conn->query("DELETE FROM participants WHERE id = {$participant_id}");
            $conn->query("UPDATE tournaments SET joined_slots = GREATEST(0, joined_slots - 1) WHERE id = {$tournament_id}");
            $conn->commit();
            $msg = "Participant slot removed and entry fee ₹" . number_format($refund_fee, 2) . " refunded to player wallet.";
            $msg_type = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "Refund error: " . $e->getMessage();
            $msg_type = "error";
        }
    }
}

// -------------------------------------------------------------
// POST ACTION: 3. DECLARE RESULTS & DISTRIBUTE WINNINGS
// -------------------------------------------------------------
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

        // Fetch participating player_ids from participants, user_tournaments, users, and user_devices
        $participating_pids = [];
        $qp_res = $conn->query("
            SELECT DISTINCT u.player_id 
            FROM participants p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.tournament_id = {$tournament_id}
            UNION
            SELECT DISTINCT u.player_id 
            FROM user_tournaments ut 
            JOIN users u ON ut.user_id = u.id 
            WHERE ut.tournament_id = {$tournament_id}
        ");
        if ($qp_res) {
            while ($pr = $qp_res->fetch_assoc()) {
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

        // Dispatch Results Push Notification ONLY to joined players
        $results_title = "Badhayi ho aap jeet gaye hain 🎉 par kitne? Khud aake dekh lo 🏆";
        $results_body = "Badhayi ho aap jeet gaye hain 🎉 par kitne? Khud aake dekh lo 🏆";

        if (!empty($participating_pids)) {
            send_onesignal_push($results_title, $results_body, "/?tab=matches", $participating_pids, $participating_uids, ["badge_type" => "RESULTS_ANNOUNCED", "tournament_id" => $tournament_id], false);
            @$conn->query("INSERT INTO app_notifications (title, message, target_url, badge_type) VALUES ('" . $conn->real_escape_string($results_title) . "', '" . $conn->real_escape_string($results_body) . "', '/?tab=matches', 'RESULTS_ANNOUNCED')");
        }

    } catch (Exception $e) {
        $conn->rollback();
        $msg = "Results error: " . $e->getMessage();
        $msg_type = "error";
    }
}

// Fetch Tournament Details
$t_res = $conn->query("
    SELECT t.*, 
    COALESCE(t.game_name, t.game, 'Free Fire') as display_game,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
    FROM tournaments t 
    WHERE t.id = {$tournament_id}
");
$t = $t_res ? $t_res->fetch_assoc() : null;

if (!$t) {
    echo "<div class='p-8 text-center text-slate-400'>Tournament not found.<br><br><a href='worker.php' class='px-4 py-2 bg-yellow-400 text-slate-950 font-bold rounded-xl text-xs'>&larr; Back to Worker Studio</a></div>";
    exit;
}

// Fetch Enrolled Participants
$participants = [];
$p_res = $conn->query("
    SELECT p.*, u.username, u.wallet_balance, u.player_id
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
        
        <!-- Header Bar with Back Button strictly to worker.php -->
        <header class="flex items-center justify-between bg-slate-800/80 backdrop-blur-md p-3.5 rounded-2xl border border-slate-700">
            <a href="worker.php" class="flex items-center gap-2 text-xs font-bold text-yellow-400 hover:text-yellow-300">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Worker Studio</span>
            </a>
            <div class="flex items-center gap-2">
                <span class="text-[10px] uppercase font-black px-2 py-0.5 rounded bg-yellow-400 text-slate-950">Worker Match Studio</span>
                <span class="text-xs font-mono font-bold text-slate-400">#<?= $t['id']; ?></span>
            </div>
        </header>

        <!-- Feedback Alert Message -->
        <?php if (!empty($msg)): ?>
            <div class="p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2.5 <?= ($msg_type === 'success') ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border border-rose-500/40' ?>">
                <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400' ?> text-sm"></i>
                <span><?= htmlspecialchars($msg); ?></span>
            </div>
        <?php endif; ?>

        <!-- Tournament Overview Card -->
        <div class="bg-[#111827] border border-slate-800 rounded-2xl p-4 space-y-3">
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-1.5 mb-1">
                        <span class="text-[9px] font-black px-2 py-0.5 rounded uppercase <?= ($t['status'] === 'completed') ? 'bg-slate-800 text-slate-300' : (($t['status'] === 'ongoing' || $t['status'] === 'live') ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40' : 'bg-yellow-400/20 text-yellow-400 border border-yellow-400/30') ?>">
                            <?= strtoupper($t['status']); ?>
                        </span>
                        <span class="text-[9px] text-slate-400 font-bold uppercase bg-slate-800 px-1.5 py-0.5 rounded">
                            <?= htmlspecialchars($t['match_type']); ?>
                        </span>
                        <span class="text-[9px] text-slate-400 font-bold uppercase bg-slate-800 px-1.5 py-0.5 rounded">
                            <?= htmlspecialchars($t['display_game']); ?>
                        </span>
                    </div>
                    <h2 class="text-sm font-black text-white"><?= htmlspecialchars($t['title']); ?></h2>
                    <p class="text-[10px] text-slate-400 font-mono mt-0.5">
                        <i class="fa-solid fa-clock mr-1 text-yellow-500"></i><?= date("d M Y, h:i A", strtotime($t['match_time'])); ?> &bull; Map: <?= htmlspecialchars($t['map']); ?>
                    </p>
                </div>
                <div class="text-right">
                    <span class="text-[10px] text-slate-400 block font-bold">Prize Pool</span>
                    <span class="text-sm font-black text-amber-400 font-mono">₹<?= number_format($t['prize_pool'], 0); ?></span>
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
                    <span class="font-bold text-yellow-400"><?= count($participants); ?> / <?= $t['total_slots']; ?></span>
                </div>
            </div>
        </div>

        <!-- 1. ROOM IDP CREDENTIALS STUDIO -->
        <div class="bg-[#111827] border border-slate-800 rounded-2xl p-4 space-y-3.5">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                <h3 class="text-xs font-black uppercase tracking-wider text-white flex items-center gap-2">
                    <i class="fa-solid fa-key text-yellow-500"></i> Room ID & Password (IDP) Studio
                </h3>
                <span class="text-[10px] font-bold text-yellow-400 bg-yellow-400/10 px-2 py-0.5 rounded border border-yellow-400/20">
                    Live Credentials
                </span>
            </div>

            <form method="POST" action="worker_manage.php?id=<?= $t['id']; ?>" class="space-y-3 text-xs">
                <input type="hidden" name="action" value="update_room">
                
                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-300 mb-1">Custom Room ID *</label>
                        <input type="text" name="room_id" required value="<?= htmlspecialchars($t['room_id'] ?? ''); ?>" placeholder="e.g. 7864321" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs font-mono font-bold text-white focus:border-yellow-400 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-300 mb-1">Room Password *</label>
                        <input type="text" name="room_password" required value="<?= htmlspecialchars($t['room_password'] ?? ''); ?>" placeholder="e.g. 1234" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs font-mono font-bold text-white focus:border-yellow-400 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-300 mb-1">Match Status</label>
                    <select name="status" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-white focus:border-yellow-400 focus:outline-none">
                        <option value="upcoming" <?= ($t['status'] === 'upcoming' ? 'selected' : ''); ?>>Upcoming (Registration Open)</option>
                        <option value="ongoing" <?= ($t['status'] === 'ongoing' || $t['status'] === 'live' ? 'selected' : ''); ?>>Ongoing / Live (Room Open)</option>
                        <option value="completed" <?= ($t['status'] === 'completed' ? 'selected' : ''); ?>>Completed (Results Published)</option>
                    </select>
                </div>

                <!-- Auto Push Preview Box -->
                <div class="p-3 bg-yellow-400/10 border border-yellow-400/30 rounded-xl space-y-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="send_idp_push" value="1" checked class="w-4 h-4 accent-yellow-400 rounded">
                        <span class="text-[11px] font-black text-yellow-400">Broadcast Instant IDP Push Alert to Joined Gamers</span>
                    </label>
                    <div class="text-[10px] font-mono text-slate-300 bg-slate-900 p-2 rounded-lg border border-yellow-400/20">
                        WAR ZONE TAYAR HAI JOIN KARLO ROOM 5 MIN MEIN START HONE WALA HAI!
                    </div>
                    <p class="text-[9px] text-slate-400">&bull; Targeted directly to <?= count($participants); ?> gamers who booked slots in this match.</p>
                </div>

                <button type="submit" class="w-full py-2.5 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Save Credentials & Dispatch IDP Push</span>
                </button>
            </form>
        </div>

        <!-- 2. DECLARE RESULTS & DISTRIBUTE WINNINGS -->
        <div class="bg-[#111827] border border-slate-800 rounded-2xl p-4 space-y-3.5">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                <h3 class="text-xs font-black uppercase tracking-wider text-white flex items-center gap-2">
                    <i class="fa-solid fa-trophy text-yellow-500"></i> Declare Results & Distribute Prizes
                </h3>
                <span class="text-[10px] font-bold text-emerald-400">
                    Auto Wallet Credit
                </span>
            </div>

            <?php if (!empty($participants)): ?>
                <form method="POST" action="worker_manage.php?id=<?= $t['id']; ?>" class="space-y-3 text-xs">
                    <input type="hidden" name="action" value="distribute_winnings">

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-[10px] text-slate-400 uppercase border-b border-slate-800">
                                    <th class="py-2 px-2">Slot</th>
                                    <th class="py-2 px-2">Player</th>
                                    <th class="py-2 px-2">Kills</th>
                                    <th class="py-2 px-2">Rank</th>
                                    <th class="py-2 px-2">Bonus ₹</th>
                                    <th class="py-2 px-2 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                <?php foreach ($participants as $p): ?>
                                    <tr>
                                        <td class="py-2.5 px-2 font-mono font-bold text-yellow-400">#<?= $p['slot_no']; ?></td>
                                        <td class="py-2.5 px-2">
                                            <span class="font-bold text-white block">@<?= htmlspecialchars($p['username']); ?></span>
                                            <span class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($p['in_game_name'] ?: 'No IGN'); ?></span>
                                        </td>
                                        <td class="py-2.5 px-2">
                                            <input type="number" name="kills[<?= $p['id']; ?>]" value="<?= $p['kills'] ?? 0; ?>" min="0" class="w-14 bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-xs text-center font-mono font-bold text-white focus:border-yellow-400">
                                        </td>
                                        <td class="py-2.5 px-2">
                                            <input type="number" name="rank_no[<?= $p['id']; ?>]" value="<?= $p['rank_no'] ?? 0; ?>" min="0" placeholder="Rank" class="w-14 bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-xs text-center font-mono font-bold text-yellow-400 focus:border-yellow-400">
                                        </td>
                                        <td class="py-2.5 px-2">
                                            <input type="number" step="1" name="bonus[<?= $p['id']; ?>]" value="<?= $p['prize_won'] ?? 0; ?>" min="0" placeholder="0" class="w-16 bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-xs text-center font-mono font-bold text-emerald-400 focus:border-yellow-400">
                                        </td>
                                        <td class="py-2.5 px-2 text-right">
                                            <form method="POST" action="worker_manage.php?id=<?= $t['id']; ?>" class="inline" onsubmit="return confirm('Remove slot and refund fee?');">
                                                <input type="hidden" name="action" value="kick_participant">
                                                <input type="hidden" name="participant_id" value="<?= $p['id']; ?>">
                                                <button type="submit" class="p-1.5 text-rose-400 hover:text-rose-300 rounded-lg text-xs" title="Refund & Kick">
                                                    <i class="fa-solid fa-user-minus"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" class="w-full py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Publish Results, Disburse Winnings & Send Push Alert</span>
                    </button>
                </form>
            <?php else: ?>
                <div class="p-6 text-center text-slate-400 text-xs">
                    No participants have joined this match yet.
                </div>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>
