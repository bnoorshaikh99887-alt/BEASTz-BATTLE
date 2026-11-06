<?php
require_once __DIR__ . "/common/config.php";
require_once __DIR__ . "/common/onesignal_helper.php";

// Authentication Check: Worker or Master Admin can access
$is_authenticated = false;
$logged_worker_id = 0;
$logged_worker_user = "";
$is_master = false;

if (!empty($_SESSION["admin_id"]) || !empty($_SESSION["worker_id"])) {
    $logged_worker_id = (int)($_SESSION["worker_id"] ?? $_SESSION["admin_id"]);
    $logged_worker_user = $_SESSION["admin_user"] ?? $_SESSION["username"] ?? "Worker";
    $is_master = (!empty($_SESSION["is_super_admin"]) || $logged_worker_user === "anonymous.349" || $logged_worker_user === "admin");
    $is_authenticated = true;
}

if (!$is_authenticated) {
    $_SESSION["active_panel"] = "worker";
    setcookie("beastz_active_panel", "worker", time() + (365 * 24 * 3600), "/");
    safe_redirect("login.php");
    exit;
}

$_SESSION["active_panel"] = "worker";
if (!isset($_COOKIE["beastz_active_panel"]) || $_COOKIE["beastz_active_panel"] !== "worker") {
    setcookie("beastz_active_panel", "worker", time() + (365 * 24 * 3600), "/");
}

$feedback_msg = "";
$feedback_type = "";

