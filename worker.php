<?php
session_start();
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

    // 1. ADD NEW TOURNAMENT
    if ($act === "create_tourney") {
        $title = sanitize($_POST["title"] ?? "");
        $game = sanitize($_POST["game"] ?? "BGMI");
        $map = sanitize($_POST["map"] ?? "Erangel");
        $match_type = sanitize($_POST["match_type"] ?? "Solo");
        $entry_fee = (float)($_POST["entry_fee"] ?? 0.00);
        $per_kill = (float)($_POST["per_kill"] ?? 0.00);
        $prize_pool = (float)($_POST["prize_pool"] ?? 0.00);
        $match_time = sanitize($_POST["match_time"] ?? "");
        $total_slots = (int)($_POST["total_slots"] ?? 100);

        if (empty($title) || empty($match_time)) {
            $feedback_msg = "Please provide match title and scheduled date & time.";
            $feedback_type = "error";
        } else {
            $stmt = $conn->prepare("INSERT INTO tournaments (title, game, map, match_type, entry_fee, per_kill, prize_pool, match_time, total_slots, joined_slots, status, hosted_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'upcoming', ?)");
            if ($stmt) {
                $stmt->bind_param("ssssdddsii", $title, $game, $map, $match_type, $entry_fee, $per_kill, $prize_pool, $match_time, $total_slots, $logged_worker_id);
                if ($stmt->execute()) {
                    $new_id = $stmt->insert_id;
                    $stmt->close();
                    $feedback_msg = "✅ Tournament #" . $new_id . " ('" . htmlspecialchars($title) . "') created successfully!";
                    $feedback_type = "success";
                } else {
                    $feedback_msg = "Failed to add tournament: " . $conn->error;
                    $feedback_type = "error";
                }
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
                            if (!empty($p["player_id"])) $player_ids[] = $p["player_id"];
                            $user_ids[] = $p["user_id"];
                        }
                    }

                    $n_title = "🔥 WAR ZONE TAYAR HAI!";
                    $n_body = "WAR ZONE TAYAR HAI JOIN KARLO ROOM 5 MIN MEIN START HONE WALA HAI! Room ID: " . $room_id . " | Pass: " . $room_pass;
                    send_onesignal_push($n_title, $n_body, "/?tab=matches", $player_ids, $user_ids);
                    
                    $conn->query("INSERT INTO app_notifications (title, message, target_url, badge_type) VALUES ('" . $conn->real_escape_string($n_title) . "', '" . $conn->real_escape_string($n_body) . "', '/?tab=matches', 'ROOM_IDP')");
                    $feedback_msg = "🚀 Room IDP saved and instant push alert dispatched to joined players!";
                    $feedback_type = "success";
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
            send_onesignal_push($b_title, $b_message, $target_url);
            $conn->query("INSERT INTO app_notifications (title, message, target_url, badge_type) VALUES ('" . $conn->real_escape_string($b_title) . "', '" . $conn->real_escape_string($b_message) . "', '" . $conn->real_escape_string($target_url) . "', 'HOST_ANNOUNCEMENT')");
            $feedback_msg = "✅ Push notification announcement broadcasted to all gamers!";
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
                            $conn->query("INSERT INTO transactions (user_id, amount, type, description) VALUES ({$uid}, {$e_fee}, 'credit', 'Refund: Revoked Match '" . $conn->real_escape_string($t_name) . "'')");
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

                $feedback_msg = "Match #" . $rev_id . " revoked successfully! " . ($refund_count > 0 ? "Entry fee refunded to {$refund_count} players." : "");
                $feedback_type = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $feedback_msg = "Revoke failed: " . $e->getMessage();
                $feedback_type = "error";
            }
        }
    }

    // 5. SUBMIT KILLS / RESULTS & DECLARE WINNERS (WITH PUSH NOTIFICATION)
    if ($act === "submit_results_and_declare") {
        $tid = (int)($_POST["tournament_id"] ?? 0);
        $kills_arr = $_POST["kills"] ?? [];
        $rank_arr = $_POST["rank_no"] ?? [];
        $bonus_arr = $_POST["bonus"] ?? [];

        if ($tid > 0) {
            $conn->begin_transaction();
            try {
                $t_stmt = $conn->query("SELECT title, per_kill, prize_pool FROM tournaments WHERE id = {$tid}")->fetch_assoc();
                $per_kill_rate = (float)($t_stmt["per_kill"] ?? 0.00);
                $t_title = $t_stmt["title"] ?? "Match #$tid";
                $total_distributed = 0.00;
                $winner_u_id = null;
                $participating_uids = [];
                $participating_pids = [];

                $pq = $conn->query("SELECT p.id, p.user_id, u.player_id FROM participants p JOIN users u ON p.user_id = u.id WHERE p.tournament_id = {$tid}");
                if ($pq) {
                    while ($p = $pq->fetch_assoc()) {
                        $pid = (int)$p["id"];
                        $p_uid = (int)$p["user_id"];
                        $participating_uids[] = $p_uid;
                        if (!empty($p["player_id"])) $participating_pids[] = $p["player_id"];

                        $kills = isset($kills_arr[$pid]) ? max(0, (int)$kills_arr[$pid]) : 0;
                        $rank_no = isset($rank_arr[$pid]) ? max(0, (int)$rank_arr[$pid]) : 0;
                        $bonus = isset($bonus_arr[$pid]) ? max(0.0, (float)$bonus_arr[$pid]) : 0.00;

                        if ($rank_no === 1) {
                            $winner_u_id = $p_uid;
                        }

                        $earned = ($kills * $per_kill_rate) + $bonus;
                        $conn->query("UPDATE participants SET kills = {$kills}, rank_no = {$rank_no}, prize_won = {$earned} WHERE id = {$pid}");

                        if ($earned > 0) {
                            $conn->query("UPDATE users SET wallet_balance = wallet_balance + {$earned}, winning_balance = winning_balance + {$earned} WHERE id = {$p_uid}");
                            $desc = "Match Winnings: " . $t_title . " ({$kills} Kills @ " . format_inr($per_kill_rate) . ($rank_no > 0 ? " + Rank #{$rank_no}" : "") . ")";
                            $conn->query("INSERT INTO transactions (user_id, amount, type, description) VALUES ({$p_uid}, {$earned}, 'credit', '" . $conn->real_escape_string($desc) . "')");
                            $total_distributed += $earned;
                        }
                    }
                }

                if ($winner_u_id) {
                    $conn->query("UPDATE tournaments SET status = 'completed', winner_user_id = {$winner_u_id} WHERE id = {$tid}");
                } else {
                    $conn->query("UPDATE tournaments SET status = 'completed' WHERE id = {$tid}");
                }

                $conn->commit();

                // TRIGGER CRITICAL PUSH NOTIFICATION
                $results_title = "Badhayi ho aap jeet gaye hain 🎉 par kitne? Khud aake dekh lo 🏆";
                $results_msg = "Badhayi ho aap jeet gaye hain 🎉 par kitne? Khud aake dekh lo 🏆";
                send_onesignal_push($results_title, $results_msg, "/?tab=matches", $participating_pids, $participating_uids);
                $conn->query("INSERT INTO app_notifications (title, message, target_url, badge_type) VALUES ('" . $conn->real_escape_string($results_title) . "', '" . $conn->real_escape_string($results_msg) . "', '/?tab=matches', 'RESULTS_ANNOUNCED')");

                $feedback_msg = "🏆 Results published! Total " . format_inr($total_distributed) . " credited to gamer wallets and push notification dispatched!";
                $feedback_type = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $feedback_msg = "Result publication error: " . $e->getMessage();
                $feedback_type = "error";
            }
        }
    }

    // 6. INSTANT MANUAL TOURNAMENT DELETION
    if ($act === "delete_tournament") {
        $del_id = (int)($_POST["tournament_id"] ?? 0);
        if ($del_id > 0) {
            @$conn->query("DELETE FROM anti_cheat_logs WHERE tournament_id = {$del_id}");
            @$conn->query("DELETE FROM participants WHERE tournament_id = {$del_id}");
            @$conn->query("DELETE FROM user_tournaments WHERE tournament_id = {$del_id}");
            @$conn->query("DELETE FROM tournaments WHERE id = {$del_id}");
            $feedback_msg = "Tournament #" . $del_id . " deleted permanently from system!";
            $feedback_type = "success";
        }
    }

    // 7. ANTI-CHEAT REVOKE (CLAWBACK)
    if ($act === "anti_cheat_revoke") {
        $suspect_user = trim(sanitize($_POST["suspect_user"] ?? ""));
        $match_id = (int)($_POST["match_id"] ?? 0);
        $revoke_amount = (float)($_POST["revoke_amount"] ?? 0.00);
        $reason = sanitize($_POST["reason"] ?? "Anti-Cheat Infraction / Illegal Gameplay");

        if (!empty($suspect_user) && $revoke_amount > 0) {
            $u_q = $conn->prepare("SELECT id, username FROM users WHERE username = ? OR id = ? LIMIT 1");
            $u_q->bind_param("ss", $suspect_user, $suspect_user);
            $u_q->execute();
            $target_u = $u_q->get_result()->fetch_assoc();
            $u_q->close();

            if ($target_u) {
                $t_uid = (int)$target_u["id"];
                $conn->query("UPDATE users SET wallet_balance = GREATEST(0, wallet_balance - {$revoke_amount}), winning_balance = GREATEST(0, winning_balance - {$revoke_amount}) WHERE id = {$t_uid}");
                $conn->query("INSERT INTO transactions (user_id, amount, type, description) VALUES ({$t_uid}, {$revoke_amount}, 'debit', 'Anti-Cheat Fund Clawback (Match #{$match_id}): " . $conn->real_escape_string($reason) . "')");
                $conn->query("INSERT INTO anti_cheat_logs (tournament_id, user_id, amount, reason, admin_username) VALUES ({$match_id}, {$t_uid}, {$revoke_amount}, '" . $conn->real_escape_string($reason) . "', '" . $conn->real_escape_string($logged_worker_user) . "')");
                $feedback_msg = "🛡️ Clawback executed: ₹" . number_format($revoke_amount, 2) . " revoked from @" . htmlspecialchars($target_u["username"]);
                $feedback_type = "success";
            }
        }
    }
}