// ==============================================================
// POST ACTION HANDLERS
// ==============================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    $act = $_POST["action"];

    // 1. ADD NEW TOURNAMENT (MIRRORING ADMIN PANEL)
    if ($act === "create_tourney" || $act === "create_tournament") {
        $title = sanitize($_POST["title"] ?? "");
        $game = sanitize($_POST["game"] ?? $_POST["game_name"] ?? "Free Fire");
        $map = sanitize($_POST["map"] ?? "BERMUDA");
        $match_type = sanitize($_POST["match_type"] ?? "SOLO BR");
        $entry_fee = (float)($_POST["entry_fee"] ?? 10.00);
        $per_kill = (float)($_POST["per_kill"] ?? 0.00);
        $prize_pool = (float)($_POST["prize_pool"] ?? 225.00);
        $total_slots = (int)($_POST["total_slots"] ?? 48);
        $commission = (int)($_POST["commission_percent"] ?? 15);
        $match_time_raw = $_POST["match_time"] ?? "";

        if (empty($title) || empty($match_time_raw)) {
            $feedback_msg = "Please provide match title and scheduled date & time.";
            $feedback_type = "error";
        } else {
            $match_time = date("Y-m-d H:i:s", strtotime($match_time_raw));
            $is_private = (!empty($_POST["is_private"]) && (int)$_POST["is_private"] === 1) ? 1 : 0;
            $access_code = $is_private ? ("BTZ-" . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4))) : null;
            $stmt = $conn->prepare("INSERT INTO tournaments (title, game, game_name, map, match_type, entry_fee, per_kill, prize_pool, match_time, total_slots, joined_slots, status, commission_percent, hosted_by_admin_id, is_private, access_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'upcoming', ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sssssdddsiiiis", $title, $game, $game, $map, $match_type, $entry_fee, $per_kill, $prize_pool, $match_time, $total_slots, $commission, $logged_worker_id, $is_private, $access_code);
                if ($stmt->execute()) {
                    $new_id = $stmt->insert_id;
                    $stmt->close();
                    
                    // Dispatch Instant Listing Push Alert to all Gamers
                    $list_title = "⚔️ New Tournament Listed: " . $title;
                    $list_body = "New " . $game . " (" . $match_type . ") match is live! Entry: " . format_inr($entry_fee) . " | Prize: " . format_inr($prize_pool) . ". Join now!";
                    send_onesignal_push($list_title, $list_body, "/?tab=home", [], [], ["badge_type" => "NEW_TOURNAMENT", "tournament_id" => $new_id], true);
                    @$conn->query("INSERT INTO app_notifications (title, message, target_url, badge_type) VALUES ('" . $conn->real_escape_string($list_title) . "', '" . $conn->real_escape_string($list_body) . "', '/?tab=home', 'NEW_TOURNAMENT')");
                    
                    $feedback_msg = "Tournament #" . $new_id . " ('" . htmlspecialchars($title) . "') created & listing push alert broadcasted to gamers!";
                    $feedback_type = "success";
                } else {
                    $feedback_msg = "Failed to add tournament: " . $stmt->error;
                    $feedback_type = "error";
                }
            } else {
                $feedback_msg = "Database statement error: " . $conn->error;
                $feedback_type = "error";
            }
        }
    }

    // 2. UPDATE & BROADCAST ROOM IDP
    if ($act === "save_and_broadcast_idp") {
        $tid = (int)($_POST["tournament_id"] ?? 0);
        $room_id = sanitize($_POST["room_id"] ?? "");
        $room_pass = sanitize($_POST["room_password"] ?? "");
        $do_broadcast = isset($_POST["send_push"]) && $_POST["send_push"] == "1";

        if ($tid <= 0) {
            $feedback_msg = "Invalid tournament selected.";
            $feedback_type = "error";
        } else {
            $upd = $conn->prepare("UPDATE tournaments SET room_id = ?, room_password = ?, status = IF(status = 'upcoming', 'ongoing', status) WHERE id = ?");
            if ($upd) {
                $upd->bind_param("ssi", $room_id, $room_pass, $tid);
                $upd->execute();
                $upd->close();

                $t_info = $conn->query("SELECT title FROM tournaments WHERE id = {$tid}")->fetch_assoc();
                $t_title = $t_info["title"] ?? "Custom Match";

                if ($do_broadcast) {
                    $player_ids = [];
                    $user_ids = [];
                    $j_q = $conn->query("
                        SELECT DISTINCT u.player_id, u.id as user_id 
                        FROM participants p 
                        JOIN users u ON p.user_id = u.id 
                        WHERE p.tournament_id = {$tid}
                        UNION
                        SELECT DISTINCT u.player_id, u.id as user_id 
                        FROM user_tournaments ut 
                        JOIN users u ON ut.user_id = u.id 
                        WHERE ut.tournament_id = {$tid}
                    ");
                    if ($j_q) {
                        while ($p = $j_q->fetch_assoc()) {
                            if (!empty($p["player_id"]) && strlen(trim($p["player_id"])) > 15) {
                                $player_ids[] = trim($p["player_id"]);
                            }
                            if (!empty($p["user_id"])) {
                                $user_ids[] = (int)$p["user_id"];
                            }
                        }
                    }
                    if (!empty($user_ids)) {
                        $uid_list = implode(',', array_unique($user_ids));
                        $q_dev = $conn->query("SELECT player_id FROM user_devices WHERE user_id IN ({$uid_list}) AND player_id IS NOT NULL AND player_id != ''");
                        if ($q_dev) {
                            while ($dr = $q_dev->fetch_assoc()) {
                                if (!empty($dr['player_id']) && strlen(trim($dr['player_id'])) > 15) {
                                    $player_ids[] = trim($dr['player_id']);
                                }
                            }
                        }
                    }
                    $player_ids = array_values(array_unique(array_filter($player_ids)));

                    $n_title = "WAR ZONE TAYAR HAI JOIN KARLO ROOM 5 MIN MEIN START HONE WALA HAI";
                    $n_body = "WAR ZONE TAYAR HAI JOIN KARLO ROOM 5 MIN MEIN START HONE WALA HAI! Room ID: " . $room_id . " | Pass: " . $room_pass;

                    if (!empty($player_ids) || !empty($user_ids)) {
                        send_onesignal_push($n_title, $n_body, "/?tab=matches", $player_ids, $user_ids, ["badge_type" => "ROOM_IDP", "tournament_id" => $tid], false);
                        $conn->query("INSERT INTO app_notifications (title, message, target_url, badge_type) VALUES ('" . $conn->real_escape_string($n_title) . "', '" . $conn->real_escape_string($n_body) . "', '/?tab=matches', 'ROOM_IDP')");
                        $feedback_msg = "Room IDP saved and instant push alert dispatched to joined players! (" . count($player_ids) . " devices notified)";
                        $feedback_type = "success";
                    } else {
                        $feedback_msg = "Room ID & Password saved successfully! (Push alert skipped: no registered player devices found for joined users).";
                        $feedback_type = "success";
                    }
                } else {
                    $feedback_msg = "Room ID & Password saved successfully for Match #" . $tid . "!";
                    $feedback_type = "success";
                }
            }
        }
    }

    // 3. BROADCAST GENERAL WORKER PUSH ANNOUNCEMENT
    if ($act === "send_worker_broadcast") {
        $b_title = sanitize($_POST["broadcast_title"] ?? "");
        $b_message = sanitize($_POST["broadcast_message"] ?? "");
        $target_url = sanitize($_POST["target_url"] ?? "/?tab=home");

        if (empty($b_title) || empty($b_message)) {
            $feedback_msg = "Title and message are required for push notification.";
            $feedback_type = "error";
        } else {
            send_onesignal_push($b_title, $b_message, $target_url, [], [], ["badge_type" => "HOST_ANNOUNCEMENT"], true);
            $conn->query("INSERT INTO app_notifications (title, message, target_url, badge_type) VALUES ('" . $conn->real_escape_string($b_title) . "', '" . $conn->real_escape_string($b_message) . "', '" . $conn->real_escape_string($target_url) . "', 'HOST_ANNOUNCEMENT')");
            $feedback_msg = "Push notification announcement broadcasted to all gamers!";
            $feedback_type = "success";
        }
    }

    // 4. REVOKE / CANCEL TOURNAMENT (REFUNDS ENTRY FEE TO ALL PARTICIPANTS)
    if ($act === "revoke_tournament") {
        $rev_id = (int)($_POST["tournament_id"] ?? 0);
        $reason = sanitize($_POST["reason"] ?? "Tournament cancelled by host");

        if ($rev_id > 0) {
            $conn->begin_transaction();
            try {
                $t_row = $conn->query("SELECT title, entry_fee FROM tournaments WHERE id = {$rev_id}")->fetch_assoc();
                $e_fee = (float)($t_row["entry_fee"] ?? 0.00);
                $t_name = $t_row["title"] ?? "Match #$rev_id";
                $refund_count = 0;

                // Refund joined participants if entry fee was charged
                if ($e_fee > 0) {
                    $part_q = $conn->query("SELECT DISTINCT user_id FROM participants WHERE tournament_id = {$rev_id}");
                    if ($part_q) {
                        while ($p = $part_q->fetch_assoc()) {
                            $uid = (int)$p["user_id"];
                            $conn->query("UPDATE users SET wallet_balance = wallet_balance + {$e_fee} WHERE id = {$uid}");
                            $conn->query("INSERT INTO transactions (user_id, amount, type, description) VALUES ({$uid}, {$e_fee}, 'credit', 'Refund: Revoked Match " . $conn->real_escape_string($t_name) . "')");
                            $refund_count++;
                        }
                    }
                }

                // Delete records
                @$conn->query("DELETE FROM anti_cheat_logs WHERE tournament_id = {$rev_id}");
                @$conn->query("DELETE FROM participants WHERE tournament_id = {$rev_id}");
                @$conn->query("DELETE FROM user_tournaments WHERE tournament_id = {$rev_id}");
                @$conn->query("DELETE FROM tournaments WHERE id = {$rev_id}");
                $conn->commit();
                $_SESSION["active_panel"] = "worker";
                setcookie("beastz_active_panel", "worker", time() + (365 * 24 * 3600), "/");
                setcookie("active_panel", "worker", time() + (365 * 24 * 3600), "/");
                $feedback_msg = "Match #" . $rev_id . " revoked successfully! " . ($refund_count > 0 ? "Entry fee refunded to {$refund_count} players." : "");
                $feedback_type = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $feedback_msg = "Revoke failed: " . $e->getMessage();
                $feedback_type = "error";
            }
        }
    }

    // 5. INSTANT MANUAL TOURNAMENT DELETION
    if ($act === "delete_tournament") {
        $del_id = (int)($_POST["tournament_id"] ?? 0);
        if ($del_id > 0) {
            @$conn->query("DELETE FROM anti_cheat_logs WHERE tournament_id = {$del_id}");
            @$conn->query("DELETE FROM participants WHERE tournament_id = {$del_id}");
            @$conn->query("DELETE FROM user_tournaments WHERE tournament_id = {$del_id}");
            $del = $conn->prepare("DELETE FROM tournaments WHERE id = ?");
            if ($del) {
                $del->bind_param("i", $del_id);
                if ($del->execute()) {
                    $feedback_msg = "Tournament #{$del_id} successfully deleted from system.";
                    $feedback_type = "success";
                } else {
                    $feedback_msg = "Delete failed: " . $del->error;
                    $feedback_type = "error";
                }
                $del->close();
            }
        }
    }
}

// Queries for View
$active_tourneys = [];
$at_q = $conn->query("
    SELECT t.*, 
    COALESCE(t.game_name, t.game, 'Free Fire') as display_game,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count 
    FROM tournaments t 
    WHERE LOWER(status) != 'completed' 
    ORDER BY match_time ASC, id DESC
    LIMIT 60");
if ($at_q) {
    while ($r = $at_q->fetch_assoc()) $active_tourneys[] = $r;
}

$completed_tourneys = [];
$ct_q = $conn->query("
    SELECT t.*, 
    COALESCE(t.game_name, t.game, 'Free Fire') as display_game,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count,
    (SELECT u.username FROM users u WHERE u.id = t.winner_user_id) as winner_name
    FROM tournaments t 
    WHERE LOWER(status) = 'completed' AND match_time >= NOW() - INTERVAL 24 HOUR
    ORDER BY match_time DESC
    LIMIT 60");
if ($ct_q) {
    while ($r = $ct_q->fetch_assoc()) $completed_tourneys[] = $r;
}

$recent_notifications = [];
$nt_q = $conn->query("SELECT * FROM app_notifications ORDER BY created_at DESC LIMIT 15");
if ($nt_q) {
    while ($r = $nt_q->fetch_assoc()) $recent_notifications[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Worker Studio - BEASTz BATTLE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com">
    function workerUpdateSlots(mode) {
        const input = document.getElementById("workerTotalSlots");
        if (!input) return;
        const m = (mode || "").toUpperCase();
        if (m === "SOLO BR") {
            input.value = 48;
        } else if (m === "DUO BR") {
            input.value = 24;
        } else if (m === "SQUAD BR") {
            input.value = 12;
        } else if (m === "CS 1VS1" || m === "LONE WOLF 1V1") {
            input.value = 2;
        } else if (m === "CS 2VS2" || m === "LONE WOLF 2V2") {
            input.value = 2;
        } else if (m === "CS 4VS4") {
            input.value = 2;
        } else {
            input.value = 48;
        }
    }
    
</script>
    <script>tailwind.config = { darkMode: "class" };</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/theme.css">
    <script src="/theme.js"></script>
    <style>
        * { font-family: "Plus Jakarta Sans", sans-serif; }
        h1, h2, h3, h4, .font-display { font-family: "Outfit", sans-serif; }
    </style>
</head>
<body class="bg-slate-100 dark:bg-[#070b14] text-slate-900 dark:text-slate-100 min-h-screen flex flex-col items-center">

    <!-- Top Header -->
    <header class="w-full bg-white dark:bg-[#0d1424] border-b border-slate-200 dark:border-slate-800 sticky top-0 z-40 shadow-xs">
        <div class="max-w-5xl mx-auto px-3 sm:px-4 py-2.5 flex items-center justify-between gap-2 overflow-hidden">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-blue-600 text-white flex items-center justify-center font-black text-sm shadow-sm shrink-0">
                    <i class="fa-solid fa-gamepad"></i>
                </div>
                <div class="min-w-0 truncate">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="font-black text-xs sm:text-sm tracking-tight font-display text-slate-900 dark:text-white">BEASTz<span class="text-blue-500">WORKER</span></span>
                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-blue-600/15 text-blue-600 dark:text-sky-400 border border-blue-500/30">WORKER STUDIO</span>
                    </div>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 font-medium truncate">Logged in as @<?= htmlspecialchars($logged_worker_user); ?></p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <button type="button" onclick="toggleTheme()" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 cursor-pointer shrink-0">
                    <i class="fa-solid fa-moon text-xs beastz-theme-icon"></i>
                </button>
                <a href="logout.php" onclick="return confirm('Are you sure you want to log out?');" class="px-2.5 py-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 border border-rose-500/30 text-xs font-bold flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-power-off"></i>
                </a>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TOP HEADING: EXACTLY 2 MAIN TABS -->
        <!-- ============================================================ -->
        <div class="max-w-5xl mx-auto px-4 pb-2 pt-1 flex items-center gap-2 border-t border-slate-100 dark:border-slate-800/80">
            <button type="button" onclick="switchWorkerMainTab('push')" id="wmain-tab-push" class="w-main-tab flex-1 py-2.5 px-4 rounded-xl font-black text-xs transition uppercase tracking-wider flex items-center justify-center gap-2 bg-blue-600 text-white shadow-sm cursor-pointer">
                <i class="fa-solid fa-paper-plane text-sm"></i>
                <span>Push Notification Studio</span>
            </button>
            <button type="button" onclick="switchWorkerMainTab('tournaments')" id="wmain-tab-tournaments" class="w-main-tab flex-1 py-2.5 px-4 rounded-xl font-bold text-xs transition uppercase tracking-wider flex items-center justify-center gap-2 bg-slate-100 dark:bg-slate-800/60 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer">
                <i class="fa-solid fa-trophy text-sm"></i>
                <span>Tournament Management</span>
            </button>
        </div>
    </header>

    <main class="max-w-5xl mx-auto w-full p-4 space-y-4">
        <!-- Toast Feedback -->
        <?php if (!empty($feedback_msg)): ?>
            <div class="p-4 rounded-2xl text-xs font-bold flex items-center gap-3 shadow-sm <?= $feedback_type === 'success' ? 'bg-emerald-500/15 border border-emerald-500/40 text-emerald-700 dark:text-emerald-300' : 'bg-rose-500/15 border border-rose-500/40 text-rose-700 dark:text-rose-300' ?>">
                <i class="fa-solid <?= $feedback_type === 'success' ? 'fa-circle-check text-emerald-500' : 'fa-circle-exclamation text-rose-500' ?> text-base shrink-0"></i>
                <span><?= htmlspecialchars($feedback_msg); ?></span>
            </div>
        <?php endif; ?>

        <!-- ============================================================ -->
        <!-- TAB 1: PUSH NOTIFICATION STUDIO -->
        <!-- ============================================================ -->
        <section id="worker-tab-view-push" class="worker-top-view space-y-5">
            <div class="flex items-center justify-between pb-1 border-b border-slate-200 dark:border-slate-800">
                <div>
                    <h2 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-paper-plane text-blue-400"></i> Push Notification Studio
                    </h2>
                    <p class="text-xs text-slate-500">Send instant Room ID & Password alerts to match participants or broadcast announcements to all gamers</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- 1. WORKING ROOM IDP PUSH ALERT -->
                <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="fa-solid fa-key text-blue-400"></i> Match Room IDP Push Alert
                        </h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-600/20 text-sky-300 dark:text-sky-400">
                            TARGETED PLAYERS
                        </span>
                    </div>

                    <form method="POST" action="worker.php" class="space-y-3.5 text-xs">
                        <input type="hidden" name="action" value="save_and_broadcast_idp">
                        <input type="hidden" name="send_push" value="1">

                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Select Active Tournament</label>
                            <select name="tournament_id" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-900 dark:text-white">
                                <option value="">-- Choose Match --</option>
                                <?php foreach ($active_tourneys as $at): ?>
                                    <option value="<?= $at["id"]; ?>">#<?= $at["id"]; ?> - <?= htmlspecialchars($at["title"]); ?> (<?= $at["enrolled_count"]; ?> joined)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Room ID</label>
                                <input type="text" name="room_id" required placeholder="e.g. 9876543" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-mono font-bold">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Room Password</label>
                                <input type="text" name="room_password" required placeholder="e.g. 1234" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-mono font-bold">
                            </div>
                        </div>

                        <!-- Push Alert Preview Box (Mirroring Admin Panel) -->
                        <div class="p-3 bg-blue-600/10 border border-blue-500/40 rounded-xl space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-black text-blue-400">Auto Push Notification (To Joined Players Only)</span>
                                <span class="px-2 py-0.5 rounded bg-blue-600 text-white font-black text-[9px] uppercase shadow-xs">DEFAULT ACTIVE</span>
                            </div>
                            <div class="text-[11px] font-mono text-slate-700 dark:text-slate-300 bg-white/60 dark:bg-slate-900/60 p-2 rounded-lg border border-blue-500/20">
                                WAR ZONE TAYAR HAI JOIN KARLO ROOM 5 MIN MEIN START HONE WALA HAI
                            </div>
                            <p class="text-[10px] text-slate-400">&bull; Target: Only gamers who booked slot in this match</p>
                        </div>

                        <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                            <i class="fa-solid fa-paper-plane"></i>
                            <span>Save Room Credentials & Dispatch Instant IDP Push</span>
                        </button>
                    </form>
                </div>

                <!-- 2. BROADCAST ANNOUNCEMENTS PUSH -->
                <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="fa-solid fa-bullhorn text-indigo-500"></i> Broadcast Push Announcement
                        </h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-500/10 text-indigo-500">
                            ALL GAMERS
                        </span>
                    </div>

                    <form method="POST" action="worker.php" class="space-y-3.5 text-xs">
                        <input type="hidden" name="action" value="send_worker_broadcast">

                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Push Alert Title</label>
                            <input type="text" name="broadcast_title" required placeholder="e.g. 🔥 Mega Tournament Starting Soon!" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Push Alert Message</label>
                            <textarea name="broadcast_message" rows="2" required placeholder="Enter announcement body..." class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white"></textarea>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Target Tab</label>
                            <select name="target_url" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white">
                                <option value="/?tab=home">Home / Tournaments Arena</option>
                                <option value="/?tab=matches">My Matches Tab</option>
                                <option value="/?tab=wallet">Wallet Deposit / Payouts</option>
                                <option value="/?tab=leaderboard">Leaderboard / Rankings</option>
                            </select>
                        </div>

                        <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                            <i class="fa-solid fa-broadcast-tower"></i>
                            <span>Broadcast Push Alert to All Devices</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- RECENT PUSH NOTIFICATIONS TABLE -->
            <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-3">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-blue-400"></i> Recent Push Alerts & In-App Logs
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 text-[10px] text-slate-400 uppercase">
                                <th class="py-2 px-3">Title</th>
                                <th class="py-2 px-3">Message</th>
                                <th class="py-2 px-3">Type</th>
                                <th class="py-2 px-3">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php if (!empty($recent_notifications)): ?>
                                <?php foreach ($recent_notifications as $rn): ?>
                                    <tr>
                                        <td class="py-2.5 px-3 font-bold text-slate-900 dark:text-white whitespace-nowrap"><?= htmlspecialchars($rn["title"]); ?></td>
                                        <td class="py-2.5 px-3 text-slate-600 dark:text-slate-300 max-w-xs truncate"><?= htmlspecialchars($rn["message"]); ?></td>
                                        <td class="py-2.5 px-3 whitespace-nowrap"><span class="px-2 py-0.5 rounded text-[9px] font-black bg-blue-600/20 text-blue-400"><?= htmlspecialchars($rn["badge_type"]); ?></span></td>
                                        <td class="py-2.5 px-3 text-slate-400 text-[10px] whitespace-nowrap"><?= date("d M, h:i A", strtotime($rn["created_at"])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center py-4 text-slate-400 text-xs">No push notifications dispatched yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ============================================================ -->
        <!-- TAB 2: TOURNAMENT MANAGEMENT (MIRRORING ADMIN PANEL TOURNEYS UI 100%) -->
        <!-- ============================================================ -->
        <section id="worker-tab-view-tournaments" class="worker-top-view hidden space-y-5">
            
            <!-- Create Tournament Form Card (Mirroring Admin Panel Screenshot 2) -->
            <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-xs mb-6">
                <div class="flex items-center justify-between mb-3 border-b border-slate-100 dark:border-slate-800 pb-2">
                    <h3 class="text-xs font-extrabold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-plus text-blue-400 text-sm"></i> Create New Tournament
                    </h3>
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Free Fire & BGMI</span>
                </div>
                <form method="POST" action="worker.php" class="space-y-3">
                    <input type="hidden" name="action" value="create_tourney">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Tournament Title *</label>
                        <input type="text" name="title" required placeholder="e.g. FREE FIRE - SOLO HUNTER" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-blue-500 focus:bg-white dark:focus:bg-slate-900 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white font-bold focus:outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Game *</label>
                            <select name="game" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-blue-500 rounded-xl px-2.5 py-2 text-xs text-slate-900 dark:text-white font-medium focus:outline-none">
                                <option value="Free Fire">Free Fire</option>
                                <option value="Free Fire Max">Free Fire Max</option>
                                <option value="BGMI">BGMI</option>
                                <option value="Call of Duty">Call of Duty Mobile</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Match Category</label>
                            <select name="match_type" id="workerMatchType" onchange="workerUpdateSlots(this.value)" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-blue-500 rounded-xl px-2.5 py-2 text-xs text-slate-900 dark:text-white font-medium focus:outline-none">
                                <option value="SOLO BR">SOLO BR (48 Slots)</option>
                                <option value="DUO BR">DUO BR (24 Teams)</option>
                                <option value="SQUAD BR">SQUAD BR (12 Squads)</option>
                                <option value="CS 1VS1">CS 1VS1 (2 Slots)</option>
                                <option value="CS 2VS2">CS 2VS2 (2 Teams)</option>
                                <option value="CS 4VS4">CS 4VS4 (2 Squads)</option>
                                <option value="LONE WOLF 1V1">LONE WOLF 1V1 (2 Slots)</option>
                                <option value="LONE WOLF 2V2">LONE WOLF 2V2 (2 Teams)</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Map</label>
                            <select name="map" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-blue-500 rounded-xl px-2.5 py-2 text-xs text-slate-900 dark:text-white font-medium focus:outline-none">
                                <option value="BERMUDA">BERMUDA</option>
                                <option value="PURGATORY">PURGATORY</option>
                                <option value="KALAHARI">KALAHARI</option>
                                <option value="NEXTERRA">NEXTERRA</option>
                                <option value="ERANGEL">ERANGEL (BGMI)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Total Slots</label>
                            <input type="number" name="total_slots" id="workerTotalSlots" value="48" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-blue-500 rounded-xl px-2.5 py-2 text-xs text-slate-900 dark:text-white font-mono font-bold focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Visibility / Access</label>
                            <select name="is_private" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-blue-500 rounded-xl px-2.5 py-2 text-xs text-slate-900 dark:text-white font-medium focus:outline-none">
                                <option value="0">🌐 Public (Visible to All)</option>
                                <option value="1">🔒 Private (Join via Match Code)</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Entry Fee (₹)</label>
                            <input type="number" step="0.5" name="entry_fee" value="10" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-blue-500 rounded-xl px-2.5 py-2 text-xs text-slate-900 dark:text-white font-mono font-bold focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Per Kill (₹)</label>
                            <input type="number" step="0.5" name="per_kill" value="0" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-blue-500 rounded-xl px-2.5 py-2 text-xs text-slate-900 dark:text-white font-mono font-bold focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Prize Pool (₹)</label>
                            <input type="number" step="1" name="prize_pool" value="225" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-blue-500 rounded-xl px-2.5 py-2 text-xs text-blue-400 font-mono font-black focus:outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Match Schedule Time *</label>
                        <input type="datetime-local" name="match_time" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-blue-500 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white font-medium focus:outline-none">
                    </div>
                    <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-bolt text-xs"></i>
                        <span>Publish Tournament Match</span>
                    </button>
                </form>
            </div>

            <!-- TOURNAMENTS SECTION WITH SEPARATE ACTIVE & COMPLETED TABS -->
            <div class="space-y-3 mb-8">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tournament Management</h3>
                </div>

                <!-- Navigation Tabs (Active vs Completed Section) -->
                <div class="grid grid-cols-2 gap-2 p-1 bg-slate-200/80 dark:bg-slate-800 rounded-2xl text-xs font-bold">
                    <button type="button" onclick="switchWorkerSubTab('active', this)" id="workerBtnTourneyActive" class="worker-subtab-btn py-2 text-center rounded-xl transition bg-blue-600 text-white font-black shadow-xs cursor-pointer">
                        <i class="fa-solid fa-gamepad mr-1"></i> Active & Live (<?= count($active_tourneys); ?>)
                    </button>
                    <button type="button" onclick="switchWorkerSubTab('completed', this)" id="workerBtnTourneyCompleted" class="worker-subtab-btn py-2 text-center rounded-xl transition text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold cursor-pointer">
                        <i class="fa-solid fa-clock-rotate-left mr-1"></i> Completed Matches (<?= count($completed_tourneys); ?>)
                    </button>
                </div>

                <!-- CONTAINER 1: ACTIVE & LIVE TOURNAMENTS -->
                <div id="workerTourneysActive" class="worker-tourney-pane space-y-3">
                    <?php if (!empty($active_tourneys)): ?>
                        <?php foreach ($active_tourneys as $t): ?>
                            <?php
                                $status_clean = strtolower($t["status"] ?? "upcoming");
                                $is_live = ($status_clean === "live" || $status_clean === "ongoing");
                                $slots = (int)($t["total_slots"] ?: 48);
                                $enrolled = (int)$t["enrolled_count"];
                            ?>
                            <div class="bg-white dark:bg-[#111827] border <?= $is_live ? "border-emerald-400 ring-2 ring-emerald-400/20" : "border-slate-200 dark:border-slate-800" ?> rounded-2xl p-4 shadow-sm hover:shadow transition space-y-2.5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="flex items-center gap-1.5 mb-1">
                                            <span class="text-[9px] font-bold px-2 py-0.5 rounded uppercase <?= $is_live ? "bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 font-black" : "bg-blue-950/60 text-sky-200 dark:bg-yellow-950 dark:text-yellow-300 font-bold" ?>">
                                                <?= htmlspecialchars($t["status"]); ?>
                                            </span>
                                            <span class="text-[9px] text-slate-500 font-bold uppercase bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">
                                                <?= htmlspecialchars($t["match_type"]); ?>
                                            </span>
                                        </div>
                                        <h4 class="text-xs font-black text-slate-900 dark:text-white leading-tight">
                                            <?= htmlspecialchars($t["title"]); ?>
                                        </h4>
                                        <?php if (!empty($t["is_private"])): ?>
                                            <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                                <span class="text-[9px] font-black px-1.5 py-0.2 rounded uppercase bg-purple-500/20 text-purple-300 border border-purple-500/30">
                                                    🔒 PRIVATE
                                                </span>
                                                <span class="text-[10px] font-mono font-bold text-sky-400">
                                                    Code: <?= htmlspecialchars($t["access_code"] ?? ""); ?>
                                                </span>
                                                <button type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($t['access_code'] ?? ''); ?>'); alert('Copied code: <?= htmlspecialchars($t['access_code'] ?? ''); ?>');" class="px-1.5 py-0.2 rounded bg-blue-600 text-white text-[9px] font-bold">
                                                    Copy
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                        <p class="text-[10px] text-slate-400 font-mono mt-0.5">
                                            <?= date("d M Y, h:i A", strtotime($t["match_time"])); ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-[10px] text-slate-400 block font-bold">Prize Pool</span>
                                        <span class="text-xs font-black text-blue-400 font-mono"><?= format_compact_inr($t["prize_pool"]); ?></span>
                                    </div>
                                </div>

                                <div class="bg-slate-50 dark:bg-slate-900/60 p-2.5 rounded-xl border border-slate-200/60 dark:border-slate-800 text-xs flex justify-between">
                                    <div>
                                        <span class="text-slate-400 text-[10px] block">Entry Fee</span>
                                        <span class="font-extrabold text-slate-900 dark:text-white"><?= format_inr($t["entry_fee"]); ?></span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 text-[10px] block">Slots Joined</span>
                                        <span class="font-bold text-slate-800 dark:text-slate-200"><?= $enrolled; ?> / <?= $slots; ?></span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-slate-400 text-[10px] block">Credentials</span>
                                        <span class="font-mono text-[11px] <?= !empty($t["room_id"]) ? "text-emerald-600 font-bold" : "text-slate-400" ?>">
                                            <?= !empty($t["room_id"]) ? "ID: " . htmlspecialchars($t["room_id"]) : "Not Added"; ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex items-center gap-2 pt-1 text-xs">
                                    <a href="worker_manage.php?id=<?= $t["id"]; ?>" class="flex-1 py-2 bg-blue-600 hover:bg-blue-500 text-white text-center font-black rounded-xl text-xs shadow-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                                        <i class="fa-solid fa-gear text-[10px]"></i> Manage & Results
                                    </a>
                                    <form method="POST" action="worker.php" onsubmit="return confirm('Revoke Match #<?= $t["id"]; ?> and refund entry fees to all joined players?');" class="shrink-0">
                                        <input type="hidden" name="action" value="revoke_tournament">
                                        <input type="hidden" name="tournament_id" value="<?= $t["id"]; ?>">
                                        <button type="submit" class="px-3 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold rounded-xl text-xs transition" title="Revoke Match with Refunds">
                                            <i class="fa-solid fa-ban mr-1"></i> Revoke
                                        </button>
                                    </form>
                                    <form method="POST" action="worker.php" onsubmit="return confirm('Instant Delete Match #<?= $t["id"]; ?>? This permanently removes the match.');" class="shrink-0">
                                        <input type="hidden" name="action" value="delete_tournament">
                                        <input type="hidden" name="tournament_id" value="<?= $t["id"]; ?>">
                                        <button type="submit" class="p-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs border border-rose-200 transition" title="Delete Match">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="bg-white dark:bg-[#111827] rounded-2xl border border-slate-200 dark:border-slate-800 p-8 text-center text-slate-400 text-xs">
                            <i class="fa-solid fa-gamepad text-2xl text-slate-300 dark:text-slate-600 mb-2 block"></i>
                            No active or live tournaments right now. Create one above!
                        </div>
                    <?php endif; ?>
                </div>

                <!-- CONTAINER 2: COMPLETED TOURNAMENTS (24-HR HISTORY) -->
                <div id="workerTourneysCompleted" class="worker-tourney-pane hidden space-y-3">
                    <?php if (!empty($completed_tourneys)): ?>
                        <?php foreach ($completed_tourneys as $ct): ?>
                            <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs space-y-2.5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="flex items-center gap-1.5 mb-1">
                                            <span class="text-[9px] font-black px-2 py-0.5 rounded uppercase bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                                COMPLETED
                                            </span>
                                            <span class="text-[9px] text-slate-500 font-bold uppercase bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">
                                                <?= htmlspecialchars($ct["match_type"]); ?>
                                            </span>
                                        </div>
                                        <h4 class="text-xs font-black text-slate-900 dark:text-white leading-tight">
                                            <?= htmlspecialchars($ct["title"]); ?>
                                        </h4>
                                        <p class="text-[10px] text-slate-400 font-mono mt-0.5">
                                            <?= date("d M Y, h:i A", strtotime($ct["match_time"])); ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-[10px] text-slate-400 block font-bold">Prize Disbursed</span>
                                        <span class="text-xs font-black text-emerald-500 font-mono"><?= format_compact_inr($ct["prize_pool"]); ?></span>
                                    </div>
                                </div>

                                <div class="bg-slate-50 dark:bg-slate-900/60 p-2.5 rounded-xl border border-slate-200/60 dark:border-slate-800 text-xs flex justify-between items-center">
                                    <div>
                                        <span class="text-slate-400 text-[10px] block">Total Players</span>
                                        <span class="font-bold text-slate-800 dark:text-slate-200"><?= $ct["enrolled_count"]; ?> Gamers</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 text-[10px] block">Top Winner</span>
                                        <span class="font-bold text-blue-400"><?= !empty($ct["winner_name"]) ? "@" . htmlspecialchars($ct["winner_name"]) : "Results Paid"; ?></span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-slate-400 text-[10px] block">Status</span>
                                        <span class="text-emerald-500 font-bold">Prizes Distributed</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 pt-1 text-xs">
                                    <a href="worker_manage.php?id=<?= $ct["id"]; ?>" class="flex-1 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold rounded-xl text-center transition">
                                        <i class="fa-solid fa-eye mr-1"></i> View Result Rankings
                                    </a>
                                    <form method="POST" action="worker.php" onsubmit="return confirm('Delete this completed tournament archive?');" class="shrink-0">
                                        <input type="hidden" name="action" value="delete_tournament">
                                        <input type="hidden" name="tournament_id" value="<?= $ct["id"]; ?>">
                                        <button type="submit" class="p-2 bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 border border-rose-500/30 rounded-xl" title="Instant Delete Archive">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="bg-white dark:bg-[#111827] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 text-center text-slate-400 text-xs">
                            No matches completed in the last 24 hours.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <!-- 0ms 2-Tab Switcher Script & Sub-tab Switcher -->
    <script>
    function switchWorkerMainTab(tab) {
        var pushView = document.getElementById("worker-tab-view-push");
        var tourneysView = document.getElementById("worker-tab-view-tournaments");
        var btnPush = document.getElementById("wmain-tab-push");
        var btnTourneys = document.getElementById("wmain-tab-tournaments");

        var actCls = ["bg-blue-600", "text-white", "shadow-sm", "font-black"];
        var inactCls = ["bg-slate-100", "dark:bg-slate-800/60", "text-slate-600", "dark:text-slate-400", "font-bold"];

        if (tab === "push") {
            pushView.classList.remove("hidden");
            tourneysView.classList.add("hidden");
            
            inactCls.forEach(function(c) { btnPush.classList.remove(c); });
            actCls.forEach(function(c) { btnPush.classList.add(c); });
            actCls.forEach(function(c) { btnTourneys.classList.remove(c); });
            inactCls.forEach(function(c) { btnTourneys.classList.add(c); });
        } else {
            pushView.classList.add("hidden");
            tourneysView.classList.remove("hidden");

            actCls.forEach(function(c) { btnPush.classList.remove(c); });
            inactCls.forEach(function(c) { btnPush.classList.add(c); });
            inactCls.forEach(function(c) { btnTourneys.classList.remove(c); });
            actCls.forEach(function(c) { btnTourneys.classList.add(c); });
        }
        try { localStorage.setItem("beastz_worker_tab", tab); } catch(e) {}
        if (window.initCustomSelects) window.initCustomSelects();
    }

    function switchWorkerSubTab(sub, btn) {
        document.querySelectorAll(".worker-tourney-pane").forEach(function(el) { el.classList.add("hidden"); });
        document.querySelectorAll(".worker-subtab-btn").forEach(function(b) {
            b.classList.remove("bg-blue-600", "text-slate-950", "font-black", "shadow-xs");
            b.classList.add("text-slate-600", "dark:text-slate-400", "font-bold");
        });
        if (sub === "active") {
            document.getElementById("workerTourneysActive").classList.remove("hidden");
        } else {
            document.getElementById("workerTourneysCompleted").classList.remove("hidden");
        }
        btn.classList.remove("text-slate-600", "dark:text-slate-400", "font-bold");
        btn.classList.add("bg-blue-600", "text-slate-950", "font-black", "shadow-xs");
        if (window.initCustomSelects) window.initCustomSelects();
    }

    (function() {
        var savedTab = "push";
        try { savedTab = localStorage.getItem("beastz_worker_tab") || "push"; } catch(e) {}
        switchWorkerMainTab(savedTab);
        if (window.initCustomSelects) window.initCustomSelects();
    })();
    </script>
</body>
</html>