// Queries for View
$active_tourneys = [];
$at_q = $conn->query("
    SELECT t.*, (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count 
    FROM tournaments t 
    WHERE LOWER(status) != 'completed' 
    ORDER BY match_time ASC, id DESC
");
if ($at_q) {
    while ($r = $at_q->fetch_assoc()) $active_tourneys[] = $r;
}

$completed_tourneys = [];
$ct_q = $conn->query("
    SELECT t.*, (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count,
    (SELECT u.username FROM users u WHERE u.id = t.winner_user_id) as winner_name
    FROM tournaments t 
    WHERE LOWER(status) = 'completed' AND match_time >= NOW() - INTERVAL 24 HOUR
    ORDER BY match_time DESC
");
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
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: "class" };</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/theme.css">
    <style>
        * { font-family: "Plus Jakarta Sans", sans-serif; }
        h1, h2, h3, h4, .font-display { font-family: "Outfit", sans-serif; }
    </style>
</head>
<body class="bg-slate-100 dark:bg-[#070b14] text-slate-900 dark:text-slate-100 min-h-screen flex flex-col items-center">
    <!-- Top Header -->
    <header class="w-full bg-white dark:bg-[#0d1424] border-b border-slate-200 dark:border-slate-800 sticky top-0 z-40 shadow-xs">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-400 text-slate-950 flex items-center justify-center font-black text-xl shadow-sm">
                    <i class="fa-solid fa-gamepad"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-black text-sm tracking-tight font-display">BEASTz<span class="text-amber-500">WORKER</span></span>
                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-amber-400/20 text-amber-500 border border-amber-400/40">HOST STUDIO</span>
                    </div>
                    <p class="text-[11px] text-slate-400 font-medium">Logged in as @<?= htmlspecialchars($logged_worker_user); ?></p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="toggleTheme()" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300">
                    <i class="fa-solid fa-moon text-xs beastz-theme-icon"></i>
                </button>
                <?php if ($is_master): ?>
                <a href="admin/index.php" class="px-3 py-1.5 rounded-xl bg-yellow-400 text-slate-950 text-xs font-bold flex items-center gap-1 shadow-xs">
                    <i class="fa-solid fa-shield"></i> <span class="hidden sm:inline">Admin Panel</span>
                </a>
                <?php endif; ?>
                <a href="index.php" target="_blank" class="px-3 py-1.5 rounded-xl bg-slate-200 dark:bg-slate-800 text-xs font-bold flex items-center gap-1">
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i> <span class="hidden sm:inline">User App</span>
                </a>
                <a href="logout.php" class="px-3 py-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 border border-rose-500/30 text-xs font-bold">
                    <i class="fa-solid fa-power-off"></i>
                </a>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TOP HEADING: EXACTLY 2 MAIN TABS -->
        <!-- ============================================================ -->
        <div class="max-w-5xl mx-auto px-4 pb-2 pt-1 flex items-center gap-2 border-t border-slate-100 dark:border-slate-800/80">
            <button type="button" onclick="switchWorkerMainTab('push')" id="wmain-tab-push" class="w-main-tab flex-1 py-2.5 px-4 rounded-xl font-black text-xs transition uppercase tracking-wider flex items-center justify-center gap-2 bg-amber-400 text-slate-950 shadow-sm cursor-pointer">
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
                    <h2 class="text-base font-extrabold flex items-center gap-2">
                        <i class="fa-solid fa-paper-plane text-amber-500"></i> Push Notification Studio
                    </h2>
                    <p class="text-xs text-slate-500">Send instant Room ID & Password alerts to match participants or broadcast announcements to all gamers</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- 1. WORKING ROOM IDP PUSH ALERT -->
                <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="fa-solid fa-key text-amber-500"></i> Match Room IDP Push Alert
                        </h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-400/20 text-amber-600 dark:text-amber-400">
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
                        <button type="submit" class="w-full py-2.5 bg-amber-400 hover:bg-amber-500 text-slate-950 font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                            <i class="fa-solid fa-paper-plane"></i>
                            <span>Save Room & Dispatch Instant IDP Push</span>
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
                            <input type="text" name="broadcast_title" required placeholder="e.g. 🔥 Mega BGMI Tournament Starting Soon!" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white">
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
                    <i class="fa-solid fa-clock-rotate-left text-amber-500"></i> Recent Push Alerts & In-App Logs
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
                                        <td class="py-2.5 px-3 whitespace-nowrap"><span class="px-2 py-0.5 rounded text-[9px] font-black bg-amber-400/20 text-amber-500"><?= htmlspecialchars($rn["badge_type"]); ?></span></td>
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
        <!-- TAB 2: TOURNAMENT MANAGEMENT -->
        <!-- ============================================================ -->
        <section id="worker-tab-view-tournaments" class="worker-top-view hidden space-y-5">
            <div class="flex items-center justify-between pb-1 border-b border-slate-200 dark:border-slate-800">
                <div>
                    <h2 class="text-base font-extrabold flex items-center gap-2">
                        <i class="fa-solid fa-trophy text-amber-500"></i> Tournament Management
                    </h2>
                    <p class="text-xs text-slate-500">Create matches, update room IDP, declare kills/results, revoke matches with refunds, and delete instantly</p>
                </div>
                <button type="button" onclick="document.getElementById('createTourneyCard').classList.toggle('hidden')" class="px-3.5 py-2 bg-amber-400 hover:bg-amber-500 text-slate-950 font-black rounded-xl text-xs flex items-center gap-1.5 shadow-sm active:scale-95 transition cursor-pointer">
                    <i class="fa-solid fa-plus"></i>
                    <span>Create New Match</span>
                </button>
            </div>

            <!-- COLLAPSIBLE CREATE TOURNAMENT CARD -->
            <div id="createTourneyCard" class="bg-white dark:bg-[#111827] border-2 border-amber-400/40 rounded-2xl p-5 shadow-md space-y-4 hidden">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-wand-magic-sparkles text-amber-500"></i> Create Tournament
                    </h3>
                    <button type="button" onclick="document.getElementById('createTourneyCard').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <form method="POST" action="worker.php" class="space-y-3.5 text-xs">
                    <input type="hidden" name="action" value="create_tourney">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Match Title *</label>
                            <input type="text" name="title" required placeholder="e.g. BGMI Squad War #101" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2 text-xs font-bold text-slate-900 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Game</label>
                            <select name="game" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold">
                                <option value="BGMI">BGMI (Battlegrounds Mobile India)</option>
                                <option value="Free Fire">Free Fire Max</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Map</label>
                            <select name="map" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold">
                                <option value="Erangel">Erangel</option>
                                <option value="Livik">Livik</option>
                                <option value="Miramar">Miramar</option>
                                <option value="Sanhok">Sanhok</option>
                                <option value="Bermuda">Bermuda (Free Fire)</option>
                                <option value="Purgatory">Purgatory</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Match Type</label>
                            <select name="match_type" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold">
                                <option value="Solo">Solo</option>
                                <option value="Duo">Duo</option>
                                <option value="Squad" selected>Squad</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Entry Fee (₹)</label>
                            <input type="number" step="1" name="entry_fee" value="20" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-mono font-bold">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Per Kill Prize (₹)</label>
                            <input type="number" step="1" name="per_kill" value="10" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-mono font-bold">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Total Prize Pool (₹)</label>
                            <input type="number" step="1" name="prize_pool" value="500" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-mono font-bold">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Match Date & Time *</label>
                            <input type="datetime-local" name="match_time" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-mono font-bold">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Total Slots</label>
                            <input type="number" name="total_slots" value="100" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-mono font-bold">
                        </div>
                    </div>
                    <button type="submit" class="w-full py-3 bg-amber-400 hover:bg-amber-500 text-slate-950 font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-plus"></i> Publish Tournament
                    </button>
                </form>
            </div>

            <!-- ACTIVE & LIVE TOURNAMENTS LIST -->
            <div class="space-y-3">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-fire text-amber-500"></i> Active & Live Tournaments (<?= count($active_tourneys); ?>)
                </h3>

                <?php if (!empty($active_tourneys)): ?>
                    <?php foreach ($active_tourneys as $t): ?>
                        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-amber-400 text-slate-950">#<?= $t["id"]; ?> <?= htmlspecialchars($t["game"]); ?></span>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300"><?= htmlspecialchars($t["map"]); ?> • <?= htmlspecialchars($t["match_type"]); ?></span>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold <?= $t["status"] === "ongoing" ? "bg-emerald-500/20 text-emerald-500" : "bg-sky-500/20 text-sky-500"; ?>"><?= strtoupper($t["status"]); ?></span>
                                    </div>
                                    <h4 class="text-sm font-black text-slate-900 dark:text-white"><?= htmlspecialchars($t["title"]); ?></h4>
                                    <p class="text-[11px] text-slate-400 font-mono mt-0.5"><i class="fa-regular fa-clock mr-1"></i><?= date("d M Y, h:i A", strtotime($t["match_time"])); ?></p>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] text-slate-400 block font-bold">Prize / Per Kill</span>
                                    <span class="text-xs font-black text-amber-500 font-mono"><?= format_compact_inr($t["prize_pool"]); ?> / ₹<?= (int)$t["per_kill"]; ?></span>
                                </div>
                            </div>

                            <div class="bg-slate-50 dark:bg-slate-900/60 p-2.5 rounded-xl border border-slate-200/60 dark:border-slate-800 text-xs flex justify-between items-center">
                                <div>
                                    <span class="text-slate-400 text-[10px] block">Joined Gamers</span>
                                    <span class="font-bold text-slate-800 dark:text-slate-200"><?= $t["enrolled_count"]; ?> / <?= $t["total_slots"]; ?> Slots</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 text-[10px] block">Room ID & Pass</span>
                                    <span class="font-mono font-bold text-slate-800 dark:text-slate-200">
                                        <?= !empty($t["room_id"]) ? htmlspecialchars($t["room_id"]) . " / " . htmlspecialchars($t["room_password"]) : "Not Added"; ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="text-slate-400 text-[10px] block">Entry Fee</span>
                                    <span class="font-bold text-amber-500">₹<?= (int)$t["entry_fee"]; ?></span>
                                </div>
                            </div>

                            <!-- ACTIONS ROW: QUICK IDP, RESULTS/KILLS, REVOKE (REFUND), DELETE -->
                            <div class="flex flex-wrap items-center gap-2 pt-1 text-xs">
                                <!-- Enter Kills & Declare Results -->
                                <a href="admin/manage_tournament.php?id=<?= $t["id"]; ?>" class="flex-1 py-2 bg-amber-400 hover:bg-amber-500 text-slate-950 font-black rounded-xl text-center shadow-xs transition active:scale-95">
                                    <i class="fa-solid fa-list-check mr-1"></i> Enter Kills & Declare Results
                                </a>

                                <!-- Revoke / Cancel Match (with Refunds) -->
                                <form method="POST" action="worker.php" onsubmit="return confirm('Revoke Match #<?= $t["id"]; ?>? This will cancel the match and automatically refund entry fees to all joined players.');" class="shrink-0">
                                    <input type="hidden" name="action" value="revoke_tournament">
                                    <input type="hidden" name="tournament_id" value="<?= $t["id"]; ?>">
                                    <button type="submit" class="px-3 py-2 bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold rounded-xl text-xs transition" title="Revoke Match with Refunds">
                                        <i class="fa-solid fa-ban mr-1"></i> Revoke
                                    </button>
                                </form>

                                <!-- Instant Manual Delete Button -->
                                <form method="POST" action="worker.php" onsubmit="return confirm('Instant Delete Match #<?= $t["id"]; ?>? This permanently removes the match.');" class="shrink-0">
                                    <input type="hidden" name="action" value="delete_tournament">
                                    <input type="hidden" name="tournament_id" value="<?= $t["id"]; ?>">
                                    <button type="submit" class="px-3 py-2 bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 border border-rose-500/30 font-bold rounded-xl text-xs transition" title="Instant Manual Delete">
                                        <i class="fa-solid fa-trash-can mr-1"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-white dark:bg-[#111827] rounded-2xl border border-slate-200 dark:border-slate-800 p-8 text-center text-slate-400 text-xs">
                        <i class="fa-solid fa-gamepad text-2xl mb-2 block"></i>
                        No active tournaments right now. Click "Create New Match" to publish one!
                    </div>
                <?php endif; ?>
            </div>

            <!-- 24-HOUR COMPLETED MATCHES HISTORY -->
            <div class="space-y-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-amber-500"></i> 24-Hour Completed Matches History (<?= count($completed_tourneys); ?>)
                    </h3>
                    <span class="text-[10px] text-slate-400">Auto 24-hr log archive</span>
                </div>

                <?php if (!empty($completed_tourneys)): ?>
                    <?php foreach ($completed_tourneys as $ct): ?>
                        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs space-y-3">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300">COMPLETED</span>
                                        <span class="text-xs font-black text-slate-900 dark:text-white"><?= htmlspecialchars($ct["title"]); ?></span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-mono"><?= date("d M Y, h:i A", strtotime($ct["match_time"])); ?></p>
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
                                    <span class="font-bold text-amber-500"><?= !empty($ct["winner_name"]) ? "@" . htmlspecialchars($ct["winner_name"]) : "Results Paid"; ?></span>
                                </div>
                                <div class="text-right">
                                    <span class="text-slate-400 text-[10px] block">Status</span>
                                    <span class="text-emerald-500 font-bold">Prizes Distributed</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 pt-1 text-xs">
                                <a href="admin/manage_tournament.php?id=<?= $ct["id"]; ?>" class="flex-1 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold rounded-xl text-center transition">
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
        </section>
    </main>

    <!-- 0ms 2-Tab Switcher Script -->
    <script>
    function switchWorkerMainTab(tab) {
        var pushView = document.getElementById("worker-tab-view-push");
        var tourneysView = document.getElementById("worker-tab-view-tournaments");
        var btnPush = document.getElementById("wmain-tab-push");
        var btnTourneys = document.getElementById("wmain-tab-tournaments");

        var actCls = ["bg-amber-400", "text-slate-950", "shadow-sm", "font-black"];
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
    }

    (function() {
        var savedTab = "push";
        try { savedTab = localStorage.getItem("beastz_worker_tab") || "push"; } catch(e) {}
        switchWorkerMainTab(savedTab);
    })();
    </script>
</body>
</html>
